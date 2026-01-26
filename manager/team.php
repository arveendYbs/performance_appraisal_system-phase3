<?php
// manager/team.php
require_once __DIR__ . '/../config/config.php';

// Check if user can access team features
if (!canAccessTeamFeatures()) {
    redirect(BASE_URL . '/index.php', 'Access denied. You need to be a manager or have team members to access this page.', 'error');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get current user info
    $current_user = new User($db);
    $current_user->id = $_SESSION['user_id'];
    $current_user->readOne();
    
    $is_dept_manager = isDepartmentManager();
    $user_department = getUserDepartment();
    $user_company_id = $current_user->company_id;
    
    // Get company name
    $company_name = $current_user->company_name ?? 'N/A';
    
    // SECTION 1: Direct Reports (always show for everyone)
    $direct_reports = [];
    $direct_query = "SELECT u.id, u.name, u.emp_number, u.position, u.department, u.site, u.email, u.role,
                            c.name as company_name,
                            (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id) as total_appraisals,
                            (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id AND status = 'completed') as completed_appraisals
                     FROM users u
                     LEFT JOIN companies c ON u.company_id = c.id
                     WHERE u.direct_superior = ? AND u.is_active = 1
                     ORDER BY u.name";
    
    $stmt = $db->prepare($direct_query);
    $stmt->execute([$_SESSION['user_id']]);
    $direct_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // SECTION 2: All Department Members (only for dept managers)
    $dept_members = [];
    if ($is_dept_manager && $user_department && $user_company_id) {
        // Get ALL department members from SAME COMPANY (excluding self and direct reports)
        $direct_report_ids = array_column($direct_reports, 'id');
        $exclude_ids = array_merge([$_SESSION['user_id']], $direct_report_ids);
        $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
        
        $dept_query = "SELECT u.id, u.name, u.emp_number, u.position, u.department, u.site, u.email, u.role,
                              c.name as company_name,
                              sup.name as supervisor_name,
                              (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id) as total_appraisals,
                              (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id AND status = 'completed') as completed_appraisals
                       FROM users u
                       LEFT JOIN companies c ON u.company_id = c.id
                       LEFT JOIN users sup ON u.direct_superior = sup.id
                       WHERE u.department = ? 
                       AND u.company_id = ?
                       AND u.is_active = 1 
                       AND u.id NOT IN ($placeholders)
                       ORDER BY 
                         CASE 
                             WHEN u.role = 'manager' THEN 1
                             WHEN u.role = 'employee' THEN 2
                             WHEN u.role = 'worker' THEN 3
                             ELSE 4
                         END,
                         u.name";
        
        $params = array_merge([$user_department, $user_company_id], $exclude_ids);
        $stmt = $db->prepare($dept_query);
        $stmt->execute($params);
        $dept_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Dept Manager - Company: $company_name, Dept: $user_department, Found: " . count($dept_members) . " dept members");
    }
    
} catch (Exception $e) {
    error_log("Team view error: " . $e->getMessage());
    $direct_reports = [];
    $dept_members = [];
}

$total_team = count($direct_reports) + count($dept_members);
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-people me-2"></i>
                    <?php if ($is_dept_manager): ?>
                        <?php echo htmlspecialchars(ucfirst($user_department)); ?> Department Team
                    <?php else: ?>
                        My Team
                    <?php endif; ?>
                </h1>
                <?php if ($is_dept_manager): ?>
                <p class="text-muted mb-0">
                    <small>
                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($company_name); ?> - 
                        <?php echo htmlspecialchars(ucfirst($user_department)); ?> Department
                    </small>
                </p>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge bg-primary fs-6">
                    <?php echo $total_team; ?> Total Member<?php echo $total_team != 1 ? 's' : ''; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php if ($is_dept_manager): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Department Manager Access:</strong> As a <?php echo htmlspecialchars(ucfirst($user_department)); ?> 
    department manager at <strong><?php echo htmlspecialchars($company_name); ?></strong>, you can view:
    <ul class="mb-0 mt-2">
        <li>Your direct reports (Section 1)</li>
        <li>All <?php echo htmlspecialchars(ucfirst($user_department)); ?> employees/workers in your company (Section 2)</li>
    </ul>
    <small class="d-block mt-2"><strong>Note:</strong> You can only review appraisals for your direct reports.</small>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif (!hasRole('manager') && hasSubordinates()): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Team Lead Access:</strong> You have access to these features because you have team members reporting to you.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- SECTION 1: Direct Reports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-person-lines-fill me-2"></i>Section 1: My Direct Reports
                    <span class="badge bg-light text-primary ms-2"><?php echo count($direct_reports); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($direct_reports)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-people display-4 text-muted mb-3"></i>
                    <h6 class="text-muted">No Direct Reports</h6>
                    <p class="text-muted mb-0">
                        <small>You don't have any employees reporting directly to you.</small>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Site</th>
                                <th>Appraisals</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($direct_reports as $member): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                            <span class="badge bg-success ms-2" style="font-size: 0.7rem;">Direct Report</span>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['emp_number']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($member['position']); ?></td>
                                <td>
                                    <?php
                                    $role_badges = [
                                        'admin' => 'danger',
                                        'manager' => 'warning',
                                        'employee' => 'info',
                                        'worker' => 'secondary'
                                    ];
                                    $badge_class = $role_badges[$member['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($member['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($member['department']); ?></td>
                                <td><?php echo htmlspecialchars($member['site'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo $member['completed_appraisals']; ?></span>
                                    <span class="text-muted">/</span>
                                    <span class="badge bg-secondary"><?php echo $member['total_appraisals']; ?></span>
                                    <br>
                                    <small class="text-muted">Done / Total</small>
                                </td>
                                <td>
                                    <a href="employee_history.php?user_id=<?php echo $member['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-clock-history me-1"></i>View History
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

<!-- SECTION 2: All Department Members (Only for Department Managers) -->
<?php if ($is_dept_manager): ?>
<div class="row">
    <div class="col-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-building me-2"></i>Section 2: All <?php echo htmlspecialchars(ucfirst($user_department)); ?> Department Employees
                    <span class="badge bg-light text-info ms-2"><?php echo count($dept_members); ?></span>
                </h5>
                <small class="d-block mt-1">
                    Showing all <?php echo htmlspecialchars(ucfirst($user_department)); ?> employees from 
                    <strong><?php echo htmlspecialchars($company_name); ?></strong> (excluding your direct reports above)
                </small>
            </div>
            <div class="card-body">
                <?php if (empty($dept_members)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-people display-4 text-muted mb-3"></i>
                    <h6 class="text-muted">No Other Department Members</h6>
                    <p class="text-muted mb-0">
                        <small>No other employees in <?php echo htmlspecialchars(ucfirst($user_department)); ?> department 
                        at <?php echo htmlspecialchars($company_name); ?>.</small>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Role</th>
                                <th>Reports To</th>
                                <th>Site</th>
                                <th>Appraisals</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_members as $member): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-info rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['emp_number']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($member['position']); ?></td>
                                <td>
                                    <?php
                                    $role_badges = [
                                        'admin' => 'danger',
                                        'manager' => 'warning',
                                        'employee' => 'info',
                                        'worker' => 'secondary'
                                    ];
                                    $badge_class = $role_badges[$member['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($member['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($member['supervisor_name']): ?>
                                        <small><?php echo htmlspecialchars($member['supervisor_name']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($member['site'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo $member['completed_appraisals']; ?></span>
                                    <span class="text-muted">/</span>
                                    <span class="badge bg-secondary"><?php echo $member['total_appraisals']; ?></span>
                                    <br>
                                    <small class="text-muted">Done / Total</small>
                                </td>
                                <td>
                                    <a href="employee_history.php?user_id=<?php echo $member['id']; ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 p-3 bg-light rounded">
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Department View:</strong> 
                        Showing <?php echo count($dept_members); ?> additional employee<?php echo count($dept_members) != 1 ? 's' : ''; ?> 
                        in the <?php echo htmlspecialchars(ucfirst($user_department)); ?> department at 
                        <?php echo htmlspecialchars($company_name); ?>. 
                        You can view their information but can only review appraisals for your direct reports (Section 1).
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Summary Footer -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h3 class="text-primary mb-0"><?php echo count($direct_reports); ?></h3>
                        <small class="text-muted">Direct Reports</small>
                    </div>
                    <?php if ($is_dept_manager): ?>
                    <div class="col-md-4">
                        <h3 class="text-info mb-0"><?php echo count($dept_members); ?></h3>
                        <small class="text-muted">Department Members</small>
                    </div>
                    <div class="col-md-4">
                        <h3 class="text-success mb-0"><?php echo $total_team; ?></h3>
                        <small class="text-muted">Total Visible</small>
                    </div>
                    <?php else: ?>
                    <div class="col-md-4">
                        <h3 class="text-success mb-0"><?php echo $total_team; ?></h3>
                        <small class="text-muted">Total Team</small>
                    </div>
                    <div class="col-md-4">
                        <a href="review/pending.php" class="btn btn-warning">
                            <i class="bi bi-clipboard-check me-2"></i>Pending Reviews
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>