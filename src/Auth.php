<?php
namespace MagicianNews;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Last updated: 2025-10-09 15:45 - Short token deployment
class Auth {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function register(string $email, string $password, string $name): array {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Invalid email address");
        }

        // Validate name
        if (empty(trim($name))) {
            throw new \Exception("Name is required");
        }

        // Check if user exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$email]
        );

        if ($existing) {
            throw new \Exception("Email already registered");
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));

        // Insert user
        $this->db->query(
            "INSERT INTO users (email, password_hash, name, subscription_status, email_verification_token, email_verified) VALUES (?, ?, ?, ?, ?, ?)",
            [$email, $passwordHash, trim($name), 'free', $verificationToken, 0]
        );

        $userId = $this->db->getConnection()->lastInsertId();

        // Send verification email
        try {
            $emailService = new Email();
            $emailService->sendVerificationEmail($email, $name, $verificationToken);
        } catch (\Exception $e) {
            // Don't fail registration if email sending fails
        }

        // Fetch the created user
        $user = $this->db->fetchOne(
            "SELECT id, email, name, subscription_status, subscription_end_date, email_verified, created_at FROM users WHERE id = ?",
            [$userId]
        );

        // Generate token
        $token = $this->generateToken($userId, $email);

        return [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'subscription_status' => $user['subscription_status'],
                'subscription_end_date' => $user['subscription_end_date'],
                'email_verified' => (bool)$user['email_verified'],
                'created_at' => $user['created_at']
            ],
            'token' => $token
        ];
    }

    public function login(string $email, string $password, bool $rememberMe = true): array {
        $user = $this->db->fetchOne(
            "SELECT id, email, name, password_hash, subscription_status, subscription_end_date, email_verified, created_at
             FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            throw new \Exception("Invalid credentials");
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new \Exception("Invalid credentials");
        }

        // Generate token with appropriate expiry
        $token = $this->generateToken($user['id'], $user['email'], $rememberMe);

        return [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'subscription_status' => $user['subscription_status'],
                'subscription_end_date' => $user['subscription_end_date'],
                'email_verified' => (bool)$user['email_verified'],
                'created_at' => $user['created_at']
            ],
            'token' => $token
        ];
    }

    public function verifyToken(string $token): array {
        try {
            $decoded = JWT::decode(
                $token,
                new Key($_ENV['JWT_SECRET'], 'HS256')
            );

            return [
                'user_id' => $decoded->user_id,
                'email' => $decoded->email
            ];
        } catch (\Exception $e) {
            throw new \Exception("Invalid token");
        }
    }

    public function getCurrentUser(string $token): ?array {
        try {
            $decoded = $this->verifyToken($token);

            $user = $this->db->fetchOne(
                "SELECT id, email, name, subscription_status, subscription_end_date, email_verified, created_at
                 FROM users WHERE id = ?",
                [$decoded['user_id']]
            );

            if (!$user) {
                return null;
            }

            // Convert email_verified to boolean
            $user['email_verified'] = (bool)$user['email_verified'];

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getUserFromToken(string $token): ?array {
        return $this->getCurrentUser($token);
    }

    public function generateToken(int $userId, string $email, bool $rememberMe = true): string {
        // If remember_me is true, set expiry to 365 days (forever)
        // If remember_me is false, use the default JWT_EXPIRY from env (24 hours)
        $expiry = $rememberMe ? (365 * 24 * 60 * 60) : (int)$_ENV['JWT_EXPIRY'];

        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + $expiry
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    public function getAuthHeader(): ?string {
        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Generate a short, human-friendly token (6 characters)
     *
     * GMAIL THREADING FIX (2025-10-09):
     * Switched from 64-character hex tokens to 6-character alphanumeric tokens for better UX.
     * The short tokens work with Email.php's anti-threading mechanism:
     * - Same token within 1 hour = same Message-ID = proper Gmail threading
     * - Different tokens = different Message-IDs = separate email threads
     *
     * Excludes similar-looking characters: 0,O,1,I,l,5,S to prevent user confusion.
     */
    private function generateShortToken(): string {
        // Safe characters: no 0/O, 1/I/l, 5/S confusion
        $chars = '234679ABCDEFGHJKMNPQRTUVWXYZ';
        $token = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < 6; $i++) {
            $token .= $chars[random_int(0, $max)];
        }

        return $token;
    }

    /**
     * Request password reset - sends email with reset token
     *
     * TOKEN REUSE STRATEGY (2025-10-09):
     * If a valid unexpired token already exists, we reuse it instead of generating a new one.
     * This creates a better user experience:
     * - Multiple password reset requests = same URL = user won't get confused by different links
     * - Works with Gmail threading fix in Email.php: same token = same Message-ID = proper threading
     * - Prevents token confusion: user sees consistent reset link across multiple emails
     */
    public function requestPasswordReset(string $email): bool {
        $user = $this->db->fetchOne(
            "SELECT id, email, name, password_reset_token, password_reset_expires FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            // Don't reveal if email exists or not for security
            return true;
        }

        // Check if there's a valid existing token - REUSE it if found
        $resetToken = null;
        if ($user['password_reset_token'] && $user['password_reset_expires']) {
            $expiresTime = strtotime($user['password_reset_expires']);
            if ($expiresTime > time()) {
                // Reuse existing valid token - this maintains consistent URLs and Message-IDs
                $resetToken = $user['password_reset_token'];
            }
        }

        // Generate new short token only if no valid token exists
        if (!$resetToken) {
            $resetToken = $this->generateShortToken();
        }

        // Set/extend expiration to 1 hour from now
        $expires = date('Y-m-d H:i:s', time() + 3600);

        // Store token (update expiration even if reusing token)
        $this->db->query(
            "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?",
            [$resetToken, $expires, $user['id']]
        );

        // Send reset email (will have same URL and Message-ID if token was reused)
        $emailService = new Email();
        $emailService->sendPasswordResetEmail($user['email'], $user['name'], $resetToken);

        return true;
    }

    /**
     * Reset password using token
     */
    public function resetPassword(string $token, string $newPassword): bool {
        // Find user with valid token
        $user = $this->db->fetchOne(
            "SELECT id FROM users
             WHERE password_reset_token = ?
             AND password_reset_expires > datetime('now')",
            [$token]
        );

        if (!$user) {
            throw new \Exception("Invalid or expired reset token");
        }

        // Hash new password
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update password and clear reset token
        $this->db->query(
            "UPDATE users SET
             password_hash = ?,
             password_reset_token = NULL,
             password_reset_expires = NULL
             WHERE id = ?",
            [$passwordHash, $user['id']]
        );

        return true;
    }

    /**
     * Verify email using token
     */
    public function verifyEmail(string $token): array {
        $user = $this->db->fetchOne(
            "SELECT id, email, name FROM users WHERE email_verification_token = ?",
            [$token]
        );

        if (!$user) {
            throw new \Exception("Invalid verification token");
        }

        // Mark email as verified
        $this->db->query(
            "UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE id = ?",
            [$user['id']]
        );

        // Generate login token
        $jwtToken = $this->generateToken($user['id'], $user['email']);

        return [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'email_verified' => true
            ],
            'token' => $jwtToken
        ];
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(string $email): bool {
        $user = $this->db->fetchOne(
            "SELECT id, email, name, email_verified FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            throw new \Exception("User not found");
        }

        if ($user['email_verified']) {
            throw new \Exception("Email already verified");
        }

        // Generate new token
        $verificationToken = bin2hex(random_bytes(32));

        $this->db->query(
            "UPDATE users SET email_verification_token = ? WHERE id = ?",
            [$verificationToken, $user['id']]
        );

        // Send email
        $emailService = new Email();
        $emailService->sendVerificationEmail($user['email'], $user['name'], $verificationToken);

        return true;
    }
}
