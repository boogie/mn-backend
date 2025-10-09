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
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>{$title}</title>
    <!--[if mso]>
    <style>
        * { font-family: sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        html, body {
            margin: 0 auto !important;
            padding: 0 !important;
            height: 100% !important;
            width: 100% !important;
            background: #f3f4f6;
        }
        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }
        div[style*="margin: 16px 0"] {
            margin: 0 !important;
        }
        table, td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }
        table {
            border-spacing: 0 !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 0 auto !important;
        }
        img {
            -ms-interpolation-mode: bicubic;
        }
        a {
            text-decoration: none;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body width="100%" style="margin: 0; padding: 0 !important; mso-line-height-rule: exactly; background-color: #f3f4f6;">
    <center style="width: 100%; background-color: #f3f4f6;">
        <!--[if mso | IE]>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f3f4f6;">
        <tr>
        <td>
        <![endif]-->

        <div style="max-width: 600px; margin: 0 auto;" class="email-container">
            <!--[if mso]>
            <table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600">
            <tr>
            <td>
            <![endif]-->

            <table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: auto;">

                <!-- Header : BEGIN -->
                <tr>
                    <td style="background-color: #667eea; padding: 40px 30px; text-align: center;">
                        <h1 style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 28px; line-height: 34px; color: #ffffff; font-weight: 700;">🎩 Magicians News</h1>
                    </td>
                </tr>
                <!-- Header : END -->

                <!-- Content : BEGIN -->
                <tr>
                    <td style="background-color: #ffffff;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 40px 30px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 16px; line-height: 24px; color: #4b5563;">
                                    <h2 style="margin: 0 0 20px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 18px; line-height: 24px; color: #1f2937; font-weight: 600;">{$greeting}</h2>
                                    <p style="margin: 0 0 15px;">{$body}</p>
                                    <p style="margin: 0; font-size: 14px; color: #6b7280;">{$footer}</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 20px 30px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 22px; color: #4b5563; border-top: 1px solid #e5e7eb;">
                                    <p style="margin: 0 0 10px; font-size: 16px; color: #1f2937; font-weight: 600;">What to expect:</p>
                                    <p style="margin: 0;">• Launch announcement with early access pricing (€1/month)<br>• Exclusive content previews<br>• Founding member benefits</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- Content : END -->

                <!-- Footer : BEGIN -->
                <tr>
                    <td style="background-color: #f9fafb; padding: 30px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                        <p style="margin: 0 0 10px; font-size: 14px; line-height: 20px; color: #6b7280;">
                            Magicians News - Your Daily Dose of Magic<br>
                            <a href="{$this->appUrl}" style="color: #667eea; text-decoration: none;">magicians.news</a>
                        </p>
                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #9ca3af;">
                            Don't want to receive these emails? <a href="{$unsubscribeUrl}" style="color: #667eea; text-decoration: underline;">Unsubscribe</a>
                        </p>
                    </td>
                </tr>
                <!-- Footer : END -->

            </table>

            <!--[if mso]>
            </td>
            </tr>
            </table>
            <![endif]-->
        </div>

        <!--[if mso | IE]>
        </td>
        </tr>
        </table>
        <![endif]-->
    </center>
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

            // Add unique Message-ID to prevent Gmail threading/caching issues
            $messageId = '<' . time() . '.' . uniqid() . '@' . parse_url($this->appUrl, PHP_URL_HOST) . '>';
            $mail->addCustomHeader('Message-ID', $messageId);

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
        // Add unique timestamp to force Gmail to re-render each email in thread
        $uniqueId = time() . '-' . uniqid();

        return <<<HTML
<!-- Email ID: {$uniqueId} -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>{$title}</title>
    <style type="text/css" rel="stylesheet" media="all">
    body {
      width: 100% !important;
      height: 100%;
      margin: 0;
      -webkit-text-size-adjust: none;
      background-color: #F2F4F6;
      color: #51545E;
    }

    body, td, th {
      font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    }

    h1 {
      margin-top: 0;
      color: #333333;
      font-size: 22px;
      font-weight: bold;
      text-align: left;
    }

    p {
      margin: .4em 0 1.1875em;
      font-size: 16px;
      line-height: 1.625;
      color: #51545E;
    }

    p.sub {
      font-size: 13px;
    }

    a {
      color: #3869D4;
    }

    .button {
      background-color: #7c6ad6;
      border-top: 10px solid #7c6ad6;
      border-right: 18px solid #7c6ad6;
      border-bottom: 10px solid #7c6ad6;
      border-left: 18px solid #7c6ad6;
      display: inline-block;
      color: #FFF;
      text-decoration: none;
      border-radius: 3px;
      box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16);
      -webkit-text-size-adjust: none;
      box-sizing: border-box;
    }

    .email-wrapper {
      width: 100%;
      margin: 0;
      padding: 0;
      background-color: #F2F4F6;
    }

    .email-content {
      width: 100%;
      margin: 0;
      padding: 0;
    }

    .email-masthead {
      padding: 25px 0;
      text-align: center;
    }

    .email-masthead_name {
      font-size: 16px;
      font-weight: bold;
      color: #A8AAAF;
      text-decoration: none;
    }

    .email-body {
      width: 100%;
      margin: 0;
      padding: 0;
    }

    .email-body_inner {
      width: 570px;
      margin: 0 auto;
      padding: 0;
      background-color: #FFFFFF;
    }

    .email-footer {
      width: 570px;
      margin: 0 auto;
      padding: 0;
      text-align: center;
    }

    .email-footer p {
      color: #A8AAAF;
    }

    .body-action {
      width: 100%;
      margin: 30px auto;
      padding: 0;
      text-align: center;
    }

    .body-sub {
      margin-top: 25px;
      padding-top: 25px;
      border-top: 1px solid #EAEAEC;
    }

    .content-cell {
      padding: 45px;
    }

    @media only screen and (max-width: 600px) {
      .email-body_inner,
      .email-footer {
        width: 100% !important;
      }
    }
    </style>
    <!--[if mso]>
    <style type="text/css">
      .f-fallback  {
        font-family: Arial, sans-serif;
      }
    </style>
    <![endif]-->
</head>
<body style="width: 100% !important; height: 100%; margin: 0; -webkit-text-size-adjust: none; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F2F4F6; color: #51545E;">
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; background-color: #F2F4F6; margin: 0; padding: 0;" bgcolor="#F2F4F6">
      <tr>
        <td align="center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px;">
          <table class="email-content" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 0; padding: 0;">
            <tr>
              <td class="email-masthead" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px; text-align: center; padding: 25px 0;" align="center">
                <a href="{$this->appUrl}" class="email-masthead_name" style="color: #667eea; font-size: 20px; font-weight: bold; text-decoration: none;">
                🎩 Magicians News
                </a>
              </td>
            </tr>
            <tr>
              <td class="email-body" width="570" cellpadding="0" cellspacing="0" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px; width: 100%; margin: 0; padding: 0;">
                <table class="email-body_inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="width: 570px; background-color: #FFFFFF; margin: 0 auto; padding: 0;" bgcolor="#FFFFFF">
                  <tr>
                    <td class="content-cell" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px; padding: 45px;">
                      <h1 style="margin-top: 0; color: #333333; font-size: 22px; font-weight: bold; text-align: left;" align="left">{$greeting}</h1>
                      <p style="font-size: 16px; line-height: 1.625; color: #51545E; margin: .4em 0 1.1875em;">{$body}</p>
                      <table class="body-action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; text-align: center; margin: 30px auto; padding: 0;">
                        <tr>
                          <td align="center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation">
                              <tr>
                                <td align="center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px;">
                                  <a href="{$buttonUrl}" class="button" target="_blank" style="color: #FFF; background-color: #7c6ad6; display: inline-block; text-decoration: none; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); -webkit-text-size-adjust: none; box-sizing: border-box; border-color: #7c6ad6; border-style: solid; border-width: 10px 18px;">{$buttonText}</a>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                      </table>
                      <p style="font-size: 16px; line-height: 1.625; color: #51545E; margin: .4em 0 1.1875em;">{$footer}</p>
                      <table class="body-sub" role="presentation" style="margin-top: 25px; padding-top: 25px; border-top: 1px solid #EAEAEC;">
                        <tr>
                          <td style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px;">
                            <p class="sub" style="font-size: 13px; line-height: 1.625; color: #51545E; margin: .4em 0 1.1875em;">If you're having trouble with the button above, copy and paste the URL below into your web browser.</p>
                            <p class="sub" style="font-size: 13px; line-height: 1.625; color: #51545E; margin: .4em 0 1.1875em;">{$buttonUrl}</p>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px;">
                <table class="email-footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="width: 570px; text-align: center; margin: 0 auto; padding: 0;">
                  <tr>
                    <td class="content-cell" align="center" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px; padding: 45px;">
                      <p class="sub align-center" style="font-size: 13px; line-height: 1.625; text-align: center; color: #A8AAAF; margin: .4em 0 1.1875em;" align="center">
                        Magicians News - Your Daily Dose of Magic
                        <br /><a href="{$this->appUrl}" style="color: #3869D4;">magicians.news</a>
                      </p>
                    </td>
                  </tr>
                </table>
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
