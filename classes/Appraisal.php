
<?php
class Appraisal {
    private $conn;
    private $table_name = "appraisals";

    public $id;
    public $user_id;
    public $form_id;
    public $appraiser_id;
    public $appraisal_period_from;
    public $appraisal_period_to;
    public $status;
    public $total_score;
    public $performance_score;
    public $grade;
    public $overall_comments;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new appraisal
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET user_id = :user_id, form_id = :form_id, 
                      appraisal_period_from = :period_from, 
                      appraisal_period_to = :period_to,
                      status = 'draft'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':form_id', $this->form_id);
        $stmt->bindParam(':period_from', $this->appraisal_period_from);
        $stmt->bindParam(':period_to', $this->appraisal_period_to);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->user_id = $row['user_id'];
            $this->form_id = $row['form_id'];
            $this->appraiser_id = $row['appraiser_id'];
            $this->appraisal_period_from = $row['appraisal_period_from'];
            $this->appraisal_period_to = $row['appraisal_period_to'];
            $this->status = $row['status'];
            $this->total_score = $row['total_score'];
            $this->performance_score = $row['performance_score'];
            $this->grade = $row['grade'];
            return true;
        }
        return false;
    }
    /**
     * Get user's current appraisal
     */
    public function getCurrentAppraisal($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  AND status IN ('draft', 'submitted', 'in_review')
                  ORDER BY created_at DESC 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->form_id = $row['form_id'];
            $this->appraiser_id = $row['appraiser_id'];
            $this->appraisal_period_from = $row['appraisal_period_from'];
            $this->appraisal_period_to = $row['appraisal_period_to'];
            $this->status = $row['status'];
            $this->total_score = $row['total_score'];
            $this->performance_score = $row['performance_score'];
            $this->grade = $row['grade'];
            return true;
        }
        return false;
    }

/**
     * Get ALL questions with responses for manager review
     * This includes questions even if employee hasn't answered
     */
    public function getAllResponsesForReview() {
        $query = "SELECT fq.id as question_id, fq.question_text, fq.response_type, fq.options,
                         fs.section_title, fs.section_order, fq.question_order,
                         r.employee_response, r.employee_rating, r.employee_comments, r.employee_attachment,
                         r.manager_response, r.manager_rating, r.manager_comments, r.manager_attachment
                  FROM form_questions fq
                  JOIN form_sections fs ON fq.section_id = fs.id
                  JOIN appraisals a ON fs.form_id = a.form_id
                  LEFT JOIN responses r ON (fq.id = r.question_id AND r.appraisal_id = a.id)
                  WHERE a.id = :appraisal_id
                  ORDER BY fs.section_order, fq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':appraisal_id', $this->id);
        $stmt->execute();

        return $stmt;
    }

public function saveSectionComment($section_id, $comment) {
    try {
        // Check if comment exists
        $check_query = "SELECT id FROM section_comments 
                       WHERE appraisal_id = :appraisal_id 
                       AND section_id = :section_id";
        
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':appraisal_id', $this->id);
        $check_stmt->bindParam(':section_id', $section_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            // Update existing comment
            $query = "UPDATE section_comments 
                     SET comment = :comment,
                         updated_at = NOW()
                     WHERE appraisal_id = :appraisal_id 
                     AND section_id = :section_id";
        } else {
            // Insert new comment
            $query = "INSERT INTO section_comments 
                     (appraisal_id, section_id, comment, created_at)
                     VALUES 
                     (:appraisal_id, :section_id, :comment, NOW())";
        }
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':appraisal_id', $this->id);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->bindParam(':comment', $comment);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error saving section comment: " . $e->getMessage());
        return false;
    }
}

public function getSectionComments() {
    $query = "SELECT * FROM section_comments 
              WHERE appraisal_id = :appraisal_id";
              
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':appraisal_id', $this->id);
    $stmt->execute();
    
    return $stmt;
}
    
    /**
     * Get pending appraisals for manager
     */
    public function getPendingForManager($manager_id) {
        $query = "SELECT a.id, a.user_id, a.appraisal_period_from, a.appraisal_period_to, 
                         a.status, a.employee_submitted_at, u.name, u.emp_number, u.position
                  FROM " . $this->table_name . " a
                  JOIN users u ON a.user_id = u.id
                  WHERE u.direct_superior = :manager_id 
                  AND a.status IN ('submitted', 'in_review')
                  ORDER BY a.employee_submitted_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':manager_id', $manager_id);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Update appraisal status
     */
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status";

        if ($status === 'submitted') {
            $query .= ", employee_submitted_at = NOW()";
        } elseif ($status === 'completed') {
            $query .= ", manager_reviewed_at = NOW()";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
    public function saveResponse($question_id, $employee_response = null, $employee_rating = null, 
                             $employee_comments = null, $manager_response = null, 
                             $manager_rating = null, $manager_comments = null) {
    try {
        $query = "INSERT INTO responses 
                  (appraisal_id, question_id, employee_response, employee_rating, 
                   employee_comments, manager_response, manager_rating, manager_comments)
                  VALUES (:appraisal_id, :question_id, :employee_response, :employee_rating,
                          :employee_comments, :manager_response, :manager_rating, :manager_comments)
                  ON DUPLICATE KEY UPDATE
                  employee_response = VALUES(employee_response),
                  employee_rating = VALUES(employee_rating),
                  employee_comments = VALUES(employee_comments),
                  manager_response = VALUES(manager_response),
                  manager_rating = VALUES(manager_rating),
                  manager_comments = VALUES(manager_comments)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':appraisal_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':question_id', $question_id, PDO::PARAM_INT);
        $stmt->bindValue(':employee_response', $employee_response);
        $stmt->bindValue(':employee_rating', $employee_rating);
        $stmt->bindValue(':employee_comments', $employee_comments);
        $stmt->bindValue(':manager_response', $manager_response);
        $stmt->bindValue(':manager_rating', $manager_rating);
        $stmt->bindValue(':manager_comments', $manager_comments);

        $ok = $stmt->execute();
        if (!$ok) {
            error_log("saveResponse SQL error: " . print_r($stmt->errorInfo(), true));
        }
        return $ok;
    } catch (Exception $e) {
        error_log("Error saving response: " . $e->getMessage());
        return false;
    }
}
/**
     * Save response with attachment support
     */
    public function saveResponseWithAttachment($question_id, $employee_response = null, $employee_rating = null, 
                                             $employee_comments = null, $employee_attachment = null,
                                             $manager_response = null, $manager_rating = null, 
                                             $manager_comments = null, $manager_attachment = null) {
        
        $query = "INSERT INTO responses 
                  (appraisal_id, question_id, employee_response, employee_rating, 
                   employee_comments, employee_attachment, manager_response, manager_rating, 
                   manager_comments, manager_attachment)
                  VALUES (:appraisal_id, :question_id, :employee_response, :employee_rating,
                          :employee_comments, :employee_attachment, :manager_response, :manager_rating, 
                          :manager_comments, :manager_attachment)
                  ON DUPLICATE KEY UPDATE
                  employee_response = CASE WHEN VALUES(employee_response) IS NOT NULL THEN VALUES(employee_response) ELSE employee_response END,
                  employee_rating = CASE WHEN VALUES(employee_rating) IS NOT NULL THEN VALUES(employee_rating) ELSE employee_rating END,
                  employee_comments = CASE WHEN VALUES(employee_comments) IS NOT NULL THEN VALUES(employee_comments) ELSE employee_comments END,
                  employee_attachment = CASE WHEN VALUES(employee_attachment) IS NOT NULL THEN VALUES(employee_attachment) ELSE employee_attachment END,
                  manager_response = CASE WHEN VALUES(manager_response) IS NOT NULL THEN VALUES(manager_response) ELSE manager_response END,
                  manager_rating = CASE WHEN VALUES(manager_rating) IS NOT NULL THEN VALUES(manager_rating) ELSE manager_rating END,
                  manager_comments = CASE WHEN VALUES(manager_comments) IS NOT NULL THEN VALUES(manager_comments) ELSE manager_comments END,
                  manager_attachment = CASE WHEN VALUES(manager_attachment) IS NOT NULL THEN VALUES(manager_attachment) ELSE manager_attachment END";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':appraisal_id', $this->id);
        $stmt->bindParam(':question_id', $question_id);
        $stmt->bindParam(':employee_response', $employee_response);
        $stmt->bindParam(':employee_rating', $employee_rating);
        $stmt->bindParam(':employee_comments', $employee_comments);
        $stmt->bindParam(':employee_attachment', $employee_attachment);
        $stmt->bindParam(':manager_response', $manager_response);
        $stmt->bindParam(':manager_rating', $manager_rating);
        $stmt->bindParam(':manager_comments', $manager_comments);
        $stmt->bindParam(':manager_attachment', $manager_attachment);

        return $stmt->execute();
    }

    /**
     * Get form structure filtered by visibility
     */
    public function getFormStructureFiltered($viewer_type = 'both') {
        $query = "SELECT fs.id as section_id, fs.section_title, fs.section_description, 
                         fs.section_order, fs.visible_to,
                         fq.id as question_id, fq.question_text, fq.question_description,
                         fq.response_type, fq.options, fq.is_required, fq.question_order
                  FROM form_sections fs
                  LEFT JOIN form_questions fq ON fs.id = fq.section_id AND fq.is_active = 1
                  WHERE fs.form_id = :form_id AND fs.is_active = 1
                  AND (fs.visible_to = 'both' OR fs.visible_to = :viewer_type)
                  ORDER BY fs.section_order, fq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':form_id', $this->form_id);
        $stmt->bindParam(':viewer_type', $viewer_type);
        $stmt->execute();

        $structure = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $section_id = $row['section_id'];
            
            if (!isset($structure[$section_id])) {
                $structure[$section_id] = [
                    'id' => $section_id,
                    'title' => $row['section_title'],
                    'description' => $row['section_description'],
                    'order' => $row['section_order'],
                    'visible_to' => $row['visible_to'],
                    'questions' => []
                ];
            }

            if ($row['question_id']) {
                $structure[$section_id]['questions'][] = [
                    'id' => $row['question_id'],
                    'text' => $row['question_text'],
                    'description' => $row['question_description'],
                    'response_type' => $row['response_type'],
                    'options' => !is_null($row['options']) ? json_decode($row['options'], true) : null,
                    'is_required' => $row['is_required'],
                    'order' => $row['question_order']
                ];
            }
        }

        return array_values($structure);
    }
    /**
     * Get appraisal responses
     */
    public function getResponses() {
        $query = "SELECT r.*, fq.question_text, fq.response_type, fs.section_title
                  FROM responses r
                  JOIN form_questions fq ON r.question_id = fq.id
                  JOIN form_sections fs ON fq.section_id = fs.id
                  WHERE r.appraisal_id = :appraisal_id
                  ORDER BY fs.section_order, fq.question_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':appraisal_id', $this->id);
        $stmt->execute();

        return $stmt;
    }

    /**
 * Get appraisals visible to HR user
 * HR can see all appraisals from their assigned companies
 */
public function getAppraisalsForHR($hr_user_id, $status = null) {
    $query = "SELECT a.*, 
                     u.name as employee_name, u.emp_number, u.position, u.department,
                     c.name as company_name,
                     m.name as manager_name,
                     f.title as form_title
              FROM " . $this->table_name . " a
              JOIN users u ON a.user_id = u.id
              JOIN companies c ON u.company_id = c.id
              JOIN hr_companies hc ON c.id = hc.company_id
              LEFT JOIN users m ON a.appraiser_id = m.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE hc.user_id = ?";
    
    if ($status) {
        $query .= " AND a.status = ?";
    }
    
    $query .= " ORDER BY a.created_at DESC";

    $stmt = $this->conn->prepare($query);
    
    if ($status) {
        $stmt->execute([$hr_user_id, $status]);
    } else {
        $stmt->execute([$hr_user_id]);
    }

    return $stmt;
}

/**
 * Check if HR user can view specific appraisal
 */
public function canHRView($appraisal_id, $hr_user_id) {
    $query = "SELECT COUNT(*) as count
              FROM " . $this->table_name . " a
              JOIN users u ON a.user_id = u.id
              JOIN hr_companies hc ON u.company_id = hc.company_id
              WHERE a.id = ? AND hc.user_id = ?";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$appraisal_id, $hr_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
}



/**
 * Initialize approval chain when appraisal is submitted
 * Call this in employee/appraisal/submit.php
 */
public function initializeApprovalChain() {
    try {
        require_once __DIR__ . '/ApprovalChain.php';
        
        $approvalChain = new ApprovalChain($this->conn);
        $chain = $approvalChain->buildApprovalChain($this->id, $this->user_id);
        
        if ($chain) {
            // Update appraisal status to submitted
            $query = "UPDATE appraisals 
                      SET status = 'submitted',
                          employee_submitted_at = NOW()
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$this->id]);
            
            // Send notification to Level 1 approver
            $this->notifyApprover(1);
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Appraisal initializeApprovalChain error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get approval chain for this appraisal
 */
public function getApprovalChain() {
    $query = "SELECT aa.*, 
                     u.name as approver_name, 
                     u.emp_number as approver_emp_number,
                     u.email as approver_email,
                     u.emp_email as approver_emp_email
              FROM appraisal_approvals aa
              JOIN users u ON aa.approver_id = u.id
              WHERE aa.appraisal_id = ?
              ORDER BY aa.approval_level ASC";
    
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->id]);
    
    return $stmt;
}

/**
 * Get current approval level details
 */
public function getCurrentApprovalLevel() {
    $query = "SELECT a.current_approval_level, a.total_approval_levels,
                     aa.*, 
                     u.name as approver_name,
                     u.emp_number as approver_emp_number
              FROM appraisals a
              LEFT JOIN appraisal_approvals aa ON a.id = aa.appraisal_id 
                  AND a.current_approval_level = aa.approval_level
              LEFT JOIN users u ON aa.approver_id = u.id
              WHERE a.id = ?";
    
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check if user can approve this appraisal at current level
 */
public function canUserApprove($user_id) {
    $current = $this->getCurrentApprovalLevel();
    
    if (!$current || !isset($current['approver_id'])) {
        return false;
    }
    
    return ($current['approver_id'] == $user_id && $current['status'] == 'pending');
}

/**
 * Check if user can rate (only Level 1)
 */
public function canUserRate($user_id) {
    $current = $this->getCurrentApprovalLevel();
    
    if (!$current) {
        return false;
    }
    
    return ($current['approver_id'] == $user_id && 
            $current['can_rate'] == 1 && 
            $current['status'] == 'pending');
}

/**
 * Process approval action
 */
public function processApproval($user_id, $action, $comments = null) {
    require_once __DIR__ . '/ApprovalChain.php';
    
    $approvalChain = new ApprovalChain($this->conn);
    $result = $approvalChain->processApproval($this->id, $user_id, $action, $comments);
    
    if ($result['success']) {
        // Send notification to next approver or employee (if completed/rejected)
        $this->sendApprovalNotification($action);
    }
    
    return $result;
}

/**
 * Get approval history/logs
 */
public function getApprovalLogs() {
    $query = "SELECT al.*, 
                     u.name as actor_name,
                     u.emp_number as actor_emp_number
              FROM appraisal_approval_logs al
              LEFT JOIN users u ON al.actor_id = u.id
              WHERE al.appraisal_id = ?
              ORDER BY al.created_at DESC";
    
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->id]);
    
    return $stmt;
}

/**
 * Send notification to approver at specific level
 */
private function notifyApprover($level) {
    // Get approver at this level
    $query = "SELECT aa.approver_id, aa.approver_role,
                     u.name, u.email, u.emp_email,
                     emp.name as employee_name, emp.emp_number
              FROM appraisal_approvals aa
              JOIN users u ON aa.approver_id = u.id
              JOIN appraisals a ON aa.appraisal_id = a.id
              JOIN users emp ON a.user_id = emp.id
              WHERE aa.appraisal_id = ? AND aa.approval_level = ?";
    
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->id, $level]);
    $approver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$approver) {
        return false;
    }
    
    // Send email notification
    $to_email = !empty($approver['emp_email']) ? $approver['emp_email'] : $approver['email'];
    $subject = "Appraisal Approval Required - {$approver['employee_name']}";
    
    $level_text = $level == 1 ? "review and rate" : "approve";
    
    $message = "Dear {$approver['name']},\n\n";
    $message .= "You have a pending appraisal to {$level_text}.\n\n";
    $message .= "Employee: {$approver['employee_name']} ({$approver['emp_number']})\n";
    $message .= "Your Role: " . ucwords(str_replace('_', ' ', $approver['approver_role'])) . "\n";
    $message .= "Approval Level: {$level}\n\n";
    $message .= "Please login to the system to complete your review.\n";
    $message .= BASE_URL . "/manager/approvals/pending.php\n\n";
    $message .= "Thank you.";
    
    $headers = "From: " . SMTP_FROM . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM. "\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

/**
 * Send notification after approval action
 */
private function sendApprovalNotification($action) {
    $current_level = $this->getCurrentApprovalLevel();
    
    if ($action === 'approve' && !$current_level['is_final_approver']) {
        // Notify next level approver
        $this->notifyApprover($current_level['approval_level'] + 1);
    } elseif ($action === 'approve' && $current_level['is_final_approver']) {
        // Notify employee - appraisal completed
        $this->notifyEmployeeCompletion();
    } elseif ($action === 'reject') {
        // Notify employee - appraisal rejected
        $this->notifyEmployeeRejection($current_level['comments']);
    }
}

/**
 * Notify employee of completion
 */
private function notifyEmployeeCompletion() {
    $query = "SELECT u.name, u.email, u.emp_email, u.emp_number
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              WHERE a.id = ?";
    
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        return false;
    }
    
    $to_email = !empty($employee['emp_email']) ? $employee['emp_email'] : $employee['email'];
    $subject = "Appraisal Completed - All Approvals Received";
    
    $message = "Dear {$employee['name']},\n\n";
    $message .= "Your appraisal has been completed and approved by all required levels.\n\n";
    $message .= "You can now view your final appraisal results.\n";
    $message .= BASE_URL . "/employee/appraisal/view.php?id={$this->id}\n\n";
    $message .= "Thank you.";
    
    $headers = "From: " . SMTP_FROM . "\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

/**
 * Notify employee of rejection
 */
private function notifyEmployeeRejection($reason) {
    $query = "SELECT u.name, u.email, u.emp_email, u.emp_number
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              WHERE a.id = ?";
    
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        return false;
    }
    
    $to_email = !empty($employee['emp_email']) ? $employee['emp_email'] : $employee['email'];
    $subject = "Appraisal Rejected - Action Required";
    
    $message = "Dear {$employee['name']},\n\n";
    $message .= "Your appraisal has been rejected and requires revision.\n\n";
    if ($reason) {
        $message .= "Reason: {$reason}\n\n";
    }
    $message .= "Please review the feedback and resubmit.\n";
    $message .= BASE_URL . "/employee/appraisal/edit.php?id={$this->id}\n\n";
    $message .= "Thank you.";
    
    $headers = "From: " . SMTP_FROM . "\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

/**
 * Get appraisals pending approval by user
 */
public static function getPendingApprovalsForUser($db, $user_id) {
    $query = "SELECT a.*, 
                     u.name as employee_name,
                     u.emp_number,
                     u.position,
                     u.department,
                     c.name as company_name,
                     f.title as form_title,
                     aa.approval_level,
                     aa.approver_role,
                     aa.can_rate
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              JOIN companies c ON u.company_id = c.id
              LEFT JOIN forms f ON a.form_id = f.id
              JOIN appraisal_approvals aa ON a.id = aa.appraisal_id 
                  AND a.current_approval_level = aa.approval_level
              WHERE aa.approver_id = ?
              AND aa.status = 'pending'
              AND a.status IN ('submitted', 'pending_approval')
              ORDER BY a.employee_submitted_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    
    return $stmt;
}
}