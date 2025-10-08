<?php
namespace MagicianNews;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Subscription as StripeSubscription;

class Subscription {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    }

    public function isSubscribed(int $userId): bool {
        $user = $this->db->fetchOne(
            "SELECT subscription_status, subscription_end_date FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return false;
        }

        if ($user['subscription_status'] !== 'active') {
            return false;
        }

        // Check if subscription hasn't expired
        if ($user['subscription_end_date']) {
            $endDate = new \DateTime($user['subscription_end_date']);
            $now = new \DateTime();

            if ($now > $endDate) {
                // Update status to expired
                $this->updateSubscriptionStatus($userId, 'expired');
                return false;
            }
        }

        return true;
    }

    public function createCustomerPortalSession(int $userId): string {
        $user = $this->db->fetchOne(
            "SELECT stripe_customer_id FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user || !$user['stripe_customer_id']) {
            throw new \Exception("No Stripe customer found for this user");
        }

        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $user['stripe_customer_id'],
            'return_url' => $_ENV['APP_URL'] . '/profile',
        ]);

        return $session->url;
    }

    public function createCheckoutSession(int $userId, string $email): string {
        $session = Session::create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $_ENV['STRIPE_PRICE_ID'],
                'quantity' => 1,
            ]],
            'success_url' => $_ENV['APP_URL'] . '/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $_ENV['APP_URL'] . '/subscription/cancel',
            'customer_email' => $email,
            'client_reference_id' => (string)$userId,
            'metadata' => [
                'user_id' => $userId
            ],
            // Enable automatic tax calculation
            'automatic_tax' => [
                'enabled' => true
            ],
            // Collect tax IDs (VAT numbers for EU B2B)
            'tax_id_collection' => [
                'enabled' => true
            ],
            // Collect billing address for invoicing
            'billing_address_collection' => 'required',
            // Allow promotional codes
            'allow_promotion_codes' => true
        ]);

        return $session->url;
    }

    public function handleWebhook(string $payload, string $signature): void {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $_ENV['STRIPE_WEBHOOK_SECRET']
            );

            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdate($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
            }
        } catch (\Exception $e) {
            \error_log("Webhook error: " . $e->getMessage());
            throw $e;
        }
    }

    private function handleCheckoutCompleted($session): void {
        $userId = (int)$session->client_reference_id;
        $subscriptionId = $session->subscription;

        // Store billing address from checkout session
        if ($session->customer_details) {
            $this->storeBillingData($userId, $session);
        }

        // Store Stripe customer ID
        if ($session->customer) {
            $this->db->query(
                "UPDATE users SET stripe_customer_id = ? WHERE id = ?",
                [$session->customer, $userId]
            );
        }

        // Get subscription details
        $subscription = StripeSubscription::retrieve($subscriptionId);

        $this->db->query(
            "INSERT INTO subscriptions (user_id, stripe_subscription_id, status, current_period_end)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             stripe_subscription_id = VALUES(stripe_subscription_id),
             status = VALUES(status),
             current_period_end = VALUES(current_period_end)",
            [
                $userId,
                $subscriptionId,
                $subscription->status,
                date('Y-m-d H:i:s', $subscription->current_period_end)
            ]
        );

        $this->updateSubscriptionStatus(
            $userId,
            'active',
            date('Y-m-d H:i:s', $subscription->current_period_end)
        );
    }

    private function handleSubscriptionUpdate($subscription): void {
        $this->db->query(
            "UPDATE subscriptions
             SET status = ?, current_period_end = ?
             WHERE stripe_subscription_id = ?",
            [
                $subscription->status,
                date('Y-m-d H:i:s', $subscription->current_period_end),
                $subscription->id
            ]
        );

        // Update user status
        $sub = $this->db->fetchOne(
            "SELECT user_id FROM subscriptions WHERE stripe_subscription_id = ?",
            [$subscription->id]
        );

        if ($sub) {
            $status = $subscription->status === 'active' ? 'active' : 'expired';
            $this->updateSubscriptionStatus(
                $sub['user_id'],
                $status,
                date('Y-m-d H:i:s', $subscription->current_period_end)
            );
        }
    }

    private function handleSubscriptionDeleted($subscription): void {
        $sub = $this->db->fetchOne(
            "SELECT user_id FROM subscriptions WHERE stripe_subscription_id = ?",
            [$subscription->id]
        );

        if ($sub) {
            $this->updateSubscriptionStatus($sub['user_id'], 'cancelled');
        }
    }

    private function handlePaymentSucceeded($invoice): void {
        // Payment succeeded, subscription is active
        if ($invoice->subscription) {
            $subscription = StripeSubscription::retrieve($invoice->subscription);
            $this->handleSubscriptionUpdate($subscription);

            // Create Billingo invoice for this payment
            $this->createBillingoInvoice($invoice);
        }
    }

    private function handlePaymentFailed($invoice): void {
        // Payment failed, might need to notify user
        \error_log("Payment failed for subscription: " . $invoice->subscription);
    }

    private function updateSubscriptionStatus(
        int $userId,
        string $status,
        ?string $endDate = null
    ): void {
        $this->db->query(
            "UPDATE users SET subscription_status = ?, subscription_end_date = ? WHERE id = ?",
            [$status, $endDate, $userId]
        );
    }

    /**
     * Store billing data from Stripe checkout session
     */
    private function storeBillingData(int $userId, $session): void {
        $details = $session->customer_details;
        $address = $details->address ?? null;
        $taxIds = $details->tax_ids ?? [];

        $vatNumber = null;
        foreach ($taxIds as $taxId) {
            if ($taxId->type === 'eu_vat') {
                $vatNumber = $taxId->value;
                break;
            }
        }

        $this->db->query(
            "UPDATE users SET
             billing_name = ?,
             billing_country = ?,
             billing_postal_code = ?,
             billing_city = ?,
             billing_line1 = ?,
             vat_number = ?,
             company_name = ?
             WHERE id = ?",
            [
                $details->name ?? '',
                $address->country ?? '',
                $address->postal_code ?? '',
                $address->city ?? '',
                $address->line1 ?? '',
                $vatNumber,
                $details->name ?? '', // Use name as company name if available
                $userId
            ]
        );
    }

    /**
     * Create Billingo invoice for Stripe payment
     */
    private function createBillingoInvoice($stripeInvoice): void {
        try {
            // Get user ID from subscription
            $sub = $this->db->fetchOne(
                "SELECT user_id FROM subscriptions WHERE stripe_subscription_id = ?",
                [$stripeInvoice->subscription]
            );

            if (!$sub) {
                throw new \Exception("Subscription not found for invoice");
            }

            $userId = $sub['user_id'];

            // Check if invoice already created
            $existing = $this->db->fetchOne(
                "SELECT id FROM invoices WHERE stripe_invoice_id = ?",
                [$stripeInvoice->id]
            );

            if ($existing) {
                return; // Already created
            }

            // Get user billing data
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE id = ?",
                [$userId]
            );

            if (!$user) {
                throw new \Exception("User not found");
            }

            // Prepare customer data for Billingo
            $customer = [
                'name' => $user['billing_name'] ?: $user['email'],
                'email' => $user['email'],
                'country' => $user['billing_country'] ?: 'HU',
                'postal_code' => $user['billing_postal_code'] ?: '',
                'city' => $user['billing_city'] ?: '',
                'line1' => $user['billing_line1'] ?: '',
                'vat_number' => $user['vat_number'] ?: ''
            ];

            // Calculate amounts (Stripe amounts are in cents)
            $totalAmount = $stripeInvoice->total / 100;
            $taxAmount = $stripeInvoice->tax / 100;
            $currency = strtoupper($stripeInvoice->currency);

            // Create Billingo client and invoice
            $billingo = new BillingoClient();
            $invoiceData = $billingo->buildSubscriptionInvoice(
                $customer,
                $totalAmount,
                $taxAmount,
                $currency,
                $stripeInvoice->id
            );

            $billingoResponse = $billingo->createInvoice($invoiceData);

            // Store invoice record
            $this->db->query(
                "INSERT INTO invoices (user_id, stripe_invoice_id, billingo_invoice_id, amount, tax_amount, currency, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $stripeInvoice->id,
                    $billingoResponse['id'] ?? null,
                    $totalAmount,
                    $taxAmount,
                    $currency,
                    'completed'
                ]
            );

        } catch (\Exception $e) {
            // Log error but don't fail the payment
            \error_log("Billingo invoice creation failed: " . $e->getMessage());

            // Store failed invoice attempt
            if (isset($userId) && isset($stripeInvoice)) {
                $this->db->query(
                    "INSERT INTO invoices (user_id, stripe_invoice_id, amount, tax_amount, currency, status)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $userId ?? 0,
                        $stripeInvoice->id,
                        ($stripeInvoice->total ?? 0) / 100,
                        ($stripeInvoice->tax ?? 0) / 100,
                        strtoupper($stripeInvoice->currency ?? 'EUR'),
                        'failed'
                    ]
                );
            }
        }
    }
}
