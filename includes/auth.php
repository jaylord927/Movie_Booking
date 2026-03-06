<?php
// includes/auth.php
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        redirect(SITE_URL . "index.php?page=login");
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'Admin') {
        redirect(SITE_URL . "index.php");
    }
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? 0;
}

function get_current_user_name() {
    return $_SESSION['user_name'] ?? 'Guest';
}

function get_current_user_role() {
    return $_SESSION['user_role'] ?? 'Guest';
}
?>