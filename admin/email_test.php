<?php
// admin/email_test.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/email.php';

// Check if user is admin
if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied. Admin only.', 'error');
}

// Handle email sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_emails'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('email_test.php', 'Invalid request. Please try again.', 'error');
    }
    
    $selected_appraisals = $_POST['appraisal_ids'] ?? [];
    
    if (empty($selected_appraisals)) {
        redirect('email_test.php', 'Please select at least one appraisal.', 'warning');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $success_count = 0;
    $failed_count = 0;
    $results = [];
    
    foreach ($selected_appraisals as $appraisal_id) {
        try {
            // Send review completion emails
            $email_sent = sendReviewCompletionEmails($appraisal_id);
            
            if ($email_sent) {
                $success_count++;
                $results[] = "Appraisal ID {$appraisal_id}: Email sent successfully";
            } else {
                $failed_count++;
                $results[] = "Appraisal ID {$appraisal_id}: Failed to send email";
            }
        } catch (Exception $e) {
            $failed_count++;
            $results[] = "Appraisal ID {$appraisal_id}: Error - " . $e->getMessage();
            error_log("Email test error for appraisal {$appraisal_id}: " . $e->getMessage());
        }
    }
    
    // Store results in session for display
    $_SESSION['email_test_results'] = [
        'success_count' => $success_count,
        'failed_count' => $failed_count,
        'details' => $results
    ];
    
    redirect('email_test.php', "Emails sent: {$success_count} successful, {$failed_count} failed.", 
             $failed_count > 0 ? 'warning' : 'success');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get completed appraisals where emails might not have been sent
    // We'll show all completed appraisals and let admin decide which ones to resend
    $query = "SELECT 
                a.id,
                a.user_id,
                a.appraisal_period_from,
                a.appraisal_period_to,
                a.status,
                a.grade,
                a.total_score,
                a.manager_reviewed_at,
                u.name as employee_name,
                COALESCE(NULLIF(u.emp_email, ''), u.email) as employee_email,
                m.name as manager_name,
                c.name as company_name,
                (SELECT COUNT(*) 
                 FROM email_logs 
                 WHERE appraisal_id = a.id 
                 AND email_type = 'review_completed_employee'
                 AND status = 'sent') as email_sent_count
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN users m ON u.direct_superior = m.id
              LEFT JOIN companies c ON u.company_id = c.id
              WHERE a.status = 'completed'
              ORDER BY a.manager_reviewed_at DESC
              LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get email test results if available
    $email_results = $_SESSION['email_test_results'] ?? null;
    unset($_SESSION['email_test_results']);
    
} catch (Exception $e) {
    error_log("Admin email test error: " . $e->getMessage());
    $appraisals = [];
}
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-2">
                    <i class="bi bi-envelope-check me-2"></i>Admin Email Test Panel
                </h1>
                <p class="text-muted mb-0">Manually send completion emails for appraisals</p>
            </div>
            <div class="text-end">
                <small class="text-muted">Total Completed Appraisals</small>
                <h4 class="mb-0"><?php echo count($appraisals); ?></h4>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info d-flex align-items-start">
            <i class="bi bi-info-circle me-2 mt-1"></i>
            <div>
                <strong>Purpose:</strong> Use this panel to manually send completion emails for appraisals that were 
                completed when the email system was down. Select the appraisals you want to send emails for and click 
                "Send Selected Emails". The system will send notifications to employees and HR personnel.
            </div>
        </div>

        <?php if ($email_results): ?>
        <!-- Email Results -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-check-circle text-success me-2"></i>Email Send Results
                </h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="alert alert-success mb-0">
                            <strong>Successful:</strong> <?php echo $email_results['success_count']; ?> emails sent
                        </div>
                    </div>
                    <?php if ($email_results['failed_count'] > 0): ?>
                    <div class="col-md-6">
                        <div class="alert alert-danger mb-0">
                            <strong>Failed:</strong> <?php echo $email_results['failed_count']; ?> emails
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <details>
                    <summary class="btn btn-sm btn-outline-secondary">View Details</summary>
                    <div class="mt-3">
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($email_results['details'] as $detail): ?>
                            <li class="mb-1">
                                <i class="bi bi-<?php echo strpos($detail, 'successfully') !== false ? 'check-circle text-success' : 'x-circle text-danger'; ?>"></i>
                                <?php echo htmlspecialchars($detail); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        <?php endif; ?>

        <!-- Appraisals List -->
        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Completed Appraisals</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllUnsent()">
                            Select All Unsent
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                            Clear Selection
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($appraisals)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-1"></i>
                    <p class="mt-3">No completed appraisals found</p>
                </div>
                <?php else: ?>
                <form method="POST" action="" id="emailForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="send_emails" value="1">
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAll" class="form-check-input" 
                                               onchange="toggleSelectAll(this)">
                                    </th>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th>Grade</th>
                                    <th>Score</th>
                                    <th>Completed Date</th>
                                    <th>Manager</th>
                                    <th>Email Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appraisals as $appraisal): 
                                    $email_sent = $appraisal['email_sent_count'] > 0;
                                ?>
                                <tr class="<?php echo $email_sent ? '' : 'table-warning'; ?>">
                                    <td>
                                        <input type="checkbox" 
                                               name="appraisal_ids[]" 
                                               value="<?php echo $appraisal['id']; ?>"
                                               class="form-check-input appraisal-checkbox"
                                               data-sent="<?php echo $email_sent ? '1' : '0'; ?>">
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($appraisal['employee_name']); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($appraisal['employee_email']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($appraisal['company_name']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo formatDate($appraisal['appraisal_period_from'], 'M Y'); ?> - 
                                            <?php echo formatDate($appraisal['appraisal_period_to'], 'M Y'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($appraisal['grade'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($appraisal['total_score'], 1); ?></td>
                                    <td>
                                        <small>
                                            <?php echo $appraisal['manager_reviewed_at'] 
                                                ? formatDate($appraisal['manager_reviewed_at']) 
                                                : 'N/A'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($appraisal['manager_name'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($email_sent): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> Sent
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-exclamation-triangle"></i> Not Sent
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <span id="selectedCount">0</span> appraisal(s) selected
                        </div>
                        <button type="submit" class="btn btn-primary" id="sendBtn" disabled>
                            <i class="bi bi-send me-2"></i>Send Selected Emails
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Email Log -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Email Activity</h5>
            </div>
            <div class="card-body">
                <?php
                $log_query = "SELECT el.*, u.name as recipient_user_name
                              FROM email_logs el
                              LEFT JOIN users u ON el.user_id = u.id
                              WHERE el.email_type = 'review_completed_employee'
                              ORDER BY el.sent_at DESC
                              LIMIT 20";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute();
                $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($logs)): ?>
                <p class="text-muted mb-0">No email activity recorded</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Recipient</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Appraisal ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('Y-m-d H:i:s', strtotime($log['sent_at'])); ?></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['recipient_name']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['recipient_email']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['email_type']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo $log['appraisal_id'] ?? '-'; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $log['status'] == 'sent' ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($log['status']); ?>
                                    </span>
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

<script>
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.appraisal-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('sendBtn').disabled = count === 0;
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.appraisal-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function selectAllUnsent() {
    document.getElementById('selectAll').checked = false;
    const checkboxes = document.querySelectorAll('.appraisal-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = cb.dataset.sent === '0';
    });
    updateSelectedCount();
}

function clearSelection() {
    document.getElementById('selectAll').checked = false;
    const checkboxes = document.querySelectorAll('.appraisal-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    updateSelectedCount();
}

// Add event listeners to checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.appraisal-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    // Confirm before sending
    document.getElementById('emailForm').addEventListener('submit', function(e) {
        const count = document.querySelectorAll('.appraisal-checkbox:checked').length;
        if (!confirm(`Are you sure you want to send completion emails for ${count} appraisal(s)?`)) {
            e.preventDefault();
        }
    });
});
</script>

<style>
.table-warning {
    background-color: #fff3cd !important;
}
details summary {
    cursor: pointer;
    user-select: none;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>