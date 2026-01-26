
<?php
// employee/appraisal/continue.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get current appraisal
    $appraisal = new Appraisal($db);
    if (!$appraisal->getCurrentAppraisal($_SESSION['user_id'])) {
        redirect('start.php', 'No active appraisal found. Please start a new one.', 'warning');
    }
    
    // Check if already submitted
    if ($appraisal->status !== 'draft') {
        redirect('view.php?id=' . $appraisal->id, 'This appraisal has already been submitted.', 'info');
    }
    
    // Get form structure
    $form = new Form($db);
    $form->id = $appraisal->form_id;
    $form->readOne();
    $form_structure = $form->getFormStructure('employee');
    error_log("Original Form Structure: " . print_r($form_structure, true));

    /*  // Simple visibility check function
    function isSectionVisibleToEmployee($section_id, $db) {
        $query = "SELECT visible_to FROM form_sections WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$section_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $visible_to = $result['visible_to'] ?? 'both';
        return ($visible_to === 'both' || $visible_to === 'employee');
    } */
    // Filter sections by visibility (default to both if not set)
    $filtered_sections = [];
    foreach ($form_structure as $section) {
        $visible_to = strtolower($section['visible_to'] ?? 'both'); // fallback
        if ($visible_to === 'both' || $visible_to === 'employee') {
            $filtered_sections[] = $section;
        }
    }

    // If filtering removed everything (unlikely), fallback to full structure
    $form_structure = !empty($filtered_sections) ? $filtered_sections : $form_structure;


    // Get existing responses
    $existing_responses = [];
    $responses_stmt = $appraisal->getResponses();
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_responses[$response['question_id']] = $response;
    }
    
} catch (Exception $e) {
    error_log("Continue appraisal error: " . $e->getMessage());
    redirect('../index.php', 'An error occurred. Please try again.', 'error');
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST Data: " . print_r($_POST, true));

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('continue.php', 'Invalid request. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? 'save';
    $appraisal_id = $appraisal->id; // IMPORTANT: defined once
    $success = true;

    try {

         // Handle file uploads first
        $uploaded_files = [];
        if (!empty($_FILES)) {
            foreach ($_FILES as $field_name => $file) {
                if ($file['error'] === UPLOAD_ERR_OK && strpos($field_name, 'attachment_') === 0) {
                    $question_id = str_replace('attachment_', '', $field_name);
                    
                    // Validate file
                    $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'txt'];
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $file_size = $file['size'];
                    
                    if (!in_array($file_ext, $allowed_types)) {
                        throw new Exception("Invalid file type for attachment. Allowed: " . implode(', ', $allowed_types));
                    }
                    
                    if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                        throw new Exception("File size too large. Maximum 5MB allowed.");
                    }
                    
                    // Create uploads directory if it doesn't exist
                    $upload_dir = __DIR__ . '/../../uploads/appraisals/' . $appraisal->id . '/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'q' . $question_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $file['name']);
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $uploaded_files[$question_id] = 'uploads/appraisals/' . $appraisal->id . '/' . $filename;
                    } else {
                        throw new Exception("Failed to upload file: " . $file['name']);
                    }
                }
            }
        }
        // Save responses - UPDATED VERSION
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = str_replace('question_', '', $key);
                $rating_key = 'rating_' . $question_id;
                $comment_key = 'comment_' . $question_id;
                
                $response = is_array($value) ? implode(', ', $value) : $value;
                $rating = $_POST[$rating_key] ?? null;
                $comment = $_POST[$comment_key] ?? null;
                $attachment = $uploaded_files[$question_id] ?? null;
                
                $appraisal->saveResponseWithAttachment($question_id, $response, $rating, $comment, $attachment);
            }
            // Handle standalone rating fields
            elseif (strpos($key, 'rating_') === 0) {
                $question_id = str_replace('rating_', '', $key);
                if (!isset($_POST['question_' . $question_id])) {
                    $comment_key = 'comment_' . $question_id;
                    $comment = $_POST[$comment_key] ?? null;
                    $attachment = $uploaded_files[$question_id] ?? null;
                    $appraisal->saveResponseWithAttachment($question_id, null, $value, $comment, $attachment);
                }
            }
            // Handle standalone comment fields
            elseif (strpos($key, 'comment_') === 0) {
                $question_id = str_replace('comment_', '', $key);
                if (!isset($_POST['question_' . $question_id]) && !isset($_POST['rating_' . $question_id])) {
                    $attachment = $uploaded_files[$question_id] ?? null;
                    $appraisal->saveResponseWithAttachment($question_id, null, null, $value, $attachment);
                }
            }
        }
        
        // Handle attachment-only fields
        foreach ($uploaded_files as $question_id => $filepath) {
            if (!isset($_POST['question_' . $question_id]) && 
                !isset($_POST['rating_' . $question_id]) && 
                !isset($_POST['comment_' . $question_id])) {
                $appraisal->saveResponseWithAttachment($question_id, null, null, null, $filepath);
            }
        }

        // 5) If anything failed, throw to return error to user
        if (!$success) {
            throw new Exception('One or more saves failed. Check logs for details.');
        }

        // 6) Handle action
        if ($action === 'submit') {
            // Instead of submitting directly, redirect to confirmation page
            redirect('submit.php?id=' . $appraisal_id);
        } else {
            // Save progress
            logActivity($_SESSION['user_id'], 'UPDATE', 'appraisals', $appraisal_id, null, null, 'Saved appraisal progress');
            redirect('continue.php?id=' . $appraisal_id, 'Progress saved successfully!', 'success');
        }
    } catch (Exception $e) {
        error_log("Save appraisal error: " . $e->getMessage());
        redirect('continue.php', 'Failed to save. Please try again.', 'error');
    }
}

?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>My Appraisal
                </h1>
                <small class="text-muted">
                    Period: <?php echo formatDate($appraisal->appraisal_period_from); ?> - 
                    <?php echo formatDate($appraisal->appraisal_period_to); ?>
                </small>
            </div>
            <div>
                <span class="badge <?php echo getStatusBadgeClass($appraisal->status); ?> me-2">
                    <?php echo ucwords(str_replace('_', ' ', $appraisal->status)); ?>
                </span>
            </div>
        </div>
    </div>
</div>




<form method="POST" action="" id="appraisalForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <?php foreach ($form_structure as $section_index => $section): ?>
        <?php 
/*     // Check if section is visible to employee
    if (!isSectionVisibleToEmployee($section['id'], $db)) {
        continue; // Skip this section for employee
    } */
    ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-list-ul me-2"></i>
                Section <?php echo $section_index + 1; ?>: <?php echo htmlspecialchars($section['title']); ?>
            </h5>
            <?php if ($section['description']): ?>
            <small class="text-muted"><?php echo htmlspecialchars($section['description']); ?></small>
            <?php endif; ?>
        </div>
        <div class="card-body">
          <?php
// Replace the existing Cultural Values section with this:
if ($section['title'] === 'Cultural Values'): ?>
    <!-- Cultural Values section -->
    <div class="row">
        <?php foreach ($section['questions'] as $question): 
    // Initialize variables at the start of the loop
    $question_id = $question['id'];
    $existing_response = $existing_responses[$question_id] ?? [];
    $response_value = $existing_response['employee_response'] ?? '';
?>
    <div class="mb-4 question-item">
            <label class="form-label fw-bold">
                    <?php echo htmlspecialchars($question['text']); ?>
                    <?php if ($question['is_required']): ?><span class="text-danger">*</span><?php endif; ?>
                </label>
                
                  <?php if ($question['description']): ?>
                <div class="text-muted  mb-2">
                    <?php echo formatDescriptionAsBullets($question['description']); ?>
                </div>
                <?php endif; ?>
        </label>
        
        <?php if ($question['response_type'] === 'display'): ?>
                    <!-- Display only - no input fields -->
                    <!-- <div class="alert alert-info mb-2">
                        <i class="bi bi-info-circle me-2"></i>
                        <?php echo formatDescriptionAsBullets($question['text']); ?>
                    </div> -->
        <?php else: ?>
            <!-- Regular input fields -->
            <?php if ($question['response_type'] === 'textarea'): ?>
                <textarea class="form-control" 
                        name="question_<?php echo $question_id; ?>" 
                        rows="3"
                        <?php echo $question['is_required'] ? 'required' : ''; ?>
                ><?php echo htmlspecialchars($response_value); ?></textarea>
            <?php elseif ($question['response_type'] === 'text'): ?>
                <input type="text" 
                       class="form-control"
                       name="question_<?php echo $question_id; ?>"
                       value="<?php echo htmlspecialchars($response_value); ?>"
                       <?php echo $question['is_required'] ? 'required' : ''; ?>>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

    <!-- Overall Comments -->
   <!--  <?php
    // Find the overall comments question
    $overall_question = array_filter($section['questions'], function($q) {
        return $q['text'] === 'Overall Comments';
    });
    $overall_question = reset($overall_question);
    if ($overall_question):
        $existing_overall = $existing_responses[$overall_question['id']] ?? null;
    ?>
    <div class="mt-4">
        <label class="form-label"><strong>Overall Comments on Cultural Values</strong></label>
        <textarea class="form-control" 
                name="question_<?php echo $overall_question['id']; ?>" 
                rows="4"
                placeholder="Share your overall thoughts on how you demonstrate these cultural values..."
                <?php echo $overall_question['is_required'] ? 'required' : ''; ?>
        ><?php echo htmlspecialchars($existing_overall['employee_response'] ?? ''); ?></textarea>
    </div>
    <?php endif; ?> -->
<?php else: ?>
    <!-- Regular questions section remains the same -->
        
            <!-- Regular questions -->
            <?php foreach ($section['questions'] as $question): 
                $existing_response = $existing_responses[$question['id']] ?? null;
            ?>
            <div class="mb-4 question-item">
                <label class="form-label fw-bold">
                    <?php echo htmlspecialchars($question['text']); ?>
                    <?php if ($question['is_required']): ?><span class="text-danger">*</span><?php endif; ?>
                </label>
                
                  <?php if ($question['description']): ?>
                <div class="text-muted  mb-2">
                    <?php echo formatDescriptionAsBullets($question['description']); ?>
                </div>
                <?php endif; ?>
                
                

                <?php switch ($question['response_type']): 
                    case 'text': ?>
                        <input type="text" class="form-control" name="question_<?php echo $question['id']; ?>"
                               value="<?php echo htmlspecialchars($existing_response['employee_response'] ?? ''); ?>"
                               <?php echo $question['is_required'] ? 'required' : ''; ?>>
                    <?php break;
                    
                    case 'textarea': ?>
                        <textarea class="form-control" name="question_<?php echo $question['id']; ?>" rows="4"
                                  <?php echo $question['is_required'] ? 'required' : ''; ?>><?php echo htmlspecialchars($existing_response['employee_response'] ?? ''); ?></textarea>
                    <?php break;
                    
                    case 'rating_5': ?>
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <input type="range" class="form-range" min="1" max="5" step="1"
                                       name="rating_<?php echo $question['id']; ?>" 
                                       id="rating_<?php echo $question['id']; ?>"
                                       value="<?php echo $existing_response['employee_rating'] ?? 3; ?>"
                                       oninput="updateRatingValue(this, 'rating_display_<?php echo $question['id']; ?>')">
                            </div>
                            <div class="col-md-4">
                                <span class="badge bg-primary fs-6" id="rating_display_<?php echo $question['id']; ?>">
                                    <?php echo $existing_response['employee_rating'] ?? 3; ?>
                                </span>
                                <small class="text-muted d-block">1=Poor, 5=Excellent</small>
                            </div>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Comments (optional)..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    
                    /* case 'rating_10': ?>
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <input type="range" class="form-range" min="0" max="10" step="1"
                                       name="rating_<?php echo $question['id']; ?>" 
                                       id="rating_<?php echo $question['id']; ?>"
                                       value="<?php echo $existing_response['employee_rating'] ?? 5; ?>"
                                       oninput="updateRatingValue(this, 'rating_display_<?php echo $question['id']; ?>')">
                            </div>
                            <div class="col-md-4">
                                <span class="badge bg-primary fs-6" id="rating_display_<?php echo $question['id']; ?>">
                                    <?php echo $existing_response['employee_rating'] ?? 5; ?>
                                </span>
                                <small class="text-muted d-block">0-10 Scale</small>
                            </div>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Comments (optional)..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break; */

                     case 'attachment': ?>
                        <div class="mb-3">
                            <input type="file" class="form-control" name="attachment_<?php echo $question['id']; ?>" 
                                   id="attachment_<?php echo $question['id']; ?>"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx,.txt">
                            <div class="form-text">
                                Allowed formats: PDF, Word, Excel, Images, Text files (Max: 5MB)
                            </div>
                            <?php if (!empty($existing_response['employee_attachment'])): ?>
                            <div class="mt-2">
                                <small class="text-success">
                                    <i class="bi bi-paperclip me-1"></i>
                                    Current file: <?php echo htmlspecialchars(basename($existing_response['employee_attachment'])); ?>
                                    <a href="download.php?file=<?php echo urlencode($existing_response['employee_attachment']); ?>&type=employee" 
                                       class="text-primary ms-2" target="_blank">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Optional comments about the attachment..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;

                    case 'rating_10': ?>
                        <select class="form-select" name="rating_<?php echo $question['id']; ?>">
                            <option value="">Select rating...</option>
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($existing_response['employee_rating'] ?? '') == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> - <?php 
                                if ($i == 0) echo 'Not Applicable';
                                elseif ($i <= 2) echo 'Below standard: Below job requirements and significant improvement is needed';
                                elseif ($i <= 4) echo 'Need Improvement: Improvement is needed to meet job requirements';
                                elseif ($i <= 6) echo 'Satisfactory: Satisfactorily met job requirements';
                                elseif ($i <= 8) echo 'Good: Exceeded job requirements';
                                else echo 'Excellent: Far exceeds job requirements';
                                ?>
                            </option>
                            <?php endfor; ?>
                        </select>   
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Comments (optional)..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    case 'display': ?>
                           <!-- Display only - no input needed -->
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <?php echo nl2br(htmlspecialchars($question['text'])); ?>
                        </div>
                        <?php if ($question['description']): ?>
                            <div class="text-muted small">
                                <?php echo formatDescriptionAsBullets($question['description']); ?>
                            </div>
                        <?php endif; ?>
                    <?php break;

                    case 'checkbox': 
                        $options = $question['options'] ?? [];
                        $selected = explode(', ', $existing_response['employee_response'] ?? '');
                    ?>
                        <div class="row">
                            <?php foreach ($options as $option): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="question_<?php echo $question['id']; ?>[]" 
                                           value="<?php echo htmlspecialchars($option); ?>"
                                           <?php echo in_array($option, $selected) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Additional comments or specify others..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    
                endswitch; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Total Score Display -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="bi bi-calculator me-2"></i>Performance Score Summary</h6>
                    <div id="score-summary">
                        <div class="mb-2">
                            <strong>Performance Assesment Question Answered:</strong> 
                            <span id="answered-count">0</span> / <span id="total-questions">0</span>
                        </div>
                        <div class="mb-2">
                            <strong>Average Score:</strong> 
                            <span id="average-score" class="badge bg-primary fs-6">0.0</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6><i class="bi bi-info-circle me-2"></i>Rating Guide</h6>
                    <small class="text-muted">
<!--                         <strong>5-Point Scale:</strong> 1=Poor, 2=Below Average, 3=Average, 4=Good, 5=Excellent<br>
 -->                        <strong>Performance Score:</strong> 0=N/A, <49=C, 50~59=B-, 60~74=B, 75~84=B+, >85=A<br>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Save Progress:</strong> Your progress is automatically saved. You can return anytime to continue.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Submit:</strong> Once submitted, you cannot make further changes until your manager's review.
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" name="action" value="save" class="btn btn-outline-primary">
                    <i class="bi bi-save me-2"></i>Save Progress
                </button>
                <button type="submit" name="action" value="submit" class="btn btn-success"
                        onclick="return confirm('Are you sure you want to submit this appraisal? You will not be able to make changes after submission.')">
                    <i class="bi bi-send me-2"></i>Submit for Review
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// Initialize rating displays
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="range"]').forEach(function(slider) {
        const displayId = slider.getAttribute('oninput').match(/'([^']+)'/)[1];
        updateRatingValue(slider, displayId);
    });
    
    // Initialize score calculator
    updateScoreCalculator();
});

// Calculate performance scores
function updateScoreCalculator() {
    let totalQuestions = 0;
    let answeredQuestions = 0;
    let totalScore = 0;
    
   /*  // Count rating_5 questions
    document.querySelectorAll('input[name^="rating_"][type="range"][max="5"]').forEach(function(input) {
        totalQuestions++;
        const value = parseInt(input.value) || 0;
        if (value > 0) {
            answeredQuestions++;
            totalScore += value;
        }
    }); */
    
    // Count rating_10 questions
    document.querySelectorAll('select[name^="rating_"]').forEach(function(select) {
        totalQuestions++;
        const value = parseInt(select.value) || 0;
        if (value > 0) {
            answeredQuestions++;
            // Convert 10-point to 5-point scale for average calculation
           // totalScore += (value / 2);
           totalScore += value; // Keep as is for 10-point average
        }
    });
    
    // Calculate average
    const average = answeredQuestions > 0 ? (totalScore / answeredQuestions * 10).toFixed(1) : 0;
    
    // Update display
    document.getElementById('answered-count').textContent = answeredQuestions;
    document.getElementById('total-questions').textContent = totalQuestions;
    document.getElementById('average-score').textContent = average;
    
    // Update badge color based on average
    const avgBadge = document.getElementById('average-score');
    if (average >= 4.0) {
        avgBadge.className = 'badge bg-success fs-6';
    } else if (average >= 3.0) {
        avgBadge.className = 'badge bg-warning fs-6';
    } else {
        avgBadge.className = 'badge bg-danger fs-6';
    }
}

// Add event listeners for score updates
document.addEventListener('DOMContentLoaded', function() {
    // For range inputs
    document.querySelectorAll('input[type="range"]').forEach(function(input) {
        input.addEventListener('input', updateScoreCalculator);
    });
    
    // For select dropdowns
    document.querySelectorAll('select[name^="rating_"]').forEach(function(select) {
        select.addEventListener('change', updateScoreCalculator);
    });
});

// Form validation
document.getElementById('appraisalForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let hasError = false;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            hasError = true;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (hasError) {
        e.preventDefault();
        alert('Please fill in all required fields');
    }
});

// Auto-save functionality
let autoSaveTimer;
document.getElementById('appraisalForm').addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        const formData = new FormData(document.getElementById('appraisalForm'));
        formData.set('action', 'save');
        
        fetch('continue.php', {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                console.log('Auto-saved successfully');
                showAutoSaveIndicator();
            }
        }).catch(error => {
            console.error('Auto-save failed:', error);
        });
    }, 30000);
});

function showAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'alert alert-success position-fixed';
    indicator.style.cssText = 'top: 80px; right: 20px; z-index: 9999; opacity: 0.9; font-size: 0.875rem; padding: 0.5rem 1rem;';
    indicator.innerHTML = '<i class="bi bi-check-circle me-2"></i>Auto-saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        if (indicator.parentNode) {
            indicator.parentNode.removeChild(indicator);
        }
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
