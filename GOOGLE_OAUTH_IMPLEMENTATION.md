# Google OAuth Implementation Guide

## ✅ Completed Steps

### 1. Backend Dependencies
- Added `league/oauth2-google` to `composer.json`
- Library will be installed during deployment

### 2. Database Migration
- Created `/database/migrations/add_google_oauth.sql`
- Adds:
  - `google_id VARCHAR(255)` column to users table
  - `name VARCHAR(255)` column for storing user's name
  - Makes `password_hash` nullable (Google users won't have passwords)
  - Adds index on `google_id` for fast lookups

**Action Required:** Run this migration on your production database

### 3. Environment Variables
- Updated `.env.example` with Google OAuth config:
  - `GOOGLE_CLIENT_ID`
  - `GOOGLE_CLIENT_SECRET`
  - `GOOGLE_REDIRECT_URI`

## 📝 Remaining Implementation Steps

### Step 1: Get Google OAuth Credentials
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable Google+ API
4. Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"
5. Set authorized redirect URIs:
   - `https://api.magicians.news/oauth/google/callback`
   - `http://localhost:8000/oauth/google/callback` (for testing)
6. Copy Client ID and Client Secret to `.env` file

### Step 2: Create GoogleOAuth Service Class

Create `/src/GoogleOAuth.php`:

```php
<?php
namespace MagicianNews;

use League\OAuth2\Client\Provider\Google;

class GoogleOAuth {
    private Google $provider;

    public function __construct() {
        $this->provider = new Google([
            'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
            'redirectUri'  => $_ENV['GOOGLE_REDIRECT_URI'],
        ]);
    }

    public function getAuthorizationUrl(): string {
        return $this->provider->getAuthorizationUrl([
            'scope' => ['email', 'profile']
        ]);
    }

    public function getAccessToken(string $code): array {
        try {
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            $user = $this->provider->getResourceOwner($token);

            return [
                'google_id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'avatar' => $user->getAvatar(),
            ];
        } catch (\Exception $e) {
            error_log("Google OAuth Error: " . $e->getMessage());
            throw new \Exception("Failed to authenticate with Google");
        }
    }
}
```

### Step 3: Create OAuth API Endpoint

Create `/public/api/oauth/google.php`:

```php
<?php
require_once __DIR__ . '/../../../src/config.php';

use MagicianNews\\Auth;
use MagicianNews\\GoogleOAuth;
use MagicianNews\\Database;
use MagicianNews\\Response;

$oauth = new GoogleOAuth();
$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Handle callback from Google
if (isset($_GET['code'])) {
    try {
        // Exchange code for user info
        $googleUser = $oauth->getAccessToken($_GET['code']);

        // Check if user exists
        $stmt = $db->prepare("
            SELECT id, email FROM users
            WHERE google_id = ? OR email = ?
        ");
        $stmt->execute([$googleUser['google_id'], $googleUser['email']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user) {
            // Update existing user with Google ID if not set
            if (!$user['google_id']) {
                $stmt = $db->prepare("
                    UPDATE users
                    SET google_id = ?, name = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $googleUser['google_id'],
                    $googleUser['name'],
                    $user['id']
                ]);
            }
            $userId = $user['id'];
        } else {
            // Create new user
            $stmt = $db->prepare("
                INSERT INTO users (email, google_id, name, password_hash)
                VALUES (?, ?, ?, NULL)
            ");
            $stmt->execute([
                $googleUser['email'],
                $googleUser['google_id'],
                $googleUser['name']
            ]);
            $userId = $db->lastInsertId();
        }

        // Generate JWT token
        $token = $auth->generateToken($userId, $googleUser['email']);

        // Redirect to frontend with token
        $frontendUrl = $_ENV['APP_URL'];
        header("Location: {$frontendUrl}/oauth/callback?token={$token}");
        exit;

    } catch (\Exception $e) {
        error_log("OAuth callback error: " . $e->getMessage());
        $frontendUrl = $_ENV['APP_URL'];
        header("Location: {$frontendUrl}/login?error=oauth_failed");
        exit;
    }
}

// Initial OAuth redirect
header("Location: " . $oauth->getAuthorizationUrl());
exit;
```

### Step 4: Update Auth Endpoints

Modify `/public/api/auth.php` to handle Google-authenticated users:

```php
// In the GET handler (getCurrentUser), update query:
$stmt = $db->prepare("
    SELECT id, email, name, subscription_status
    FROM users
    WHERE id = ?
");
```

### Step 5: Frontend Implementation

#### Update `/src/pages/Login.tsx`:

Add Google login button:

```typescript
const handleGoogleLogin = () => {
  // Redirect to backend OAuth endpoint
  window.location.href = `${import.meta.env.VITE_API_URL}/oauth/google`
}

// Add button in JSX:
<button
  type="button"
  onClick={handleGoogleLogin}
  className="google-button"
>
  <img src="/google-icon.svg" alt="Google" />
  Continue with Google
</button>
```

#### Create OAuth Callback Handler

Create `/src/pages/OAuthCallback.tsx`:

```typescript
import { useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { api } from '../utils/api'

export function OAuthCallback() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { setUser, setIsAuthenticated } = useAuth()

  useEffect(() => {
    const token = searchParams.get('token')
    const error = searchParams.get('error')

    if (error) {
      navigate('/login?error=oauth_failed')
      return
    }

    if (token) {
      // Store token
      api.setToken(token)

      // Fetch user data
      api.getCurrentUser().then(user => {
        if (user) {
          setUser(user)
          setIsAuthenticated(true)
          navigate('/articles')
        } else {
          navigate('/login')
        }
      })
    }
  }, [searchParams])

  return <div>Authenticating...</div>
}
```

#### Update App Routing

Add route in `/src/App.tsx`:

```typescript
import { OAuthCallback } from './pages/OAuthCallback'

// Add route:
<Route path="/oauth/callback" element={<OAuthCallback />} />
```

#### Update TypeScript Types

In `/src/types/index.ts`, add `name` field:

```typescript
export interface User {
  id: number
  email: string
  name?: string  // Add this
  subscription_status: 'free' | 'active' | 'cancelled' | 'expired'
}
```

### Step 6: Styling

Add Google button styles to Login.css:

```css
.google-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  background: white;
  color: #444;
  border-radius: 4px;
  font-size: 16px;
  cursor: pointer;
  transition: background-color 0.2s;
  margin-bottom: 16px;
}

.google-button:hover {
  background-color: #f5f5f5;
}

.google-button img {
  width: 20px;
  height: 20px;
}
```

### Step 7: Testing Checklist

- [ ] Run database migration
- [ ] Add Google OAuth credentials to `.env`
- [ ] Test OAuth flow locally
- [ ] Test user creation with Google
- [ ] Test linking existing account
- [ ] Test token generation and authentication
- [ ] Deploy backend changes
- [ ] Deploy frontend changes
- [ ] Test on production

## Security Considerations

1. **HTTPS Required**: OAuth requires HTTPS in production
2. **CSRF Protection**: The OAuth2 library handles state parameter automatically
3. **Token Security**: JWT tokens stored in localStorage (consider httpOnly cookies for better security)
4. **Email Verification**: Google-authenticated emails are already verified

## Troubleshooting

### "redirect_uri_mismatch" Error
- Ensure the redirect URI in Google Console exactly matches `GOOGLE_REDIRECT_URI` in .env
- Include both http (local) and https (production) URIs

### "invalid_client" Error
- Check Client ID and Client Secret are correct
- Ensure credentials are for a Web application type

### Database Errors
- Run the migration script
- Check that password_hash column is nullable

## Next Steps After Implementation

1. Add profile picture support (store Google avatar URL)
2. Add "Sign in with Google" to Register page
3. Allow users to link/unlink Google account in profile
4. Add remember me functionality
5. Implement refresh tokens for long-term sessions
