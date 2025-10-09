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

            } elseif ($action === 'unsubscribe') {
                // Unsubscribe by token from email link
                $token = $_GET['token'] ?? '';

                if (empty($token)) {
                    Response::error('Unsubscribe token is required', 400);
                }

                $result = $newsletter->unsubscribeByToken($token);

                // Return HTML page instead of JSON for better UX
                http_response_code(200);
                header('Content-Type: text/html; charset=UTF-8');
                echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribed - Magicians News</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 48px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #1f2937; font-size: 32px; margin-bottom: 16px; }
        p { color: #6b7280; font-size: 16px; line-height: 1.6; margin-bottom: 32px; }
        .icon { font-size: 64px; margin-bottom: 24px; }
        a {
            display: inline-block;
            background: linear-gradient(180deg, #9b87f5, #7c6ad6);
            color: white;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        a:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Unsubscribed Successfully</h1>
        <p>{$result['message']}<br><br>We're sorry to see you go. You won't receive any more emails from us.</p>
        <a href="{$_ENV['FRONTEND_URL']}">Visit Magicians News</a>
    </div>
</body>
</html>
HTML;
                exit;

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
