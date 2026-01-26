<?php
// manager/employee_history.php
require_once __DIR__ . '/../config/config.php';

if (!canAccessTeamFeatures()) {
    redirect(BASE_URL . '/index.php', 'Access denied. You need to be a manager or have team members to access this page.', 'error');
    exit;
}

$user_id = $_GET['user_id'] ?? 0;
if (!$user_id) {
    redirect('team.php', 'Employee ID is required.', 'error');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$page = $_GET['page'] ?? 1;
$records_per_page = 10;

try {
    $database = new Database();
    $db = $database->getConnection();

    // ✅ Get employee details (including direct_superior)
    $emp_query = "SELECT id, name, position, emp_number, department, direct_superior 
                  FROM users 
                  WHERE id = ? AND is_active = 1";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute([$user_id]);
    $employee = $emp_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        redirect('team.php', 'Employee not found.', 'error');
        exit;
    }

    // ✅ Check if logged-in user is this employee's direct superior
    $is_direct_report = ($employee['direct_superior'] == $_SESSION['user_id']);

    // ✅ Get employee's appraisal history with pagination
    $from_record_num = ($records_per_page * $page) - $records_per_page;

    $query = "SELECT a.*, f.title as form_title
              FROM appraisals a
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE a.user_id = ?
              ORDER BY a.created_at DESC
              LIMIT ?, ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(3, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();

    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Get total count
    $count_query = "SELECT COUNT(*) as total FROM appraisals WHERE user_id = ?";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute([$user_id]);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    error_log("Employee history error: " . $e->getMessage());
    redirect('team.php', 'An error occurred.', 'error');
    exit;
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-clock-history me-2"></i>Appraisal History
                </h1>
                <p class="text-muted mb-0">
                    <strong><?php echo htmlspecialchars($employee['name'] ?? 'Unknown'); ?></strong> 
                    (<?php echo htmlspecialchars($employee['emp_number'] ?? '-'); ?>)
                    - <?php echo htmlspecialchars($employee['position'] ?? '-'); ?>
                </p>
            </div>
            <a href="team.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Team
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($appraisals)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clipboard-x display-1 text-muted mb-3"></i>
                    <h5>No Appraisal History</h5>
                    <p class="text-muted">This employee doesn't have any appraisals yet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Form</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Score</th>
                                <th>Submitted</th>
                                <th>Reviewed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appraisals as $appraisal): ?>
                            <tr>
                                <td>
                                    <small>
                                        <?php echo formatDate($appraisal['appraisal_period_from'], 'M Y'); ?> - 
                                        <?php echo formatDate($appraisal['appraisal_period_to'], 'M Y'); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($appraisal['form_title'] ?? 'N/A'); ?></td>
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
                                            formatDate($appraisal['employee_submitted_at'], 'M j, Y') : '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $appraisal['manager_reviewed_at'] ? 
                                            formatDate($appraisal['manager_reviewed_at'], 'M j, Y') : '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($is_direct_report && ($appraisal['status'] === 'submitted' || $appraisal['status'] === 'in_review')): ?>
                                        <a href="review/review.php?id=<?php echo $appraisal['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Review
                                        </a>
                                    <?php elseif ($appraisal['status'] === 'completed' || $appraisal['status'] === 'submitted' || $appraisal['status'] === 'in_review' ): ?>
                                        <a href="review/view.php?id=<?php echo $appraisal['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    <?php else: ?>
                                        <small class="text-muted">Draft</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?user_id=<?php echo $user_id; ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
