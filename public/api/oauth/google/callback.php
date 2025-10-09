<?php

require_once __DIR__ . '/../../../../src/config.php';

use MagicianNews\Database;
use MagicianNews\Auth;
use League\OAuth2\Client\Provider\Google;

// Check if Google OAuth is configured
if (empty($_ENV['GOOGLE_CLIENT_ID']) || empty($_ENV['GOOGLE_CLIENT_SECRET'])) {
    $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://magicians.news';
    header('Location: ' . $frontendUrl . '/login?error=oauth_not_configured');
    exit;
}

// Initialize Google OAuth provider
$provider = new Google([
    'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
    'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
    'redirectUri'  => $_ENV['BACKEND_URL'] . '/api/oauth/google/callback.php',
]);

// Handle OAuth callback
if (isset($_GET['code'])) {
    try {
        // Exchange code for access token
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Get user info
        $ownerDetails = $provider->getResourceOwner($token);
        $userInfo = $ownerDetails->toArray();

        $email = $userInfo['email'];
        $name = $userInfo['name'] ?? $userInfo['given_name'] ?? 'User';
        $googleId = $userInfo['sub'];

        // Initialize database and auth
        $db = Database::getInstance();
        $auth = new Auth();

        // Check if user exists
        $user = $db->fetchOne(
            "SELECT id, email, name, subscription_status, subscription_end_date, created_at
             FROM users WHERE email = ? OR google_id = ?",
            [$email, $googleId]
        );

        if ($user) {
            // Update google_id and mark as verified if not set
            if (empty($user['google_id'])) {
                $db->query(
                    "UPDATE users SET google_id = ?, email_verified = 1 WHERE id = ?",
                    [$googleId, $user['id']]
                );
            }
        } else {
            // Create new user (Google OAuth users are auto-verified)
            $db->query(
                "INSERT INTO users (email, name, google_id, subscription_status, email_verified) VALUES (?, ?, ?, ?, ?)",
                [$email, $name, $googleId, 'free', 1]
            );

            $userId = $db->getConnection()->lastInsertId();

            $user = $db->fetchOne(
                "SELECT id, email, name, subscription_status, subscription_end_date, created_at
                 FROM users WHERE id = ?",
                [$userId]
            );
        }

        // Generate JWT token
        $jwtToken = $auth->generateToken($user['id'], $user['email']);

        // Redirect to frontend with token
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://magicians.news';
        header('Location: ' . $frontendUrl . '/oauth/callback?token=' . urlencode($jwtToken));
        exit;

    } catch (\Exception $e) {
        file_put_contents(__DIR__ . '/../../../../database/oauth_errors.log',
            date('Y-m-d H:i:s') . ": " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://magicians.news';
        header('Location: ' . $frontendUrl . '/login?error=oauth_failed');
        exit;
    }
} else {
    // Redirect to Google OAuth
    $authUrl = $provider->getAuthorizationUrl([
        'scope' => ['email', 'profile']
    ]);

    // Store state in session for CSRF protection (optional but recommended)
    $_SESSION['oauth2state'] = $provider->getState();

    header('Location: ' . $authUrl);
    exit;
}
