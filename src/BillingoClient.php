<?php
namespace MagicianNews;

use GuzzleHttp\Client;

class BillingoClient {
    private Client $client;
    private string $apiKey;
    private string $apiUrl = 'https://api.billingo.hu/v3';

    public function __construct() {
        if (empty($_ENV['BILLINGO_API_KEY'])) {
            throw new \Exception("BILLINGO_API_KEY is not set");
        }

        $this->apiKey = $_ENV['BILLINGO_API_KEY'];

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 10.0,
            'headers' => [
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Create invoice for subscription payment
     *
     * @param array $invoiceData Invoice data with customer and line items
     * @return array Created invoice data from Billingo
     */
    public function createInvoice(array $invoiceData): array {
        try {
            $response = $this->client->post('/documents', [
                'json' => $invoiceData
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data === null) {
                throw new \Exception("Invalid JSON response from Billingo");
            }

            return $data;
        } catch (\Exception $e) {
            throw new \Exception("Failed to create Billingo invoice: " . $e->getMessage());
        }
    }

    /**
     * Build invoice data for subscription payment
     *
     * @param array $customer Customer billing data
     * @param float $amount Gross amount (including VAT)
     * @param float $taxAmount VAT amount
     * @param string $currency Currency code (e.g., 'EUR', 'USD')
     * @param string $stripePaymentId Stripe payment/invoice ID
     * @return array Invoice data ready for Billingo API
     */
    public function buildSubscriptionInvoice(
        array $customer,
        float $amount,
        float $taxAmount,
        string $currency,
        string $stripePaymentId
    ): array {
        $netAmount = $amount - $taxAmount;
        $vatRate = $taxAmount > 0 ? ($taxAmount / $netAmount) * 100 : 0;

        // Convert 3-letter currency to Billingo format
        $billingoCurrency = $this->convertCurrency($currency);

        return [
            'vendor_id' => $_ENV['BILLINGO_VENDOR_ID'] ?? '',
            'partner' => [
                'name' => $customer['name'],
                'address' => [
                    'country_code' => $customer['country'],
                    'post_code' => $customer['postal_code'] ?? '',
                    'city' => $customer['city'] ?? '',
                    'address' => $customer['line1'] ?? ''
                ],
                'emails' => [$customer['email']],
                'taxcode' => $customer['vat_number'] ?? ''
            ],
            'block_id' => $_ENV['BILLINGO_BLOCK_ID'] ?? '',
            'bank_account_id' => $_ENV['BILLINGO_BANK_ACCOUNT_ID'] ?? '',
            'type' => 'invoice',
            'fulfillment_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d'),
            'payment_method' => 'online_bankcard',
            'language' => $this->getLanguageFromCountry($customer['country']),
            'currency' => $billingoCurrency,
            'comment' => 'Stripe Payment ID: ' . $stripePaymentId,
            'items' => [
                [
                    'name' => 'Magicians News - Monthly Subscription',
                    'unit_price' => round($netAmount, 2),
                    'unit_price_type' => 'net',
                    'quantity' => 1,
                    'unit' => 'month',
                    'vat' => $this->getVatName($vatRate),
                    'comment' => date('F Y')
                ]
            ],
            'settings' => [
                'should_send_email' => true
            ]
        ];
    }

    /**
     * Convert ISO currency code to Billingo format
     */
    private function convertCurrency(string $currency): string {
        $currencyMap = [
            'USD' => 'USD',
            'EUR' => 'EUR',
            'HUF' => 'HUF',
            'GBP' => 'GBP'
        ];

        return $currencyMap[strtoupper($currency)] ?? 'EUR';
    }

    /**
     * Get Billingo VAT name from percentage
     */
    private function getVatName(float $vatRate): string {
        // Billingo VAT codes
        if ($vatRate === 0) {
            return 'TAM'; // Tax-exempt (outside EU or reverse charge)
        } elseif ($vatRate >= 26 && $vatRate <= 28) {
            return 'AAM'; // 27% (Hungary standard rate)
        } elseif ($vatRate >= 19 && $vatRate <= 21) {
            return '20%'; // ~20% (many EU countries)
        } elseif ($vatRate >= 22 && $vatRate <= 26) {
            return '25%'; // ~25% (Sweden, Denmark, etc.)
        }

        // Default to closest standard rate
        return round($vatRate) . '%';
    }

    /**
     * Get language code from country code
     */
    private function getLanguageFromCountry(string $countryCode): string {
        $languageMap = [
            'HU' => 'hu',
            'DE' => 'de',
            'AT' => 'de',
            'CH' => 'de',
            'FR' => 'fr',
            'IT' => 'it',
            'ES' => 'es',
            'RO' => 'ro',
            'SK' => 'sk',
            'HR' => 'hr',
            'SL' => 'sl'
        ];

        return $languageMap[$countryCode] ?? 'en';
    }
}
