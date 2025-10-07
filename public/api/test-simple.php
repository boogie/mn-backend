<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../vendor/autoload.php';

    echo json_encode([
        'autoload_works' => true,
        'guzzle_exists' => class_exists('GuzzleHttp\Client'),
    ]);
} catch (\Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
