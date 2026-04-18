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

$current_admin_id = $_SESSION['user_id'];
$current_admin_name = $_SESSION['user_name'];
$current_admin_role = $_SESSION['user_role'];
$is_owner = ($current_admin_role === 'Owner');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';
$edit_mode = false;
$edit_user = null;

// Get owner account ID (the first Owner account created)
$owner_result = $conn->query("SELECT u_id FROM users WHERE u_role = 'Owner' LIMIT 1");
$owner_id = $owner_result ? ($owner_result->fetch_assoc()['u_id'] ?? null) : null;

function log_admin_action($conn, $action, $details, $target_id = null) {
    global $current_admin_id;
    
    $stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, details, target_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $current_admin_id, $action, $details, $target_id);
    $stmt->execute();
    $stmt->close();
}

// ADD NEW ADMIN ACCOUNT (Admin or Owner can do this)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $name = sanitize_input(trim($_POST['name']));
    $email = sanitize_input(trim($_POST['email']));
    $password = sanitize_input(trim($_POST['password']));
    $confirm_password = sanitize_input(trim($_POST['confirm_password']));
    $role = 'Admin';
    
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        $username = strtolower(str_replace(' ', '_', $name)) . '_' . rand(100, 999);
        
        $check_stmt = $conn->prepare("SELECT u_id FROM users WHERE u_email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (u_name, u_username, u_email, u_pass, u_role, u_status, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, 'Active', ?, ?)");
            $stmt->bind_param("sssssis", $name, $username, $email, $hashed_password, $role, $current_admin_id, $current_admin_name);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                $success = "New Admin added successfully! Username: " . $username;
                
                log_admin_action($conn, 'ADD_ADMIN', "Added new admin: $name ($email)", $new_user_id);
                
                $_POST = array();
            } else {
                $error = "Failed to add admin: " . $conn->error;
            }
            
            $stmt->close();
        }
        
        $check_stmt->close();
    }
}

// ADD NEW CUSTOMER ACCOUNT (Admin or Owner can do this)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = sanitize_input(trim($_POST['customer_name']));
    $username = sanitize_input(trim($_POST['customer_username']));
    $email = sanitize_input(trim($_POST['customer_email']));
    $password = sanitize_input(trim($_POST['customer_password']));
    $confirm_password = sanitize_input(trim($_POST['customer_confirm_password']));
    $role = 'Customer';
    
    if (empty($name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All customer fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $error = "Username must be 3-50 characters and can only contain letters, numbers, and underscores!";
    } else {
        $check_username = $conn->prepare("SELECT u_id FROM users WHERE u_username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        $username_result = $check_username->get_result();
        
        if ($username_result->num_rows > 0) {
            $error = "Username already taken! Please choose a different username.";
        } else {
            $check_username->close();
            
            $check_email = $conn->prepare("SELECT u_id FROM users WHERE u_email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $email_result = $check_email->get_result();
            
            if ($email_result->num_rows > 0) {
                $error = "Email already registered! Please use a different email.";
            } else {
                $check_email->close();
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (u_name, u_username, u_email, u_pass, u_role, u_status, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, 'Active', ?, ?)");
                $stmt->bind_param("sssssis", $name, $username, $email, $hashed_password, $role, $current_admin_id, $current_admin_name);
                
                if ($stmt->execute()) {
                    $new_user_id = $stmt->insert_id;
                    $success = "Customer account created successfully! Username: " . $username;
                    
                    log_admin_action($conn, 'ADD_CUSTOMER', "Added new customer: $name ($email)", $new_user_id);
                    
                    $_POST = array();
                } else {
                    $error = "Failed to create customer: " . $conn->error;
                }
                
                $stmt->close();
            }
        }
    }
}

// UPDATE ADMIN STATUS (Admin or Owner can do this, but Owner has more privileges)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $id = intval($_POST['id']);
    $status = sanitize_input(trim($_POST['status']));
    
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role, created_by FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    // Check if this is the owner account - PROTECT OWNER
    if ($id == $owner_id) {
        $error = "Cannot modify the Owner account!";
    } elseif ($user_data['u_role'] !== 'Admin') {
        $error = "You can only edit other administrators!";
    } elseif ($id == $current_admin_id && $status == 'Inactive') {
        $error = "You cannot deactivate your own account!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET u_status = ? WHERE u_id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $success = "Admin updated successfully!";
            
            log_admin_action($conn, 'UPDATE_ADMIN', "Updated admin: {$user_data['u_name']} ({$user_data['u_email']}) - Status: $status", $id);
        } else {
            $error = "Failed to update admin: " . $stmt->error;
        }
        $stmt->close();
    }
}

// RESET ADMIN PASSWORD (Admin or Owner can do this)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_admin_password'])) {
    $id = intval($_POST['id']);
    $new_password = sanitize_input(trim($_POST['new_password']));
    $confirm_password = sanitize_input(trim($_POST['confirm_password']));
    
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role, created_by FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (empty($new_password)) {
        $error = "Password cannot be empty!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif ($id == $owner_id) {
        $error = "Cannot reset password for the Owner account!";
    } elseif ($user_data['u_role'] !== 'Admin') {
        $error = "You can only reset passwords for other administrators!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET u_pass = ? WHERE u_id = ?");
        $stmt->bind_param("si", $hashed_password, $id);
        
        if ($stmt->execute()) {
            $success = "Admin password reset successfully!";
            
            log_admin_action($conn, 'RESET_ADMIN_PASSWORD', "Reset password for admin: {$user_data['u_name']} ({$user_data['u_email']})", $id);
        } else {
            $error = "Failed to reset password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// RESET CUSTOMER PASSWORD (Admin or Owner can do this for any customer)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_customer_password'])) {
    $id = intval($_POST['id']);
    $new_password = sanitize_input(trim($_POST['new_password']));
    $confirm_password = sanitize_input(trim($_POST['confirm_password']));
    
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (empty($new_password)) {
        $error = "Password cannot be empty!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif ($user_data['u_role'] !== 'Customer') {
        $error = "Invalid user type!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET u_pass = ? WHERE u_id = ?");
        $stmt->bind_param("si", $hashed_password, $id);
        
        if ($stmt->execute()) {
            $success = "Customer password reset successfully!";
            
            log_admin_action($conn, 'RESET_CUSTOMER_PASSWORD', "Reset password for customer: {$user_data['u_name']} ({$user_data['u_email']})", $id);
        } else {
            $error = "Failed to reset password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// DELETE CUSTOMER (Admin or Owner can delete any customer)
elseif (isset($_GET['delete_customer']) && is_numeric($_GET['delete_customer'])) {
    $id = intval($_GET['delete_customer']);
    
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user_data) {
        $error = "User not found!";
    } elseif ($user_data['u_role'] !== 'Customer') {
        $error = "Only customer accounts can be deleted!";
    } else {
        // Soft delete - set status to Inactive
        $stmt = $conn->prepare("UPDATE users SET u_status = 'Inactive', is_visible = 0 WHERE u_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Customer account deleted successfully!";
            
            log_admin_action($conn, 'DELETE_CUSTOMER', "Deleted customer: {$user_data['u_name']} ({$user_data['u_email']})", $id);
        } else {
            $error = "Failed to delete customer: " . $stmt->error;
        }
        $stmt->close();
    }
}

// DELETE ADMIN (Admin or Owner can delete other admins, but Owner cannot be deleted) - PROTECT OWNER
elseif (isset($_GET['delete_admin']) && is_numeric($_GET['delete_admin'])) {
    $id = intval($_GET['delete_admin']);
    
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user_data) {
        $error = "User not found!";
    } elseif ($id == $owner_id) {
        $error = "Cannot delete the Owner account!";
    } elseif ($id == $current_admin_id) {
        $error = "You cannot delete your own account!";
    } elseif ($user_data['u_role'] !== 'Admin') {
        $error = "You can only delete other administrators!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET u_status = 'Inactive', is_visible = 0 WHERE u_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Admin deleted successfully!";
            
            log_admin_action($conn, 'DELETE_ADMIN', "Deleted admin: {$user_data['u_name']} ({$user_data['u_email']})", $id);
        } else {
            $error = "Failed to delete admin: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all users
$users_result = $conn->query("
    SELECT u_id, u_name, u_username, u_email, u_role, u_status, created_at, created_by, created_by_name, last_login,
           CASE 
               WHEN created_by IS NULL THEN 'Self-Registered'
               ELSE created_by_name
           END as creator_display
    FROM users 
    WHERE (u_status = 'Active' OR (u_status = 'Inactive' AND is_visible = 1)) AND is_visible = 1
    ORDER BY 
        CASE u_role 
            WHEN 'Owner' THEN 0
            WHEN 'Admin' THEN 1 
            WHEN 'Customer' THEN 2 
            ELSE 3 
        END,
        created_at DESC
");

$users = [];
$owner = null;
$admins = [];
$customers = [];

if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
        if ($row['u_role'] === 'Owner') {
            $owner = $row;
        } elseif ($row['u_role'] === 'Admin') {
            $admins[] = $row;
        } else {
            $customers[] = $row;
        }
    }
}

// Get customer activity log with login and booking info
$customer_activity_log = [];
$customer_log_result = $conn->query("
    SELECT 
        cal.id,
        cal.customer_id,
        cal.action_type,
        cal.details,
        cal.created_at,
        u.u_name as customer_name,
        u.u_email as customer_email,
        u.last_login,
        CASE 
            WHEN cal.action_type = 'BOOKING' THEN CONCAT('Booked movie - ', cal.details)
            WHEN cal.action_type = 'MOVIE_VIEW' THEN CONCAT('Viewed movie - ', cal.details)
            WHEN cal.action_type = 'LOGIN' THEN 'Logged in to account'
            ELSE cal.details
        END as full_details,
        CASE 
            WHEN cal.action_type = 'BOOKING' THEN 'booking'
            WHEN cal.action_type = 'LOGIN' THEN 'login'
            ELSE 'other'
        END as activity_type
    FROM customer_activity_log cal
    LEFT JOIN users u ON cal.customer_id = u.u_id
    WHERE u.u_role = 'Customer' AND (u.is_visible = 1 OR u.is_visible IS NULL)
    ORDER BY cal.created_at DESC
    LIMIT 50
");

if ($customer_log_result) {
    while ($row = $customer_log_result->fetch_assoc()) {
        $customer_activity_log[] = $row;
    }
}

// Get admin activity log
$admin_activity_log = [];
$admin_log_result = $conn->query("
    SELECT al.*, u.u_name as admin_name
    FROM admin_activity_log al
    LEFT JOIN users u ON al.admin_id = u.u_id
    ORDER BY al.created_at DESC
    LIMIT 50
");

if ($admin_log_result) {
    while ($row = $admin_log_result->fetch_assoc()) {
        $admin_activity_log[] = $row;
    }
}

$admin_count = count($admins);
$customer_count = count($customers);
$active_admin_count = 0;
$active_customer_count = 0;

foreach ($admins as $admin) {
    if ($admin['u_status'] == 'Active') $active_admin_count++;
}

foreach ($customers as $customer) {
    if ($customer['u_status'] == 'Active') $active_customer_count++;
}

$total_users = count($users);

$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Users</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Create and manage user accounts</p>
        <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 10px;">Logged in as: <strong style="color: #3498db;"><?php echo $current_admin_name; ?></strong> (<?php echo $current_admin_role; ?>)</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div style="background: rgba(52, 152, 219, 0.1); border-radius: 12px; padding: 25px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px; font-weight: 700;"><?php echo $total_users; ?></div>
            <div style="color: white; font-size: 1rem; font-weight: 600;">Total Users</div>
        </div>
        <div style="background: rgba(52, 152, 219, 0.1); border-radius: 12px; padding: 25px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px; font-weight: 700;"><?php echo $admin_count + ($owner ? 1 : 0); ?></div>
            <div style="color: white; font-size: 1rem; font-weight: 600;">Total Admins</div>
            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); margin-top: 5px;"><?php echo $active_admin_count; ?> active</div>
        </div>
        <div style="background: rgba(52, 152, 219, 0.1); border-radius: 12px; padding: 25px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 10px; font-weight: 700;"><?php echo $customer_count; ?></div>
            <div style="color: white; font-size: 1rem; font-weight: 600;">Total Customers</div>
            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); margin-top: 5px;"><?php echo $active_customer_count; ?> active</div>
        </div>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(231, 76, 60, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(231, 76, 60, 0.3);">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Add New Admin Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-user-plus"></i> Add New Admin Account
        </h2>
        
        <form method="POST" action="" id="adminForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-user"></i> Full Name *
                    </label>
                    <input type="text" name="name" required 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Enter admin's full name">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-envelope"></i> Email Address *
                    </label>
                    <input type="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Enter admin's email">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-lock"></i> Password *
                    </label>
                    <input type="password" name="password" required 
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="At least 6 characters">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <input type="password" name="confirm_password" required 
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Confirm password">
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" name="add_admin" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Add Admin Account
                </button>
            </div>
        </form>
    </div>

    <!-- Add New Customer Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-user-plus"></i> Add New Customer Account
        </h2>
        
        <form method="POST" action="" id="customerForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-user"></i> Full Name *
                    </label>
                    <input type="text" name="customer_name" required 
                           value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''; ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Enter customer's full name">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-at"></i> Username *
                    </label>
                    <input type="text" name="customer_username" required 
                           value="<?php echo isset($_POST['customer_username']) ? htmlspecialchars($_POST['customer_username']) : ''; ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Choose a username (3-50 characters)"
                           pattern="[a-zA-Z0-9_]+"
                           title="Only letters, numbers, and underscores allowed">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-envelope"></i> Email Address *
                    </label>
                    <input type="email" name="customer_email" required 
                           value="<?php echo isset($_POST['customer_email']) ? htmlspecialchars($_POST['customer_email']) : ''; ?>"
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Enter customer's email">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-lock"></i> Password *
                    </label>
                    <input type="password" name="customer_password" required 
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="At least 6 characters">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <input type="password" name="customer_confirm_password" required 
                           style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 10px; color: white; font-size: 1rem;"
                           placeholder="Confirm password">
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" name="add_customer" style="padding: 16px 45px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); display: inline-flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-plus"></i> Add Customer Account
                </button>
            </div>
        </form>
    </div>

    <!-- Owner Section (if exists) - PROTECTED DISPLAY -->
    <?php if ($owner): ?>
    <div style="background: rgba(255, 215, 0, 0.1); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 2px solid #ffd700; border-left: 10px solid #ffd700;">
        <h2 style="color: #ffd700; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #ffd700; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-crown"></i> Owner Account
        </h2>
        
        <div style="background: rgba(255, 215, 0, 0.05); border-radius: 10px; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div>
                    <div style="color: white; font-size: 1.2rem; font-weight: 700; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($owner['u_name']); ?>
                        <span style="background: #ffd700; color: #333; padding: 3px 12px; border-radius: 20px; font-size: 0.75rem; margin-left: 10px;">OWNER</span>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
                        <?php echo htmlspecialchars($owner['u_email']); ?> • Username: <?php echo htmlspecialchars($owner['u_username']); ?>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.5); font-size: 0.85rem; margin-top: 5px;">
                        Last login: <?php echo $owner['last_login'] ? date('M d, Y h:i A', strtotime($owner['last_login'])) : 'Never'; ?>
                    </div>
                </div>
                <div>
                    <span style="background: rgba(255, 215, 0, 0.2); color: #ffd700; padding: 8px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                        <i class="fas fa-shield-alt"></i> Protected Account - Cannot be modified
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Administrators Section -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-crown"></i> Administrators (<?php echo count($admins); ?>)
        </h2>
        
        <?php if (empty($admins)): ?>
        <div style="text-align: center; padding: 50px; color: rgba(255, 255, 255, 0.6);">
            <i class="fas fa-crown fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">No other administrators found.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto; border-radius: 10px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
                <thead>
                    <tr>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">ID</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Admin Details</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Created By</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Status</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): 
                        $is_current_user = $admin['u_id'] == $current_admin_id;
                    ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); <?php echo $is_current_user ? 'background: rgba(52, 152, 219, 0.05);' : ''; ?>">
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $admin['u_id']; ?></td>
                        <td style="padding: 16px;">
                            <div style="color: white; font-size: 1.1rem; font-weight: 700; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($admin['u_name']); ?>
                                <?php if ($is_current_user): ?>
                                <span style="color: #3498db; font-size: 0.8rem; margin-left: 5px;">(You)</span>
                                <?php endif; ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
                                <?php echo htmlspecialchars($admin['u_email']); ?><br>
                                <small>Username: <?php echo htmlspecialchars($admin['u_username']); ?></small>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.5); font-size: 0.8rem; margin-top: 3px;">
                                Last login: <?php echo $admin['last_login'] ? date('M d, Y h:i A', strtotime($admin['last_login'])) : 'Never'; ?>
                            </div>
                        </td>
                        <td style="padding: 16px;">
                            <span style="background: <?php echo $admin['creator_display'] == 'Self-Registered' ? 'rgba(149,165,166,0.2)' : 'rgba(52,152,219,0.2)'; ?>; color: <?php echo $admin['creator_display'] == 'Self-Registered' ? '#95a5a6' : '#3498db'; ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                                <i class="fas <?php echo $admin['creator_display'] == 'Self-Registered' ? 'fa-user' : 'fa-user-cog'; ?>"></i>
                                <?php echo $admin['creator_display']; ?>
                            </span>
                        </td>
                        <td style="padding: 16px;">
                            <span style="background: <?php echo $admin['u_status'] == 'Active' ? 'rgba(46, 204, 113, 0.2)' : 'rgba(108, 117, 125, 0.2)'; ?>; color: <?php echo $admin['u_status'] == 'Active' ? '#2ecc71' : '#6c757d'; ?>; padding: 8px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas <?php echo $admin['u_status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $admin['u_status']; ?>
                            </span>
                        </td>
                        <td style="padding: 16px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" 
                                        style="padding: 8px 16px; background: rgba(52, 152, 219, 0.2); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;"
                                        onclick="openEditModal(<?php echo $admin['u_id']; ?>, '<?php echo addslashes($admin['u_name']); ?>', '<?php echo $admin['u_status']; ?>', <?php echo $is_current_user ? 'true' : 'false'; ?>)"
                                        <?php echo $is_current_user ? 'disabled' : ''; ?>>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <button type="button" 
                                        style="padding: 8px 16px; background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid rgba(23, 162, 184, 0.3); border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;"
                                        onclick="openResetPasswordModal(<?php echo $admin['u_id']; ?>, '<?php echo addslashes($admin['u_name']); ?>', 'admin')">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                                
                                <?php if (!$is_current_user): ?>
                                <a href="index.php?page=admin/manage-users&delete_admin=<?php echo $admin['u_id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(231, 76, 60, 0.3); display: inline-flex; align-items: center; gap: 5px;"
                                   onclick="return confirm('Are you sure you want to delete admin \'<?php echo addslashes($admin['u_name']); ?>\'?\nThis will deactivate their account.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Customers Section -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-users"></i> Customers (<?php echo $customer_count; ?>)
        </h2>
        
        <?php if (empty($customers)): ?>
        <div style="text-align: center; padding: 50px; color: rgba(255, 255, 255, 0.6);">
            <i class="fas fa-users fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">No customers found.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto; border-radius: 10px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
                <thead>
                    <tr>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">ID</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Customer Details</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Created By</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Status</th>
                        <th style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 16px; text-align: left; font-weight: 700; font-size: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <td style="padding: 16px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $customer['u_id']; ?></td>
                        <td style="padding: 16px;">
                            <div style="color: white; font-size: 1.1rem; font-weight: 700; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($customer['u_name']); ?>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
                                <?php echo htmlspecialchars($customer['u_email']); ?><br>
                                <small>Username: <?php echo htmlspecialchars($customer['u_username']); ?></small>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.5); font-size: 0.8rem; margin-top: 3px;">
                                Last login: <?php echo $customer['last_login'] ? date('M d, Y h:i A', strtotime($customer['last_login'])) : 'Never'; ?>
                            </div>
                        </td>
                        <td style="padding: 16px;">
                            <span style="background: <?php echo $customer['creator_display'] == 'Self-Registered' ? 'rgba(149,165,166,0.2)' : 'rgba(52,152,219,0.2)'; ?>; color: <?php echo $customer['creator_display'] == 'Self-Registered' ? '#95a5a6' : '#3498db'; ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                                <i class="fas <?php echo $customer['creator_display'] == 'Self-Registered' ? 'fa-user' : 'fa-user-cog'; ?>"></i>
                                <?php echo $customer['creator_display']; ?>
                            </span>
                        </td>
                        <td style="padding: 16px;">
                            <span style="background: <?php echo $customer['u_status'] == 'Active' ? 'rgba(46, 204, 113, 0.2)' : 'rgba(108, 117, 125, 0.2)'; ?>; color: <?php echo $customer['u_status'] == 'Active' ? '#2ecc71' : '#6c757d'; ?>; padding: 8px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas <?php echo $customer['u_status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $customer['u_status']; ?>
                            </span>
                        </td>
                        <td style="padding: 16px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" 
                                        style="padding: 8px 16px; background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid rgba(23, 162, 184, 0.3); border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;"
                                        onclick="openResetPasswordModal(<?php echo $customer['u_id']; ?>, '<?php echo addslashes($customer['u_name']); ?>', 'customer')">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                                
                                <a href="index.php?page=admin/manage-users&delete_customer=<?php echo $customer['u_id']; ?>" 
                                   style="padding: 8px 16px; background: rgba(231, 76, 60, 0.2); color: #e74c3c; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(231, 76, 60, 0.3); display: inline-flex; align-items: center; gap: 5px;"
                                   onclick="return confirm('Are you sure you want to delete customer \'<?php echo addslashes($customer['u_name']); ?>\'?\nThis will deactivate their account.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Activity Logs Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(600px, 1fr)); gap: 30px; margin-bottom: 40px;">
        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-history"></i> Recent Admin Actions
            </h2>
            
            <?php if (empty($admin_activity_log)): ?>
            <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.6);">
                <i class="fas fa-history fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No recent admin actions found.</p>
            </div>
            <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                <?php foreach ($admin_activity_log as $log): 
                    $action_icon = 'fa-cog';
                    $action_color = '#3498db';
                    
                    if (strpos($log['action'], 'ADD') !== false) {
                        $action_icon = 'fa-plus-circle';
                        $action_color = '#2ecc71';
                    } elseif (strpos($log['action'], 'UPDATE') !== false || strpos($log['action'], 'EDIT') !== false) {
                        $action_icon = 'fa-edit';
                        $action_color = '#f39c12';
                    } elseif (strpos($log['action'], 'DELETE') !== false) {
                        $action_icon = 'fa-trash';
                        $action_color = '#e74c3c';
                    } elseif (strpos($log['action'], 'RESET') !== false) {
                        $action_icon = 'fa-key';
                        $action_color = '#9b59b6';
                    }
                ?>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid <?php echo $action_color; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas <?php echo $action_icon; ?>" style="color: <?php echo $action_color; ?>; font-size: 1.2rem;"></i>
                            <div>
                                <div style="color: white; font-weight: 600; font-size: 1rem;">
                                    <?php 
                                    $action_map = [
                                        'ADD_ADMIN' => 'Added Admin',
                                        'ADD_CUSTOMER' => 'Added Customer',
                                        'UPDATE_ADMIN' => 'Updated Admin',
                                        'RESET_ADMIN_PASSWORD' => 'Reset Admin Password',
                                        'RESET_CUSTOMER_PASSWORD' => 'Reset Customer Password',
                                        'DELETE_ADMIN' => 'Deleted Admin',
                                        'DELETE_CUSTOMER' => 'Deleted Customer'
                                    ];
                                    echo $action_map[$log['action']] ?? $log['action'];
                                    ?>
                                </div>
                                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem;">
                                    By: <strong style="color: #3498db;"><?php echo $log['admin_name'] ?? 'System'; ?></strong>
                                </div>
                            </div>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.8rem;">
                            <?php echo date('M d, h:i A', strtotime($log['created_at'])); ?>
                        </div>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem; padding-left: 30px;">
                        <?php echo htmlspecialchars($log['details']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
            <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-user-clock"></i> Recent Customer Activity
            </h2>
            
            <?php if (empty($customer_activity_log)): ?>
            <div style="text-align: center; padding: 30px; color: rgba(255, 255, 255, 0.6);">
                <i class="fas fa-user-clock fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No recent customer actions found.</p>
            </div>
            <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                <?php foreach ($customer_activity_log as $log): 
                    $action_icon = 'fa-user';
                    $action_color = '#007bff';
                    
                    if ($log['action_type'] === 'BOOKING') {
                        $action_icon = 'fa-ticket-alt';
                        $action_color = '#2ecc71';
                    } elseif ($log['action_type'] === 'MOVIE_VIEW') {
                        $action_icon = 'fa-eye';
                        $action_color = '#9b59b6';
                    } elseif ($log['action_type'] === 'LOGIN') {
                        $action_icon = 'fa-sign-in-alt';
                        $action_color = '#f39c12';
                    }
                ?>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid <?php echo $action_color; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas <?php echo $action_icon; ?>" style="color: <?php echo $action_color; ?>; font-size: 1.2rem;"></i>
                            <div>
                                <div style="color: white; font-weight: 600; font-size: 1rem;">
                                    <?php 
                                    $action_map = [
                                        'BOOKING' => 'Movie Booking',
                                        'MOVIE_VIEW' => 'Movie View',
                                        'LOGIN' => 'Login'
                                    ];
                                    echo $action_map[$log['action_type']] ?? $log['action_type'];
                                    ?>
                                </div>
                                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem;">
                                    By: <strong style="color: #3498db;"><?php echo htmlspecialchars($log['customer_name']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.8rem;">
                            <?php echo date('M d, h:i A', strtotime($log['created_at'])); ?>
                        </div>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem; padding-left: 30px;">
                        <?php echo htmlspecialchars($log['full_details']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; border: 1px solid rgba(52, 152, 219, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(52, 152, 219, 0.3);">
            <h3 style="color: #3498db; font-size: 1.3rem;">Edit Administrator</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="id" id="editUserId">
            <input type="hidden" name="update_admin" value="1">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Admin Name</label>
                <input type="text" id="editUserName" style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; font-size: 1rem;" readonly>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Status *</label>
                <select id="editUserStatus" name="status" required style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                    <option value="">Select Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            
            <div id="selfEditWarning" style="background: rgba(243, 156, 18, 0.2); color: #f39c12; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(243, 156, 18, 0.3); display: none;">
                <i class="fas fa-exclamation-triangle"></i> You cannot edit your own account!
            </div>
            
            <div style="text-align: center; margin-top: 25px;">
                <button type="submit" style="padding: 12px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" onclick="closeEditModal()" style="padding: 12px 30px; background: rgba(255, 255, 255, 0.1); color: white; border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-left: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; border: 1px solid rgba(52, 152, 219, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(52, 152, 219, 0.3);">
            <h3 style="color: #3498db; font-size: 1.3rem;" id="resetModalTitle">Reset Password</h3>
            <button onclick="closeResetPasswordModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="" id="resetPasswordForm">
            <input type="hidden" name="id" id="resetUserId">
            <input type="hidden" name="reset_admin_password" value="1" id="resetAdminField">
            <input type="hidden" name="reset_customer_password" value="1" id="resetCustomerField">
            
            <div style="margin-bottom: 20px;">
                <div id="resetUserNameLabel" style="color: white; font-size: 1rem;"></div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">New Password *</label>
                <input type="password" id="new_password" name="new_password" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; color: white; font-size: 1rem;"
                       placeholder="Enter new password">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Confirm New Password *</label>
                <input type="password" id="confirm_new_password" name="confirm_password" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; color: white; font-size: 1rem;"
                       placeholder="Confirm new password">
            </div>
            
            <div style="text-align: center; margin-top: 25px;">
                <button type="submit" style="padding: 12px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-key"></i> Reset Password
                </button>
                <button type="button" onclick="closeResetPasswordModal()" style="padding: 12px 30px; background: rgba(255, 255, 255, 0.1); color: white; border: 2px solid rgba(52, 152, 219, 0.3); border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-left: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    input:focus, select:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.12);
        border-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
    }
    
    button:hover:not(:disabled) {
        transform: translateY(-2px);
        opacity: 0.9;
    }
    
    tr:hover {
        background: rgba(255, 255, 255, 0.03) !important;
    }
    
    .admin-content {
        scrollbar-width: thin;
        scrollbar-color: #3498db #2c3e50;
    }
    
    .admin-content::-webkit-scrollbar {
        width: 8px;
    }
    
    .admin-content::-webkit-scrollbar-track {
        background: #2c3e50;
    }
    
    .admin-content::-webkit-scrollbar-thumb {
        background: #3498db;
        border-radius: 4px;
    }
    
    :root {
        --admin-primary: #2c3e50;
        --admin-secondary: #34495e;
        --admin-accent: #3498db;
        --admin-success: #2ecc71;
        --admin-danger: #e74c3c;
        --admin-warning: #f39c12;
        --admin-light: #ecf0f1;
        --admin-dark: #1a252f;
    }
    
    @media (max-width: 768px) {
        .admin-content {
            padding: 15px;
        }
        
        div > div {
            padding: 20px;
        }
        
        table {
            font-size: 0.9rem;
        }
        
        .grid-2-col {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script>
function openEditModal(userId, userName, userStatus, isCurrentUser) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUserName').value = userName;
    document.getElementById('editUserStatus').value = userStatus;
    
    const warningDiv = document.getElementById('selfEditWarning');
    if (isCurrentUser) {
        warningDiv.style.display = 'block';
        document.getElementById('editUserStatus').disabled = true;
    } else {
        warningDiv.style.display = 'none';
        document.getElementById('editUserStatus').disabled = false;
    }
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openResetPasswordModal(userId, userName, userType) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserNameLabel').innerHTML = 
        `<strong style="color: white;">${userType === 'admin' ? 'Admin' : 'Customer'}:</strong> ${userName}<br>
         <small style="color: rgba(255,255,255,0.6);">Enter new password for this ${userType}</small>`;
    
    if (userType === 'admin') {
        document.getElementById('resetAdminField').disabled = false;
        document.getElementById('resetCustomerField').disabled = true;
        document.getElementById('resetCustomerField').value = '';
        document.getElementById('resetAdminField').value = '1';
    } else {
        document.getElementById('resetAdminField').disabled = true;
        document.getElementById('resetCustomerField').disabled = false;
        document.getElementById('resetAdminField').value = '';
        document.getElementById('resetCustomerField').value = '1';
    }
    
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}

window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const resetModal = document.getElementById('resetPasswordModal');
    
    if (event.target == editModal) {
        closeEditModal();
    }
    if (event.target == resetModal) {
        closeResetPasswordModal();
    }
}

document.getElementById('adminForm')?.addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    const email = document.querySelector('input[name="email"]').value;
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address!');
        return false;
    }
    
    return true;
});

document.getElementById('customerForm')?.addEventListener('submit', function(e) {
    const username = document.querySelector('input[name="customer_username"]').value;
    const email = document.querySelector('input[name="customer_email"]').value;
    const password = document.querySelector('input[name="customer_password"]').value;
    const confirmPassword = document.querySelector('input[name="customer_confirm_password"]').value;
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
        e.preventDefault();
        alert('Username must be 3-50 characters and can only contain letters, numbers, and underscores!');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address!');
        return false;
    }
    
    return true;
});

document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_new_password').value;
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    return true;
});

const inputs = document.querySelectorAll('input, select');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.style.transition = 'all 0.3s ease';
    });
    
    input.addEventListener('blur', function() {
        this.style.transition = 'none';
    });
});
</script>

</div>
</body>
</html>