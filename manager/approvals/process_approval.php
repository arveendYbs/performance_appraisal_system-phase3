<?php
// manager/approvals/view.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$appraisal_id = $_GET['id'] ?? 0;
if (!$appraisal_id) {
    redirect('pending.php', 'Appraisal ID is required.', 'error');
}

$database = new Database();
$db = $database->getConnection();

require_once __DIR__ . '/../../classes/Appraisal.php';
require_once __DIR__ . '/../../classes/ApprovalChain.php';

$appraisal = new Appraisal($db);
$appraisal->id = $appraisal_id;

// Check if user can approve this appraisal
if (!$appraisal->canUserApprove($_SESSION['user_id'])) {
    redirect('pending.php', 'You are not authorized to approve this appraisal.', 'error');
}

// Get appraisal details
$query = "SELECT a.*, 
                 u.name as employee_name, 
                 u.emp_number, 
                 u.position, 
                 u.department, 
                 u.site,
                 c.name as company_name,
                 f.title as form_title,
                 f.form_type
          FROM appraisals a
          JOIN users u ON a.user_id = u.id
          JOIN companies c ON u.company_id = c.id
          LEFT JOIN forms f ON a.form_id = f.id
          WHERE a.id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$appraisal_id]);
$appraisal_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appraisal_data) {
    redirect('pending.php', 'Appraisal not found.', 'error');
}

// Get current approval level details
$current_approval = $appraisal->getCurrentApprovalLevel();

// Get approval chain
$approval_chain_stmt = $appraisal->getApprovalChain();
$approval_chain = $approval_chain_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get form structure and responses (same as manager/review/view.php)
require_once __DIR__ . '/../../classes/Form.php';
$form = new Form($db);
$form->id = $appraisal_data['form_id'];
$form_structure = $form->getFormStructure();

$appraisal->id = $appraisal_id;
$responses_stmt = $appraisal->getResponses();
$responses = [];
while ($response = $responses_stmt->fetch(PDO::FETCH_ASSOC)) {
    $responses[$response['question_id']] = $response;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Approval Review</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="pending.php">Pending Approvals</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Employee Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <h6 class="text-muted mb-1">Employee</h6>
                        <p class="mb-0"><strong><?php echo htmlspecialchars($appraisal_data['employee_name']); ?></strong></p>
                        <small class="text-muted"><?php echo htmlspecialchars($appraisal_data['emp_number']); ?></small>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted mb-1">Position</h6>
                        <p class="mb-0"><?php echo htmlspecialchars($appraisal_data['position']); ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted mb-1">Department</h6>
                        <p class="mb-0"><?php echo htmlspecialchars($appraisal_data['department']); ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-muted mb-1">Your Role</h6>
                        <p class="mb-0">
                            <span class="badge bg-success">
                                Level <?php echo $current_approval['approval_level']; ?> - 
                                <?php echo ucwords(str_replace('_', ' ', $current_approval['approver_role'])); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Chain Progress -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Approval Chain Progress</h6>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <?php foreach ($approval_chain as $index => $level): ?>
                        <?php
                        $is_current = ($level['approval_level'] == $current_approval['approval_level']);
                        $is_completed = ($level['status'] == 'approved');
                        $is_pending = ($level['status'] == 'pending');
                        $is_future = ($level['approval_level'] > $current_approval['approval_level']);
                        
                        if ($is_completed) {
                            $badge_class = 'bg-success';
                            $icon = 'check-circle-fill';
                        } elseif ($is_current) {
                            $badge_class = 'bg-warning';
                            $icon = 'arrow-right-circle-fill';
                        } else {
                            $badge_class = 'bg-secondary';
                            $icon = 'circle';
                        }
                        ?>
                        
                        <div class="d-flex align-items-center">
                            <div class="text-center">
                                <div class="badge <?php echo $badge_class; ?> p-2">
                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                    Level <?php echo $level['approval_level']; ?>
                                </div>
                                <div class="small mt-1" style="max-width: 150px;">
                                    <?php echo htmlspecialchars($level['approver_name']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo ucwords(str_replace('_', ' ', $level['approver_role'])); ?>
                                    </small>
                                    <?php if ($is_completed && $level['action_date']): ?>
                                        <br>
                                        <small class="text-success">
                                            ✓ <?php echo date('M d', strtotime($level['action_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($index < count($approval_chain) - 1): ?>
                                <i class="bi bi-arrow-right mx-2 text-muted"></i>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Important Notice -->
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>Approval Only:</strong> You are reviewing this appraisal at Level <?php echo $current_approval['approval_level']; ?>.
            You can approve or reject, but <strong>cannot modify the ratings</strong> given by the direct manager (Level 1).
            All rating fields below are read-only.
        </div>

        <!-- Appraisal Form (Read-Only) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-4"><?php echo htmlspecialchars($appraisal_data['form_title']); ?></h5>
                
                <?php foreach ($form_structure as $section): ?>
                    <div class="section-container mb-5">
                        <h5 class="section-title bg-light p-3 rounded">
                            <?php echo htmlspecialchars($section['section_title']); ?>
                        </h5>
                        
                        <?php if (!empty($section['section_description'])): ?>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($section['section_description'])); ?></p>
                        <?php endif; ?>
                        
                        <?php foreach ($section['questions'] as $question): ?>
                            <?php
                            $question_id = $question['question_id'];
                            $response = $responses[$question_id] ?? null;
                            ?>
                            
                            <div class="question-container mb-4 p-3 border rounded">
                                <label class="form-label fw-bold">
                                    <?php echo htmlspecialchars($question['question_text']); ?>
                                </label>
                                
                                <div class="row">
                                    <!-- Employee Response -->
                                    <div class="col-md-6">
                                        <h6 class="text-muted small">Employee Response</h6>
                                        
                                        <?php if ($question['response_type'] == 'rating_5' || $question['response_type'] == 'rating_10'): ?>
                                            <p class="mb-1">
                                                <strong>Rating:</strong> 
                                                <span class="badge bg-primary">
                                                    <?php echo $response['employee_rating'] ?? 'N/A'; ?>
                                                </span>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-muted">
                                                <?php echo htmlspecialchars($response['employee_response'] ?? 'No response'); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($response['employee_comments'])): ?>
                                            <p class="small text-muted mb-0">
                                                <em><?php echo nl2br(htmlspecialchars($response['employee_comments'])); ?></em>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Manager Review (Level 1) -->
                                    <div class="col-md-6 bg-light p-3 rounded">
                                        <h6 class="text-primary small">Manager Review (Level 1) - READ ONLY</h6>
                                        
                                        <?php if ($question['response_type'] == 'rating_5' || $question['response_type'] == 'rating_10'): ?>
                                            <p class="mb-1">
                                                <strong>Rating:</strong> 
                                                <span class="badge bg-success">
                                                    <?php echo $response['manager_rating'] ?? 'Not rated yet'; ?>
                                                </span>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-muted">
                                                <?php echo htmlspecialchars($response['manager_response'] ?? 'No response'); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($response['manager_comments'])): ?>
                                            <p class="small mb-0">
                                                <strong>Comments:</strong> 
                                                <?php echo nl2br(htmlspecialchars($response['manager_comments'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Approval Action Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-check-circle"></i> Your Approval Decision</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="process_approval.php" id="approvalForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="appraisal_id" value="<?php echo $appraisal_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Your Comments (Optional)</label>
                        <textarea class="form-control" name="comments" rows="4" 
                                  placeholder="Add any comments about this appraisal..."></textarea>
                        <small class="text-muted">These comments will be visible to all subsequent approvers and the employee.</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg"
                                onclick="return confirm('Are you sure you want to reject this appraisal? This will send it back for revision.')">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <a href="pending.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Previous Approval Comments -->
        <?php
        $previous_approvals = array_filter($approval_chain, fn($a) => $a['status'] == 'approved' && !empty($a['comments']));
        if (!empty($previous_approvals)):
        ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-chat-left-text"></i> Previous Approval Comments</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($previous_approvals as $prev): ?>
                        <div class="border-start border-primary border-3 ps-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($prev['approver_name']); ?></strong>
                                <small class="text-muted">
                                    Level <?php echo $prev['approval_level']; ?> - 
                                    <?php echo date('M d, Y H:i', strtotime($prev['action_date'])); ?>
                                </small>
                            </div>
                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($prev['comments'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>