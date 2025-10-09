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
                Response::success($result, 'Registration successful. Please check your email to verify your account.');
            } elseif ($action === 'login') {
                $rememberMe = $input['remember_me'] ?? true;
                $result = $auth->login(
                    $input['email'] ?? '',
                    $input['password'] ?? '',
                    $rememberMe
                );
                Response::success($result, 'Login successful');
            } elseif ($action === 'forgot-password') {
                try {
                    $auth->requestPasswordReset($input['email'] ?? '');
                    Response::success(null, 'If an account exists with this email, a password reset link has been sent.');
                } catch (\Exception $e) {
                    error_log("Password reset error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
                    throw $e;
                }
            } elseif ($action === 'reset-password') {
                $auth->resetPassword(
                    $input['token'] ?? '',
                    $input['password'] ?? ''
                );
                Response::success(null, 'Password reset successful. You can now log in with your new password.');
            } elseif ($action === 'verify-email') {
                $result = $auth->verifyEmail($input['token'] ?? '');
                Response::success($result, 'Email verified successfully!');
            } elseif ($action === 'resend-verification') {
                $auth->resendVerificationEmail($input['email'] ?? '');
                Response::success(null, 'Verification email sent. Please check your inbox.');
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
