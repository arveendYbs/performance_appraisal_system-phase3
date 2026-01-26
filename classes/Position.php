<?php
/**
 * Position Model
 * Manages standardized job positions
 */
class Position {
    private $conn;
    private $table_name = "positions";

    public $id;
    public $company_id;
    public $position_title;
    public $position_code;
    public $employee_type;
    public $is_management_position;
    public $department_id;
    public $job_description;
    public $min_salary;
    public $max_salary;
    public $requires_probation;
    public $default_probation_months;
    public $is_active;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all active positions
     */
    public function getAll($company_id = null) {
        $query = "SELECT p.*, 
                         c.name as company_name,
                         d.department_name
                  FROM " . $this->table_name . " p
                  JOIN companies c ON p.company_id = c.id
                  LEFT JOIN departments d ON p.department_id = d.id
                  WHERE p.is_active = 1";
        
        if ($company_id) {
            $query .= " AND p.company_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$company_id]);
        } else {
            $query .= " ORDER BY c.name, p.position_title";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        }
        
        return $stmt;
    }

    /**
     * Get position by ID
     */
    public function readOne() {
        $query = "SELECT p.*, c.name as company_name, d.department_name
                  FROM " . $this->table_name . " p
                  JOIN companies c ON p.company_id = c.id
                  LEFT JOIN departments d ON p.department_id = d.id
                  WHERE p.id = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->company_id = $row['company_id'];
            $this->position_title = $row['position_title'];
            $this->position_code = $row['position_code'];
            $this->employee_type = $row['employee_type'];
            $this->is_management_position = $row['is_management_position'];
            $this->department_id = $row['department_id'];
            $this->job_description = $row['job_description'];
            $this->min_salary = $row['min_salary'];
            $this->max_salary = $row['max_salary'];
            $this->requires_probation = $row['requires_probation'];
            $this->default_probation_months = $row['default_probation_months'];
            $this->is_active = $row['is_active'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }

    /**
     * Create new position
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      position_title = :position_title,
                      position_code = :position_code,
                      employee_type = :employee_type,
                      is_management_position = :is_management_position,
                      department_id = :department_id,
                      job_description = :job_description,
                      min_salary = :min_salary,
                      max_salary = :max_salary,
                      requires_probation = :requires_probation,
                      default_probation_months = :default_probation_months";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':company_id', $this->company_id);
        $stmt->bindParam(':position_title', $this->position_title);
        $stmt->bindParam(':position_code', $this->position_code);
        $stmt->bindParam(':employee_type', $this->employee_type);
        $stmt->bindParam(':is_management_position', $this->is_management_position);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':job_description', $this->job_description);
        $stmt->bindParam(':min_salary', $this->min_salary);
        $stmt->bindParam(':max_salary', $this->max_salary);
        $stmt->bindParam(':requires_probation', $this->requires_probation);
        $stmt->bindParam(':default_probation_months', $this->default_probation_months);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    /**
     * Update position
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET position_title = :position_title,
                      position_code = :position_code,
                      employee_type = :employee_type,
                      is_management_position = :is_management_position,
                      department_id = :department_id,
                      job_description = :job_description,
                      min_salary = :min_salary,
                      max_salary = :max_salary,
                      requires_probation = :requires_probation,
                      default_probation_months = :default_probation_months,
                      is_active = :is_active
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':position_title', $this->position_title);
        $stmt->bindParam(':position_code', $this->position_code);
        $stmt->bindParam(':employee_type', $this->employee_type);
        $stmt->bindParam(':is_management_position', $this->is_management_position);
        $stmt->bindParam(':department_id', $this->department_id);
        $stmt->bindParam(':job_description', $this->job_description);
        $stmt->bindParam(':min_salary', $this->min_salary);
        $stmt->bindParam(':max_salary', $this->max_salary);
        $stmt->bindParam(':requires_probation', $this->requires_probation);
        $stmt->bindParam(':default_probation_months', $this->default_probation_months);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    /**
     * Get employee count for this position
     */
    public function getEmployeeCount() {
        $query = "SELECT COUNT(*) as count FROM users 
                  WHERE position_id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] ?? 0;
    }
}