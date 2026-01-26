<?php
/**
 * ApprovalChain Class
 * Core logic for building and managing multi-level approval chains
 */
class ApprovalChain {
    private $conn;
    
    // Maximum supported approval levels
    const MAX_APPROVAL_LEVELS = 6;
    
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Build approval chain for an appraisal
     * This is the MAIN function called when employee submits appraisal
     * 
     * @param int $appraisal_id
     * @param int $employee_id
     * @return array Approval chain or false on error
     */
    public function buildApprovalChain($appraisal_id, $employee_id) {
        try {
            // Get employee details
            $employee = $this->getEmployeeDetails($employee_id);
            
            if (!$employee) {
                error_log("ApprovalChain: Employee not found - ID: {$employee_id}");
                return false;
            }
            
            // Get department configuration
            $department = $this->getDepartmentConfig($employee['department_id']);
            
            if (!$department) {
                error_log("ApprovalChain: Department not found - ID: {$employee['department_id']}");
                return false;
            }
            
            // Get position details
            $position = $this->getPositionDetails($employee['position_id']);
            
            if (!$position) {
                error_log("ApprovalChain: Position not found - ID: {$employee['position_id']}");
                return false;
            }
            
            // Check for overrides
            $override = $this->checkOverrides($employee, $position, $department);
            
            // Determine total approval levels needed
            $total_levels = $this->calculateTotalLevels($employee, $position, $department, $override);
            
            // Build the chain
            $chain = $this->generateChain($employee, $department, $total_levels);
            
            // Remove duplicates
            $chain = $this->deduplicateChain($chain);
            
            // Apply overrides (skip levels, add extra approvers, etc.)
            if ($override) {
                $chain = $this->applyOverride($chain, $override, $employee);
            }
            
            // Mark final approver
            if (!empty($chain)) {
                $last_key = array_key_last($chain);
                $chain[$last_key]['is_final_approver'] = true;
            }
            
            // Save to database
            $saved = $this->saveApprovalChain($appraisal_id, $chain);
            
            if ($saved) {
                // Update appraisals table
                $this->updateAppraisalMetadata($appraisal_id, count($chain));
                
                // Log chain creation
                $this->logChainCreation($appraisal_id, $chain);
                
                return $chain;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("ApprovalChain buildApprovalChain error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get employee details with all related info
     */
    private function getEmployeeDetails($employee_id) {
        $query = "SELECT u.id, u.name, u.emp_number, u.company_id, u.position_id, 
                         u.department_id, u.direct_superior, u.is_confirmed,
                         p.employee_type, p.is_management_position
                  FROM users u
                  LEFT JOIN positions p ON u.position_id = p.id
                  WHERE u.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$employee_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get department configuration
     */
    private function getDepartmentConfig($department_id) {
        if (!$department_id) {
            return null;
        }
        
        $query = "SELECT * FROM departments WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$department_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get position details
     */
    private function getPositionDetails($position_id) {
        if (!$position_id) {
            return null;
        }
        
        $query = "SELECT * FROM positions WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$position_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check for applicable overrides
     */
    private function checkOverrides($employee, $position, $department) {
        $query = "SELECT * FROM approval_overrides
                  WHERE company_id = ?
                  AND is_active = TRUE
                  AND (
                      (department_id IS NULL OR department_id = ?)
                      AND (employee_type IS NULL OR employee_type = ?)
                      AND (is_probation IS NULL OR is_probation = ?)
                      AND (specific_user_id IS NULL OR specific_user_id = ?)
                      AND (position_id IS NULL OR position_id = ?)
                  )
                  ORDER BY priority ASC
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $employee['company_id'],
            $employee['department_id'],
            $position['employee_type'] ?? 'office_staff',
            !$employee['is_confirmed'], // is_probation
            $employee['id'],
            $employee['position_id']
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate total approval levels needed
     */
    private function calculateTotalLevels($employee, $position, $department, $override = null) {
        // Check override first
        if ($override && $override['set_approval_levels']) {
            return min($override['set_approval_levels'], self::MAX_APPROVAL_LEVELS);
        }
        
        // Get base level from department config based on employee type
        $employee_type = $position['employee_type'] ?? 'office_staff';
        $base_levels = $this->getApprovalLevelsFromDepartment($department, $employee_type);
        
        // Check probation limit
        if (!$employee['is_confirmed'] && $department['probation_approval_levels']) {
            $base_levels = min($base_levels, $department['probation_approval_levels']);
        }
        
        return min($base_levels, self::MAX_APPROVAL_LEVELS);
    }

    /**
     * Get approval levels from department based on employee type
     */
    private function getApprovalLevelsFromDepartment($department, $employee_type) {
        switch ($employee_type) {
            case 'office_staff':
                return $department['staff_approval_levels'] ?? 2;
            case 'production_worker':
                return $department['worker_approval_levels'] ?? 5;
            case 'supervisor':
                return $department['supervisor_approval_levels'] ?? 3;
            case 'manager':
                return $department['manager_approval_levels'] ?? 3;
            case 'executive':
                return $department['executive_approval_levels'] ?? 2;
            default:
                return 2;
        }
    }

    /**
     * Generate approval chain array
     */
    private function generateChain($employee, $department, $total_levels) {
        $chain = [];
        
        // Level 1: ALWAYS direct superior (gives marks)
        if ($employee['direct_superior']) {
            $chain[1] = [
                'level' => 1,
                'approver_id' => $employee['direct_superior'],
                'approver_role' => 'direct_supervisor',
                'can_rate' => true,
                'can_edit_ratings' => false,
                'is_mandatory' => true,
                'sequence_order' => 1,
                'is_final_approver' => false
            ];
        } else {
            error_log("ApprovalChain: WARNING - Employee {$employee['id']} has no direct superior!");
        }
        
        // Levels 2 through $total_levels: From department configuration
        for ($level = 2; $level <= $total_levels; $level++) {
            $approver_field = "level_{$level}_approver_id";
            $role_field = "level_{$level}_role_name";
            
            if (!empty($department[$approver_field])) {
                $chain[$level] = [
                    'level' => $level,
                    'approver_id' => $department[$approver_field],
                    'approver_role' => $department[$role_field] ?? "level_{$level}_approver",
                    'can_rate' => false,
                    'can_edit_ratings' => false,
                    'is_mandatory' => true,
                    'sequence_order' => $level,
                    'is_final_approver' => false
                ];
            } else {
                error_log("ApprovalChain: WARNING - Level {$level} approver not set for department {$department['id']}");
            }
        }
        
        return $chain;
    }

    /**
     * Remove duplicate approvers from chain
     */
    private function deduplicateChain($chain) {
        $seen_approvers = [];
        $deduplicated = [];
        $current_level = 1;
        
        foreach ($chain as $approval) {
            $approver_id = $approval['approver_id'];
            
            // Skip if we've already seen this approver
            if (in_array($approver_id, $seen_approvers)) {
                error_log("ApprovalChain: Skipping duplicate approver ID {$approver_id} at original level {$approval['level']}");
                continue;
            }
            
            // Add to chain with renumbered level
            $approval['level'] = $current_level;
            $approval['sequence_order'] = $current_level;
            $deduplicated[$current_level] = $approval;
            
            $seen_approvers[] = $approver_id;
            $current_level++;
        }
        
        return $deduplicated;
    }

    /**
     * Apply override rules to chain
     */
    private function applyOverride($chain, $override, $employee) {
        // Skip specific levels
        if ($override['skip_level_2']) {
            unset($chain[2]);
        }
        if ($override['skip_level_3']) {
            unset($chain[3]);
        }
        if ($override['skip_level_4']) {
            unset($chain[4]);
        }
        
        // Add additional approver
        if ($override['add_additional_approver_id']) {
            $max_level = !empty($chain) ? max(array_keys($chain)) : 0;
            $chain[$max_level + 1] = [
                'level' => $max_level + 1,
                'approver_id' => $override['add_additional_approver_id'],
                'approver_role' => 'additional_approver',
                'can_rate' => false,
                'can_edit_ratings' => false,
                'is_mandatory' => true,
                'sequence_order' => $max_level + 1,
                'is_final_approver' => false
            ];
        }
        
        // Renumber levels if there are gaps
        return $this->renumberChain($chain);
    }

    /**
     * Renumber chain to remove gaps
     */
    private function renumberChain($chain) {
        $renumbered = [];
        $new_level = 1;
        
        foreach ($chain as $approval) {
            $approval['level'] = $new_level;
            $approval['sequence_order'] = $new_level;
            $renumbered[$new_level] = $approval;
            $new_level++;
        }
        
        return $renumbered;
    }

    /**
     * Save approval chain to database
     */
    private function saveApprovalChain($appraisal_id, $chain) {
        try {
            $this->conn->beginTransaction();
            
            // Delete any existing approvals for this appraisal
            $delete_query = "DELETE FROM appraisal_approvals WHERE appraisal_id = ?";
            $delete_stmt = $this->conn->prepare($delete_query);
            $delete_stmt->execute([$appraisal_id]);
            
            // Insert new approval levels
            $insert_query = "INSERT INTO appraisal_approvals 
                            (appraisal_id, approval_level, approver_id, approver_role, 
                             status, can_rate, can_edit_ratings, is_mandatory, 
                             sequence_order, is_final_approver)
                            VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)";
            
            $insert_stmt = $this->conn->prepare($insert_query);
            
            foreach ($chain as $approval) {
                $insert_stmt->execute([
                    $appraisal_id,
                    $approval['level'],
                    $approval['approver_id'],
                    $approval['approver_role'],
                    $approval['can_rate'] ? 1 : 0,
                    $approval['can_edit_ratings'] ? 1 : 0,
                    $approval['is_mandatory'] ? 1 : 0,
                    $approval['sequence_order'],
                    $approval['is_final_approver'] ? 1 : 0
                ]);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ApprovalChain saveApprovalChain error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update appraisals table with metadata
     */
    private function updateAppraisalMetadata($appraisal_id, $total_levels) {
        $query = "UPDATE appraisals 
                  SET current_approval_level = 1,
                      total_approval_levels = ?,
                      status = 'pending_approval'
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$total_levels, $appraisal_id]);
    }

    /**
     * Log chain creation for audit
     */
    private function logChainCreation($appraisal_id, $chain) {
        $chain_summary = [];
        foreach ($chain as $approval) {
            $chain_summary[] = "L{$approval['level']}:{$approval['approver_id']}({$approval['approver_role']})";
        }
        
        $log_query = "INSERT INTO appraisal_approval_logs 
                      (appraisal_id, approval_level, action, actor_id, new_status, comments)
                      VALUES (?, 0, 'created', 0, 'pending_approval', ?)";
        
        $stmt = $this->conn->prepare($log_query);
        $stmt->execute([
            $appraisal_id,
            'Approval chain created: ' . implode(' → ', $chain_summary)
        ]);
    }

    /**
     * Get current approver for an appraisal
     */
    public function getCurrentApprover($appraisal_id) {
        $query = "SELECT a.current_approval_level, aa.*
                  FROM appraisals a
                  JOIN appraisal_approvals aa ON a.id = aa.appraisal_id 
                      AND a.current_approval_level = aa.approval_level
                  WHERE a.id = ? AND aa.status = 'pending'
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$appraisal_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user can approve this appraisal
     */
    public function canUserApprove($appraisal_id, $user_id) {
        $current_approver = $this->getCurrentApprover($appraisal_id);
        
        if (!$current_approver) {
            return false;
        }
        
        return ($current_approver['approver_id'] == $user_id);
    }

    /**
     * Process approval action (approve/reject)
     */
    public function processApproval($appraisal_id, $user_id, $action, $comments = null) {
        try {
            // Verify user can approve
            if (!$this->canUserApprove($appraisal_id, $user_id)) {
                return ['success' => false, 'message' => 'You are not authorized to approve this appraisal.'];
            }
            
            $current_approver = $this->getCurrentApprover($appraisal_id);
            $current_level = $current_approver['approval_level'];
            
            $this->conn->beginTransaction();
            
            if ($action === 'approve') {
                // Update approval status
                $update_query = "UPDATE appraisal_approvals 
                                SET status = 'approved',
                                    action_date = NOW(),
                                    comments = ?
                                WHERE appraisal_id = ? AND approval_level = ?";
                
                $stmt = $this->conn->prepare($update_query);
                $stmt->execute([$comments, $appraisal_id, $current_level]);
                
                // Check if this is final approver
                if ($current_approver['is_final_approver']) {
                    // Mark appraisal as completed
                    $complete_query = "UPDATE appraisals 
                                      SET status = 'completed',
                                          current_approval_level = ?,
                                          final_approver_id = ?,
                                          final_approval_date = NOW()
                                      WHERE id = ?";
                    
                    $stmt = $this->conn->prepare($complete_query);
                    $stmt->execute([$current_level, $user_id, $appraisal_id]);
                    
                    $message = 'Appraisal approved and completed!';
                } else {
                    // Move to next level
                    $next_level = $current_level + 1;
                    $update_appraisal = "UPDATE appraisals 
                                        SET current_approval_level = ?
                                        WHERE id = ?";
                    
                    $stmt = $this->conn->prepare($update_appraisal);
                    $stmt->execute([$next_level, $appraisal_id]);
                    
                    $message = "Appraisal approved. Moving to level {$next_level}.";
                }
                
            } elseif ($action === 'reject') {
                // Update approval status
                $update_query = "UPDATE appraisal_approvals 
                                SET status = 'rejected',
                                    action_date = NOW(),
                                    comments = ?
                                WHERE appraisal_id = ? AND approval_level = ?";
                
                $stmt = $this->conn->prepare($update_query);
                $stmt->execute([$comments, $appraisal_id, $current_level]);
                
                // Mark appraisal as rejected
                $reject_query = "UPDATE appraisals 
                                SET status = 'rejected'
                                WHERE id = ?";
                
                $stmt = $this->conn->prepare($reject_query);
                $stmt->execute([$appraisal_id]);
                
                $message = 'Appraisal rejected.';
            }
            
            // Log the action
            $log_query = "INSERT INTO appraisal_approval_logs 
                         (appraisal_id, approval_level, action, actor_id, 
                          previous_status, new_status, comments)
                         VALUES (?, ?, ?, ?, 'pending', ?, ?)";
            
            $stmt = $this->conn->prepare($log_query);
            $stmt->execute([
                $appraisal_id,
                $current_level,
                $action,
                $user_id,
                $action === 'approve' ? 'approved' : 'rejected',
                $comments
            ]);
            
            $this->conn->commit();
            
            return ['success' => true, 'message' => $message];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ApprovalChain processApproval error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while processing approval.'];
        }
    }

    /**
     * Get approval chain for an appraisal
     */
    public function getApprovalChain($appraisal_id) {
        $query = "SELECT aa.*, u.name as approver_name, u.emp_number as approver_emp_number
                  FROM appraisal_approvals aa
                  JOIN users u ON aa.approver_id = u.id
                  WHERE aa.appraisal_id = ?
                  ORDER BY aa.sequence_order ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$appraisal_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}