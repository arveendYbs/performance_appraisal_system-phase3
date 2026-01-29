<?php
// admin/positions/edit.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Position.php';
require_once __DIR__ . '/../../classes/Company.php';

$position_id = $_GET['id'] ?? 0;
if (!$position_id) {
    redirect('index.php', 'Position ID is required.', 'error');
}

$position = new Position($db);
$position->id = $position_id;

if (!$position->readOne()) {
    redirect('index.php', 'Position not found.', 'error');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $position->position_title = sanitize($_POST['position_title'] ?? '');
        $position->position_code = sanitize($_POST['position_code'] ?? '');
        $position->employee_type = $_POST['employee_type'] ?? 'office_staff';
        $position->is_management_position = isset($_POST['is_management_position']) ? 1 : 0;
        $position->department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $position->job_description = sanitize($_POST['job_description'] ?? '');
        $position->min_salary = !empty($_POST['min_salary']) ? floatval($_POST['min_salary']) : null;
        $position->max_salary = !empty($_POST['max_salary']) ? floatval($_POST['max_salary']) : null;
        $position->requires_probation = isset($_POST['requires_probation']) ? 1 : 0;
        $position->default_probation_months = intval($_POST['default_probation_months'] ?? 3);
        $position->is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($position->position_title)) {
            $error_message = 'Position title is required.';
        } elseif (empty($position->position_code)) {
            $error_message = 'Position code is required.';
        } elseif ($position->min_salary && $position->max_salary && $position->min_salary > $position->max_salary) {
            $error_message = 'Minimum salary cannot be greater than maximum salary.';
        } else {
            if ($position->update()) {
                logActivity($_SESSION['user_id'], 'UPDATE', 'positions', $position->id, 
                           null, ['title' => $position->position_title], 
                           'Updated position: ' . $position->position_title);
                
                $success_message = 'Position updated successfully!';
            } else {
                $error_message = 'Failed to update position.';
            }
        }
    }
}

// Get employee count
$employee_count = $position->getEmployeeCount();

// Get companies
$company_model = new Company($db);
$companies_stmt = $company_model->getAll();
$companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for this company
$depts_query = "SELECT id, department_name FROM departments 
                WHERE company_id = ? AND is_active = 1 
                ORDER BY department_name";
$depts_stmt = $db->prepare($depts_query);
$depts_stmt->execute([$position->company_id]);
$departments = $depts_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Edit Position</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Positions</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($position->position_title); ?></li>
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
                        <?php if ($employee_count > 0): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                This position has <strong><?php echo $employee_count; ?> active employees</strong>. 
                                Changes may affect their approval chains in future appraisals.
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="positionForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <!-- Basic Information -->
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Company</label>
                                <input type="text" class="form-control" value="<?php 
                                    $comp = array_filter($companies, fn($c) => $c['id'] == $position->company_id);
                                    echo htmlspecialchars(reset($comp)['name'] ?? 'Unknown');
                                ?>" disabled>
                                <small class="text-muted">Company cannot be changed after creation</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="position_title" class="form-label">Position Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="position_title" 
                                               name="position_title" required 
                                               value="<?php echo htmlspecialchars($position->position_title); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="position_code" class="form-label">Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="position_code" 
                                               name="position_code" required 
                                               value="<?php echo htmlspecialchars($position->position_code); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="employee_type" class="form-label">Employee Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="employee_type" name="employee_type" required>
                                            <option value="office_staff" <?php echo $position->employee_type == 'office_staff' ? 'selected' : ''; ?>>Office Staff</option>
                                            <option value="production_worker" <?php echo $position->employee_type == 'production_worker' ? 'selected' : ''; ?>>Production Worker</option>
                                            <option value="supervisor" <?php echo $position->employee_type == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                            <option value="manager" <?php echo $position->employee_type == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                            <option value="executive" <?php echo $position->employee_type == 'executive' ? 'selected' : ''; ?>>Executive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department_id" class="form-label">Department (Optional)</label>
                                        <select class="form-select" id="department_id" name="department_id">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo $position->department_id == $dept['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_management_position" 
                                           name="is_management_position" <?php echo $position->is_management_position ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_management_position">
                                        This is a management position
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="job_description" class="form-label">Job Description</label>
                                <textarea class="form-control" id="job_description" name="job_description" 
                                          rows="4"><?php echo htmlspecialchars($position->job_description ?? ''); ?></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Salary Range -->
                            <h5 class="mb-3">Salary Range</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="min_salary" class="form-label">Minimum Salary</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RM</span>
                                            <input type="number" class="form-control" id="min_salary" 
                                                   name="min_salary" step="0.01" 
                                                   value="<?php echo $position->min_salary ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_salary" class="form-label">Maximum Salary</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RM</span>
                                            <input type="number" class="form-control" id="max_salary" 
                                                   name="max_salary" step="0.01" 
                                                   value="<?php echo $position->max_salary ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Probation Settings -->
                            <h5 class="mb-3">Probation Settings</h5>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="requires_probation" 
                                           name="requires_probation" <?php echo $position->requires_probation ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="requires_probation">
                                        This position requires probation period
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="probation_months_wrapper">
                                <label for="default_probation_months" class="form-label">Default Probation Period</label>
                                <select class="form-select" id="default_probation_months" name="default_probation_months">
                                    <option value="1" <?php echo $position->default_probation_months == 1 ? 'selected' : ''; ?>>1 month</option>
                                    <option value="2" <?php echo $position->default_probation_months == 2 ? 'selected' : ''; ?>>2 months</option>
                                    <option value="3" <?php echo $position->default_probation_months == 3 ? 'selected' : ''; ?>>3 months</option>
                                    <option value="6" <?php echo $position->default_probation_months == 6 ? 'selected' : ''; ?>>6 months</option>
                                    <option value="12" <?php echo $position->default_probation_months == 12 ? 'selected' : ''; ?>>12 months</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" 
                                           name="is_active" <?php echo $position->is_active ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Active Position
                                    </label>
                                </div>
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
                        <h6 class="card-title"><i class="bi bi-info-circle"></i> Position Info</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Employees:</th>
                                <td><?php echo $employee_count; ?></td>
                            </tr>
                            <tr>
                                <th>Created:</th>
                                <td><?php echo date('M d, Y', strtotime($position->created_at)); ?></td>
                            </tr>
                            <tr>
                                <th>Updated:</th>
                                <td><?php echo date('M d, Y', strtotime($position->updated_at)); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <?php if ($position->is_active): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($employee_count > 0): ?>
                    <div class="card border-0 shadow-sm bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-exclamation-triangle"></i> Important Note</h6>
                            <p class="small mb-0">
                                Changing the employee type will affect approval chain generation for employees 
                                in this position. Existing appraisals will keep their current chains, but new 
                                appraisals will use the updated employee type.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle probation months based on checkbox
document.getElementById('requires_probation').addEventListener('change', function() {
    document.getElementById('probation_months_wrapper').style.display = this.checked ? 'block' : 'none';
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const requiresProbation = document.getElementById('requires_probation').checked;
    document.getElementById('probation_months_wrapper').style.display = requiresProbation ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>