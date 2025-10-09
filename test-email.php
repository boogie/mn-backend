<?php
/**
 * Email Test Script
 *
 * Usage: php test-email.php recipient@example.com
 *
 * This script tests SMTP connectivity and email sending without
 * requiring the full application stack.
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get recipient from command line argument
if ($argc < 2) {
    echo "Usage: php test-email.php recipient@example.com\n";
    exit(1);
}

$recipient = $argv[1];

echo "=== Email SMTP Test ===\n\n";

// Check if SMTP credentials are loaded
echo "Checking environment variables:\n";
echo "SMTP_HOST: " . ($_ENV['SMTP_HOST'] ?? 'NOT SET') . "\n";
echo "SMTP_PORT: " . ($_ENV['SMTP_PORT'] ?? 'NOT SET') . "\n";
echo "SMTP_USER: " . (isset($_ENV['SMTP_USER']) ? substr($_ENV['SMTP_USER'], 0, 10) . '...' : 'NOT SET') . "\n";
echo "SMTP_PASSWORD: " . (isset($_ENV['SMTP_PASSWORD']) ? '[SET]' : 'NOT SET') . "\n";
echo "EMAIL_FROM: " . ($_ENV['EMAIL_FROM'] ?? 'NOT SET') . "\n";
echo "EMAIL_FROM_NAME: " . ($_ENV['EMAIL_FROM_NAME'] ?? 'NOT SET') . "\n\n";

if (empty($_ENV['SMTP_HOST']) || empty($_ENV['SMTP_USER']) || empty($_ENV['SMTP_PASSWORD'])) {
    echo "ERROR: SMTP credentials not configured!\n";
    echo "Make sure .env file has SMTP_HOST, SMTP_USER, and SMTP_PASSWORD set.\n";
    exit(1);
}

echo "Attempting to send test email to: $recipient\n\n";

try {
    $mail = new PHPMailer(true);

    // Enable verbose debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo "DEBUG: $str\n";
    };

    // Server settings
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)($_ENV['SMTP_PORT'] ?? 587);

    // Recipients
    $fromEmail = $_ENV['EMAIL_FROM'] ?? 'noreply@magicians.news';
    $fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'Magicians News';

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($recipient);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Magicians News SMTP';
    $mail->Body = '<h1>Test Email</h1><p>This is a test email sent via AWS SES SMTP.</p><p>If you received this, SMTP is working correctly!</p>';
    $mail->CharSet = 'UTF-8';

    echo "\n";
    $mail->send();
    echo "\n✅ SUCCESS! Email sent successfully.\n";
    echo "Check the inbox (and spam folder) of: $recipient\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: Email could not be sent.\n";
    echo "Error: {$mail->ErrorInfo}\n";
    echo "\nCommon issues:\n";
    echo "- Sender email not verified in AWS SES Console\n";
    echo "- AWS SES in sandbox mode (recipient must also be verified)\n";
    echo "- Invalid SMTP credentials\n";
    echo "- Firewall blocking port 587\n";
    exit(1);
}
