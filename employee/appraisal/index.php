<?php
// employee/appraisal/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user has current appraisal
    $appraisal = new Appraisal($db);
    $has_current = $appraisal->getCurrentAppraisal($_SESSION['user_id']);
    
    if ($has_current) {
        // Redirect based on status
        switch ($appraisal->status) {
            case 'draft':
                redirect('continue.php');
                break;
            case 'submitted':
            case 'in_review':
            case 'completed':
                redirect('view.php?id=' . $appraisal->id);
                break;
        }
    }
    
} catch (Exception $e) {
    error_log("Appraisal index error: " . $e->getMessage());
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-clipboard-data me-2"></i>My Appraisal
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-clipboard-plus display-1 text-primary mb-4"></i>
                <h4>Start Your Performance Appraisal</h4>
                <p class="text-muted mb-4">
                    Complete your performance appraisal to review your achievements, 
                    set goals, and plan your professional development.
                </p>
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="alert alert-info text-start">
                            <h6><i class="bi bi-info-circle me-2"></i>What to expect:</h6>
                            <ul class="mb-0">
                                <li>Cultural Values Assessment (H³CIS framework)</li>
                                <li>Performance competencies evaluation</li>
                                <li>Review of key achievements</li>
                                <li>Goal setting for next year</li>
                                <li>Training and development planning</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="start.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-play-circle me-2"></i>Start New Appraisal
                    </a>
                    <a href="../history.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-clock-history me-2"></i>View History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>