
// admin/index.php
<?php
require_once __DIR__ . '/../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../includes/header.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get admin dashboard statistics
    $stats = [];
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    // Total forms
    $stmt = $db->query("SELECT COUNT(*) as total FROM forms WHERE is_active = 1");
    $stats['total_forms'] = $stmt->fetch()['total'];
    
    // Total appraisals
    $stmt = $db->query("SELECT COUNT(*) as total FROM appraisals");
    $stats['total_appraisals'] = $stmt->fetch()['total'];
    
    // Pending reviews
    $stmt = $db->query("SELECT COUNT(*) as total FROM appraisals WHERE status = 'submitted'");
    $stats['pending_reviews'] = $stmt->fetch()['pending_reviews'];
    
    // Recent activities
    $stmt = $db->query("SELECT al.*, u.name as user_name FROM audit_logs al 
                       JOIN users u ON al.user_id = u.id 
                       ORDER BY al.created_at DESC LIMIT 10");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $stats = [];
    $recent_activities = [];
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-gear me-2"></i>Administration Dashboard
        </h1>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
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
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="users/create.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus-fill d-block mb-2" style="font-size: 2rem;"></i>
                            Add New User
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="forms/create.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-file-plus d-block mb-2" style="font-size: 2rem;"></i>
                            Create Form
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="audit/" class="btn btn-outline-info w-100">
                            <i class="bi bi-clock-history d-block mb-2" style="font-size: 2rem;"></i>
                            View Audit Logs
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="forms/" class="btn btn-outline-warning w-100">
                            <i class="bi bi-gear d-block mb-2" style="font-size: 2rem;"></i>
                            Manage Forms
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                <p class="text-muted">No recent activities to display.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Details</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                                <td><small><?php echo htmlspecialchars($activity['table_name']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($activity['details'] ?? ''); ?></small></td>
                                <td><small><?php echo formatDate($activity['created_at'], 'M d, H:i'); ?></small></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
