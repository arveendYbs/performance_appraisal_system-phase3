<?php
// config/config.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Application Configuration
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Performance Appraisal System');
define('APP_VERSION', $_ENV['APP_VERSION'] ?? '1.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('BASE_URL', $_ENV['BASE_URL'] ?? 'http://localhost/performance_appraisal_system-phase3');

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'appraisal_system');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Email Configuration
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.office365.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
define('SMTP_ENCRYPTION', $_ENV['SMTP_ENCRYPTION'] ?? 'tls');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? 'it.team@example.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Appraisal System');

// Security Configuration
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_TIMEOUT', $_ENV['SESSION_TIMEOUT'] ?? 3600);

// File Upload Configuration
define('UPLOAD_DIR', $_ENV['UPLOAD_DIR'] ?? 'uploads/');
define('MAX_FILE_SIZE', $_ENV['MAX_FILE_SIZE'] ?? 5242880);

// Pagination
define('RECORDS_PER_PAGE', $_ENV['RECORDS_PER_PAGE'] ?? 10);

// Error Reporting based on environment
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Include required files first
require_once __DIR__ . '/../includes/functions.php';

// Autoload classes
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Include database after autoloading
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/email.php';

?>