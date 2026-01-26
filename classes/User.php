
<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $emp_number;
    public $email;
    public $emp_email;
    public $position;
    public $direct_superior;
    public $department;
    public $date_joined;
    public $site;
    public $role;
    public $password;
    public $is_active;
    public $created_at;
    public $updated_at;
    public $company_id;
    public $is_hr;
    public $is_confirmed;
    public $is_top_management;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Authenticate user with email and password
     */
    public function login($email, $password) {
        $query = "SELECT id, name, emp_number, email, emp_email, position, direct_superior, 
                         department, date_joined, site, role, password, is_active
                  FROM " . $this->table_name . " 
                  WHERE (email = ? OR emp_email = ?) AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email, $email]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->emp_number = $row['emp_number'];
                $this->email = $row['email'];
                $this->emp_email = $row['emp_email'];
                $this->position = $row['position'];
                $this->direct_superior = $row['direct_superior'];
                $this->department = $row['department'];
                $this->date_joined = $row['date_joined'];
                $this->site = $row['site'];
                $this->role = $row['role'];
                $this->is_active = $row['is_active'];
                
                return true;
            }
        }
        return false;
    }

    /**
     * UPDATED - Create new user (include company_id and is_hr)
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  (name, emp_number, email, emp_email, position, direct_superior, 
                   department, date_joined, site, role, company_id, is_hr, is_confirmed, is_top_management, password)
                  VALUES (:name, :emp_number, :email, :emp_email, :position, 
                          :direct_superior, :department, :date_joined, :site, :role, 
                          :company_id, :is_hr, :is_confirmed, :is_top_management, :password)";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->name = sanitize($this->name);
        $this->emp_number = sanitize($this->emp_number);
        $this->email = sanitize($this->email);
        $this->emp_email = sanitize($this->emp_email);
        $this->position = sanitize($this->position);
        $this->department = sanitize($this->department);
        $this->site = sanitize($this->site);

        // Bind parameters
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':emp_number', $this->emp_number);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':emp_email', $this->emp_email);
        $stmt->bindParam(':position', $this->position);
        $stmt->bindParam(':direct_superior', $this->direct_superior);
        $stmt->bindParam(':department', $this->department);
        $stmt->bindParam(':date_joined', $this->date_joined);
        $stmt->bindParam(':site', $this->site);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':company_id', $this->company_id);
        $stmt->bindParam(':is_hr', $this->is_hr);
        $stmt->bindParam(':is_confirmed', $this->is_confirmed);
        $stmt->bindParam(':is_top_management', $this->is_top_management);

        $hashed_password = password_hash($this->password, HASH_ALGO);
        $stmt->bindParam(':password', $hashed_password);

        return $stmt->execute();
    }


     /**
     * UPDATED - Read all users (include company_id and is_hr)
     */
    public function read($page = 1, $records_per_page = 10) {
        $from_record_num = ($page - 1) * $records_per_page;

        $query = "SELECT u.id, u.name, u.emp_number, u.email, u.emp_email, u.position, 
                         u.direct_superior, u.department, u.date_joined, u.site, u.role, 
                         u.company_id, u.is_hr, u.is_confirmed, u.is_active, u.created_at,
                         s.name as superior_name, c.name as company_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN " . $this->table_name . " s ON u.direct_superior = s.id
                  LEFT JOIN companies c ON u.company_id = c.id
                  ORDER BY u.name ASC
                  LIMIT :from_record_num, :records_per_page";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
        $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Count total users
     */
    public function count() {
        $query = "SELECT COUNT(*) as total_rows FROM " . $this->table_name . " WHERE is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'];
    }

    /**
     * UPDATED - Read one user (include company_id and is_hr)
     */
    public function readOne() {
        $query = "SELECT u.id, u.name, u.emp_number, u.email, u.emp_email,u.password, u.position, 
                         u.direct_superior, u.department, u.date_joined, u.site, u.role, 
                         u.company_id, u.is_hr, u.is_confirmed, u.is_active, u.created_at, u.updated_at,
                         u.is_top_management,
                         s.name as superior_name, c.name as company_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN " . $this->table_name . " s ON u.direct_superior = s.id
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.id = ?
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->name = $row['name'];
            $this->emp_number = $row['emp_number'];
            $this->email = $row['email'];
            $this->emp_email = $row['emp_email'];
            $this->password = $row['password'];
            $this->position = $row['position'];
            $this->direct_superior = $row['direct_superior'];
            $this->department = $row['department'];
            $this->date_joined = $row['date_joined'];
            $this->site = $row['site'];
            $this->role = $row['role'];
            $this->company_id = $row['company_id'];
            $this->is_hr = $row['is_hr'];
            $this->is_confirmed = $row['is_confirmed'];
            $this->is_active = $row['is_active'];
            $this->is_top_management = $row['is_top_management'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }

        return false;
    }


    /**
     * UPDATED - Update user (include company_id and is_hr)
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET name = :name, emp_number = :emp_number, email = :email, 
                      emp_email = :emp_email, position = :position,
                      direct_superior = :direct_superior, department = :department,
                      date_joined = :date_joined, site = :site, role = :role,
                      company_id = :company_id, is_hr = :is_hr, is_active = :is_active,
                      is_confirmed = :is_confirmed, is_top_management = :is_top_management
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->name = sanitize($this->name);
        $this->emp_number = sanitize($this->emp_number);
        $this->email = sanitize($this->email);
        $this->emp_email = sanitize($this->emp_email);
        $this->position = sanitize($this->position);
        $this->department = sanitize($this->department);
        $this->site = sanitize($this->site);

        // Bind parameters
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':emp_number', $this->emp_number);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':emp_email', $this->emp_email);
        $stmt->bindParam(':position', $this->position);
        $stmt->bindParam(':direct_superior', $this->direct_superior);
        $stmt->bindParam(':department', $this->department);
        $stmt->bindParam(':date_joined', $this->date_joined);
        $stmt->bindParam(':site', $this->site);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':company_id', $this->company_id);
        $stmt->bindParam(':is_hr', $this->is_hr);
        $stmt->bindParam(':is_confirmed', $this->is_confirmed);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':is_top_management', $this->is_top_management);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Update password
     */
    public function updatePassword($new_password) {
        $query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($new_password, HASH_ALGO);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    /**
     * Delete user (soft delete - set inactive)
     */
    public function delete() {
        $query = "UPDATE " . $this->table_name . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    /**
     * Get subordinates (team members)
     */
    public function getSubordinates() {
        $query = "SELECT id, name, emp_number, position, department, email, is_active 
                  FROM " . $this->table_name . " 
                  WHERE direct_superior = :id AND is_active = 1
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        $query = "SELECT id, name, emp_number, position, department 
                  FROM " . $this->table_name . " 
                  WHERE role = :role AND is_active = 1
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Get potential supervisors (admin and managers)
     */
    public function getPotentialSupervisors() {
        $query = "SELECT id, name, position, department 
                  FROM " . $this->table_name . " 
                  WHERE role IN ('admin', 'manager') AND is_active = 1
                  AND id != :id
                  ORDER BY name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Search users
     */
    public function search($search_term, $page = 1, $records_per_page = RECORDS_PER_PAGE) {
        $from_record_num = ($records_per_page * $page) - $records_per_page;
        
        $query = "SELECT u.id, u.name, u.emp_number, u.email, u.position, 
                         u.department, u.site, u.role, u.is_active,
                         s.name as superior_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN " . $this->table_name . " s ON u.direct_superior = s.id
                  WHERE (u.name LIKE :search OR u.emp_number LIKE :search 
                         OR u.email LIKE :search OR u.position LIKE :search
                         OR u.department LIKE :search)
                  ORDER BY u.name ASC
                  LIMIT :from_record_num, :records_per_page";

        $stmt = $this->conn->prepare($query);
        
        $search_param = "%{$search_term}%";
        $stmt->bindParam(':search', $search_param);
        $stmt->bindParam(':from_record_num', $from_record_num, PDO::PARAM_INT);
        $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Check if email exists
     */
    /* public function emailExists($email, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE (email = :email OR emp_email = :email)";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } */
    public function emailExists($email, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " 
                WHERE (email = ? OR emp_email = ?)";
        
        $params = [$email, $email];
        
        if ($exclude_id) {
            $query .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if employee number exists
     */
    public function empNumberExists($emp_number, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE emp_number = :emp_number";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':emp_number', $emp_number);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Get user statistics
     */
    public function getStats() {
        $stats = [];
        
        // Total users
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total'];
        
        // Users by role
        $query = "SELECT role, COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE is_active = 1 GROUP BY role";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Users by department
        $query = "SELECT department, COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE is_active = 1 GROUP BY department ORDER BY count DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }

    /**
     * Get user's full hierarchy path
     */
    public function getHierarchyPath() {
        $path = [];
        $current_id = $this->id;
        
        while ($current_id) {
            $query = "SELECT id, name, position, direct_superior FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $current_id);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) break;
            
            array_unshift($path, $user);
            $current_id = $user['direct_superior'];
        }
        
        return $path;
    }

    /**
     * Check if user is HR
     */
    public function isHR() {
        return $this->is_hr == 1;
    }

    /**
     * Get companies assigned to HR user
     */
    public function getHRCompanies() {
        if (!$this->isHR()) {
            return [];
        }

        $query = "SELECT c.id, c.name, c.code
                  FROM hr_companies hc
                  JOIN companies c ON hc.company_id = c.id
                  WHERE hc.user_id = ? AND c.is_active = 1
                  ORDER BY c.name";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign HR user to a company
     */
    public function assignToCompany($company_id) {
        if (!$this->isHR()) {
            return false;
        }

        $query = "INSERT INTO hr_companies (user_id, company_id) 
                  VALUES (?, ?) 
                  ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->id, $company_id]);
    }

    /**
     * Remove HR user from a company
     */
    public function removeFromCompany($company_id) {
        $query = "DELETE FROM hr_companies WHERE user_id = ? AND company_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->id, $company_id]);
    }

    /**
     * Sync HR user companies (remove old, add new)
     */
    public function syncHRCompanies($company_ids) {
        if (!$this->isHR()) {
            return false;
        }

        try {
            // Start transaction
            $this->conn->beginTransaction();

            // Remove all existing assignments
            $delete_query = "DELETE FROM hr_companies WHERE user_id = ?";
            $delete_stmt = $this->conn->prepare($delete_query);
            $delete_stmt->execute([$this->id]);

            // Add new assignments
            if (!empty($company_ids)) {
                $insert_query = "INSERT INTO hr_companies (user_id, company_id) VALUES (?, ?)";
                $insert_stmt = $this->conn->prepare($insert_query);

                foreach ($company_ids as $company_id) {
                    $insert_stmt->execute([$this->id, $company_id]);
                }
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Sync HR companies error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all HR users for a specific company
     */
    public static function getHRByCompany($db, $company_id) {
        $query = "SELECT DISTINCT u.id, u.name, u.email, u.emp_number, u.position
                  FROM hr_companies hc
                  JOIN users u ON hc.user_id = u.id
                  WHERE hc.company_id = ? AND u.is_hr = TRUE AND u.is_active = TRUE
                  ORDER BY u.name";

        $stmt = $db->prepare($query);
        $stmt->execute([$company_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if HR user can view appraisals from a specific company
     */
    public function canViewCompany($company_id) {
        if (!$this->isHR()) {
            return false;
        }

        $query = "SELECT COUNT(*) as count FROM hr_companies 
                  WHERE user_id = ? AND company_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id, $company_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    /**
     * Get all users from companies HR is responsible for
     */
    public function getHRVisibleUsers() {
        if (!$this->isHR()) {
            return [];
        }

        $query = "SELECT DISTINCT u.id, u.name, u.emp_number, u.position, 
                         u.department, u.site, c.name as company_name
                  FROM users u
                  JOIN companies c ON u.company_id = c.id
                  JOIN hr_companies hc ON c.id = hc.company_id
                  WHERE hc.user_id = ? AND u.is_active = 1
                  ORDER BY c.name, u.name";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all HR users (for admin management)
     */
    public static function getAllHRUsers($db) {
        $query = "SELECT u.id, u.name, u.emp_number, u.email, u.position, 
                         u.department, c.name as company_name,
                         GROUP_CONCAT(DISTINCT hc_comp.name SEPARATOR ', ') as hr_companies
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  LEFT JOIN hr_companies hc ON u.id = hc.user_id
                  LEFT JOIN companies hc_comp ON hc.company_id = hc_comp.id
                  WHERE u.is_hr = TRUE AND u.is_active = TRUE
                  GROUP BY u.id
                  ORDER BY u.name";

        $stmt = $db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all potential supervisors excluding a specific user
     */
    public function getAllPotentialSupervisors($exclude_id = null) {
        $query = "SELECT u.id, u.name, u.position, u.department, u.emp_number,
                         c.name as company_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN companies c ON u.company_id = c.id
                  WHERE u.is_active = 1";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $query .= " ORDER BY u.name ASC";
        
        $stmt = $this->conn->prepare($query);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        
        return $stmt;
    }
  
    /* 
    check if user is top management 
    */
    
    public function isTopManagement() {
        return $this->is_top_management == 1;
    }

    /* 
    get companies assigned to top management user 
    */
    public function getTopManagementCompanies() {
        if (!$this->isTopManagement()) {
            return [];
        }

        $query = "SELECT c.id, c.name, c.code
                  FROM top_management_companies tmc
                  JOIN companies c ON tmc.company_id = c.id
                  WHERE tmc.user_id = ? AND c.is_active = 1
                  ORDER BY c.name";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* 
    Assign Top Management user to a company 
    */
    public function assignTopManagementToCompany($company_id)
    {
        if (!$this->isTopManagement()) {
            return false;
        }

        $query = "INSERT INTO top_management_companies (user_id, company_id) 
                  VALUES (?, ?) 
                  ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->id, $company_id]);

    }

    /* 
    Remove Top management user from a company
    */
    public function removeTopManagementFromCompany($company_id)
    {
        $query = "DELETE FROM top_management_companies WHERE user_id = ? AND company_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->id, $company_id]);
    }

    /* 
    Get all Top Management users for (admin management)

    */

    public static function getAllTopManagementUsers($db)
    {
        $query = "SELECT u.id, u.name, u.emp_number, u.email, u.position, 
                         u.department, c.name as company_name,
                         GROUP_CONCAT(DISTINCT tmc_comp.name SEPARATOR ', ') as top_management_companies
                  FROM users u
                  LEFT JOIN companies c ON u.company_id = c.id
                  LEFT JOIN top_management_companies tmc ON u.id = tmc.user_id
                  LEFT JOIN companies tmc_comp ON tmc.company_id = tmc_comp.id
                  WHERE u.is_top_management = TRUE AND u.is_active = TRUE
                  GROUP BY u.id
                  ORDER BY u.name";

        $stmt = $db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*  generate password */
    public function generatePasswordResetToken($email)
    {
        // Check if user exists
        $query = "SELECT id FROM " . $this->table_name . " 
                WHERE (email = ? OR emp_email = ?) AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email, $email]);
        
        if ($stmt->rowCount() == 0) {
            return false;
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $user['id'];
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Delete any existing unused tokens for this user
        $delete_query = "DELETE FROM password_resets WHERE user_id = ? AND used = 0";
        $delete_stmt = $this->conn->prepare($delete_query);
        $delete_stmt->execute([$user_id]);
        
        // Insert new token
        $insert_query = "INSERT INTO password_resets (user_id, email, token, expires_at) 
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))";
        $insert_stmt = $this->conn->prepare($insert_query);
        
       if ($insert_stmt->execute([$user_id, $email, $token])) {
            return $token;
        }
        return false;
    }

        
    /**
     * Verify password reset token
     */
    public function verifyResetToken($token) {
        $query = "SELECT pr.*, u.id as user_id, u.name, u.email, u.emp_email
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token = ? 
                AND pr.used = 0 
                AND pr.expires_at > NOW()
                AND u.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$token]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

        
    /**
     * Reset password using token
     */
    public function resetPassword($token, $new_password) {
        // Verify token
        $reset_data = $this->verifyResetToken($token);
        
        if (!$reset_data) {
            return false;
        }
        
        $user_id = $reset_data['user_id'];
        
        try {
            $this->conn->beginTransaction();
            
            // Update password
            $hashed_password = password_hash($new_password, HASH_ALGO);
            $update_query = "UPDATE " . $this->table_name . " SET password = ? WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->execute([$hashed_password, $user_id]);
            
            // Mark token as used
            $mark_used_query = "UPDATE password_resets SET used = 1 WHERE token = ?";
            $mark_stmt = $this->conn->prepare($mark_used_query);
            $mark_stmt->execute([$token]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            return false;
        }
    }


}