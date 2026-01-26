
<?php
// admin/sections/delete.php

require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$section_id = $_GET['id'] ?? 0;
if (!$section_id) {
    redirect('index.php', 'Section ID is required.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get section details
    $query = "SELECT fs.section_title, fs.form_id, f.title as form_title
              FROM form_sections fs 
              JOIN forms f ON fs.form_id = f.id 
              WHERE fs.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        redirect('index.php', 'Section not found.', 'error');
    }
    
    // Check if section has questions
    $query = "SELECT COUNT(*) as count FROM form_questions WHERE section_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id]);
    $question_count = $stmt->fetch()['count'];
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('index.php?form_id=' . $section['form_id'], 'Invalid request.', 'error');
        }
        
        // Delete section (cascade will handle questions)
        $query = "DELETE FROM form_sections WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$section_id])) {
            logActivity($_SESSION['user_id'], 'DELETE', 'form_sections', $section_id,
                       ['title' => $section['section_title']], null,
                       'Deleted section: ' . $section['section_title']);
            
            redirect('index.php?form_id=' . $section['form_id'], 'Section deleted successfully!', 'success');
        } else {
            redirect('index.php?form_id=' . $section['form_id'], 'Failed to delete section.', 'error');
        }
    }
    
} catch (Exception $e) {
    error_log("Section delete error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-trash me-2"></i>Delete Section
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
                    <strong>Warning:</strong> This action cannot be undone. 
                    <?php if ($question_count > 0): ?>
                    Deleting this section will also delete <?php echo $question_count; ?> question(s).
                    <?php endif; ?>
                </div>
                
                <p><strong>Form:</strong> <?php echo htmlspecialchars($section['form_title']); ?></p>
                <p><strong>Section to delete:</strong> <?php echo htmlspecialchars($section['section_title']); ?></p>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php?form_id=<?php echo $section['form_id']; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Delete Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>