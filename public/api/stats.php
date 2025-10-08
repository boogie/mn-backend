<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Stats;
use MagicianNews\Response;

// Get the secret token from query parameter or header
$token = $_GET['token'] ?? $_SERVER['HTTP_X_STATS_TOKEN'] ?? '';

if (empty($token)) {
    Response::error('Missing secret token', 401);
}

$stats = new Stats();

if (!$stats->verifyToken($token)) {
    Response::error('Invalid secret token', 403);
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'all';

        switch ($action) {
            case 'all':
                // Return all stats
                $data = $stats->getStats();
                Response::success($data);
                break;

            case 'growth':
                // Return growth metrics
                $data = $stats->getGrowthMetrics();
                Response::success($data);
                break;

            case 'summary':
                // Return a summary for quick overview
                $fullStats = $stats->getStats();
                $summary = [
                    'total_users' => $fullStats['users']['total'],
                    'active_subscribers' => $fullStats['subscriptions']['active_subscribers'],
                    'total_content' => $fullStats['content']['total_content_pieces'],
                    'total_comments' => $fullStats['engagement']['total_comments'],
                    'estimated_mrr' => $fullStats['revenue']['estimated_mrr'],
                    'generated_at' => $fullStats['generated_at'],
                ];
                Response::success($summary);
                break;

            default:
                Response::error('Invalid action. Use: all, growth, or summary');
        }
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (\Exception $e) {
    Response::error('Failed to fetch stats: ' . $e->getMessage(), 500);
}
