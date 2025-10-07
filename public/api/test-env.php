<?php
require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

$env_check = [
    'PHP_VERSION' => phpversion(),
    'ENV_FILE_EXISTS' => file_exists(__DIR__ . '/../../.env'),
    'CMS_API_URL' => $_ENV['CMS_API_URL'] ?? 'NOT SET',
    'CMS_API_KEY_SET' => !empty($_ENV['CMS_API_KEY']),
    'CORS_ORIGIN' => $_ENV['CORS_ORIGIN'] ?? 'NOT SET',
    'COMPOSER_AUTOLOAD_EXISTS' => file_exists(__DIR__ . '/../../vendor/autoload.php'),
];

echo json_encode($env_check, JSON_PRETTY_PRINT);
