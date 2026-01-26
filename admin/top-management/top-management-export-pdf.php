<?php
// top-management/export-pdf.php
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
    
    // Get all statistics (same queries as in index.php)
    
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
    
    // Company Statistics
    $company_query = "SELECT 
                        c.name as company_name,
                        COUNT(*) as total_appraisals,
                        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN a.status = 'submitted' OR a.status = 'in_review' THEN 1 ELSE 0 END) as pending_review,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as not_submitted,
                        AVG(CASE WHEN a.status = 'completed' THEN a.total_score ELSE NULL END) as avg_score,
                        COUNT(DISTINCT u.id) as total_employees
                      FROM appraisals a
                      $base_conditions
                      GROUP BY c.name
                      ORDER BY c.name";
    
    $company_stmt = $db->prepare($company_query);
    $company_stmt->execute($base_params);
    $company_stats = $company_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Department Statistics
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
    
} catch (Exception $e) {
    error_log("Top Management PDF Export error: " . $e->getMessage());
    die("Error generating report.");
}

// Generate PDF using TCPDF or similar library
// For now, we'll create a simple HTML export that can be saved as PDF

// Set headers for download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="Top_Management_Report_' . date('Y-m-d') . '.html"');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Management Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24pt;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            border: 2px solid #ddd;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
        }
        
        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 12pt;
            color: #666;
        }
        
        .stat-box .value {
            font-size: 28pt;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-box.total { border-color: #007bff; }
        .stat-box.completed { border-color: #28a745; }
        .stat-box.pending { border-color: #ffc107; }
        .stat-box.submitted { border-color: #17a2b8; }
        .stat-box.review { border-color: #6c757d; }
        .stat-box.draft { border-color: #dc3545; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        table th {
            background: #f8f9fa;
            padding: 12px 8px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-weight: bold;
        }
        
        table td {
            padding: 10px 8px;
            border: 1px solid #dee2e6;
        }
        
        table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .section-title {
            font-size: 18pt;
            font-weight: bold;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        
        .grade-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .grade-item {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        
        .grade-item .grade {
            font-size: 18pt;
            font-weight: bold;
        }
        
        .grade-item .count {
            font-size: 14pt;
            color: #666;
        }
        
        @media print {
            .stats-grid {
                page-break-inside: avoid;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Top Management Dashboard Report</h1>
        <p>Generated on: <?php echo date('F d, Y g:i A'); ?></p>
        <?php if ($company_filter): ?>
        <p>Company: <?php 
            $selected_company = array_filter($top_mgmt_companies, function($c) use ($company_filter) {
                return $c['id'] == $company_filter;
            });
            echo $selected_company ? reset($selected_company)['name'] : 'All Companies';
        ?></p>
        <?php else: ?>
        <p>Company: All Companies</p>
        <?php endif; ?>
        <p>Year: <?php echo $year_filter; ?></p>
    </div>

    <!-- Overall Statistics -->
    <h2 class="section-title">Overall Statistics</h2>
    <div class="stats-grid">
        <div class="stat-box total">
            <h3>Total</h3>
            <div class="value"><?php echo $overall_stats['total'] ?? 0; ?></div>
        </div>
        <div class="stat-box completed">
            <h3>Completed</h3>
            <div class="value"><?php echo $overall_stats['completed'] ?? 0; ?></div>
            <small><?php 
                $total = $overall_stats['total'] ?? 0;
                $completed = $overall_stats['completed'] ?? 0;
                echo $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%'; 
            ?></small>
        </div>
        <div class="stat-box pending">
            <h3>Pending Review</h3>
            <div class="value"><?php echo ($overall_stats['submitted'] ?? 0) + ($overall_stats['in_review'] ?? 0); ?></div>
        </div>
        <div class="stat-box submitted">
            <h3>Submitted</h3>
            <div class="value"><?php echo $overall_stats['submitted'] ?? 0; ?></div>
        </div>
        <div class="stat-box review">
            <h3>In Review</h3>
            <div class="value"><?php echo $overall_stats['in_review'] ?? 0; ?></div>
        </div>
        <div class="stat-box draft">
            <h3>Not Submitted</h3>
            <div class="value"><?php echo $overall_stats['draft'] ?? 0; ?></div>
        </div>
    </div>

    <!-- Company Statistics -->
    <h2 class="section-title">Progress by Company</h2>
    <table>
        <thead>
            <tr>
                <th>Company</th>
                <th style="text-align: center;">Employees</th>
                <th style="text-align: center;">Total</th>
                <th style="text-align: center;">Completed</th>
                <th style="text-align: center;">Pending Review</th>
                <th style="text-align: center;">Not Submitted</th>
                <th style="text-align: center;">Avg Score</th>
                <th style="text-align: center;">Progress %</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($company_stats)): ?>
            <tr>
                <td colspan="8" style="text-align: center;">No data available</td>
            </tr>
            <?php else: ?>
            <?php foreach ($company_stats as $stat): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($stat['company_name']); ?></strong></td>
                <td style="text-align: center;"><?php echo $stat['total_employees']; ?></td>
                <td style="text-align: center;"><?php echo $stat['total_appraisals']; ?></td>
                <td style="text-align: center;"><strong><?php echo $stat['completed']; ?></strong></td>
                <td style="text-align: center;"><?php echo $stat['pending_review']; ?></td>
                <td style="text-align: center;"><?php echo $stat['not_submitted']; ?></td>
                <td style="text-align: center;">
                    <?php echo $stat['avg_score'] ? round($stat['avg_score'], 1) . '%' : '-'; ?>
                </td>
                <td style="text-align: center;">
                    <?php 
                    $progress = $stat['total_appraisals'] > 0 
                        ? round(($stat['completed'] / $stat['total_appraisals']) * 100, 1) 
                        : 0;
                    echo $progress . '%';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Department Statistics -->
    <h2 class="section-title">Progress by Department</h2>
    <table>
        <thead>
            <tr>
                <th>Department</th>
                <th>Company</th>
                <th style="text-align: center;">Total</th>
                <th style="text-align: center;">Completed</th>
                <th style="text-align: center;">Pending</th>
                <th style="text-align: center;">Not Submitted</th>
                <th style="text-align: center;">Avg Score</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dept_stats)): ?>
            <tr>
                <td colspan="7" style="text-align: center;">No data available</td>
            </tr>
            <?php else: ?>
            <?php foreach ($dept_stats as $stat): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($stat['department']); ?></strong></td>
                <td><?php echo htmlspecialchars($stat['company_name']); ?></td>
                <td style="text-align: center;"><?php echo $stat['total_appraisals']; ?></td>
                <td style="text-align: center;"><strong><?php echo $stat['completed']; ?></strong></td>
                <td style="text-align: center;"><?php echo $stat['pending_review']; ?></td>
                <td style="text-align: center;"><?php echo $stat['not_submitted']; ?></td>
                <td style="text-align: center;">
                    <?php echo $stat['avg_score'] ? round($stat['avg_score'], 1) . '%' : '-'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Grade Distribution -->
    <?php if (!empty($grade_distribution)): ?>
    <h2 class="section-title">Grade Distribution</h2>
    <div class="grade-list">
        <?php 
        $total_graded = array_sum(array_column($grade_distribution, 'count'));
        foreach ($grade_distribution as $grade): 
            $percentage = ($grade['count'] / $total_graded) * 100;
        ?>
        <div class="grade-item">
            <div class="grade"><?php echo htmlspecialchars($grade['grade']); ?></div>
            <div class="count"><?php echo $grade['count']; ?> (<?php echo round($percentage, 1); ?>%)</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div style="margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 10pt;">
        <p>This report was generated by the Performance Appraisal System</p>
        <p>© <?php echo date('Y'); ?> YBS International Berhad. All rights reserved.</p>
    </div>
</body>
</html>