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
            ]
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
}
