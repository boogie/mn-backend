# Email Setup Guide

## Current Status

**✅ AWS SES Integration Implemented**

The email system now uses AWS Simple Email Service (SES) for production email sending. In development mode, emails are logged to `database/emails/` directory. The system automatically falls back to PHP's `mail()` function if AWS credentials are not configured.

## Required Environment Variables

Add these to your production `.env` file:

```bash
# Email Configuration
EMAIL_ENABLED=true                          # Set to true to enable actual email sending
EMAIL_FROM=noreply@magicians.news          # Sender email address (must be verified in AWS SES)
EMAIL_FROM_NAME=Magicians News             # Sender display name
APP_ENV=production                         # Set to production to disable file logging
APP_URL=https://magicians.news             # Frontend URL for email links

# AWS SES Configuration (Required for production email sending)
AWS_ACCESS_KEY_ID=your-access-key-id       # AWS IAM user access key
AWS_SECRET_ACCESS_KEY=your-secret-key      # AWS IAM user secret key
AWS_REGION=eu-west-1                       # AWS region (e.g., us-east-1, eu-west-1)
```

## Email Features

The system sends emails for:

1. **Email Verification** - Sent automatically on user registration
2. **Password Reset** - Sent when user requests password reset
3. **Newsletter Confirmation** - Sent when someone subscribes to the newsletter (with unsubscribe link)

## AWS SES Setup (Production Email)

**✅ AWS SDK Already Installed**

The AWS SDK for PHP is already installed via Composer. Follow these steps to configure AWS SES:

### 1. Verify Sender Domain in AWS SES Console

- Go to AWS SES Console → Verified identities
- Click "Create identity"
- Choose "Domain" and enter `magicians.news`
- Add the provided DNS records to your domain registrar:
  - DKIM records (3 CNAME records for email authentication)
  - SPF record (TXT record: `"v=spf1 include:amazonses.com ~all"`)
  - DMARC record (TXT record: `"v=DMARC1; p=none; rua=mailto:admin@magicians.news"`)
- Wait for verification (usually a few minutes after DNS propagation)

### 2. Create IAM User for SES

- Go to AWS IAM Console → Users → Create user
- User name: `mn-ses-sender`
- Attach policy: `AmazonSESFullAccess` (or create custom policy with only SendEmail permission)
- Create access key → Choose "Application running outside AWS"
- Save the Access Key ID and Secret Access Key

### 3. Add AWS Credentials to Environment Variables

Add these to your production `.env` file or GitHub Secrets:

```bash
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=abc123...
AWS_REGION=eu-west-1
```

### 4. Move Out of SES Sandbox (Important!)

By default, AWS SES accounts are in "sandbox mode" which only allows sending to verified email addresses.

To send to any email address:
- Go to AWS SES Console → Account dashboard
- Click "Request production access"
- Fill out the form explaining your use case
- AWS typically approves within 24 hours

**While in sandbox mode**: You can only send emails to verified email addresses. Verify your test email addresses in SES Console → Verified identities.

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
3. **Check AWS credentials are configured** (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_REGION)
4. **Check sender email is verified in AWS SES** (must match EMAIL_FROM in .env)
5. **Check if account is in SES sandbox mode** - if yes, recipient email must also be verified
6. **Check spam folder**
7. **Check server error logs** for AWS SES errors (look for "AWS SES error:" messages)
8. **Verify sender domain** is properly configured (SPF, DKIM, DMARC)

### "AWS SES error: Email address is not verified"

Your AWS SES account is in sandbox mode. Either:
- Verify the recipient email address in AWS SES Console, or
- Request production access to send to any email address

### "AWS SES error: MessageRejected"

Check that:
- Sender email (EMAIL_FROM) is verified in AWS SES
- Domain has proper DNS records (SPF, DKIM, DMARC)
- Email content doesn't trigger spam filters

### "Verification banner shows for existing users"

This is fixed in the latest migration. The `email_verified` field is automatically set to `1` for all existing users when the migration runs.

### "Google OAuth users not verified"

This is fixed. Google OAuth users are automatically marked as verified when they log in or register.

## Current Implementation

**File**: `src/Email.php`

**Email Provider**:
- ✅ AWS SES integration implemented
- Automatic fallback to PHP `mail()` if AWS credentials not configured
- Development mode logs emails to `database/emails/` instead of sending

**Email Templates**:
- Password Reset: Beautiful HTML template with purple gradient
- Email Verification: Similar template with verification button
- Newsletter Confirmation: Welcome email with unsubscribe link
- All templates include manual link fallback for accessibility

**Security**:
- Password reset tokens expire in 1 hour
- Email verification tokens expire in 24 hours
- Newsletter unsubscribe tokens are permanent (64 characters)
- All tokens use cryptographically secure random generation
- Password reset endpoint doesn't reveal if email exists (prevents enumeration)

## Implementation Status

1. ✅ Add email configuration to `.env.example`
2. ✅ Choose email provider (AWS SES)
3. ✅ Install required packages (`aws/aws-sdk-php`)
4. ✅ Update `src/Email.php` with AWS SES integration
5. ⏳ Verify sender domain in AWS SES Console
6. ⏳ Add AWS credentials to production environment
7. ⏳ Move out of SES sandbox mode
8. ⏳ Test in production

## Questions?

- Check AWS SES documentation: https://docs.aws.amazon.com/ses/
- Check Railway SMTP documentation: https://docs.railway.app/
- Review `src/Email.php` for implementation details
