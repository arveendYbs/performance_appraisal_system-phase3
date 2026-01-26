
<?php
class AuditLog {
    private $conn;
    private $table_name = "audit_logs";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Log activity
     */
    public function log($user_id, $action, $table_name, $record_id = null, 
                       $old_values = null, $new_values = null, $details = null) {
        
        $query = "INSERT INTO " . $this->table_name . "
                  SET user_id = :user_id, action = :action, table_name = :table_name,
                      record_id = :record_id, old_values = :old_values, 
                      new_values = :new_values, details = :details,
                      ip_address = :ip_address, user_agent = :user_agent";

        $stmt = $this->conn->prepare($query);
        
        // Get client information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Convert arrays to JSON strings and store in variables
        $old_values_json = json_encode($old_values);
        $new_values_json = json_encode($new_values);
        
        // Bind parameters using variables
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':table_name', $table_name);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindParam(':old_values', $old_values_json);  // Now binding the variable
        $stmt->bindParam(':new_values', $new_values_json);  // Now binding the variable
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);

        return $stmt->execute();
    }

    /**
     * Get audit logs with pagination
     */
    public function read($page = 1, $records_per_page = RECORDS_PER_PAGE) {
        $from_record_num = ($records_per_page * $page) - $records_per_page;

        $query = "SELECT al.*, u.name as user_name
                  FROM " . $this->table_name . " al
                  JOIN users u ON al.user_id = u.id
                  ORDER BY al.created_at DESC
                  LIMIT :from_record_num, :records_per_page";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
        $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }
}
?>