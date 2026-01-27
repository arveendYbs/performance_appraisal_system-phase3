<?php
// admin/positions/create.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Position.php';
require_once __DIR__ . '/../../classes/Company.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $position = new Position($db);
        
        $position->company_id = $_POST['company_id'] ?? null;
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
        
        if (empty($position->company_id)) {
            $error_message = 'Company is required.';
        } elseif (empty($position->position_title)) {
            $error_message = 'Position title is required.';
        } elseif (empty($position->position_code)) {
            $error_message = 'Position code is required.';
        } elseif ($position->min_salary && $position->max_salary && $position->min_salary > $position->max_salary) {
            $error_message = 'Minimum salary cannot be greater than maximum salary.';
        } else {
            if ($position->create()) {
                logActivity($_SESSION['user_id'], 'CREATE', 'positions', $position->id, null,
                           ['title' => $position->position_title], 
                           'Created position: ' . $position->position_title);
                
                redirect('index.php', 'Position created successfully!', 'success');
            } else {
                $error_message = 'Failed to create position. Code may already exist.';
            }
        }
    }
}

// Get companies
$company_model = new Company($db);
$companies_stmt = $company_model->getAll();
$companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Create Position</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Positions</a></li>
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
                        <form method="POST" id="positionForm">
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
                                        <label for="position_title" class="form-label">Position Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="position_title" 
                                               name="position_title" required 
                                               placeholder="e.g., Sales Executive, Production Operator">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="position_code" class="form-label">Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="position_code" 
                                               name="position_code" required 
                                               placeholder="e.g., SE, PO">
                                        <small class="text-muted">Short unique code</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="employee_type" class="form-label">Employee Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="employee_type" name="employee_type" required>
                                            <option value="office_staff">Office Staff (2 approval levels)</option>
                                            <option value="production_worker">Production Worker (5 approval levels)</option>
                                            <option value="supervisor">Supervisor (3 approval levels)</option>
                                            <option value="manager">Manager (3 approval levels)</option>
                                            <option value="executive">Executive (2 approval levels)</option>
                                        </select>
                                        <small class="text-muted">Determines default approval chain</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department_id" class="form-label">Department (Optional)</label>
                                        <select class="form-select" id="department_id" name="department_id">
                                            <option value="">All Departments</option>
                                            <!-- Will be populated via AJAX based on company -->
                                        </select>
                                        <small class="text-muted">Leave blank if position applies to all departments</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_management_position" 
                                           name="is_management_position">
                                    <label class="form-check-label" for="is_management_position">
                                        This is a management position
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="job_description" class="form-label">Job Description</label>
                                <textarea class="form-control" id="job_description" name="job_description" 
                                          rows="4" placeholder="Brief description of role and responsibilities"></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Salary Range -->
                            <h5 class="mb-3">Salary Range (Optional)</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="min_salary" class="form-label">Minimum Salary</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RM</span>
                                            <input type="number" class="form-control" id="min_salary" 
                                                   name="min_salary" step="0.01" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_salary" class="form-label">Maximum Salary</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RM</span>
                                            <input type="number" class="form-control" id="max_salary" 
                                                   name="max_salary" step="0.01" placeholder="0.00">
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
                                           name="requires_probation" checked>
                                    <label class="form-check-label" for="requires_probation">
                                        This position requires probation period
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="probation_months_wrapper">
                                <label for="default_probation_months" class="form-label">Default Probation Period</label>
                                <select class="form-select" id="default_probation_months" name="default_probation_months">
                                    <option value="1">1 month</option>
                                    <option value="2">2 months</option>
                                    <option value="3" selected>3 months</option>
                                    <option value="6">6 months</option>
                                    <option value="12">12 months</option>
                                </select>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Create Position
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
                        <h6 class="card-title"><i class="bi bi-lightbulb"></i> Position Guidelines</h6>
                        
                        <h6 class="mt-3">Employee Types</h6>
                        <ul class="small">
                            <li><strong>Office Staff:</strong> Clerks, executives, coordinators (2 approval levels)</li>
                            <li><strong>Production Worker:</strong> Operators, technicians (5 approval levels)</li>
                            <li><strong>Supervisor:</strong> Team leads, supervisors (3 approval levels)</li>
                            <li><strong>Manager:</strong> Department heads, managers (3 approval levels)</li>
                            <li><strong>Executive:</strong> Directors, VPs, C-level (2 approval levels)</li>
                        </ul>
                        
                        <h6 class="mt-3">Code Best Practices</h6>
                        <ul class="small">
                            <li>Keep codes short (2-6 characters)</li>
                            <li>Use uppercase (e.g., SE, PM, OPS)</li>
                            <li>Make them memorable and unique</li>
                            <li>Avoid special characters</li>
                        </ul>
                        
                        <h6 class="mt-3">Department Assignment</h6>
                        <p class="small mb-0">
                            Leave department blank for positions that apply across multiple departments 
                            (e.g., "Manager" can be used in Sales, IT, HR). Set a specific department 
                            for specialized roles (e.g., "Production Supervisor" only in Production dept).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle probation months based on checkbox
document.getElementById('requires_probation').addEventListener('change', function() {
    document.getElementById('probation_months_wrapper').style.display = this.checked ? 'block' : 'none';
});

// Load departments when company changes
document.getElementById('company_id').addEventListener('change', function() {
    const companyId = this.value;
    const deptSelect = document.getElementById('department_id');
    
    if (!companyId) {
        deptSelect.innerHTML = '<option value="">All Departments</option>';
        return;
    }
    
    fetch(`<?php echo BASE_URL; ?>/admin/departments/get_departments_by_company.php?company_id=${companyId}`)
        .then(response => response.json())
        .then(departments => {
            deptSelect.innerHTML = '<option value="">All Departments</option>';
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.department_name;
                deptSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading departments:', error));
});

// Auto-generate position code from title
document.getElementById('position_title').addEventListener('blur', function() {
    const codeInput = document.getElementById('position_code');
    if (!codeInput.value) {
        // Generate code from title (first letters of words, max 6 chars)
        const words = this.value.trim().split(' ');
        let code = words.map(w => w.charAt(0)).join('').toUpperCase();
        code = code.substring(0, 6);
        codeInput.value = code;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>