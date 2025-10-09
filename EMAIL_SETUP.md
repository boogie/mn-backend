# Email Setup Guide

## Current Status

**✅ AWS SES SMTP Integration Implemented**

The email system now uses AWS SES SMTP via PHPMailer for production email sending. In development mode, emails are logged to `database/emails/` directory. The system automatically falls back to PHP's `mail()` function if SMTP credentials are not configured.

## Required Environment Variables

Add these to your production `.env` file or GitHub Secrets:

```bash
# Email Configuration
EMAIL_ENABLED=true                          # Set to true to enable actual email sending
EMAIL_FROM=noreply@magicians.news          # Sender email address (must be verified in AWS SES)
EMAIL_FROM_NAME=Magicians News             # Sender display name
APP_ENV=production                         # Set to production to disable file logging
APP_URL=https://magicians.news             # Frontend URL for email links

# AWS SES SMTP Configuration (Required for production email sending)
SMTP_HOST=email-smtp.eu-north-1.amazonaws.com  # AWS SES SMTP endpoint
SMTP_PORT=587                                   # Port 587 for STARTTLS
SMTP_USER=AKIA...                               # AWS SES SMTP username
SMTP_PASSWORD=your-smtp-password                # AWS SES SMTP password
```

## Email Features

The system sends emails for:

1. **Email Verification** - Sent automatically on user registration
2. **Password Reset** - Sent when user requests password reset
3. **Newsletter Confirmation** - Sent when someone subscribes to the newsletter (with unsubscribe link)

## AWS SES SMTP Setup (Production Email)

**✅ PHPMailer Already Installed**

PHPMailer is already installed via Composer for SMTP email sending. The SMTP credentials are already configured.

### 1. Verify Sender Domain/Email in AWS SES Console

- Go to AWS SES Console → Verified identities
- Verify your sender domain (`magicians.news`) or individual email (`noreply@magicians.news`)
- Add DNS records if verifying a domain:
  - DKIM records (3 CNAME records for email authentication)
  - SPF record (TXT record: `"v=spf1 include:amazonses.com ~all"`)
  - DMARC record (TXT record: `"v=DMARC1; p=none; rua=mailto:admin@magicians.news"`)
- Wait for verification (usually a few minutes after DNS propagation)

### 2. Create SMTP Credentials (If not done already)

- Go to AWS SES Console → SMTP settings
- Click "Create SMTP credentials"
- Enter a username (e.g., `mn-smtp-user`)
- Download and save the SMTP credentials:
  - SMTP Username (looks like: `AKIA...`)
  - SMTP Password (long alphanumeric string)
- Note the SMTP endpoint for your region (e.g., `email-smtp.eu-north-1.amazonaws.com`)

### 3. Add SMTP Credentials to GitHub Secrets

**✅ Already configured** with the following secrets:
- `SMTP_HOST` = `email-smtp.eu-north-1.amazonaws.com`
- `SMTP_PORT` = `587`
- `SMTP_USER` = SMTP username
- `SMTP_PASSWORD` = SMTP password

### 4. Move Out of SES Sandbox (Important!)

By default, AWS SES accounts are in "sandbox mode" which only allows sending to verified email addresses.

To send to any email address:
- Go to AWS SES Console → Account dashboard
- Click "Request production access"
- Fill out the form explaining your use case
- AWS typically approves within 24 hours

**While in sandbox mode**: You can only send emails to verified email addresses. Verify your test email addresses in SES Console → Verified identities.

## Alternative: Using Different SMTP Provider

If you want to use a different SMTP provider (like SendGrid, Mailgun, or Gmail), simply update the SMTP credentials:

```bash
SMTP_HOST=smtp.your-provider.com
SMTP_PORT=587
SMTP_USER=your-username
SMTP_PASSWORD=your-password
```

The PHPMailer implementation will work with any standard SMTP server.

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

1. **Check EMAIL_ENABLED is set to true** in production `.env` or GitHub Secrets
2. **Check APP_ENV is set to production** (not 'development')
3. **Check SMTP credentials are configured** (SMTP_HOST, SMTP_USER, SMTP_PASSWORD, SMTP_PORT)
4. **Check sender email is verified in AWS SES** (must match EMAIL_FROM in .env)
5. **Check if account is in SES sandbox mode** - if yes, recipient email must also be verified
6. **Check spam folder**
7. **Check server error logs** for SMTP errors (look for "SMTP error:" messages)
8. **Verify sender domain** is properly configured (SPF, DKIM, DMARC)

### "SMTP error: Email address is not verified"

Your AWS SES account is in sandbox mode. Either:
- Verify the recipient email address in AWS SES Console, or
- Request production access to send to any email address

### "SMTP Authentication Failed"

Check that:
- SMTP_USER and SMTP_PASSWORD are correctly set in GitHub Secrets
- SMTP credentials are still valid (regenerate if needed in AWS SES Console)
- SMTP_HOST matches your AWS region (e.g., `email-smtp.eu-north-1.amazonaws.com`)

### "Connection timeout" or "Could not connect to SMTP host"

Check that:
- SMTP_PORT is set to 587 (for STARTTLS)
- Your hosting provider allows outbound connections on port 587
- Firewall rules allow SMTP traffic

### "Verification banner shows for existing users"

This is fixed in the latest migration. The `email_verified` field is automatically set to `1` for all existing users when the migration runs.

### "Google OAuth users not verified"

This is fixed. Google OAuth users are automatically marked as verified when they log in or register.

## Current Implementation

**File**: `src/Email.php`

**Email Provider**:
- ✅ AWS SES SMTP integration via PHPMailer
- Automatic fallback to PHP `mail()` if SMTP credentials not configured
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
2. ✅ Choose email provider (AWS SES SMTP)
3. ✅ Install required packages (`phpmailer/phpmailer`)
4. ✅ Update `src/Email.php` with SMTP integration
5. ✅ Add SMTP credentials to GitHub Secrets
6. ⏳ Verify sender domain/email in AWS SES Console
7. ⏳ Move out of SES sandbox mode (if needed)
8. ⏳ Test in production

## Questions?

- Check AWS SES documentation: https://docs.aws.amazon.com/ses/
- Check Railway SMTP documentation: https://docs.railway.app/
- Review `src/Email.php` for implementation details
