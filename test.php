<?php
require_once 'vendor/autoload.php';
require_once 'config/config.php';

// session_start() probably already in config.php

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

// example: login by email
$user->email = trim($_POST['email']);

if ($user->readOne()) {

    // user found
    if (password_verify(trim($_POST['password']), $user->password)) {

        $_SESSION['user_id'] = $user->id;
        header("Location: dashboard.php");
        exit;

    } else {
        echo "Wrong password";
    }

} else {
    echo "User not found";
}
