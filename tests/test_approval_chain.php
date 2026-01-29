<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/ApprovalChain.php';

// Start HTML output
echo '<!DOCTYPE html>
<html>
<head>
    <title>Approval Chain Engine - Automated Test Runner</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f5f5f5; 
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 20px; 
            text-align: center; 
            border-radius: 10px; 
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .employee-card { 
            background: white; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            padding: 20px; 
            margin: 20px 0; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .employee-header { 
            background-color: #e3f2fd; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 15px;
            border-left: 4px solid #2196f3;
        }
        .status-success { 
            color: #4caf50; 
            font-weight: bold; 
        }
        .status-error { 
            color: #f44336; 
            font-weight: bold; 
        }
        .status-info { 
            color: #2196f3; 
            font-weight: bold; 
        }
        .chain-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
            background: white;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chain-table th { 
            background-color: #ff9800; 
            color: white; 
            padding: 12px; 
            text-align: left;
        }
        .chain-table td { 
            padding: 12px; 
            border-bottom: 1px solid #eee; 
        }
        .chain-table tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .chain-table tr:hover { 
            background-color: #f5f5f5; 
        }
        .section-title { 
            color: #ff9800; 
            border-bottom: 2px solid #ff9800; 
            padding-bottom: 5px; 
            margin: 20px 0 10px 0;
        }
        .final-yes { 
            color: #4caf50; 
            font-weight: bold; 
        }
        .final-no { 
            color: #9e9e9e; 
        }
        .cleanup { 
            background-color: #e8f5e8; 
            padding: 10px; 
            border-radius: 5px; 
            border-left: 3px solid #4caf50;
        }
        .separator { 
            height: 1px; 
            background: linear-gradient(to right, transparent, #ccc, transparent); 
            margin: 30px 0; 
        }
    </style>
</head>
<body>';

echo '<div class="header">
    <h1>APPROVAL CHAIN ENGINE</h1>
    <h2>AUTOMATED TEST RUNNER</h2>
</div>';

try {
    $database = new Database();
    $db = $database->getConnection();
    $approvalChain = new ApprovalChain($db);

    // Get test subjects
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.emp_number, u.department_id, u.position_id, u.direct_superior, p.employee_type 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id 
        WHERE u.is_active = 1 AND u.department_id IS NOT NULL LIMIT 3
    ");
    $stmt->execute();
    $test_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($test_employees as $emp) {
        echo '<div class="employee-card">';
        echo '<div class="employee-header">';
        echo '<h3>TESTING EMPLOYEE: ' . htmlspecialchars($emp['name']) . '</h3>';
        echo '<p><strong>ID:</strong> ' . $emp['id'] . ' | <strong>Type:</strong> ' . htmlspecialchars($emp['employee_type']) . '</p>';
        echo '</div>';

        // First, get a valid form_id for testing
        $formStmt = $db->prepare("SELECT id FROM forms LIMIT 1");
        $formStmt->execute();
        $form = $formStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$form) {
            echo '<p class="status-error">✗ No forms found in database. Cannot create appraisal.</p>';
            echo '</div>';
            echo '<div class="separator"></div>';
            continue;
        }
        
        $form_id = $form['id'];

        // 1. Create Appraisal with form_id
        try {
            $insertStmt = $db->prepare("INSERT INTO appraisals (user_id, form_id, status) VALUES (?, ?, 'draft')");
            $insertStmt->execute([$emp['id'], $form_id]);
            $appraisal_id = $db->lastInsertId();
            
            echo '<p class="status-success">✓ Appraisal created successfully (ID: ' . $appraisal_id . ')</p>';

            // 2. Build Chain
            $chain = $approvalChain->buildApprovalChain($appraisal_id, $emp['id']);

            if ($chain && count($chain) > 0) {
                echo '<p class="status-success">✓ Chain built successfully</p>';
                
                echo '<h4 class="section-title">APPROVAL CHAIN</h4>';
                echo '<table class="chain-table">';
                echo '<thead><tr><th>Level</th><th>Approver Name</th><th>Role</th><th>Final?</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($chain as $lvl) {
                    // Fetch name for the table
                    $u_stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                    $u_stmt->execute([$lvl['approver_id']]);
                    $u_name = $u_stmt->fetchColumn() ?: "Unknown User";
                    
                    $final_class = $lvl['is_final_approver'] ? 'final-yes' : 'final-no';
                    $final_text = $lvl['is_final_approver'] ? 'YES' : 'NO';
                    
                    echo '<tr>';
                    echo '<td>' . $lvl['level'] . '</td>';
                    echo '<td>' . htmlspecialchars(substr($u_name, 0, 30)) . '</td>';
                    echo '<td>' . htmlspecialchars($lvl['approver_role']) . '</td>';
                    echo '<td class="' . $final_class . '">' . $final_text . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                
            } else {
                echo '<p class="status-error">✗ No approval chain generated</p>';
            }

        } catch (Exception $e) {
            echo '<p class="status-error">✗ Error creating appraisal: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }

        // 3. Cleanup
        if (isset($appraisal_id)) {
            try {
                $db->prepare("DELETE FROM appraisal_approvals WHERE appraisal_id = ?")->execute([$appraisal_id]);
                $db->prepare("DELETE FROM appraisals WHERE id = ?")->execute([$appraisal_id]);
                echo '<div class="cleanup">';
                echo '<p>↺ Cleanup completed for appraisal ID: ' . $appraisal_id . '</p>';
                echo '</div>';
            } catch (Exception $cleanupError) {
                echo '<p class="status-error">⚠ Cleanup failed: ' . htmlspecialchars($cleanupError->getMessage()) . '</p>';
            }
        }
        
        echo '</div>';
        echo '<div class="separator"></div>';
    }

} catch (Exception $e) {
    echo '<div style="background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; border-left: 4px solid #c62828;">';
    echo '<h3>FATAL ERROR</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}

echo '</body></html>';
?>
