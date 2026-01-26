<?php
// manager/review/review.php
require_once __DIR__ . '/../../config/config.php';

/* if (!hasRole('manager') && !hasRole('admin')) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
} */

// Check if user can access team features (includes dept managers)
if (!canAccessTeamFeatures()) {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}


$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('pending.php', 'Appraisal ID is required.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get appraisal details and verify it belongs to manager's team
    $query = "SELECT a.*, u.name as employee_name, u.position, u.emp_number, f.title as form_title
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE a.id = ? AND u.direct_superior = ? 
              AND a.status IN ('submitted', 'in_review')";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$appraisal_id, $_SESSION['user_id']]);
    $appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appraisal_data) {
        redirect('pending.php', 'Appraisal not found or not available for review.', 'error');
    }
    
    // Update status to in_review if it's still submitted
    if ($appraisal_data['status'] === 'submitted') {
        $update_query = "UPDATE appraisals SET status = 'in_review' WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$appraisal_id]);
        $appraisal_data['status'] = 'in_review';
    }
    
    // Get form structure
    $form = new Form($db);
    $form->id = $appraisal_data['form_id'];
    $form_structure = $form->getFormStructure('reviewer');


     /* // Simple visibility check function for manager
    function isSectionVisibleToManager($section_id, $db) {
        $query = "SELECT visible_to FROM form_sections WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$section_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $visible_to = $result['visible_to'] ?? 'both';
        return ($visible_to === 'both' || $visible_to === 'reviewer');
    }
     */
    // Get existing responses
    $appraisal = new Appraisal($db);
    $appraisal->id = $appraisal_id;
    $responses_stmt = $appraisal->getAllResponsesForReview(); //getResponse
    
    $responses = [];
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $responses[$response['question_id']] = $response;
    }
    // Get existing responses - FIXED VERSION
    $appraisal = new Appraisal($db);
    $appraisal->id = $appraisal_id;
    $responses_stmt = $appraisal->getAllResponsesForReview(); // Use the new method
    
    $responses = [];
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $responses[$response['question_id']] = $response;
    }



} catch (Exception $e) {
    error_log("Review appraisal error: " . $e->getMessage());
    redirect('pending.php', 'An error occurred. Please try again.', 'error');
}


    // Get employee's confirmation status
    $emp_query = "SELECT is_confirmed FROM users WHERE id = ?";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute([$appraisal_data['user_id']]);
    $employee_data = $emp_stmt->fetch(PDO::FETCH_ASSOC);
    $is_employee_confirmed = $employee_data['is_confirmed'] ?? false;

        // Filter out probation sections for confirmed employees
    $filtered_form_structure = [];
    foreach ($form_structure as $section) {
        // Skip reviewer-only sections (probation) if employee is confirmed
        if ($section['visible_to'] === 'reviewer' && $is_employee_confirmed) {
            continue; // Skip this section
        }
        $filtered_form_structure[] = $section;
    }
        $form_structure = $filtered_form_structure;
// Handle form submission for manager feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect('review.php?id=' . $appraisal_id, 'Invalid request. Please try again.', 'error');
    }
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
                    $upload_dir = __DIR__ . '/../../uploads/appraisals/' . $appraisal_id . '/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'q' . $question_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $file['name']);
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $uploaded_files[$question_id] = 'uploads/appraisals/' . $appraisal_id . '/' . $filename;
                    } else {
                        throw new Exception("Failed to upload file: " . $file['name']);
                    }
                }
            }
        }

    
        // Save manager responses with attachments
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'manager_rating_') === 0) {
                $question_id = str_replace('manager_rating_', '', $key);
                $rating = !empty($value) ? intval($value) : null;
                $comment_key = 'manager_comment_' . $question_id;
                $comment = $_POST[$comment_key] ?? null;
                $attachment = $uploaded_files[$question_id] ?? null;

                // Handle checkbox arrays
                if (is_array($value)) {
                    $response_value = implode(', ', $value);
                } else {
                    $response_value = sanitize($value);
                }

                  // Get associated comment if exists
        $comment = sanitize($_POST['manager_comment_' . $question_id] ?? '');
        $attachment = $uploaded_files[$question_id] ?? null;

                // Check if response exists
                $check_query = "SELECT * FROM responses WHERE appraisal_id = ? AND question_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$appraisal_id, $question_id]);
                $existing = $check_stmt->fetch();
                
                if ($existing) {
                    // Update existing
                    $update_fields = [];
                    $update_values = [];
                    
                    if ($rating !== null) {
                        $update_fields[] = "manager_rating = ?";
                        $update_values[] = $rating;
                    }
                    if ($comment !== null) {
                        $update_fields[] = "manager_comments = ?";
                        $update_values[] = $comment;
                    }
                    if ($attachment !== null) {
                        $update_fields[] = "manager_attachment = ?";
                        $update_values[] = $attachment;
                    }
                    
                    if (!empty($update_fields)) {
                        $query = "UPDATE responses SET " . implode(', ', $update_fields) . " WHERE appraisal_id = ? AND question_id = ?";
                        $update_values[] = $appraisal_id;
                        $update_values[] = $question_id;
                        $stmt = $db->prepare($query);
                        $stmt->execute($update_values);
                    }
                } else {
                    // Insert new
                    $query = "INSERT INTO responses (appraisal_id, question_id, manager_rating, manager_comments, manager_attachment) 
                             VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$appraisal_id, $question_id, $rating, $comment, $attachment]);
                }
            }

             // Handle manager_response_ fields (for reviewer-only sections like "Only Applicable to New Joiner")
            elseif (strpos($key, 'manager_response_') === 0) {
                $question_id = str_replace('manager_response_', '', $key);
                
                // Handle checkbox arrays
                if (is_array($value)) {
                    $response_value = implode(', ', array_map('sanitize', $value));
                } else {
                    $response_value = sanitize($value);
                }
                
                // Get associated comment if exists
                $comment = sanitize($_POST['manager_comment_' . $question_id] ?? '');
                $attachment = $uploaded_files[$question_id] ?? null;
                
                // Only save if there's actual data
                if (!empty($response_value) || !empty($comment) || $attachment) {
                    // Check if response exists
                    $check_query = "SELECT * FROM responses WHERE appraisal_id = ? AND question_id = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$appraisal_id, $question_id]);
                    $existing = $check_stmt->fetch();
                    
                    if ($existing) {
                        // Update existing response
                        $update_fields = [];
                        $update_values = [];
                        
                        if (!empty($response_value)) {
                            $update_fields[] = "manager_response = ?";
                            $update_values[] = $response_value;
                        }
                        if (!empty($comment)) {
                            $update_fields[] = "manager_comments = ?";
                            $update_values[] = $comment;
                        }
                        if ($attachment) {
                            $update_fields[] = "manager_attachment = ?";
                            $update_values[] = $attachment;
                        }
                        
                        if (!empty($update_fields)) {
                            $query = "UPDATE responses SET " . implode(', ', $update_fields) . " WHERE appraisal_id = ? AND question_id = ?";
                            $update_values[] = $appraisal_id;
                            $update_values[] = $question_id;
                            $stmt = $db->prepare($query);
                            $stmt->execute($update_values);
                        }
                    } else {
                        // Insert new response
                        $query = "INSERT INTO responses (appraisal_id, question_id, manager_response, manager_comments, manager_attachment) 
                                 VALUES (?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$appraisal_id, $question_id, $response_value, $comment, $attachment]);
                    }
                }
            }
            // Handle standalone manager comments
            elseif (strpos($key, 'manager_comment_') === 0 && 
                    !isset($_POST[str_replace('manager_comment_', 'manager_rating_', $key)])) {
                $question_id = str_replace('manager_comment_', '', $key);
                $comment = $value;
                $attachment = $uploaded_files[$question_id] ?? null;
                
                if (!empty($comment) || $attachment) {
                    $check_query = "SELECT * FROM responses WHERE appraisal_id = ? AND question_id = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$appraisal_id, $question_id]);
                    $existing = $check_stmt->fetch();
                    
                    if ($existing) {
                        $update_fields = [];
                        $update_values = [];
                        
                        if (!empty($comment)) {
                            $update_fields[] = "manager_comments = ?";
                            $update_values[] = $comment;
                        }
                        if ($attachment) {
                            $update_fields[] = "manager_attachment = ?";
                            $update_values[] = $attachment;
                        }
                        
                        if (!empty($update_fields)) {
                            $query = "UPDATE responses SET " . implode(', ', $update_fields) . " WHERE appraisal_id = ? AND question_id = ?";
                            $update_values[] = $appraisal_id;
                            $update_values[] = $question_id;
                            $stmt = $db->prepare($query);
                            $stmt->execute($update_values);
                        }
                    } else {
                        $query = "INSERT INTO responses (appraisal_id, question_id, manager_comments, manager_attachment) 
                                 VALUES (?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$appraisal_id, $question_id, $comment, $attachment]);
                    }
                }
            }
        }
        
       
        
        logActivity($_SESSION['user_id'], 'UPDATE', 'appraisals', $appraisal_id, null, null, 
                   'Updated manager review for ' . $appraisal_data['employee_name']);
        
        redirect('review.php?id=' . $appraisal_id, 'Review progress saved successfully!', 'success');
        
    } catch (Exception $e) {
        error_log("Save manager review error: " . $e->getMessage());
        redirect('review.php?id=' . $appraisal_id, 'Failed to save review. Please try again.', 'error');
    }
    

}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-clipboard-check me-2"></i>Review Appraisal
                </h1>
                <small class="text-muted">
                    Employee: <?php echo htmlspecialchars($appraisal_data['employee_name']); ?> 
                    (<?php echo htmlspecialchars($appraisal_data['position']); ?>)
                </small>
            </div>
            <div>
                <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?> me-2">
                    <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                </span>
                <a href="pending.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Pending
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Appraisal Summary -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h6>Employee Details</h6>
                <p>
                    <strong><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></small><br>
                    <?php echo htmlspecialchars($appraisal_data['position']); ?>
                </p>
            </div>
            <div class="col-md-4">
                <h6>Appraisal Period</h6>
                <p>
                    <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - 
                    <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                </p>
            </div>
            <div class="col-md-4">
                <h6>Form Type</h6>
                <p><?php echo htmlspecialchars($appraisal_data['form_title']); ?></p>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="" id="reviewForm">
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
    <!-- Cultural Values Review -->
    <div class="row">
        <?php 
        $overall_question = null;
        $overall_response = null;
        
        foreach ($section['questions'] as $question): 
            $question_id = $question['id'];
            $response = $responses[$question_id] ?? [];
            
            // Store overall comments question for later processing
            if (stripos($question['text'], 'Overall Comments') !== false || 
                stripos($question['text'], 'Overall Comment') !== false) {
                $overall_question = $question;
                $overall_response = $response;
                continue; // Skip for now, handle at the end
            }
        ?>
        <div class="mb-4 pb-4 border-bottom">
            <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>
            
            <?php if ($question['description']): ?>
            <p class="text-muted small mb-3"><?php echo formatDescriptionAsBullets($question['description']); ?></p>
            <?php endif; ?>
                   
            <?php if ($question['response_type'] === 'display'): ?>
                <!-- Display questions - show only title and description -->
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <?php echo nl2br(htmlspecialchars($question['text'])); ?>
                    <?php if ($question['description']): ?>
                        <div class="mt-2">
                            <?php echo formatDescriptionAsBullets($question['description']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Manager Feedback for Cultural Values (No duplication of content) -->
                <div class="mt-3">
                    <label class="form-label fw-bold">Manager Feedback</label>
                    <textarea class="form-control" 
                            name="manager_comment_<?php echo $question_id; ?>" 
                            rows="3"
                            placeholder="Provide your feedback on this cultural value..."
                    ><?php echo htmlspecialchars($response['manager_comments'] ?? ''); ?></textarea>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <!-- Handle Cultural Values Overall Comments (Show employee response + manager feedback) -->
        <?php if ($overall_question): ?>
        <div class="mt-4">
            <h6><strong>Overall Comments on Cultural Values</strong></h6>
            
            <div class="row">
                
                    <label class="form-label text-primary">Employee's Overall Comments:</label>
                    <div class="bg-light p-3 rounded">
                        <?php if (!empty($overall_response['employee_response'])): ?>
                            <?php echo nl2br(htmlspecialchars($overall_response['employee_response'])); ?>
                        <?php elseif (!empty($overall_response['employee_comments'])): ?>
                            <?php echo nl2br(htmlspecialchars($overall_response['employee_comments'])); ?>
                        <?php else: ?>
                            <em class="text-muted">No overall comments provided</em>
                        <?php endif; ?>
                    </div>
                
        
                    <label class="form-label text-success">Your Feedback on Overall Cultural Values:</label>
                    <textarea class="form-control" 
                            name="manager_comment_<?php echo $overall_question['id']; ?>" 
                            rows="4"
                            placeholder="Provide your overall feedback on the employee's demonstration of cultural values..."
                    ><?php echo htmlspecialchars($overall_response['manager_comments'] ?? ''); ?></textarea>
              
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php else: ?>
            <!-- Performance Assessment and Other Sections -->
        <!-- Performance Assessment and Other Sections -->
            <?php foreach ($section['questions'] as $question): 
                $response = $responses[$question['id']] ?? null;
            ?>
            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>
                
                <?php if ($question['description']): ?>
                <p class="text-muted small mb-3"><?php echo formatDescriptionAsBullets($question['description']); ?></p>
                <?php endif; ?>
                
                <?php if ($question['response_type'] === 'display'): ?>
                    <!-- Display questions - show only title and description -->
                        <?php echo nl2br(htmlspecialchars($question['text'])); ?>
                        <?php if ($question['description']): ?>
                            <div class="mt-2">
                                <?php echo formatDescriptionAsBullets($question['description']); ?>
                            </div>
                        <?php endif; ?>
                    
                <?php elseif ($section['visible_to'] === 'reviewer'): ?>
                    <!-- Reviewer-only sections - Manager answers the questions directly -->
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-person-check me-2"></i>
                        <strong>Reviewer Assessment Required:</strong> This question is for your assessment only.
                    </div>
                    
                    <?php if ($question['response_type'] === 'checkbox'): ?>
                        <!-- Checkbox for manager to select -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your Selection:</label>
                            <?php 
                            $options = $question['options'] ?? [];
                            $selected = [];
                            if ($response && $response['manager_response']) {
                                $selected = explode(', ', $response['manager_response']);
                            }
                            ?>
                            <div class="row">
                                <?php foreach ($options as $option): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="manager_response_<?php echo $question['id']; ?>[]" 
                                               value="<?php echo htmlspecialchars($option); ?>"
                                               <?php echo in_array($option, $selected) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">
                                            <?php echo htmlspecialchars($option); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif ($question['response_type'] === 'radio'): ?>
                        <!-- Radio buttons for manager to select -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your Selection:</label>
                            <?php 
                            $options = $question['options'] ?? [];
                            $selected = $response['manager_response'] ?? '';
                            ?>
                            <?php foreach ($options as $option): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" 
                                       name="manager_response_<?php echo $question['id']; ?>" 
                                       value="<?php echo htmlspecialchars($option); ?>"
                                       <?php echo $selected === $option ? 'checked' : ''; ?>>
                                <label class="form-check-label">
                                    <?php echo htmlspecialchars($option); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['response_type'] === 'textarea'): ?>
                        <!-- Checkbox for manager to select -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Your Selection:</label>
                        <?php 
                        $options = $question['options'] ?? [];
                        $selected = [];
                        if ($response && $response['manager_response']) {
                            $selected = explode(', ', $response['manager_response']);
                        }
                        ?>
                        <div class="row">
                            <?php foreach ($options as $option): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                        name="manager_response_<?php echo $question['id']; ?>[]" 
                                        value="<?php echo htmlspecialchars($option); ?>"
                                        <?php echo in_array($option, $selected) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($question['response_type'] === 'textarea'): ?>
                        <!-- Textarea for manager to fill -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your Response:</label>
                            <textarea class="form-control" name="manager_response_<?php echo $question['id']; ?>" rows="4"
                                      placeholder="Provide your response..."><?php echo htmlspecialchars($response['manager_response'] ?? ''); ?></textarea>
                        </div>
                    <?php elseif ($question['response_type'] === 'text'): ?>
                        <!-- Text input for manager -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your Response:</label>
                            <input type="text" class="form-control" name="manager_response_<?php echo $question['id']; ?>"
                                   value="<?php echo htmlspecialchars($response['manager_response'] ?? ''); ?>"
                                   placeholder="Enter your response...">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Additional comments for reviewer-only questions -->
                    <div class="mt-3">
                        <label class="form-label">Additional Comments (Optional):</label>
                        <textarea class="form-control" name="manager_comment_<?php echo $question['id']; ?>" rows="2"
                                  placeholder="Add any additional comments..."><?php echo htmlspecialchars($response['manager_comments'] ?? ''); ?></textarea>
                    </div>
                    
                <?php else: ?>
                    <!-- Regular questions with employee/manager response -->
                    <div class="review-layout">
                        <div class="employee-column">
                            <div class="column-header">Employee Response:</div>
                            
                            <?php // Show employee rating if it exists ?>
                            <?php if (in_array($question['response_type'], ['rating_5', 'rating_10']) && 
                                      isset($response['employee_rating']) && $response['employee_rating'] !== null): ?>
                            <div class="mb-3 p-2 bg-info bg-opacity-10 rounded border-start border-info border-3">
                                <strong>Employee Score: </strong>
                                <span class="badge bg-info fs-6"><?php echo $response['employee_rating']; ?></span>
                                <input type="hidden" class="employee-rating" value="<?php echo intval($response['employee_rating']); ?>">

                                <?php 
                                $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                                $percentage = round(($response['employee_rating'] / $max_rating) * 100);
                                ?>
                                <span class="text-muted">/ <?php echo $max_rating; ?> (<?php echo $percentage; ?>%)</span>
                                
                                <?php // Show rating description ?>
                                <div class="small text-muted mt-1">
                                    <?php
                                    if ($question['response_type'] === 'rating_5') {
                                        $descriptions = [1 => 'Poor', 2 => 'Below Average', 3 => 'Average', 4 => 'Good', 5 => 'Excellent'];
                                        echo $descriptions[$response['employee_rating']] ?? '';
                                    } else {
                                        if ($response['employee_rating'] == 0) echo 'Below Standard: Below job requirements and significant improvement is needed';
                                        elseif ($response['employee_rating'] <= 2) echo 'Below Standard: Below job requirements and significant improvement is needed';
                                        elseif ($response['employee_rating'] <= 4) echo 'Need Improvement: Improvement is needed to meet job requirements';
                                        elseif ($response['employee_rating'] <= 6) echo 'Satisfactory: Satisfactorily met job requirements';
                                        elseif ($response['employee_rating'] <= 8) echo 'Good: Exceeded job requirements';
                                        else echo 'Excellent: Far exceeded job requirements';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php // Show employee attachment if exists ../../employee/appraisal/download.php ?>
                            <?php if ($question['response_type'] === 'attachment'): ?>
                                <?php if ($response && $response['employee_attachment']): ?>
                                    <div class="mb-2">
                                        <i class="bi bi-paperclip me-2"></i>
                                        <strong>Employee Attachment:</strong>
                                        <a href="download.php?file=<?php echo urlencode($response['employee_attachment']); ?>&type=employee" 
                                           class="text-primary ms-2" target="_blank">
                                            <?php echo htmlspecialchars(basename($response['employee_attachment'])); ?>
                                            <i class="bi bi-download ms-1"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="bg-light p-3 rounded">
                                <?php if ($response && $response['employee_comments']): ?>
                                    <strong>Employee Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?>
                                <?php else: ?>
                                    <?php if (!$response || !$response['employee_attachment']): ?>
                                        <em class="text-muted">No attachment provided</em>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            <?php elseif ($question['response_type'] === 'checkbox'): ?>
                                <?php if ($response && $response['employee_response']): ?>
                                    <?php 
                                    $selected_options = explode(', ', $response['employee_response']);
                                    foreach ($selected_options as $option): 
                                    ?>
                                    <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($option); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em class="text-muted">No options selected</em>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php // Show employee response text ?>
                            <div class="bg-light p-3 rounded">
                                <?php if ($response && ($response['employee_response'] || $response['employee_comments'])): ?>
                                    <?php if ($response['employee_response']): ?>
                                        <div class="mb-2"><?php echo nl2br(htmlspecialchars($response['employee_response'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($response['employee_comments']): ?>
                                        <strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (!isset($response['employee_rating']) || $response['employee_rating'] === null): ?>
                                        <em class="text-muted">No response provided</em>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                          
                         
                        </div>
                        
                        <div class="manager-column">
                            <div class="column-header">Manager Assessment:</div>
                            
                            <?php if (in_array($question['response_type'], ['rating_5', 'rating_10'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Your Score</label>
                                <?php $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10; ?>
                                <select class="form-select" name="manager_rating_<?php echo $question['id']; ?>" id="manager_rating_<?php echo $question['id']; ?>">
                                    <option value="">Select score...</option>
                                    <?php if ($question['response_type'] === 'rating_5'): ?>
                                        <option value="1" <?php echo ($response['manager_rating'] ?? '') == '1' ? 'selected' : ''; ?>>1 - Poor</option>
                                        <option value="2" <?php echo ($response['manager_rating'] ?? '') == '2' ? 'selected' : ''; ?>>2 - Below Average</option>
                                        <option value="3" <?php echo ($response['manager_rating'] ?? '') == '3' ? 'selected' : ''; ?>>3 - Average</option>
                                        <option value="4" <?php echo ($response['manager_rating'] ?? '') == '4' ? 'selected' : ''; ?>>4 - Good</option>
                                        <option value="5" <?php echo ($response['manager_rating'] ?? '') == '5' ? 'selected' : ''; ?>>5 - Excellent</option>
                                    <?php else: ?>
                                        <?php for ($i = 0; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($response['manager_rating'] ?? '') == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> - <?php 
                                            if ($i == 0) echo 'Below Standard: Below job requirements and significant improvement is needed';
                                            elseif ($i <= 2) echo 'Below Standard: Below job requirements and significant improvement is needed';
                                            elseif ($i <= 4) echo 'Need Improvement: Improvement is needed to meet job requirements';
                                            elseif ($i <= 6) echo 'Satisfactory: Satisfactorily met job requirements';
                                            elseif ($i <= 8) echo 'Good: Exceeded job requirements';
                                            else echo 'Excellent: Far exceeded job requirements';
                                            ?>
                                        </option>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <textarea class="form-control" name="manager_comment_<?php echo $question['id']; ?>" rows="3"
                                      placeholder="Provide your assessment and feedback..."><?php echo htmlspecialchars($response['manager_comments'] ?? ''); ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    
<!-- Total Score Display for Manager Review -->
<div class="card bg-light mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h6><i class="bi bi-calculator me-2"></i>Performance Score Summary</h6>
                <div id="score-summary">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong>Performance Questions Reviewed:</strong> 
                                <span id="answered-count">0</span> / <span id="total-questions">0</span>
                            </div>
                            <div class="mb-2">
                                <strong>Your Average Score:</strong> 
                                <span id="average-score" class="badge bg-primary fs-6">0%</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                           
                            <div class="mb-2">
                                <strong>Employee Score:</strong> 
                                <span id="performance-percentage" class="badge bg-success fs-6">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <h6><i class="bi bi-info-circle me-2"></i>Grading Scale</h6>
                <small class="text-muted">
                    <strong>Performance Grades:</strong><br>
                    A = ≥85% (Excellent)<br>
                    B+ = 75-84% (Good)<br>
                    B = 60-74% (Satisfactory)<br>
                    B- = 50-59% (Below Average)<br>
                    C = <50% (Poor)
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
                        <strong>Save Progress:</strong> Your review progress is saved automatically. You can return anytime to continue.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Complete Review:</strong> Once all sections are reviewed, complete the appraisal with final grades.
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-save me-2"></i>Save Progress
                </button>
                <a href="complete.php?id=<?php echo $appraisal_id; ?>" class="btn btn-success">
                    <i class="bi bi-check-circle me-2"></i>Complete Review
                </a>
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
    let employeeTotal = 0;
    let employeeAnswered = 0;
    
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
    document.querySelectorAll('select[name^="manager_"]').forEach(function(select) {
        totalQuestions++;
        const value = parseInt(select.value) || 0;
        if (value > 0) {
            answeredQuestions++;
            // Convert 10-point to 5-point scale for average calculation
           // totalScore += (value / 2);
           totalScore += value; // Keep as is for 10-point average
        }
    });
        // Employee scores (from hidden inputs)
    document.querySelectorAll('.employee-rating').forEach(function(input) {
        const value = parseInt(input.value) || 0;
        if (value > 0) {
            employeeAnswered++;
            employeeTotal += value;
        }
    });
    // Calculate average
    const average = answeredQuestions > 0 ? (totalScore / answeredQuestions ).toFixed(1) : 0;
    const employeeAverage = employeeAnswered > 0 ? (employeeTotal / employeeAnswered).toFixed(1) : 0;

    // Update display
    document.getElementById('answered-count').textContent = answeredQuestions;
    document.getElementById('total-questions').textContent = totalQuestions;
    document.getElementById('average-score').textContent = `${(average * 10).toFixed(0)}%`;
    document.getElementById('performance-percentage').textContent = `${(employeeAverage * 10).toFixed(0)}%`;



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
    document.querySelectorAll('select[name^="manager_"]').forEach(function(select) {
        select.addEventListener('change', updateScoreCalculator);
    });
});

// Auto-save functionality
let autoSaveTimer;
document.getElementById('reviewForm').addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        const formData = new FormData(document.getElementById('reviewForm'));
        
        fetch('review.php?id=<?php echo $appraisal_id; ?>', {
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
    indicator.style.cssText = 'top: 80px; right: 20px; z-index: 9999; opacity: 0.9;';
    indicator.innerHTML = '<i class="bi bi-check-circle me-2"></i>Review auto-saved';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>