<?php
ob_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/export_functions.php';

$output = ob_get_clean();

echo "<h2>Output Buffer Check</h2>";
echo "<p>Length of captured output: " . strlen($output) . " bytes</p>";

if (strlen($output) > 0) {
    echo "<p style='color: red;'><strong>WARNING: There is output being generated before Excel!</strong></p>";
    echo "<pre>";
    echo "First 500 characters:\n";
    echo htmlspecialchars(substr($output, 0, 500));
    echo "\n\nHex dump of first 50 bytes:\n";
    for ($i = 0; $i < min(50, strlen($output)); $i++) {
        printf("%02X ", ord($output[$i]));
        if (($i + 1) % 16 == 0) echo "\n";
    }
    echo "</pre>";
} else {
    echo "<p style='color: green;'><strong>Good! No output detected.</strong></p>";
}

// Check for headers sent
if (headers_sent($file, $line)) {
    echo "<p style='color: red;'><strong>Headers already sent in $file on line $line</strong></p>";
} else {
    echo "<p style='color: green;'><strong>Headers not sent yet - Good!</strong></p>";
}