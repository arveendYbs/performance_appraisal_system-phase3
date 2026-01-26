<?php
// top-management/managers.php - Manager/HOD Performance Dashboard
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
    
    // Build base conditions for filtering
    $base_conditions = "WHERE tmc.user_id = ?";
    $base_params = [$_SESSION['user_id']];
    
    if ($company_filter) {
        $base_conditions .= " AND c.id = ?";
        $base_params[] = $company_filter;
    }
    
    // Get all managers/supervisors with their team statistics
    $manager_query = "SELECT 
                        m.id as manager_id,
                        m.name as manager_name,
                        m.emp_number as manager_emp_number,
                        m.position as manager_position,
                        m.department as manager_department,
                        m.role as manager_role,
                        c.name as company_name,
                        
                        -- Team size
                        COUNT(DISTINCT sub.id) as team_size,
                        
                        -- Total appraisals
                        COUNT(DISTINCT a.id) as total_appraisals,
                        
                        -- Completed appraisals
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appraisals,
                        
                        -- Pending reviews (submitted + in_review)
                        SUM(CASE WHEN a.status IN ('submitted', 'in_review') THEN 1 ELSE 0 END) as pending_reviews,
                        
                        -- Not submitted
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as not_submitted,
                        
                        -- Average score of completed appraisals
                        AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score,
                        
                        -- Average review time (days between submission and completion)
                        AVG(CASE 
                            WHEN a.status = 'completed' 
                                AND a.employee_submitted_at IS NOT NULL 
                                AND a.manager_reviewed_at IS NOT NULL
                            THEN DATEDIFF(a.manager_reviewed_at, a.employee_submitted_at)
                            ELSE NULL 
                        END) as avg_review_days,
                        
                        -- Completion rate
                        CASE 
                            WHEN COUNT(DISTINCT a.id) > 0 
                            THEN (SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) / COUNT(DISTINCT a.id)) * 100
                            ELSE 0 
                        END as completion_rate
                        
                      FROM users m
                      JOIN companies c ON m.company_id = c.id
                      JOIN top_management_companies tmc ON c.id = tmc.company_id
                      LEFT JOIN users sub ON sub.direct_superior = m.id AND sub.is_active = 1
                      LEFT JOIN appraisals a ON a.user_id = sub.id 
                          AND YEAR(a.appraisal_period_from) = ?
                      
                      $base_conditions
                      AND m.is_active = 1
                      AND m.role IN ('admin', 'manager', 'employee', 'worker')
                      
                      GROUP BY m.id, m.name, m.emp_number, m.position, m.department, m.role, c.name
                      HAVING team_size > 0
                      ORDER BY completion_rate DESC, team_size DESC";
    
    $params = array_merge([$year_filter], $base_params);
    $manager_stmt = $db->prepare($manager_query);
    $manager_stmt->execute($params);
    $managers_data = $manager_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall statistics
    $total_managers = count($managers_data);
    $total_team_members = array_sum(array_column($managers_data, 'team_size'));
    $total_appraisals = array_sum(array_column($managers_data, 'total_appraisals'));
    $total_completed = array_sum(array_column($managers_data, 'completed_appraisals'));
    $total_pending = array_sum(array_column($managers_data, 'pending_reviews'));
    $avg_completion_rate = $total_managers > 0 
        ? array_sum(array_column($managers_data, 'completion_rate')) / $total_managers 
        : 0;
    
    // Get top and bottom performers
    $top_performers = array_slice($managers_data, 0, 5);
    $bottom_performers = array_slice(array_reverse($managers_data), 0, 5);
    
} catch (Exception $e) {
    error_log("Manager Performance Dashboard error: " . $e->getMessage());
    $managers_data = [];
    $total_managers = 0;
}
?>

<!-- Print Styles -->
<style>
@media print {
    .no-print { display: none !important; }
    .sidebar { display: none !important; }
    .main-content { margin-left: 0 !important; }
}
</style>

<div class="row no-print">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-person-badge me-2"></i>Manager/HOD Performance
                    <small class="text-muted">Team Leadership Analytics</small>
                </h1>
            </div>
            <button onclick="window.print()" class="btn btn-primary">
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
                    <div class="col-md-5">
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company">
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
                        <select class="form-select" name="year">
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
                    
                    <div class="col-md-2 d-flex align-items-end">
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
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <i class="bi bi-person-badge text-primary" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2 mb-2">Total Managers/HODs</h6>
                <h2 class="mb-0"><?php echo $total_managers; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="bi bi-people text-info" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2 mb-2">Total Team Members</h6>
                <h2 class="mb-0"><?php echo $total_team_members; ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2 mb-2">Avg Completion Rate</h6>
                <h2 class="mb-0 text-success"><?php echo round($avg_completion_rate, 1); ?>%</h2>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                <h6 class="text-muted mt-2 mb-2">Pending Reviews</h6>
                <h2 class="mb-0 text-warning"><?php echo $total_pending; ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<!-- <pre>
<?php print_r($managers_data); ?>
</pre> -->

<div class="row mb-4">
    <!-- Top Performers Bar Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 5 Performers (Completion Rate)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_performers)): ?>
                <p class="text-muted text-center py-5">No data available</p>
                <?php else: ?>
                <div style="position: relative; height: 350px;">
                    <canvas id="topPerformersChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Team Size Distribution Pie Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Team Size Distribution</h5>
            </div>
            <div class="card-body">
                <?php if (empty($managers_data)): ?>
                <p class="text-muted text-center py-5">No data available</p>
                <?php else: ?>
                <div style="position: relative; height: 300px;">
                    <canvas id="teamSizeChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Manager Performance Table -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-table me-2"></i>Manager/HOD Detailed Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Manager/HOD</th>
                                <th>Department</th>
                                <th>Company</th>
                                <th class="text-center">Team Size</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Not Submitted</th>
                                <th class="text-center">Avg Score</th>
                                <th class="text-center">Completion %</th>
                                <th class="text-center">Avg Review Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($managers_data)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No managers found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($managers_data as $manager): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($manager['manager_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($manager['manager_position']); ?></small><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($manager['manager_emp_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($manager['manager_department']); ?></td>
                                <td><?php echo htmlspecialchars($manager['company_name']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $manager['team_size']; ?></span>
                                </td>
                                <td class="text-center"><?php echo $manager['total_appraisals']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $manager['completed_appraisals']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning"><?php echo $manager['pending_reviews']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $manager['not_submitted']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($manager['avg_score']): ?>
                                    <strong><?php echo round($manager['avg_score'], 1); ?>%</strong>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $completion = round($manager['completion_rate'], 1);
                                    $color = $completion >= 80 ? 'success' : ($completion >= 50 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                             style="width: <?php echo $completion; ?>%">
                                            <?php echo $completion; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($manager['avg_review_days']): ?>
                                    <?php echo round($manager['avg_review_days'], 1); ?> days
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
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

<!-- Performance Insights -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 5 Best Performers</h6>
            </div>
            <div class="card-body">
                <?php if (empty($top_performers)): ?>
                <p class="text-muted">No data available</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($top_performers as $index => $manager): ?>
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-warning me-2">#<?php echo $index + 1; ?></span>
                            <strong><?php echo htmlspecialchars($manager['manager_name']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($manager['manager_department']); ?> - 
                                Team: <?php echo $manager['team_size']; ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <h5 class="mb-0 text-success"><?php echo round($manager['completion_rate'], 1); ?>%</h5>
                            <small class="text-muted">completion</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Needs Attention (Low Completion)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($bottom_performers)): ?>
                <p class="text-muted">No data available</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($bottom_performers as $manager): ?>
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($manager['manager_name']); ?></strong><br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($manager['manager_department']); ?> - 
                                Team: <?php echo $manager['team_size']; ?> - 
                                Pending: <?php echo $manager['pending_reviews']; ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <h5 class="mb-0 text-danger"><?php echo round($manager['completion_rate'], 1); ?>%</h5>
                            <small class="text-muted">completion</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<script>
// Top Performers Chart
<?php if (!empty($top_performers)): ?>
const topPerformersCtx = document.getElementById('topPerformersChart');
if (topPerformersCtx) {
    new Chart(topPerformersCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(function($m) { 
                return substr($m['manager_name'], 0, 20); 
            }, $top_performers)); ?>,
            datasets: [{
                label: 'Completion Rate (%)',
                data: <?php echo json_encode(array_map(function($m) { 
                    return round($m['completion_rate'], 1); 
                }, $top_performers)); ?>,
                backgroundColor: '#28a745',
                borderColor: '#1e7e34',
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
                            return 'Completion: ' + context.parsed.x + '%';
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Team Size Distribution Chart
<?php if (!empty($managers_data)): ?>
const teamSizeCtx = document.getElementById('teamSizeChart');
if (teamSizeCtx) {
    const teamSizeData = <?php 
        $ranges = ['1-3' => 0, '4-7' => 0, '8-15' => 0, '16+' => 0];
        foreach ($managers_data as $manager) {
            $size = $manager['team_size'];
            if ($size <= 3) $ranges['1-3']++;
            else if ($size <= 7) $ranges['4-7']++;
            else if ($size <= 15) $ranges['8-15']++;
            else $ranges['16+']++;
        }
        echo json_encode(array_values($ranges));
    ?>;
    
    new Chart(teamSizeCtx, {
        type: 'pie',
        data: {
            labels: ['1-3 members', '4-7 members', '8-15 members', '16+ members'],
            datasets: [{
                data: teamSizeData,
                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
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
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            return `${label}: ${value} manager${value !== 1 ? 's' : ''}`;
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

