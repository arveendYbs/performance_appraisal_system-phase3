<?php
// admin/users/edit.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/User.php';

// Check authentication and authorization
if (!isLoggedIn()) {
    redirect('/auth/login.php', 'Please login first.', 'warning');
}



/* if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
} */

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}
// Get user ID from URL
$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    redirect('index.php', 'User ID is required.', 'error');
}

$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $user->id = $user_id;
    
    // Get user details
    if (!$user->readOne()) {
        redirect('index.php', 'User not found.', 'error');
    }
    
    // After loading user data: load top-management company assignments directly from DB
       $top_mgmt_companies_assigned = [];
        if ($user->isTopManagement()) {
            $assigned = $user->getTopManagementCompanies();
            foreach ($assigned as $comp) {
                $top_mgmt_companies_assigned[] = $comp['id'];
            }
        }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid request. Please try again.';
        } else {
            // Get and sanitize form data
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
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $company_id = $_POST['company_id'] ?? null;
            $is_hr = isset($_POST['is_hr']) ? 1 : 0;
            $hr_companies = $_POST['hr_companies'] ?? [];
            $is_confirmed = isset($_POST['is_confirmed']) ? 1 : 0;
            $is_top_management = isset($_POST['is_top_management']) ? 1 : 0;
            $top_mgmt_companies = $_POST['top_mgmt_companies'] ?? [];

            // Validation
            if (empty($name) || empty($emp_number) || empty($email) || empty($position) || 
                empty($department) || empty($date_joined) || empty($site) || empty($role)) {
                $error_message = 'All fields are required except company email and direct superior.';
            } elseif (!validateEmail($email)) {
                $error_message = 'Invalid email format.';
            } elseif (!empty($emp_email) && !validateEmail($emp_email)) {
                $error_message = 'Invalid company email format.';
            } elseif ($user->emailExists($email, $user_id)) {
                $error_message = 'Email already exists.';
            } elseif ($user->empNumberExists($emp_number, $user_id)) {
                $error_message = 'Employee number already exists.';
            } elseif (!empty($new_password) && $new_password !== $confirm_password) {
                $error_message = 'Passwords do not match.';
            } elseif (!empty($new_password) && strlen($new_password) < 6) {
                $error_message = 'Password must be at least 6 characters long.';
            } else {
                // Update user details
                $old_values = [
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_active' => $user->is_active
                ];

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
                $user->is_active = $is_active;
                $user->company_id = $company_id;  // ADD THIS
                $user->is_hr = $is_hr;            // ADD THIS
                $user->is_confirmed = $is_confirmed; // ADD THIS
                $user->is_top_management = $is_top_management; // ADD THIS
error_log("Saving is_top_management for user {$user_id}: " . $user->is_top_management);

                if ($user->update()) {
                    // Update password if provided
                    if (!empty($new_password)) {
                        $user->updatePassword($new_password);
                    }
                    
                    $new_values = [
                        'name' => $name,
                        'role' => $role,
                        'is_active' => $is_active
                    ];
                    // Sync HR companies
                    if ($is_hr) {
                        $user->syncHRCompanies($hr_companies);
                    } else {
                        // Remove all HR assignments if no longer HR
                        $user->syncHRCompanies([]);
                    }
                    

                    // Handle Top Management Company Assignments
                    if ($is_top_management) {
                        // Remove old assignments
                        $db->prepare("DELETE FROM top_management_companies WHERE user_id = ?")->execute([$user_id]);
                        
                        // Add new assignments
                        if (!empty($top_mgmt_companies)) {
                            $user->id = $user_id;
                            foreach ($top_mgmt_companies as $comp_id) {
                                $user->assignTopManagementToCompany($comp_id);
                            }
                        }
                    }
                    logActivity(
                        $_SESSION['user_id'],
                        'UPDATE',
                        'users',
                        $user_id,
                        $old_values,
                        $new_values,
                        'Updated user: ' . $name
                    );
                    
                    redirect('index.php', 'User updated successfully!', 'success');
                } else {
                    $error_message = 'Failed to update user. Please try again.';
                }
            }
        }
    }
    
    // Get potential supervisors
    $stmt = $db->query("SELECT id, name, position FROM users 
                        WHERE role IN ('admin', 'manager') 
                        AND is_active = 1 
                        AND id != $user_id 
                        ORDER BY name");
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("User edit error: " . $e->getMessage());
    $error_message = 'An error occurred. Please try again.';
}

// Include header
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-pencil me-2"></i>Edit User
                </h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Users
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           value="<?php echo htmlspecialchars($user->name); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emp_number" class="form-label">Employee Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="emp_number" name="emp_number" required
                                           value="<?php echo htmlspecialchars($user->emp_number); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Email Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Personal Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo htmlspecialchars($user->email); ?>">
                                    <div class="form-text">Used for login</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emp_email" class="form-label">Company Email</label>
                                    <input type="email" class="form-control" id="emp_email" name="emp_email"
                                           value="<?php echo htmlspecialchars($user->emp_email ?? ''); ?>">
                                    <div class="form-text">Optional - can also be used for login</div>
                                </div>
                            </div>
                        </div>

                        <!-- Position and Superior -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="position" name="position" required
                                           value="<?php echo htmlspecialchars($user->position); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="direct_superior" class="form-label">Direct Superior</label>
                                    <select class="form-select select-supervisor" id="direct_superior" name="direct_superior">
                                        <option value="">Select supervisor...</option>
                                        <?php 
                                        $supervisors = $user->getAllPotentialSupervisors($direct_superior);
                                        
                                        while ($supervisor = $supervisors->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $supervisor['id']; ?>" 
                                                data-position="<?php echo htmlspecialchars($supervisor['position']); ?>"
                                                data-department="<?php echo htmlspecialchars($supervisor['department']); ?>"
                                                data-company="<?php echo htmlspecialchars($supervisor['company_name'] ?? ''); ?>"
                                                <?php echo ($user->direct_superior == $supervisor['id']) ? 'selected' : ''; ?>>
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


                        <!-- Department and Site -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="department" name="department" required
                                           value="<?php echo htmlspecialchars($user->department, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="site" class="form-label">Site <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="site" name="site" required
                                           value="<?php echo htmlspecialchars($user->site); ?>">
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
                                    $selected = ($company['id'] == $user->company_id) ? 'selected' : '';
                                    echo "<option value='{$company['id']}' {$selected}>{$company['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- HR Status -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_hr" name="is_hr" value="1" 
                                    <?php echo $user->is_hr ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_hr">
                                    <strong>HR Personnel</strong> - Can view appraisals from assigned companies
                                </label>
                            </div>
                        </div>

                        <!-- HR Companies -->
                        <?php
                        $user_hr_companies = $user->getHRCompanies();
                        $user_hr_company_ids = array_column($user_hr_companies, 'id');
                        ?>
                            <div class="mb-3" id="hr_companies_section" style="display: <?php echo $user->is_hr ? 'block' : 'none'; ?>;">
                                <label class="form-label">HR Responsible for Companies</label>
                                <?php
                                $companies_stmt2 = $company_model->getAll();
                                while ($company = $companies_stmt2->fetch(PDO::FETCH_ASSOC)) {
                                    $checked = in_array($company['id'], $user_hr_company_ids) ? 'checked' : '';
                                    echo "
                                    <div class='form-check'>
                                        <input class='form-check-input hr-company-checkbox' type='checkbox' name='hr_companies[]' 
                                            value='{$company['id']}' id='hr_company_{$company['id']}' {$checked}>
                                        <label class='form-check-label' for='hr_company_{$company['id']}'>
                                            {$company['name']}
                                        </label>
                                    </div>";
                                }
                                ?>
                            </div>



                                <!-- Top Management Section -->
                                <div class="mb-3">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_top_management" name="is_top_management" value="1" 
                                        <?php echo $user->is_top_management ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_top_management">
                                        <strong>Top Management</strong>
                                    </label>
                                </div>

                                <div class="mb-3" id="top_mgmt_companies_section" style="display: <?php echo $user->is_top_management ? 'block' : 'none'; ?>;">
                                    <small class="form-label">Top Management Responsible for Companies</small>
                                    <?php
                                    $companies_stmt3 = $db->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");
                                    while ($company = $companies_stmt3->fetch(PDO::FETCH_ASSOC)) {
                                        $checked = in_array($company['id'], $top_mgmt_companies_assigned) ? 'checked' : '';
                                        echo "
                                        <div class='form-check'>
                                            <input class='form-check-input top-mgmt-company-checkbox' type='checkbox' name='top_mgmt_companies[]' 
                                                value='{$company['id']}' id='top_mgmt_company_{$company['id']}' {$checked}>
                                            <label class='form-check-label' for='top_mgmt_company_{$company['id']}'>
                                                {$company['name']}
                                            </label>
                                        </div>";
                                    }
                                    ?>
                                </div>



                                            <!--                                 <?php var_dump($user->is_confirmed); ?>
                                -->

    
                        <!-- Employment Confirmation Status -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_confirmed" name="is_confirmed" value="1" 
                                    <?php echo $user->is_confirmed ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_confirmed">
                                    <strong>Confirmed Employee</strong> - Employee has passed probation period
                                </label>
                            </div>
                            <small class="text-muted">
                                Unconfirmed employees will require probation assessment during appraisal reviews.
                            </small>
                        </div>





                        <!-- Date Joined and Role -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_joined" class="form-label">Date Joined <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_joined" name="date_joined" required
                                           value="<?php echo htmlspecialchars($user->date_joined); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select role...</option>
                                        <option value="admin" <?php echo ($user->role == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="manager" <?php echo ($user->role == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                        <option value="employee" <?php echo ($user->role == 'employee') ? 'selected' : ''; ?>>Employee</option>
                                        <option value="worker" <?php echo ($user->role == 'worker') ? 'selected' : ''; ?>>Worker</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?php echo $user->is_active ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Active (user can login and access the system)
                                </label>
                            </div>
                        </div>

                        <!-- Password Change Section -->
                        <hr>
                        <h6>Change Password</h6>
                        <p class="text-muted small">Leave blank to keep current password</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6">
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" minlength="6">
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>



document.getElementById('is_top_management').addEventListener('change', function() {
    document.getElementById('top_mgmt_companies_section').style.display = this.checked ? 'block' : 'none';
    if (!this.checked) {
        document.querySelectorAll('.top-mgmt-company-checkbox').forEach(cb => cb.checked = false);
    }
});

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

document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);

    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
document.getElementById('is_hr').addEventListener('change', function() {
    document.getElementById('hr_companies_section').style.display = this.checked ? 'block' : 'none';
    if (!this.checked) {
        document.querySelectorAll('.hr-company-checkbox').forEach(cb => cb.checked = false);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>