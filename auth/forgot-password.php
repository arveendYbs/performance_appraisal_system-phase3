
<?php
// auth/forgot-password.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/email.php';

// If user is already logged in, redirect
if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email) || !validateEmail($email)) {
            $error_message = 'Please enter a valid email address.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $user = new User($db);
                
                // Generate reset token
                $token = $user->generatePasswordResetToken($email);
                
                if ($token) {
                    // Get user details
                    $query = "SELECT id, name FROM users 
                              WHERE (email = ? OR emp_email = ?) AND is_active = 1";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$email, $email]);
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Send reset email
                    $reset_link = BASE_URL . "/auth/reset-password.php?token=" . $token;
                    
                    $email_message = "
                        <div class='info-box'>
                            <p><strong>Password Reset Request</strong></p>
                        </div>
                        <p>We received a request to reset your password for the Performance Appraisal System.</p>
                        <p>Click the button below to reset your password. This link will expire in 1 hour.</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='{$reset_link}' class='button' style='background-color: #0d6efd; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                                Reset Password
                            </a>
                        </p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p style='word-break: break-all; color: #6c757d;'>{$reset_link}</p>
                        <hr>
                        <p><strong>Security Note:</strong></p>
                        <ul>
                            <li>This link will expire in 1 hour</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Your password will not change until you access the link and create a new password</li>
                        </ul>
                    ";
                    
                    $email_sent = sendEmail(
                        $email,
                        'Password Reset Request - ' . APP_NAME,
                        $email_message,
                        $user_data['name'] ?? '',
                        ['email_type' => 'password_reset']
                    );
                    
                    if ($email_sent) {
                        logActivity($user_data['id'], 'PASSWORD_RESET_REQUEST', 'users', $user_data['id'], 
                                   null, null, 'Password reset requested');
                        
                        $success_message = 'Password reset instructions have been sent to your email address. Please check your inbox.';
                    } else {
                        $error_message = 'Failed to send reset email. Please try again or contact support.';
                    }
                } else {
                    // Don't reveal if email exists or not (security best practice)
                    $success_message = 'If an account exists with this email, password reset instructions have been sent.';
                }
                
            } catch (Exception $e) {
                error_log("Forgot password error: " . $e->getMessage());
                $error_message = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        .forgot-header {
            background: white;
            padding: 2rem;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-card">
            <div class="forgot-header">
                <i class="bi bi-lock-fill text-primary" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Forgot Password?</h2>
                <p class="text-muted">Enter your email and we'll send you reset instructions</p>
            </div>
            
            <div class="p-4">
                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
                <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter your email" required autofocus>
                        </div>
                        <small class="text-muted">Enter the email you used to register</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Send Reset Link
                        </button>
                        <a href="login.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Login
                        </a>
                    </div> 
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <small class="text-white-50">
                <?php echo APP_NAME; ?> - © <?php echo date('Y'); ?>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

