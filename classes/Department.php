<?php
/**
 * Department Model
 * Manages organizational departments and approval configurations
 */
class Department {
    private $conn;
    private $table_name = "departments";

    public $id;
    public $company_id;
    public $department_name;
    public $department_code;
    public $parent_department_id;
    
    // Approval chain approvers (Levels 2-6)
    public $level_2_approver_id;
    public $level_2_role_name;
    public $level_3_approver_id;
    public $level_3_role_name;
    public $level_4_approver_id;
    public $level_4_role_name;
    public $level_5_approver_id;
    public $level_5_role_name;
    public $level_6_approver_id;
    public $level_6_role_name;
    
    // Approval levels by employee type
    public $staff_approval_levels;
    public $worker_approval_levels;
    public $supervisor_approval_levels;
    public $manager_approval_levels;
    public $executive_approval_levels;
    public $probation_approval_levels;
    
    public $cost_center;
    public $location;
    public $is_active;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all active departments
     */
    public function getAll($company_id = null) {
        $query = "SELECT d.*, 
                         c.name as company_name,
                         parent.department_name as parent_department_name,
                         l2.name as level_2_approver_name,
                         l3.name as level_3_approver_name,
                         l4.name as level_4_approver_name,
                         l5.name as level_5_approver_name,
                         l6.name as level_6_approver_name
                  FROM " . $this->table_name . " d
                  JOIN companies c ON d.company_id = c.id
                  LEFT JOIN departments parent ON d.parent_department_id = parent.id
                  LEFT JOIN users l2 ON d.level_2_approver_id = l2.id
                  LEFT JOIN users l3 ON d.level_3_approver_id = l3.id
                  LEFT JOIN users l4 ON d.level_4_approver_id = l4.id
                  LEFT JOIN users l5 ON d.level_5_approver_id = l5.id
                  LEFT JOIN users l6 ON d.level_6_approver_id = l6.id
                  WHERE d.is_active = 1";
        
        if ($company_id) {
            $query .= " AND d.company_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$company_id]);
        } else {
            $query .= " ORDER BY c.name, d.department_name";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }
        
        return $stmt;
    }

    /**
     * Get department by ID
     */
    public function readOne() {
        $query = "SELECT d.*, c.name as company_name
                  FROM " . $this->table_name . " d
                  JOIN companies c ON d.company_id = c.id
                  WHERE d.id = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->company_id = $row['company_id'];
            $this->department_name = $row['department_name'];
            $this->department_code = $row['department_code'];
            $this->parent_department_id = $row['parent_department_id'];
            
            $this->level_2_approver_id = $row['level_2_approver_id'];
            $this->level_2_role_name = $row['level_2_role_name'];
            $this->level_3_approver_id = $row['level_3_approver_id'];
            $this->level_3_role_name = $row['level_3_role_name'];
            $this->level_4_approver_id = $row['level_4_approver_id'];
            $this->level_4_role_name = $row['level_4_role_name'];
            $this->level_5_approver_id = $row['level_5_approver_id'];
            $this->level_5_role_name = $row['level_5_role_name'];
            $this->level_6_approver_id = $row['level_6_approver_id'];
            $this->level_6_role_name = $row['level_6_role_name'];
            
            $this->staff_approval_levels = $row['staff_approval_levels'];
            $this->worker_approval_levels = $row['worker_approval_levels'];
            $this->supervisor_approval_levels = $row['supervisor_approval_levels'];
            $this->manager_approval_levels = $row['manager_approval_levels'];
            $this->executive_approval_levels = $row['executive_approval_levels'];
            $this->probation_approval_levels = $row['probation_approval_levels'];
            
            $this->cost_center = $row['cost_center'];
            $this->location = $row['location'];
            $this->is_active = $row['is_active'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Create new department
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      department_name = :department_name,
                      department_code = :department_code,
                      parent_department_id = :parent_department_id,
                      level_2_approver_id = :level_2_approver_id,
                      level_2_role_name = :level_2_role_name,
                      level_3_approver_id = :level_3_approver_id,
                      level_3_role_name = :level_3_role_name,
                      level_4_approver_id = :level_4_approver_id,
                      level_4_role_name = :level_4_role_name,
                      level_5_approver_id = :level_5_approver_id,
                      level_5_role_name = :level_5_role_name,
                      level_6_approver_id = :level_6_approver_id,
                      level_6_role_name = :level_6_role_name,
                      staff_approval_levels = :staff_approval_levels,
                      worker_approval_levels = :worker_approval_levels,
                      supervisor_approval_levels = :supervisor_approval_levels,
                      manager_approval_levels = :manager_approval_levels,
                      executive_approval_levels = :executive_approval_levels,
                      probation_approval_levels = :probation_approval_levels,
                      cost_center = :cost_center,
                      location = :location";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(':company_id', $this->company_id);
        $stmt->bindParam(':department_name', $this->department_name);
        $stmt->bindParam(':department_code', $this->department_code);
        $stmt->bindParam(':parent_department_id', $this->parent_department_id);
        $stmt->bindParam(':level_2_approver_id', $this->level_2_approver_id);
        $stmt->bindParam(':level_2_role_name', $this->level_2_role_name);
        $stmt->bindParam(':level_3_approver_id', $this->level_3_approver_id);
        $stmt->bindParam(':level_3_role_name', $this->level_3_role_name);
        $stmt->bindParam(':level_4_approver_id', $this->level_4_approver_id);
        $stmt->bindParam(':level_4_role_name', $this->level_4_role_name);
        $stmt->bindParam(':level_5_approver_id', $this->level_5_approver_id);
        $stmt->bindParam(':level_5_role_name', $this->level_5_role_name);
        $stmt->bindParam(':level_6_approver_id', $this->level_6_approver_id);
        $stmt->bindParam(':level_6_role_name', $this->level_6_role_name);
        $stmt->bindParam(':staff_approval_levels', $this->staff_approval_levels);
        $stmt->bindParam(':worker_approval_levels', $this->worker_approval_levels);
        $stmt->bindParam(':supervisor_approval_levels', $this->supervisor_approval_levels);
        $stmt->bindParam(':manager_approval_levels', $this->manager_approval_levels);
        $stmt->bindParam(':executive_approval_levels', $this->executive_approval_levels);
        $stmt->bindParam(':probation_approval_levels', $this->probation_approval_levels);
        $stmt->bindParam(':cost_center', $this->cost_center);
        $stmt->bindParam(':location', $this->location);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    /**
     * Update department
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET department_name = :department_name,
                      department_code = :department_code,
                      parent_department_id = :parent_department_id,
                      level_2_approver_id = :level_2_approver_id,
                      level_2_role_name = :level_2_role_name,
                      level_3_approver_id = :level_3_approver_id,
                      level_3_role_name = :level_3_role_name,
                      level_4_approver_id = :level_4_approver_id,
                      level_4_role_name = :level_4_role_name,
                      level_5_approver_id = :level_5_approver_id,
                      level_5_role_name = :level_5_role_name,
                      level_6_approver_id = :level_6_approver_id,
                      level_6_role_name = :level_6_role_name,
                      staff_approval_levels = :staff_approval_levels,
                      worker_approval_levels = :worker_approval_levels,
                      supervisor_approval_levels = :supervisor_approval_levels,
                      manager_approval_levels = :manager_approval_levels,
                      executive_approval_levels = :executive_approval_levels,
                      probation_approval_levels = :probation_approval_levels,
                      cost_center = :cost_center,
                      location = :location,
                      is_active = :is_active
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(':department_name', $this->department_name);
        $stmt->bindParam(':department_code', $this->department_code);
        $stmt->bindParam(':parent_department_id', $this->parent_department_id);
        $stmt->bindParam(':level_2_approver_id', $this->level_2_approver_id);
        $stmt->bindParam(':level_2_role_name', $this->level_2_role_name);
        $stmt->bindParam(':level_3_approver_id', $this->level_3_approver_id);
        $stmt->bindParam(':level_3_role_name', $this->level_3_role_name);
        $stmt->bindParam(':level_4_approver_id', $this->level_4_approver_id);
        $stmt->bindParam(':level_4_role_name', $this->level_4_role_name);
        $stmt->bindParam(':level_5_approver_id', $this->level_5_approver_id);
        $stmt->bindParam(':level_5_role_name', $this->level_5_role_name);
        $stmt->bindParam(':level_6_approver_id', $this->level_6_approver_id);
        $stmt->bindParam(':level_6_role_name', $this->level_6_role_name);
        $stmt->bindParam(':staff_approval_levels', $this->staff_approval_levels);
        $stmt->bindParam(':worker_approval_levels', $this->worker_approval_levels);
        $stmt->bindParam(':supervisor_approval_levels', $this->supervisor_approval_levels);
        $stmt->bindParam(':manager_approval_levels', $this->manager_approval_levels);
        $stmt->bindParam(':executive_approval_levels', $this->executive_approval_levels);
        $stmt->bindParam(':probation_approval_levels', $this->probation_approval_levels);
        $stmt->bindParam(':cost_center', $this->cost_center);
        $stmt->bindParam(':location', $this->location);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    /**
     * Get employee count for this department
     */
    public function getEmployeeCount() {
        $query = "SELECT COUNT(*) as count FROM users 
                  WHERE department_id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] ?? 0;
    }

    /**
     * Get approver at specific level
     */
    public function getApproverAtLevel($level) {
        if ($level < 2 || $level > 6) {
            return null;
        }
        
        $field_name = "level_{$level}_approver_id";
        return $this->$field_name ?? null;
    }

    /**
     * Get role name at specific level
     */
    public function getRoleNameAtLevel($level) {
        if ($level < 2 || $level > 6) {
            return null;
        }
        
        $field_name = "level_{$level}_role_name";
        return $this->$field_name ?? "level_{$level}_approver";
    }

    /**
     * Get approval levels for employee type
     */
    public function getApprovalLevelsForType($employee_type) {
        switch ($employee_type) {
            case 'office_staff':
                return $this->staff_approval_levels ?? 2;
            case 'production_worker':
                return $this->worker_approval_levels ?? 5;
            case 'supervisor':
                return $this->supervisor_approval_levels ?? 3;
            case 'manager':
                return $this->manager_approval_levels ?? 3;
            case 'executive':
                return $this->executive_approval_levels ?? 2;
            default:
                return 2;
        }
    }
}