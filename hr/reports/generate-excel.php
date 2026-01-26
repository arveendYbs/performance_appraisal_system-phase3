<?php
// hr/reports/generate-excel.php - FINAL FIXED VERSION
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

// FIX: Add this check to ensure only HR or Admins can download reports
if (!$user->isHR() && $user->role !== 'admin') {
    redirect(BASE_URL . '/index.php', 'Access denied.', 'error');
}
$user_id = $_GET['user_id'] ?? 0;
$year = $_GET['year'] ?? date('Y');

if (!$user_id) {
    redirect('index.php', 'User ID is required.', 'error');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user information
    $user_query = "SELECT u.*, c.name as company_name,
                          sup.name as supervisor_name
                   FROM users u
                   LEFT JOIN companies c ON u.company_id = c.id
                   LEFT JOIN users sup ON u.direct_superior = sup.id
                   WHERE u.id = ?";
    $stmt = $db->prepare($user_query);
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        redirect('index.php', 'Employee not found.', 'error');
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
        redirect('index.php', 'No completed appraisals found for this employee in ' . $year, 'warning');
    }
    
    $report_data = [
        'employee' => $employee,
        'appraisals' => [],
        'year' => $year
    ];
    
    // Process each appraisal
    foreach ($appraisals as $appraisal) {
        $appraisal_id = $appraisal['id'];
        
        // KEY CHANGE: Get individual question scores from Section 2 ONLY
        // Section 2 is typically section_order = 2
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
        
        // Create array of 12 question scores (some may be empty)
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
        
        // NEW: Get Training and Development Needs (checkbox responses)
        // Stored as comma-separated string in employee_response field
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
                // Split by comma: "Business Writing, Finance for Non Finance Executives, Consultative Selling"
                $items = explode(',', $response['employee_response']);
                foreach ($items as $item) {
                    $item = trim($item);
                    if (!empty($item) && !in_array($item, $training_needs)) {
                        $training_needs[] = $item;
                    }
                }
            }
        }
        
        $report_data['appraisals'][] = [
            'id' => $appraisal_id,
            'form_title' => $appraisal['form_title'],
            'period_from' => $appraisal['appraisal_period_from'],
            'period_to' => $appraisal['appraisal_period_to'],
            'total_score' => $appraisal['total_score'],
            'grade' => $appraisal['grade'],
            'submitted_at' => $appraisal['employee_submitted_at'],
            'reviewed_at' => $appraisal['manager_reviewed_at'],
            'questions' => $question_scores,  // Section 2 questions
            'training_needs' => $training_needs  // Training checkboxes
        ];
    }
    
    // Save to JSON
    $temp_json = tempnam(sys_get_temp_dir(), 'appraisal_report_');
    file_put_contents($temp_json, json_encode($report_data, JSON_PRETTY_PRINT));
    
    // Generate Excel
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $employee['name']);
    $output_filename = 'Appraisal_Report_' . $safe_name . '_' . $year . '.xlsx';
    $output_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $output_filename;
    
    $python_script = __DIR__ . DIRECTORY_SEPARATOR . 'generate-excel-report.py';
    
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
        redirect('index.php', 'Failed to generate Excel report.', 'error');
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
    error_log("Excel Report error: " . $e->getMessage());
    redirect('index.php', 'An error occurred: ' . $e->getMessage(), 'error');
}





























