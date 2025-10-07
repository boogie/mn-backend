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
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
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
