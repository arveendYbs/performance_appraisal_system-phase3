<?php
// hr/reports/debug_export.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/export_debug.log');

require_once __DIR__ . '/../../config/config.php';

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../../logs')) {
    mkdir(__DIR__ . '/../../logs', 0777, true);
}

function logDebug($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= "\n" . print_r($data, true);
    }
    $log .= "\n---\n";
    file_put_contents(__DIR__ . '/../../logs/export_debug.log', $log, FILE_APPEND);
    echo "<pre>" . htmlspecialchars($log) . "</pre>";
}

logDebug("=== DEBUG EXPORT STARTED ===");

// --- Authentication ---
if (!isset($_SESSION['user_id'])) {
    logDebug("ERROR: No session user_id");
    die('Unauthorized access');
}

logDebug("Session user_id", $_SESSION['user_id']);

$database = new Database();
$db = $database->getConnection();

logDebug("Database connected");

$user = new User($db);
$user->id = $_SESSION['user_id'];
$user->readOne();

logDebug("User loaded", [
    'id' => $user->id,
    'name' => $user->name,
    'is_hr' => $user->isHR()
]);

if (!$user->isHR()) {
    logDebug("ERROR: User is not HR");
    die('Access denied. HR personnel only.');
}

// --- Get parameters ---
$company_id = $_GET['company'] ?? '';
$year = $_GET['year'] ?? date('Y');
$export_type = $_GET['type'] ?? 'detailed';

logDebug("Parameters", [
    'company_id' => $company_id,
    'year' => $year,
    'export_type' => $export_type
]);

if (empty($company_id)) {
    logDebug("ERROR: Empty company_id");
    die('Company ID is required');
}

// Verify HR access
$hr_companies = $user->getHRCompanies();
logDebug("HR Companies", $hr_companies);

$has_access = false;
$company_name = '';
foreach ($hr_companies as $company) {
    if ($company['id'] == $company_id) {
        $has_access = true;
        $company_name = $company['name'];
        break;
    }
}

logDebug("Access check", [
    'has_access' => $has_access,
    'company_name' => $company_name
]);

if (!$has_access) {
    logDebug("ERROR: No access to company");
    die('No access to this company\'s data');
}

try {
    // --- TEST 1: Basic appraisals query ---
    logDebug("TEST 1: Fetching appraisals");
    
    $query = "SELECT 
                a.id as appraisal_id,
                a.form_id,
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

    logDebug("Query prepared", ['query' => $query]);
    
    $stmt = $db->prepare($query);
    logDebug("Statement prepared");
    
    logDebug("Executing with params", ['company_id' => $company_id, 'year' => $year]);
    $stmt->execute([$company_id, $year]);
    
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logDebug("Appraisals fetched", ['count' => count($appraisals)]);
    
    if (empty($appraisals)) {
        logDebug("ERROR: No appraisals found");
        die('No completed appraisals found for this company in ' . $year);
    }
    
    logDebug("Sample appraisal", $appraisals[0]);
    
    // --- TEST 2: Test responses query for first appraisal ---
    logDebug("TEST 2: Testing responses query");
    
    $test_appraisal = $appraisals[0];
    if (!empty($test_appraisal['form_id'])) {
        logDebug("Testing form_id", $test_appraisal['form_id']);
        
        $resp_query = "SELECT r.*, fq.question_text, fq.response_type, fs.section_title
                       FROM responses r
                       JOIN form_questions fq ON r.question_id = fq.id
                       JOIN form_sections fs ON fq.section_id = fs.id
                       WHERE r.appraisal_id = ?
                       ORDER BY fs.section_order, fq.question_order";
        
        logDebug("Responses query", ['query' => $resp_query]);
        
        $resp_stmt = $db->prepare($resp_query);
        logDebug("Responses statement prepared");
        
        logDebug("Executing responses with appraisal_id", $test_appraisal['appraisal_id']);
        $resp_stmt->execute([$test_appraisal['appraisal_id']]);
        
        $responses = $resp_stmt->fetchAll(PDO::FETCH_ASSOC);
        logDebug("Responses fetched", ['count' => count($responses)]);
        
        if (!empty($responses)) {
            logDebug("Sample response", $responses[0]);
        }
    }
    
    // --- TEST 3: Test ratings query ---
    logDebug("TEST 3: Testing average ratings query");
    
    $ratings_query = "SELECT 
                AVG(CASE WHEN employee_rating IS NOT NULL THEN employee_rating END) as employee_avg,
                AVG(CASE WHEN manager_rating IS NOT NULL THEN manager_rating END) as manager_avg,
                COUNT(*) as total_questions
              FROM responses
              WHERE appraisal_id = ?
              AND question_id IN (
                  SELECT id FROM form_questions 
                  WHERE response_type IN ('rating_5', 'rating_10')
              )";
    
    logDebug("Ratings query", ['query' => $ratings_query]);
    
    $ratings_stmt = $db->prepare($ratings_query);
    logDebug("Ratings statement prepared");
    
    logDebug("Executing ratings with appraisal_id", $test_appraisal['appraisal_id']);
    $ratings_stmt->execute([$test_appraisal['appraisal_id']]);
    
    $ratings = $ratings_stmt->fetch(PDO::FETCH_ASSOC);
    logDebug("Ratings fetched", $ratings);
    
    logDebug("=== ALL TESTS PASSED ===");
    echo "<h2 style='color: green;'>All database queries executed successfully!</h2>";
    echo "<p>Found " . count($appraisals) . " appraisals</p>";
    echo "<p>You can now proceed with the actual export.</p>";
    
} catch (PDOException $e) {
    logDebug("PDO EXCEPTION", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);
    die('PDO Error: ' . $e->getMessage());
} catch (Exception $e) {
    logDebug("GENERAL EXCEPTION", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);
    die('Error: ' . $e->getMessage());
}