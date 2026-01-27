<?php
// manager/approvals/pending.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Appraisal.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

try {
    // Get pending approvals for this user
    $stmt = Appraisal::getPendingApprovalsForUser($db, $_SESSION['user_id']);
    $pending_appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Apply search filter
    if ($search) {
        $pending_appraisals = array_filter($pending_appraisals, function($appraisal) use ($search) {
            return stripos($appraisal['employee_name'], $search) !== false ||
                   stripos($appraisal['emp_number'], $search) !== false ||
                   stripos($appraisal['department'], $search) !== false;
        });
    }
    
} catch (Exception $e) {
    error_log("Pending approvals error: " . $e->getMessage());
    $error_message = "Error loading pending approvals.";
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Pending Approvals</h1>
                <p class="text-muted">Appraisals waiting for your approval</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Pending Approvals</h6>
                                <h3 class="mb-0"><?php echo count($pending_appraisals); ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="bi bi-clock-history text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Level 1 (Review)</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    echo count(array_filter($pending_appraisals, fn($a) => $a['can_rate'] == 1));
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-star text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Level 2+ (Approve)</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    echo count(array_filter($pending_appraisals, fn($a) => $a['can_rate'] == 0));
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Avg. Days Pending</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    if (!empty($pending_appraisals)) {
                                        $total_days = 0;
                                        foreach ($pending_appraisals as $appraisal) {
                                            $submitted = strtotime($appraisal['employee_submitted_at']);
                                            $days = floor((time() - $submitted) / (60 * 60 * 24));
                                            $total_days += $days;
                                        }
                                        echo round($total_days / count($pending_appraisals));
                                    } else {
                                        echo 0;
                                    }
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-calendar-event text-info fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by employee name, number, or department..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Pending Approvals Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($pending_appraisals)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle display-4 text-success"></i>
                        <p class="text-muted mt-3">No pending approvals. Great job!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Company</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Form</th>
                                    <th>Your Role</th>
                                    <th>Submitted</th>
                                    <th>Days Pending</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_appraisals as $appraisal): ?>
                                    <?php
                                    $submitted = strtotime($appraisal['employee_submitted_at']);
                                    $days_pending = floor((time() - $submitted) / (60 * 60 * 24));
                                    $urgency_class = $days_pending > 7 ? 'text-danger' : ($days_pending > 3 ? 'text-warning' : '');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appraisal['employee_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appraisal['emp_number']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($appraisal['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appraisal['position']); ?></td>
                                        <td><?php echo htmlspecialchars($appraisal['department']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($appraisal['form_title'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($appraisal['can_rate']): ?>
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-star"></i> Level <?php echo $appraisal['approval_level']; ?> - Review & Rate
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check"></i> Level <?php echo $appraisal['approval_level']; ?> - Approve
                                                </span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo ucwords(str_replace('_', ' ', $appraisal['approver_role'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', $submitted); ?></small>
                                        </td>
                                        <td>
                                            <span class="<?php echo $urgency_class; ?>">
                                                <strong><?php echo $days_pending; ?></strong> day<?php echo $days_pending != 1 ? 's' : ''; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($appraisal['can_rate']): ?>
                                                <a href="<?php echo BASE_URL; ?>/manager/review/review.php?id=<?php echo $appraisal['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Review & Rate
                                                </a>
                                            <?php else: ?>
                                                <a href="view.php?id=<?php echo $appraisal['id']; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="bi bi-eye"></i> Review & Approve
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help Card -->
        <div class="card border-0 shadow-sm mt-4 bg-light">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle"></i> About Multi-Level Approvals</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="small mb-2">
                            <strong>Level 1 (Review & Rate):</strong> You need to provide ratings and comments. 
                            This is the direct manager review where you assess the employee's performance.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="small mb-0">
                            <strong>Level 2+ (Approve Only):</strong> You review the ratings given by Level 1 
                            and approve or reject. You cannot change the ratings, only add your approval comments.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>