<?php
// includes/database.php

// Include config if not already loaded
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        
        $this->connection->set_charset("utf8mb4");
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($query) {
        return $this->connection->prepare($query);
    }
    
    public function query($query) {
        return $this->connection->query($query);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function getError() {
        return $this->connection->error;
    }
    
    public function getInsertId() {
        return $this->connection->insert_id;
    }
}

// Function to get database instance
function get_db() {
    return Database::getInstance()->getConnection();
}
?>