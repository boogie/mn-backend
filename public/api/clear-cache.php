<?php
header('Content-Type: application/json');

$result = [];

// Try to clear opcache
if (function_exists('opcache_reset')) {
    $cleared = opcache_reset();
    $result['opcache_reset'] = $cleared ? 'success' : 'failed';
} else {
    $result['opcache_reset'] = 'function_not_available';
}

// Touch the CMSClient file to update modification time
$file = __DIR__ . '/../../src/CMSClient.php';
if (file_exists($file)) {
    touch($file);
    $result['cmsclient_touched'] = true;
    $result['cmsclient_mtime'] = filemtime($file);
} else {
    $result['cmsclient_touched'] = false;
}

echo json_encode($result, JSON_PRETTY_PRINT);
