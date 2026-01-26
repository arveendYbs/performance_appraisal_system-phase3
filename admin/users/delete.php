<?php
// admin/users/delete.php
require_once __DIR__ . '/../../config/config.php';

/* if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
} */

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    redirect('index.php', 'User ID is required.', 'error');
}

// Prevent self-deletion
if ($user_id == $_SESSION['user_id']) {
    redirect('index.php', 'You cannot delete your own account.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $user->id = $user_id;
    
    if (!$user->readOne()) {
        redirect('index.php', 'User not found.', 'error');
    }
    
    // Check if user has appraisals
    $query = "SELECT COUNT(*) as count FROM appraisals WHERE user_id = ? OR appraiser_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $user_id]);
    $appraisal_count = $stmt->fetch()['count'];
    
    // Check if user has subordinates
    $query = "SELECT COUNT(*) as count FROM users WHERE direct_superior = ? AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $subordinate_count = $stmt->fetch()['count'];
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('index.php', 'Invalid request.', 'error');
        }
        
        if ($subordinate_count > 0) {
            redirect('index.php', 'Cannot delete user: they have ' . $subordinate_count . ' subordinate(s). Please reassign them first.', 'error');
        }
        
        // Soft delete (deactivate) instead of hard delete to preserve data integrity
        if ($user->delete()) {
            logActivity($_SESSION['user_id'], 'DELETE', 'users', $user_id,
                       ['name' => $user->name, 'role' => $user->role], null,
                       'Deactivated user: ' . $user->name);
            
            redirect('index.php', 'User deactivated successfully!', 'success');
        } else {
            redirect('index.php', 'Failed to deactivate user.', 'error');
        }
    }
    
} catch (Exception $e) {
    error_log("User delete error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-trash me-2"></i>Deactivate User
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Deactivation</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This will deactivate the user account. The user will no longer be able to login, but their data will be preserved.
                    <?php if ($appraisal_count > 0): ?>
                    <br><br>This user has <?php echo $appraisal_count; ?> appraisal record(s) that will be preserved.
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <h6>User Details:</h6>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($user->name); ?></p>
                    <p><strong>Employee Number:</strong> <?php echo htmlspecialchars($user->emp_number); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user->email); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($user->position); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($user->department); ?></p>
                    <p><strong>Role:</strong> <?php echo ucfirst($user->role); ?></p>
                </div>
                
                <?php if ($subordinate_count > 0): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-2"></i>
                    <strong>Cannot Deactivate:</strong> This user has <?php echo $subordinate_count; ?> subordinate(s). 
                    Please reassign them to another manager first.
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-warning" <?php echo $subordinate_count > 0 ? 'disabled' : ''; ?>>
                            <i class="bi bi-person-x me-2"></i>Deactivate User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>