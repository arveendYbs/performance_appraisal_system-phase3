<?php
// admin/email_diagnostic.php
require_once __DIR__ . '/../config/config.php';

// Check if user is admin
if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied. Admin only.', 'error');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$database = new Database();
$db = $database->getConnection();

// Check for employees without direct_superior
$query1 = "SELECT u.id, u.name, u.emp_number, u.email, u.emp_email, u.position, 
                  u.department, u.direct_superior, c.name as company_name,
                  (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id) as appraisal_count
           FROM users u
           LEFT JOIN companies c ON u.company_id = c.id
           WHERE u.is_active = 1 AND u.direct_superior IS NULL
           ORDER BY u.name";

$stmt1 = $db->prepare($query1);
$stmt1->execute();
$users_without_supervisor = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// Check for employees with inactive supervisors
$query2 = "SELECT u.id, u.name, u.emp_number, u.email, u.position, 
                  u.direct_superior, 
                  m.name as supervisor_name, m.email as supervisor_email, 
                  m.emp_email as supervisor_work_email, m.is_active as supervisor_active,
                  c.name as company_name,
                  (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id) as appraisal_count
           FROM users u
           LEFT JOIN users m ON u.direct_superior = m.id
           LEFT JOIN companies c ON u.company_id = c.id
           WHERE u.is_active = 1 
           AND u.direct_superior IS NOT NULL 
           AND (m.is_active = 0 OR m.id IS NULL)
           ORDER BY u.name";

$stmt2 = $db->prepare($query2);
$stmt2->execute();
$users_with_inactive_supervisor = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Check for supervisors without email
$query3 = "SELECT u.id, u.name, u.emp_number, u.position,
                  m.id as supervisor_id, m.name as supervisor_name, 
                  m.email as supervisor_email, m.emp_email as supervisor_work_email,
                  c.name as company_name,
                  (SELECT COUNT(*) FROM appraisals WHERE user_id = u.id) as appraisal_count
           FROM users u
           LEFT JOIN users m ON u.direct_superior = m.id
           LEFT JOIN companies c ON u.company_id = c.id
           WHERE u.is_active = 1 
           AND u.direct_superior IS NOT NULL 
           AND m.is_active = 1
           AND (m.email IS NULL OR m.email = '')
           AND (m.emp_email IS NULL OR m.emp_email = '')
           ORDER BY u.name";

$stmt3 = $db->prepare($query3);
$stmt3->execute();
$supervisors_without_email = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// Check appraisals that were submitted but manager might not have received email
$query4 = "SELECT a.id as appraisal_id, a.status, a.employee_submitted_at,
                  u.name as employee_name, u.email as employee_email,
                  u.direct_superior,
                  m.name as manager_name,
                  COALESCE(NULLIF(m.emp_email, ''), m.email) as manager_email,
                  (SELECT COUNT(*) FROM email_logs 
                   WHERE appraisal_id = a.id 
                   AND email_type = 'appraisal_submitted_manager'
                   AND status = 'sent') as manager_email_count,
                  (SELECT COUNT(*) FROM email_logs 
                   WHERE appraisal_id = a.id 
                   AND email_type = 'appraisal_submitted_employee'
                   AND status = 'sent') as employee_email_count
           FROM appraisals a
           JOIN users u ON a.user_id = u.id
           LEFT JOIN users m ON u.direct_superior = m.id
           WHERE a.status IN ('submitted', 'in_review', 'completed')
           AND a.employee_submitted_at IS NOT NULL
           ORDER BY a.employee_submitted_at DESC
           LIMIT 50";

$stmt4 = $db->prepare($query4);
$stmt4->execute();
$recent_submissions = $stmt4->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-2">
                    <i class="bi bi-bug me-2"></i>Email Diagnostic Tool
                </h1>
                <p class="text-muted mb-0">Identify issues preventing email delivery</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <i class="bi bi-person-x display-4 text-danger"></i>
                        <h3 class="mt-2 mb-0"><?php echo count($users_without_supervisor); ?></h3>
                        <p class="text-muted mb-0">No Supervisor</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <i class="bi bi-person-dash display-4 text-warning"></i>
                        <h3 class="mt-2 mb-0"><?php echo count($users_with_inactive_supervisor); ?></h3>
                        <p class="text-muted mb-0">Inactive Supervisor</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <i class="bi bi-envelope-x display-4 text-warning"></i>
                        <h3 class="mt-2 mb-0"><?php echo count($supervisors_without_email); ?></h3>
                        <p class="text-muted mb-0">Supervisor No Email</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-check display-4 text-info"></i>
                        <h3 class="mt-2 mb-0"><?php echo count($recent_submissions); ?></h3>
                        <p class="text-muted mb-0">Recent Submissions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Issue 1: Employees without supervisor -->
        <?php if (count($users_without_supervisor) > 0): ?>
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Critical: Employees Without Direct Supervisor (<?php echo count($users_without_supervisor); ?>)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-danger">
                    <strong>Issue:</strong> These employees have no direct_superior assigned. 
                    Their managers will NOT receive appraisal notification emails.
                </p>
                <p><strong>Action Required:</strong> Assign a direct supervisor to these employees.</p>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Emp #</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Company</th>
                                <th>Appraisals</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_without_supervisor as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user['emp_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['position']); ?></td>
                                <td><?php echo htmlspecialchars($user['department']); ?></td>
                                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $user['appraisal_count']; ?></span>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/admin/users/edit.php?id=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Issue 2: Employees with inactive supervisors -->
        <?php if (count($users_with_inactive_supervisor) > 0): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    Warning: Employees With Inactive/Missing Supervisors (<?php echo count($users_with_inactive_supervisor); ?>)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-warning">
                    <strong>Issue:</strong> These employees have a direct_superior assigned, but that supervisor is inactive or doesn't exist.
                </p>
                <p><strong>Action Required:</strong> Reassign to an active supervisor.</p>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Supervisor Info</th>
                                <th>Company</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_with_inactive_supervisor as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user['position']); ?></td>
                                <td>
                                    <span class="badge bg-danger">
                                        ID: <?php echo $user['direct_superior']; ?> - 
                                        <?php echo $user['supervisor_name'] ?? 'Not Found'; ?>
                                        (<?php echo $user['supervisor_active'] ? 'Active' : 'Inactive'; ?>)
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/admin/users/edit.php?id=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Fix
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Issue 3: Supervisors without email -->
        <?php if (count($supervisors_without_email) > 0): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="bi bi-envelope-x-fill me-2"></i>
                    Warning: Supervisors Without Email Addresses (<?php echo count($supervisors_without_email); ?>)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-warning">
                    <strong>Issue:</strong> These supervisors have no email address (both personal and work email are empty).
                </p>
                <p><strong>Action Required:</strong> Add an email address for these supervisors.</p>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Supervisor (No Email)</th>
                                <th>Company</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supervisors_without_email as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['position']); ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo htmlspecialchars($user['supervisor_name']); ?>
                                        (ID: <?php echo $user['supervisor_id']; ?>)
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/admin/users/edit.php?id=<?php echo $user['supervisor_id']; ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="bi bi-envelope-plus"></i> Add Email
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Submissions Email Status -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Recent Appraisal Submissions - Email Status Check
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Shows the last 50 submitted appraisals and whether emails were sent to both employee and manager.
                </p>
                
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Appraisal ID</th>
                                <th>Employee</th>
                                <th>Manager</th>
                                <th>Submitted Date</th>
                                <th>Status</th>
                                <th>Employee Email</th>
                                <th>Manager Email</th>
                                <th>Issue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_submissions as $submission): 
                                $has_issue = false;
                                $issue_text = '';
                                
                                // Check for issues
                                if (empty($submission['direct_superior'])) {
                                    $has_issue = true;
                                    $issue_text = 'No supervisor assigned';
                                } elseif (empty($submission['manager_email'])) {
                                    $has_issue = true;
                                    $issue_text = 'Supervisor has no email';
                                } elseif ($submission['manager_email_count'] == 0 && $submission['employee_email_count'] > 0) {
                                    $has_issue = true;
                                    $issue_text = 'Manager email not sent';
                                }
                            ?>
                            <tr class="<?php echo $has_issue ? 'table-warning' : ''; ?>">
                                <td><?php echo $submission['appraisal_id']; ?></td>
                                <td>
                                    <small>
                                        <strong><?php echo htmlspecialchars($submission['employee_name']); ?></strong><br>
                                        <?php echo htmlspecialchars($submission['employee_email']); ?>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <?php if ($submission['manager_name']): ?>
                                            <strong><?php echo htmlspecialchars($submission['manager_name']); ?></strong><br>
                                            <?php echo htmlspecialchars($submission['manager_email'] ?? 'No email'); ?>
                                        <?php else: ?>
                                            <span class="text-danger">No supervisor</span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <small><?php echo date('Y-m-d H:i', strtotime($submission['employee_submitted_at'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $submission['status'] === 'submitted' ? 'warning' : 
                                            ($submission['status'] === 'in_review' ? 'info' : 'success'); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($submission['employee_email_count'] > 0): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sent</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Not Sent</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (empty($submission['direct_superior'])): ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php elseif ($submission['manager_email_count'] > 0): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sent</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Not Sent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($has_issue): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <?php echo $issue_text; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="bi bi-check-circle"></i> OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Fix Guide -->
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-lightbulb-fill me-2"></i>Quick Fix Guide</h5>
            </div>
            <div class="card-body">
                <h6>Common Issues and Solutions:</h6>
                <ol>
                    <li class="mb-2">
                        <strong>Employee has no direct supervisor:</strong>
                        <ul>
                            <li>Go to Admin → Users → Edit the employee</li>
                            <li>Assign their direct supervisor in the "Direct Superior" field</li>
                            <li>Save changes</li>
                        </ul>
                    </li>
                    <li class="mb-2">
                        <strong>Supervisor has no email:</strong>
                        <ul>
                            <li>Go to Admin → Users → Edit the supervisor</li>
                            <li>Add their work email (emp_email) or personal email</li>
                            <li>Save changes</li>
                        </ul>
                    </li>
                    <li class="mb-2">
                        <strong>Emails not sent for past appraisals:</strong>
                        <ul>
                            <li>First fix the supervisor assignment/email issues above</li>
                            <li>Then go to Admin → Email Test Panel</li>
                            <li>Select the appraisals and manually resend emails</li>
                        </ul>
                    </li>
                </ol>
                
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> After fixing these issues, future appraisal submissions will automatically 
                    send emails to the correct managers.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>