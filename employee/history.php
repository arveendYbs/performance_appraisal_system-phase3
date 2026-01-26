
<?php
// employee/history.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$page = $_GET['page'] ?? 1;
$records_per_page = 10;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user's appraisal history with pagination
    $from_record_num = ($records_per_page * $page) - $records_per_page;
    
    $query = "SELECT a.*, f.title as form_title, u.name as appraiser_name
              FROM appraisals a
              LEFT JOIN forms f ON a.form_id = f.id
              LEFT JOIN users u ON a.appraiser_id = u.id
              WHERE a.user_id = ?
              ORDER BY a.created_at DESC
              LIMIT ?, ?";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(2, $from_record_num, PDO::PARAM_INT);
    $stmt->bindParam(3, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM appraisals WHERE user_id = ?";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute([$_SESSION['user_id']]);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
} catch (Exception $e) {
    error_log("Appraisal history error: " . $e->getMessage());
    $appraisals = [];
    $total_pages = 1;
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-clock-history me-2"></i>Appraisal History
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($appraisals)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history display-1 text-muted mb-3"></i>
                    <h5>No Appraisal History</h5>
                    <p class="text-muted mb-3">You haven't completed any appraisals yet.</p>
                    <a href="appraisal/start.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Start Your First Appraisal
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Appraisal Period</th>
                                <th>Form Type</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Score</th>
                                <th>Reviewer</th>
                                <th>Completed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appraisals as $appraisal): ?>
                            <tr>
                                <td>
                                    <strong><?php echo formatDate($appraisal['appraisal_period_from'], 'M Y'); ?></strong> - 
                                    <strong><?php echo formatDate($appraisal['appraisal_period_to'], 'M Y'); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo formatDate($appraisal['appraisal_period_from']); ?> - 
                                        <?php echo formatDate($appraisal['appraisal_period_to']); ?>
                                    </small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($appraisal['form_title'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($appraisal['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $appraisal['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($appraisal['grade']): ?>
                                    <span class="badge bg-light <?php echo getGradeColorClass($appraisal['grade']); ?>">
                                        <?php echo $appraisal['grade']; ?>
                                    </span>
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appraisal['total_score']): ?>
                                    <strong><?php echo $appraisal['total_score']; ?>%</strong>
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($appraisal['appraiser_name'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $appraisal['manager_reviewed_at'] ? 
                                            formatDate($appraisal['manager_reviewed_at'], 'M d, Y') : '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="appraisal/view.php?id=<?php echo $appraisal['id']; ?>" 
                                           class="btn btn-outline-primary" title="View Appraisal">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($appraisal['status'] == 'completed'): ?>
                                        <button class="btn btn-outline-secondary" title="Download PDF" 
                                                onclick="downloadPDF(<?php echo $appraisal['id']; ?>)">
                                            <i class="bi bi-download"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Appraisal history pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
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

<script>
function downloadPDF(appraisalId) {
    // Placeholder for PDF download functionality
    alert('PDF download functionality would be implemented here for appraisal ID: ' + appraisalId);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>