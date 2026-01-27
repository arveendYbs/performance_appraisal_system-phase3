<?php
// admin/departments/create.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Department.php';
require_once __DIR__ . '/../../classes/Company.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $department = new Department($db);
        
        $department->company_id = $_POST['company_id'] ?? null;
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
        
        if (empty($department->company_id)) {
            $error_message = 'Company is required.';
        } elseif (empty($department->department_name)) {
            $error_message = 'Department name is required.';
        } elseif (empty($department->department_code)) {
            $error_message = 'Department code is required.';
        } else {
            if ($department->create()) {
                logActivity($_SESSION['user_id'], 'CREATE', 'departments', $department->id, null,
                           ['name' => $department->department_name], 
                           'Created department: ' . $department->department_name);
                
                redirect('index.php', 'Department created successfully!', 'success');
            } else {
                $error_message = 'Failed to create department. Code may already exist.';
            }
        }
    }
}

// Get companies
$company_model = new Company($db);
$companies_stmt = $company_model->getAll();
$companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for approver selection (will be filtered by company via AJAX)
$users_query = "SELECT id, name, emp_number, position FROM users WHERE is_active = 1 ORDER BY name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Create Department</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Departments</a></li>
                        <li class="breadcrumb-item active">Create</li>
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

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST" id="departmentForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <!-- Basic Information -->
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                <select class="form-select" id="company_id" name="company_id" required>
                                    <option value="">Select Company</option>
                                    <?php foreach ($companies as $comp): ?>
                                        <option value="<?php echo $comp['id']; ?>">
                                            <?php echo htmlspecialchars($comp['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="department_name" 
                                               name="department_name" required 
                                               placeholder="e.g., Sales, Production, IT">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="department_code" class="form-label">Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="department_code" 
                                               name="department_code" required 
                                               placeholder="e.g., SLS, PROD">
                                        <small class="text-muted">Short unique code</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parent_department_id" class="form-label">Parent Department</label>
                                        <select class="form-select" id="parent_department_id" name="parent_department_id">
                                            <option value="">None (Top-level department)</option>
                                            <!-- Will be populated via AJAX based on company -->
                                        </select>
                                        <small class="text-muted">For sub-departments</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="cost_center" class="form-label">Cost Center</label>
                                        <input type="text" class="form-control" id="cost_center" 
                                               name="cost_center" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Approval Chain Configuration -->
                            <h5 class="mb-3">Approval Chain Configuration</h5>
                            <p class="text-muted small">
                                Configure approvers for each level. Level 1 is always the employee's direct superior.
                                Levels 2-6 are department-wide approvers.
                            </p>
                            
                            <!-- Level 2 -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light">
                                    <strong>Level 2 Approver</strong> <span class="text-muted">(Usually Team Lead or Department Head)</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="form-label">Select Approver</label>
                                            <select class="form-select approver-select" id="level_2_approver_id" 
                                                    name="level_2_approver_id" data-level="2">
                                                <option value="">None</option>
                                                <!-- Will be populated via AJAX -->
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Role Name</label>
                                            <input type="text" class="form-control" name="level_2_role_name" 
                                                   value="team_lead" placeholder="team_lead">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Level 3 -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light">
                                    <strong>Level 3 Approver</strong> <span class="text-muted">(Production Manager / Senior Manager)</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="form-label">Select Approver</label>
                                            <select class="form-select approver-select" id="level_3_approver_id" 
                                                    name="level_3_approver_id" data-level="3">
                                                <option value="">None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Role Name</label>
                                            <input type="text" class="form-control" name="level_3_role_name" 
                                                   value="production_manager" placeholder="production_manager">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Level 4 -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light">
                                    <strong>Level 4 Approver</strong> <span class="text-muted">(Operations Manager / GM)</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="form-label">Select Approver</label>
                                            <select class="form-select approver-select" id="level_4_approver_id" 
                                                    name="level_4_approver_id" data-level="4">
                                                <option value="">None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Role Name</label>
                                            <input type="text" class="form-control" name="level_4_role_name" 
                                                   value="operations_manager" placeholder="operations_manager">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Level 5 -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light">
                                    <strong>Level 5 Approver</strong> <span class="text-muted">(COO / MD / Company Head)</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="form-label">Select Approver</label>
                                            <select class="form-select approver-select" id="level_5_approver_id" 
                                                    name="level_5_approver_id" data-level="5">
                                                <option value="">None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Role Name</label>
                                            <input type="text" class="form-control" name="level_5_role_name" 
                                                   value="coo" placeholder="coo">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Level 6 (Optional) -->
                            <div class="card border mb-3">
                                <div class="card-header bg-light">
                                    <strong>Level 6 Approver</strong> <span class="text-muted">(Group CEO / Chairman - Rare)</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="form-label">Select Approver</label>
                                            <select class="form-select approver-select" id="level_6_approver_id" 
                                                    name="level_6_approver_id" data-level="6">
                                                <option value="">None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Role Name</label>
                                            <input type="text" class="form-control" name="level_6_role_name" 
                                                   value="group_ceo" placeholder="group_ceo">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Approval Levels by Employee Type -->
                            <h5 class="mb-3">Approval Levels by Employee Type</h5>
                            <p class="text-muted small">
                                Configure how many approval levels are required for each employee type in this department.
                            </p>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Office Staff</label>
                                        <select class="form-select" name="staff_approval_levels">
                                            <option value="1">1 level</option>
                                            <option value="2" selected>2 levels</option>
                                            <option value="3">3 levels</option>
                                            <option value="4">4 levels</option>
                                            <option value="5">5 levels</option>
                                            <option value="6">6 levels</option>
                                        </select>
                                        <small class="text-muted">Direct → Team Lead</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Production Workers</label>
                                        <select class="form-select" name="worker_approval_levels">
                                            <option value="1">1 level</option>
                                            <option value="2">2 levels</option>
                                            <option value="3">3 levels</option>
                                            <option value="4">4 levels</option>
                                            <option value="5" selected>5 levels</option>
                                            <option value="6">6 levels</option>
                                        </select>
                                        <small class="text-muted">All 5 levels</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Supervisors</label>
                                        <select class="form-select" name="supervisor_approval_levels">
                                            <option value="1">1 level</option>
                                            <option value="2">2 levels</option>
                                            <option value="3" selected>3 levels</option>
                                            <option value="4">4 levels</option>
                                            <option value="5">5 levels</option>
                                            <option value="6">6 levels</option>
                                        </select>
                                        <small class="text-muted">Skip Team Lead</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Managers</label>
                                        <select class="form-select" name="manager_approval_levels">
                                            <option value="1">1 level</option>
                                            <option value="2">2 levels</option>
                                            <option value="3" selected>3 levels</option>
                                            <option value="4">4 levels</option>
                                            <option value="5">5 levels</option>
                                            <option value="6">6 levels</option>
                                        </select>
                                        <small class="text-muted">To GM level</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Executives</label>
                                        <select class="form-select" name="executive_approval_levels">
                                            <option value="1">1 level</option>
                                            <option value="2" selected>2 levels</option>
                                            <option value="3">3 levels</option>
                                            <option value="4">4 levels</option>
                                            <option value="5">5 levels</option>
                                            <option value="6">6 levels</option>
                                        </select>
                                        <small class="text-muted">Minimal levels</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Probation (Max)</label>
                                        <select class="form-select" name="probation_approval_levels">
                                            <option value="1">1 level</option>
                                            <option value="2" selected>2 levels</option>
                                            <option value="3">3 levels</option>
                                            <option value="4">4 levels</option>
                                            <option value="5">5 levels</option>
                                            <option value="6">6 levels</option>
                                        </select>
                                        <small class="text-muted">Limits all types</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Create Department
                                </button>
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Help Sidebar -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-lightbulb"></i> Setup Guide</h6>
                        
                        <h6 class="mt-3">Approval Levels</h6>
                        <ul class="small">
                            <li><strong>Level 1:</strong> Always employee's direct superior (gives ratings)</li>
                            <li><strong>Level 2:</strong> Team Lead / Dept Head (approve only)</li>
                            <li><strong>Level 3:</strong> Production/Senior Manager</li>
                            <li><strong>Level 4:</strong> Operations Manager / GM</li>
                            <li><strong>Level 5:</strong> COO / MD / Company Head</li>
                            <li><strong>Level 6:</strong> Group CEO (rare)</li>
                        </ul>
                        
                        <h6 class="mt-3">Employee Types</h6>
                        <ul class="small">
                            <li><strong>Office Staff:</strong> Usually 2 levels (Direct → Team Lead)</li>
                            <li><strong>Production Workers:</strong> Usually 5 levels (all)</li>
                            <li><strong>Supervisors:</strong> Usually 3 levels (skip Team Lead)</li>
                            <li><strong>Managers:</strong> Usually 3 levels (to GM)</li>
                            <li><strong>Executives:</strong> Usually 2 levels (minimal)</li>
                        </ul>
                        
                        <h6 class="mt-3">Best Practices</h6>
                        <ul class="small">
                            <li>Configure at least Level 2 approver</li>
                            <li>Set appropriate levels per employee type</li>
                            <li>Use descriptive role names</li>
                            <li>System auto-skips duplicate approvers</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// AJAX to load users by company
document.getElementById('company_id').addEventListener('change', function() {
    const companyId = this.value;
    
    if (!companyId) {
        // Clear all approver dropdowns
        document.querySelectorAll('.approver-select').forEach(select => {
            select.innerHTML = '<option value="">None</option>';
        });
        return;
    }
    
    // Fetch users for this company
    fetch(`<?php echo BASE_URL; ?>/admin/departments/get_users_by_company.php?company_id=${companyId}`)
        .then(response => response.json())
        .then(users => {
            // Populate all approver dropdowns
            document.querySelectorAll('.approver-select').forEach(select => {
                select.innerHTML = '<option value="">None</option>';
                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.name} (${user.emp_number}) - ${user.position}`;
                    select.appendChild(option);
                });
            });
        })
        .catch(error => console.error('Error loading users:', error));
    
    // Also load parent departments
    fetch(`<?php echo BASE_URL; ?>/admin/departments/get_departments_by_company.php?company_id=${companyId}`)
        .then(response => response.json())
        .then(departments => {
            const parentSelect = document.getElementById('parent_department_id');
            parentSelect.innerHTML = '<option value="">None (Top-level department)</option>';
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.department_name;
                parentSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading departments:', error));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>