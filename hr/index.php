<?php
// hr/index.php
require_once __DIR__ . '/../config/config.php';

// Check if user is HR
if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isHR()) {
    redirect(BASE_URL . '/index.php', 'Access denied. HR personnel only.', 'error');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

try {
    // Get HR companies
    $hr_companies = $user->getHRCompanies();
    
    // Get statistics
    $appraisal = new Appraisal($db);
    
    // Get appraisal counts by status
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as draft,
                        SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                        SUM(CASE WHEN a.status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM appraisals a
                    JOIN users u ON a.user_id = u.id
                    JOIN hr_companies hc ON u.company_id = hc.company_id
                    WHERE hc.user_id = ?";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$_SESSION['user_id']]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent appraisals
    $recent_stmt = $appraisal->getAppraisalsForHR($_SESSION['user_id']);
    $recent_appraisals = [];
    $count = 0;
    while ($row = $recent_stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($count < 10) { // Limit to 10 recent
            $recent_appraisals[] = $row;
            $count++;
        }
    }
    
    // Get employee count
    $emp_query = "SELECT COUNT(DISTINCT u.id) as count
                  FROM users u
                  JOIN hr_companies hc ON u.company_id = hc.company_id
                  WHERE hc.user_id = ? AND u.is_active = 1";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute([$_SESSION['user_id']]);
    $emp_data = $emp_stmt->fetch(PDO::FETCH_ASSOC);
    $employee_count = $emp_data['count'];
    
} catch (Exception $e) {
    error_log("HR Dashboard error: " . $e->getMessage());
    $hr_companies = [];
    $stats = ['total' => 0, 'draft' => 0, 'submitted' => 0, 'in_review' => 0, 'completed' => 0];
    $recent_appraisals = [];
    $employee_count = 0;
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-briefcase me-2"></i>HR Dashboard
        </h1>
    </div>
</div>

<!-- Companies Overview -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-building me-2"></i>Your Assigned Companies</h5>
                <?php if (empty($hr_companies)): ?>
                <p class="text-muted">You are not assigned to any companies yet. Please contact your administrator.</p>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($hr_companies as $company): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="mb-0">
                                    <i class="bi bi-building-fill text-primary me-2"></i>
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </h6>
                                <small class="text-muted">Code: <?php echo htmlspecialchars($company['code']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Total Employees</h6>
                        <h2 class="mb-0"><?php echo $employee_count; ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-people fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Total Appraisals</h6>
                        <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-clipboard-data fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Pending Review</h6>
                        <h2 class="mb-0"><?php echo $stats['submitted'] + $stats['in_review']; ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-clock-history fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Completed</h6>
                        <h2 class="mb-0"><?php echo $stats['completed']; ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="appraisals/index.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-list-ul me-2"></i>View All Appraisals
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="appraisals/index.php?status=submitted" class="btn btn-outline-warning w-100">
                            <i class="bi bi-exclamation-circle me-2"></i>Pending Reviews
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="employees/index.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-people me-2"></i>View Employees
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="reports/index.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-graph-up me-2"></i>Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Appraisals -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Appraisals</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_appraisals)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                    <h5>No Appraisals Found</h5>
                    <p class="text-muted">No appraisals available from your assigned companies.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Manager</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_appraisals as $appraisal_data): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($appraisal_data['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($appraisal_data['position']); ?></td>
                                <td><?php echo htmlspecialchars($appraisal_data['manager_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <small>
                                        <?php echo formatDate($appraisal_data['appraisal_period_from']); ?><br>
                                        to <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($appraisal_data['grade']): ?>
                                    <span class="badge bg-secondary"><?php echo $appraisal_data['grade']; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="appraisals/view.php?id=<?php echo $appraisal_data['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="appraisals/index.php" class="btn btn-outline-primary">
                        View All Appraisals <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>