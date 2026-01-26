
<?php
// auth/reset-password.php
require_once __DIR__ . '/../config/config.php';

// If user is already logged in, redirect
if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$token = $_GET['token'] ?? '';
$success_message = '';
$error_message = '';
$token_valid = false;
$user_data = null;

// Verify token
if (empty($token)) {
    $error_message = 'Invalid or missing reset token.';
} else {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        
        $user_data = $user->verifyResetToken($token);
        
        if ($user_data) {
            $token_valid = true;
        } else {
            $error_message = 'This password reset link is invalid or has expired. Please request a new one.';
        }
    } catch (Exception $e) {
        error_log("Reset password verification error: " . $e->getMessage());
        $error_message = 'An error occurred. Please try again.';
    }
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token_valid) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password)) {
            $error_message = 'Please enter a new password.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $user = new User($db);
                
                if ($user->resetPassword($token, $new_password)) {
                    logActivity($user_data['user_id'], 'PASSWORD_RESET', 'users', $user_data['user_id'], 
                               null, null, 'Password reset completed');
                    
                    $success_message = 'Your password has been reset successfully! You can now login with your new password.';
                    $token_valid = false; // Prevent form from showing again
                } else {
                    $error_message = 'Failed to reset password. Please try again or request a new reset link.';
                }
            } catch (Exception $e) {
                error_log("Reset password error: " . $e->getMessage());
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
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        .reset-header {
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
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-card">
            <div class="reset-header">
                <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Reset Password</h2>
                <?php if ($token_valid && $user_data): ?>
                <p class="text-muted">Hello, <?php echo htmlspecialchars($user_data['name']); ?>!</p>
                <p class="text-muted">Create your new password below</p>
                <?php endif; ?>
            </div>
            
            <div class="p-4">
                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php if (!$token_valid): ?>
                <div class="text-center mt-3">
                    <a href="forgot-password.php" class="btn btn-primary">
                        Request New Reset Link
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>
                <?php elseif ($token_valid): ?>
                <form method="POST" action="" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   placeholder="Enter new password" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="strengthBar"></div>
                        <small class="text-muted" id="strengthText">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted" id="matchText"></small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Reset Password
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
    <script>
        // Toggle password visibility
        document.getElementById('toggleNewPassword')?.addEventListener('click', function() {
            togglePasswordVisibility('new_password', this);
        });
        
        document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
            togglePasswordVisibility('confirm_password', this);
        });
        
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Password strength indicator
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            const colors = ['#dc3545', '#ffc107', '#17a2b8', '#28a745'];
            const texts = ['Weak', 'Fair', 'Good', 'Strong'];
            const widths = ['25%', '50%', '75%', '100%'];
            
            if (password.length > 0) {
                const index = Math.min(strength - 1, 3);
                strengthBar.style.width = widths[index];
                strengthBar.style.backgroundColor = colors[index];
                strengthText.textContent = texts[index] + ' password';
                strengthText.style.color = colors[index];
            } else {
                strengthBar.style.width = '0%';
                strengthText.textContent = 'Minimum 6 characters';
                strengthText.style.color = '#6c757d';
            }
        });
        
        // Password match indicator
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('matchText');
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchText.textContent = '✓ Passwords match';
                    matchText.style.color = '#28a745';
                } else {
                    matchText.textContent = '✗ Passwords do not match';
                    matchText.style.color = '#dc3545';
                }
            } else {
                matchText.textContent = '';
            }
        });
    </script>
</body>
</html>
