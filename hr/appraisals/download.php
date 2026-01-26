<?php
// employee/appraisal/download.php
require_once __DIR__ . '/../../config/config.php';

$file_path = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'employee';

if (empty($file_path)) {
    header("HTTP/1.0 404 Not Found");
    echo "File not found";
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Security check - verify file belongs to user or their appraisal
    $column = $type === 'employee' ? 'employee_attachment' : 'manager_attachment';
    
    $query = "SELECT r.*, a.user_id, a.appraiser_id
              FROM responses r 
              JOIN appraisals a ON r.appraisal_id = a.id
              WHERE r.$column = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$file_path]);
    $response = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$response) {
        header("HTTP/1.0 404 Not Found");
        echo "File not found in database";
        exit;
    }
    
    // Check permissions
    $can_download = false;
    if ($type === 'employee' && $response['user_id'] == $_SESSION['user_id']) {
        $can_download = true; // Employee can download their own files
    } elseif ($type === 'employee' || $response['appraiser_id'] == $_SESSION['user_id']) {
        $can_download = true; // Manager can download employee files
    } elseif ($type === 'manager' || $response['appraiser_id'] == $_SESSION['user_id']) {
        $can_download = true; // Manager can download their own files
    } elseif (hasRole('admin')) {
        $can_download = true; // Admin can download any file
    }
    
    if (!$can_download) {
        header("HTTP/1.0 403 Forbidden");
        echo "Access denied";
        exit;
    }
    
    // Build full file path - try both possible locations
    $full_paths = [
        __DIR__ . '/../../' . $file_path,
        __DIR__ . '/../../../' . $file_path,  // In case of different directory structure
        realpath(__DIR__ . '/../../' . $file_path)
    ];
    
    $full_path = null;
    foreach ($full_paths as $path) {
        if ($path && file_exists($path)) {
            $full_path = $path;
            break;
        }
    }
    
    if (!$full_path || !file_exists($full_path)) {
        header("HTTP/1.0 404 Not Found");
        echo "Physical file not found at: " . htmlspecialchars($file_path);
        echo "<br>Checked paths:<br>";
        foreach ($full_paths as $path) {
            echo "- " . htmlspecialchars($path) . " (exists: " . (file_exists($path) ? 'yes' : 'no') . ")<br>";
        }
        exit;
    }
    
    // Set headers for file download
    $filename = basename($full_path);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $full_path);
    finfo_close($finfo);
    
    // Fallback for common types
    if (!$mime_type) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime_types = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'txt' => 'text/plain'
        ];
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
    }
    
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($full_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($full_path);
    
} catch (Exception $e) {
    error_log("File download error: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    echo "Server error: " . htmlspecialchars($e->getMessage());
    exit;
}
?>