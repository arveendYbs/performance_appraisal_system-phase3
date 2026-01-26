
<?php
// admin/audit/index.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';

$page = $_GET['page'] ?? 1;
$records_per_page = 20;

try {
    $database = new Database();
    $db = $database->getConnection();
    $audit = new AuditLog($db);
    
    // Get audit logs with pagination
    $stmt = $audit->read($page, $records_per_page);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM audit_logs";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute();
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
} catch (Exception $e) {
    error_log("Audit logs error: " . $e->getMessage());
    $logs = [];
    $total_pages = 1;
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-clock-history me-2"></i>Audit Logs
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history display-1 text-muted mb-3"></i>
                    <h5>No Audit Logs</h5>
                    <p class="text-muted">System activities will appear here.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Record ID</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small>
                                        <?php echo formatDate($log['created_at'], 'M d, Y'); ?><br>
                                        <?php echo formatDate($log['created_at'], 'H:i:s'); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $log['action'] == 'CREATE' ? 'success' : 
                                            ($log['action'] == 'DELETE' ? 'danger' : 
                                            ($log['action'] == 'LOGIN' ? 'info' : 'warning')); 
                                    ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($log['table_name']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($log['record_id'] ?? ''); ?></small></td>
                                <td>
                                    <small><?php echo htmlspecialchars(substr($log['details'] ?? '', 0, 50)); ?></small>
                                    <?php if (strlen($log['details'] ?? '') > 50): ?>
                                        <button class="btn btn-link btn-sm p-0" data-bs-toggle="modal" 
                                                data-bs-target="#detailsModal<?php echo $log['id']; ?>">
                                            ...more
                                        </button>
                                        
                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $log['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Audit Log Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>Time:</strong> <?php echo formatDate($log['created_at'], 'M d, Y H:i:s'); ?></p>
                                                        <p><strong>User:</strong> <?php echo htmlspecialchars($log['user_name']); ?></p>
                                                        <p><strong>Action:</strong> <?php echo htmlspecialchars($log['action']); ?></p>
                                                        <p><strong>Details:</strong></p>
                                                        <pre><?php echo htmlspecialchars($log['details']); ?></pre>
                                                        
                                                        <?php if ($log['old_values']): ?>
                                                        <p><strong>Old Values:</strong></p>
                                                        <pre><?php echo htmlspecialchars($log['old_values']); ?></pre>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($log['new_values']): ?>
                                                        <p><strong>New Values:</strong></p>
                                                        <pre><?php echo htmlspecialchars($log['new_values']); ?></pre>
                                                        <?php endif; ?>
                                                        
                                                        <p><strong>User Agent:</strong></p>
                                                        <small><?php echo htmlspecialchars($log['user_agent']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Audit log pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>