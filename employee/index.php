
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get current user's appraisal status
    $appraisal = new Appraisal($db);
    $current_appraisal = null;
    if ($appraisal->getCurrentAppraisal($_SESSION['user_id'])) {
        $current_appraisal = [
            'id' => $appraisal->id,
            'status' => $appraisal->status,
            'period_from' => $appraisal->appraisal_period_from,
            'period_to' => $appraisal->appraisal_period_to,
            'grade' => $appraisal->grade,
            'total_score' => $appraisal->total_score
        ];
    }
    
    // Get appraisal history
    $history_query = "SELECT id, status, grade, total_score, appraisal_period_from, appraisal_period_to, 
                             employee_submitted_at, manager_reviewed_at
                      FROM appraisals 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT 5";
    $stmt = $db->prepare($history_query);
    $stmt->execute([$_SESSION['user_id']]);
    $appraisal_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Employee dashboard error: " . $e->getMessage());
    $current_appraisal = null;
    $appraisal_history = [];
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-house me-2"></i>Employee Dashboard
            <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</small>
        </h1>
    </div>
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
                            <?php if ($current_appraisal['total_score']): ?>
                            <span class="badge bg-info">Score: <?php echo $current_appraisal['total_score']; ?>%</span>
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
                        <a href="appraisal/continue.php" class="btn btn-primary">
                            <i class="bi bi-pencil me-2"></i>Continue Appraisal
                        </a>
                        <?php else: ?>
                        <a href="appraisal/view.php?id=<?php echo $current_appraisal['id']; ?>" class="btn btn-outline-primary">
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
                    <a href="appraisal/start.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Start New Appraisal
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Appraisal History -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Appraisals</h5>
            </div>
            <div class="card-body">
                <?php if (empty($appraisal_history)): ?>
                <p class="text-muted">No previous appraisals found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Score</th>
                                <th>Submitted</th>
                                <th>Actions</th>
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
                                <td>
                                    <a href="appraisal/view.php?id=<?php echo $appraisal['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end">
                    <a href="history.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-clock-history me-2"></i>View All History
                    </a>
                </div>
                <?php endif; ?>
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
                    <div class="col-md-4 mb-3">
                        <a href="appraisal/" class="btn btn-outline-primary w-100">
                            <i class="bi bi-clipboard-data d-block mb-2" style="font-size: 2rem;"></i>
                            My Appraisal
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="profile.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-person-circle d-block mb-2" style="font-size: 2rem;"></i>
                            My Profile
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="history.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-clock-history d-block mb-2" style="font-size: 2rem;"></i>
                            History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
