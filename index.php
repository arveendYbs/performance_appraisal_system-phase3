

<?php
// index.php (Main Dashboard)

require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get dashboard statistics
    $stats = [];
    
    if (hasRole('admin')) {
        // Admin dashboard stats
        $stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
        $stats['total_users'] = $stmt->fetch()['total_users'];
        
        $stmt = $db->query("SELECT COUNT(*) as total_forms FROM forms WHERE is_active = 1");
        $stats['total_forms'] = $stmt->fetch()['total_forms'];
        
        $stmt = $db->query("SELECT COUNT(*) as total_appraisals FROM appraisals");
        $stats['total_appraisals'] = $stmt->fetch()['total_appraisals'];
        
        $stmt = $db->query("SELECT COUNT(*) as pending_reviews FROM appraisals WHERE status = 'submitted'");
        $stats['pending_reviews'] = $stmt->fetch()['pending_reviews'];
        
    } elseif (hasRole('manager')) {
        // Manager dashboard stats
        $stmt = $db->prepare("SELECT COUNT(*) as team_members FROM users WHERE direct_superior = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['team_members'] = $stmt->fetch()['team_members'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as pending_reviews 
                             FROM appraisals a 
                             JOIN users u ON a.user_id = u.id 
                             WHERE u.direct_superior = ? AND a.status = 'submitted'");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['pending_reviews'] = $stmt->fetch()['pending_reviews'];
    }
    
    // Get current user's appraisal status
    $appraisal = new Appraisal($db);
    $current_appraisal = null;
    if ($appraisal->getCurrentAppraisal($_SESSION['user_id'])) {
        $current_appraisal = [
            'id' => $appraisal->id,
            'status' => $appraisal->status,
            'period_from' => $appraisal->appraisal_period_from,
            'period_to' => $appraisal->appraisal_period_to,
            'grade' => $appraisal->grade
        ];
    }
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = [];
    $current_appraisal = null;
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
            <small class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</small>
        </h1>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <?php if (hasRole('admin')): ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Forms</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_forms'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-file-earmark-text fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Appraisals</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_appraisals'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clipboard-data fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Reviews</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_reviews'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clock-history fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif (hasRole('manager')): ?>
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Team Members</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['team_members'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Reviews</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_reviews'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clipboard-check fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Current Appraisal Status -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>My Current Appraisal</h5>
            </div>
            <div class="card-body">
                <?php if ($current_appraisal): ?>
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6>Appraisal Period: <?php echo formatDate($current_appraisal['period_from']); ?> - <?php echo formatDate($current_appraisal['period_to']); ?></h6>
                        <p class="mb-2">
                            <span class="badge <?php echo getStatusBadgeClass($current_appraisal['status']); ?> me-2">
                                <?php echo ucwords(str_replace('_', ' ', $current_appraisal['status'])); ?>
                            </span>
                            <?php if ($current_appraisal['grade']): ?>
                            <span class="badge bg-secondary">Grade: <?php echo $current_appraisal['grade']; ?></span>
                            <?php endif; ?>
                        </p>
                        <small class="text-muted">
                            <?php
                            switch ($current_appraisal['status']) {
                                case 'draft':
                                    echo 'You can continue working on your appraisal and submit it when ready.';
                                    break;
                                case 'submitted':
                                    echo 'Your appraisal has been submitted and is waiting for manager review.';
                                    break;
                                case 'in_review':
                                    echo 'Your manager is currently reviewing your appraisal.';
                                    break;
                                case 'completed':
                                    echo 'Your appraisal has been completed and finalized.';
                                    break;
                            }
                            ?>
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($current_appraisal['status'] == 'draft'): ?>
                        <a href="<?php echo BASE_URL; ?>/employee/appraisal/continue.php" class="btn btn-primary">
                            <i class="bi bi-pencil me-2"></i>Continue Appraisal
                        </a>
                        <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/employee/appraisal/view.php?id=<?php echo $current_appraisal['id']; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-eye me-2"></i>View Appraisal
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-clipboard-x display-1 text-muted mb-3"></i>
                    <h5>No Active Appraisal</h5>
                    <p class="text-muted mb-3">You don't have any active appraisal at the moment.</p>
                    <a href="<?php echo BASE_URL; ?>/employee/appraisal/start.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Start New Appraisal
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (hasRole('admin')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/admin/users/create.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus-fill d-block mb-2" style="font-size: 2rem;"></i>
                            Add New User
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/admin/forms/" class="btn btn-outline-success w-100">
                            <i class="bi bi-file-plus d-block mb-2" style="font-size: 2rem;"></i>
                            Manage Forms
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('manager') || hasRole('admin')): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/manager/review/pending.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-clipboard-check d-block mb-2" style="font-size: 2rem;"></i>
                            Review Appraisals
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo BASE_URL; ?>/employee/profile.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-person-circle d-block mb-2" style="font-size: 2rem;"></i>
                            My Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>