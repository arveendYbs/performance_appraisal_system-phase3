<?php
// api/update_question_order.php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$questions = $_POST['questions'] ?? [];

if (empty($questions) || !is_array($questions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid questions data']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    foreach ($questions as $question) {
        $query = "UPDATE form_questions SET question_order = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$question['order'], $question['id']]);
    }
    
    $db->commit();
    
    // Log the activity
    logActivity($_SESSION['user_id'], 'UPDATE', 'form_questions', null, null, $questions, 
               'Updated question order');
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $db->rollback();
    error_log("Update question order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order']);
}
?>