<?php
// employee/appraisal/submit.php
require_once __DIR__ . '/../../config/config.php';
// Make sure email.php is loaded
if (!function_exists('sendAppraisalSubmissionEmails')) {
    require_once __DIR__ . '/../../config/email.php';
}

$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('index.php', 'Appraisal ID is required.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify appraisal belongs to user and is in draft status
    $query = "SELECT * FROM appraisals WHERE id = ? AND user_id = ? AND status = 'draft'";
    $stmt = $db->prepare($query);
    $stmt->execute([$appraisal_id, $_SESSION['user_id']]);
    $appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appraisal_data) {
        redirect('index.php', 'Appraisal not found or already submitted.', 'error');
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('submit.php?id=' . $appraisal_id, 'Invalid request. Please try again.', 'error');
        }
        
        // Update status to submitted
        $query = "UPDATE appraisals SET status = 'submitted', employee_submitted_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$appraisal_id])) {
            error_log("✅ Appraisal status updated to 'submitted'");

            // Get user's direct superior
            $user_query = "SELECT direct_superior FROM users WHERE id = ?";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->execute([$_SESSION['user_id']]);
            $user_data = $user_stmt->fetch();
            
            if ($user_data['direct_superior']) {
                // Update appraiser
                $update_query = "UPDATE appraisals SET appraiser_id = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$user_data['direct_superior'], $appraisal_id]);
            }
            
            logActivity($_SESSION['user_id'], 'SUBMIT', 'appraisals', $appraisal_id, null, null, 
                       'Submitted appraisal for review');
                 // DEBUG: Check if function exists
    error_log("✅ Activity logged");
              // THIS MUST BE HERE
    error_log("--- CALLING EMAIL FUNCTION ---");
    error_log("sendAppraisalSubmissionEmails function exists: " . (function_exists('sendAppraisalSubmissionEmails') ? 'YES' : 'NO'));
    
    if (function_exists('sendAppraisalSubmissionEmails')) {
        error_log("Calling sendAppraisalSubmissionEmails({$appraisal_id})");
        $email_result = sendAppraisalSubmissionEmails($appraisal_id);
        error_log("Email result: " . ($email_result ? 'SUCCESS' : 'FAILED'));
    } else {
        error_log("❌ Function does not exist!");
    }
    
            
            redirect('../index.php', 'Appraisal submitted successfully! Notifications are being sent.', 'success');
        } else {
            error_log("Failed to update appraisal status");
            redirect('submit.php?id=' . $appraisal_id, 'Failed to submit appraisal. Please try again.', 'error');
        }
    }
    
} catch (Exception $e) {
    error_log("Submit appraisal error: " . $e->getMessage());
    redirect('index.php', 'An error occurred. Please try again.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-send me-2"></i>Submit Appraisal
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Submission</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Important:</strong> Once you submit your appraisal, you will not be able to make any changes until your manager completes the review process.
                </div>
                
                <h6>Appraisal Details:</h6>
                <ul>
                    <li><strong>Period:</strong> <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - <?php echo formatDate($appraisal_data['appraisal_period_to']); ?></li>
                    <li><strong>Status:</strong> <span class="badge bg-secondary">Draft</span></li>
                    <li><strong>Created:</strong> <?php echo formatDate($appraisal_data['created_at'], 'M d, Y H:i'); ?></li>
                </ul>
                
                <div class="alert alert-warning">
                    <h6><i class="bi bi-checklist me-2"></i>Before submitting, please ensure:</h6>
                    <ul class="mb-0">
                        <li>All required sections are completed</li>
                        <li>Cultural values assessments are filled</li>
                        <li>Performance ratings are provided</li>
                        <li>Key achievements are documented</li>
                        <li>Objectives for next year are set</li>
                        <li>Training needs are identified</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="confirm_completion" required>
                        <label class="form-check-label" for="confirm_completion">
                            I confirm that my appraisal is complete and ready for manager review
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="continue.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Edit
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-send me-2"></i>Submit for Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to submit this appraisal? You will not be able to make changes after submission.')) {
        e.preventDefault();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>