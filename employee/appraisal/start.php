<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';


try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user already has an active appraisal
    $appraisal = new Appraisal($db);
    if ($appraisal->getCurrentAppraisal($_SESSION['user_id'])) {
        redirect('continue.php', 'You already have an active appraisal.', 'info');
    }
    
    // Get appropriate form based on user role
    $form = new Form($db);
    if (!$form->getFormByRole($_SESSION['user_role'])) {
        redirect('../index.php', 'No appraisal form is available for your role. Please contact administrator.', 'error');
    }
    
} catch (Exception $e) {
    error_log("Start appraisal error: " . $e->getMessage());
    redirect('../index.php', 'An error occurred. Please try again.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('start.php', 'Invalid request. Please try again.', 'error');
    }
    
    $period_from = $_POST['period_from'] ?? '';
    $period_to = $_POST['period_to'] ?? '';
    
    if (empty($period_from) || empty($period_to)) {
        $error_message = 'Please select both start and end dates for the appraisal period.';
    } elseif (strtotime($period_from) >= strtotime($period_to)) {
        $error_message = 'End date must be after start date.';
    } else {
        try {
            $appraisal->user_id = $_SESSION['user_id'];
            $appraisal->form_id = $form->id;
            $appraisal->appraisal_period_from = $period_from;
            $appraisal->appraisal_period_to = $period_to;
            
            if ($appraisal->create()) {
                logActivity($_SESSION['user_id'], 'CREATE', 'appraisals', $appraisal->id, null,
                           ['period_from' => $period_from, 'period_to' => $period_to],
                           'Started new appraisal');
                
                redirect('continue.php', 'Appraisal started successfully!', 'success');
            } else {
                $error_message = 'Failed to start appraisal. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Create appraisal error: " . $e->getMessage());
            $error_message = 'An error occurred. Please try again.';
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-play-circle me-2"></i>Start New Appraisal
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Appraisal Setup</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Form Type:</strong> <?php echo ucfirst($form->form_type); ?> Staff<br>
                    <strong>Form Title:</strong> <?php echo htmlspecialchars($form->title); ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_from" class="form-label">Appraisal Period From <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="period_from" name="period_from" 
                                       value="<?php echo $_POST['period_from'] ?? date('Y-01-01'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_to" class="form-label">Appraisal Period To <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="period_to" name="period_to" 
                                       value="<?php echo $_POST['period_to'] ?? date('Y-12-31'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Once you start the appraisal, you can save your progress and return later. 
                        Make sure to submit it for manager review when completed.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-play-circle me-2"></i>Start Appraisal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
