<?php
require_once __DIR__ . '/../../../src/config.php';

use MagicianNews\Auth;
use MagicianNews\GoogleOAuth;
use MagicianNews\Database;
use MagicianNews\Response;

$oauth = new GoogleOAuth();
$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Handle callback from Google
if (isset($_GET['code'])) {
    try {
        // Exchange code for user info
        $googleUser = $oauth->getAccessToken($_GET['code']);

        // Check if user exists
        $stmt = $db->prepare("
            SELECT id, email, google_id FROM users
            WHERE google_id = ? OR email = ?
        ");
        $stmt->execute([$googleUser['google_id'], $googleUser['email']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user) {
            // Update existing user with Google ID if not set
            if (!$user['google_id']) {
                $stmt = $db->prepare("
                    UPDATE users
                    SET google_id = ?, name = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $googleUser['google_id'],
                    $googleUser['name'],
                    $user['id']
                ]);
            }
            $userId = $user['id'];
        } else {
            // Create new user
            $stmt = $db->prepare("
                INSERT INTO users (email, google_id, name, password_hash)
                VALUES (?, ?, ?, NULL)
            ");
            $stmt->execute([
                $googleUser['email'],
                $googleUser['google_id'],
                $googleUser['name']
            ]);
            $userId = $db->lastInsertId();
        }

        // Generate JWT token
        $token = $auth->generateToken($userId, $googleUser['email']);

        // Redirect to frontend with token
        $frontendUrl = $_ENV['APP_URL'];
        header("Location: {$frontendUrl}/oauth/callback?token={$token}");
        exit;

    } catch (\Exception $e) {
        error_log("OAuth callback error: " . $e->getMessage());
        $frontendUrl = $_ENV['APP_URL'];
        header("Location: {$frontendUrl}/login?error=oauth_failed");
        exit;
    }
}

// Initial OAuth redirect
header("Location: " . $oauth->getAuthorizationUrl());
exit;
