<?php
// admin/departments/index.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Department.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$company_filter = $_GET['company'] ?? '';
$search = $_GET['search'] ?? '';

try {
    $department = new Department($db);
    
    // Build query
    $query = "SELECT d.*, 
                     c.name as company_name,
                     (SELECT COUNT(*) FROM users WHERE department_id = d.id AND is_active = 1) as employee_count,
                     l2.name as level_2_name,
                     l3.name as level_3_name,
                     l4.name as level_4_name,
                     l5.name as level_5_name
              FROM departments d
              JOIN companies c ON d.company_id = c.id
              LEFT JOIN users l2 ON d.level_2_approver_id = l2.id
              LEFT JOIN users l3 ON d.level_3_approver_id = l3.id
              LEFT JOIN users l4 ON d.level_4_approver_id = l4.id
              LEFT JOIN users l5 ON d.level_5_approver_id = l5.id
              WHERE 1=1";
    
    $params = [];
    
    if ($company_filter) {
        $query .= " AND d.company_id = ?";
        $params[] = $company_filter;
    }
    
    if ($search) {
        $query .= " AND (d.department_name LIKE ? OR d.department_code LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY c.name, d.department_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for filter
    require_once __DIR__ . '/../../classes/Company.php';
    $company_model = new Company($db);
    $companies_stmt = $company_model->getAll();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_query = "SELECT 
                        COUNT(*) as total_departments,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_departments,
                        SUM(CASE WHEN level_2_approver_id IS NOT NULL THEN 1 ELSE 0 END) as with_level2,
                        SUM(CASE WHEN level_3_approver_id IS NOT NULL THEN 1 ELSE 0 END) as with_level3,
                        SUM(CASE WHEN level_4_approver_id IS NOT NULL THEN 1 ELSE 0 END) as with_level4
                    FROM departments";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Departments index error: " . $e->getMessage());
    $error_message = "Error loading departments.";
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Departments</h1>
                <p class="text-muted">Manage organizational departments and approval chains</p>
            </div>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Department
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Departments</h6>
                                <h3 class="mb-0"><?php echo $stats['total_departments']; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-diagram-3 text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Active</h6>
                                <h3 class="mb-0"><?php echo $stats['active_departments']; ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">With Level 2 Approver</h6>
                                <h3 class="mb-0"><?php echo $stats['with_level2']; ?></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-person-check text-info fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">With Level 3+ Approvers</h6>
                                <h3 class="mb-0"><?php echo $stats['with_level3']; ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="bi bi-people text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Company</label>
                        <select name="company" class="form-select">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>" 
                                        <?php echo $company_filter == $comp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($comp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or code..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Departments Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($departments)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <p class="text-muted mt-3">No departments found.</p>
                        <a href="create.php" class="btn btn-primary">Create First Department</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Company</th>
                                    <th>Code</th>
                                    <th>Employees</th>
                                    <th>Approval Chain</th>
                                    <th>Levels Config</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($dept['company_name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($dept['department_code']); ?></code></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $dept['employee_count']; ?> employees
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if ($dept['level_2_name']): ?>
                                                    <span class="badge bg-primary" title="Level 2">
                                                        L2: <?php echo htmlspecialchars($dept['level_2_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">⚠ L2 Missing</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($dept['level_3_name']): ?>
                                                    <span class="badge bg-primary" title="Level 3">
                                                        L3: <?php echo htmlspecialchars($dept['level_3_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($dept['level_4_name']): ?>
                                                    <span class="badge bg-primary" title="Level 4">
                                                        L4: <?php echo htmlspecialchars($dept['level_4_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($dept['level_5_name']): ?>
                                                    <span class="badge bg-primary" title="Level 5">
                                                        L5: <?php echo htmlspecialchars($dept['level_5_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                Staff:<?php echo $dept['staff_approval_levels']; ?> |
                                                Worker:<?php echo $dept['worker_approval_levels']; ?> |
                                                Mgr:<?php echo $dept['manager_approval_levels']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($dept['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit.php?id=<?php echo $dept['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($dept['employee_count'] == 0): ?>
                                                    <a href="delete.php?id=<?php echo $dept['id']; ?>" 
                                                       class="btn btn-outline-danger" 
                                                       onclick="return confirm('Delete this department?')" 
                                                       title="Delete">
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
                <?php endif; ?>
            </div>
        </div>

        <!-- Help Card -->
        <div class="card border-0 shadow-sm mt-4 bg-light">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle"></i> About Approval Chains</h6>
                <p class="small mb-2">
                    <strong>Approval Chain Configuration:</strong> Each department can have up to 6 approval levels. 
                    Level 1 is always the employee's direct superior (who gives ratings). Levels 2-6 are configured here and approve without rating.
                </p>
                <p class="small mb-0">
                    <strong>Levels by Employee Type:</strong> Different employee types (staff, workers, supervisors, managers) 
                    can have different numbers of approval levels. For example, production workers might need 5 levels 
                    while office staff only need 2 levels.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>