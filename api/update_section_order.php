
<?php
// api/update_section_order.php
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

$sections = $_POST['sections'] ?? [];

if (empty($sections) || !is_array($sections)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid sections data']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    foreach ($sections as $section) {
        $query = "UPDATE form_sections SET section_order = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$section['order'], $section['id']]);
    }
    
    $db->commit();
    
    // Log the activity
    logActivity($_SESSION['user_id'], 'UPDATE', 'form_sections', null, null, $sections, 
               'Updated section order');
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $db->rollback();
    error_log("Update section order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order']);
}
?>
