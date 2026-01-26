<?php
// hr/reports/generate_all_employees_excel.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// 1. Authenticate & Check Permissions
if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/auth/login.php', 'Please login first.', 'warning');
}

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

if (!$user->isHR() && $user->role !== 'admin') {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}

$company_id = $_GET['company'] ?? 0;
$year = $_GET['year'] ?? date('Y');

if (!$company_id) {
    redirect('index.php', 'Company ID is required.', 'error');
}

try {
    // 2. Verify HR access and get company name
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
        redirect('index.php', 'No access to this company\'s data.', 'error');
    }

    // 3. Initialize Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("All Employees Report");

    // Define Styles
    $titleStyle = [
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0066CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '44546A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sectionHeaderStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    // 4. Build Title Section
    $sheet->setCellValue('A1', 'PERFORMANCE ASSESSMENT - ALL EMPLOYEES REPORT');
    $sheet->mergeCells('A1:AZ1');
    $sheet->getStyle('A1:AZ1')->applyFromArray($titleStyle);

    $sheet->setCellValue('A2', "$company_name - $year");
    $sheet->mergeCells('A2:AZ2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setBold(true);

    $currentRow = 4;

    // 5. Build Column Headers
    $baseHeaders = ['Company', 'Dept', 'Name', 'Staff No.', 'Form', 'Role', 'Position', 'Date Joined', 'Period'];
    $headers = $baseHeaders;
    for ($i = 1; $i <= 12; $i++) $headers[] = "Q$i";
    array_push($headers, 'Total', 'Score', 'Rating');
    for ($i = 1; $i <= 12; $i++) $headers[] = "Q$i";
    array_push($headers, 'Total', 'Score', 'Final Rating');
    for ($i = 1; $i <= 10; $i++) $headers[] = "T$i";

    // Section Overlays (The row above the column names)
    // Employee Scores Section (Blue)
    $sheet->setCellValue('J' . $currentRow, 'Performance Assessment - Employee Scores');
    $sheet->mergeCells('J4:W4');
    $sheet->getStyle('J4:W4')->applyFromArray($sectionHeaderStyle);
    $sheet->getStyle('J4:W4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');

    // Manager Scores Section (Green)
    $sheet->setCellValue('X' . $currentRow, 'Performance Assessment - Manager Scores');
    $sheet->mergeCells('X4:AK4');
    $sheet->getStyle('X4:AK4')->applyFromArray($sectionHeaderStyle);
    $sheet->getStyle('X4:AK4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('70AD47');

    // Training Section (Yellow)
    $sheet->setCellValue('AL' . $currentRow, 'Training & Development Needs');
    $sheet->mergeCells('AL4:AU4');
    $sheet->getStyle('AL4:AU4')->applyFromArray($sectionHeaderStyle);
    $sheet->getStyle('AL4:AU4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC000');

    $currentRow++; // Move to Row 5 (Actual Column Names)
    foreach ($headers as $idx => $val) {
        $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
        $sheet->setCellValue($colLetter . $currentRow, $val);
        $sheet->getStyle($colLetter . $currentRow)->applyFromArray($headerStyle);
    }

    $currentRow++; // Row 6 (Data Start)

    // 6. Fetch and Write Employee Data
    $emp_query = "SELECT DISTINCT u.* FROM users u 
                  JOIN appraisals a ON u.id = a.user_id 
                  WHERE u.company_id = ? AND YEAR(a.appraisal_period_from) = ? AND a.status = 'completed'
                  ORDER BY u.department, u.name";
    $stmt = $db->prepare($emp_query);
    $stmt->execute([$company_id, $year]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($employees as $employee) {
        $app_query = "SELECT a.*, f.title as form_title FROM appraisals a 
                      LEFT JOIN forms f ON a.form_id = f.id 
                      WHERE a.user_id = ? AND YEAR(a.appraisal_period_from) = ? AND a.status = 'completed'";
        $stmt_app = $db->prepare($app_query);
        $stmt_app->execute([$employee['id'], $year]);
        $appraisals = $stmt_app->fetchAll(PDO::FETCH_ASSOC);

        foreach ($appraisals as $app) {
            // Fetch Question Ratings
            $q_query = "SELECT r.employee_rating, r.manager_rating FROM responses r 
                        JOIN form_questions fq ON r.question_id = fq.id 
                        JOIN form_sections fs ON fq.section_id = fs.id 
                        WHERE r.appraisal_id = ? AND fs.section_order = 2 ORDER BY fq.question_order LIMIT 12";
            $stmt_q = $db->prepare($q_query);
            $stmt_q->execute([$app['id']]);
            $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Training Needs
            $t_query = "SELECT r.employee_response FROM responses r 
                        JOIN form_questions fq ON r.question_id = fq.id 
                        JOIN form_sections fs ON fq.section_id = fs.id 
                        WHERE r.appraisal_id = ? AND fs.section_title LIKE '%Training%' AND fq.response_type = 'checkbox'";
            $stmt_t = $db->prepare($t_query);
            $stmt_t->execute([$app['id']]);
            $training = [];
            while($t_row = $stmt_t->fetch(PDO::FETCH_ASSOC)) {
                $items = explode(',', $t_row['employee_response']);
                foreach($items as $it) if(trim($it)) $training[] = trim($it);
            }

            // Build Row
            $rowData = [
                $company_name, $employee['department'], $employee['name'], $employee['emp_number'],
                $app['form_title'], 'Employee', $employee['position'], $employee['date_joined'],
                $app['appraisal_period_from'] . ' to ' . $app['appraisal_period_to']
            ];

            // Ratings (Emp)
            for ($i = 0; $i < 12; $i++) {
                $rowData[] = (isset($questions[$i]) && $questions[$i]['employee_rating'] > 0) ? $questions[$i]['employee_rating'] : '';
            }
            $rowData[] = "=SUM(J$currentRow:U$currentRow)"; // Total
            $rowData[] = "=IF(COUNT(J$currentRow:U$currentRow)>0, ROUND((V$currentRow/(COUNT(J$currentRow:U$currentRow)*5))*100,2), 0)"; // Score (Assuming 5 is max)
            $rowData[] = "=IF(W$currentRow>=85,\"A\",IF(W$currentRow>=75,\"B+\",IF(W$currentRow>=65,\"B\",IF(W$currentRow>=60,\"B-\",\"C\"))))";

            // Ratings (Mgr)
            for ($i = 0; $i < 12; $i++) {
                $rowData[] = (isset($questions[$i]) && $questions[$i]['manager_rating'] > 0) ? $questions[$i]['manager_rating'] : '';
            }
            $rowData[] = "=SUM(X$currentRow:AI$currentRow)"; // Total
            $rowData[] = "=IF(COUNT(X$currentRow:AI$currentRow)>0, ROUND((AJ$currentRow/(COUNT(X$currentRow:AI$currentRow)*5))*100,2), 0)"; // Score
            $rowData[] = "=IF(AK$currentRow>=85,\"A\",IF(AK$currentRow>=75,\"B+\",IF(AK$currentRow>=65,\"B\",IF(AK$currentRow>=60,\"B-\",\"C\"))))";

            // Training
            for ($i = 0; $i < 10; $i++) {
                $rowData[] = $training[$i] ?? '';
            }

            $sheet->fromArray($rowData, NULL, 'A' . $currentRow);
            
            // Row Border
            $sheet->getStyle("A$currentRow:AU$currentRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("J$currentRow:AK$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $currentRow++;
        }
    }

    // 7. Column Formatting
    $sheet->freezePane('J6');
    foreach (range(1, 47) as $i) {
        $col = Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($col)->setWidth(15);
    }

    // 8. Send to Browser
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name);
    $fileName = "All_Employees_Report_{$safe_name}_{$year}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    ob_end_clean();
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
}