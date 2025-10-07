<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use MagicianNews\CMSClient;

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();

    $cms = new CMSClient();
    $articles = $cms->getArticles(2, 1);

    echo json_encode([
        'success' => true,
        'data' => $articles
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
