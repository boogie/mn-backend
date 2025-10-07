# Setup Guide for Magician News Backend

## Generated Configuration Values

### JWT Secret (Generated)
```
JWT_SECRET=263e2bf0b856bb5d4acef63ea797d1f83b34653844de09db5e68218178c83b99
```

### CMS Configuration
```
CMS_API_URL=https://cms.magicians.news/api
CMS_API_KEY=<copy-from-cms-admin>
```

**To get your CMS_API_KEY:**
1. Go to https://cms.magicians.news/admin
2. Navigate to API Keys collection
3. Find your API key (starts with `mn_`)
4. Copy the key value

### App Configuration
```
APP_URL=https://api.magicians.news
CORS_ORIGIN=https://magicians.news
```

## Required Information from You

### FTP Configuration
You need to provide:
- FTP_SERVER
- FTP_USERNAME
- FTP_PASSWORD
- FTP_SERVER_DIR=/www/magicians.news/api/

### Database Configuration
You need to provide:
- DB_HOST (usually localhost)
- DB_NAME=mn
- DB_USER
- DB_PASS

### Stripe Configuration (OPTIONAL - Currently Disabled)
Stripe can be added later. For now, use placeholder values:
- STRIPE_SECRET_KEY=sk_test_placeholder
- STRIPE_PUBLISHABLE_KEY=pk_test_placeholder
- STRIPE_WEBHOOK_SECRET=whsec_placeholder
- STRIPE_PRICE_ID=price_placeholder

## Setup Steps

### 1. Add GitHub Secrets to mn-backend Repository

Go to: `https://github.com/<your-username>/mn-backend/settings/secrets/actions`

Add these secrets:

#### FTP Configuration
```
FTP_SERVER=<your-ftp-server>
FTP_USERNAME=<your-ftp-username>
FTP_PASSWORD=<your-ftp-password>
FTP_SERVER_DIR=/www/magicians.news/api/
```

#### Database Configuration
```
DB_HOST=localhost
DB_NAME=mn
DB_USER=<your-mysql-username>
DB_PASS=<your-mysql-password>
```

#### JWT Configuration
```
JWT_SECRET=263e2bf0b856bb5d4acef63ea797d1f83b34653844de09db5e68218178c83b99
```

#### CMS API Configuration
```
CMS_API_URL=https://cms.magicians.news/api
CMS_API_KEY=<your-api-key-from-cms>
```

#### Stripe Configuration (OPTIONAL - Use Placeholders for Now)
```
STRIPE_SECRET_KEY=sk_test_placeholder
STRIPE_PUBLISHABLE_KEY=pk_test_placeholder
STRIPE_WEBHOOK_SECRET=whsec_placeholder
STRIPE_PRICE_ID=price_placeholder
```

#### App Configuration
```
APP_URL=https://api.magicians.news
CORS_ORIGIN=https://magicians.news
```

### 2. Import Database Schema

On your hosting, import the database schema:

```bash
mysql -u <your-user> -p mn < database/schema.sql
```

Or use phpMyAdmin to import `database/schema.sql`

### 3. Configure Web Server

Point `api.magicians.news` to `/www/magicians.news/api/public/`

This ensures the `.env` file is outside the web-accessible directory.

### 4. Deploy

Push to GitHub to trigger deployment:

```bash
cd /Users/boogie/Workspace/mn-backend
git add -A
git commit -m "Backend configuration"
git push origin main
```

### 5. Test the API

Once deployed, test:

```bash
curl https://api.magicians.news/
```

Should return:
```json
{
  "message": "Magician News API",
  "version": "1.0.0"
}
```

## Next Steps

1. Test authentication flow
2. Test content proxy (CMS integration)
3. (Later) Set up Stripe product and webhook
4. (Later) Test subscription flow

## Troubleshooting

### Database Connection Failed
- Verify MySQL is running
- Check DB credentials
- Ensure database `mn` exists
- Import schema if needed

### CMS API Not Working
- Verify CMS_API_KEY is correct
- Test CMS endpoint: `curl https://cms.magicians.news/api/articles`

### CORS Issues
- Ensure CORS_ORIGIN matches your frontend domain
- Check that `.htaccess` is enabled on hosting
