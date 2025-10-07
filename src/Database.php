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
            \error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed");
        }
    }

    private function initTables(): void {
        // Create users table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                subscription_status TEXT DEFAULT 'free',
                subscription_end_date TEXT,
                stripe_customer_id TEXT,
                stripe_subscription_id TEXT,
                billing_name TEXT,
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

        // Migrate old schema if needed (rename password to password_hash)
        try {
            $columns = $this->connection->query("PRAGMA table_info(users)")->fetchAll();
            $hasPassword = false;
            $hasPasswordHash = false;

            foreach ($columns as $col) {
                if ($col['name'] === 'password') $hasPassword = true;
                if ($col['name'] === 'password_hash') $hasPasswordHash = true;
            }

            // If old schema detected, migrate it
            if ($hasPassword && !$hasPasswordHash) {
                $this->connection->exec("
                    ALTER TABLE users RENAME TO users_old;
                    CREATE TABLE users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        email TEXT UNIQUE NOT NULL,
                        password_hash TEXT NOT NULL,
                        subscription_status TEXT DEFAULT 'free',
                        subscription_end_date TEXT,
                        stripe_customer_id TEXT,
                        stripe_subscription_id TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    INSERT INTO users (id, email, password_hash, subscription_status, stripe_customer_id, stripe_subscription_id, created_at, updated_at)
                    SELECT id, email, password, subscription_status, stripe_customer_id, stripe_subscription_id, created_at, updated_at FROM users_old;
                    DROP TABLE users_old;
                ");
                \error_log("Migrated users table from 'password' to 'password_hash' column");
            }
        } catch (\Exception $e) {
            \error_log("Schema migration check failed: " . $e->getMessage());
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
