<?php
// DEBUG SCRIPT - Run this to see what data is being passed to Python
// Save as: hr/reports/debug-scores.php

require_once __DIR__ . '/../../config/config.php';

$user_id = $_GET['user_id'] ?? 0;
$year = $_GET['year'] ?? date('Y');

if (!$user_id) {
    die("Add ?user_id=X to URL");
}

$database = new Database();
$db = $database->getConnection();

// Get user
$user_query = "SELECT u.*, c.name as company_name
               FROM users u
               LEFT JOIN companies c ON u.company_id = c.id
               WHERE u.id = ?";
$stmt = $db->prepare($user_query);
$stmt->execute([$user_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Employee: " . htmlspecialchars($employee['name']) . "</h2>";

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

echo "<h3>Found " . count($appraisals) . " completed appraisals</h3>";

foreach ($appraisals as $appraisal) {
    echo "<hr><h4>Appraisal ID: " . $appraisal['id'] . " - " . htmlspecialchars($appraisal['form_title']) . "</h4>";
    
    // Get responses
    $responses_query = "SELECT 
                            r.id,
                            r.employee_rating,
                            r.manager_rating,
                            r.employee_comments,
                            r.manager_comments,
                            fs.id as section_id,
                            fs.section_title,
                            fs.section_order,
                            fq.question_text,
                            fq.response_type,
                            fq.question_order
                       FROM responses r
                       JOIN form_questions fq ON r.question_id = fq.id
                       JOIN form_sections fs ON fq.section_id = fs.id
                       WHERE r.appraisal_id = ?
                       AND fq.response_type IN ('rating_5', 'rating_10')
                       ORDER BY fs.section_order, fq.question_order
                       LIMIT 10";
    
    $stmt = $db->prepare($responses_query);
    $stmt->execute([$appraisal['id']]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Section</th><th>Question</th><th>Emp Rating</th><th>Mgr Rating</th></tr>";
    
    foreach ($responses as $resp) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($resp['section_title']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($resp['question_text'], 0, 50)) . "...</td>";
        echo "<td style='background: " . ($resp['employee_rating'] ? 'lightgreen' : 'pink') . "'>" . 
             ($resp['employee_rating'] ?? 'NULL') . "</td>";
        echo "<td style='background: " . ($resp['manager_rating'] ? 'lightblue' : 'pink') . "'>" . 
             ($resp['manager_rating'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show section totals
    echo "<h5>Section Totals:</h5>";
    
    $sections = [];
    foreach ($responses as $response) {
        $section_id = $response['section_id'];
        
        if (!isset($sections[$section_id])) {
            $sections[$section_id] = [
                'title' => $response['section_title'],
                'employee_score' => 0,
                'manager_score' => 0,
                'count' => 0
            ];
        }
        
        $sections[$section_id]['employee_score'] += ($response['employee_rating'] ?? 0);
        $sections[$section_id]['manager_score'] += ($response['manager_rating'] ?? 0);
        $sections[$section_id]['count']++;
    }
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Section</th><th>Questions</th><th>Emp Total</th><th>Mgr Total</th></tr>";
    foreach ($sections as $section) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($section['title']) . "</td>";
        echo "<td>" . $section['count'] . "</td>";
        echo "<td>" . $section['employee_score'] . "</td>";
        echo "<td>" . $section['manager_score'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}