<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Subscription;
use MagicianNews\Response;

// Get payload and signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $subscription = new Subscription();
    $subscription->handleWebhook($payload, $signature);

    Response::success(null, 'Webhook handled');
} catch (\Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    Response::error('Webhook failed', 400);
}
