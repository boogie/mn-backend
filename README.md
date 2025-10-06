# Magician News - Backend API

PHP backend for Magician News with authentication, subscription management, and CMS integration.

## Features

- 🔐 JWT authentication
- 💳 Stripe subscription integration
- 🔒 Paywall enforcement
- 📡 CMS API proxy
- 🪝 Webhook handling

## Tech Stack

- PHP 8.1+
- MySQL/MariaDB
- Composer packages:
  - phpdotenv
  - firebase/php-jwt
  - guzzlehttp/guzzle
  - stripe/stripe-php

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- MySQL/MariaDB database
- Composer
- PHP hosting with .htaccess support

### Installation

1. **Install dependencies**:
```bash
composer install
```

2. **Configure environment**:
```bash
cp .env.example .env
# Edit .env with your settings
```

3. **Setup database**:
```bash
# Import the schema into your MySQL database
mysql -u your_user -p magician_users < database/schema.sql

# Or use phpMyAdmin to import database/schema.sql
```

4. **Configure environment variables** in `.env`:
```env
# Database
DB_HOST=localhost
DB_NAME=magician_users
DB_USER=your_mysql_user
DB_PASS=your_mysql_password

# JWT
JWT_SECRET=your-random-secret-key-here
JWT_EXPIRY=3600

# CMS API
CMS_API_URL=https://cms.magician.news/api
CMS_API_KEY=your-cms-api-key

# Stripe
STRIPE_SECRET_KEY=sk_live_xxx
STRIPE_PUBLISHABLE_KEY=pk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
STRIPE_PRICE_ID=price_xxx

# App
APP_URL=https://magician.news
CORS_ORIGIN=https://magician.news
```

### Local Development

```bash
# Start PHP built-in server
php -S localhost:8000 -t public

# API will be available at http://localhost:8000/api/
```

### Deployment to PHP Hosting

1. Upload all files to your hosting
2. Point your domain to the `public/` directory
3. Ensure `.htaccess` is enabled
4. Set environment variables (or use .env file)
5. Import database schema

## Project Structure

```
mn-backend/
├── public/
│   ├── index.php          # API info endpoint
│   ├── .htaccess          # URL rewriting
│   └── api/
│       ├── auth.php       # Authentication endpoints
│       ├── subscription.php  # Subscription endpoints
│       ├── content.php    # Content proxy (paywalled)
│       └── webhook.php    # Stripe webhooks
├── src/
│   ├── config.php         # Configuration & CORS
│   ├── Database.php       # Database connection
│   ├── Auth.php           # Authentication logic
│   ├── Subscription.php   # Subscription logic
│   ├── CMSClient.php      # CMS API client
│   └── Response.php       # JSON response helper
├── database/
│   └── schema.sql         # Database schema
├── composer.json
├── .env.example
└── README.md
```

## API Endpoints

### Authentication

**Register**:
```http
POST /api/auth?action=register
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secure-password"
}
```

**Login**:
```http
POST /api/auth?action=login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secure-password"
}
```

**Get Current User**:
```http
GET /api/auth
Authorization: Bearer <token>
```

### Subscription

**Check Status**:
```http
GET /api/subscription?action=status
Authorization: Bearer <token>
```

**Create Checkout Session**:
```http
POST /api/subscription?action=checkout
Authorization: Bearer <token>
```

### Content (Requires Active Subscription)

**List Articles**:
```http
GET /api/content?page=1&limit=10
Authorization: Bearer <token>
```

**Get Single Article**:
```http
GET /api/content?id=article-id
Authorization: Bearer <token>
```

**Search Articles**:
```http
GET /api/content?search=query
Authorization: Bearer <token>
```

### Webhooks

**Stripe Webhook**:
```http
POST /api/webhook
Stripe-Signature: <signature>

<stripe webhook payload>
```

## Database Schema

### Users Table
- `id` - Primary key
- `email` - Unique email address
- `password_hash` - Bcrypt hashed password
- `subscription_status` - free/active/cancelled/expired
- `subscription_end_date` - When subscription expires
- `created_at` - Account creation date
- `updated_at` - Last update date

### Subscriptions Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `stripe_subscription_id` - Stripe subscription ID
- `status` - Subscription status from Stripe
- `current_period_end` - Current billing period end
- `created_at` - Creation date
- `updated_at` - Last update date

## Stripe Setup

1. Create a Stripe account
2. Create a product ($1/month subscription)
3. Get API keys (Secret, Publishable, Webhook secret)
4. Configure webhook endpoint: `https://your-domain.com/api/webhook`
5. Subscribe to events:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`

## Security Notes

- JWT tokens stored in frontend localStorage
- Passwords hashed with bcrypt
- CORS configured for your frontend domain
- Prepared statements for SQL queries
- Webhook signature verification
- HTTPS required in production

## Troubleshooting

### CORS Issues
- Check `CORS_ORIGIN` in `.env`
- Ensure your hosting supports `.htaccess`

### Database Connection
- Verify MySQL credentials
- Check if database exists
- Ensure PDO MySQL extension is enabled

### Stripe Webhooks
- Use Stripe CLI for local testing
- Verify webhook secret matches
- Check webhook logs in Stripe dashboard

### API Errors
- Check PHP error logs
- Enable error display temporarily:
  ```php
  ini_set('display_errors', 1);
  ```

## Testing

### Test Authentication:
```bash
curl -X POST http://localhost:8000/api/auth?action=register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

### Test Subscription Status:
```bash
curl -X GET http://localhost:8000/api/subscription?action=status \
  -H "Authorization: Bearer <your-token>"
```

## Production Checklist

- [ ] Change `JWT_SECRET` to a random string
- [ ] Use production Stripe keys
- [ ] Configure HTTPS
- [ ] Set `CORS_ORIGIN` to production domain
- [ ] Disable PHP error display
- [ ] Setup database backups
- [ ] Configure webhook endpoint in Stripe
- [ ] Test all endpoints
- [ ] Monitor error logs

## Support

For issues or questions, contact the development team.

## License

Proprietary - Magician News
