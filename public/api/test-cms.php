<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\CMSClient;
use MagicianNews\Response;

header('Content-Type: application/json');

try {
    $cms = new CMSClient();
    $articles = $cms->getArticles(2, 1);
    Response::success($articles);
} catch (\Exception $e) {
    Response::error("CMS Test Error: " . $e->getMessage(), 500);
}
