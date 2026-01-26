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

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$report_type = $_GET['report'] ?? 'overview';
$company_filter = $_GET['company'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

try {
    // Get HR companies
    $hr_companies = $user->getHRCompanies();
    
    // Get available years
    $years_query = "SELECT DISTINCT YEAR(appraisal_period_from) as year 
                    FROM appraisals a
                    JOIN users u ON a.user_id = u.id
                    JOIN hr_companies hc ON u.company_id = hc.company_id
                    WHERE hc.user_id = ?
                    ORDER BY year DESC";
    $years_stmt = $db->prepare($years_query);
    $years_stmt->execute([$_SESSION['user_id']]);
    $available_years = $years_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build base query conditions
    $base_conditions = "JOIN users u ON a.user_id = u.id
                        JOIN companies c ON u.company_id = c.id
                        JOIN hr_companies hc ON c.id = hc.company_id
                        WHERE hc.user_id = ?";
    $base_params = [$_SESSION['user_id']];
    
    if ($company_filter) {
        $base_conditions .= " AND c.id = ?";
        $base_params[] = $company_filter;
    }
    
    if ($year_filter) {
        $base_conditions .= " AND YEAR(a.appraisal_period_from) = ?";
        $base_params[] = $year_filter;
    }
    
    // Generate report based on type
    switch ($report_type) {
        case 'overview':
            $report_data = generateOverviewReport($db, $base_conditions, $base_params);
            break;
        case 'completion':
            $report_data = generateCompletionReport($db, $base_conditions, $base_params);
            break;
        case 'performance':
            $report_data = generatePerformanceReport($db, $base_conditions, $base_params);
            break;
        case 'department':
            $report_data = generateDepartmentReport($db, $base_conditions, $base_params);
            break;
        default:
            $report_data = generateOverviewReport($db, $base_conditions, $base_params);
    }
    
} catch (Exception $e) {
    error_log("HR Reports error: " . $e->getMessage());
    $report_data = [];
    $hr_companies = [];
    $available_years = [];
}

// Report generation functions
function generateOverviewReport($db, $conditions, $params) {
    $query = "SELECT 
                COUNT(a.id) as total_appraisals,
                COUNT(DISTINCT u.id) as total_employees,
                SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN a.status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score,
                COUNT(CASE WHEN a.status = 'completed' AND a.grade = 'A' THEN 1 END) as grade_a,
                COUNT(CASE WHEN a.status = 'completed' AND a.grade = 'B+' THEN 1 END) as grade_b_plus,
                COUNT(CASE WHEN a.status = 'completed' AND a.grade = 'B' THEN 1 END) as grade_b,
                COUNT(CASE WHEN a.status = 'completed' AND a.grade = 'B-' THEN 1 END) as grade_b_minus,
                COUNT(CASE WHEN a.status = 'completed' AND a.grade = 'C' THEN 1 END) as grade_c
              FROM appraisals a
              $conditions";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function generateCompletionReport($db, $conditions, $params) {
    $query = "SELECT 
                c.name as company_name,
                COUNT(DISTINCT u.id) as total_employees,
                COUNT(a.id) as total_appraisals,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                ROUND((SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as completion_rate
              FROM appraisals a
              $conditions
              GROUP BY c.id, c.name
              ORDER BY completion_rate DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generatePerformanceReport($db, $conditions, $params) {
    $query = "SELECT 
                u.name as employee_name,
                u.emp_number,
                u.position,
                c.name as company_name,
                u.department,
                a.grade,
                a.total_score,
                a.appraisal_period_from,
                a.appraisal_period_to
              FROM appraisals a
              $conditions
              AND a.status = 'completed'
              ORDER BY a.total_score DESC
              LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateDepartmentReport($db, $conditions, $params) {
    $query = "SELECT 
                u.department,
                COUNT(DISTINCT u.id) as total_employees,
                COUNT(a.id) as total_appraisals,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score,
                ROUND((SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as completion_rate
              FROM appraisals a
              $conditions
              GROUP BY u.department
              ORDER BY avg_score DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-graph-up me-2"></i>HR Reports & Analytics
            </h1>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Report Type Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" id="report_type" onchange="changeReport()">
                            <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                            <option value="completion" <?php echo $report_type === 'completion' ? 'selected' : ''; ?>>Completion Rate</option>
                            <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>Top Performers</option>
                            <option value="department" <?php echo $report_type === 'department' ? 'selected' : ''; ?>>Department Analysis</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <select class="form-select" id="company_filter" onchange="applyFilters()">
                            <option value="">All Companies</option>
                            <?php foreach ($hr_companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" 
                                    <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select class="form-select" id="year_filter" onchange="applyFilters()">
                            <option value="">All Years</option>
                            <?php foreach ($available_years as $year_data): ?>
                            <option value="<?php echo $year_data['year']; ?>" 
                                    <?php echo $year_filter == $year_data['year'] ? 'selected' : ''; ?>>
                                <?php echo $year_data['year']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    
                                    <!-- Replace the existing "Export to Excel" button with this: -->
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <div class="dropdown w-100">
                            <button class="btn btn-success dropdown-toggle w-100" type="button" 
                                    id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                            </button>
                            <ul class="dropdown-menu w-100" aria-labelledby="exportDropdown">
                                <li>
                                    <a class="dropdown-item" href="#" onclick="exportCompanyExcel('detailed'); return false;">
                                        <i class="bi bi-file-earmark-text me-2"></i>
                                        <strong>Detailed Report</strong>
                                        <br>
                                        <small class="text-muted">One sheet per employee with full details</small>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="exportCompanyExcel('summary'); return false;">
                                        <i class="bi bi-table me-2"></i>
                                        <strong>Summary Report</strong>
                                        <br>
                                        <small class="text-muted">All employees in rows for analysis</small>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="exportCompanyExcel('comprehensive'); return false;">
                                        <i class="bi bi-table me-2"></i>
                                        <strong>Comprehensive</strong>
                                        <br>
                                        <small class="text-muted">All scores & Training Needs</small>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="exportAllEmployeesExcel(); return false;">
                                        <i class="bi bi-people-fill me-2"></i>
                                        <strong>All Employees Report (Python)</strong>
                                        <br>
                                        <small class="text-muted">Detailed report for all employees using Python</small>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                
                                                                                            

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Content -->
<?php if ($report_type === 'overview'): ?>
    <!-- Overview Report -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Total Appraisals</h6>
                    <h2><?php echo number_format($report_data['total_appraisals'] ?? 0); ?></h2>
                    <small><?php echo number_format($report_data['total_employees'] ?? 0); ?> Employees</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Completed</h6>
                    <h2><?php echo number_format($report_data['completed'] ?? 0); ?></h2>
                    <small>
                        <?php 
                        $completion_pct = ($report_data['total_appraisals'] > 0) 
                            ? round(($report_data['completed'] / $report_data['total_appraisals']) * 100, 1) 
                            : 0;
                        echo $completion_pct; 
                        ?>% Completion Rate
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">Pending</h6>
                    <h2><?php echo number_format(($report_data['submitted'] ?? 0) + ($report_data['in_review'] ?? 0)); ?></h2>
                    <small>Awaiting Review</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Average Score</h6>
                    <h2><?php echo number_format($report_data['avg_score'] ?? 0, 2); ?></h2>
                    <small>Out of 100</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Grade Distribution -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Grade Distribution</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Grade A</strong></td>
                            <td class="text-end"><?php echo $report_data['grade_a'] ?? 0; ?></td>
                            <td class="text-end">
                                <?php 
                                $total_graded = ($report_data['completed'] ?? 0);
                                echo $total_graded > 0 ? round(($report_data['grade_a'] / $total_graded) * 100, 1) : 0; 
                                ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Grade B+</strong></td>
                            <td class="text-end"><?php echo $report_data['grade_b_plus'] ?? 0; ?></td>
                            <td class="text-end">
                                <?php echo $total_graded > 0 ? round(($report_data['grade_b_plus'] / $total_graded) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Grade B</strong></td>
                            <td class="text-end"><?php echo $report_data['grade_b'] ?? 0; ?></td>
                            <td class="text-end">
                                <?php echo $total_graded > 0 ? round(($report_data['grade_b'] / $total_graded) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Grade B-</strong></td>
                            <td class="text-end"><?php echo $report_data['grade_b_minus'] ?? 0; ?></td>
                            <td class="text-end">
                                <?php echo $total_graded > 0 ? round(($report_data['grade_b_minus'] / $total_graded) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Grade C</strong></td>
                            <td class="text-end"><?php echo $report_data['grade_c'] ?? 0; ?></td>
                            <td class="text-end">
                                <?php echo $total_graded > 0 ? round(($report_data['grade_c'] / $total_graded) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($report_type === 'completion'): ?>
    <!-- Completion Rate Report -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Completion Rate by Company</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Total Employees</th>
                                    <th>Total Appraisals</th>
                                    <th>Completed</th>
                                    <th>Completion Rate</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['company_name']); ?></strong></td>
                                    <td><?php echo number_format($row['total_employees']); ?></td>
                                    <td><?php echo number_format($row['total_appraisals']); ?></td>
                                    <td><?php echo number_format($row['completed']); ?></td>
                                    <td><span class="badge bg-info"><?php echo number_format($row['completion_rate'], 1); ?>%</span></td>
                                    <td>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $row['completion_rate']; ?>%"
                                                 aria-valuenow="<?php echo $row['completion_rate']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo number_format($row['completion_rate'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($report_type === 'performance'): ?>
    <!-- Top Performers Report -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Performers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th>Company</th>
                                    <th>Department</th>
                                    <th>Period</th>
                                    <th>Grade</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($report_data as $row): ?>
                                <tr>
                                    <td><strong><?php echo $rank++; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['emp_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['position']); ?></td>
                                    <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td>
                                        <small>
                                            <?php echo formatDate($row['appraisal_period_from'], 'M Y'); ?> - 
                                            <?php echo formatDate($row['appraisal_period_to'], 'M Y'); ?>
                                        </small>
                                    </td>
                                    <td><span class="badge bg-success"><?php echo $row['grade']; ?></span></td>
                                    <td><strong><?php echo number_format($row['total_score'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($report_type === 'department'): ?>
    <!-- Department Analysis Report -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Department Performance Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Employees</th>
                                    <th>Total Appraisals</th>
                                    <th>Completed</th>
                                    <th>Completion Rate</th>
                                    <th>Avg Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['department']); ?></strong></td>
                                    <td><?php echo number_format($row['total_employees']); ?></td>
                                    <td><?php echo number_format($row['total_appraisals']); ?></td>
                                    <td><?php echo number_format($row['completed']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo number_format($row['completion_rate'] ?? 0, 1); ?>%</span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($row['avg_score'] ?? 0, 2); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Include Chart.js for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function changeReport() {
    const reportType = document.getElementById('report_type').value;
    const company = document.getElementById('company_filter').value;
    const year = document.getElementById('year_filter').value;
    window.location.href = `?report=${reportType}&company=${company}&year=${year}`;
}

function applyFilters() {
    const reportType = document.getElementById('report_type').value;
    const company = document.getElementById('company_filter').value;
    const year = document.getElementById('year_filter').value;
    window.location.href = `?report=${reportType}&company=${company}&year=${year}`;
}

// CORRECTED: Export function with type parameter
function exportCompanyExcel(type) {
    const company = document.getElementById('company_filter').value;
    const year = document.getElementById('year_filter').value;
    
    if (!company) {
        alert('Please select a company first');
        return;
    }
    
    // Show loading indicator
    const dropdownBtn = document.getElementById('exportDropdown');
    const originalText = dropdownBtn.innerHTML;
    dropdownBtn.disabled = true;
    dropdownBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    
    // Navigate to export script with type parameter
    window.location.href = `export_company_excel.php?company=${company}&year=${year}&type=${type}`;
    
    // Re-enable button after a delay
    setTimeout(() => {
        dropdownBtn.disabled = false;
        dropdownBtn.innerHTML = originalText;
    }, 3000);
}
function exportAllEmployeesExcel() {
    const company = document.getElementById('company_filter').value;
    const year = document.getElementById('year_filter').value;
    
    if (!company) {
        alert('Please select a company first');
        return;
    }
    
    const dropdownBtn = document.getElementById('exportDropdown');
    const originalText = dropdownBtn.innerHTML;
    dropdownBtn.disabled = true;
    dropdownBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    
    window.location.href = `generate_all_employees_excel.php?company=${company}&year=${year}`;
    
    setTimeout(() => {
        dropdownBtn.disabled = false;
        dropdownBtn.innerHTML = originalText;
    }, 3000);
}

// Chart for Overview Report
<?php if ($report_type === 'overview' && !empty($report_data)): ?>
const ctx = document.getElementById('statusChart');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Draft', 'Submitted', 'In Review', 'Completed'],
        datasets: [{
            data: [
                <?php echo $report_data['draft'] ?? 0; ?>,
                <?php echo $report_data['submitted'] ?? 0; ?>,
                <?php echo $report_data['in_review'] ?? 0; ?>,
                <?php echo $report_data['completed'] ?? 0; ?>
            ],
            backgroundColor: [
                '#6c757d',
                '#ffc107',
                '#0dcaf0',
                '#198754'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>