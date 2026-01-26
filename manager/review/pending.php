<?php
// manager/review/pending.php
require_once __DIR__ . '/../../config/config.php';

// Check if user can access team features (manager role OR has subordinates)
if (!canAccessTeamFeatures()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get pending appraisals for the current user's team
    $query = "SELECT a.id, a.status, a.appraisal_period_from, a.appraisal_period_to, 
                     a.employee_submitted_at, a.created_at,
                     u.name as employee_name, u.emp_number, u.position, u.department,
                     f.title as form_title
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE u.direct_superior = ? 
              AND a.status IN ('submitted', 'in_review')
              ORDER BY a.employee_submitted_at ASC, a.created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $pending_appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Pending reviews error: " . $e->getMessage());
    $pending_appraisals = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-clipboard-check me-2"></i>Pending Appraisal Reviews
            </h1>
            <span class="badge bg-warning fs-6">
                <?php echo count($pending_appraisals); ?> Pending
            </span>
        </div>
    </div>
</div>

<?php if (!hasRole('manager') && hasSubordinates()): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Team Lead Access:</strong> You have access to review appraisals for your team members.
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($pending_appraisals)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clipboard-check display-1 text-muted mb-3"></i>
                    <h5>No Pending Reviews</h5>
                    <p class="text-muted">All team member appraisals have been reviewed.</p>
                    <a href="<?php echo BASE_URL; ?>/manager/team.php" class="btn btn-outline-primary mt-3">
                        <i class="bi bi-people me-2"></i>View My Team
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Form</th>
                                <th>Appraisal Period</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_appraisals as $appraisal): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 35px; height: 35px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($appraisal['employee_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appraisal['emp_number']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($appraisal['position']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($appraisal['department']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($appraisal['form_title'] ?? 'N/A'); ?></td>
                                <td>
                                    <small>
                                        <?php echo formatDate($appraisal['appraisal_period_from'], 'M Y'); ?> - 
                                        <?php echo formatDate($appraisal['appraisal_period_to'], 'M Y'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = $appraisal['status'] === 'submitted' ? 'warning' : 'info';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $appraisal['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php 
                                        if ($appraisal['employee_submitted_at']) {
                                            echo formatDate($appraisal['employee_submitted_at'], 'M d, Y');
                                            $days_ago = floor((time() - strtotime($appraisal['employee_submitted_at'])) / 86400);
                                            if ($days_ago > 0) {
                                                echo '<br><span class="text-muted">(' . $days_ago . ' day' . ($days_ago != 1 ? 's' : '') . ' ago)</span>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($appraisal['status'] === 'in_review'): ?>
                                        <a href="review.php?id=<?php echo $appraisal['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil-square me-1"></i>Continue Review
                                        </a>
                                    <?php else: ?>
                                        <a href="review.php?id=<?php echo $appraisal['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bi bi-clipboard-check me-1"></i>Start Review
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
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>