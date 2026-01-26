<?php
// hr/reports/generate_all_employees_excel.php
require_once __DIR__ . '/../../config/config.php';

// Check if user is HR
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
    // Verify HR has access to this company
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
    
    // Get all employees with completed appraisals for this company and year
    $employees_query = "SELECT DISTINCT 
                            u.id,
                            u.name,
                            u.emp_number,
                            u.position,
                            u.department,
                            u.site,
                            u.date_joined,
                            sup.name as supervisor_name
                        FROM users u
                        JOIN appraisals a ON u.id = a.user_id
                        LEFT JOIN users sup ON u.direct_superior = sup.id
                        WHERE u.company_id = ?
                        AND YEAR(a.appraisal_period_from) = ?
                        AND a.status = 'completed'
                        ORDER BY u.department, u.name";
    
    $stmt = $db->prepare($employees_query);
    $stmt->execute([$company_id, $year]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($employees)) {
        redirect('index.php', 'No completed appraisals found for this company in ' . $year, 'warning');
    }
    
    $report_data = [
        'company_name' => $company_name,
        'year' => $year,
        'employees' => []
    ];
    
    // Process each employee
    foreach ($employees as $employee) {
        $employee_id = $employee['id'];
        
        // Get appraisals for this employee
        $appraisals_query = "SELECT a.*, f.title as form_title
                             FROM appraisals a
                             LEFT JOIN forms f ON a.form_id = f.id
                             WHERE a.user_id = ?
                             AND YEAR(a.appraisal_period_from) = ?
                             AND a.status = 'completed'
                             ORDER BY a.appraisal_period_from";
        
        $stmt = $db->prepare($appraisals_query);
        $stmt->execute([$employee_id, $year]);
        $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $employee_appraisals = [];
        
        // Process each appraisal
        foreach ($appraisals as $appraisal) {
            $appraisal_id = $appraisal['id'];
            
            // Get individual question scores from Section 2
            $questions_query = "SELECT 
                                    r.employee_rating,
                                    r.manager_rating,
                                    fq.question_order,
                                    fq.question_text,
                                    fs.section_title,
                                    fs.section_order
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
            
            // Create array of 12 question scores
            $question_scores = [];
            for ($i = 0; $i < 12; $i++) {
                if (isset($questions[$i])) {
                    $question_scores[] = [
                        'employee_rating' => $questions[$i]['employee_rating'] ?? 0,
                        'manager_rating' => $questions[$i]['manager_rating'] ?? 0,
                        'question_text' => $questions[$i]['question_text'] ?? ''
                    ];
                } else {
                    $question_scores[] = [
                        'employee_rating' => 0,
                        'manager_rating' => 0,
                        'question_text' => ''
                    ];
                }
            }
            
            // Get Training and Development Needs
            $training_query = "SELECT 
                                  fq.question_text,
                                  r.employee_response
                              FROM responses r
                              JOIN form_questions fq ON r.question_id = fq.id
                              JOIN form_sections fs ON fq.section_id = fs.id
                              WHERE r.appraisal_id = ?
                              AND fs.section_title LIKE '%Training%'
                              AND fq.response_type = 'checkbox'
                              AND r.employee_response IS NOT NULL
                              AND r.employee_response != ''
                              ORDER BY fq.question_order";
            
            $stmt = $db->prepare($training_query);
            $stmt->execute([$appraisal_id]);
            $training_responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse training needs from comma-separated string
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
            
            $employee_appraisals[] = [
                'id' => $appraisal_id,
                'form_title' => $appraisal['form_title'],
                'period_from' => $appraisal['appraisal_period_from'],
                'period_to' => $appraisal['appraisal_period_to'],
                'total_score' => $appraisal['total_score'],
                'grade' => $appraisal['grade'],
                'submitted_at' => $appraisal['employee_submitted_at'],
                'reviewed_at' => $appraisal['manager_reviewed_at'],
                'questions' => $question_scores,
                'training_needs' => $training_needs
            ];
        }
        
        $report_data['employees'][] = [
            'id' => $employee_id,
            'name' => $employee['name'],
            'emp_number' => $employee['emp_number'],
            'position' => $employee['position'],
            'department' => $employee['department'],
            'site' => $employee['site'],
            'date_joined' => $employee['date_joined'],
            'supervisor_name' => $employee['supervisor_name'],
            'appraisals' => $employee_appraisals
        ];
    }
    
    // Save to JSON
    $temp_json = tempnam(sys_get_temp_dir(), 'all_employees_report_');
    file_put_contents($temp_json, json_encode($report_data, JSON_PRETTY_PRINT));
    
    // Generate Excel
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name);
    $output_filename = 'All_Employees_Report_' . $safe_name . '_' . $year . '.xlsx';
    $output_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $output_filename;
    
    $python_script = __DIR__ . DIRECTORY_SEPARATOR . 'generate_all_employees_report.py';
    
    if (!file_exists($python_script)) {
        unlink($temp_json);
        redirect('index.php', 'Excel generator script not found.', 'error');
    }
    
    $python_cmd = 'py';
    
    $command = $python_cmd . " " . escapeshellarg($python_script) . " " . 
               escapeshellarg($temp_json) . " " . 
               escapeshellarg($output_path) . " 2>&1";
    
    $output = shell_exec($command);
    
    unlink($temp_json);
    
    if (!file_exists($output_path)) {
        redirect('index.php', 'Failed to generate Excel report. Output: ' . $output, 'error');
    }
    
    $filesize = filesize($output_path);
    if ($filesize === 0) {
        unlink($output_path);
        redirect('index.php', 'Generated Excel file is empty.', 'error');
    }
    
    // Clean output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $output_filename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: max-age=0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($output_path);
    
    unlink($output_path);
    
    exit();
    
} catch (Exception $e) {
    error_log("All Employees Excel Report error: " . $e->getMessage());
    redirect('index.php', 'An error occurred: ' . $e->getMessage(), 'error');
}