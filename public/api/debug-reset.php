<?php
/**
 * Debug Password Reset Endpoint
 * Detailed debugging for password reset flow
 */

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Auth;
use MagicianNews\Response;

$debug = [];
$debug['step'] = 'Starting';

try {
    $debug['step'] = 'Reading input';
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    $debug['email_provided'] = !empty($email);
    $debug['step'] = 'Creating Auth instance';

    $auth = new Auth();

    $debug['step'] = 'Calling requestPasswordReset';
    $result = $auth->requestPasswordReset($email);

    $debug['step'] = 'Password reset completed';
    $debug['result'] = $result;

    // Get any buffered output
    $buffered = ob_get_clean();
    if (!empty($buffered)) {
        $debug['buffered_output'] = $buffered;
    }

    Response::success($debug, 'Password reset request processed successfully');

} catch (\Throwable $e) {
    $buffered = ob_get_clean();
    if (!empty($buffered)) {
        $debug['buffered_output'] = $buffered;
    }

    $debug['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];

    Response::error('Debug error', 500, $debug);
}
