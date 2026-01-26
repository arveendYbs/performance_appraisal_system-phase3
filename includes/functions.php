
<?php
// includes/functions.php
/**
 * Common utility functions
 */

/**
 * Sanitize input data
 */
function sanitize($value) {
    if (is_null($value)) return null;

    // Trim and remove invisible characters, but don't HTML-encode
    $value = trim($value);

    // Optionally, strip tags if you don't allow HTML input
    $value = strip_tags($value);

    return $value;
}


/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 */
function hasRole($required_role) {
    if (!isLoggedIn()) return false;
    
    $user_role = $_SESSION['user_role'] ?? '';
    
    // Admin has access to everything
    if ($user_role === 'admin') return true;
    
    // Check specific role
    return $user_role === $required_role;
}

/**
 * Check if user can manage other users
 * Returns true if user is:
 * - Admin role
 * - HR role
 */
function canManageUsers() {
    if (!isLoggedIn()) return false;
    
    //admin can always manage users 
    if (hasRole('admin')) return true;

    try {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        $user->id = $_SESSION['user_id'];
        $user->readOne();

        return $user->isHR();
    } catch (Exception $e) {
        error_log("canManageUsers error: " . $e->getMessage());
        return false;
    }

}

function debugPDOStatement($query, $params) {
    error_log("=== PDO DEBUG ===");
    error_log("Query: " . $query);
    error_log("Params: " . print_r($params, true));
    
    // Count placeholders
    $question_marks = substr_count($query, '?');
    $named_params = preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $query);
    
    error_log("Question mark placeholders: " . $question_marks);
    error_log("Named placeholders: " . $named_params);
    error_log("Parameter count: " . (is_array($params) ? count($params) : 1));
    error_log("=================");
}

/**
 * Check if user has subordinates (team members)
 */
function hasSubordinates($user_id = null) {
    if (!isLoggedIn()) return false;
    
    $user_id = $user_id ?? $_SESSION['user_id'];
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT COUNT(*) as count FROM users 
                  WHERE direct_superior = ? AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("hasSubordinates error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user can access team/manager features
 * Returns true if user is:
 * - Admin (full access)
 * - Manager role
 * - OR has subordinates reporting to them
 */
function canAccessTeamFeatures($user_id = null) {
    if (!isLoggedIn()) return false;
    
    $user_id = $user_id ?? $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? '';
    
    // Admin has full access
    if ($user_role === 'admin') return true;
    
    // Manager role has access
    if ($user_role === 'manager') return true;
 // Department managers (Operation Manager, Manager in Production, etc.)
    if (isDepartmentManager($user_id)) return true;
    // Anyone with subordinates has access
    return hasSubordinates($user_id);
}


/**
 * Check if user is a department manager/operation manager
 * Returns true if position contains "Manager" or "Operation Manager" 
 * in specific departments
 */
function isDepartmentManager($user_id = null) {
    if (!isLoggedIn()) return false;
    
    $user_id = $user_id ?? $_SESSION['user_id'];
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT position, department FROM users WHERE id = ? AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_info) return false;
        
        $position = strtolower($user_info['position'] ?? '');
        $department = strtolower($user_info['department'] ?? '');
        
        // Check if user is in Production department
        if ($department === 'production') {
            // Check if position contains "manager" or "operation manager"
            if (
                strpos($position, 'operation manager') !== false || 
                strpos($position, 'manager') !== false
            ) {
                return true;
            }
        }
        
        // Add more departments here if needed
        // Example: if ($department === 'sales') { ... }
        
        return false;
    } catch (Exception $e) {
        error_log("isDepartmentManager error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's department
 */
function getUserDepartment($user_id = null) {
    if (!isLoggedIn()) return null;
    
    $user_id = $user_id ?? $_SESSION['user_id'];
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT department FROM users WHERE id = ? AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['department'] ?? null;
    } catch (Exception $e) {
        error_log("getUserDepartment error: " . $e->getMessage());
        return null;
    }
}
/**
 * Redirect with message
 */
function redirect($url, $message = '', $type = 'info') {
    // Start output buffering if it's not already started
    if (!ob_get_level()) {
        ob_start();
    }
    
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    
    // Clear any existing output
    ob_end_clean();
    
    header("Location: " . $url);
    exit();
}

/**
 * Display flash messages
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $alert_class = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        echo '<div class="alert ' . ($alert_class[$type] ?? 'alert-info') . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}
/**
 * Convert text with newlines to bullet points
 */
function formatDescriptionAsBullets($text) {
    if (empty($text)) {
        return '';
    }
    
    // Split by newlines and filter out empty lines
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    
    if (count($lines) <= 1) {
        // Single line - just return as is
        return htmlspecialchars($text);
    }
    
    // Multiple lines - convert to bullet points
    $bullets = '';
    foreach ($lines as $line) {
        if (!empty($line)) {
            $bullets .= '<li>' . htmlspecialchars($line) . '</li>';
        }
    }
    
    return '<ul class="mb-0">' . $bullets . '</ul>';
}
/**
 * Calculate performance grade
 */
function calculateGrade($score) {
    if ($score >= 85) return 'A';
    if ($score >= 75) return 'B+';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'B-';
    return 'C';
}

/**
 * Get grade color class
 */
function getGradeColorClass($grade) {
    switch ($grade) {
        case 'A': return 'text-success';
        case 'B+': return 'text-success';
        case 'B': return 'text-primary';
        case 'B-': return 'text-warning';
        case 'C': return 'text-danger';
        default: return 'text-muted';
    }
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'bg-success';
        case 'submitted': return 'bg-info';
        case 'in_review': return 'bg-warning';
        case 'draft': return 'bg-secondary';
        case 'cancelled': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

/**
 * Log user activity
 */
function logActivity($user_id, $action, $table_name, $record_id = null, $old_values = null, $new_values = null, $details = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $audit = new AuditLog($db);
        $audit->log($user_id, $action, $table_name, $record_id, $old_values, $new_values, $details);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        redirect('/auth/login.php', 'Your session has expired. Please login again.', 'warning');
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $filename = pathinfo($original_filename, PATHINFO_FILENAME);
    return $filename . '_' . uniqid() . '.' . $extension;
}

/* 
check if user is top management

*/

function isTopManagement() {
    if (!isLoggedIn()) return false;
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        $user->id = $_SESSION['user_id'];
        $user->readOne();

        return $user->isTopManagement();
    } catch (Exception $e) {
        error_log("isTopManagement error: " . $e->getMessage());
        return false;
    }
}
?>