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
// DELETE STAFF (MUST BE BEFORE ANY OUTPUT)
// ============================================
if (isset($_GET['delete_staff']) && is_numeric($_GET['delete_staff'])) {
    $id = intval($_GET['delete_staff']);
    
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user_data) {
        $error = "User not found!";
    } elseif ($user_data['u_role'] !== 'Staff') {
        $error = "Only staff accounts can be deleted!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET u_status = 'Inactive', is_visible = 0 WHERE u_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Staff account deleted successfully!";
            
            log_admin_action($conn, 'DELETE_STAFF', "Deleted staff: {$user_data['u_name']}", $id);
        } else {
            $error = "Failed to delete staff: " . $conn->error;
        }
        $stmt->close();
    }
}

// ============================================
// ADD STAFF
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name = sanitize_input(trim($_POST['staff_name']));
    $username = sanitize_input(trim($_POST['staff_username']));
    $email = sanitize_input(trim($_POST['staff_email']));
    $password = sanitize_input(trim($_POST['staff_password']));
    $confirm_password = sanitize_input(trim($_POST['staff_confirm_password']));
    $role = 'Staff';
    $status = 'Active';
    
    if (empty($name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All staff fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $error = "Username must be 3-50 characters and can only contain letters, numbers, and underscores!";
    } else {
        $check_username_stmt = $conn->prepare("SELECT u_id FROM users WHERE u_username = ?");
        $check_username_stmt->bind_param("s", $username);
        $check_username_stmt->execute();
        $username_result = $check_username_stmt->get_result();
        
        if ($username_result->num_rows > 0) {
            $error = "Username already taken! Please choose a different username.";
        } else {
            $check_username_stmt->close();
            
            $check_stmt = $conn->prepare("SELECT u_id FROM users WHERE u_email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Email already registered!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (u_name, u_username, u_email, u_pass, u_role, u_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $name, $username, $email, $hashed_password, $role, $status, $current_admin_id);
                
                if ($stmt->execute()) {
                    $new_user_id = $stmt->insert_id;
                    $success = "New Staff added successfully! Username: " . $username;
                    
                    log_admin_action($conn, 'ADD_STAFF', "Added new staff: $name ($username - $email)", $new_user_id);
                    
                    $_POST = array();
                } else {
                    $error = "Failed to add staff: " . $conn->error;
                }
                
                $stmt->close();
            }
            
            $check_stmt->close();
        }
    }
}

// ============================================
// UPDATE STAFF
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $id = intval($_POST['id']);
    $status = sanitize_input(trim($_POST['status']));
    
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if ($user_data['u_role'] !== 'Staff') {
        $error = "You can only edit staff members!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET u_status = ? WHERE u_id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $success = "Staff member updated successfully!";
            
            log_admin_action($conn, 'UPDATE_STAFF', "Updated staff: {$user_data['u_name']} - Status: $status", $id);
        } else {
            $error = "Failed to update staff: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// RESET STAFF PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_staff_password'])) {
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
    } elseif ($user_data['u_role'] !== 'Staff') {
        $error = "Invalid user type!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET u_pass = ? WHERE u_id = ?");
        $stmt->bind_param("si", $hashed_password, $id);
        
        if ($stmt->execute()) {
            $success = "Staff password reset successfully!";
            
            log_admin_action($conn, 'RESET_STAFF_PASSWORD', "Reset password for staff: {$user_data['u_name']}", $id);
        } else {
            $error = "Failed to reset password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// FETCH STAFF MEMBERS
// ============================================
$staffs_result = $conn->query("
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
        creator.u_name as created_by_name
    FROM users u
    LEFT JOIN users creator ON u.created_by = creator.u_id
    WHERE u.u_role = 'Staff' AND u.is_visible = 1
    ORDER BY u.created_at DESC
");

$staffs = [];
if ($staffs_result) {
    while ($row = $staffs_result->fetch_assoc()) {
        $staffs[] = $row;
    }
}

// DO NOT CLOSE CONNECTION HERE - will close after footer
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(39, 174, 96, 0.2)); border-radius: 20px; border: 2px solid rgba(46, 204, 113, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Staff</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Add, edit, or remove staff accounts</p>
        <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: 10px;">Logged in as: <strong style="color: #2ecc71;"><?php echo $current_admin_name; ?></strong> (<?php echo $current_admin_role; ?>)</p>
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

    <!-- Add Staff Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(46, 204, 113, 0.2);">
        <h2 style="color: #2ecc71; font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-user-plus"></i> Add New Staff Account
        </h2>
        
        <form method="POST" action="" id="staffForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-user"></i> Full Name *
                    </label>
                    <input type="text" name="staff_name" required 
                           value="<?php echo isset($_POST['staff_name']) ? htmlspecialchars($_POST['staff_name']) : ''; ?>"
                           style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(46, 204, 113, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-at"></i> Username *
                    </label>
                    <input type="text" name="staff_username" required 
                           value="<?php echo isset($_POST['staff_username']) ? htmlspecialchars($_POST['staff_username']) : ''; ?>"
                           pattern="[a-zA-Z0-9_]{3,50}"
                           style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(46, 204, 113, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-envelope"></i> Email Address *
                    </label>
                    <input type="email" name="staff_email" required 
                           value="<?php echo isset($_POST['staff_email']) ? htmlspecialchars($_POST['staff_email']) : ''; ?>"
                           style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(46, 204, 113, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-lock"></i> Password *
                    </label>
                    <input type="password" name="staff_password" required 
                           style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(46, 204, 113, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <input type="password" name="staff_confirm_password" required 
                           style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(46, 204, 113, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" name="add_staff" style="padding: 12px 30px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-plus"></i> Add Staff Account
                </button>
            </div>
        </form>
    </div>

    <!-- Staff List Table -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(46, 204, 113, 0.2);">
        <h2 style="color: #2ecc71; font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-user-tie"></i> Staff Members (<?php echo count($staffs); ?>)
        </h2>
        
        <?php if (empty($staffs)): ?>
        <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.6);">
            <i class="fas fa-user-tie fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No staff members found.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                        <th style="padding: 12px; text-align: left; color: white;">ID</th>
                        <th style="padding: 12px; text-align: left; color: white;">Staff Details</th>
                        <th style="padding: 12px; text-align: left; color: white;">Created By</th>
                        <th style="padding: 12px; text-align: left; color: white;">Status</th>
                        <th style="padding: 12px; text-align: left; color: white;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffs as $staff): ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <td style="padding: 12px; color: rgba(255, 255, 255, 0.9); font-weight: 700;"><?php echo $staff['u_id']; ?></td>
                        <td style="padding: 12px;">
                            <div style="color: white; font-weight: 700;"><?php echo htmlspecialchars($staff['u_name']); ?></div>
                            <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem;">
                                <?php echo htmlspecialchars($staff['u_email']); ?><br>
                                <small>Username: <?php echo htmlspecialchars($staff['u_username']); ?></small>
                            </div>
                            <div style="color: rgba(255, 255, 255, 0.5); font-size: 0.75rem; margin-top: 3px;">
                                Last login: <?php echo $staff['last_login'] ? date('M d, Y h:i A', strtotime($staff['last_login'])) : 'Never'; ?>
                            </div>
                        </div>
                        </td>
                        <td style="padding: 12px;">
                            <span style="background: <?php echo $staff['created_by_name'] ? 'rgba(46,204,113,0.2)' : 'rgba(149,165,166,0.2)'; ?>; color: <?php echo $staff['created_by_name'] ? '#2ecc71' : '#95a5a6'; ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                <i class="fas <?php echo $staff['created_by_name'] ? 'fa-user-cog' : 'fa-user'; ?>"></i>
                                <?php echo $staff['created_by_name'] ?? 'System'; ?>
                            </span>
                        </div>
                        </td>
                        <td style="padding: 12px;">
                            <span style="background: <?php echo $staff['u_status'] == 'Active' ? 'rgba(46,204,113,0.2)' : 'rgba(108,117,125,0.2)'; ?>; color: <?php echo $staff['u_status'] == 'Active' ? '#2ecc71' : '#6c757d'; ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600;">
                                <i class="fas <?php echo $staff['u_status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $staff['u_status']; ?>
                            </span>
                        </div>
                        </td>
                        <td style="padding: 12px;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" 
                                        onclick="openEditStaffModal(<?php echo $staff['u_id']; ?>, '<?php echo addslashes($staff['u_name']); ?>', '<?php echo $staff['u_status']; ?>')" 
                                        style="padding: 6px 12px; background: rgba(46,204,113,0.2); color: #2ecc71; border: 1px solid rgba(46,204,113,0.3); border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <button type="button" 
                                        onclick="openResetStaffPasswordModal(<?php echo $staff['u_id']; ?>, '<?php echo addslashes($staff['u_name']); ?>')" 
                                        style="padding: 6px 12px; background: rgba(23,162,184,0.2); color: #17a2b8; border: 1px solid rgba(23,162,184,0.3); border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                                    <i class="fas fa-key"></i> Reset PW
                                </button>
                                
                                <!-- FIXED: Correct URL for delete - points back to manage-users.php with type=staff -->
                                <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users&type=staff&delete_staff=<?php echo $staff['u_id']; ?>" 
                                   onclick="return confirm('Delete staff \'<?php echo addslashes($staff['u_name']); ?>\'?')"
                                   style="padding: 6px 12px; background: rgba(231,76,60,0.2); color: #e74c3c; text-decoration: none; border-radius: 5px; font-size: 0.8rem;">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
             </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Staff Modal -->
<div id="editStaffModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; border: 1px solid rgba(46,204,113,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #2ecc71;">Edit Staff Member</h3>
            <button onclick="closeEditStaffModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="" id="editStaffForm">
            <input type="hidden" name="id" id="editStaffUserId">
            <input type="hidden" name="update_staff" value="1">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Staff Name</label>
                <input type="text" id="editStaffUserName" style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white;" readonly>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Status</label>
                <select id="editStaffStatus" name="status" style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 8px; color: white;">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" style="padding: 12px 30px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" onclick="closeEditStaffModal()" style="padding: 12px 30px; background: rgba(255,255,255,0.1); color: white; border: 2px solid rgba(46,204,113,0.3); border-radius: 8px; cursor: pointer; margin-left: 10px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Staff Password Modal -->
<div id="resetStaffPasswordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; border: 1px solid rgba(46,204,113,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #2ecc71;">Reset Staff Password</h3>
            <button onclick="closeResetStaffPasswordModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="" id="resetStaffPasswordForm">
            <input type="hidden" name="id" id="resetStaffUserId">
            <input type="hidden" name="reset_staff_password" value="1">
            
            <div style="margin-bottom: 20px;">
                <div id="resetStaffUserNameLabel" style="color: white; margin-bottom: 15px;"></div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">New Password</label>
                <input type="password" name="new_password" id="new_staff_password" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 8px; color: white;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_staff_password" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 8px; color: white;">
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" style="padding: 12px 30px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-key"></i> Reset Password
                </button>
                <button type="button" onclick="closeResetStaffPasswordModal()" style="padding: 12px 30px; background: rgba(255,255,255,0.1); color: white; border: 2px solid rgba(46,204,113,0.3); border-radius: 8px; cursor: pointer; margin-left: 10px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
input:focus, select:focus {
    outline: none;
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46,204,113,0.2);
}
button:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}
tr:hover {
    background: rgba(255,255,255,0.03);
}
@media (max-width: 768px) {
    .admin-content { padding: 15px; }
    .admin-content > div { padding: 20px; }
    table { font-size: 0.85rem; }
}
</style>

<script>
function openEditStaffModal(id, name, status) {
    document.getElementById('editStaffUserId').value = id;
    document.getElementById('editStaffUserName').value = name;
    document.getElementById('editStaffStatus').value = status;
    document.getElementById('editStaffModal').style.display = 'flex';
}

function closeEditStaffModal() {
    document.getElementById('editStaffModal').style.display = 'none';
}

function openResetStaffPasswordModal(id, name) {
    document.getElementById('resetStaffUserId').value = id;
    document.getElementById('resetStaffUserNameLabel').innerHTML = `<strong>Staff:</strong> ${name}`;
    document.getElementById('resetStaffPasswordModal').style.display = 'flex';
}

function closeResetStaffPasswordModal() {
    document.getElementById('resetStaffPasswordModal').style.display = 'none';
}

window.onclick = function(event) {
    const editModal = document.getElementById('editStaffModal');
    const resetModal = document.getElementById('resetStaffPasswordModal');
    if (event.target == editModal) closeEditStaffModal();
    if (event.target == resetModal) closeResetStaffPasswordModal();
}

document.getElementById('staffForm')?.addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="staff_password"]').value;
    const confirm = document.querySelector('input[name="staff_confirm_password"]').value;
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
    const username = document.querySelector('input[name="staff_username"]').value;
    if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
        e.preventDefault();
        alert('Username must be 3-50 characters and can only contain letters, numbers, and underscores!');
        return false;
    }
    return true;
});

document.getElementById('resetStaffPasswordForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('new_staff_password').value;
    const confirm = document.getElementById('confirm_staff_password').value;
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