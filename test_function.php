<?php
// test_function.php
require_once __DIR__ . '/config/config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>Function Test</h2>";

echo "<p><strong>PHPMailer class exists:</strong> " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? '✅ YES' : '❌ NO') . "</p>";

echo "<p><strong>sendEmail function exists:</strong> " . (function_exists('sendEmail') ? '✅ YES' : '❌ NO') . "</p>";

echo "<p><strong>sendAppraisalSubmissionEmails function exists:</strong> " . (function_exists('sendAppraisalSubmissionEmails') ? '✅ YES' : '❌ NO') . "</p>";

echo "<p><strong>getEmailTemplate function exists:</strong> " . (function_exists('getEmailTemplate') ? '✅ YES' : '❌ NO') . "</p>";

echo "<p><strong>logEmail function exists:</strong> " . (function_exists('logEmail') ? '✅ YES' : '❌ NO') . "</p>";

echo "<hr>";

echo "<h3>Email Config Constants:</h3>";
echo "<p>SMTP_FROM: " . (defined('SMTP_FROM') ? SMTP_FROM : '❌ NOT DEFINED') . "</p>";
echo "<p>SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : '❌ NOT DEFINED') . "</p>";

echo "<hr>";

// Try to call the function with a test ID
echo "<h3>Attempting to call sendAppraisalSubmissionEmails(999)...</h3>";

if (function_exists('sendAppraisalSubmissionEmails')) {
    try {
        error_log("TEST: Calling sendAppraisalSubmissionEmails from test_function.php");
        $result = sendAppraisalSubmissionEmails(999);
        echo "<p>Function called. Result: " . ($result ? 'TRUE' : 'FALSE') . "</p>";
        echo "<p>Check log_viewer.php to see if logs appeared</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>EXCEPTION: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Function does not exist!</p>";
}

echo "<hr>";
echo "<p><a href='log_viewer.php'>View Logs</a> | <a href='index.php'>Dashboard</a></p>";
?>