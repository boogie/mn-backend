# GitHub Secrets Setup Guide

## Setting up GitHub Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions → New repository secret

---

## Backend Secrets (`mn-backend` repository)

### FTP Configuration
```
FTP_SERVER = ftp.yourdomain.com
FTP_USERNAME = your_ftp_username
FTP_PASSWORD = your_ftp_password
FTP_SERVER_DIR = /www/magicians.news/api/
```

### Database Configuration
```
DB_HOST = localhost  (or your MySQL host)
DB_NAME = magician_users
DB_USER = your_mysql_username
DB_PASS = your_mysql_password
```

### JWT Configuration
```
JWT_SECRET = <generate-random-secret>
```
**Generate with:**
```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

### CMS API Configuration
```
CMS_API_URL = https://cms.magicians.news/api
CMS_API_KEY = <your-payloadcms-api-key>
```

### Stripe Configuration
```
STRIPE_SECRET_KEY = sk_live_xxx  (from Stripe dashboard)
STRIPE_PUBLISHABLE_KEY = pk_live_xxx  (from Stripe dashboard)
STRIPE_WEBHOOK_SECRET = whsec_xxx  (from Stripe webhook settings)
STRIPE_PRICE_ID = price_xxx  (from Stripe product pricing)
```

### App Configuration
```
APP_URL = https://api.magicians.news
CORS_ORIGIN = https://magicians.news
```

---

## Frontend Secrets (`mn-frontend` repository)

### FTP Configuration
```
FTP_SERVER = ftp.yourdomain.com
FTP_USERNAME = your_ftp_username
FTP_PASSWORD = your_ftp_password
FTP_SERVER_DIR = /www/magicians.news/static/
```

### API Configuration
```
VITE_API_URL = https://api.magicians.news/api
```

### Stripe Configuration
```
VITE_STRIPE_PUBLISHABLE_KEY = pk_live_xxx  (same as backend)
```

---

## Testing Secrets

After adding secrets, you can test by:

1. **Push to main branch**:
   ```bash
   git push origin main
   ```

2. **Or trigger manual deployment**:
   - Go to GitHub Actions tab
   - Select "Deploy Backend to FTP" or "Deploy Frontend to FTP"
   - Click "Run workflow"
   - Select branch: main
   - Click "Run workflow"

3. **Check deployment logs**:
   - Go to Actions tab
   - Click on the running/completed workflow
   - View logs for any errors

---

## Common Issues

### FTP Connection Failed
- Check FTP_SERVER, FTP_USERNAME, FTP_PASSWORD
- Ensure FTP (not SFTP) is enabled on your hosting
- Try connecting manually with FileZilla to verify credentials

### Database Connection Failed
- Verify MySQL is running
- Check DB_HOST (use `localhost` if MySQL is on same server)
- Ensure database `magician_users` exists
- Import schema: `mysql -u user -p magician_users < database/schema.sql`

### CMS API Not Working
- Deploy CMS to Railway first
- Get API key from PayloadCMS admin
- Update CMS_API_URL with correct domain

### Stripe Errors
- Use test keys (sk_test_, pk_test_) for testing
- Switch to live keys (sk_live_, pk_live_) for production
- Verify webhook secret matches Stripe dashboard

---

## Deployment Flow

1. **Push code to GitHub**
2. **GitHub Actions triggers**:
   - Backend: Installs composer dependencies, creates .env, deploys to FTP
   - Frontend: Installs npm dependencies, builds with Vite, deploys dist/ to FTP
3. **Files uploaded to your hosting via FTP**
4. **Site is live!**

---

## File Structure on FTP Server

### Directory Structure:
```
/www/magicians.news/
├── static/              # Frontend (magicians.news)
│   ├── index.html
│   ├── assets/
│   └── .htaccess        # React Router support
└── api/                 # Backend (api.magicians.news)
    ├── .env             # Environment variables (NOT web accessible)
    ├── public/          # Web server document root for api.magicians.news
    │   ├── index.php
    │   └── api/         # API endpoints
    ├── src/
    ├── vendor/
    └── composer.json
```

### Web Server Configuration:
Configure your web server virtual hosts:
- `magicians.news` → Document root: `/www/magicians.news/static/`
- `api.magicians.news` → Document root: `/www/magicians.news/api/public/`

This ensures `.env` files are **outside** the web-accessible directory.

### Frontend .htaccess (for React Router):
Place in `/www/magicians.news/static/.htaccess`:
```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # Don't rewrite files or directories
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d

  # Rewrite everything else to index.html
  RewriteRule ^ index.html [L]
</IfModule>
```

---

## Security Checklist

- [ ] Use strong, unique passwords for FTP and MySQL
- [ ] Generate new JWT_SECRET for production
- [ ] Use Stripe live keys (not test keys)
- [ ] Set correct CORS_ORIGIN
- [ ] Enable HTTPS on your domain
- [ ] Keep secrets in GitHub Secrets (never commit to code)
- [ ] Restrict MySQL access to localhost if possible
- [ ] Use strong MySQL password

---

## Next Steps

1. Add all secrets to both GitHub repositories
2. Import database schema to MySQL
3. Push code to trigger deployment
4. Test the deployed sites
5. Set up Stripe webhook endpoint
6. Configure DNS records for domains
