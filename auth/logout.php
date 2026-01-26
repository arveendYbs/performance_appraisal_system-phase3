
<?php
// auth/logout.php

require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    // Log the logout activity
    logActivity($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], null, null, 'User logged out');
    
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Destroy session
    session_destroy();
}

redirect(BASE_URL . '/auth/login.php', 'You have been successfully logged out.', 'success');
?>