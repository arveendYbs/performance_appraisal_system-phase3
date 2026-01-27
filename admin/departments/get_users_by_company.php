<?php
// admin/departments/get_departments_by_company.php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if (!canManageUsers()) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$company_id = $_GET['company_id'] ?? 0;

if (!$company_id) {
    echo json_encode([]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, department_name, department_code 
              FROM departments 
              WHERE company_id = ? AND is_active = 1
              ORDER BY department_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$company_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($departments);
    
} catch (Exception $e) {
    error_log("Get departments by company error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load departments']);
}