<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Newsletter;
use MagicianNews\Response;

$newsletter = new Newsletter();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'POST':
            $action = $_GET['action'] ?? 'subscribe';

            if ($action === 'subscribe') {
                $email = $input['email'] ?? '';
                $name = $input['name'] ?? null;
                $source = $input['source'] ?? 'homepage';

                if (empty($email)) {
                    Response::error('Email is required', 400);
                }

                $result = $newsletter->subscribe($email, $name, $source);
                Response::success($result, $result['message']);

            } elseif ($action === 'unsubscribe') {
                $email = $input['email'] ?? '';

                if (empty($email)) {
                    Response::error('Email is required', 400);
                }

                $result = $newsletter->unsubscribe($email);
                Response::success($result, $result['message']);

            } else {
                Response::error('Invalid action', 400);
            }
            break;

        case 'GET':
            $action = $_GET['action'] ?? 'stats';

            if ($action === 'stats') {
                // This endpoint can be used for internal stats
                // Consider adding authentication for production
                $stats = $newsletter->getStats();
                Response::success($stats);

            } elseif ($action === 'export') {
                // TODO: Add authentication/token check for this endpoint
                $limit = (int)($_GET['limit'] ?? 100);
                $offset = (int)($_GET['offset'] ?? 0);

                $subscribers = $newsletter->getSubscribers($limit, $offset);
                Response::success([
                    'subscribers' => $subscribers,
                    'count' => count($subscribers),
                ]);

            } else {
                Response::error('Invalid action', 400);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (\Exception $e) {
    Response::error($e->getMessage(), 400);
}
