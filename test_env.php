
<?php
// test_env.php (Create this in project root for testing)

require_once 'vendor/autoload.php';
require_once 'config/config.php';

echo "<h2>Environment Configuration Test</h2>";
echo "<pre>";

echo "App Name: " . APP_NAME . "\n";
echo "App Version: " . APP_VERSION . "\n";
echo "App Environment: " . APP_ENV . "\n";
echo "Debug Mode: " . (APP_DEBUG ? 'Enabled' : 'Disabled') . "\n";
echo "Base URL: " . BASE_URL . "\n\n";

echo "Database Host: " . DB_HOST . "\n";
echo "Database Name: " . DB_NAME . "\n";
echo "Database User: " . DB_USER . "\n";
echo "Database Password: " . (DB_PASS ? '***SET***' : '***EMPTY***') . "\n\n";

echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP User: " . SMTP_USERNAME . "\n";
echo "SMTP Password: " . (SMTP_PASSWORD ? '***SET***' : '***EMPTY***') . "\n";
echo "SMTP From: " . SMTP_FROM . "\n\n";

// Test database connection
try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "Database Connection: ✅ SUCCESS\n";
} catch (Exception $e) {
    echo "Database Connection: ❌ FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

// DELETE THIS FILE AFTER TESTING!
?>