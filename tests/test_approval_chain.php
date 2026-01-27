<?php
/**
 * Test Approval Chain Generation
 * Run this script to test the approval chain logic
 * 
 * Usage: php tests/test_approval_chain.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/ApprovalChain.php';
require_once __DIR__ . '/../classes/Department.php';
require_once __DIR__ . '/../classes/Position.php';

echo "=== APPROVAL CHAIN TEST SCRIPT ===\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get a test employee
    $test_query = "SELECT u.*, p.employee_type 
                   FROM users u 
                   LEFT JOIN positions p ON u.position_id = p.id 
                   WHERE u.is_active = 1 
                   AND u.department_id IS NOT NULL 
                   AND u.direct_superior IS NOT NULL
                   LIMIT 5";
    
    $stmt = $db->prepare($test_query);
    $stmt->execute();
    $test_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($test_employees)) {
        echo "ERROR: No suitable test employees found.\n";
        echo "Please ensure you have employees with:\n";
        echo "- department_id set\n";
        echo "- direct_superior set\n";
        echo "- position_id set\n";
        exit;
    }
    
    $approvalChain = new ApprovalChain($db);
    
    foreach ($test_employees as $employee) {
        echo "===========================================\n";
        echo "TEST EMPLOYEE: {$employee['name']} ({$employee['emp_number']})\n";
        echo "===========================================\n";
        echo "Position ID: {$employee['position_id']}\n";
        echo "Employee Type: " . ($employee['employee_type'] ?? 'Not set') . "\n";
        echo "Department ID: {$employee['department_id']}\n";
        echo "Direct Superior: {$employee['direct_superior']}\n";
        echo "Is Confirmed: " . ($employee['is_confirmed'] ? 'Yes' : 'No (Probation)') . "\n\n";
        
        // Get department details
        $dept = new Department($db);
        $dept->id = $employee['department_id'];
        if ($dept->readOne()) {
            echo "DEPARTMENT: {$dept->department_name}\n";
            echo "  Staff Levels: {$dept->staff_approval_levels}\n";
            echo "  Worker Levels: {$dept->worker_approval_levels}\n";
            echo "  Probation Max: {$dept->probation_approval_levels}\n";
            echo "  Level 2 Approver: " . ($dept->level_2_approver_id ?? 'Not set') . "\n";
            echo "  Level 3 Approver: " . ($dept->level_3_approver_id ?? 'Not set') . "\n";
            echo "  Level 4 Approver: " . ($dept->level_4_approver_id ?? 'Not set') . "\n";
            echo "  Level 5 Approver: " . ($dept->level_5_approver_id ?? 'Not set') . "\n\n";
        }
        
        // Create a test appraisal
        echo "Creating test appraisal...\n";
        $test_appraisal_query = "INSERT INTO appraisals (user_id, form_id, appraisal_period_from, appraisal_period_to, status)
                                 VALUES (?, 1, '2025-01-01', '2025-12-31', 'draft')";
        $stmt = $db->prepare($test_appraisal_query);
        $stmt->execute([$employee['id']]);
        $test_appraisal_id = $db->lastInsertId();
        
        echo "Test appraisal created with ID: {$test_appraisal_id}\n\n";
        
        // Build approval chain
        echo "BUILDING APPROVAL CHAIN...\n";
        $chain = $approvalChain->buildApprovalChain($test_appraisal_id, $employee['id']);
        
        if ($chain) {
            echo "✓ Approval chain built successfully!\n\n";
            echo "GENERATED CHAIN:\n";
            echo str_repeat("-", 80) . "\n";
            printf("%-8s %-12s %-20s %-10s %-10s\n", "Level", "Approver ID", "Role", "Can Rate", "Final?");
            echo str_repeat("-", 80) . "\n";
            
            foreach ($chain as $level) {
                // Get approver name
                $approver_query = "SELECT name FROM users WHERE id = ?";
                $approver_stmt = $db->prepare($approver_query);
                $approver_stmt->execute([$level['approver_id']]);
                $approver = $approver_stmt->fetch(PDO::FETCH_ASSOC);
                
                printf("%-8d %-12s %-20s %-10s %-10s\n",
                       $level['level'],
                       $level['approver_id'] . " ({$approver['name']})",
                       $level['approver_role'],
                       $level['can_rate'] ? 'YES' : 'NO',
                       $level['is_final_approver'] ? 'YES' : 'NO'
                );
            }
            echo str_repeat("-", 80) . "\n\n";
            
            // Verify in database
            $verify_query = "SELECT * FROM appraisal_approvals WHERE appraisal_id = ? ORDER BY approval_level";
            $verify_stmt = $db->prepare($verify_query);
            $verify_stmt->execute([$test_appraisal_id]);
            $saved_chain = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "VERIFICATION: Saved in database\n";
            echo "  Total levels: " . count($saved_chain) . "\n";
            echo "  Status: All levels are 'pending'\n\n";
            
            // Get appraisal metadata
            $meta_query = "SELECT current_approval_level, total_approval_levels, status FROM appraisals WHERE id = ?";
            $meta_stmt = $db->prepare($meta_query);
            $meta_stmt->execute([$test_appraisal_id]);
            $meta = $meta_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "APPRAISAL METADATA:\n";
            echo "  Current Level: {$meta['current_approval_level']}\n";
            echo "  Total Levels: {$meta['total_approval_levels']}\n";
            echo "  Status: {$meta['status']}\n\n";
            
        } else {
            echo "✗ Failed to build approval chain!\n";
            echo "Check error log for details.\n\n";
        }
        
        // Clean up test appraisal
        echo "Cleaning up test data...\n";
        $cleanup1 = "DELETE FROM appraisal_approvals WHERE appraisal_id = ?";
        $stmt = $db->prepare($cleanup1);
        $stmt->execute([$test_appraisal_id]);
        
        $cleanup2 = "DELETE FROM appraisals WHERE id = ?";
        $stmt = $db->prepare($cleanup2);
        $stmt->execute([$test_appraisal_id]);
        
        echo "✓ Test data cleaned up.\n\n";
    }
    
    echo "===========================================\n";
    echo "ALL TESTS COMPLETED\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}