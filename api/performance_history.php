
<?php
// api/performance_history.php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!hasRole('manager') && !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$user_id = $_GET['user_id'] ?? 0;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify the user is in the manager's team
    $team_query = "SELECT name FROM users WHERE id = ? AND direct_superior = ? AND is_active = 1";
    $stmt = $db->prepare($team_query);
    $stmt->execute([$user_id, $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found or not in your team']);
        exit;
    }
    
    // Get appraisal history
    $history_query = "SELECT id, status, grade, total_score, appraisal_period_from, appraisal_period_to, 
                             employee_submitted_at, manager_reviewed_at
                      FROM appraisals 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC";
    
    $stmt = $db->prepare($history_query);
    $stmt->execute([$user_id]);
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formatted_appraisals = array_map(function($appraisal) {
        return [
            'id' => $appraisal['id'],
            'period' => formatDate($appraisal['appraisal_period_from'], 'M Y') . ' - ' . formatDate($appraisal['appraisal_period_to'], 'M Y'),
            'status' => $appraisal['status'],
            'grade' => $appraisal['grade'],
            'total_score' => $appraisal['total_score'],
            'submitted_at' => $appraisal['employee_submitted_at'],
            'reviewed_at' => $appraisal['manager_reviewed_at']
        ];
    }, $appraisals);
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'appraisals' => $formatted_appraisals
    ]);
    
} catch (Exception $e) {
    error_log("Performance history API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>