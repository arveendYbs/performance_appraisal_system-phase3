<?php
// hr/reports/generate_excel_php.php - Fixed column indexing
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    die('Unauthorized access');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isHR() && $user->role !== 'admin') {
    ob_end_clean();
    die('Access denied');
}

$user_id = $_GET['user_id'] ?? 0;
$year = $_GET['year'] ?? date('Y');

if (!$user_id) {
    ob_end_clean();
    die('User ID is required');
}

try {
    // Get user information
    $user_query = "SELECT u.*, c.name as company_name
                   FROM users u
                   LEFT JOIN companies c ON u.company_id = c.id
                   WHERE u.id = ?";
    $stmt = $db->prepare($user_query);
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        ob_end_clean();
        die('Employee not found');
    }
    
    // Get appraisals
    $appraisals_query = "SELECT a.*, f.title as form_title
                         FROM appraisals a
                         LEFT JOIN forms f ON a.form_id = f.id
                         WHERE a.user_id = ?
                         AND YEAR(a.appraisal_period_from) = ?
                         AND a.status = 'completed'
                         ORDER BY a.appraisal_period_from";
    
    $stmt = $db->prepare($appraisals_query);
    $stmt->execute([$user_id, $year]);
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($appraisals)) {
        ob_end_clean();
        die('No completed appraisals found');
    }
    
    // Process appraisals data
    $appraisals_data = [];
    foreach ($appraisals as $appraisal) {
        $appraisal_id = $appraisal['id'];
        
        // Get question scores
        $questions_query = "SELECT 
                                r.employee_rating,
                                r.manager_rating,
                                fq.question_order
                           FROM responses r
                           JOIN form_questions fq ON r.question_id = fq.id
                           JOIN form_sections fs ON fq.section_id = fs.id
                           WHERE r.appraisal_id = ?
                           AND fq.response_type IN ('rating_5', 'rating_10')
                           AND fs.section_order = 2
                           ORDER BY fq.question_order
                           LIMIT 12";
        
        $stmt = $db->prepare($questions_query);
        $stmt->execute([$appraisal_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $question_scores = [];
        for ($i = 0; $i < 12; $i++) {
            if (isset($questions[$i])) {
                $question_scores[] = [
                    'employee_rating' => $questions[$i]['employee_rating'] ?? 0,
                    'manager_rating' => $questions[$i]['manager_rating'] ?? 0
                ];
            } else {
                $question_scores[] = [
                    'employee_rating' => 0,
                    'manager_rating' => 0
                ];
            }
        }
        
        // Get training needs
        $training_query = "SELECT r.employee_response
                          FROM responses r
                          JOIN form_questions fq ON r.question_id = fq.id
                          JOIN form_sections fs ON fq.section_id = fs.id
                          WHERE r.appraisal_id = ?
                          AND fs.section_title LIKE '%Training%'
                          AND fq.response_type = 'checkbox'
                          AND r.employee_response IS NOT NULL
                          AND r.employee_response != ''";
        
        $stmt = $db->prepare($training_query);
        $stmt->execute([$appraisal_id]);
        $training_responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $training_needs = [];
        foreach ($training_responses as $response) {
            if (!empty($response['employee_response'])) {
                $items = explode(',', $response['employee_response']);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item) && !in_array($item, $training_needs)) {
                        $training_needs[] = $item;
                    }
                }
            }
        }
        
        $appraisals_data[] = [
            'form_title' => $appraisal['form_title'],
            'period_from' => $appraisal['appraisal_period_from'],
            'period_to' => $appraisal['appraisal_period_to'],
            'questions' => $question_scores,
            'training_needs' => $training_needs
        ];
    }
    
    // Create Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($year . ' Report');
    
    $row = 1;
    
    // Title
    $sheet->setCellValue('A1', "Summary of {$year} Appraisal Ratings - " . ($employee['company_name'] ?? 'N/A'));
    $sheet->mergeCells('A1:R1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    
    $row = 3;
    
    // Section headers
    $sheet->mergeCells("J{$row}:U{$row}");
    $sheet->setCellValue("J{$row}", 'Performance Assessment - Employee Scores');
    $sheet->getStyle("J{$row}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    $sheet->mergeCells("Y{$row}:AJ{$row}");
    $sheet->setCellValue("Y{$row}", 'Performance Assessment - Manager Scores');
    $sheet->getStyle("Y{$row}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    $sheet->mergeCells("AN{$row}:AW{$row}");
    $sheet->setCellValue("AN{$row}", 'Training & Development Needs');
    $sheet->getStyle("AN{$row}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    $row++;
    
    // Column headers
    $headers = [
        'Company', 'Dept', 'Name', 'Staff No.', 'Form', 'Role', 
        'Position', 'Date Joined', 'Period'
    ];
    for ($i = 1; $i <= 12; $i++) $headers[] = "Q{$i}";
    $headers = array_merge($headers, ['Total', 'Score', 'Rating']);
    for ($i = 1; $i <= 12; $i++) $headers[] = "Q{$i}";
    $headers = array_merge($headers, ['Total', 'Score', 'Final Rating']);
    for ($i = 1; $i <= 10; $i++) $headers[] = "T{$i}";
    
    // Write headers
    $colNum = 1; // Start from 1, not 0
    foreach ($headers as $header) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum);
        $sheet->setCellValue($colLetter . $row, $header);
        $sheet->getStyle($colLetter . $row)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '366092']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $colNum++;
    }
    
    $row++;
    
    // Write data rows
    foreach ($appraisals_data as $appraisal) {
        $questions = $appraisal['questions'];
        
        // Build row data array
        $rowData = [
            $employee['company_name'] ?? '',
            $employee['department'] ?? '',
            $employee['name'] ?? '',
            $employee['emp_number'] ?? '',
            $appraisal['form_title'] ?? '',
            ucfirst($employee['role'] ?? ''),
            $employee['position'] ?? '',
            $employee['date_joined'] ?? '',
            ($appraisal['period_from'] ?? '') . ' to ' . ($appraisal['period_to'] ?? '')
        ];
        
        // Employee scores (12 questions)
        for ($i = 0; $i < 12; $i++) {
            $score = isset($questions[$i]) ? ($questions[$i]['employee_rating'] ?? 0) : 0;
            $rowData[] = $score ?: '';
        }
        
        // Employee calculations
        $rowData[] = "=SUM(J{$row}:U{$row})";
        
        $role = strtolower($employee['role'] ?? 'employee');
        $divisor = (strpos($role, 'manager') !== false || strpos($role, 'admin') !== false) ? 1.2 : 
                   ((strpos($role, 'worker') !== false) ? 0.8 : 1);
        
        $rowData[] = "=ROUND(V{$row}/{$divisor},0)";
        $rowData[] = "=IF(W{$row}=0,\"\",IF(W{$row}<50,\"C\",IF(W{$row}<60,\"B-\",IF(W{$row}<75,\"B\",IF(W{$row}<85,\"B+\",\"A\")))))";
        
        // Manager scores (12 questions)
        for ($i = 0; $i < 12; $i++) {
            $score = isset($questions[$i]) ? ($questions[$i]['manager_rating'] ?? 0) : 0;
            $rowData[] = $score ?: '';
        }
        
        // Manager calculations
        $rowData[] = "=SUM(Y{$row}:AJ{$row})";
        $rowData[] = "=ROUND(AK{$row}/{$divisor},0)";
        $rowData[] = "=IF(AL{$row}=0,\"\",IF(AL{$row}<50,\"C\",IF(AL{$row}<60,\"B-\",IF(AL{$row}<75,\"B\",IF(AL{$row}<85,\"B+\",\"A\")))))";
        
        // Training (10 columns)
        $training = $appraisal['training_needs'];
        for ($i = 0; $i < 10; $i++) {
            $rowData[] = isset($training[$i]) ? $training[$i] : '';
        }
        
        // Write entire row
        $colNum = 1; // IMPORTANT: Start from 1
        foreach ($rowData as $value) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum);
            $sheet->setCellValue($colLetter . $row, $value);
            $colNum++;
        }
        
        $row++;
    }
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(10);
    $sheet->getColumnDimension('G')->setWidth(18);
    $sheet->getColumnDimension('H')->setWidth(12);
    $sheet->getColumnDimension('I')->setWidth(20);
    
    // Freeze panes
    $sheet->freezePane('A5');
    
    // Generate filename
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $employee['name']);
    $filename = 'Appraisal_Report_' . $safe_name . '_' . $year . '.xlsx';
    
    // Save to temp file
    $temp_file = tempnam(sys_get_temp_dir(), 'excel_');
    $writer = new Xlsx($spreadsheet);
    $writer->save($temp_file);
    
    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!file_exists($temp_file) || filesize($temp_file) === 0) {
        die('Failed to generate Excel file');
    }
    
    // Output headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Content-Length: ' . filesize($temp_file));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Output file
    readfile($temp_file);
    unlink($temp_file);
    
    exit();
    
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log("Excel Report PHP error: " . $e->getMessage() . " at line " . $e->getLine());
    die('Error: ' . $e->getMessage());
}