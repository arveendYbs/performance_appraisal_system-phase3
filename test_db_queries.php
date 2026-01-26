
<?php
// test_db_queries.php - Run this to test your database queries
require_once 'config/config.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Testing Database Queries</h2>";
    
    // Test 1: Login query
    echo "<h3>Test 1: Login Query</h3>";
    $email = "test@example.com";
    $query = "SELECT id FROM users WHERE (email = ? OR emp_email = ?) AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$email, $email]);
    echo "✓ Login query works<br>";
    
    // Test 2: Email exists
    echo "<h3>Test 2: Email Exists</h3>";
    $query = "SELECT id FROM users WHERE (email = ? OR emp_email = ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$email, $email]);
    echo "✓ Email exists query works<br>";
    
    // Test 3: Company export query
    echo "<h3>Test 3: Company Export Query</h3>";
    $company_id = 1;
    $year = 2025;
    $query = "SELECT COUNT(*) FROM appraisals a 
              JOIN users u ON a.user_id = u.id
              WHERE u.company_id = ? AND YEAR(a.appraisal_period_from) = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$company_id, $year]);
    echo "✓ Company export query works<br>";
    
    echo "<hr>";
    echo "<h3>All tests passed! ✓</h3>";
    echo "<p><strong>If you see errors above, those are the queries that need fixing.</strong></p>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error found:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>