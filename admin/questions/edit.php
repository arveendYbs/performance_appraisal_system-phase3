<?php
// admin/questions/edit.php
require_once __DIR__ . '/../../config/config.php';

if (!hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$question_id = $_GET['id'] ?? 0;
if (!$question_id) {
    redirect('index.php', 'Question ID is required.', 'error');
}

$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get question details
    $query = "SELECT fq.*, fs.section_title, fs.id as section_id, f.title as form_title, f.id as form_id
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
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error_message = 'Invalid request. Please try again.';
        } else {
            $question_text = trim($_POST['question_text'] ?? '');
            $question_description = trim($_POST['question_description'] ?? '');
            $response_type = $_POST['response_type'] ?? '';
            $options = trim($_POST['options'] ?? '');
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $question_order = intval($_POST['question_order'] ?? 1);


                        // Force not required for display type
            if ($response_type === 'display') {
                $is_required = 0;
            }
            if (empty($question_text) || empty($response_type)) {
                $error_message = 'Question text and response type are required.';
            } else {
                // Process options for checkbox/radio types
                $options_json = null;
                if (in_array($response_type, ['checkbox', 'radio']) && !empty($options)) {
                    $options_array = array_map('trim', explode("\n", $options));
                    $options_array = array_filter($options_array); // Remove empty lines
                    $options_json = json_encode($options_array);
                }
                
                $update_query = "UPDATE form_questions 
                               SET question_text = ?, question_description = ?, response_type = ?, 
                                   options = ?, is_required = ?, question_order = ? 
                               WHERE id = ?";
                $stmt = $db->prepare($update_query);
                
                if ($stmt->execute([$question_text, $question_description, $response_type, $options_json, $is_required, $question_order, $question_id])) {
                    logActivity($_SESSION['user_id'], 'UPDATE', 'form_questions', $question_id,
                               ['question_text' => $question['question_text']], ['question_text' => substr($question_text, 0, 50)],
                               'Updated question: ' . substr($question_text, 0, 50));
                    
                    redirect('index.php?section_id=' . $question['section_id'], 'Question updated successfully!', 'success');
                } else {
                    $error_message = 'Failed to update question. Please try again.';
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Question edit error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-pencil me-2"></i>Edit Question
                </h1>
                <small class="text-muted">
                    Form: <?php echo htmlspecialchars($question['form_title']); ?> → 
                    Section: <?php echo htmlspecialchars($question['section_title']); ?>
                </small>
            </div>
            <a href="index.php?section_id=<?php echo $question['section_id']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Questions
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Question Details</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="questionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required
                                  placeholder="Enter the question text..."><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_description" class="form-label">Description/Instructions</label>
                        <textarea class="form-control" id="question_description" name="question_description" rows="2"
                                  placeholder="Optional: Additional instructions or clarification..."><?php echo htmlspecialchars($question['question_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="response_type" class="form-label">Response Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="response_type" name="response_type" required onchange="toggleOptions()">
                                    <option value="">Select response type...</option>
                                    <option value="text" <?php echo ($question['response_type'] == 'text') ? 'selected' : ''; ?>>Text (Single Line)</option>
                                    <option value="textarea" <?php echo ($question['response_type'] == 'textarea') ? 'selected' : ''; ?>>Textarea (Multi-line)</option>
                                    <option value="rating_5" <?php echo ($question['response_type'] == 'rating_5') ? 'selected' : ''; ?>>Rating Scale (1-5)</option>
                                    <option value="rating_10" <?php echo ($question['response_type'] == 'rating_10') ? 'selected' : ''; ?>>Rating Scale (0-10)</option>
                                    <option value="checkbox" <?php echo ($question['response_type'] == 'checkbox') ? 'selected' : ''; ?>>Multiple Choice (Checkboxes)</option>
                                    <option value="radio" <?php echo ($question['response_type'] == 'radio') ? 'selected' : ''; ?>>Single Choice (Radio)</option>
                                    <option value="display" <?php echo ($question['response_type'] == 'display') ? 'selected' : ''; ?>>Display Only</option>

                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="question_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="question_order" name="question_order" 
                                       min="1" value="<?php echo $question['question_order']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="options-container" style="display: none;">
                        <label for="options" class="form-label">Options <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="options" name="options" rows="5"
                                  placeholder="Enter each option on a new line:&#10;Option 1&#10;Option 2&#10;Option 3"><?php 
                                  if ($question['options']) {
                                      echo htmlspecialchars(implode("\n", json_decode($question['options'], true)));
                                  }
                                  ?></textarea>
                        <div class="form-text">Enter each option on a new line. These will be displayed as checkboxes or radio buttons.</div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_required" name="is_required" 
                                   <?php echo $question['is_required'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_required">
                                Required (users must answer this question)
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle me-2"></i>Question Type Guide:</h6>
                        <ul class="mb-0">
                            <li><strong>Text:</strong> Short answers (name, email, etc.)</li>
                            <li><strong>Textarea:</strong> Long responses (achievements, objectives)</li>
                            <li><strong>Rating 1-5:</strong> Simple satisfaction or agreement scale</li>
                            <li><strong>Rating 0-10:</strong> Performance assessment scale</li>
                            <li><strong>Checkboxes:</strong> Multiple selections allowed (training needs)</li>
                            <li><strong>Radio:</strong> Single selection only</li>
                            <li><strong>Display:</strong> Show info without input</li>

                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php?section_id=<?php echo $question['section_id']; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Update Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleOptions() {
    const responseType = document.getElementById('response_type').value;
    const optionsContainer = document.getElementById('options-container');
    const optionsField = document.getElementById('options');
    
    if (responseType === 'checkbox' || responseType === 'radio') {
        optionsContainer.style.display = 'block';
        optionsField.required = true;
    } else {
        optionsContainer.style.display = 'none';
        optionsField.required = false;
    }
}
if (responseType === 'display') {
    document.querySelector('.form-check').style.display = 'none';
    document.getElementById('is_required').checked = false;
} else {
    document.querySelector('.form-check').style.display = 'block';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleOptions();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>