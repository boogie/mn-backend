<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use MagicianNews\Database;
use MagicianNews\Auth;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = Database::getInstance();
    $auth = new Auth($db);

    // Get auth token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    if (empty($token)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authorization token required'
        ]);
        exit;
    }

    $user = $auth->getUserFromToken($token);
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired token'
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['name']) || empty(trim($input['name']))) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Name is required'
            ]);
            exit;
        }

        $name = trim($input['name']);

        // Update user's name
        $db->query(
            "UPDATE users SET name = ? WHERE id = ?",
            [$name, $user['id']]
        );

        // Fetch updated user data
        $updatedUser = $db->fetchOne(
            "SELECT id, email, name, billing_name, subscription_status, subscription_end_date, created_at FROM users WHERE id = ?",
            [$user['id']]
        );

        echo json_encode([
            'success' => true,
            'data' => $updatedUser
        ]);
        exit;
    }

    // GET method - return current user data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $userData = $db->fetchOne(
            "SELECT id, email, name, billing_name, subscription_status, subscription_end_date, created_at FROM users WHERE id = ?",
            [$user['id']]
        );

        echo json_encode([
            'success' => true,
            'data' => $userData
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
