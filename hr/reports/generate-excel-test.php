<?php
// hr/reports/generate-excel.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate; // Required for stable coordinate conversion

// 1. Auth & Permissions (Keep your existing checks)
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

$user_id = $_GET['user_id'] ?? 0;
$year = $_GET['year'] ?? date('Y');

try {
    // 2. Data Fetching (Employee)
    $stmt = $db->prepare("SELECT u.*, c.name as company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) die("Employee not found.");

    // 3. Data Fetching (Appraisals)
    $stmt = $db->prepare("SELECT a.*, f.title as form_title FROM appraisals a LEFT JOIN forms f ON a.form_id = f.id WHERE a.user_id = ? AND YEAR(a.appraisal_period_from) = ? AND a.status = 'completed' ORDER BY a.appraisal_period_from");
    $stmt->execute([$user_id, $year]);
    $appraisal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($appraisal_list)) die("No completed appraisals found for $year");

    // 4. Initialize Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($year . " Report");

    // Styles
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '366092']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    $subHeaderStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    // 5. Title & Section Headers
    $sheet->setCellValue('A1', "Summary of $year Appraisal Ratings - " . ($employee['company_name'] ?? 'N/A'));
    $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
    $sheet->mergeCells('A1:R1');

    $currentRow = 3;
    $sheet->setCellValue('J' . $currentRow, 'Performance Assessment - Employee Scores');
    $sheet->mergeCells("J$currentRow:U$currentRow");
    $sheet->getStyle("J$currentRow:U$currentRow")->applyFromArray($subHeaderStyle);

    $sheet->setCellValue('Y' . $currentRow, 'Performance Assessment - Manager Scores');
    $sheet->mergeCells("Y$currentRow:AJ$currentRow");
    $sheet->getStyle("Y$currentRow:AJ$currentRow")->applyFromArray($subHeaderStyle);

    $sheet->setCellValue('AN' . $currentRow, 'Training & Development Needs');
    $sheet->mergeCells("AN$currentRow:AW$currentRow");
    $sheet->getStyle("AN$currentRow:AW$currentRow")->applyFromArray($subHeaderStyle);

    $currentRow++; // Now on row 4 (Headers)

    // 6. Column Headers
    $headers = ['Company', 'Dept', 'Name', 'Staff No.', 'Form', 'Role', 'Position', 'Date Joined', 'Period'];
    for ($i = 1; $i <= 12; $i++) $headers[] = "Q$i";
    array_push($headers, 'Total', 'Score', 'Rating');
    for ($i = 1; $i <= 12; $i++) $headers[] = "Q$i";
    array_push($headers, 'Total', 'Score', 'Final Rating');
    for ($i = 1; $i <= 10; $i++) $headers[] = "T$i";

    foreach ($headers as $index => $title) {
        // Convert number index (0-based) to Excel Letter (A, B, C...)
        $colLetter = Coordinate::stringFromColumnIndex($index + 1);
        $sheet->setCellValue($colLetter . $currentRow, $title);
        $sheet->getStyle($colLetter . $currentRow)->applyFromArray($headerStyle);
    }

    $currentRow++; // Now on row 5 (Data start)

    // 7. Data Loop
    foreach ($appraisal_list as $app) {
        // Fetch Question Ratings
        $stmt = $db->prepare("SELECT r.employee_rating, r.manager_rating FROM responses r JOIN form_questions fq ON r.question_id = fq.id JOIN form_sections fs ON fq.section_id = fs.id WHERE r.appraisal_id = ? AND fs.section_order = 2 ORDER BY fq.question_order LIMIT 12");
        $stmt->execute([$app['id']]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Training
        $stmt = $db->prepare("SELECT r.employee_response FROM responses r JOIN form_questions fq ON r.question_id = fq.id JOIN form_sections fs ON fq.section_id = fs.id WHERE r.appraisal_id = ? AND fs.section_title LIKE '%Training%' AND fq.response_type = 'checkbox'");
        $stmt->execute([$app['id']]);
        $training_items = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $parts = explode(',', $row['employee_response']);
            foreach($parts as $p) if(trim($p)) $training_items[] = trim($p);
        }

        $role = strtolower($employee['role'] ?? 'employee');
        $divisor = (strpos($role, 'manager') !== false || strpos($role, 'admin') !== false) ? 1.2 : ((strpos($role, 'worker') !== false) ? 0.8 : 1);

        $rowData = [
            $employee['company_name'], $employee['department'], $employee['name'], $employee['emp_number'],
            $app['form_title'], ucfirst($role), $employee['position'], $employee['date_joined'],
            $app['appraisal_period_from'] . ' to ' . $app['appraisal_period_to']
        ];

        // Emp Scores Q1-Q12
        for ($i = 0; $i < 12; $i++) {
            $rowData[] = (isset($questions[$i]) && $questions[$i]['employee_rating'] > 0) ? $questions[$i]['employee_rating'] : '';
        }
        $rowData[] = "=SUM(J$currentRow:U$currentRow)";
        $rowData[] = "=ROUND(V$currentRow/$divisor,0)";
        $rowData[] = "=IF(W$currentRow=0,\"\",IF(W$currentRow<50,\"C\",IF(W$currentRow<60,\"B-\",IF(W$currentRow<75,\"B\",IF(W$currentRow<85,\"B+\",\"A\")))))";

        // Mgr Scores Q1-Q12
        for ($i = 0; $i < 12; $i++) {
            $rowData[] = (isset($questions[$i]) && $questions[$i]['manager_rating'] > 0) ? $questions[$i]['manager_rating'] : '';
        }
        $rowData[] = "=SUM(Y$currentRow:AJ$currentRow)";
        $rowData[] = "=ROUND(AK$currentRow/$divisor,0)";
        $rowData[] = "=IF(AL$currentRow=0,\"\",IF(AL$currentRow<50,\"C\",IF(AL$currentRow<60,\"B-\",IF(AL$currentRow<75,\"B\",IF(AL$currentRow<85,\"B+\",\"A\")))))";

        // Training T1-T10
        for ($i = 0; $i < 10; $i++) {
            $rowData[] = $training_items[$i] ?? '';
        }

        // Write row data
        $sheet->fromArray($rowData, NULL, 'A' . $currentRow);
        
        // Final row styling
        $sheet->getStyle("A$currentRow:AW$currentRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("J$currentRow:AW$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $currentRow++;
    }

    // 8. Final Formatting
    $sheet->freezePane('A5');
    foreach (range('A', 'I') as $colID) { $sheet->getColumnDimension($colID)->setAutoSize(true); }
    for ($i = 40; $i <= 49; $i++) { 
        $colLetter = Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($colLetter)->setWidth(25); 
    }

    // 9. Output
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $employee['name']);
    $fileName = "Appraisal_Report_{$safe_name}_{$year}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    if (ob_get_length()) ob_end_clean();
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
}