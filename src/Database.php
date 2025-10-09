<?php
namespace MagicianNews;

class Database {
    private static ?Database $instance = null;
    private \PDO $connection;

    private function __construct() {
        // Use SQLite for development
        $dbPath = __DIR__ . '/../database/mn.db';
        $dbDir = dirname($dbPath);

        // Create database directory if it doesn't exist
        if (!file_exists($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $dsn = "sqlite:" . $dbPath;

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new \PDO($dsn, null, null, $options);
            $this->initTables();
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function initTables(): void {
        // Create users table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT,
                name TEXT NOT NULL,
                billing_name TEXT,
                google_id TEXT UNIQUE,
                subscription_status TEXT DEFAULT 'free',
                subscription_end_date TEXT,
                stripe_customer_id TEXT,
                stripe_subscription_id TEXT,
                billing_country TEXT,
                billing_postal_code TEXT,
                billing_city TEXT,
                billing_line1 TEXT,
                vat_number TEXT,
                company_name TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create invoices table for Billingo references
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                stripe_invoice_id TEXT NOT NULL,
                billingo_invoice_id TEXT,
                amount REAL NOT NULL,
                tax_amount REAL NOT NULL,
                currency TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Create comments table for article discussions
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                parent_id INTEGER,
                content TEXT NOT NULL,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (parent_id) REFERENCES comments(id)
            )
        ");

        // Create index for faster article comment lookups
        $this->connection->exec("
            CREATE INDEX IF NOT EXISTS idx_comments_article ON comments(article_id)
        ");

        // Create newsletter table for pre-launch email capture
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS newsletter (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                name TEXT,
                source TEXT DEFAULT 'homepage',
                status TEXT DEFAULT 'subscribed',
                subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create index for faster email lookups
        $this->connection->exec("
            CREATE INDEX IF NOT EXISTS idx_newsletter_email ON newsletter(email)
        ");

        // Create admin invite tokens table (Phase 0)
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS admin_invite_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT UNIQUE NOT NULL,
                created_by_admin INTEGER NOT NULL,
                max_uses INTEGER DEFAULT 1,
                current_uses INTEGER DEFAULT 0,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by_admin) REFERENCES users(id)
            )
        ");

        // Create invite usage tracking table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS invite_usage (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                inviter_user_id INTEGER,
                admin_token_id INTEGER,
                new_user_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (inviter_user_id) REFERENCES users(id),
                FOREIGN KEY (admin_token_id) REFERENCES admin_invite_tokens(id),
                FOREIGN KEY (new_user_id) REFERENCES users(id)
            )
        ");

        // Migrate old schema if needed
        try {
            $columns = $this->connection->query("PRAGMA table_info(users)")->fetchAll();
            $columnNames = array_column($columns, 'name');

            $hasPassword = in_array('password', $columnNames);
            $hasPasswordHash = in_array('password_hash', $columnNames);
            $hasName = in_array('name', $columnNames);
            $hasBillingName = in_array('billing_name', $columnNames);

            // Migration 1: password -> password_hash
            if ($hasPassword && !$hasPasswordHash) {
                $this->connection->exec("ALTER TABLE users RENAME COLUMN password TO password_hash");
            }

            // Migration 2: Add name column if missing
            if (!$hasName) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN name TEXT");
                // Set name to email for existing users
                $this->connection->exec("UPDATE users SET name = email WHERE name IS NULL");
            }

            // Migration 3: Add billing_name if missing (legal name for invoices)
            if (!$hasBillingName) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN billing_name TEXT");
            }

            // Migration 4: Add billing columns if missing
            $billingColumns = ['billing_country', 'billing_postal_code', 'billing_city', 'billing_line1', 'vat_number', 'company_name'];
            foreach ($billingColumns as $col) {
                if (!in_array($col, $columnNames)) {
                    $this->connection->exec("ALTER TABLE users ADD COLUMN $col TEXT");
                }
            }

            // Migration 5: Add google_id for OAuth
            if (!in_array('google_id', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN google_id TEXT");
                $this->connection->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google_id ON users(google_id) WHERE google_id IS NOT NULL");
            }

            // Migration 6: Make password_hash nullable for OAuth users
            // Note: SQLite doesn't support ALTER COLUMN, so we skip this migration
            // OAuth users will have an empty password_hash

            // Migration 7: Add email verification fields
            if (!in_array('email_verified', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN email_verified INTEGER DEFAULT 0");
                // Mark all existing users as verified (they registered before this feature)
                $this->connection->exec("UPDATE users SET email_verified = 1 WHERE email_verified = 0 OR email_verified IS NULL");
            }
            if (!in_array('email_verification_token', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN email_verification_token TEXT");
            }

            // Migration 8: Add password reset fields
            if (!in_array('password_reset_token', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN password_reset_token TEXT");
            }
            if (!in_array('password_reset_expires', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN password_reset_expires DATETIME");
            }

            // Migration 9: Add invite system fields (Phase 0-1)
            if (!in_array('invite_credits', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN invite_credits INTEGER DEFAULT 3");
            }
            if (!in_array('invited_by_user_id', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN invited_by_user_id INTEGER");
            }
            if (!in_array('invite_token', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN invite_token TEXT");
                $this->connection->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_invite_token ON users(invite_token) WHERE invite_token IS NOT NULL");
            }
            if (!in_array('is_active_member', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN is_active_member INTEGER DEFAULT 0");
            }

            // Migration 10: Add shop referral field (Phase 2)
            if (!in_array('registered_through_shop_id', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN registered_through_shop_id INTEGER");
            }

        } catch (\Exception $e) {
            // Migration failed - log to file instead
            file_put_contents(__DIR__ . '/../database/migration_errors.log',
                date('Y-m-d H:i:s') . ": " . $e->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): \PDO {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }
}
