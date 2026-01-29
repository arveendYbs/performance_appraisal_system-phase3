<?php
// admin/departments/get_superior_chain.php
require_once __DIR__ . '/../../config/config.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_GET['user_id'] ?? 0;
$chain = [];
$current_id = $user_id;

// We climb 5 steps (L2 to L6)
for ($i = 2; $i <= 6; $i++) {
    $stmt = $db->prepare("SELECT u.id, u.name, u.direct_superior, p.job_title 
                          FROM users u 
                          LEFT JOIN positions p ON u.position_id = p.id 
                          WHERE u.id = (SELECT direct_superior FROM users WHERE id = ?)");
    $stmt->execute([$current_id]);
    $boss = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($boss) {
        $chain[$i] = $boss;
        $current_id = $boss['id'];
    } else {
        break;
    }
}
echo json_encode($chain);