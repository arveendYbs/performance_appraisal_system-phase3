<?php
// test_email.php - DELETE THIS FILE AFTER TESTING
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/email.php';
require_once __DIR__ . '/classes/Database.php';
// Only allow admins to access this
if (!isLoggedIn() || !hasRole('admin')) {
    die('Access denied. Admin only.');
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = $_POST['test_email'] ?? '';
    
    if (!empty($test_email) && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        
        // Test 1: Simple email
        $test_message = "
            <h3>Test Email</h3>
            <p>This is a test email from your Performance Appraisal System.</p>
            <p><strong>Date/Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p>If you're receiving this, your email configuration is working correctly!</p>
        ";
        
        $result = sendEmail(
            $test_email,
            'Test Email - Performance Appraisal System',
            $test_message,
            'Test User',
            ['email_type' => 'test']
        );
        
        if ($result) {
            $message = "✅ Test email sent successfully to {$test_email}! Check your inbox (and spam folder).";
            $success = true;
        } else {
            $message = "❌ Failed to send test email. Check error logs for details.";
            $success = false;
        }
        
    } else {
        $message = "❌ Please enter a valid email address.";
        $success = false;
    }
}

 $database = new Database();
    $db = $database->getConnection();
// Check email logs
$email_logs = [];
if ($db) {
    try {
        $log_query = "SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 10";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute();
        $email_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignore if table doesn't exist yet
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">📧 Email Configuration Test</h3>
                </div>
                <div class="card-body">
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Email Configuration Info -->
                    <div class="alert alert-info">
                        <h5>📋 Current Email Configuration:</h5>
                        <ul class="mb-0">
                            <li><strong>From Email:</strong> <?php echo SMTP_FROM ?? 'Not set'; ?></li>
                            <li><strong>From Name:</strong> <?php echo SMTP_FROM_NAME ?? 'Not set'; ?></li>
                            <li><strong>PHP mail() function:</strong> <?php echo function_exists('mail') ? '✅ Available' : '❌ Not available'; ?></li>
                        </ul>
                    </div>
                    
                    <!-- Test Form -->
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Send test email to:</label>
                            <input type="email" name="test_email" class="form-control" 
                                   placeholder="your-email@example.com" required>
                            <small class="text-muted">Enter your email address to receive a test email</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send Test Email
                        </button>
                        
                        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary">
                            Back to Dashboard
                        </a>
                    </form>
                    
                    <!-- PHP Mail Configuration Check -->
                    <div class="mt-4">
                        <h5>🔧 PHP Mail Configuration:</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <tr>
                                    <td><strong>SMTP (php.ini):</strong></td>
                                    <td><?php echo ini_get('SMTP') ?: 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>smtp_port:</strong></td>
                                    <td><?php echo ini_get('smtp_port') ?: 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>sendmail_from:</strong></td>
                                    <td><?php echo ini_get('sendmail_from') ?: 'Not configured'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>sendmail_path:</strong></td>
                                    <td><?php echo ini_get('sendmail_path') ?: 'Not configured'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Email Logs -->
            <?php if (!empty($email_logs)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">📝 Recent Email Logs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($email_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($log['sent_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($log['subject'], 0, 50)); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $log['email_type']; ?></span></td>
                                    <td>
                                        <?php if ($log['status'] === 'sent'): ?>
                                        <span class="badge bg-success">✓ Sent</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">✗ Failed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Troubleshooting Guide -->
            <div class="card mt-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">🔍 Troubleshooting Guide</h5>
                </div>
                <div class="card-body">
                    <h6>If emails are not working:</h6>
                    <ol>
                        <li><strong>For XAMPP on Windows:</strong>
                            <ul>
                                <li>Install a mail server like <a href="https://www.hmailserver.com/" target="_blank">hMailServer</a> or use Gmail SMTP</li>
                                <li>Configure <code>php.ini</code> with SMTP settings</li>
                                <li>Restart Apache after changes</li>
                            </ul>
                        </li>
                        
                        <li><strong>For Production Server:</strong>
                            <ul>
                                <li>Check if <code>mail()</code> function is enabled</li>
                                <li>Verify SPF and DKIM records</li>
                                <li>Check server's mail logs</li>
                            </ul>
                        </li>
                        
                        <li><strong>Use PHPMailer (Recommended):</strong>
                            <ul>
                                <li>Install: <code>composer require phpmailer/phpmailer</code></li>
                                <li>Configure with Gmail/SendGrid/Mailgun SMTP</li>
                            </ul>
                        </li>
                        
                        <li><strong>Check Spam Folder:</strong>
                            <ul>
                                <li>Test emails often land in spam initially</li>
                                <li>Mark as "Not Spam" to train the filter</li>
                            </ul>
                        </li>
                        
                        <li><strong>Check Error Logs:</strong>
                            <ul>
                                <li>PHP error log: Check XAMPP/logs/php_error_log</li>
                                <li>Apache error log: Check XAMPP/logs/error_log</li>
                            </ul>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>