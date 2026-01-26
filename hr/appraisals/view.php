<?php
// hr/appraisals/view.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('index.php', 'Appraisal ID is required.', 'error');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isHR()) {
    redirect(BASE_URL . '/index.php', 'Access denied. HR personnel only.', 'error');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

try {
    $appraisal = new Appraisal($db);
    
    // Check if HR can view this appraisal
    if (!$appraisal->canHRView($appraisal_id, $_SESSION['user_id'])) {
        redirect('index.php', 'You do not have permission to view this appraisal.', 'error');
    }
    
    // Get appraisal details (similar to manager/review/view.php)
    $query = "SELECT a.*, u.name as employee_name, u.emp_number, u.position, 
                     u.department, u.site, u.email as employee_email,
                     c.name as company_name,
                     f.title as form_title, f.form_type,
                     appraiser.name as appraiser_name, appraiser.email as appraiser_email
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              JOIN companies c ON u.company_id = c.id
              LEFT JOIN forms f ON a.form_id = f.id
              LEFT JOIN users appraiser ON u.direct_superior = appraiser.id
              WHERE a.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$appraisal_id]);
    $appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appraisal_data) {
        redirect('index.php', 'Appraisal not found.', 'error');
    }
    
    // Get form structure
    $form = new Form($db);
    $form->id = $appraisal_data['form_id'];
    $form_structure = $form->getFormStructure();
    
    // Get responses
    $appraisal->id = $appraisal_id;
    $responses_stmt = $appraisal->getResponses();
    
    $responses = [];
    while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
        $responses[$response['question_id']] = $response;
    }

    // After getting form_structure
    $emp_query = "SELECT is_confirmed FROM users WHERE id = ?";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute([$appraisal_data['user_id']]);
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


    // Calculate performance statistics (if completed)
    $performance_stats = [
        'total_rating_questions' => 0,
        'answered_rating_questions' => 0,
        'employee_total' => 0,
        'manager_total' => 0,
        'employee_max' => 0,
        'manager_max' => 0
    ];
    
    if ($appraisal_data['status'] === 'completed') {
        foreach ($form_structure as $section) {
            if ($section['title'] === 'Performance Assessment') {
                foreach ($section['questions'] as $question) {
                    if (in_array($question['response_type'], ['rating_5', 'rating_10'])) {
                        $performance_stats['total_rating_questions']++;
                        $response = $responses[$question['id']] ?? null;
                        
                        $max_rating = $question['response_type'] === 'rating_5' ? 5 : 10;
                        
                        if ($response && $response['employee_rating']) {
                            $performance_stats['answered_rating_questions']++;
                            $performance_stats['employee_total'] += $response['employee_rating'];
                            $performance_stats['employee_max'] += $max_rating;
                        }
                        
                        if ($response && $response['manager_rating']) {
                            $performance_stats['manager_total'] += $response['manager_rating'];
                            $performance_stats['manager_max'] += $max_rating;
                        }
                    }
                }
            }
        }
        
        // Calculate percentages
        if ($performance_stats['employee_max'] > 0) {
            $performance_stats['employee_percentage'] = round(($performance_stats['employee_total'] / $performance_stats['employee_max']) * 100, 1);
        }
        
        if ($performance_stats['manager_max'] > 0) {
            $performance_stats['manager_percentage'] = round(($performance_stats['manager_total'] / $performance_stats['manager_max']) * 100, 1);
        }
    }
    
} catch (Exception $e) {
    error_log("HR View appraisal error: " . $e->getMessage());
    redirect('index.php', 'An error occurred.', 'error');
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-clipboard-data me-2"></i>Appraisal Details (HR View)
            </h1>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to List
                </a>
                <button onclick="window.print()" class="btn btn-outline-primary">
                    <i class="bi bi-printer me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Appraisal Overview -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Employee Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($appraisal_data['employee_name']); ?></p>
                        <p><strong>Employee Number:</strong> <?php echo htmlspecialchars($appraisal_data['emp_number']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($appraisal_data['employee_email']); ?></p>
                        <p><strong>Position:</strong> <?php echo htmlspecialchars($appraisal_data['position']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($appraisal_data['department']); ?></p>
                        <p><strong>Site:</strong> <?php echo htmlspecialchars($appraisal_data['site']); ?></p>
                        <p><strong>Company:</strong> <?php echo htmlspecialchars($appraisal_data['company_name']); ?></p>
                        <p><strong>Manager:</strong> <?php echo htmlspecialchars($appraisal_data['appraiser_name'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Appraisal Status</h5>
            </div>
            <div class="card-body">
                <p>
                    <strong>Status:</strong><br>
                    <span class="badge <?php echo getStatusBadgeClass($appraisal_data['status']); ?> mt-1">
                        <?php echo ucwords(str_replace('_', ' ', $appraisal_data['status'])); ?>
                    </span>
                </p>
                
                <p>
                    <strong>Period:</strong><br>
                    <?php echo formatDate($appraisal_data['appraisal_period_from']); ?> to<br>
                    <?php echo formatDate($appraisal_data['appraisal_period_to']); ?>
                </p>
                
                <?php if ($appraisal_data['grade']): ?>
                <p>
                    <strong>Final Grade:</strong><br>
                    <span class="badge bg-secondary fs-5 mt-1"><?php echo $appraisal_data['grade']; ?></span>
                </p>
                <?php endif; ?>
                
                <?php if ($appraisal_data['total_score']): ?>
                <p>
                    <strong>Total Score:</strong><br>
                    <span class="fs-5"><?php echo number_format($appraisal_data['total_score'], 2); ?></span>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Performance Statistics -->
<?php if ($appraisal_data['status'] === 'completed' && $performance_stats['total_rating_questions'] > 0): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted">Employee Self-Assessment</h6>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Performance Score:</span>
                    <strong><?php echo $performance_stats['employee_total']; ?> / <?php echo $performance_stats['employee_max']; ?></strong>
                </div>
                <div class="progress mb-2" style="height: 25px;">
                    <div class="progress-bar bg-primary" role="progressbar" 
                         style="width: <?php echo $performance_stats['employee_percentage']; ?>%">
                        <?php echo $performance_stats['employee_percentage']; ?>%
                    </div>
                </div>
                <small class="text-muted">Based on <?php echo $performance_stats['answered_rating_questions']; ?> rating questions</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted">Manager Assessment</h6>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Performance Score:</span>
                    <strong><?php echo $performance_stats['manager_total']; ?> / <?php echo $performance_stats['manager_max']; ?></strong>
                </div>
                <div class="progress mb-2" style="height: 25px;">
                    <div class="progress-bar bg-success" role="progressbar" 
                         style="width: <?php echo $performance_stats['manager_percentage']; ?>%">
                        <?php echo $performance_stats['manager_percentage']; ?>%
                    </div>
                </div>
                <small class="text-muted">Based on <?php echo $performance_stats['total_rating_questions']; ?> rating questions</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Timeline -->
<?php if ($appraisal_data['status'] !== 'draft'): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-clock-history me-2"></i>Timeline</h5>
                <div class="timeline">
                    <div class="timeline-item">
                        <i class="bi bi-circle-fill text-primary"></i>
                        <strong>Created:</strong> <?php echo formatDate($appraisal_data['created_at'], 'M d, Y H:i'); ?>
                    </div>
                    
                    <?php if (!empty($appraisal_data['employee_submitted_at'])): ?>
                    <div class="timeline-item">
                        <i class="bi bi-circle-fill text-info"></i>
                        <strong>Employee Submitted:</strong> <?php echo formatDate($appraisal_data['employee_submitted_at'], 'M d, Y H:i'); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($appraisal_data['manager_reviewed_at'])): ?>
                    <div class="timeline-item">
                        <i class="bi bi-circle-fill text-success"></i>
                        <strong>Manager Completed:</strong> <?php echo formatDate($appraisal_data['manager_reviewed_at'], 'M d, Y H:i'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
                <h6 class="fw-bold mb-3"><?php echo formatDescriptionAsBullets($question['text']); ?></h6>
                    <?php if (!empty($question['description'])): ?>
                    <div class="mt-2">
                        <?php echo formatDescriptionAsBullets($question['description']); ?>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <!-- Employee side -->
                    <div class="col-md-6">
                        <h6 class="text-primary">Employee Response:</h6>
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
                                            <a href="download.php? file=<?php echo urlencode($response['employee_attachment']); ?>&type=employee" 
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
        
        <!-- Manager's Final Comments -->
        <?php if ($appraisal_data['status'] === 'completed' && !empty($appraisal_data['manager_comments'])): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-chat-square-text me-2"></i>Manager's Final Comments</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($appraisal_data['manager_comments'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
    padding-left: 20px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-item:before {
    content: '';
    position: absolute;
    left: -20px;
    top: 8px;
    bottom: -20px;
    width: 2px;
    background: #dee2e6;
}
.timeline-item:last-child:before {
    display: none;
}
.timeline-item i {
    position: absolute;
    left: -26px;
    top: 2px;
    font-size: 12px;
}

.question-block {
    background-color: #f8f9fa;
    border-radius: 8px;
}

.question-text {
    font-size: 1.05rem;
    color: #212529;
}

@media print {
    .btn, .sidebar, .navbar {
        display: none !important;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>