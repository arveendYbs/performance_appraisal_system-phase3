<?php
// hr/reports/export_company_excel.php

// CRITICAL: Start output buffering and error handling FIRST
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/export_errors.log');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/export_functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Clear any output that might have been generated
ob_clean();

// --- Authentication ---
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    die('Unauthorized access');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isHR()) {
    ob_end_clean();
    die('Access denied. HR personnel only.');
}

// --- Get parameters ---
$company_id = $_GET['company'] ?? '';
$year = $_GET['year'] ?? date('Y');
$export_type = $_GET['type'] ?? 'detailed'; // 'detailed', 'summary', or 'comprehensive'

if (empty($company_id)) {
    ob_end_clean();
    die('Company ID is required');
}

// Verify HR access
$hr_companies = $user->getHRCompanies();
$has_access = false;
$company_name = '';
foreach ($hr_companies as $company) {
    if ($company['id'] == $company_id) {
        $has_access = true;
        $company_name = $company['name'];
        break;
    }
}

if (!$has_access) {
    ob_end_clean();
    die('No access to this company\'s data');
}

try {
    // --- Fetch completed appraisals ---
    $query = "SELECT 
                a.id as appraisal_id,
                a.form_id,
                a.user_id,
                a.appraisal_period_from,
                a.appraisal_period_to,
                a.grade,
                a.total_score,
                a.employee_submitted_at,
                a.manager_reviewed_at,
                u.name as employee_name,
                u.emp_number,
                u.position,
                u.department,
                u.site,
                u.date_joined,
                u.role,
                m.name as manager_name,
                f.title as form_title
              FROM appraisals a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN users m ON u.direct_superior = m.id
              LEFT JOIN forms f ON a.form_id = f.id
              WHERE u.company_id = ? 
              AND a.status = 'completed' 
              AND YEAR(a.appraisal_period_from) = ? 
              ORDER BY u.department, u.name";

    $stmt = $db->prepare($query);
    $stmt->execute([$company_id, $year]);
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($appraisals)) {
        ob_end_clean();
        die('No completed appraisals found for this company in ' . $year);
    }

    // --- Create Spreadsheet ---
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    // --- Generate based on export type ---
    if ($export_type === 'comprehensive') {
        generateComprehensiveExport($spreadsheet, $appraisals, $company_name, $year, $db);
        $filename = 'Appraisals_Comprehensive_' . preg_replace('/[^a-zA-Z0-9]/', '_', $company_name) . '_' . $year . '_' . date('Ymd') . '.xlsx';
    } elseif ($export_type === 'summary') {
        generateSummaryExport($spreadsheet, $appraisals, $company_name, $year, $db);
        $filename = 'Appraisals_Summary_' . preg_replace('/[^a-zA-Z0-9]/', '_', $company_name) . '_' . $year . '_' . date('Ymd') . '.xlsx';
    } else {
        generateDetailedExport($spreadsheet, $appraisals, $company_name, $year, $db);
        $filename = 'Appraisals_Detailed_' . preg_replace('/[^a-zA-Z0-9]/', '_', $company_name) . '_' . $year . '_' . date('Ymd') . '.xlsx';
    }

    // --- Output file ---
    $spreadsheet->setActiveSheetIndex(0);
    
    // Clear all output buffers
    ob_end_clean();
    
    // Remove any previously sent headers
    if (headers_sent($file, $line)) {
        error_log("Headers already sent in $file on line $line");
        die("Cannot generate Excel file - headers already sent");
    }
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    ob_end_clean();
    error_log("Excel export PDO error: " . $e->getMessage());
    die('Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    ob_end_clean();
    error_log("Excel export error: " . $e->getMessage());
    die('Error generating Excel file: ' . $e->getMessage());
}