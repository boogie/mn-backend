<?php
namespace MagicianNews;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email {
    private string $fromEmail;
    private string $fromName;
    private string $appUrl;

    public function __construct() {
        $this->fromEmail = $_ENV['EMAIL_FROM'] ?? 'noreply@magicians.news';
        $this->fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'Magicians News';
        $this->appUrl = $_ENV['FRONTEND_URL'] ?? 'https://magicians.news';
    }

    /**
     * Send email verification email
     */
    public function sendVerificationEmail(string $toEmail, string $name, string $token): bool {
        $verifyUrl = $this->appUrl . '/verify-email?token=' . urlencode($token);

        $subject = 'Verify your email - Magicians News';

        $html = $this->getEmailTemplate(
            'Verify Your Email',
            "Hi $name,",
            "Thank you for signing up for Magicians News! Please verify your email address by clicking the button below:",
            $verifyUrl,
            'Verify Email',
            "This link will expire in 24 hours. If you didn't create an account, you can safely ignore this email."
        );

        return $this->send($toEmail, $subject, $html);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $toEmail, string $name, string $token): bool {
        $resetUrl = $this->appUrl . '/reset-password?token=' . urlencode($token);

        $subject = 'Reset your password - Magicians News';

        $html = $this->getEmailTemplate(
            'Reset Your Password',
            "Hi $name,",
            "We received a request to reset your password. Click the button below to choose a new password:",
            $resetUrl,
            'Reset Password',
            "This link will expire in 1 hour. If you didn't request a password reset, you can safely ignore this email."
        );

        return $this->send($toEmail, $subject, $html);
    }

    /**
     * Send newsletter confirmation email
     */
    public function sendNewsletterConfirmation(string $toEmail, string $name, string $unsubscribeToken): bool {
        $unsubscribeUrl = ($_ENV['BACKEND_URL'] ?? 'https://api.magicians.news') . '/newsletter?action=unsubscribe&token=' . urlencode($unsubscribeToken);

        $subject = 'Welcome to Magicians News! 🎩';

        $greeting = $name ? "Hi $name," : "Hi there,";
        $html = $this->getNewsletterTemplate(
            'Welcome to Magicians News',
            $greeting,
            "Thank you for subscribing to our newsletter! You'll be among the first to know when we launch.",
            "We'll send you updates about our launch, early access opportunities, and exclusive content for founding members.",
            $unsubscribeUrl
        );

        return $this->send($toEmail, $subject, $html);
    }

    /**
     * Get newsletter email template (with unsubscribe link)
     */
    private function getNewsletterTemplate(
        string $title,
        string $greeting,
        string $body,
        string $footer,
        string $unsubscribeUrl
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">🎩 Magicians News</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 18px; color: #1f2937; font-weight: 600;">{$greeting}</p>
                            <p style="margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #4b5563;">{$body}</p>

                            <p style="margin: 30px 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">{$footer}</p>

                            <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 10px; font-size: 16px; color: #1f2937; font-weight: 600;">What to expect:</p>
                                <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #4b5563;">
                                    <li>Launch announcement with early access pricing (€1/month)</li>
                                    <li>Exclusive content previews</li>
                                    <li>Founding member benefits</li>
                                </ul>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="margin: 0 0 10px; font-size: 14px; color: #6b7280;">
                                Magicians News - Your Daily Dose of Magic<br>
                                <a href="{$this->appUrl}" style="color: #667eea; text-decoration: none;">magicians.news</a>
                            </p>
                            <p style="margin: 10px 0 0; font-size: 12px; color: #9ca3af;">
                                Don't want to receive these emails? <a href="{$unsubscribeUrl}" style="color: #667eea; text-decoration: underline;">Unsubscribe</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Send email using AWS SES SMTP or fallback to logging in development
     */
    private function send(string $to, string $subject, string $html): bool {
        try {
            // For development, just log the email instead of sending
            if ($_ENV['APP_ENV'] === 'development' || empty($_ENV['EMAIL_ENABLED'])) {
                $this->logEmail($to, $subject, $html);
                return true;
            }

            // Use AWS SES SMTP in production
            if (!empty($_ENV['SMTP_HOST']) && !empty($_ENV['SMTP_USER']) && !empty($_ENV['SMTP_PASSWORD'])) {
                return $this->sendViaSmtp($to, $subject, $html);
            }

            // Fallback to PHP mail() if SMTP is not configured
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                "From: {$this->fromName} <{$this->fromEmail}>",
                "Reply-To: {$this->fromEmail}",
            ];
            return mail($to, $subject, $html, implode("\r\n", $headers));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send email via AWS SES SMTP using PHPMailer
     */
    private function sendViaSmtp(string $to, string $subject, string $html): bool {
        // Start output buffering to prevent any output from PHPMailer
        ob_start();

        try {
            $mail = new PHPMailer(true);

            // Disable debug output completely
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = function($str, $level) {
                // Suppress all debug output
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
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->CharSet = 'UTF-8';

            $mail->send();

            // Clean output buffer
            ob_end_clean();

            return true;

        } catch (Exception $e) {
            // Clean output buffer on error too
            ob_end_clean();

            return false;
        }
    }

    /**
     * Log email to file for development
     */
    private function logEmail(string $to, string $subject, string $html): void {
        $logDir = __DIR__ . '/../database/emails';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/email-' . date('Y-m-d') . '.log';
        $log = sprintf(
            "[%s] TO: %s | SUBJECT: %s\n%s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('-', 80),
            $html
        );

        file_put_contents($logFile, $log, FILE_APPEND);
    }

    /**
     * Get email HTML template
     */
    private function getEmailTemplate(
        string $title,
        string $greeting,
        string $body,
        string $buttonUrl,
        string $buttonText,
        string $footer
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">🎩 Magicians News</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 18px; color: #1f2937; font-weight: 600;">{$greeting}</p>
                            <p style="margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #4b5563;">{$body}</p>

                            <!-- Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="{$buttonUrl}" style="display: inline-block; background: linear-gradient(180deg, #9b87f5, #7c6ad6); color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 8px; font-size: 16px; font-weight: 700; box-shadow: 0 4px 12px rgba(124, 106, 214, 0.3);">{$buttonText}</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">{$footer}</p>

                            <!-- Manual link -->
                            <p style="margin: 20px 0 0; font-size: 12px; color: #9ca3af;">
                                Or copy and paste this link into your browser:<br>
                                <a href="{$buttonUrl}" style="color: #667eea; word-break: break-all;">{$buttonUrl}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                Magicians News - Your Daily Dose of Magic<br>
                                <a href="{$this->appUrl}" style="color: #667eea; text-decoration: none;">magicians.news</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
