<?php
namespace MagicianNews;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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

        // Insert user
        $this->db->query(
            "INSERT INTO users (email, password_hash, name, subscription_status) VALUES (?, ?, ?, ?)",
            [$email, $passwordHash, trim($name), 'free']
        );

        $userId = $this->db->getConnection()->lastInsertId();

        // Fetch the created user
        $user = $this->db->fetchOne(
            "SELECT id, email, name, subscription_status, subscription_end_date, created_at FROM users WHERE id = ?",
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
                'created_at' => $user['created_at']
            ],
            'token' => $token
        ];
    }

    public function login(string $email, string $password): array {
        $user = $this->db->fetchOne(
            "SELECT id, email, name, password_hash, subscription_status, subscription_end_date, created_at
             FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            throw new \Exception("Invalid credentials");
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new \Exception("Invalid credentials");
        }

        // Generate token
        $token = $this->generateToken($user['id'], $user['email']);

        return [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'subscription_status' => $user['subscription_status'],
                'subscription_end_date' => $user['subscription_end_date'],
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
                "SELECT id, email, name, subscription_status, subscription_end_date, created_at
                 FROM users WHERE id = ?",
                [$decoded['user_id']]
            );

            if (!$user) {
                return null;
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getUserFromToken(string $token): ?array {
        return $this->getCurrentUser($token);
    }

    public function generateToken(int $userId, string $email): string {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + (int)$_ENV['JWT_EXPIRY']
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
}
