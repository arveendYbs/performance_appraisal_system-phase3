
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
    $form_structure = $form->getFormStructure();
    
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
        // 1) Save section comments first (section_comment_{id})
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'section_comment_') === 0) {
                $section_id = (int) str_replace('section_comment_', '', $key);
                if (!$appraisal->saveSectionComment($section_id, $value)) {
                    error_log("Failed to save section comment for section_id={$section_id}: " . print_r($value, true));
                    $success = false;
                }
            }
        }

        // 2) Save employee responses (question_{id}) and associated rating/comment fields
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = (int) str_replace('question_', '', $key);
                $response = is_array($value) ? implode(', ', $value) : $value;
                $employee_rating = $_POST['rating_' . $question_id] ?? null;
                $employee_comment = $_POST['comment_' . $question_id] ?? null;

                // Your Appraisal::saveResponse expects:
                // saveResponse($question_id, $employee_response = null, $employee_rating = null, $employee_comments = null,
                //              $manager_response = null, $manager_rating = null, $manager_comments = null)
                if (!$appraisal->saveResponse(
                    $question_id,
                    $response,
                    $employee_rating,
                    $employee_comment,
                    null,
                    null,
                    null
                )) {
                    error_log("Failed to save employee response for question_id={$question_id}");
                    $success = false;
                }
            }
        }

        // 3) Handle standalone rating_ or comment_ fields for questions that don't post 'question_' (if any)
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'rating_') === 0) {
                $question_id = (int) str_replace('rating_', '', $key);
                // if question_ was not posted we save rating only
                if (!isset($_POST['question_' . $question_id])) {
                    $employee_comment = $_POST['comment_' . $question_id] ?? null;
                    if (!$appraisal->saveResponse($question_id, null, $value, $employee_comment, null, null, null)) {
                        error_log("Failed to save standalone rating for question_id={$question_id}");
                        $success = false;
                    }
                }
            } elseif (strpos($key, 'comment_') === 0) {
                $question_id = (int) str_replace('comment_', '', $key);
                if (!isset($_POST['question_' . $question_id]) && !isset($_POST['rating_' . $question_id])) {
                    if (!$appraisal->saveResponse($question_id, null, null, $value, null, null, null)) {
                        error_log("Failed to save standalone comment for question_id={$question_id}");
                        $success = false;
                    }
                }
            }
        }

        // 4) Save manager inputs (if this page is used by managers or manager fields posted)
        // manager_rating_{id} and manager_comment_{id}
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'manager_rating_') === 0) {
                $question_id = (int) str_replace('manager_rating_', '', $key);
                $mgr_rating = !empty($value) ? intval($value) : null;
                $mgr_comment = $_POST['manager_comment_' . $question_id] ?? null;

                // Use saveResponse to set manager columns (pass manager params in the later args)
                if (!$appraisal->saveResponse(
                    $question_id,
                    null,  // employee_response
                    null,  // employee_rating
                    null,  // employee_comments
                    null,  // manager_response
                    $mgr_rating,
                    $mgr_comment
                )) {
                    error_log("Failed to save manager rating/comment for question_id={$question_id}");
                    $success = false;
                }
            }
        }

        // 5) If anything failed, throw to return error to user
        if (!$success) {
            throw new Exception('One or more saves failed. Check logs for details.');
        }

        // 6) Update status if submitting
        if ($action === 'submit') {
            if (!$appraisal->updateStatus('submitted')) {
                throw new Exception('Failed to update status to submitted.');
            }
            logActivity($_SESSION['user_id'], 'SUBMIT', 'appraisals', $appraisal_id, null, null, 'Submitted appraisal for review');
            redirect('../index.php', 'Appraisal submitted successfully! Your manager will be notified.', 'success');
        } else {
            logActivity($_SESSION['user_id'], 'UPDATE', 'appraisals', $appraisal_id, null, null, 'Saved appraisal progress');
            redirect('continue.php', 'Progress saved successfully!', 'success');
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




<form method="POST" action="" id="appraisalForm">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <?php foreach ($form_structure as $section_index => $section): ?>
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
            <?php if ($section['title'] === 'Cultural Values'): ?>
            <!-- Special handling for Cultural Values section -->
            <div class="row">
                <?php
             
               $cultural_values = [
                    ['code' => 'H', 'title' => 'Hard Work', 'desc' => 'Commitment to diligence and perseverance in all aspects of Operations'],
                    ['code' => 'H', 'title' => 'Honesty', 'desc' => 'Integrity in dealings with customers, partners and stakeholders'],
                    ['code' => 'H', 'title' => 'Harmony', 'desc' => 'Fostering Collaborative relationships and a balanced work environment'],
                    ['code' => 'C', 'title' => 'Customer Focus', 'desc' => 'Striving to be the "Only Supplier of Choice" by enhancing customer competitiveness'],
                    ['code' => 'I', 'title' => 'Innovation', 'desc' => 'Embracing transformation and agility, as symbolized by their "Evolving with Momentum" theme'],
                    ['code' => 'S', 'title' => 'Sustainability', 'desc' => 'Rooted in organic growth and long-term value creation, reflected in their visual metaphors']
                ];
                
                foreach ($cultural_values as $cv_index => $cv): 
                    $question_id = $section['questions'][$cv_index]['id'] ?? null;
                    $existing_response = $existing_responses[$question_id] ?? null;
                ?>
                <div class="col-md-6 mb-4">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary me-2" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                <?php echo $cv['code']; ?>
                            </span>
                            <strong><?php echo $cv['title']; ?></strong>
                        </div>
                        <p class="small text-muted mb-3"><?php echo $cv['desc']; ?></p>
                        <?php if ($question_id): ?>
                        <textarea class="form-control" name="comment_<?php echo $question_id; ?>" rows="3"
                                  placeholder="Share your thoughts and examples on this cultural value..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            

            <div class="mt-4">
                <label class="form-label"><strong>Overall Comments on Cultural Values</strong></label>
                <textarea class="form-control" 
                        name="cultural_values_overall" 
                        rows="4"
                        placeholder="Share your overall thoughts on how you demonstrate these cultural values..."
                ><?php 
                    $cultural_overall = array_filter($existing_responses, function($resp) {
                        return isset($resp['employee_comments']) && 
                            $resp['employee_comments'] === 'Cultural Values Overall Comments';
                    });
                    echo htmlspecialchars(reset($cultural_overall)['employee_response'] ?? '');
                ?></textarea>
            </div>
            
            <?php else: ?>
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
                <div class="text-muted small mb-2"><?php echo htmlspecialchars($question['description']); ?></div>
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

                    case 'rating_10': ?>
                        <select class="form-select" name="rating_<?php echo $question['id']; ?>">
                            <option value="">Select rating...</option>
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($existing_response['employee_rating'] ?? '') == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> - <?php 
                                if ($i == 0) echo 'Not Applicable';
                                elseif ($i <= 2) echo 'Poor';
                                elseif ($i <= 4) echo 'Below Average';
                                elseif ($i <= 6) echo 'Average';
                                elseif ($i <= 8) echo 'Good';
                                else echo 'Excellent';
                                ?>
                            </option>
                            <?php endfor; ?>
                        </select>   
                        <textarea class="form-control mt-2" name="comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Comments (optional)..."><?php echo htmlspecialchars($existing_response['employee_comments'] ?? ''); ?></textarea>
                    <?php break;
                    case 'display': ?>
                        <div class="alert alert-secondary">
                            <?php echo nl2br(htmlspecialchars($question['text'])); ?>
                        </div>
                        <?php if ($question['description']): ?>
                            <div class="text-muted small mb-2">
                                <?php echo nl2br(htmlspecialchars($question['description'])); ?>
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
});
// Add to your existing JavaScript
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
        // Create a hidden form to submit for auto-save
        const formData = new FormData(document.getElementById('appraisalForm'));
        formData.set('action', 'save');
        
        fetch('continue.php', {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                console.log('Auto-saved successfully');
                // Show a small indicator
                showAutoSaveIndicator();
            }
        }).catch(error => {
            console.error('Auto-save failed:', error);
        });
    }, 30000); // Auto-save after 30 seconds of inactivity
});

function showAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'alert alert-success position-fixed';
    indicator.style.cssText = 'top: 80px; right: 20px; z-index: 9999; opacity: 0.9;';
    indicator.innerHTML = '<i class="bi bi-check-circle me-2"></i>Auto-saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
