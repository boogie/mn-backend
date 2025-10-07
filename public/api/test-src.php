<?php
header('Content-Type: application/json');

echo json_encode([
    'src_database_exists' => file_exists(__DIR__ . '/../../src/Database.php'),
    'src_cmsclient_exists' => file_exists(__DIR__ . '/../../src/CMSClient.php'),
    'src_auth_exists' => file_exists(__DIR__ . '/../../src/Auth.php'),
    'src_config_exists' => file_exists(__DIR__ . '/../../src/config.php'),
]);
