<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Auth;
use MagicianNews\Subscription;
use MagicianNews\Response;

$auth = new Auth();
$subscription = new Subscription();

// Verify authentication
$token = $auth->getAuthHeader();
if (!$token) {
    Response::unauthorized();
}

try {
    $decoded = $auth->verifyToken($token);
    $userId = $decoded['user_id'];
} catch (\Exception $e) {
    Response::unauthorized('Invalid token');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'status') {
                // Check subscription status
                $isSubscribed = $subscription->isSubscribed($userId);
                Response::success(['is_subscribed' => $isSubscribed]);
            } else {
                Response::error('Invalid action');
            }
            break;

        case 'POST':
            if ($action === 'checkout') {
                // Create checkout session
                $user = $auth->getCurrentUser($token);
                $checkoutUrl = $subscription->createCheckoutSession($userId, $user['email']);
                Response::success(['checkout_url' => $checkoutUrl]);
            } else {
                Response::error('Invalid action');
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (\Exception $e) {
    Response::error($e->getMessage());
}
