
<?php
// admin/forms/delete.php

require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$form_id = $_GET['id'] ?? 0;
if (!$form_id) {
    redirect('index.php', 'Form ID is required.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if form exists and get details
    $query = "SELECT title FROM forms WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        redirect('index.php', 'Form not found.', 'error');
    }
    
    // Check if form is being used in appraisals
    $query = "SELECT COUNT(*) as count FROM appraisals WHERE form_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$form_id]);
    $usage_count = $stmt->fetch()['count'];
    
    if ($usage_count > 0) {
        redirect('index.php', 'Cannot delete form: it is being used in ' . $usage_count . ' appraisal(s).', 'error');
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('index.php', 'Invalid request.', 'error');
        }
        
        // Delete form (cascade will handle sections and questions)
        $query = "DELETE FROM forms WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$form_id])) {
            logActivity($_SESSION['user_id'], 'DELETE', 'forms', $form_id, 
                       ['title' => $form['title']], null, 
                       'Deleted form: ' . $form['title']);
            
            redirect('index.php', 'Form deleted successfully!', 'success');
        } else {
            redirect('index.php', 'Failed to delete form.', 'error');
        }
    }
    
} catch (Exception $e) {
    error_log("Form delete error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-trash me-2"></i>Delete Form
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. Deleting this form will also delete all associated sections and questions.
                </div>
                
                <p><strong>Form to delete:</strong> <?php echo htmlspecialchars($form['title']); ?></p>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Delete Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
