<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Auth;
use MagicianNews\Subscription;
use MagicianNews\CMSClient;
use MagicianNews\Response;

$auth = new Auth();
$subscription = new Subscription();

try {
    $cms = new CMSClient();
} catch (\Exception $e) {
    error_log("Failed to initialize CMSClient: " . $e->getMessage());
    Response::error("CMS connection error: " . $e->getMessage(), 500);
}

// Verify authentication (TEMPORARILY DISABLED FOR TESTING)
// TODO: Re-enable authentication once we have proper user registration
// $token = $auth->getAuthHeader();
// if (!$token) {
//     Response::unauthorized();
// }

// try {
//     $decoded = $auth->verifyToken($token);
//     $userId = $decoded['user_id'];
// } catch (\Exception $e) {
//     Response::unauthorized('Invalid token');
// }

// Check subscription (PAYWALL) - DISABLED FOR NOW
// if (!$subscription->isSubscribed($userId)) {
//     Response::forbidden('Subscription required');
// }

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        $search = $_GET['search'] ?? null;

        if ($id) {
            // Get single article
            $article = $cms->getArticle($id);
            Response::success($article);
        } elseif ($search) {
            // Search articles
            $results = $cms->searchArticles($search);
            Response::success($results);
        } else {
            // List articles
            $limit = (int)($_GET['limit'] ?? 10);
            $page = (int)($_GET['page'] ?? 1);

            $articles = $cms->getArticles($limit, $page);
            Response::success($articles);
        }
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (\Exception $e) {
    error_log("Content API Error: " . $e->getMessage());
    Response::error($e->getMessage(), 500);
}
