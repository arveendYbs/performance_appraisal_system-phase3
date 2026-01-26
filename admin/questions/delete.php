<?php
// admin/questions/delete.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$question_id = $_GET['id'] ?? 0;
if (!$question_id) {
    redirect('index.php', 'Question ID is required.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get question details
    $query = "SELECT fq.question_text, fq.section_id, fs.section_title, f.title as form_title
              FROM form_questions fq 
              JOIN form_sections fs ON fq.section_id = fs.id 
              JOIN forms f ON fs.form_id = f.id 
              WHERE fq.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$question_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        redirect('index.php', 'Question not found.', 'error');
    }
    
    // Check if question has responses
    $query = "SELECT COUNT(*) as count FROM responses WHERE question_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$question_id]);
    $response_count = $stmt->fetch()['count'];
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('index.php?section_id=' . $question['section_id'], 'Invalid request.', 'error');
        }
        
        if ($response_count > 0) {
            // Soft delete - set inactive
            $query = "UPDATE form_questions SET is_active = 0 WHERE id = ?";
        } else {
            // Hard delete if no responses
            $query = "DELETE FROM form_questions WHERE id = ?";
        }
        
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$question_id])) {
            logActivity($_SESSION['user_id'], 'DELETE', 'form_questions', $question_id,
                       ['question_text' => $question['question_text']], null,
                       'Deleted question: ' . substr($question['question_text'], 0, 50));
            
            redirect('index.php?section_id=' . $question['section_id'], 'Question deleted successfully!', 'success');
        } else {
            redirect('index.php?section_id=' . $question['section_id'], 'Failed to delete question.', 'error');
        }
    }
    
} catch (Exception $e) {
    error_log("Question delete error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="bi bi-trash me-2"></i>Delete Question
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> 
                    <?php if ($response_count > 0): ?>
                    This question has <?php echo $response_count; ?> response(s). It will be deactivated instead of permanently deleted.
                    <?php else: ?>
                    This action cannot be undone. The question will be permanently deleted.
                    <?php endif; ?>
                </div>
                
                <p><strong>Form:</strong> <?php echo htmlspecialchars($question['form_title']); ?></p>
                <p><strong>Section:</strong> <?php echo htmlspecialchars($question['section_title']); ?></p>
                <p><strong>Question:</strong> <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)); ?>
                <?php if (strlen($question['question_text']) > 100): ?>...<?php endif; ?></p>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php?section_id=<?php echo $question['section_id']; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>
                            <?php echo $response_count > 0 ? 'Deactivate' : 'Delete'; ?> Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>