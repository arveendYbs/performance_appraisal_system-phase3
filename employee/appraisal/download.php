<?php
// employee/appraisal/download.php
require_once __DIR__ . '/../../config/config.php';

$file_path = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'employee';
if (empty($file_path)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Security check - verify file belongs to user or their appraisal
    $column = $type === 'employee' ? 'employee_attachment' : 'manager_attachment';
    $query = "SELECT r.*, a.user_id, a.appraiser_id, u.direct_superior
          FROM responses r 
          JOIN appraisals a ON r.appraisal_id = a.id
          LEFT JOIN users u ON a.user_id = u.id
          WHERE r.$column = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$file_path]);
    $response = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$response) {
        header("HTTP/1.0 404 Not Found");
        exit;
    }
    
    // Check permissions
    $can_download = false;
    if ($type === 'employee' && $response['user_id'] == $_SESSION['user_id']) {
        $can_download = true;
    } elseif ($type === 'manager' && $response['appraiser_id'] == $_SESSION['user_id']) {
        $can_download = true;
    } elseif (hasRole('admin')) {
        $can_download = true;
    }
    
    if (!$can_download) {
        header("HTTP/1.0 403 Forbidden");
        exit;
    }
    
    $full_path = __DIR__ . '/../../' . $file_path;
    
    if (!file_exists($full_path)) {
        header("HTTP/1.0 404 Not Found");
        exit;
    }
    
    // Set headers for file download
    $filename = basename($full_path);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $full_path);
    finfo_close($finfo);
    
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($full_path));
    header('Cache-Control: no-cache, must-revalidate');
    
    readfile($full_path);

} catch (Exception $e) {
    error_log("File download error: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    exit;
}
?>