<?php
// hr/employees/history.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$employee_id = $_GET['id'] ?? 0;
if (!$employee_id) {
    redirect('index.php', 'Employee ID is required.', 'error');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isHR()) {
    redirect(BASE_URL . '/index.php', 'Access denied. HR personnel only.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$page = $_GET['page'] ?? 1;
$records_per_page = 10;

try {
    // Verify employee is in HR's assigned companies
    $employee_query = "SELECT u.id, u.name, u.position, u.emp_number, u.department, u.email,
                              c.name as company_name, m.name as manager_name
                       FROM users u
                       JOIN companies c ON u.company_id = c.id
                       JOIN hr_companies hc ON c.id = hc.company_id
                       LEFT JOIN users m ON u.direct_superior = m.id
                       WHERE u.id = ? AND hc.user_id = ? AND u.is_active = 1";
    
    $employee_stmt = $db->prepare($employee_query);
    $employee_stmt->execute([$employee_id, $_SESSION['user_id']]);
    $employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        redirect('index.php', 'Employee not found or not in your assigned companies.', 'error');
    }
    
    // Get employee's appraisal history with pagination
    $from_record_num = ($records_per_page * $page) - $records_per_page;
    
    $query = "SELECT a.*, f.title as form_title
              FROM appraisals a
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE a.user_id = ?
              ORDER BY a.created_at DESC
              LIMIT ?, ?";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(3, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM appraisals WHERE user_id = ?";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute([$employee_id]);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
} catch (Exception $e) {
    error_log("HR Employee history error: " . $e->getMessage());
    redirect('index.php', 'An error occurred while loading employee history.', 'error');
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
                    <strong><?php echo htmlspecialchars($employee['name']); ?></strong> 
                    (<?php echo htmlspecialchars($employee['emp_number']); ?>)
                    - <?php echo htmlspecialchars($employee['position']); ?>
                </p>
                <p class="text-muted mb-0">
                    <small>
                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($employee['company_name']); ?>
                        <?php if ($employee['manager_name']): ?>
                        | <i class="bi bi-person me-1"></i>Manager: <?php echo htmlspecialchars($employee['manager_name']); ?>
                        <?php endif; ?>
                    </small>
                </p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Employees
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
                                    <?php
                                    $status_badges = [
                                        'draft' => 'secondary',
                                        'submitted' => 'warning',
                                        'in_review' => 'info',
                                        'completed' => 'success'
                                    ];
                                    $badge_class = $status_badges[$appraisal['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $appraisal['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($appraisal['grade']): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($appraisal['grade']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $appraisal['total_score'] ? number_format($appraisal['total_score'], 2) . '%' : '-'; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $appraisal['employee_submitted_at'] ? formatDate($appraisal['employee_submitted_at'], 'M d, Y') : '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $appraisal['manager_reviewed_at'] ? formatDate($appraisal['manager_reviewed_at'], 'M d, Y') : '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="../appraisals/view.php?id=<?php echo $appraisal['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $employee_id; ?>&page=<?php echo $i; ?>">
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>