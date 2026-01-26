<?php
/**
 * Company Model - NEW CLASS
 */
class Company {
    private $conn;
    private $table_name = "companies";

    public $id;
    public $name;
    public $code;
    public $is_active;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all active companies
     */
    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE is_active = 1 
                  ORDER BY name";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Get all companies (including inactive)
     */
    public function getAllIncludingInactive() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Get company by ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->name = $row['name'];
            $this->code = $row['code'];
            $this->is_active = $row['is_active'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }

        return false;
    }

    /**
     * Create new company
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (name, code) VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->name, $this->code]);
    }

    /**
     * Update company
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name = ?, code = ?, is_active = ? 
                  WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->name, $this->code, $this->is_active, $this->id]);
    }

    /**
     * Get employee count by company
     */
    public function getEmployeeCount() {
        $query = "SELECT COUNT(*) as count FROM users 
                  WHERE company_id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    /**
     * Get appraisal statistics for company
     */
    public function getAppraisalStats() {
        $query = "SELECT 
                    COUNT(*) as total_appraisals,
                    SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                    SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
                    SUM(CASE WHEN a.status = 'in_review' THEN 1 ELSE 0 END) as in_review_count,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                  FROM appraisals a
                  JOIN users u ON a.user_id = u.id
                  WHERE u.company_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if company code exists
     */
    public function codeExists($code, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE code = ?";
        
        if ($exclude_id) {
            $query .= " AND id != ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$code, $exclude_id]);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$code]);
        }
        
        return $stmt->rowCount() > 0;
    }
}