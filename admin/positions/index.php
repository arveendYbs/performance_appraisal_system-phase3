<?php
// admin/positions/index.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Position.php';
require_once __DIR__ . '/../../classes/Company.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$company_filter = $_GET['company'] ?? '';
$employee_type_filter = $_GET['employee_type'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Build query
    $query = "SELECT p.*, 
                     c.name as company_name,
                     d.department_name,
                     (SELECT COUNT(*) FROM users WHERE position_id = p.id AND is_active = 1) as employee_count
              FROM positions p
              JOIN companies c ON p.company_id = c.id
              LEFT JOIN departments d ON p.department_id = d.id
              WHERE 1=1";
    
    $params = [];
    
    if ($company_filter) {
        $query .= " AND p.company_id = ?";
        $params[] = $company_filter;
    }
    
    if ($employee_type_filter) {
        $query .= " AND p.employee_type = ?";
        $params[] = $employee_type_filter;
    }
    
    if ($search) {
        $query .= " AND (p.position_title LIKE ? OR p.position_code LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY c.name, p.employee_type, p.position_title";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for filter
    $company_model = new Company($db);
    $companies_stmt = $company_model->getAll();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_query = "SELECT 
                        COUNT(*) as total_positions,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_positions,
                        (SELECT COUNT(*) FROM users WHERE position_id IS NOT NULL AND is_active = 1) as employees_assigned
                    FROM positions";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Positions index error: " . $e->getMessage());
    $error_message = "Error loading positions.";
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Positions</h1>
                <p class="text-muted">Manage standardized job positions</p>
            </div>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Position
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Positions</h6>
                                <h3 class="mb-0"><?php echo $stats['total_positions']; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-briefcase text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Active Positions</h6>
                                <h3 class="mb-0"><?php echo $stats['active_positions']; ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Employees Assigned</h6>
                                <h3 class="mb-0"><?php echo $stats['employees_assigned']; ?></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-people text-info fs-4"></i>
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
                    <div class="col-md-3">
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
                    
                    <div class="col-md-3">
                        <label class="form-label">Employee Type</label>
                        <select name="employee_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="office_staff" <?php echo $employee_type_filter == 'office_staff' ? 'selected' : ''; ?>>Office Staff</option>
                            <option value="production_worker" <?php echo $employee_type_filter == 'production_worker' ? 'selected' : ''; ?>>Production Worker</option>
                            <option value="supervisor" <?php echo $employee_type_filter == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            <option value="manager" <?php echo $employee_type_filter == 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="executive" <?php echo $employee_type_filter == 'executive' ? 'selected' : ''; ?>>Executive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by title or code..." 
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

        <!-- Positions Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($positions)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <p class="text-muted mt-3">No positions found.</p>
                        <a href="create.php" class="btn btn-primary">Create First Position</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Position Title</th>
                                    <th>Company</th>
                                    <th>Code</th>
                                    <th>Employee Type</th>
                                    <th>Department</th>
                                    <th>Probation</th>
                                    <th>Employees</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($positions as $position): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($position['position_title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($position['company_name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($position['position_code']); ?></code></td>
                                        <td>
                                            <?php
                                            $type_badges = [
                                                'office_staff' => 'primary',
                                                'production_worker' => 'warning',
                                                'supervisor' => 'info',
                                                'manager' => 'success',
                                                'executive' => 'danger'
                                            ];
                                            $badge_color = $type_badges[$position['employee_type']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $position['employee_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($position['department_name']): ?>
                                                <small><?php echo htmlspecialchars($position['department_name']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">All Departments</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($position['requires_probation']): ?>
                                                <small><?php echo $position['default_probation_months']; ?> months</small>
                                            <?php else: ?>
                                                <small class="text-muted">Not required</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $position['employee_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($position['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit.php?id=<?php echo $position['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($position['employee_count'] == 0): ?>
                                                    <a href="delete.php?id=<?php echo $position['id']; ?>" 
                                                       class="btn btn-outline-danger" 
                                                       onclick="return confirm('Delete this position?')" 
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
                <h6 class="card-title"><i class="bi bi-info-circle"></i> About Employee Types</h6>
                <div class="row small">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Office Staff:</strong> General office employees, clerks, executives (non-production)</p>
                        <p class="mb-2"><strong>Production Worker:</strong> Factory workers, operators, technicians (production line)</p>
                        <p class="mb-0"><strong>Supervisor:</strong> Team leaders, line supervisors, shift leaders</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Manager:</strong> Department managers, heads, senior managers</p>
                        <p class="mb-0"><strong>Executive:</strong> Directors, VPs, C-level executives (top management)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>