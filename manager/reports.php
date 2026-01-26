
<?php
// manager/reports.php
require_once __DIR__ . '/../config/config.php';

/* if (!hasRole('manager') && !hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
} */

    if (!canAccessTeamFeatures()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}


require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get team performance statistics
    $year = $_GET['year'] ?? date('Y');
    
    // Grade distribution
    $grade_query = "SELECT a.grade, COUNT(*) as count
                    FROM appraisals a
                    JOIN users u ON a.user_id = u.id
                    WHERE u.direct_superior = ? 
                    AND a.status = 'completed'
                    AND YEAR(a.manager_reviewed_at) = ?
                    AND a.grade IS NOT NULL
                    GROUP BY a.grade
                    ORDER BY a.grade";
    
    $stmt = $db->prepare($grade_query);
    $stmt->execute([$_SESSION['user_id'], $year]);
    $grade_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Average scores by department
    $dept_query = "SELECT u.department, AVG(a.total_score) as avg_score, COUNT(*) as count
                   FROM appraisals a
                   JOIN users u ON a.user_id = u.id
                   WHERE u.direct_superior = ? 
                   AND a.status = 'completed'
                   AND YEAR(a.manager_reviewed_at) = ?
                   AND a.total_score IS NOT NULL
                   GROUP BY u.department
                   ORDER BY avg_score DESC";
    
    $stmt = $db->prepare($dept_query);
    $stmt->execute([$_SESSION['user_id'], $year]);
    $dept_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly completion trend
    $trend_query = "SELECT MONTH(a.manager_reviewed_at) as month, COUNT(*) as count
                    FROM appraisals a
                    JOIN users u ON a.user_id = u.id
                    WHERE u.direct_superior = ? 
                    AND a.status = 'completed'
                    AND YEAR(a.manager_reviewed_at) = ?
                    GROUP BY MONTH(a.manager_reviewed_at)
                    ORDER BY month";
    
    $stmt = $db->prepare($trend_query);
    $stmt->execute([$_SESSION['user_id'], $year]);
    $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top and bottom performers
    $performers_query = "SELECT u.name, u.position, a.grade, a.total_score
                        FROM appraisals a
                        JOIN users u ON a.user_id = u.id
                        WHERE u.direct_superior = ? 
                        AND a.status = 'completed'
                        AND YEAR(a.manager_reviewed_at) = ?
                        AND a.total_score IS NOT NULL
                        ORDER BY a.total_score DESC";
    
    $stmt = $db->prepare($performers_query);
    $stmt->execute([$_SESSION['user_id'], $year]);
    $all_performers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $top_performers = array_slice($all_performers, 0, 5);
    $bottom_performers = array_slice(array_reverse($all_performers), 0, 5);
    
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $grade_distribution = [];
    $dept_performance = [];
    $monthly_trend = [];
    $top_performers = [];
    $bottom_performers = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-graph-up me-2"></i>Team Performance Reports
            </h1>
            <div class="d-flex gap-2">
                <select class="form-select" id="yearSelect" onchange="changeYear()">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $year) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-outline-secondary" onclick="exportReport()">
                    <i class="bi bi-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><?php echo count($all_performers); ?></h5>
                <p class="card-text">Completed Reviews</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <?php echo !empty($all_performers) ? round(array_sum(array_column($all_performers, 'total_score')) / count($all_performers), 1) : 0; ?>%
                </h5>
                <p class="card-text">Average Score</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning">
                    <?php 
                    $grade_a_count = 0;
                    foreach ($grade_distribution as $grade) {
                        if ($grade['grade'] === 'A') $grade_a_count = $grade['count'];
                    }
                    echo $grade_a_count;
                    ?>
                </h5>
                <p class="card-text">Grade A Performers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info"><?php echo count($dept_performance); ?></h5>
                <p class="card-text">Departments</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Grade Distribution -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Grade Distribution</h6>
            </div>
            <div class="card-body">
                <?php if (empty($grade_distribution)): ?>
                <p class="text-muted">No completed appraisals for this year.</p>
                <?php else: ?>
                <canvas id="gradeChart" width="400" height="300"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Department Performance -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Department Performance</h6>
            </div>
            <div class="card-body">
                <?php if (empty($dept_performance)): ?>
                <p class="text-muted">No department data available.</p>
                <?php else: ?>
                <canvas id="deptChart" width="400" height="300"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Performers -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Performers</h6>
            </div>
            <div class="card-body">
                <?php if (empty($top_performers)): ?>
                <p class="text-muted">No performance data available.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($top_performers as $index => $performer): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-warning me-2"><?php echo $index + 1; ?></span>
                            <strong><?php echo htmlspecialchars($performer['name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($performer['position']); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success"><?php echo $performer['grade']; ?></span><br>
                            <small class="text-muted"><?php echo $performer['total_score']; ?>%</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Areas for Improvement -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Needs Attention</h6>
            </div>
            <div class="card-body">
                <?php if (empty($bottom_performers)): ?>
                <p class="text-muted">No performance data available.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($bottom_performers as $performer): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <strong><?php echo htmlspecialchars($performer['name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($performer['position']); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-<?php echo $performer['total_score'] < 60 ? 'danger' : 'warning'; ?>">
                                <?php echo $performer['grade']; ?>
                            </span><br>
                            <small class="text-muted"><?php echo $performer['total_score']; ?>%</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Trend -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Completion Trend</h6>
            </div>
            <div class="card-body">
                <?php if (empty($monthly_trend)): ?>
                <p class="text-muted">No completion data for this year.</p>
                <?php else: ?>
                <canvas id="trendChart" width="400" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Grade Distribution Chart
<?php if (!empty($grade_distribution)): ?>
const gradeCtx = document.getElementById('gradeChart').getContext('2d');
new Chart(gradeCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($g) { return "'" . $g['grade'] . "'"; }, $grade_distribution)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($grade_distribution, 'count')); ?>],
            backgroundColor: [
                '#28a745', // A - Green
                '#17a2b8', // B+ - Teal
                '#007bff', // B - Blue
                '#ffc107', // B- - Yellow
                '#dc3545'  // C - Red
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Department Performance Chart
<?php if (!empty($dept_performance)): ?>
const deptCtx = document.getElementById('deptChart').getContext('2d');
new Chart(deptCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($d) { return "'" . $d['department'] . "'"; }, $dept_performance)); ?>],
        datasets: [{
            label: 'Average Score (%)',
            data: [<?php echo implode(',', array_map(function($d) { return round($d['avg_score'], 1); }, $dept_performance)); ?>],
            backgroundColor: '#007bff',
            borderColor: '#0056b3',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
<?php endif; ?>

// Monthly Trend Chart
<?php if (!empty($monthly_trend)): ?>
const trendCtx = document.getElementById('trendChart').getContext('2d');
const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($m) use ($months) { return "'" . $months[$m['month']] . "'"; }, $monthly_trend)); ?>],
        datasets: [{
            label: 'Completed Reviews',
            data: [<?php echo implode(',', array_column($monthly_trend, 'count')); ?>],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>

function changeYear() {
    const year = document.getElementById('yearSelect').value;
    window.location.href = '?year=' + year;
}

function exportReport() {
    // Placeholder for export functionality
    alert('Export functionality would generate a PDF/Excel report here');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
