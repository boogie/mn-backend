# Email Setup Guide

## Current Status

The email system is currently using PHP's built-in `mail()` function for sending emails. This works in development mode (emails are logged to `database/emails/` directory) but may not work reliably in production without proper SMTP configuration.

## Required Environment Variables

Add these to your production `.env` file:

```bash
# Email Configuration
EMAIL_ENABLED=true                          # Set to true to enable actual email sending
EMAIL_FROM=noreply@magicians.news          # Sender email address
EMAIL_FROM_NAME=Magicians News             # Sender display name
APP_ENV=production                         # Set to production to disable file logging
APP_URL=https://magicians.news             # Frontend URL for email links
```

## Email Features

The system sends emails for:

1. **Email Verification** - Sent automatically on user registration
2. **Password Reset** - Sent when user requests password reset
3. **Future**: Welcome emails, subscription notifications, etc.

## Production Email Options

### Option 1: AWS SES (Recommended)

AWS Simple Email Service is recommended for production. To implement:

1. **Install AWS SDK for PHP:**
   ```bash
   composer require aws/aws-sdk-php
   ```

2. **Add AWS credentials to `.env`:**
   ```bash
   AWS_ACCESS_KEY_ID=your-access-key
   AWS_SECRET_ACCESS_KEY=your-secret-key
   AWS_REGION=us-east-1
   AWS_SES_SENDER=noreply@magicians.news
   ```

3. **Verify sender domain in AWS SES Console:**
   - Go to AWS SES → Verified identities
   - Add `magicians.news` domain
   - Add DNS records (DKIM, SPF, DMARC)

4. **Update `src/Email.php`:**
   Replace the `send()` method to use AWS SES:
   ```php
   private function send(string $to, string $subject, string $html): bool {
       // For development, just log the email instead of sending
       if ($_ENV['APP_ENV'] === 'development' || empty($_ENV['EMAIL_ENABLED'])) {
           $this->logEmail($to, $subject, $html);
           return true;
       }

       // Use AWS SES
       try {
           $client = new \Aws\Ses\SesClient([
               'version' => 'latest',
               'region' => $_ENV['AWS_REGION'] ?? 'us-east-1',
               'credentials' => [
                   'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                   'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
               ],
           ]);

           $result = $client->sendEmail([
               'Source' => "{$this->fromName} <{$this->fromEmail}>",
               'Destination' => ['ToAddresses' => [$to]],
               'Message' => [
                   'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                   'Body' => ['Html' => ['Data' => $html, 'Charset' => 'UTF-8']],
               ],
           ]);

           return true;
       } catch (\Exception $e) {
           error_log("Email send failed: " . $e->getMessage());
           return false;
       }
   }
   ```

### Option 2: Railway SMTP (If using Railway)

If hosting on Railway, you can use an SMTP service:

1. **Add SMTP addon in Railway dashboard**

2. **Add SMTP credentials to `.env`:**
   ```bash
   SMTP_HOST=smtp.example.com
   SMTP_PORT=587
   SMTP_USER=your-username
   SMTP_PASSWORD=your-password
   SMTP_ENCRYPTION=tls
   ```

3. **Install PHPMailer:**
   ```bash
   composer require phpmailer/phpmailer
   ```

4. **Update `src/Email.php` to use PHPMailer**

### Option 3: PHP mail() with SMTP Configuration

If your hosting provider supports it, configure PHP's `mail()` function to use SMTP by adding to `php.ini`:

```ini
SMTP = smtp.example.com
smtp_port = 587
sendmail_from = noreply@magicians.news
```

## Testing Emails

### Development Mode

In development, emails are logged to files instead of being sent:

```bash
# Check email logs
cat database/emails/email-$(date +%Y-%m-%d).log
```

### Production Testing

1. Set `EMAIL_ENABLED=true` in production `.env`
2. Test password reset: Try forgot password with a real email
3. Test verification: Register a new account
4. Check logs: `database/migration_errors.log` and server error logs

## Troubleshooting

### "Email not received"

1. **Check EMAIL_ENABLED is set to true** in production `.env`
2. **Check APP_ENV is set to production** (not 'development')
3. **Check spam folder**
4. **Verify sender domain** is properly configured (SPF, DKIM, DMARC)
5. **Check server logs** for email sending errors

### "Verification banner shows for existing users"

This is fixed in the latest migration. The `email_verified` field is automatically set to `1` for all existing users when the migration runs.

### "Google OAuth users not verified"

This is fixed. Google OAuth users are automatically marked as verified when they log in or register.

## Current Implementation

**File**: `src/Email.php`

**Email Templates**:
- Password Reset: Beautiful HTML template with purple gradient
- Email Verification: Similar template with verification button
- Both include manual link fallback for accessibility

**Security**:
- Password reset tokens expire in 1 hour
- Email verification tokens expire in 24 hours
- Both use cryptographically secure random tokens (64 characters)
- Password reset endpoint doesn't reveal if email exists (prevents enumeration)

## Next Steps

1. ✅ Add email configuration to `.env.example`
2. ⏳ Choose email provider (AWS SES recommended)
3. ⏳ Install required packages
4. ⏳ Update `src/Email.php` with chosen provider
5. ⏳ Verify sender domain
6. ⏳ Test in production

## Questions?

- Check AWS SES documentation: https://docs.aws.amazon.com/ses/
- Check Railway SMTP documentation: https://docs.railway.app/
- Review `src/Email.php` for implementation details
