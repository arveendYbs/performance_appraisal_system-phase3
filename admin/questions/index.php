<?php
// admin/questions/index.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$section_id = $_GET['section_id'] ?? 0;
$form_id = $_GET['form_id'] ?? 0;

if (!$section_id && !$form_id) {
    redirect('../forms/index.php', 'Section ID or Form ID is required.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($section_id) {
        // Get section details
        $query = "SELECT fs.*, f.title as form_title, f.id as form_id 
                  FROM form_sections fs 
                  JOIN forms f ON fs.form_id = f.id 
                  WHERE fs.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$section_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            redirect('../forms/index.php', 'Section not found.', 'error');
        }
        $form_id = $section['form_id'];
        
        // Get questions for this section
        $query = "SELECT * FROM form_questions 
                  WHERE section_id = ? AND is_active = 1 
                  ORDER BY question_order";
        $stmt = $db->prepare($query);
        $stmt->execute([$section_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Get all questions for the form
        $query = "SELECT f.title as form_title FROM forms f WHERE f.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$form_id]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$form) {
            redirect('../forms/index.php', 'Form not found.', 'error');
        }
        
        $query = "SELECT fq.*, fs.section_title 
                  FROM form_questions fq 
                  JOIN form_sections fs ON fq.section_id = fs.id 
                  WHERE fs.form_id = ? AND fq.is_active = 1 
                  ORDER BY fs.section_order, fq.question_order";
        $stmt = $db->prepare($query);
        $stmt->execute([$form_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $section = ['form_title' => $form['form_title'], 'form_id' => $form_id];
    }
    
} catch (Exception $e) {
    error_log("Questions index error: " . $e->getMessage());
    redirect('../forms/index.php', 'An error occurred.', 'error');
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-question-circle me-2"></i>Manage Questions
                </h1>
                <small class="text-muted">
                    Form: <?php echo htmlspecialchars($section['form_title']); ?>
                    <?php if (isset($section['section_title'])): ?>
                    → Section: <?php echo htmlspecialchars($section['section_title']); ?>
                    <?php endif; ?>
                </small>
            </div>
            <div>
                <?php if ($section_id): ?>
                <a href="../sections/index.php?form_id=<?php echo $form_id; ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Back to Sections
                </a>
                <a href="create.php?section_id=<?php echo $section_id; ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add Question
                </a>
                <?php else: ?>
                <a href="../forms/index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Back to Forms
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($questions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-question-circle display-1 text-muted mb-3"></i>
                    <h5>No Questions Found</h5>
                    <p class="text-muted mb-3">Add questions to make this form functional.</p>
                    <?php if ($section_id): ?>
                    <a href="create.php?section_id=<?php echo $section_id; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add First Question
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <?php if (!$section_id): ?><th>Section</th><?php endif; ?>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sortable-questions">
                            <?php foreach ($questions as $question): ?>
                            <tr data-question-id="<?php echo $question['id']; ?>">
                                <td>
                                    <i class="bi bi-grip-vertical text-muted me-2" style="cursor: move;"></i>
                                    <span class="badge bg-secondary"><?php echo $question['question_order']; ?></span>
                                </td>
                                <?php if (!$section_id): ?>
                                <td><small class="text-muted"><?php echo htmlspecialchars($question['section_title']); ?></small></td>
                                <?php endif; ?>
                                <td>
                                    <strong><?php echo htmlspecialchars(substr($question['question_text'], 0, 80)); ?></strong>
                                    <?php if (strlen($question['question_text']) > 80): ?>...<?php endif; ?>
                                    <?php if ($question['question_description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($question['question_description'], 0, 60)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php 
                                        echo ucwords(str_replace('_', ' ', $question['response_type'])); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $question['is_required'] ? 'bg-warning' : 'bg-secondary'; ?>">
                                        <?php echo $question['is_required'] ? 'Required' : 'Optional'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit.php?id=<?php echo $question['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit Question">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $question['id']; ?>" 
                                           class="btn btn-outline-danger" title="Delete Question"
                                           onclick="return confirmDelete('Are you sure you want to delete this question?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    // Make questions sortable
    $("#sortable-questions").sortable({
        handle: '.bi-grip-vertical',
        update: function(event, ui) {
            var order = [];
            $('#sortable-questions tr').each(function(index) {
                order.push({
                    id: $(this).data('question-id'),
                    order: index + 1
                });
            });
            
            // Send AJAX request to update order
            $.post('../../api/update_question_order.php', {
                questions: order,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            }).done(function(response) {
                console.log('Order updated successfully');
            }).fail(function() {
                alert('Failed to update order. Please refresh and try again.');
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>