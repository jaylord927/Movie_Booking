<?php
$root_dir = dirname(dirname(dirname(__DIR__)));
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

// Open database connection
$conn = get_db_connection();

$error = '';
$success = '';

function log_admin_action($conn, $action, $details, $target_id = null) {
    global $current_admin_id;
    
    $stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, details, target_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $current_admin_id, $action, $details, $target_id);
    $stmt->execute();
    $stmt->close();
}

// ============================================
// DELETE CUSTOMER (MUST BE BEFORE ANY OUTPUT)
// ============================================
if (isset($_GET['delete_customer']) && is_numeric($_GET['delete_customer'])) {
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
        $stmt = $conn->prepare("UPDATE users SET u_status = 'Inactive', is_visible = 0 WHERE u_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Customer account deleted successfully!";
            
            log_admin_action($conn, 'DELETE_CUSTOMER', "Deleted customer: {$user_data['u_name']}", $id);
        } else {
            $error = "Failed to delete customer: " . $conn->error;
        }
        $stmt->close();
    }
}

// ============================================
// ADD CUSTOMER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = sanitize_input(trim($_POST['customer_name']));
    $username = sanitize_input(trim($_POST['customer_username']));
    $email = sanitize_input(trim($_POST['customer_email']));
    $password = sanitize_input(trim($_POST['customer_password']));
    $confirm_password = sanitize_input(trim($_POST['customer_confirm_password']));
    $role = 'Customer';
    $status = 'Active';
    
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
                
                $stmt = $conn->prepare("INSERT INTO users (u_name, u_username, u_email, u_pass, u_role, u_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $name, $username, $email, $hashed_password, $role, $status, $current_admin_id);
                
                if ($stmt->execute()) {
                    $new_user_id = $stmt->insert_id;
                    $success = "Customer account created successfully! Username: " . $username;
                    
                    log_admin_action($conn, 'ADD_CUSTOMER', "Added new customer: $name ($username - $email)", $new_user_id);
                    
                    $_POST = array();
                } else {
                    $error = "Failed to create customer: " . $conn->error;
                }
                
                $stmt->close();
            }
        }
    }
}

// ============================================
// RESET CUSTOMER PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_customer_password'])) {
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
            
            log_admin_action($conn, 'RESET_CUSTOMER_PASSWORD', "Reset password for customer: {$user_data['u_name']}", $id);
        } else {
            $error = "Failed to reset password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// FETCH CUSTOMERS
// ============================================
$customers_result = $conn->query("
    SELECT 
        u.u_id,
        u.u_name,
        u.u_username,
        u.u_email,
        u.u_role,
        u.u_status,
        u.created_at,
        u.last_login,
        u.created_by,
        creator.u_name as created_by_name,
        (SELECT COUNT(*) FROM bookings WHERE user_id = u.u_id AND status = 'ongoing') as active_bookings,
        (SELECT COUNT(*) FROM bookings WHERE user_id = u.u_id) as total_bookings,
        (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE user_id = u.u_id AND payment_status = 'paid') as total_spent
    FROM users u
    LEFT JOIN users creator ON u.created_by = creator.u_id
    WHERE u.u_role = 'Customer' AND u.is_visible = 1
    ORDER BY u.created_at DESC
");

$customers = [];
if ($customers_result) {
    while ($row = $customers_result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Get statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_customers,
        SUM(CASE WHEN u_status = 'Active' THEN 1 ELSE 0 END) as active_customers,
        SUM(CASE WHEN u_status = 'Inactive' THEN 1 ELSE 0 END) as inactive_customers
    FROM users 
    WHERE u_role = 'Customer' AND is_visible = 1
");
$stats = $stats_stmt ? $stats_stmt->fetch_assoc() : ['total_customers' => 0, 'active_customers' => 0, 'inactive_customers' => 0];

// Get customer activity log
$activity_log = $conn->query("
    SELECT 
        cal.id,
        cal.customer_id,
        cal.action_type,
        cal.details,
        cal.created_at,
        u.u_name as customer_name,
        u.u_email as customer_email,
        m.title as movie_title,
        b.booking_reference
    FROM customer_activity_log cal
    LEFT JOIN users u ON cal.customer_id = u.u_id
    LEFT JOIN movies m ON cal.movie_id = m.id
    LEFT JOIN bookings b ON cal.booking_id = b.id
    WHERE u.u_role = 'Customer' AND (u.is_visible = 1 OR u.is_visible IS NULL)
    ORDER BY cal.created_at DESC
    LIMIT 30
");

$activity_items = [];
if ($activity_log) {
    while ($row = $activity_log->fetch_assoc()) {
        $activity_items[] = $row;
    }
}

// DO NOT CLOSE CONNECTION HERE - will close after footer
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Customers</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">View, add, and manage customer accounts</p>
        <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 10px;">Logged in as: <strong style="color: #3498db;"><?php echo $current_admin_name; ?></strong> (<?php echo $current_admin_role; ?>)</p>
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

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 10px;"><i class="fas fa-users"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $stats['total_customers']; ?></div>
            <div style="color: rgba(255,255,255,0.8);">Total Customers</div>
        </div>
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2rem; color: #2ecc71; margin-bottom: 10px;"><i class="fas fa-check-circle"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $stats['active_customers']; ?></div>
            <div style="color: rgba(255,255,255,0.8);">Active Customers</div>
        </div>
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(149, 165, 166, 0.3);">
            <div style="font-size: 2rem; color: #95a5a6; margin-bottom: 10px;"><i class="fas fa-user-slash"></i></div>
            <div style="font-size: 2rem; font-weight: 800; color: white;"><?php echo $stats['inactive_customers']; ?></div>
            <div style="color: rgba(255,255,255,0.8);">Inactive Customers</div>
        </div>
    </div>

    <!-- Add Customer Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-user-plus"></i> Add New Customer Account
        </h2>
        
        <form method="POST" action="" id="customerForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-user"></i> Full Name *
                    </label>
                    <input type="text" name="customer_name" required 
                           value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''; ?>"
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-at"></i> Username *
                    </label>
                    <input type="text" name="customer_username" required 
                           value="<?php echo isset($_POST['customer_username']) ? htmlspecialchars($_POST['customer_username']) : ''; ?>"
                           pattern="[a-zA-Z0-9_]{3,50}"
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-envelope"></i> Email Address *
                    </label>
                    <input type="email" name="customer_email" required 
                           value="<?php echo isset($_POST['customer_email']) ? htmlspecialchars($_POST['customer_email']) : ''; ?>"
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-lock"></i> Password *
                    </label>
                    <input type="password" name="customer_password" required 
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <input type="password" name="customer_confirm_password" required 
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" name="add_customer" style="padding: 12px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-plus"></i> Add Customer Account
                </button>
            </div>
        </form>
    </div>

    <!-- Customer List Table -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
            <h2 style="color: white; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-users"></i> Customers (<?php echo count($customers); ?>)
            </h2>
            
            <!-- Live Search -->
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5);"></i>
                <input type="text" id="customerSearch" placeholder="Search by name, email, username..." 
                       style="padding: 8px 12px 8px 35px; background: rgba(255,255,255,0.08); border: 1px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; width: 250px;">
            </div>
        </div>
        
        <?php if (empty($customers)): ?>
            <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.6);">
                <i class="fas fa-users fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No customers found.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;" id="customerTable">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                            <th style="padding: 12px; text-align: left; color: white;">ID</th>
                            <th style="padding: 12px; text-align: left; color: white;">Customer Details</th>
                            <th style="padding: 12px; text-align: left; color: white;">Bookings</th>
                            <th style="padding: 12px; text-align: left; color: white;">Total Spent</th>
                            <th style="padding: 12px; text-align: left; color: white;">Created By</th>
                            <th style="padding: 12px; text-align: left; color: white;">Status</th>
                            <th style="padding: 12px; text-align: left; color: white;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr class="customer-row" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);"
                            data-name="<?php echo strtolower(htmlspecialchars($customer['u_name'])); ?>"
                            data-email="<?php echo strtolower(htmlspecialchars($customer['u_email'])); ?>"
                            data-username="<?php echo strtolower(htmlspecialchars($customer['u_username'])); ?>">
                            <td style="padding: 12px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $customer['u_id']; ?></td>
                            <td style="padding: 12px;">
                                <div style="color: white; font-weight: 700;"><?php echo htmlspecialchars($customer['u_name']); ?></div>
                                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($customer['u_email']); ?><br>
                                    <small>Username: <?php echo htmlspecialchars($customer['u_username']); ?></small>
                                </div>
                                <div style="color: rgba(255, 255, 255, 0.5); font-size: 0.75rem; margin-top: 3px;">
                                    Joined: <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                                    <?php if ($customer['last_login']): ?>
                                    • Last login: <?php echo date('M d, h:i A', strtotime($customer['last_login'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            </tr>
                            <td style="padding: 12px;">
                                <div>
                                    <span style="background: rgba(52,152,219,0.2); color: #3498db; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                        <i class="fas fa-ticket-alt"></i> Total: <?php echo $customer['total_bookings'] ?? 0; ?>
                                    </span>
                                    <?php if (($customer['active_bookings'] ?? 0) > 0): ?>
                                    <span style="background: rgba(46,204,113,0.2); color: #2ecc71; padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; margin-left: 5px;">
                                        <i class="fas fa-clock"></i> Active: <?php echo $customer['active_bookings']; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            </tr>
                            <td style="padding: 12px;">
                                <span style="color: #2ecc71; font-weight: 700;">
                                    ₱<?php echo number_format($customer['total_spent'] ?? 0, 2); ?>
                                </span>
                            </div>
                            </tr>
                            <td style="padding: 12px;">
                                <span style="background: <?php echo $customer['created_by_name'] ? 'rgba(52,152,219,0.2)' : 'rgba(149,165,166,0.2)'; ?>; color: <?php echo $customer['created_by_name'] ? '#3498db' : '#95a5a6'; ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fas <?php echo $customer['created_by_name'] ? 'fa-user-cog' : 'fa-user'; ?>"></i>
                                    <?php echo $customer['created_by_name'] ?? 'Self-Registered'; ?>
                                </span>
                            </div>
                            <tr>
                            <td style="padding: 12px;">
                                <span style="background: <?php echo $customer['u_status'] == 'Active' ? 'rgba(46,204,113,0.2)' : 'rgba(108,117,125,0.2)'; ?>; color: <?php echo $customer['u_status'] == 'Active' ? '#2ecc71' : '#6c757d'; ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fas <?php echo $customer['u_status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $customer['u_status']; ?>
                                </span>
                            </div>
                            </tr>
                            <td style="padding: 12px;">
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button type="button" 
                                            onclick="openResetCustomerPasswordModal(<?php echo $customer['u_id']; ?>, '<?php echo addslashes($customer['u_name']); ?>')" 
                                            style="padding: 6px 12px; background: rgba(23,162,184,0.2); color: #17a2b8; border: 1px solid rgba(23,162,184,0.3); border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                                        <i class="fas fa-key"></i> Reset PW
                                    </button>
                                    
                                    <!-- FIXED: Correct URL for delete - points back to manage-users.php with type=customer -->
                                    <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users&type=customer&delete_customer=<?php echo $customer['u_id']; ?>" 
                                       onclick="return confirm('Delete customer \'<?php echo addslashes($customer['u_name']); ?>\'?\nThis will deactivate their account and they will no longer be able to login.')"
                                       style="padding: 6px 12px; background: rgba(231,76,60,0.2); color: #e74c3c; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 10px; text-align: center; color: rgba(255,255,255,0.5); font-size: 0.8rem;">
                <span id="customerCount"><?php echo count($customers); ?></span> customer(s) displayed
            </div>
        <?php endif; ?>
    </div>

    <!-- Customer Activity Log -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-history"></i> Recent Customer Activity
        </h2>
        
        <?php if (empty($activity_items)): ?>
            <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.6);">
                <i class="fas fa-history fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No customer activity found.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); position: sticky; top: 0;">
                            <th style="padding: 12px; text-align: left; color: white;">Time</th>
                            <th style="padding: 12px; text-align: left; color: white;">Customer</th>
                            <th style="padding: 12px; text-align: left; color: white;">Activity</th>
                            <th style="padding: 12px; text-align: left; color: white;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_items as $activity): 
                            $action_icon = 'fa-user';
                            $action_color = '#3498db';
                            
                            if ($activity['action_type'] === 'BOOKING') {
                                $action_icon = 'fa-ticket-alt';
                                $action_color = '#2ecc71';
                            } elseif ($activity['action_type'] === 'MOVIE_VIEW') {
                                $action_icon = 'fa-eye';
                                $action_color = '#9b59b6';
                            } elseif ($activity['action_type'] === 'LOGIN') {
                                $action_icon = 'fa-sign-in-alt';
                                $action_color = '#f39c12';
                            }
                        ?>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <td style="padding: 12px; color: rgba(255, 255, 255, 0.7); font-size: 0.85rem;">
                                <?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?>
                            </td>
                            <td style="padding: 12px;">
                                <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($activity['customer_name']); ?></div>
                                <div style="color: rgba(255,255,255,0.5); font-size: 0.75rem;"><?php echo htmlspecialchars($activity['customer_email']); ?></div>
                            </td>
                            <td style="padding: 12px;">
                                <span style="color: <?php echo $action_color; ?>;">
                                    <i class="fas <?php echo $action_icon; ?>"></i> 
                                    <?php 
                                    if ($activity['action_type'] === 'BOOKING') echo 'Movie Booking';
                                    elseif ($activity['action_type'] === 'MOVIE_VIEW') echo 'Movie View';
                                    elseif ($activity['action_type'] === 'LOGIN') echo 'Login';
                                    else echo $activity['action_type'];
                                    ?>
                                </span>
                                <?php if ($activity['booking_reference']): ?>
                                <div style="font-size: 0.7rem; color: rgba(255,255,255,0.4);">
                                    Ref: <?php echo $activity['booking_reference']; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; color: rgba(255, 255, 255, 0.8); font-size: 0.85rem;">
                                <?php echo htmlspecialchars($activity['details']); ?>
                                <?php if ($activity['movie_title']): ?>
                                <span style="color: #3498db;">(<?php echo htmlspecialchars($activity['movie_title']); ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reset Customer Password Modal -->
<div id="resetCustomerPasswordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; border: 1px solid rgba(52, 152, 219, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #3498db;">Reset Customer Password</h3>
            <button onclick="closeResetCustomerPasswordModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="" id="resetCustomerPasswordForm">
            <input type="hidden" name="id" id="resetCustomerUserId">
            <input type="hidden" name="reset_customer_password" value="1">
            
            <div style="margin-bottom: 20px;">
                <div id="resetCustomerUserNameLabel" style="color: white; margin-bottom: 15px;"></div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">New Password</label>
                <input type="password" name="new_password" id="new_customer_password" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_customer_password" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" style="padding: 12px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-key"></i> Reset Password
                </button>
                <button type="button" onclick="closeResetCustomerPasswordModal()" style="padding: 12px 30px; background: rgba(255,255,255,0.1); color: white; border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; cursor: pointer; margin-left: 10px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
input:focus, select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.2);
}
button:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}
tr:hover {
    background: rgba(255,255,255,0.03);
}
#customerSearch {
    transition: all 0.3s ease;
}
#customerSearch:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
}
@media (max-width: 768px) {
    .admin-content { padding: 15px; }
    .admin-content > div { padding: 20px; }
    table { font-size: 0.85rem; }
    #customerSearch { width: 100%; margin-top: 10px; }
}
</style>

<script>
// Live search functionality
const customerSearch = document.getElementById('customerSearch');
const customerRows = document.querySelectorAll('.customer-row');
const customerCount = document.getElementById('customerCount');

if (customerSearch) {
    customerSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        let visibleCount = 0;
        
        customerRows.forEach(row => {
            const name = row.dataset.name || '';
            const email = row.dataset.email || '';
            const username = row.dataset.username || '';
            
            if (name.includes(searchTerm) || email.includes(searchTerm) || username.includes(searchTerm) || searchTerm === '') {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (customerCount) {
            customerCount.textContent = visibleCount;
        }
    });
}

function openResetCustomerPasswordModal(id, name) {
    document.getElementById('resetCustomerUserId').value = id;
    document.getElementById('resetCustomerUserNameLabel').innerHTML = `<strong>Customer:</strong> ${name}<br><small style="color: rgba(255,255,255,0.6);">Reset password for this customer account</small>`;
    document.getElementById('resetCustomerPasswordModal').style.display = 'flex';
}

function closeResetCustomerPasswordModal() {
    document.getElementById('resetCustomerPasswordModal').style.display = 'none';
}

window.onclick = function(event) {
    const resetModal = document.getElementById('resetCustomerPasswordModal');
    if (event.target == resetModal) closeResetCustomerPasswordModal();
}

document.getElementById('customerForm')?.addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="customer_password"]').value;
    const confirm = document.querySelector('input[name="customer_confirm_password"]').value;
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
    const username = document.querySelector('input[name="customer_username"]').value;
    if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
        e.preventDefault();
        alert('Username must be 3-50 characters and can only contain letters, numbers, and underscores!');
        return false;
    }
    return true;
});

document.getElementById('resetCustomerPasswordForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('new_customer_password').value;
    const confirm = document.getElementById('confirm_customer_password').value;
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
    return true;
});
</script>

<?php
// Close the database connection at the very end - ONLY ONCE
if (isset($conn) && $conn) {
    $conn->close();
}
?>
</body>
</html>