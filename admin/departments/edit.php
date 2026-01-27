<?php
// admin/departments/edit.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Department.php';
require_once __DIR__ . '/../../classes/Company.php';

$department_id = $_GET['id'] ?? 0;
if (!$department_id) {
    redirect('index.php', 'Department ID is required.', 'error');
}

$department = new Department($db);
$department->id = $department_id;

if (!$department->readOne()) {
    redirect('index.php', 'Department not found.', 'error');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $department->department_name = sanitize($_POST['department_name'] ?? '');
        $department->department_code = sanitize($_POST['department_code'] ?? '');
        $department->parent_department_id = !empty($_POST['parent_department_id']) ? $_POST['parent_department_id'] : null;
        
        // Approval chain configuration
        $department->level_2_approver_id = !empty($_POST['level_2_approver_id']) ? $_POST['level_2_approver_id'] : null;
        $department->level_2_role_name = sanitize($_POST['level_2_role_name'] ?? 'team_lead');
        $department->level_3_approver_id = !empty($_POST['level_3_approver_id']) ? $_POST['level_3_approver_id'] : null;
        $department->level_3_role_name = sanitize($_POST['level_3_role_name'] ?? 'production_manager');
        $department->level_4_approver_id = !empty($_POST['level_4_approver_id']) ? $_POST['level_4_approver_id'] : null;
        $department->level_4_role_name = sanitize($_POST['level_4_role_name'] ?? 'operations_manager');
        $department->level_5_approver_id = !empty($_POST['level_5_approver_id']) ? $_POST['level_5_approver_id'] : null;
        $department->level_5_role_name = sanitize($_POST['level_5_role_name'] ?? 'coo');
        $department->level_6_approver_id = !empty($_POST['level_6_approver_id']) ? $_POST['level_6_approver_id'] : null;
        $department->level_6_role_name = sanitize($_POST['level_6_role_name'] ?? 'group_ceo');
        
        // Approval levels by employee type
        $department->staff_approval_levels = intval($_POST['staff_approval_levels'] ?? 2);
        $department->worker_approval_levels = intval($_POST['worker_approval_levels'] ?? 5);
        $department->supervisor_approval_levels = intval($_POST['supervisor_approval_levels'] ?? 3);
        $department->manager_approval_levels = intval($_POST['manager_approval_levels'] ?? 3);
        $department->executive_approval_levels = intval($_POST['executive_approval_levels'] ?? 2);
        $department->probation_approval_levels = intval($_POST['probation_approval_levels'] ?? 2);
        
        $department->cost_center = sanitize($_POST['cost_center'] ?? '');
        $department->location = sanitize($_POST['location'] ?? '');
        $department->is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($department->department_name)) {
            $error_message = 'Department name is required.';
        } elseif (empty($department->department_code)) {
            $error_message = 'Department code is required.';
        } else {
            if ($department->update()) {
                logActivity($_SESSION['user_id'], 'UPDATE', 'departments', $department->id, 
                           null, ['name' => $department->department_name], 
                           'Updated department: ' . $department->department_name);
                
                $success_message = 'Department updated successfully!';
            } else {
                $error_message = 'Failed to update department.';
            }
        }
    }
}

// Get employee count
$employee_count = $department->getEmployeeCount();

// Get companies
$company_model = new Company($db);
$companies_stmt = $company_model->getAll();
$companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for this company
$users_query = "SELECT id, name, emp_number, position 
                FROM users 
                WHERE company_id = ? AND is_active = 1 
                ORDER BY name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute([$department->company_id]);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get other departments in same company (for parent selection)
$depts_query = "SELECT id, department_name 
                FROM departments 
                WHERE company_id = ? AND id != ? AND is_active = 1 
                ORDER BY department_name";
$depts_stmt = $db->prepare($depts_query);
$depts_stmt->execute([$department->company_id, $department->id]);
$other_departments = $depts_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Edit Department</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Departments</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($department->department_name); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST" id="departmentForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <!-- Info Alert -->
                            <?php if ($employee_count > 0): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    This department has <strong><?php echo $employee_count; ?> active employees</strong>. 
                                    Changes to approval configuration will affect future appraisals.
                                </div>
                            <?php endif; ?>
                            
                            <!-- Basic Information -->
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Company</label>
                                <input type="text" class="form-control" value="<?php 
                                    $comp = array_filter($companies, fn($c) => $c['id'] == $department->company_id);
                                    echo htmlspecialchars(reset($comp)['name'] ?? 'Unknown');
                                ?>" disabled>
                                <small class="text-muted">Company cannot be changed after creation</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="department_name" 
                                               name="department_name" required 
                                               value="<?php echo htmlspecialchars($department->department_name); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="department_code" class="form-label">Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="department_code" 
                                               name="department_code" required 
                                               value="<?php echo htmlspecialchars($department->department_code); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parent_department_id" class="form-label">Parent Department</label>
                                        <select class="form-select" id="parent_department_id" name="parent_department_id">
                                            <option value="">None (Top-level department)</option>
                                            <?php foreach ($other_departments as $other): ?>
                                                <option value="<?php echo $other['id']; ?>" 
                                                        <?php echo $department->parent_department_id == $other['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($other['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="cost_center" class="form-label">Cost Center</label>
                                        <input type="text" class="form-control" id="cost_center" 
                                               name="cost_center" 
                                               value="<?php echo htmlspecialchars($department->cost_center ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" 
                                           name="is_active" <?php echo $department->is_active ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Active Department
                                    </label>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Approval Chain Configuration -->
                            <h5 class="mb-3">Approval Chain Configuration</h5>
                            
                            <?php
                            // Helper function to create approver card
                            function renderApproverCard($level, $label, $description, $department, $users) {
                                $approver_field = "level_{$level}_approver_id";
                                $role_field = "level_{$level}_role_name";
                                ?>
                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <strong>Level <?php echo $level; ?> Approver</strong> 
                                        <span class="text-muted">(<?php echo $description; ?>)</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <label class="form-label">Select Approver</label>
                                                <select class="form-select" name="<?php echo $approver_field; ?>">
                                                    <option value="">None</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?php echo $user['id']; ?>" 
                                                                <?php echo $department->$approver_field == $user['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($user['name']); ?> 
                                                            (<?php echo htmlspecialchars($user['emp_number']); ?>) - 
                                                            <?php echo htmlspecialchars($user['position']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Role Name</label>
                                                <input type="text" class="form-control" name="<?php echo $role_field; ?>" 
                                                       value="<?php echo htmlspecialchars($department->$role_field ?? $label); ?>" 
                                                       placeholder="<?php echo $label; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            
                            renderApproverCard(2, 'team_lead', 'Usually Team Lead or Department Head', $department, $users);
                            renderApproverCard(3, 'production_manager', 'Production Manager / Senior Manager', $department, $users);
                            renderApproverCard(4, 'operations_manager', 'Operations Manager / GM', $department, $users);
                            renderApproverCard(5, 'coo', 'COO / MD / Company Head', $department, $users);
                            renderApproverCard(6, 'group_ceo', 'Group CEO / Chairman - Rare', $department, $users);
                            ?>
                            
                            <hr class="my-4">
                            
                            <!-- Approval Levels by Employee Type -->
                            <h5 class="mb-3">Approval Levels by Employee Type</h5>
                            
                            <div class="row">
                                <?php
                                function renderLevelSelect($name, $label, $description, $value) {
                                    ?>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label"><?php echo $label; ?></label>
                                            <select class="form-select" name="<?php echo $name; ?>">
                                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo $value == $i ? 'selected' : ''; ?>>
                                                        <?php echo $i; ?> level<?php echo $i > 1 ? 's' : ''; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <small class="text-muted"><?php echo $description; ?></small>
                                        </div>
                                    </div>
                                    <?php
                                }
                                
                                renderLevelSelect('staff_approval_levels', 'Office Staff', 'Direct → Team Lead', $department->staff_approval_levels);
                                renderLevelSelect('worker_approval_levels', 'Production Workers', 'All 5 levels', $department->worker_approval_levels);
                                renderLevelSelect('supervisor_approval_levels', 'Supervisors', 'Skip Team Lead', $department->supervisor_approval_levels);
                                renderLevelSelect('manager_approval_levels', 'Managers', 'To GM level', $department->manager_approval_levels);
                                renderLevelSelect('executive_approval_levels', 'Executives', 'Minimal levels', $department->executive_approval_levels);
                                renderLevelSelect('probation_approval_levels', 'Probation (Max)', 'Limits all types', $department->probation_approval_levels);
                                ?>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Info Sidebar -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-info-circle"></i> Department Info</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Employees:</th>
                                <td><?php echo $employee_count; ?></td>
                            </tr>
                            <tr>
                                <th>Created:</th>
                                <td><?php echo date('M d, Y', strtotime($department->created_at)); ?></td>
                            </tr>
                            <tr>
                                <th>Updated:</th>
                                <td><?php echo date('M d, Y', strtotime($department->updated_at)); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <?php if ($department->is_active): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-lightbulb"></i> Important Notes</h6>
                        <ul class="small mb-0">
                            <li>Changes to approval levels affect <strong>future appraisals only</strong></li>
                            <li>Existing pending appraisals keep their current chain</li>
                            <li>System auto-skips duplicate approvers</li>
                            <li>Level 1 is always the direct superior</li>
                            <li>Configure at least Level 2 for basic approval</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>