<?php
// admin/sites/create.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Company.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $company_id = $_POST['company_id'] ?? null;
        $site_name = sanitize($_POST['site_name'] ?? '');
        $site_code = sanitize($_POST['site_code'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $state = sanitize($_POST['state'] ?? '');
        $country = sanitize($_POST['country'] ?? 'Malaysia');
        $postal_code = sanitize($_POST['postal_code'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $site_manager_id = !empty($_POST['site_manager_id']) ? $_POST['site_manager_id'] : null;
        
        if (empty($company_id)) {
            $error_message = 'Company is required.';
        } elseif (empty($site_name)) {
            $error_message = 'Site name is required.';
        } elseif (empty($site_code)) {
            $error_message = 'Site code is required.';
        } else {
            try {
                $query = "INSERT INTO sites 
                         (company_id, site_name, site_code, address, city, state, country, 
                          postal_code, phone, site_manager_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($query);
                $success = $stmt->execute([
                    $company_id, $site_name, $site_code, $address, $city, $state, 
                    $country, $postal_code, $phone, $site_manager_id
                ]);
                
                if ($success) {
                    $site_id = $db->lastInsertId();
                    
                    logActivity($_SESSION['user_id'], 'CREATE', 'sites', $site_id, null,
                               ['name' => $site_name], 
                               'Created site: ' . $site_name);
                    
                    redirect('index.php', 'Site created successfully!', 'success');
                } else {
                    $error_message = 'Failed to create site. Code may already exist.';
                }
            } catch (Exception $e) {
                error_log("Site create error: " . $e->getMessage());
                $error_message = 'An error occurred. Please try again.';
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
                <h1 class="h3 mb-0">Create Site</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Sites</a></li>
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
                        <form method="POST" id="siteForm">
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
                                        <label for="site_name" class="form-label">Site Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="site_name" 
                                               name="site_name" required 
                                               placeholder="e.g., Penang Office, KL Branch">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="site_code" class="form-label">Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="site_code" 
                                               name="site_code" required 
                                               placeholder="e.g., PNG, KL">
                                        <small class="text-muted">Short unique code</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="site_manager_id" class="form-label">Site Manager (Optional)</label>
                                <select class="form-select" id="site_manager_id" name="site_manager_id">
                                    <option value="">Not assigned</option>
                                    <!-- Will be populated via AJAX based on company -->
                                </select>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Location Information -->
                            <h5 class="mb-3">Location Information</h5>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" 
                                          rows="2" placeholder="Street address"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" 
                                               name="city" placeholder="e.g., Penang">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="state" class="form-label">State</label>
                                        <select class="form-select" id="state" name="state">
                                            <option value="">Select State</option>
                                            <option value="Johor">Johor</option>
                                            <option value="Kedah">Kedah</option>
                                            <option value="Kelantan">Kelantan</option>
                                            <option value="Kuala Lumpur">Kuala Lumpur</option>
                                            <option value="Labuan">Labuan</option>
                                            <option value="Melaka">Melaka</option>
                                            <option value="Negeri Sembilan">Negeri Sembilan</option>
                                            <option value="Pahang">Pahang</option>
                                            <option value="Penang">Penang</option>
                                            <option value="Perak">Perak</option>
                                            <option value="Perlis">Perlis</option>
                                            <option value="Putrajaya">Putrajaya</option>
                                            <option value="Sabah">Sabah</option>
                                            <option value="Sarawak">Sarawak</option>
                                            <option value="Selangor">Selangor</option>
                                            <option value="Terengganu">Terengganu</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="postal_code" class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" id="postal_code" 
                                               name="postal_code" placeholder="e.g., 14000">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="country" 
                                               name="country" value="Malaysia">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="text" class="form-control" id="phone" 
                                               name="phone" placeholder="e.g., +60 4-123 4567">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Create Site
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
                        <h6 class="card-title"><i class="bi bi-lightbulb"></i> Site Guidelines</h6>
                        
                        <h6 class="mt-3">What is a Site?</h6>
                        <p class="small">
                            A site represents a physical office location or branch. 
                            Employees can be assigned to a primary site where they work.
                        </p>
                        
                        <h6 class="mt-3">Code Best Practices</h6>
                        <ul class="small">
                            <li>Use city abbreviations (PNG, KL, JB)</li>
                            <li>Keep codes short (2-4 characters)</li>
                            <li>Use uppercase for consistency</li>
                            <li>Make them easy to remember</li>
                        </ul>
                        
                        <h6 class="mt-3">Site Manager</h6>
                        <p class="small mb-0">
                            The site manager oversees operations at this location. 
                            You can assign this later if not available now.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load site managers when company changes
document.getElementById('company_id').addEventListener('change', function() {
    const companyId = this.value;
    const managerSelect = document.getElementById('site_manager_id');
    
    if (!companyId) {
        managerSelect.innerHTML = '<option value="">Not assigned</option>';
        return;
    }
    
    fetch(`<?php echo BASE_URL; ?>/admin/departments/get_users_by_company.php?company_id=${companyId}`)
        .then(response => response.json())
        .then(users => {
            managerSelect.innerHTML = '<option value="">Not assigned</option>';
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.name} (${user.position})`;
                managerSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading users:', error));
});

// Auto-generate site code from name
document.getElementById('site_name').addEventListener('blur', function() {
    const codeInput = document.getElementById('site_code');
    if (!codeInput.value) {
        const words = this.value.trim().split(' ');
        let code = words.map(w => w.charAt(0)).join('').toUpperCase();
        code = code.substring(0, 4);
        codeInput.value = code;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>