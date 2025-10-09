<?php
require_once __DIR__ . '/src/config.php';

use MagicianNews\Email;

$email = new Email();

// Call the sendPasswordResetEmail method but capture the HTML
$reflection = new ReflectionClass($email);
$method = $reflection->getMethod('getEmailTemplate');
$method->setAccessible(true);

$html = $method->invoke(
    $email,
    'Reset Your Password',
    'Hi Test User,',
    'We received a request to reset your password. Click the button below to choose a new password:',
    'https://magicians.news/reset-password?token=test123',
    'Reset Password',
    "This link will expire in 1 hour. If you didn't request a password reset, you can safely ignore this email."
);

// Save to file so we can view it
file_put_contents(__DIR__ . '/test-email.html', $html);

echo "Email template saved to test-email.html\n";
echo "Open it in a browser to see how it looks.\n";
