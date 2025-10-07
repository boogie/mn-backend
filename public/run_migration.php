<?php
/**
 * Migration Runner - Run once then DELETE this file
 *
 * Access: https://api.magicians.news/run_migration.php?token=YOUR_SECRET_TOKEN
 *
 * IMPORTANT: Delete this file after running!
 */

// Security: Change this to a random string and pass it as ?token=YOUR_SECRET_TOKEN
define('MIGRATION_TOKEN', 'change-this-to-random-string-' . md5('your-secret'));

if (!isset($_GET['token']) || $_GET['token'] !== MIGRATION_TOKEN) {
    die('Unauthorized');
}

require_once __DIR__ . '/../src/config.php';

use MagicianNews\Database;

try {
    $db = Database::getInstance()->getConnection();

    echo "<h1>Running Migration: Add Google OAuth Support</h1>";
    echo "<pre>";

    // Check if columns already exist
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Migration already applied (google_id column exists)\n";
        echo "\nNothing to do.\n";
        exit;
    }

    echo "Adding google_id column...\n";
    $db->exec("
        ALTER TABLE users
        ADD COLUMN google_id VARCHAR(255) UNIQUE NULL AFTER email
    ");
    echo "✓ google_id column added\n";

    echo "\nAdding name column...\n";
    $db->exec("
        ALTER TABLE users
        ADD COLUMN name VARCHAR(255) NULL AFTER google_id
    ");
    echo "✓ name column added\n";

    echo "\nAdding google_id index...\n";
    $db->exec("
        ALTER TABLE users
        ADD INDEX idx_google_id (google_id)
    ");
    echo "✓ google_id index added\n";

    echo "\nMaking password_hash nullable...\n";
    $db->exec("
        ALTER TABLE users
        MODIFY COLUMN password_hash VARCHAR(255) NULL
    ");
    echo "✓ password_hash is now nullable\n";

    echo "\n\n✅ Migration completed successfully!\n";
    echo "\n⚠️  IMPORTANT: Delete this file (run_migration.php) now for security!\n";

    echo "</pre>";

} catch (\PDOException $e) {
    echo "<h2 style='color: red;'>Migration Failed</h2>";
    echo "<pre style='color: red;'>";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nYou may need to run this migration manually via phpMyAdmin.\n";
    echo "SQL file location: /database/migrations/add_google_oauth.sql";
    echo "</pre>";
}
