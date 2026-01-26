<?php
// hr/appraisals/index.php
require_once __DIR__ . '/../../config/config.php';

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

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$company_filter = $_GET['company'] ?? '';
$search = $_GET['search'] ?? '';

try {
    $appraisal = new Appraisal($db);
    
    // Build query with filters
    $query = "SELECT a.*, 
                     u.name as employee_name, u.emp_number, u.position, u.department,
                     c.id as company_id, c.name as company_name,
                     m.name as manager_name,
                     f.title as form_title
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              JOIN companies c ON u.company_id = c.id
              JOIN hr_companies hc ON c.id = hc.company_id
              LEFT JOIN users m ON u.direct_superior = m.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE hc.user_id = ?";
    
    $params = [$_SESSION['user_id']];
    
    // Add status filter
    if ($status_filter) {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    // Add company filter
    if ($company_filter) {
        $query .= " AND c.id = ?";
        $params[] = $company_filter;
    }
    
    // Add search filter
    if ($search) {
        $query .= " AND (u.name LIKE ? OR u.emp_number LIKE ? OR u.department LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY a.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get HR companies for filter dropdown
    $hr_companies = $user->getHRCompanies();
    
} catch (Exception $e) {
    error_log("HR Appraisals list error: " . $e->getMessage());
    $appraisals = [];
    $hr_companies = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-clipboard-data me-2"></i>All Appraisals
            </h1>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="in_review" <?php echo $status_filter === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company">
                            <option value="">All Companies</option>
                            <?php foreach ($hr_companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" 
                                    <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Employee name, number, or department"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Appraisals Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($appraisals)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                    <h5>No Appraisals Found</h5>
                    <p class="text-muted">
                        <?php if ($status_filter || $company_filter || $search): ?>
                        No appraisals match your filter criteria.
                        <?php else: ?>
                        No appraisals available from your assigned companies.
                        <?php endif; ?>
                    </p>
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
                                <th>Manager</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appraisals as $appraisal_data): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($appraisal_data['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($appraisal_data['position']); ?></td>
                                <td><?php echo htmlspecialchars($appraisal_data['department']); ?></td>
                                <td><?php echo htmlspecialchars($appraisal_data['manager_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <small>
                                        <?php echo formatDate($appraisal_data['appraisal_period_from'], 'M d, Y'); ?><br>
                                        to <?php echo formatDate($appraisal_data['appraisal_period_to'], 'M d, Y'); ?>
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
                                    <a href="view.php?id=<?php echo $appraisal_data['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Showing <?php echo count($appraisals); ?> appraisal(s)
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>