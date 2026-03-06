<?php
// includes/functions.php

// Check if config is loaded
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/config.php';
}

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['user_role'] === 'Admin';
}

function get_db_connection() {
    global $conn;
    
    if (!isset($conn) || !$conn->ping()) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            if (DEBUG) {
                die("<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>
                        <h3>Database Connection Error</h3>
                        <p><strong>Error:</strong> " . $conn->connect_error . "</p>
                        <p><strong>Database:</strong> " . DB_NAME . "</p>
                        <p><strong>Host:</strong> " . DB_HOST . "</p>
                     </div>");
            } else {
                die("Database connection failed.");
            }
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

function debug_log($message, $data = null) {
    if (DEBUG) {
        echo "<script>console.log('PHP: " . addslashes($message) . "');</script>";
        if ($data) {
            echo "<script>console.log('PHP Data:', " . json_encode($data) . ");</script>";
        }
    }
}
?>