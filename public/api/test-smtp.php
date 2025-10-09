<?php
/**
 * SMTP Test Endpoint
 *
 * Tests SMTP configuration and connectivity
 * Returns detailed debug information
 */

require_once __DIR__ . '/../../src/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use MagicianNews\Response;

header('Content-Type: application/json');

try {
    $debug = [];

    // Check environment variables
    $debug['env'] = [
        'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'NOT SET',
        'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 'NOT SET',
        'SMTP_USER' => isset($_ENV['SMTP_USER']) ? substr($_ENV['SMTP_USER'], 0, 10) . '...' : 'NOT SET',
        'SMTP_PASSWORD' => isset($_ENV['SMTP_PASSWORD']) ? '[SET]' : 'NOT SET',
        'EMAIL_FROM' => $_ENV['EMAIL_FROM'] ?? 'NOT SET',
        'EMAIL_FROM_NAME' => $_ENV['EMAIL_FROM_NAME'] ?? 'NOT SET',
        'EMAIL_ENABLED' => $_ENV['EMAIL_ENABLED'] ?? 'NOT SET',
        'APP_ENV' => $_ENV['APP_ENV'] ?? 'NOT SET',
    ];

    // Check if SMTP credentials are configured
    if (empty($_ENV['SMTP_HOST']) || empty($_ENV['SMTP_USER']) || empty($_ENV['SMTP_PASSWORD'])) {
        Response::error('SMTP credentials not configured', 500, $debug);
    }

    // Test SMTP connection
    $mail = new PHPMailer(true);

    // Capture debug output
    $debugOutput = [];
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
        $debugOutput[] = $str;
    };

    // Server settings
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)($_ENV['SMTP_PORT'] ?? 587);

    // Test recipient
    $testEmail = $_GET['email'] ?? 'test@example.com';

    // Recipients
    $fromEmail = $_ENV['EMAIL_FROM'] ?? 'noreply@magicians.news';
    $fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'Magicians News';

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($testEmail);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test from Magicians News';
    $mail->Body = '<h1>SMTP Test Successful</h1><p>If you received this email, SMTP is working correctly!</p>';
    $mail->CharSet = 'UTF-8';

    // Try to send
    $mail->send();

    $debug['smtp_debug'] = $debugOutput;
    $debug['test_email'] = $testEmail;

    Response::success($debug, 'SMTP test successful! Email sent.');

} catch (Exception $e) {
    Response::error('SMTP test failed: ' . $mail->ErrorInfo, 500, [
        'error' => $e->getMessage(),
        'smtp_debug' => $debugOutput ?? [],
        'env_check' => $debug['env'] ?? [],
    ]);
} catch (\Exception $e) {
    Response::error('Test failed: ' . $e->getMessage(), 500, [
        'error' => $e->getMessage(),
        'env_check' => $debug['env'] ?? [],
    ]);
}
