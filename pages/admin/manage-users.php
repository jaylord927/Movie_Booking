<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Owner')) {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

// Get the user type from URL parameter
$user_type = isset($_GET['type']) ? $_GET['type'] : 'customer';

// Navigation tabs
?>
<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Users</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Manage administrators, staff, and customer accounts</p>
    </div>

    <!-- User Type Tabs -->
    <div style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid rgba(52, 152, 219, 0.3); padding-bottom: 10px; flex-wrap: wrap;">
        <a href="?page=admin/manage-users&type=customer" 
           style="padding: 12px 25px; background: <?php echo $user_type == 'customer' ? 'linear-gradient(135deg, #3498db 0%, #2980b9 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;
                  transition: all 0.3s ease;">
            <i class="fas fa-users"></i> Customers
        </a>
        <a href="?page=admin/manage-users&type=staff" 
           style="padding: 12px 25px; background: <?php echo $user_type == 'staff' ? 'linear-gradient(135deg, #2ecc71 0%, #27ae60 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;
                  transition: all 0.3s ease;">
            <i class="fas fa-user-tie"></i> Staff
        </a>
        <a href="?page=admin/manage-users&type=admin" 
           style="padding: 12px 25px; background: <?php echo $user_type == 'admin' ? 'linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%)' : 'rgba(255,255,255,0.1)'; ?>; 
                  color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;
                  transition: all 0.3s ease;">
            <i class="fas fa-crown"></i> Administrators
        </a>
    </div>

    <?php
    // Include the appropriate user management file based on type
    $user_file = $root_dir . '/pages/admin/users/user-' . $user_type . '.php';
    
    if (file_exists($user_file)) {
        include $user_file;
    } else {
        echo '<div style="background: rgba(231, 76, 60, 0.2); color: #ff9999; padding: 20px; border-radius: 10px; text-align: center;">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
                <p style="margin-top: 10px;">Invalid user type selection.</p>
              </div>';
    }
    ?>
</div>

<style>
a:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}
@media (max-width: 768px) {
    .admin-content { padding: 15px; }
}
</style>

