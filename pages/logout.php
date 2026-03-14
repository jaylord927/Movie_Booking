<?php
// pages/logout.php (Simple Version)

// Go up one level from pages/ to root
$root_dir = dirname(__DIR__);

// Include config
require_once $root_dir . '/includes/config.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to home page
header("Location: " . SITE_URL . "index.php?page=home");
exit();
?>