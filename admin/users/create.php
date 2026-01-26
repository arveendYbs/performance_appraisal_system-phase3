
<?php
// admin/users/create.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';

/* if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
} */

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}
$error_message = '';




if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $emp_number = sanitize($_POST['emp_number'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $emp_email = sanitize($_POST['emp_email'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $direct_superior = !empty($_POST['direct_superior']) ? $_POST['direct_superior'] : null;
        $department = sanitize($_POST['department'] ?? '');
        $date_joined = $_POST['date_joined'] ?? '';
        $site = sanitize($_POST['site'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $company_id = $_POST['company_id'] ?? null;
        $is_hr = isset($_POST['is_hr']) ? 1 : 0;
        $is_confirmed = isset($_POST['is_confirmed']) ? 1 : 0;
        $hr_companies = $_POST['hr_companies'] ?? [];
        $is_top_management = isset($_POST['is_top_management']) ? 1 : 0;
        $top_mgmt_companies = $_POST['top_mgmt_companies'] ?? [];

        // Validation
        if (empty($name) || empty($emp_number) || empty($email) || empty($company_id) || empty($position) || 
            empty($department) || empty($date_joined) || empty($site) || empty($role) || 
            empty($password)) {
            $error_message = 'All fields are required except company email and direct superior.';
        } elseif (!validateEmail($email)) {
            $error_message = 'Invalid email format.';
        
        } elseif (!empty($emp_email) && !validateEmail($emp_email)) {
            $error_message = 'Invalid company email format.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $user = new User($db);

                $user->name = $name;
                $user->emp_number = $emp_number;
                $user->email = $email;
                $user->emp_email = $emp_email;
                $user->position = $position;
                $user->direct_superior = $direct_superior;
                $user->department = $department;
                $user->date_joined = $date_joined;
                $user->site = $site;
                $user->role = $role;
                $user->company_id = $company_id;  // ADD THIS
                $user->is_hr = $is_hr;            // ADD THIS
                $user->is_confirmed = $is_confirmed; // ADD THIS
                $user->password = $password;
                $user->is_top_management = $is_top_management; // ADD THIS

                if ($user->create()) {
                     $user_id = $db->lastInsertId();
        
                    // If HR user, assign to companies
                    if ($is_hr && !empty($hr_companies)) {
                        $user->id = $user_id;
                        foreach ($hr_companies as $comp_id) {
                            $user->assignToCompany($comp_id);
                        }
                    }

                    if ($is_top_management && !empty($top_mgmt_companies)) {
                        $user->id = $user_id;
                        foreach ($top_mgmt_companies as $comp_id) {
                            $user->assignTopManagementToCompany($comp_id);
                        }
                    }
                    logActivity($_SESSION['user_id'], 'CREATE', 'users', $user->id, null,
                               ['name' => $name, 'emp_number' => $emp_number, 'role' => $role],
                               'Created new user: ' . $name);
                    
                    redirect('index.php', 'User created successfully!', 'success');
                } else {
                    $error_message = 'Failed to create user. Email or employee number may already exist.';
                }
            } catch (Exception $e) {
                error_log("User create error: " . $e->getMessage());
                $error_message = 'An error occurred. Please try again.';
            }
        }
    }
}


/* // Get potential supervisors
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("SELECT id, name, position FROM users WHERE role IN ('admin', 'manager') AND is_active = 1 ORDER BY name");
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $supervisors = [];
}
 */
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-person-plus me-2"></i>Add New User
            </h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Users
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">User Information</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="emp_number" class="form-label">Employee Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="emp_number" name="emp_number" required
                                       value="<?php echo htmlspecialchars($_POST['emp_number'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Personal Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <div class="form-text">Used for login</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="emp_email" class="form-label">Company Email</label>
                                <input type="email" class="form-control" id="emp_email" name="emp_email"
                                       value="<?php echo htmlspecialchars($_POST['emp_email'] ?? ''); ?>">
                                <div class="form-text">Optional - can also be used for login</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="position" name="position" required
                                       value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- new feature -- select all and search users for direct superior -->
                       <!--  <div class="col-md-6">
                            <div class="mb-3">
                                <label for="direct_superior" class="form-label">Direct Superior</label>
                                <select class="form-select" id="direct_superior" name="direct_superior">
                                    <option value="">Select supervisor...</option>
                                    <?php foreach ($supervisors as $supervisor): ?>
                                    <option value="<?php echo $supervisor['id']; ?>" 
                                            <?php echo (($_POST['direct_superior'] ?? '') == $supervisor['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supervisor['name'] . ' - ' . $supervisor['position']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div> -->

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="direct_superior" class="form-label">Direct Superior</label>
                                    <select class="form-select select-supervisor" id="direct_superior" name="direct_superior">
                                        <option value="">Select supervisor...</option>
                                            <?php 
                                            $database = new Database();
                                            $db = $database->getConnection();
                                            $user_model = new User($db);
                                            $supervisors = $user_model->getAllPotentialSupervisors();

                                            while ($supervisor = $supervisors->fetch(PDO::FETCH_ASSOC)): ?>
                                            <option value="<?php echo $supervisor['id']; ?>"
                                                    data-position="<?php echo htmlspecialchars($supervisor['position']); ?>"
                                                    data-department="<?php echo htmlspecialchars($supervisor['department']); ?>"
                                                    data-company="<?php echo htmlspecialchars($supervisor['company_name']); ?>"
                                                    <?php echo (($_POST['direct_superior'] ?? '') == $supervisor['id']) ? 'selected' : ''; ?>>
                                                <?php
                                                echo htmlspecialchars($supervisor['name']) . ' - ' . 
                                                    htmlspecialchars($supervisor['position']) . ' (' . 
                                                    htmlspecialchars($supervisor['department']) . 
                                                    ($supervisor['company_name'] ? ', ' . htmlspecialchars($supervisor['company_name']) : '') . ')';
                                                ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                <div class="form-text">
                                    <i class="bi bi-search me-1"></i>Type to search by name, position, or department
                                </div>
                            </div>
                        </div>

                                        
                        
                    </div>
                    

                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="department" name="department" required
                                       value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site" class="form-label">Site <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="site" name="site" required
                                       value="<?php echo htmlspecialchars($_POST['site'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Company Selection -->
                    <div class="mb-3">
                        <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <option value="">Select Company</option>
                            <?php
                            require_once __DIR__ . '/../../classes/Company.php';
                            $company_model = new Company($db);
                            $companies_stmt = $company_model->getAll();
                            while ($company = $companies_stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$company['id']}'>{$company['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- HR Status -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_hr" name="is_hr" value="1">
                            <label class="form-check-label" for="is_hr">
                                <strong>HR Personnel</strong> - Can view appraisals from assigned companies
                            </label>
                        </div>
                        <small class="text-muted">Note: HR is not a role. HR users can still be employees, managers, or admins.</small>
                    </div>

                    <!-- HR Companies (only shown if is_hr is checked) -->
                    <div class="mb-3" id="hr_companies_section" style="display: none;">
                        <label class="form-label">HR Responsible for Companies</label>
                        <?php
                        $companies_stmt2 = $company_model->getAll();
                        while ($company = $companies_stmt2->fetch(PDO::FETCH_ASSOC)) {
                            echo "
                            <div class='form-check'>
                                <input class='form-check-input hr-company-checkbox' type='checkbox' name='hr_companies[]' 
                                    value='{$company['id']}' id='hr_company_{$company['id']}'>
                                <label class='form-check-label' for='hr_company_{$company['id']}'>
                                    {$company['name']}
                                </label>
                            </div>";
                        }


                        ?>
                    </div>

                    <!-- top management status -->

                    <div class ="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_top_management" name="is_top_management" value="1">
                            <label class="form-check-label" for="is_top_management">
                                <strong>Top Management</strong> - Has access to all appraisal data across the organization.
                            </label>
                        </div>
                        <small class="text-muted">
                            Top Management users have elevated privileges and can oversee company-wide performance appraisals.
                        </small>
                    </div>

                    <!-- top managemnet companies -->
                    <div class="mb-3" id="top_management_companies_section" style="display: none;">
                        <label class="form-label">Top Management Responsible for Companies</label>
                        <?php
                        $companies_stmt3 = $company_model->getAll();
                        while ($company = $companies_stmt3->fetch(PDO::FETCH_ASSOC)) {
                            echo "
                            <div class='form-check'>
                                <input class='form-check-input top-management-company-checkbox' type='checkbox' name='top_management_companies[]' 
                                    value='{$company['id']}' id='top_management_company_{$company['id']}'>  
                                <label class='form-check-label' for='top_management_company_{$company['id']}'>
                                    {$company['name']}
                                </label>
                            </div>";
                        }
                        ?>
                    </div>

                    <!-- Employment Confirmation Status -->
                     <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_confirmed" name="is_confirmed" value="1">
                            <label class="form-check-label" for="is_confirmed">
                                <strong>Employment Confirmed</strong> - Check if the employee's employment status is confirmed.
                            </label>
                        </div>
                        <small class="text-muted">
                            Unconfirmed employees may have limited access until their status is updated.
                        </small>
                    </div>

                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_joined" class="form-label">Date Joined <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_joined" name="date_joined" required
                                       value="<?php echo htmlspecialchars($_POST['date_joined'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select role...</option>
                                    <option value="admin" <?php echo (($_POST['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                    <option value="manager" <?php echo (($_POST['role'] ?? '') == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                    <option value="employee" <?php echo (($_POST['role'] ?? '') == 'employee') ? 'selected' : ''; ?>>Employee</option>
                                    <option value="worker" <?php echo (($_POST['role'] ?? '') == 'worker') ? 'selected' : ''; ?>>Worker</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Select2 for supervisor dropdown
function matchCustom(params, data) {
    if ($.trim(params.term) === '') return data;
    if (typeof data.text === 'undefined' || data.text === 'Select supervisor...') return null;

    const searchTerm = params.term.toLowerCase();
    const text = data.text.toLowerCase();
    const position = $(data.element).data('position');
    const department = $(data.element).data('department');
    const company = $(data.element).data('company');

    if (
        text.indexOf(searchTerm) > -1 ||
        (position && position.toLowerCase().indexOf(searchTerm) > -1) ||
        (department && department.toLowerCase().indexOf(searchTerm) > -1) ||
        (company && company.toLowerCase().indexOf(searchTerm) > -1)
    ) {
        return data;
    }
    return null;
}

$(document).ready(function() {
    console.log("jQuery version:", $.fn.jquery);
    console.log("Select2 available:", typeof $.fn.select2);
    $('.select-supervisor').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Select or search for supervisor...',
        allowClear: true,
        matcher: matchCustom
    });
});

// Sidebar toggle for mobile
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show');
});

// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
});

// Show/hide HR companies section
document.getElementById('is_hr').addEventListener('change', function() {
    document.getElementById('hr_companies_section').style.display = this.checked ? 'block' : 'none';
    
    // If unchecked, uncheck all HR companies
    if (!this.checked) {
        document.querySelectorAll('.hr-company-checkbox').forEach(cb => cb.checked = false);
    }
});

/*
add js to toggle visibility of top management companies section

 */
document.getElementById('is_top_management').addEventListener('change', function() {
    document.getElementById('top_management_companies_section').style.display = this.checked ? 'block' : 'none';
    
});

</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
