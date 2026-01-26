<?php
// Test script to verify training data extraction
// Save as: hr/reports/test-training.php
// Run as: hr/reports/test-training.php?id=49

require_once __DIR__ . '/../../config/config.php';

$appraisal_id = $_GET['id'] ?? 49;

$database = new Database();
$db = $database->getConnection();

echo "<h3>Training Data Test - Appraisal ID: $appraisal_id</h3>";

// Query training responses
$query = "SELECT 
            fs.section_title,
            fq.question_text,
            fq.response_type,
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

$stmt = $db->prepare($query);
$stmt->execute([$appraisal_id]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>Raw Data from Database:</h4>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Section</th><th>Question</th><th>Type</th><th>Employee Response</th></tr>";

foreach ($responses as $resp) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($resp['section_title']) . "</td>";
    echo "<td>" . htmlspecialchars($resp['question_text']) . "</td>";
    echo "<td>" . htmlspecialchars($resp['response_type']) . "</td>";
    echo "<td><b>" . htmlspecialchars($resp['employee_response']) . "</b></td>";
    echo "</tr>";
}
echo "</table>";

// Parse and display as list
echo "<h4>Parsed Training Needs:</h4>";
$training_needs = [];
foreach ($responses as $response) {
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

if (empty($training_needs)) {
    echo "<p style='color: red;'>No training needs found!</p>";
    echo "<p>Possible reasons:</p>";
    echo "<ul>";
    echo "<li>Section title doesn't contain 'Training'</li>";
    echo "<li>Questions are not type 'checkbox'</li>";
    echo "<li>employee_response field is empty</li>";
    echo "</ul>";
} else {
    echo "<ul>";
    foreach ($training_needs as $need) {
        echo "<li>" . htmlspecialchars($need) . "</li>";
    }
    echo "</ul>";
    
    echo "<h4>How this will appear in Excel (Column AN):</h4>";
    echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
    foreach ($training_needs as $need) {
        echo "• " . htmlspecialchars($need) . "<br>";
    }
    echo "</div>";
}

// Debug: Show all sections
echo "<hr><h4>All Sections in this Appraisal:</h4>";
$sections_query = "SELECT DISTINCT
                    fs.section_title,
                    fs.section_order
                   FROM responses r
                   JOIN form_questions fq ON r.question_id = fq.id
                   JOIN form_sections fs ON fq.section_id = fs.id
                   WHERE r.appraisal_id = ?
                   ORDER BY fs.section_order";

$stmt = $db->prepare($sections_query);
$stmt->execute([$appraisal_id]);
$all_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Order</th><th>Section Title</th></tr>";
foreach ($all_sections as $section) {
    $highlight = (stripos($section['section_title'], 'training') !== false) ? 'background: yellow;' : '';
    echo "<tr style='$highlight'>";
    echo "<td>" . $section['section_order'] . "</td>";
    echo "<td>" . htmlspecialchars($section['section_title']) . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><i>Highlighted rows contain 'Training' in title</i></p>";