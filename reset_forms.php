<?php
require_once 'config/config.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Disable foreign key checks temporarily
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    
    // Truncate the tables in correct order
    $db->exec('TRUNCATE TABLE form_questions');
    $db->exec('TRUNCATE TABLE form_sections');
    $db->exec('TRUNCATE TABLE forms');
    
    // Re-enable foreign key checks
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    
    echo "Forms data cleared successfully!\n";
    echo "You can now run setup_default_forms.php\n";
    
} catch (Exception $e) {
    echo "Error resetting forms: " . $e->getMessage() . "\n";
    exit(1);
}
?>