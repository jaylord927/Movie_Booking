<?php
date_default_timezone_set('Asia/Manila');

// Load environment variables from .env file
if (file_exists(dirname(__DIR__) . '/.env')) {
    $lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv("$key=$value");
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants from environment or use defaults
if (!defined('SITE_URL')) {
    define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/Movie_Booking/');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'movie_booking');
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug mode
define('DEBUG', true);
?>