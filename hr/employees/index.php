<?php
// hr/employees/index.php
require_once __DIR__ . '/../../config/config.php';

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
$company_filter = $_GET['company'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Build query
    $query = "SELECT DISTINCT u.id, u.name, u.emp_number, u.email, u.position, 
                     u.department, u.site, c.name as company_name,
                     m.name as manager_name,
                     (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id) as total_appraisals,
                     (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id AND status = 'completed') as completed_appraisals
              FROM users u
              JOIN companies c ON u.company_id = c.id
              JOIN hr_companies hc ON c.id = hc.company_id
              LEFT JOIN users m ON u.direct_superior = m.id
              WHERE hc.user_id = ? AND u.is_active = 1";
    
    $params = [$_SESSION['user_id']];
    
    if ($company_filter) {
        $query .= " AND c.id = ?";
        $params[] = $company_filter;
    }
    
    if ($search) {
        $query .= " AND (u.name LIKE ? OR u.emp_number LIKE ? OR u.department LIKE ? OR u.position LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY c.name, u.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get HR companies for filter
    $hr_companies = $user->getHRCompanies();
    
} catch (Exception $e) {
    error_log("HR Employees list error: " . $e->getMessage());
    $employees = [];
    $hr_companies = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-people me-2"></i>Employees Overview
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
                    <div class="col-md-4">
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
                    
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Name, employee number, department, or position"
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

<!-- Employees Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($employees)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted mb-3"></i>
                    <h5>No Employees Found</h5>
                    <p class="text-muted">
                        <?php if ($company_filter || $search): ?>
                        No employees match your filter criteria.
                        <?php else: ?>
                        No employees available from your assigned companies.
                        <?php endif; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Company</th>
                                <th>Site</th>
                                <th>Manager</th>
                                <th>Appraisals</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($employee['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($employee['emp_number']); ?></small><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td><?php echo htmlspecialchars($employee['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['site']); ?></td>
                                <td><?php echo htmlspecialchars($employee['manager_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $employee['completed_appraisals']; ?></span>
                                     <?php echo $employee['total_appraisals']; ?>
                                </td>
                                <td>
<a href="history.php?id=<?php echo $employee['id']; ?>"
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
                        Showing <?php echo count($employees); ?> employee(s)
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>