<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Auth;
use MagicianNews\Response;

$auth = new Auth();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'POST':
            $action = $_GET['action'] ?? 'login';

            if ($action === 'register') {
                $result = $auth->register(
                    $input['email'] ?? '',
                    $input['password'] ?? '',
                    $input['name'] ?? ''
                );
                Response::success($result, 'Registration successful');
            } elseif ($action === 'login') {
                $result = $auth->login(
                    $input['email'] ?? '',
                    $input['password'] ?? ''
                );
                Response::success($result, 'Login successful');
            } else {
                Response::error('Invalid action');
            }
            break;

        case 'GET':
            // Get current user
            $token = $auth->getAuthHeader();
            if (!$token) {
                Response::unauthorized();
            }

            $user = $auth->getCurrentUser($token);
            if (!$user) {
                Response::unauthorized();
            }

            Response::success($user);
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (\Exception $e) {
    Response::error($e->getMessage());
}
