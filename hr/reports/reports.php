<?php
// hr/reports/index.php
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

// Get filter parameters
$company_filter = $_GET['company'] ?? '';
$department_filter = $_GET['department'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');
$search = $_GET['search'] ?? '';


require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    // Build query for employees with completed appraisals
    $query = "SELECT DISTINCT
                u.id,
                u.name,
                u.emp_number,
                u.position,
                u.department,
                u.role,
                c.name as company_name,
                COUNT(DISTINCT a.id) as total_appraisals,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appraisals
              FROM users u
              LEFT JOIN companies c ON u.company_id = c.id
              LEFT JOIN appraisals a ON u.id = a.user_id 
                  AND YEAR(a.appraisal_period_from) = ?
              WHERE u.is_active = 1";
    
    $params = [$year_filter];
    
    if ($company_filter) {
        $query .= " AND c.id = ?";
        $params[] = $company_filter;
    }
    
    if ($department_filter) {
        $query .= " AND u.department = ?";
        $params[] = $department_filter;
    }
    
    if ($search) {
        $query .= " AND (u.name LIKE ? OR u.emp_number LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " GROUP BY u.id, u.name, u.emp_number, u.position, u.department, u.role, c.name
                HAVING completed_appraisals > 0
                ORDER BY c.name, u.department, u.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for filter
    $companies_query = "SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name";
    $companies_stmt = $db->prepare($companies_query);
    $companies_stmt->execute();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get departments for filter
    $dept_query = "SELECT DISTINCT department 
                   FROM users 
                   WHERE is_active = 1 AND department IS NOT NULL AND department != ''
                   ORDER BY department";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("HR Reports error: " . $e->getMessage());
    $employees = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-file-earmark-excel me-2"></i>Appraisal Reports
                <small class="text-muted">Generate Excel Reports</small>
            </h1>
        </div>
    </div>
</div>

<!-- Instructions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Generate Appraisal Reports:</strong> Select an employee and year to download their complete appraisal report in Excel format. The report includes all performance assessment scores from both employee and appraiser.
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Employees</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" 
                                    <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                    <?php echo $department_filter == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Name or ID" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <a href="?" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Employee List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>Employees with Completed Appraisals
                    <span class="badge bg-primary ms-2"><?php echo count($employees); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($employees)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="text-muted mt-3">No employees with completed appraisals found.</p>
                    <p class="text-muted">Try adjusting your filters.</p>
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
                                <th class="text-center">Role</th>
                                <th class="text-center">Completed Appraisals</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($employee['name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($employee['emp_number']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td><?php echo htmlspecialchars($employee['company_name']); ?></td>
                                <td class="text-center">
                                    <?php
                                    $role_badges = [
                                        'admin' => 'danger',
                                        'manager' => 'warning',
                                        'employee' => 'info',
                                        'worker' => 'secondary'
                                    ];
                                    $badge_class = $role_badges[$employee['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($employee['role']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $employee['completed_appraisals']; ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="generate-excel-test.php?user_id=<?php echo $employee['id']; ?>&year=<?php echo $year_filter; ?>" 
                                       class="btn btn-sm btn-success"
                                       title="Download Excel Report">
                                        <i class="bi bi-file-earmark-excel me-1"></i>Download Report
                                    </a>
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

<!-- Bulk Download Section (Future Enhancement) -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-download me-2"></i>Bulk Download (Coming Soon)</h6>
            </div>
            <div class="card-body">
                <p class="mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Bulk download feature for multiple employees will be available in the next update.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function exportCompanyExcel(type) {
    const company = document.getElementById('company_filter').value;
    const year = document.getElementById('year_filter').value;
    
    if (!company) {
        alert('Please select a company first');
        return;
    }
    
    // Navigate to export script with type parameter
    window.location.href = `export_company_excel.php?company=${company}&year=${year}&type=${type}`;
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>