<?php
/**
 * Database Migration Endpoint
 * Called automatically after deployment to ensure schema is up to date
 */

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

try {
    // Initialize database (this triggers migrations in initTables)
    $db = \MagicianNews\Database::getInstance();

    // Run a test query to verify everything works
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM users");

    echo json_encode([
        'success' => true,
        'message' => 'Database migrations completed successfully',
        'user_count' => $result['count']
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
