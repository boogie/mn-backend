<?php
header('Content-Type: text/plain');

$file = __DIR__ . '/../../src/CMSClient.php';
if (file_exists($file)) {
    echo "File exists. Lines 50-56:\n\n";
    $lines = file($file);
    for ($i = 49; $i < 56 && $i < count($lines); $i++) {
        echo ($i + 1) . ": " . $lines[$i];
    }
} else {
    echo "File not found";
}
