<?php
// includes/config.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants only if not already defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/Movie/');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'movie');
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug mode
define('DEBUG', true);
?>