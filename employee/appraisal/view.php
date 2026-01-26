<?php
// employee/appraisal/view.php
require_once __DIR__ . '/../../config/config.php';

$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('index.php', 'Appraisal ID is required.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get appraisal details
/* $query = "SELECT a.*, u.name as appraiser_name, f.title as form_title
              FROM appraisals a
              LEFT JOIN users u ON a.appraiser_id = u.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE a.id = ? AND a.user_id = ?";  */
           $query = "SELECT a.*, 
                     emp.name as employee_name,
                     emp.emp_number,
                     emp.position,
                     emp.department,
                     emp.site,
                     mgr.name as appraiser_name,
                     f.title as form_title
              FROM appraisals a
              JOIN users emp ON a.user_id = emp.id
              LEFT JOIN users mgr ON emp.direct_superior = mgr.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE a.id = ? AND a.user_id = ?";
     
    $stmt = $db->prepare($query);
    $stmt->execute([$appraisal_id, $_SESSION['user_id']]);
    $appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appraisal_data) {
        redirect('index.php', 'Appraisal not found.', 'error');
    }
    
    // Get form structure
    $form = new Form($db);
    $form->id = $appraisal_data['form_id'];
    $form_structure = $form->getFormStructure();
    
    // Get responses
    $appraisal = new Appraisal($db);
    $appraisal->id = $appraisal_id;
    $responses_stmt = $appraisal->getResponses();
    
    $responses = [];
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $responses[$response['question_id']] = $response;
    }

        // After getting form_structure
    $emp_query = "SELECT is_confirmed FROM users WHERE id = ?";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute([$_SESSION['user_id']]);
    $employee_data = $emp_stmt->fetch(PDO::FETCH_ASSOC);
    $is_employee_confirmed = $employee_data['is_confirmed'] ?? false;

    // Filter sections
    $filtered_form_structure = [];
    foreach ($form_structure as $section) {
        if ($section['visible_to'] === 'reviewer' && $is_employee_confirmed) {
            continue;
        }
        $filtered_form_structure[] = $section;
    }
    $form_structure = $filtered_form_structure;
    
    // Calculate performance statistics for both employee and manager
    $performance_stats = [
        'total_rating_questions' => 0,
        'answered_rating_questions' => 0,
        'employee_total_score' => 0,
        'manager_total_score' => 0,
        'employee_average' => 0,
        'manager_average' => 0,
        'employee_percentage' => 0,
        'manager_percentage' => 0
    ];
    
    foreach ($form_structure as $section) {
        foreach ($section['questions'] as $question) {
            if (in_array($question['response_type'], ['rating_5', 'rating_10'])) {
                $performance_stats['total_rating_questions']++;
                $response = $responses[$question['id']] ?? null;
                
                if ($response && $response['employee_rating'] !== null) {
                    $performance_stats['answered_rating_questions']++;
                    
                    // Normalize to 5-point scale for consistent averaging
                    $emp_normalized = $question['response_type'] === 'rating_10' ? 
                        ($response['employee_rating'] ) : $response['employee_rating'];
                    $performance_stats['employee_total_score'] += $emp_normalized;
                    
                    // Add manager score if available
                    if ($response['manager_rating'] !== null) {
                        $mgr_normalized = $question['response_type'] === 'rating_10' ? 
                            ($response['manager_rating'] ) : $response['manager_rating'];
                        $performance_stats['manager_total_score'] += $mgr_normalized;
                    }
                }
            }
        }
    }
    
    if ($performance_stats['answered_rating_questions'] > 0) {
        $performance_stats['employee_average'] = round($performance_stats['employee_total_score'] / $performance_stats['answered_rating_questions'], 1);
        $performance_stats['employee_percentage'] = round(($performance_stats['employee_average'] / 10) * 100);
        
        if ($performance_stats['manager_total_score'] > 0) {
            $performance_stats['manager_average'] = round($performance_stats['manager_total_score'] / $performance_stats['answered_rating_questions'], 1);
            $performance_stats['manager_percentage'] = round(($performance_stats['manager_average'] / 10) * 100);
        }
    }
    
} catch (Exception $e) {
    error_log("View appraisal error: " . $e->getMessage());
    redirect('index.php', 'An error occurred. Please try again.', 'error');
}


?>


<!-- Print Header -->
<div class="print-header">
    <h1>Performance Appraisal Report</h1>
    <p><strong>YBS International</strong></p>
    <p>Confidential Document - For Internal Use Only</p>
</div>

<!-- Employee Info for Print -->
<div class="employee-info-print">
    <table>
        <tr>
            <td>Employee Name:</td>
            <td><strong><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></strong></td>
            <td>Employee No:</td>
            <td><strong><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></strong></td>
        </tr>
        <tr>
            <td>Position:</td>
            <td><?php echo htmlspecialchars($appraisal_data['position']); ?></td>
            <td>Department:</td>
            <td><?php echo htmlspecialchars($appraisal_data['department']); ?></td>
        </tr>
        <tr>
            <td>Site/Location:</td>
            <td><?php echo htmlspecialchars($appraisal_data['site']); ?></td>
            <td>Appraisal Period:</td>
            <td>
                <?php echo formatDate($appraisal_data['appraisal_period_from'], 'd M Y'); ?> - 
                <?php echo formatDate($appraisal_data['appraisal_period_to'], 'd M Y'); ?>
            </td>
        </tr>
        <?php if ($appraisal_data['status'] === 'completed'): ?>
        <tr>
            <td>Final Grade:</td>
            <td><strong style="font-size: 12pt;"><?php echo $appraisal_data['grade']; ?></strong></td>
            <td>Total Score:</td>
            <td><strong><?php echo number_format($appraisal_data['total_score'], 2); ?>%</strong></td>
        </tr>
        <?php endif; ?>
    </table>
</div>
<!-- Digital Signatures / Audit Trail -->
<div class="digital-signatures">
    <h6>Digital Audit Trail</h6>
    <table>
        <tr>
            <td>Document Created:</td>
            <td>
                <?php echo formatDate($appraisal_data['created_at'], 'd M Y, h:i A'); ?>
                <br><small>By: <?php echo htmlspecialchars($appraisal_data['employee_name']); ?> (Employee)</small>
            </td>
        </tr>
        <?php if (!empty($appraisal_data['employee_submitted_at'])): ?>
        <tr>
            <td>Submitted for Review:</td>
            <td>
                <?php echo formatDate($appraisal_data['employee_submitted_at'], 'd M Y, h:i A'); ?>
                <br><small>By: <?php echo htmlspecialchars($appraisal_data['employee_name']); ?> (Employee)</small>
            </td>
        </tr>
        <?php endif; ?>
        <?php if (($appraisal_data['manager_reviewed_at']) ): ?>
        <tr>
            <td>Reviewed By:</td>
            <td>
                <strong><?php echo htmlspecialchars($appraisal_data['appraiser_name'] ?? 'Not Assigned'); ?></strong> (Manager/Reviewer)
            </td>
        </tr>
        <tr>
            <td>Review Completed On:</td>
            <td>
                <?php echo formatDate($appraisal_data['manager_reviewed_at'], 'd M Y, h:i A'); ?>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>Document Status:</td>
            <td><strong><?php echo strtoupper(str_replace('_', ' ', $appraisal_data['status'])); ?></strong></td>
        </tr>
        <tr>
            <td>Document Generated:</td>
            <td><?php echo date('d M Y, h:i A'); ?></td>
        </tr>
    </table>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-eye me-2"></i>My Appraisal
                </h1>
                <small class="text-muted">
                    Period: <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> - 
                    <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                </small>
            </div>
            <div>
               
                <?php if ($appraisal_data['status'] === 'draft'): ?>
                <a href="continue.php" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Continue
                </a>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?> me-2">
                    <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                </span>
                
                
                <a href="../" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Home
                </a>
                <button onclick="printAppraisal()" class="btn btn-primary">
                    <i class="bi bi-printer me-2"></i>Print Appraisal
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Appraisal Summary - IMPROVED UI -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Appraisal Summary</h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <!-- Left Column - Basic Info -->
            <div class="col-md-4">
                <div class="border-start border-primary border-3 ps-3">
                    <h6 class="text-primary mb-2">Form Information</h6>
                    <p class="mb-1">
                        <strong><?php echo htmlspecialchars($appraisal_data['form_title']); ?></strong>
                    </p>
                    <p class="mb-0">
                        <strong>Period:</strong><br>
                        <small>
                            <?php echo formatDate($appraisal_data['appraisal_period_from'], 'd M Y'); ?> - 
                            <?php echo formatDate($appraisal_data['appraisal_period_to'], 'd M Y'); ?>
                        </small>
                    </p>
                </div>
            </div>
            
            <!-- Middle Column - Status & Timeline -->
            <div class="col-md-4">
                <div class="border-start border-info border-3 ps-3">
                    <h6 class="text-info mb-2">Status & Timeline</h6>
                    <div class="mb-2">
                        <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?> fs-6">
                            <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                        </span>
                    </div>
                    <p class="mb-1">
                        <small>
                            <i class="bi bi-calendar-plus text-muted"></i> 
                            <strong>Created:</strong> <?php echo formatDate($appraisal_data['created_at'], 'd M Y'); ?>
                        </small>
                    </p>
                    <?php if ($appraisal_data['employee_submitted_at']): ?>
                    <p class="mb-1">
                        <small>
                            <i class="bi bi-send-check text-success"></i> 
                            <strong>Submitted:</strong> <?php echo formatDate($appraisal_data['employee_submitted_at'], 'd M Y'); ?>
                        </small>
                    </p>
                    <?php endif; ?>
                    <?php if ($appraisal_data['manager_reviewed_at']): ?>
                    <p class="mb-1">
                        <small>
                            <i class="bi bi-check-circle text-success"></i> 
                            <strong>Reviewed:</strong> <?php echo formatDate($appraisal_data['manager_reviewed_at'], 'd M Y'); ?>
                        </small>
                    </p>
                    <?php if ($appraisal_data['appraiser_name']): ?>
                    <p class="mb-0">
                        <small>
                            <i class="bi bi-person-check text-muted"></i> 
                            <strong>By:</strong> <?php echo htmlspecialchars($appraisal_data['appraiser_name']); ?>
                        </small>
                    </p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column - Performance Summary -->
            <div class="col-md-4">
                <div class="border-start border-success border-3 ps-3">
                    <h6 class="text-success mb-2">Performance Summary</h6>
                    <?php if ($appraisal_data['status'] === 'completed' && $performance_stats['manager_average'] > 0): ?>
                        <!-- Completed - Show final results -->
                        <?php if ($appraisal_data['grade']): ?>
                        <div class="mb-2">
                            <span class="badge bg-success fs-4"><?php echo $appraisal_data['grade']; ?></span>
                            <small class="text-muted ms-2">Final Grade</small>
                        </div>
                        <?php endif; ?>
                        <?php if ($appraisal_data['total_score']): ?>
                        <p class="mb-2">
                            <strong>Overall Score:</strong> 
                            <span class="fs-5 text-success"><?php echo number_format($appraisal_data['total_score'], 1); ?>%</span>
                        </p>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="mb-1">
                            <small class="text-muted d-block">Your Average:</small>
                            <span class="badge bg-primary"><?php echo $performance_stats['employee_average']; ?>/10</span>
                            <small class="text-muted">(<?php echo $performance_stats['employee_percentage']; ?>%)</small>
                        </div>
                        <div class="mb-1">
                            <small class="text-muted d-block">Manager's Average:</small>
                            <span class="badge bg-success"><?php echo $performance_stats['manager_average']; ?>/10</span>
                            <small class="text-muted">(<?php echo $performance_stats['manager_percentage']; ?>%)</small>
                        </div>
                        
                    <?php elseif ($performance_stats['answered_rating_questions'] > 0 && in_array($appraisal_data['status'], ['submitted', 'in_review'])): ?>
                        <!-- Submitted - Show employee scores only -->
                        <div class="mb-2">
                            <small class="text-muted d-block">Your Average Score:</small>
                            <span class="badge bg-primary fs-5"><?php echo $performance_stats['employee_average']; ?>/10</span>
                            <small class="text-muted ms-1">(<?php echo $performance_stats['employee_percentage']; ?>%)</small>
                        </div>
                        <div class="alert alert-warning py-2 mb-0">
                            <small>
                                <i class="bi bi-clock me-1"></i>
                                Awaiting manager review
                            </small>
                        </div>
                        
                    <?php elseif ($performance_stats['answered_rating_questions'] > 0): ?>
                        <!-- Draft - Show progress -->
                        <div class="progress mb-2" style="height: 25px;">
                            <?php 
                            $progress = round(($performance_stats['answered_rating_questions'] / $performance_stats['total_rating_questions']) * 100);
                            ?>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%">
                                <?php echo $progress; ?>%
                            </div>
                        </div>
                        <small class="text-muted">
                            Progress: <?php echo $performance_stats['answered_rating_questions']; ?>/<?php echo $performance_stats['total_rating_questions']; ?> questions answered
                        </small>
                        
                    <?php else: ?>
                        <div class="alert alert-secondary py-2 mb-0">
                            <small>No performance scores yet</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($appraisal_data['employee_submitted_at']): ?>
        <!-- Submission Confirmation Banner -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-success mb-0">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <i class="bi bi-check-circle-fill fs-3"></i>
                        </div>
                        <div class="col">
                            <h6 class="mb-1">Appraisal Submitted Successfully</h6>
                            <small>
                                <strong>Submitted on:</strong> <?php echo formatDate($appraisal_data['employee_submitted_at'], 'l, F j, Y \a\t g:i A'); ?>
                                <?php if ($appraisal_data['manager_reviewed_at']): ?>
                                <br><strong>Reviewed on:</strong> <?php echo formatDate($appraisal_data['manager_reviewed_at'], 'l, F j, Y \a\t g:i A'); ?>
                                <?php if ($appraisal_data['appraiser_name']): ?>
                                by <?php echo htmlspecialchars($appraisal_data['appraiser_name']); ?>
                                <?php endif; ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Appraisal Content -->
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
        <?php 
        $overall_question = null;
        $overall_response = [];

        foreach ($section['questions'] as $question): 
            $response = $responses[$question['id']] ?? [];

            // For Cultural Values: defer "Overall Comments"
            if ($section['title'] === 'Cultural Values' && stripos($question['text'], 'Overall Comments') !== false) {
                $overall_question = $question;
                $overall_response = $response;
                continue;
            }
        ?>
        
        <?php if ($question['response_type'] === 'display'): ?>
            <!-- Display-only info -->
            <div class="mb-3">
                <strong><?php echo htmlspecialchars($question['text']); ?></strong>
                <?php if (!empty($question['description'])): ?>
                    <div class="mt-2">
                        <?php echo formatDescriptionAsBullets($question['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
             <?php elseif ($section['visible_to'] === 'reviewer'): ?>
            <!-- REVIEWER-ONLY SECTIONS (Pass Probation, etc.) - Show manager responses -->
            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-bold mb-3">
                    <?php echo htmlspecialchars($question['text']); ?>
                    <span class="badge bg-warning ms-2">Manager Assessment</span>
                </h6>

                <?php if ($question['description']): ?>
                    <p class="text-muted small mb-3"><?php echo formatDescriptionAsBullets($question['description']); ?></p>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label text-success fw-bold">Manager's Assessment:</label>
                        
                        <?php if ($question['response_type'] === 'radio' && !empty($response['manager_response'])): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Selected:</strong> <?php echo htmlspecialchars($response['manager_response']); ?>
                            </div>
                        <?php elseif ($question['response_type'] === 'checkbox' && !empty($response['manager_response'])): ?>
                            <div class="mb-2">
                                <?php 
                                $selected_options = explode(', ', $response['manager_response']);
                                foreach ($selected_options as $option): 
                                ?>
                                <span class="badge bg-success me-1 mb-1"><?php echo htmlspecialchars($option); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (!empty($response['manager_response'])): ?>
                            <div class="bg-success bg-opacity-10 p-3 rounded border-start border-success border-3">
                                <?php echo nl2br(htmlspecialchars($response['manager_response'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary">
                                <em>No assessment provided yet</em>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($response['manager_comments'])): ?>
                            <div class="mt-3">
                                <label class="form-label text-muted small">Additional Comments:</label>
                                <div class="bg-light p-2 rounded small">
                                    <?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Normal side-by-side layout -->
            <div class="mb-4 pb-4 border-bottom">
                <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($question['text']); ?></h6>
                <?php if (!empty($question['description'])): ?>
                    <div class="mt-2">
                        <?php echo formatDescriptionAsBullets($question['description']); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Employee side -->
                    <div class="col-md-6">
                        <h6 class="text-primary">Your Response:</h6>
                                <?php if (in_array($question['response_type'], ['rating_5', 'rating_10']) && 
                                            isset($response['employee_rating']) && $response['employee_rating'] !== null): ?>
                                        <div class="mb-3 p-2 bg-primary bg-opacity-10 rounded border-start border-primary border-3">
                                            <strong>Your Score: </strong>
                                            <span class="badge bg-primary fs-6"><?php echo $response['employee_rating']; ?></span>
                                            <?php 
                                            $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                                            $percentage = round(($response['employee_rating'] / $max_rating) * 100);
                                            ?>
                                            <span class="text-muted">/ <?php echo $max_rating; ?> (<?php echo $percentage; ?>%)</span>

                                            <div class="small text-muted mt-1">
                                                <?php
                                                if ($question['response_type'] === 'rating_5') {
                                                    $descriptions = [1 => 'Poor', 2 => 'Below Average', 3 => 'Average', 4 => 'Good', 5 => 'Excellent'];
                                                    echo $descriptions[$response['employee_rating']] ?? '';
                                                } else {
                                                    if ($response['employee_rating'] == 0) echo 'Not Applicable';
                                                    elseif ($response['employee_rating'] <= 2) echo 'Poor';
                                                    elseif ($response['employee_rating'] <= 4) echo 'Below Average';
                                                    elseif ($response['employee_rating'] <= 6) echo 'Average';
                                                    elseif ($response['employee_rating'] <= 8) echo 'Good';
                                                    else echo 'Excellent';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                        <div class="bg-light p-3 rounded">
                            <?php if ($question['response_type'] === 'checkbox'): ?>
                                <?php if (!empty($response['employee_response'])): ?>
                                    <?php foreach (explode(', ', $response['employee_response']) as $option): ?>
                                        <span class="badge bg-info me-1 mb-1"><?php echo htmlspecialchars($option); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em class="text-muted">No options selected</em>
                                <?php endif; ?>
                                
                            

                            <?php elseif ($question['response_type'] === 'attachment'): ?>
                                <?php if (!empty($response['employee_attachment'])): ?>
                                    <div class="mb-2">
                                        <i class="bi bi-paperclip me-2"></i>
                                        <strong>Attachment:</strong>
                                        <a href="download.php?file=<?php echo urlencode($response['employee_attachment']); ?>&type=employee" 
                                           class="text-primary ms-2" target="_blank">
                                            <?php echo htmlspecialchars(basename($response['employee_attachment'])); ?>
                                            <i class="bi bi-download ms-1"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($response['employee_comments'])): ?>
                                    <small><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></small>
                                <?php else: ?>
                                    <em class="text-muted">No attachment provided</em>
                                <?php endif; ?>

                            <?php else: ?>
                                <?php if (!empty($response['employee_response']) || !empty($response['employee_comments'])): ?>
                                    <?php if (!empty($response['employee_response'])): ?>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($response['employee_response'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($response['employee_comments'])): ?>
                                        <small><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em class="text-muted">No response provided</em>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Manager side -->
                    <div class="col-md-6">
                        <h6 class="text-success">Manager's Assessment:</h6>

                            <?php if (in_array($question['response_type'], ['rating_5', 'rating_10']) && 
                                    isset($response['manager_rating']) && $response['manager_rating'] !== null): ?>
                                <div class="mb-3 p-2 bg-success bg-opacity-10 rounded border-start border-success border-3">
                                    <strong>Manager Score: </strong>
                                    <span class="badge bg-success fs-6"><?php echo $response['manager_rating']; ?></span>
                                    <?php 
                                    $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                                    $percentage = round(($response['manager_rating'] / $max_rating) * 100);
                                    ?>
                                    <span class="text-muted">/ <?php echo $max_rating; ?> (<?php echo $percentage; ?>%)</span>

                                    <div class="small text-muted mt-1">
                                        <?php
                                        if ($question['response_type'] === 'rating_5') {
                                            $descriptions = [1 => 'Poor', 2 => 'Below Average', 3 => 'Average', 4 => 'Good', 5 => 'Excellent'];
                                            echo $descriptions[$response['manager_rating']] ?? '';
                                        } else {
                                            if ($response['manager_rating'] == 0) echo 'Not Applicable';
                                            elseif ($response['manager_rating'] <= 2) echo 'Poor';
                                            elseif ($response['manager_rating'] <= 4) echo 'Below Average';
                                            elseif ($response['manager_rating'] <= 6) echo 'Average';
                                            elseif ($response['manager_rating'] <= 8) echo 'Good';
                                            else echo 'Excellent';
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>


                            
                            <!-- Manager Comments Box (balanced with employee side) -->
                            <div class="bg-light p-3 rounded">
                                <?php if (!empty($response['manager_comments']) || !empty($response['manager_response'])): ?>
                                    <?php if (!empty($response['manager_response'])): ?>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($response['manager_response'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($response['manager_comments'])): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em class="text-muted">No manager feedback yet</em>
                                <?php endif; ?>
                            </div>
                        
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Cultural Values Overall Comments (side-by-side) -->
        <?php if ($section['title'] === 'Cultural Values' && $overall_question): ?>
        <div class="mt-4">
            <div class="row">
                <div class="col-md-6">
                    <h6><strong>Your Overall Comments on Cultural Values</strong></h6>
                    <div class="bg-light p-3 rounded">
                        <?php if (!empty($overall_response['employee_response'])): ?>
                            <?php echo nl2br(htmlspecialchars($overall_response['employee_response'])); ?>
                        <?php else: ?>
                            <em class="text-muted">No overall comments provided</em>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6><strong>Manager's Feedback on Cultural Values</strong></h6>
                    <?php if (!empty($overall_response['manager_comments'])): ?>
                        <div class="bg-success bg-opacity-10 p-3 rounded border-start border-success border-3">
                            <?php echo nl2br(htmlspecialchars($overall_response['manager_comments'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-light p-3 rounded">
                            <em class="text-muted">No manager feedback yet</em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>


<!-- Action Buttons -->
<?php if ($appraisal_data['status'] === 'draft'): ?>
<div class="card">
    <div class="card-body text-center">
        <h6>Continue Working on Your Appraisal</h6>
        <a href="continue.php" class="btn btn-primary">
            <i class="bi bi-pencil me-2"></i>Continue Editing
        </a>
    </div>
</div>
<?php endif; ?>


<!-- Print Footer (only shows when printing) -->
<div class="print-footer">
    <p>This is a computer-generated document. Printed on <?php echo date('F d, Y \a\t h:i A'); ?></p>
    <p>Page <span class="page-number"></span></p>
</div>
<style>
@media print {
    /* Hide elements not needed in print */
    .sidebar,
    .navbar,
    .btn,
    .no-print,
    .breadcrumb,
    .alert,
    .back-button,
    .d-flex.justify-content-between {
        display: none !important;
    }
    
    /* Page setup */
    @page {
        size: A4 portrait;
        margin: 2cm 1.5cm;
    }
    
    body {
        font-size: 10pt;
        line-height: 1.3;
        color: #000;
        background: white !important;
    }
    
    /* Print header with company logo space */
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid #000;
        page-break-after: avoid;
    }
    
    .print-header h1 {
        font-size: 16pt;
        margin: 0 0 5px 0;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .print-header p {
        margin: 3px 0;
        font-size: 9pt;
    }
    
    /* Employee info box - condensed */
    .employee-info-print {
        display: block !important;
        margin: 0 0 20px 0;
        padding: 8px;
        border: 2px solid #000;
        page-break-inside: avoid;
        page-break-after: avoid;
    }
    
    .employee-info-print table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
    }
    
    .employee-info-print td {
        padding: 3px 5px;
        border: none;
        vertical-align: top;
    }
    
    .employee-info-print td:first-child {
        font-weight: bold;
        width: 25%;
    }
    
    .employee-info-print td:nth-child(3) {
        font-weight: bold;
        width: 25%;
    }
    
    /* Digital signature section */
    .digital-signatures {
        display: block !important;
        margin: 20px 0;
        padding: 10px;
        background: #f5f5f5 !important;
        border: 1px solid #333;
        page-break-inside: avoid;
        page-break-after: avoid;
    }
    
    .digital-signatures h6 {
        font-size: 10pt;
        margin: 0 0 8px 0;
        font-weight: bold;
        border-bottom: 1px solid #666;
        padding-bottom: 5px;
    }
    
    .digital-signatures table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
    }
    
    .digital-signatures td {
        padding: 4px 5px;
        border-bottom: 1px dotted #ccc;
    }
    
    .digital-signatures td:first-child {
        font-weight: bold;
        width: 30%;
    }
    
    /* Cards - more compact */
    .card {
        border: 1px solid #666 !important;
        box-shadow: none !important;
        margin-bottom: 12px;
        page-break-inside: avoid;
    }
    
    .card-header {
        background-color: #e8e8e8 !important;
        color: #000 !important;
        padding: 6px 8px !important;
        border-bottom: 2px solid #000 !important;
        font-weight: bold;
        font-size: 11pt;
        page-break-after: avoid;
    }
    
    .card-body {
        padding: 8px !important;
    }
    
    /* Section breaks for better pagination */
    .card:nth-child(3n) {
        page-break-after: auto;
    }
    
    /* Question blocks - more compact */
    .mb-4.pb-4.border-bottom {
        margin-bottom: 10px !important;
        padding-bottom: 8px !important;
        page-break-inside: avoid;
    }
    
    h6.fw-bold {
        font-size: 9.5pt;
        margin-bottom: 6px !important;
        page-break-after: avoid;
    }
    
    /* Response columns - side by side */
    .row > .col-md-6 {
        width: 48%;
        float: left;
        padding: 5px;
        page-break-inside: avoid;
    }
    
    .row > .col-md-6:first-child {
        margin-right: 4%;
    }
    
    .row::after {
        content: "";
        display: table;
        clear: both;
    }
    
    /* Response boxes */
    .bg-light,
    .bg-primary.bg-opacity-10,
    .bg-success.bg-opacity-10 {
        background-color: #f9f9f9 !important;
        padding: 6px !important;
        border: 1px solid #ddd !important;
        font-size: 9pt;
        min-height: 30px;
    }
    
    /* Badges - print friendly */
    .badge {
        border: 1px solid #000 !important;
        padding: 2px 5px !important;
        background-color: white !important;
        color: #000 !important;
        font-weight: bold !important;
        font-size: 9pt !important;
    }
    
    /* Hide manual signatures */
    .signatures-section {
        display: none !important;
    }
    
    /* Print footer */
    .print-footer {
        display: block !important;
        margin-top: 15px;
        padding-top: 8px;
        border-top: 1px solid #999;
        text-align: center;
        font-size: 8pt;
        color: #666 !important;
        page-break-inside: avoid;
    }
    
    /* Page numbers */
    .print-footer::after {
        content: "Page " counter(page);
    }
    
    /* Remove extra spacing */
    p, ul, ol {
        margin: 3px 0 !important;
    }
    
    /* Small text even smaller */
    small, .small {
        font-size: 8pt !important;
    }
    /* Digital signature section - UPDATED */
    .digital-signatures {
        display: block !important;
        margin: 20px 0;
        padding: 10px;
        background: #f5f5f5 !important;
        border: 1px solid #333;
        page-break-inside: avoid;
        page-break-after: always; /* FORCE PAGE BREAK AFTER AUDIT TRAIL */
    }
    
    /* First section/card should start on new page */
    .card:first-of-type {
        page-break-before: auto; /* Let it flow naturally after the forced break */
    }
    /* Force grayscale */
    * {
        color-adjust: exact !important;
        -webkit-print-color-adjust: exact !important;
    }
    
    /* Orphans and widows control */
    p, h6, .card-header {
        orphans: 3;
        widows: 3;
    }
    
    /* Avoid breaking inside important elements */
    .card-header,
    h5, h6,
    .employee-info-print,
    .digital-signatures {
        page-break-after: avoid;
        page-break-inside: avoid;
    }
    
    /* Better rating display */
    .border-start.border-3 {
        border-left: 3px solid #333 !important;
    }
}

/* Screen-only styles */
.print-header,
.employee-info-print,
.digital-signatures,
.print-footer {
    display: none;
}
</style>

<script>
function printAppraisal() {
    // Show a loading message
    const originalTitle = document.title;
    document.title = 'Printing Appraisal - <?php echo htmlspecialchars($appraisal_data['employee_name']); ?>';
    
    // Small delay to ensure styles are applied
    setTimeout(function() {
        window.print();
        document.title = originalTitle;
    }, 100);
}

// Optional: Add keyboard shortcut Ctrl+P
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printAppraisal();
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>