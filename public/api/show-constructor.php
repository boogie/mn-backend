<?php
header('Content-Type: text/plain');

$file = __DIR__ . '/../../src/CMSClient.php';
if (file_exists($file)) {
    echo "CMSClient constructor area (lines 26-35):\n\n";
    $lines = file($file);
    for ($i = 25; $i < 36 && $i < count($lines); $i++) {
        echo ($i + 1) . ": " . $lines[$i];
    }
} else {
    echo "File not found";
}
