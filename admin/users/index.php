<?php
// admin/users/index.php
require_once __DIR__ . '/../../config/config.php';
/* 
if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
} */

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$company_filter = $_GET['company'] ?? '';
$site_filter = $_GET['site'] ?? '';
$department_filter = $_GET['department'] ?? '';
$role_filter = $_GET['role'] ?? '';
$page = $_GET['page'] ?? 1;
$records_per_page = 15;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query with filters
    $query = "SELECT u.id, u.name, u.emp_number, u.email, u.emp_email, u.position, 
                     u.department, u.site, u.role, u.is_active, u.is_hr, u.is_top_management,
                     s.name as superior_name, c.name as company_name
              FROM users u
              LEFT JOIN users s ON u.direct_superior = s.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE 1=1";
    
 
    
    $params = [];
       // Add after line 28 where the query is built
    if (!hasRole('admin') && $is_hr) {
        // Limit to HR's assigned companies
        $query .= " AND u.company_id IN (
            SELECT company_id FROM hr_companies WHERE user_id = ?
        )";
        // Add user_id as the first parameter
        array_unshift($params, $_SESSION['user_id']);
    }
    // Add search filter
    if (!empty($search)) {
        $query .= " AND (u.name LIKE ? OR u.emp_number LIKE ? OR u.email LIKE ? OR u.position LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add company filter
    if (!empty($company_filter)) {
        $query .= " AND u.company_id = ?";
        $params[] = $company_filter;
    }
    
    // Add site filter
    if (!empty($site_filter)) {
        $query .= " AND u.site = ?";
        $params[] = $site_filter;
    }
    
    // Add department filter
    if (!empty($department_filter)) {
        $query .= " AND u.department = ?";
        $params[] = $department_filter;
    }
    
    // Add role filter
    if (!empty($role_filter)) {
        $query .= " AND u.role = ?";
        $params[] = $role_filter;
    }
    
    // Get total count for pagination (before LIMIT)
    $count_query = str_replace("SELECT u.id, u.name, u.emp_number, u.email, u.emp_email, u.position, 
                     u.department, u.site, u.role, u.is_active, u.is_hr, u.is_top_management,
                     s.name as superior_name, c.name as company_name", "SELECT COUNT(*) as total", $query);
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Add pagination
   // Pagination
$from_record_num = ($page - 1) * $records_per_page;
$query .= " ORDER BY c.name, u.name LIMIT $from_record_num, $records_per_page";

$stmt = $db->prepare($query);
$stmt->execute($params);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get filter options
    $companies_stmt = $db->query("SELECT DISTINCT id, name FROM companies WHERE is_active = 1 ORDER BY name");
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sites_stmt = $db->query("SELECT DISTINCT site FROM users WHERE site IS NOT NULL AND site != '' ORDER BY site");
    $sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $departments_stmt = $db->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
    $departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Users index error: " . $e->getMessage());
    $users = [];
    $total_pages = 1;
    $total_records = 0;
}

// Build query string for pagination
$query_params = [];
if (!empty($search)) $query_params[] = 'search=' . urlencode($search);
if (!empty($company_filter)) $query_params[] = 'company=' . urlencode($company_filter);
if (!empty($site_filter)) $query_params[] = 'site=' . urlencode($site_filter);
if (!empty($department_filter)) $query_params[] = 'department=' . urlencode($department_filter);
if (!empty($role_filter)) $query_params[] = 'role=' . urlencode($role_filter);
$query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-people me-2"></i>User Management
                </h1>
                <p class="text-muted mb-0">
                    <small>Showing <?php echo count($users); ?> of <?php echo $total_records; ?> users</small>
                </p>
            </div>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-2"></i>Add New User
            </a>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <!-- Search -->
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Name, email, employee #..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <!-- Company Filter -->
                    <div class="col-md-2">
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" 
                                    <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Department Filter -->
                    <div class="col-md-2">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Site Filter -->
                    <div class="col-md-2">
                        <label class="form-label">Site</label>
                        <select class="form-select" name="site">
                            <option value="">All Sites</option>
                            <?php foreach ($sites as $site): ?>
                            <option value="<?php echo htmlspecialchars($site['site']); ?>" 
                                    <?php echo $site_filter == $site['site'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($site['site']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Role Filter -->
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="manager" <?php echo $role_filter == 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="employee" <?php echo $role_filter == 'employee' ? 'selected' : ''; ?>>Employee</option>
                            <option value="worker" <?php echo $role_filter == 'worker' ? 'selected' : ''; ?>>Worker</option>
                        </select>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    
                    <?php if (!empty($search) || !empty($company_filter) || !empty($site_filter) || !empty($department_filter) || !empty($role_filter)): ?>
                    <div class="col-12">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted mb-3"></i>
                    <h5>No Users Found</h5>
                    <?php if (!empty($search) || !empty($company_filter) || !empty($site_filter) || !empty($department_filter) || !empty($role_filter)): ?>
                    <p class="text-muted mb-3">No users match your filter criteria.</p>
                    <a href="index.php" class="btn btn-outline-secondary">Clear Filters</a>
                    <?php else: ?>
                    <p class="text-muted mb-3">Start by creating your first user.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Company</th>
                                <th>Department</th>
                                <th>Site</th>
                                <th>Role</th>
                                <th>Superior</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                             style="width: 32px; height: 32px;">
                                            <i class="bi bi-person-fill text-white" style="font-size: 0.9rem;"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                            
                                            <?php if ($user['is_hr']): ?>
                                            <span class="badge bg-info" style="font-size: 0.65rem;">HR</span>
                                            <?php endif; ?>
                                            <?php if ($user['is_top_management']): ?>
                                            <span class="badge bg-success" style="font-size: 0.65rem;">Top Mgmt</span>
                                               
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['emp_number']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><small><?php echo htmlspecialchars($user['position']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($user['company_name'] ?: 'N/A'); ?></small></td>
                                <td><small><?php echo htmlspecialchars($user['department']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($user['site'] ?: '-'); ?></small></td>
                                <td>
                                    <?php
                                    $role_badges = [
                                        'admin' => 'danger',
                                        'manager' => 'warning',
                                        'employee' => 'info',
                                        'worker' => 'secondary'
                                    ];
                                    $badge_class = $role_badges[$user['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($user['superior_name'] ?: '-'); ?></small></td>
                                <td>
                                    <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="view.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-info" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="delete.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Improved Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="User pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $query_string; ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <?php
                        // Smart pagination - show max 7 pages
                        $start_page = max(1, $page - 3);
                        $end_page = min($total_pages, $page + 3);
                        
                        // Always show first page
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo $query_string; ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Page numbers -->
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $query_string; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <!-- Always show last page -->
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Next Button -->
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                    
                    <p class="text-center text-muted mt-2">
                        <small>Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                        (<?php echo $total_records; ?> total users)</small>
                    </p>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>