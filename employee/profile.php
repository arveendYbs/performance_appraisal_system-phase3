<?php
// employee/profile.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$error_message = '';
$success_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $user->id = $_SESSION['user_id'];
    
    if (!$user->readOne()) {
        redirect('index.php', 'User not found.', 'error');
    }
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid request. Please try again.';
        } else {
            $email = sanitize($_POST['email'] ?? '');
            $emp_email = sanitize($_POST['emp_email'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $updates = [];
            
            // Validate email
            if (empty($email) || !validateEmail($email)) {
                $error_message = 'Valid email is required.';
            } elseif (!empty($emp_email) && !validateEmail($emp_email)) {
                $error_message = 'Invalid company email format.';
            } 
            // Validate password if provided
            elseif (!empty($new_password) || !empty($current_password) || !empty($confirm_password)) {
                if (empty($current_password)) {
                    $error_message = 'Current password is required to change password.';
                } elseif (empty($new_password)) {
                    $error_message = 'New password is required.';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'New password must be at least 6 characters.';
                } elseif (!password_verify($current_password, $user->password)) {
                    $error_message = 'Current password is incorrect.';
                }
            }
            
            // If no errors, proceed with updates
            if (empty($error_message)) {
                try {
                    $db->beginTransaction();
                    
                    // Update emails if changed
                    if ($email !== $user->email || $emp_email !== $user->emp_email) {
                        $query = "UPDATE users SET email = ?, emp_email = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$email, $emp_email, $user->id]);
                        
                        $_SESSION['user_email'] = $email;
                        $updates[] = 'email';
                    }
                    
                    // Update password if provided
                    if (!empty($new_password) && !empty($current_password)) {
                        $hashed_password = password_hash($new_password, HASH_ALGO);
                        $query = "UPDATE users SET password = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$hashed_password, $user->id]);
                        
                        $updates[] = 'password';
                    }
                    
                    $db->commit();
                    
                    if (!empty($updates)) {
                        logActivity($_SESSION['user_id'], 'UPDATE', 'users', $user->id, null,
                                   ['updates' => $updates], 'Updated profile: ' . implode(', ', $updates));
                        
                        $success_message = 'Profile updated successfully!';
                        
                        // Refresh user data
                        $user->readOne();
                    } else {
                        $error_message = 'No changes detected.';
                    }
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Profile update error: " . $e->getMessage());
                    $error_message = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error_message = 'An error occurred. Please try again.';
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-person-circle me-2"></i>My Profile
        </h1>
    </div>
</div>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card">
            <div class="card-body text-center">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                     style="width: 80px; height: 80px;">
                    <i class="bi bi-person-fill text-white" style="font-size: 2rem;"></i>
                </div>
                <h5><?php echo htmlspecialchars($user->name); ?></h5>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user->position); ?></p>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user->department); ?></p>
                <span class="badge bg-<?php echo $user->role == 'admin' ? 'danger' : ($user->role == 'manager' ? 'warning' : 'info'); ?>">
                    <?php echo ucfirst($user->role); ?>
                </span>
            </div>
        </div>
        
        <!-- Profile Details -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Profile Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted">Employee Number</small>
                    <p class="mb-0"><?php echo htmlspecialchars($user->emp_number); ?></p>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">Department</small>
                    <p class="mb-0"><?php echo htmlspecialchars($user->department); ?></p>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">Site/Location</small>
                    <p class="mb-0"><?php echo htmlspecialchars($user->site ?: 'N/A'); ?></p>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">Date Joined</small>
                    <p class="mb-0"><?php echo formatDate($user->date_joined, 'M d, Y'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Edit Profile Form -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Update Profile</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <h6>Contact Information</h6>
                    <div class="mb-3">
                        <label for="email" class="form-label">Personal Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo htmlspecialchars($user->email); ?>">
                        <div class="form-text">This email is used for login and notifications</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="emp_email" class="form-label">Company Email</label>
                        <input type="email" class="form-control" id="emp_email" name="emp_email"
                               value="<?php echo htmlspecialchars($user->emp_email ?: ''); ?>">
                        <div class="form-text">Optional - can also be used for login</div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6>Change Password</h6>
                    <p class="text-muted small">Leave all password fields blank to keep current password</p>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                        <div class="form-text">Required to change password</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password validation
document.addEventListener('DOMContentLoaded', function() {
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const form = document.querySelector('form');
    
    function validatePasswords() {
        // Check if any password field has content
        const anyPasswordFilled = currentPassword.value || newPassword.value || confirmPassword.value;
        
        if (anyPasswordFilled) {
            // If any password field is filled, all password fields become required
            if (!currentPassword.value) {
                currentPassword.setCustomValidity('Current password is required to change password');
            } else {
                currentPassword.setCustomValidity('');
            }
            
            if (!newPassword.value) {
                newPassword.setCustomValidity('New password is required');
            } else if (newPassword.value.length < 6) {
                newPassword.setCustomValidity('Password must be at least 6 characters');
            } else {
                newPassword.setCustomValidity('');
            }
            
            if (!confirmPassword.value) {
                confirmPassword.setCustomValidity('Please confirm your new password');
            } else if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        } else {
            // If all password fields are empty, clear validation
            currentPassword.setCustomValidity('');
            newPassword.setCustomValidity('');
            confirmPassword.setCustomValidity('');
        }
    }
    
    currentPassword.addEventListener('input', validatePasswords);
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
    
    form.addEventListener('submit', function(e) {
        validatePasswords();
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>