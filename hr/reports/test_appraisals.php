<?php
// test_appraisals.php
require_once __DIR__ . '/../../config/config.php'; // adjust path if needed

// Start session (if required)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // For testing, use a specific company and year
    $company_id = 1; // change to your test company
    $year = 2025;

    // Example SQL similar to export_company_excel.php
    $sql = "SELECT 
                a.id as appraisal_id,
                a.form_id,
                a.appraisal_period_from,
                a.appraisal_period_to,
                a.grade,
                a.total_score,
                a.employee_submitted_at,
                a.manager_reviewed_at,
                a.created_at,
                u.name as employee_name,
                u.emp_number,
                u.email as employee_email,
                u.position,
                u.department,
                u.site,
                u.date_joined,
                m.name as manager_name,
                f.title as form_title,
                c.name as company_name
            FROM appraisals a
            JOIN users u ON a.user_id = u.id
            JOIN companies c ON u.company_id = c.id
            LEFT JOIN users m ON u.direct_superior = m.id
            LEFT JOIN forms f ON a.form_id = f.id
            WHERE u.company_id = ?
            AND a.status = 'completed'
            AND YEAR(a.appraisal_period_from) = ?
            ORDER BY u.department, u.name";

    $stmt = $db->prepare($sql);
    $stmt->execute([$company_id, $year]);
    $appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    if (empty($appraisals)) {
        echo "No completed appraisals found for company ID $company_id in $year.\n";
    } else {
        echo "Fetched " . count($appraisals) . " appraisals:\n\n";
        foreach ($appraisals as $a) {
            echo "ID: {$a['appraisal_id']}, Employee: {$a['employee_name']}, Grade: {$a['grade']}, Score: {$a['total_score']}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
