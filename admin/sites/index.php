<?php
// admin/sites/index.php
require_once __DIR__ . '/../../config/config.php';

if (!canManageUsers()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get filter parameters
$company_filter = $_GET['company'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Build query
    $query = "SELECT s.*, 
                     c.name as company_name,
                     manager.name as manager_name,
                     (SELECT COUNT(*) FROM users WHERE primary_site_id = s.id AND is_active = 1) as employee_count
              FROM sites s
              JOIN companies c ON s.company_id = c.id
              LEFT JOIN users manager ON s.site_manager_id = manager.id
              WHERE 1=1";
    
    $params = [];
    
    if ($company_filter) {
        $query .= " AND s.company_id = ?";
        $params[] = $company_filter;
    }
    
    if ($search) {
        $query .= " AND (s.site_name LIKE ? OR s.site_code LIKE ? OR s.city LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY c.name, s.site_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get companies for filter
    require_once __DIR__ . '/../../classes/Company.php';
    $company_model = new Company($db);
    $companies_stmt = $company_model->getAll();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_query = "SELECT 
                        COUNT(*) as total_sites,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_sites,
                        (SELECT COUNT(*) FROM users WHERE primary_site_id IS NOT NULL AND is_active = 1) as employees_assigned
                    FROM sites";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Sites index error: " . $e->getMessage());
    $error_message = "Error loading sites.";
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Sites</h1>
                <p class="text-muted">Manage physical office locations and branches</p>
            </div>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Site
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Sites</h6>
                                <h3 class="mb-0"><?php echo $stats['total_sites']; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-geo-alt text-primary fs-4"></i>
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
                                <h6 class="text-muted mb-1">Active Sites</h6>
                                <h3 class="mb-0"><?php echo $stats['active_sites']; ?></h3>
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
                               placeholder="Search by name, code, or city..." 
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

        <!-- Sites Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($sites)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <p class="text-muted mt-3">No sites found.</p>
                        <a href="create.php" class="btn btn-primary">Create First Site</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Site Name</th>
                                    <th>Company</th>
                                    <th>Code</th>
                                    <th>Location</th>
                                    <th>Site Manager</th>
                                    <th>Employees</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($site['site_name']); ?></strong><br>
                                            <?php if ($site['address']): ?>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($site['address'], 0, 50)); ?>
                                                    <?php echo strlen($site['address']) > 50 ? '...' : ''; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($site['company_name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($site['site_code']); ?></code></td>
                                        <td>
                                            <?php if ($site['city']): ?>
                                                <i class="bi bi-geo-alt-fill text-muted"></i>
                                                <?php echo htmlspecialchars($site['city']); ?>
                                                <?php if ($site['state']): ?>
                                                    , <?php echo htmlspecialchars($site['state']); ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small class="text-muted">Not set</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($site['manager_name']): ?>
                                                <small><?php echo htmlspecialchars($site['manager_name']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Not assigned</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $site['employee_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($site['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit.php?id=<?php echo $site['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($site['employee_count'] == 0): ?>
                                                    <a href="delete.php?id=<?php echo $site['id']; ?>" 
                                                       class="btn btn-outline-danger" 
                                                       onclick="return confirm('Delete this site?')" 
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
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>