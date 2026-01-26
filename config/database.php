<?php
// config/database.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Get database credentials from environment variables or constants
        $this->host = defined('DB_HOST') ? DB_HOST : getenv('DB_HOST');
        $this->db_name = defined('DB_NAME') ? DB_NAME : getenv('DB_NAME');
        $this->username = defined('DB_USER') ? DB_USER : getenv('DB_USER');
        $this->password = defined('DB_PASS') ? DB_PASS : getenv('DB_PASS');
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            
            // Don't expose database details in production
            if (defined('APP_ENV') && APP_ENV === 'production') {
                throw new Exception("Database connection failed");
            } else {
                throw new Exception("Database connection failed: " . $exception->getMessage());
            }
        }

        return $this->conn;
    }
}
?>