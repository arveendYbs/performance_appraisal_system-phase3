<?php
// top-management/index.php - ENHANCED VERSION WITH CHARTS
require_once __DIR__ . '/../../config/config.php';

// Check if user is Top Management
if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isTopManagement()) {
    redirect(BASE_URL . '/index.php', 'Access denied. Top Management only.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$company_filter = $_GET['company'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

try {
    // Get Top Management companies
    $top_mgmt_companies = $user->getTopManagementCompanies();
    
    // Build base query conditions
    $base_conditions = "JOIN users u ON a.user_id = u.id
                        JOIN companies c ON u.company_id = c.id
                        JOIN top_management_companies tmc ON c.id = tmc.company_id
                        WHERE tmc.user_id = ?";
    $base_params = [$_SESSION['user_id']];
    
    if ($company_filter) {
        $base_conditions .= " AND c.id = ?";
        $base_params[] = $company_filter;
    }
    
    if ($year_filter) {
        $base_conditions .= " AND YEAR(a.appraisal_period_from) = ?";
        $base_params[] = $year_filter;
    }
    
    // Overall Statistics
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as draft,
                        SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                        SUM(CASE WHEN a.status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
                    FROM appraisals a
                    $base_conditions";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute($base_params);
    $overall_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistics by Department
    $dept_query = "SELECT 
                        u.department,
                        c.name as company_name,
                        COUNT(*) as total_appraisals,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN a.status = 'submitted' OR a.status = 'in_review' THEN 1 ELSE 0 END) as pending_review,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as not_submitted,
                        AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score
                   FROM appraisals a
                   $base_conditions
                   GROUP BY u.department, c.name
                   ORDER BY c.name, u.department";
    
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute($base_params);
    $dept_stats = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics by Company
    $company_query = "SELECT 
                        c.id,
                        c.name as company_name,
                        COUNT(*) as total_appraisals,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN a.status = 'submitted' OR a.status = 'in_review' THEN 1 ELSE 0 END) as pending_review,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as not_submitted,
                        AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score,
                        COUNT(DISTINCT u.id) as total_employees
                      FROM appraisals a
                      $base_conditions
                      GROUP BY c.id, c.name
                      ORDER BY c.name";
    
    $company_stmt = $db->prepare($company_query);
    $company_stmt->execute($base_params);
    $company_stats = $company_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grade Distribution
    $grade_query = "SELECT 
                        a.grade,
                        COUNT(*) as count
                    FROM appraisals a
                    $base_conditions
                    AND a.status = 'completed'
                    AND a.grade IS NOT NULL
                    GROUP BY a.grade
                    ORDER BY a.grade";
    
    $grade_stmt = $db->prepare($grade_query);
    $grade_stmt->execute($base_params);
    $grade_distribution = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly Trend (for line chart)
    $monthly_query = "SELECT 
                        MONTH(a.manager_reviewed_at) as month,
                        COUNT(*) as count
                      FROM appraisals a
                      $base_conditions
                      AND a.status = 'completed'
                      AND a.manager_reviewed_at IS NOT NULL
                      GROUP BY MONTH(a.manager_reviewed_at)
                      ORDER BY month";
    
    $monthly_stmt = $db->prepare($monthly_query);
    $monthly_stmt->execute($base_params);
    $monthly_trend = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent Activity
    $recent_query = "SELECT 
                        a.id,
                        a.status,
                        a.grade,
                        u.name as employee_name,
                        u.department,
                        c.name as company_name,
                        a.updated_at
                     FROM appraisals a
                     $base_conditions
                     ORDER BY a.updated_at DESC
                     LIMIT 10";
    
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute($base_params);
    $recent_activity = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Top Management Dashboard error: " . $e->getMessage());
    $overall_stats = [];
    $dept_stats = [];
    $company_stats = [];
}
?>

<!-- Print Styles -->
<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .sidebar {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
    }
    
    .card {
        page-break-inside: avoid;
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    
    .row {
        page-break-inside: avoid;
    }
    
    body {
        background: white !important;
    }
    
    @page {
        margin: 1.5cm;
    }
    
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #333;
        padding-bottom: 15px;
    }
}

.print-header {
    display: none;
}
</style>

<div id="printable-content">
    <!-- Print Header (only visible when printing) -->
    <div class="print-header">
        <h2>Top Management Dashboard Report</h2>
        <p>Generated on: <?php echo date('F d, Y'); ?></p>
        <?php if ($company_filter): ?>
        <p>Company: <?php 
            $selected_company = array_filter($top_mgmt_companies, function($c) use ($company_filter) {
                return $c['id'] == $company_filter;
            });
            echo $selected_company ? reset($selected_company)['name'] : 'All Companies';
        ?></p>
        <?php endif; ?>
        <p>Year: <?php echo $year_filter; ?></p>
    </div>

    <div class="row no-print">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-graph-up me-2"></i>Top Management Dashboard
                        <small class="text-muted">Comprehensive Overview</small>
                    </h1>
                </div>
                <button onclick="window.location.href='top-management-export-pdf.php'" class="btn btn-primary">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4 no-print">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Company</label>
                            <select class="form-select" name="company" id="company_filter">
                                <option value="">All Companies</option>
                                <?php foreach ($top_mgmt_companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>" 
                                        <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Year</label>
                            <select class="form-select" name="year" id="year_filter">
                                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <a href="?" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-6 col-sm-6 mb-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-clipboard-data text-primary" style="font-size: 2rem;"></i>
                    <h6 class="text-muted mt-2 mb-2">Total Appraisals</h6>
                    <h2 class="mb-0"><?php echo $overall_stats['total'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    <h6 class="text-muted mt-2 mb-2">Completed</h6>
                    <h2 class="mb-0 text-success"><?php echo $overall_stats['completed'] ?? 0; ?></h2>
                    <small class="text-muted">
                        <?php 
                        $total = $overall_stats['total'] ?? 0;
                        $completed = $overall_stats['completed'] ?? 0;
                        echo $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%'; 
                        ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                    <h6 class="text-muted mt-2 mb-2">Pending Review</h6>
                    <h2 class="mb-0 text-warning">
                        <?php echo ($overall_stats['submitted'] ?? 0) + ($overall_stats['in_review'] ?? 0); ?>
                    </h2>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="bi bi-send text-info" style="font-size: 2rem;"></i>
                    <h6 class="text-muted mt-2 mb-2">Submitted</h6>
                    <h2 class="mb-0 text-info"><?php echo $overall_stats['submitted'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <i class="bi bi-eye text-secondary" style="font-size: 2rem;"></i>
                    <h6 class="text-muted mt-2 mb-2">In Review</h6>
                    <h2 class="mb-0 text-secondary"><?php echo $overall_stats['in_review'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <i class="bi bi-file-earmark-x text-danger" style="font-size: 2rem;"></i>
                    <h6 class="text-muted mt-2 mb-2">Not Submitted</h6>
                    <h2 class="mb-0 text-danger"><?php echo $overall_stats['draft'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1: Status Overview & Monthly Trend -->
    <div class="row mb-4">
        <!-- Status Overview Pie Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Appraisal Status Overview</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusPieChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Completion Trend Line Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Completion Trend</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_trend)): ?>
                    <p class="text-muted text-center py-5">No completion data available for <?php echo $year_filter; ?></p>
                    <?php else: ?>
                    <canvas id="monthlyTrendChart" height="250"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
echo "<script>console.log('Grade Data:', " . json_encode($grade_distribution) . ");</script>";
?>

    <!-- Charts Row 2: Grade Distribution & Department Performance -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-award me-2"></i>Grade Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($grade_distribution)): ?>
                    <p class="text-muted text-center py-5">No grade data available yet</p>
                    <?php else: ?>
                    <div style="max-width: 400px; margin: 0 auto;">
                        <canvas id="gradeDonutChart" height="250"></canvas>
                    </div>
                    <div class="mt-3">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Grade</th>
                                    <th class="text-center">Count</th>
                                    <th class="text-end">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_graded = array_sum(array_column($grade_distribution, 'count'));
                                foreach ($grade_distribution as $grade): 
                                    // SAFETY CHECK: Ensure $total_graded is not zero before dividing
                                    $percentage = $total_graded > 0 ? ($grade['count'] / $total_graded) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($grade['grade']); ?></strong></td>
                                    <td class="text-center"><?php echo $grade['count']; ?></td>
                                    <td class="text-end"><?php echo round($percentage, 1); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Department Performance</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($dept_stats)): ?>
                    <p class="text-muted text-center py-5">No department data available</p>
                    <?php else: ?>
                    <canvas id="deptBarChart" height="250"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics by Company Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Progress by Company</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th class="text-center">Employees</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Completed</th>
                                    <th class="text-center">Pending Review</th>
                                    <th class="text-center">Not Submitted</th>
                                    <th class="text-center">Avg Score</th>
                                    <th class="text-center">Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($company_stats)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No data available</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($company_stats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['company_name']); ?></strong></td>
                                    <td class="text-center"><?php echo $stat['total_employees']; ?></td>
                                    <td class="text-center"><?php echo $stat['total_appraisals']; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo $stat['completed']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning"><?php echo $stat['pending_review']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?php echo $stat['not_submitted']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($stat['avg_score']): ?>
                                        <strong><?php echo round($stat['avg_score'], 1); ?>%</strong>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $progress = $stat['total_appraisals'] > 0 
                                            ? round(($stat['completed'] / $stat['total_appraisals']) * 100, 1) 
                                            : 0;
                                        ?>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $progress; ?>%">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics by Department Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Progress by Department</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Company</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Completed</th>
                                    <th class="text-center">Pending Review</th>
                                    <th class="text-center">Not Submitted</th>
                                    <th class="text-center">Avg Score</th>
                                    <th class="text-center">Completion %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dept_stats)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No data available</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($dept_stats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['department']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($stat['company_name']); ?></td>
                                    <td class="text-center"><?php echo $stat['total_appraisals']; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo $stat['completed']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning"><?php echo $stat['pending_review']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?php echo $stat['not_submitted']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($stat['avg_score']): ?>
                                        <strong><?php echo round($stat['avg_score'], 1); ?>%</strong>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $completion = $stat['total_appraisals'] > 0 
                                            ? round(($stat['completed'] / $stat['total_appraisals']) * 100, 1) 
                                            : 0;
                                        ?>
                                        <strong><?php echo $completion; ?>%</strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row no-print">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activity)): ?>
                    <p class="text-muted text-center py-4">No recent activity</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($activity['department']); ?> - 
                                        <?php echo htmlspecialchars($activity['company_name']); ?>
                                    </small>
                                </div>
                                <span class="badge <?php echo getStatusBadgeClass($activity['status']); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $activity['status'])); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php echo ($activity['updated_at']); ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
// Define the PHP version of the months array
$months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<script>
// Chart.js Configuration
Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif";

// Status Pie Chart
const statusPieCtx = document.getElementById('statusPieChart');
if (statusPieCtx) {
    new Chart(statusPieCtx, {
        type: 'pie',
        data: {
            labels: ['Draft', 'Submitted', 'In Review', 'Completed'],
            datasets: [{
                data: [
                    <?php echo $overall_stats['draft'] ?? 0; ?>,
                    <?php echo $overall_stats['submitted'] ?? 0; ?>,
                    <?php echo $overall_stats['in_review'] ?? 0; ?>,
                    <?php echo $overall_stats['completed'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#dc3545', // Red for draft
                    '#ffc107', // Yellow for submitted
                    '#17a2b8', // Info for in review
                    '#28a745'  // Green for completed
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Monthly Trend Line Chart
<?php if (!empty($monthly_trend)): ?>
const monthlyTrendCtx = document.getElementById('monthlyTrendChart');
if (monthlyTrendCtx) {
    new Chart(monthlyTrendCtx, {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($m) use ($months) { 
                return "'" . $months[$m['month']] . "'"; 
            }, $monthly_trend)); ?>],
            datasets: [{
                label: 'Completed Appraisals',
                data: [<?php echo implode(',', array_column($monthly_trend, 'count')); ?>],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#28a745',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}
<?php endif; ?>

// Grade Distribution Donut Chart - FIXED VERSION
<?php if (!empty($grade_distribution)): ?>
const gradeDonutCtx = document.getElementById('gradeDonutChart');
if (gradeDonutCtx) {
    // Use json_encode for reliable data passing
    const gradeData = {
        labels: <?php echo json_encode(array_column($grade_distribution, 'grade')); ?>,
        counts: <?php echo json_encode(array_column($grade_distribution, 'count')); ?>
    };
    
    console.log('Grade Chart Data:', gradeData); // Debug log
    
    new Chart(gradeDonutCtx, {
        type: 'doughnut',
        data: {
            labels: gradeData.labels,
            datasets: [{
                data: gradeData.counts,
                backgroundColor: [
                    '#28a745', // A - Green
                    '#20c997', // B+ - Teal  
                    '#17a2b8', // B - Cyan
                    '#ffc107', // B- - Yellow
                    '#fd7e14', // C+ - Orange
                    '#dc3545'  // C - Red
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `Grade ${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
} else {
    console.error('Canvas element gradeDonutChart not found!');
}
<?php endif; ?>



// Department Performance Bar Chart
<?php if (!empty($dept_stats)): ?>
const deptBarCtx = document.getElementById('deptBarChart');
if (deptBarCtx) {
    // Get top 10 departments by average score
    const deptData = <?php echo json_encode(array_slice($dept_stats, 0, 10)); ?>;
    
    new Chart(deptBarCtx, {
        type: 'bar',
        data: {
            labels: deptData.map(d => d.department),
            datasets: [{
                label: 'Average Score (%)',
                data: deptData.map(d => d.avg_score ? parseFloat(d.avg_score).toFixed(1) : 0),
                backgroundColor: '#667eea',
                borderColor: '#5568d3',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Avg Score: ' + context.parsed.x + '%';
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>


</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>