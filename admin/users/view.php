<?php
// admin/users/view.php
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

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $user->id = $user_id;
    
    if (!$user->readOne()) {
        redirect('index.php', 'User not found.', 'error');
    }
    
    // Get superior details
    $superior = null;
    if ($user->direct_superior) {
        $query = "SELECT name, position FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user->direct_superior]);
        $superior = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get subordinates
    $subordinates_stmt = $user->getSubordinates();
    $subordinates = $subordinates_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get appraisal history
    $query = "SELECT id, status, grade, total_score, appraisal_period_from, appraisal_period_to, 
                     employee_submitted_at, manager_reviewed_at
              FROM appraisals 
              WHERE user_id = ? 
              ORDER BY created_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $appraisal_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("User view error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-person me-2"></i>User Details
            </h1>
            <div>
                <a href="edit.php?id=<?php echo $user->id; ?>" class="btn btn-primary me-2">
                    <i class="bi bi-pencil me-2"></i>Edit User
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Users
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- User Profile Card -->
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
                <span class="badge <?php echo $user->is_active ? 'bg-success' : 'bg-secondary'; ?> ms-1">
                    <?php echo $user->is_active ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-envelope me-2"></i>Contact Information</h6>
            </div>
            <div class="card-body">
                <p><strong>Personal Email:</strong><br><?php echo htmlspecialchars($user->email); ?></p>
                <?php if ($user->emp_email): ?>
                <p><strong>Company Email:</strong><br><?php echo htmlspecialchars($user->emp_email); ?></p>
                <?php endif; ?>
                <p><strong>Employee Number:</strong><br><?php echo htmlspecialchars($user->emp_number); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Basic Information -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Employment Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Department:</strong><br><?php echo htmlspecialchars($user->department); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Site:</strong><br><?php echo htmlspecialchars($user->site); ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Date Joined:</strong><br><?php echo formatDate($user->date_joined); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Years of Service:</strong><br>
                        <?php 
                        $years = floor((time() - strtotime($user->date_joined)) / (365*24*60*60));
                        echo $years . ' year' . ($years != 1 ? 's' : '');
                        ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($superior): ?>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Direct Superior:</strong><br>
                        <?php echo htmlspecialchars($superior['name'] . ' - ' . $superior['position']); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Subordinates -->
        <?php if (!empty($subordinates)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Team Members (<?php echo count($subordinates); ?>)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($subordinates as $subordinate): ?>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                 style="width: 30px; height: 30px;">
                                <i class="bi bi-person-fill text-white" style="font-size: 0.8rem;"></i>
                            </div>
                            <div>
                                <small><strong><?php echo htmlspecialchars($subordinate['name']); ?></strong><br>
                                <?php echo htmlspecialchars($subordinate['position']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Appraisals -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Recent Appraisals</h6>
            </div>
            <div class="card-body">
                <?php if (empty($appraisal_history)): ?>
                <p class="text-muted">No appraisal history found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Score</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appraisal_history as $appraisal): ?>
                            <tr>
                                <td>
                                    <small>
                                        <?php echo formatDate($appraisal['appraisal_period_from'], 'M Y'); ?> - 
                                        <?php echo formatDate($appraisal['appraisal_period_to'], 'M Y'); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($appraisal['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $appraisal['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($appraisal['grade']): ?>
                                    <span class="badge bg-secondary"><?php echo $appraisal['grade']; ?></span>
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appraisal['total_score']): ?>
                                    <?php echo $appraisal['total_score']; ?>%
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $appraisal['employee_submitted_at'] ? 
                                            formatDate($appraisal['employee_submitted_at'], 'M d, Y') : '-'; ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>