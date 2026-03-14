<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';
$edit_method = null;

if (isset($_GET['edit_method']) && is_numeric($_GET['edit_method'])) {
    $edit_id = intval($_GET['edit_method']);
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_method = $result->fetch_assoc();
    $stmt->close();
}

if (isset($_GET['delete_method']) && is_numeric($_GET['delete_method'])) {
    $delete_id = intval($_GET['delete_method']);
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM manual_payments WHERE payment_method_id = ?");
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count = $check_result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($count > 0) {
        $error = "Cannot delete this payment method because it has existing payment records.";
    } else {
        $method_info = $conn->prepare("SELECT qr_code_path FROM payment_methods WHERE id = ?");
        $method_info->bind_param("i", $delete_id);
        $method_info->execute();
        $method_result = $method_info->get_result();
        $method_data = $method_result->fetch_assoc();
        $method_info->close();
        
        if ($method_data && !empty($method_data['qr_code_path']) && file_exists($root_dir . '/' . $method_data['qr_code_path'])) {
            unlink($root_dir . '/' . $method_data['qr_code_path']);
        }
        
        $delete_stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $success = "Payment method deleted successfully!";
        } else {
            $error = "Failed to delete payment method: " . $conn->error;
        }
        $delete_stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_payment_method']) || isset($_POST['update_payment_method'])) {
        $method_name = sanitize_input($_POST['method_name']);
        $account_name = sanitize_input($_POST['account_name']);
        $account_number = sanitize_input($_POST['account_number']);
        $instructions = sanitize_input($_POST['instructions'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($method_name) || empty($account_name) || empty($account_number)) {
            $error = "Method name, account name, and account number are required!";
        } else {
            $target_dir = $root_dir . "/uploads/qrcodes/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $qr_code_path = null;
            if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                $file_type = $_FILES['qr_code']['type'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only JPG, PNG, and GIF files are allowed for QR code.";
                } elseif ($_FILES['qr_code']['size'] > 2000000) {
                    $error = "QR code file size must be less than 2MB.";
                } else {
                    $extension = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
                    $filename = 'qr_' . strtolower(str_replace(' ', '_', $method_name)) . '_' . time() . '.' . $extension;
                    $target_file = $target_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $target_file)) {
                        $qr_code_path = 'uploads/qrcodes/' . $filename;
                    } else {
                        $error = "Failed to upload QR code. Please try again.";
                    }
                }
            }
            
            if (empty($error)) {
                if (isset($_POST['update_payment_method']) && isset($_POST['method_id'])) {
                    $method_id = intval($_POST['method_id']);
                    
                    if ($qr_code_path) {
                        $old_qr = $conn->prepare("SELECT qr_code_path FROM payment_methods WHERE id = ?");
                        $old_qr->bind_param("i", $method_id);
                        $old_qr->execute();
                        $old_result = $old_qr->get_result();
                        $old_data = $old_result->fetch_assoc();
                        $old_qr->close();
                        
                        if ($old_data && !empty($old_data['qr_code_path']) && file_exists($root_dir . '/' . $old_data['qr_code_path'])) {
                            unlink($root_dir . '/' . $old_data['qr_code_path']);
                        }
                        
                        $stmt = $conn->prepare("UPDATE payment_methods SET method_name = ?, account_name = ?, account_number = ?, qr_code_path = ?, instructions = ?, display_order = ?, is_active = ? WHERE id = ?");
                        $stmt->bind_param("sssssiii", $method_name, $account_name, $account_number, $qr_code_path, $instructions, $display_order, $is_active, $method_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE payment_methods SET method_name = ?, account_name = ?, account_number = ?, instructions = ?, display_order = ?, is_active = ? WHERE id = ?");
                        $stmt->bind_param("sssssii", $method_name, $account_name, $account_number, $instructions, $display_order, $is_active, $method_id);
                    }
                    
                    if ($stmt->execute()) {
                        $success = "Payment method updated successfully!";
                        $edit_method = null;
                    } else {
                        $error = "Failed to update payment method: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("INSERT INTO payment_methods (method_name, account_name, account_number, qr_code_path, instructions, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssii", $method_name, $account_name, $account_number, $qr_code_path, $instructions, $display_order, $is_active);
                    
                    if ($stmt->execute()) {
                        $success = "Payment method added successfully!";
                    } else {
                        $error = "Failed to add payment method: " . $conn->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$methods_result = $conn->query("SELECT * FROM payment_methods ORDER BY display_order, method_name");
$methods_count = $conn->query("SELECT COUNT(*) as count FROM payment_methods WHERE is_active = 1")->fetch_assoc()['count'];

$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Payment Methods</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Manage payment methods displayed to customers</p>
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

    <div style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid rgba(52, 152, 219, 0.3); padding-bottom: 10px;">
        <a href="?page=admin/manage-payments" 
           style="padding: 12px 25px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;"
           onmouseover="this.style.background='rgba(52,152,219,0.2)'"
           onmouseout="this.style.background='rgba(255,255,255,0.1)'">
            <i class="fas fa-clock"></i> Payment Requests
        </a>
        <a href="?page=admin/payment-methods" 
           style="padding: 12px 25px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-credit-card"></i> Payment Methods
            <span style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><?php echo $methods_count; ?></span>
        </a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div>
            <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
                    <i class="<?php echo $edit_method ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
                    <?php echo $edit_method ? 'Edit Payment Method' : 'Add New Payment Method'; ?>
                </h2>
                
                <form method="POST" action="" enctype="multipart/form-data" id="methodForm">
                    <?php if ($edit_method): ?>
                    <input type="hidden" name="method_id" value="<?php echo $edit_method['id']; ?>">
                    <?php endif; ?>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Method Name *
                        </label>
                        <input type="text" name="method_name" required 
                               value="<?php echo $edit_method ? htmlspecialchars($edit_method['method_name']) : ''; ?>"
                               style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                               placeholder="e.g., GCash, PayMaya, Bank Transfer">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Account Name *
                        </label>
                        <input type="text" name="account_name" required 
                               value="<?php echo $edit_method ? htmlspecialchars($edit_method['account_name']) : ''; ?>"
                               style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                               placeholder="Full account name">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Account Number *
                        </label>
                        <input type="text" name="account_number" required 
                               value="<?php echo $edit_method ? htmlspecialchars($edit_method['account_number']) : ''; ?>"
                               style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                               placeholder="Account number">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            QR Code Image
                        </label>
                        <?php if ($edit_method && !empty($edit_method['qr_code_path'])): ?>
                        <div style="margin-bottom: 10px; text-align: center;">
                            <img src="<?php echo SITE_URL . $edit_method['qr_code_path']; ?>" 
                                 alt="Current QR Code"
                                 style="max-width: 150px; border-radius: 8px; border: 2px solid rgba(52,152,219,0.3);">
                            <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 5px;">Current QR Code</p>
                        </div>
                        <?php endif; ?>
                        <div style="border: 2px dashed rgba(52,152,219,0.3); border-radius: 8px; padding: 20px; text-align: center;">
                            <i class="fas fa-qrcode" style="font-size: 2rem; color: var(--pale-red); margin-bottom: 10px;"></i>
                            <p style="color: white; margin-bottom: 10px;">Upload QR code image</p>
                            <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-bottom: 15px;">JPG, PNG, GIF (Max 2MB)</p>
                            <input type="file" name="qr_code" accept="image/*" style="display: none;" id="qrFileInput">
                            <button type="button" onclick="document.getElementById('qrFileInput').click()" class="btn btn-secondary" style="padding: 8px 20px;">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                            <div id="qrFileName" style="margin-top: 10px; color: #2ecc71; font-size: 0.9rem;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Instructions
                        </label>
                        <textarea name="instructions" rows="3" style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem; resize: vertical;" 
                                  placeholder="Payment instructions for customers"><?php echo $edit_method ? htmlspecialchars($edit_method['instructions']) : ''; ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                                Display Order
                            </label>
                            <input type="number" name="display_order" min="0" 
                                   value="<?php echo $edit_method ? $edit_method['display_order'] : '0'; ?>"
                                   style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; font-size: 1rem;">
                        </div>
                        <div>
                            <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                                Status
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; color: white; cursor: pointer;">
                                <input type="checkbox" name="is_active" <?php echo !$edit_method || $edit_method['is_active'] ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #2ecc71;">
                                <span>Active</span>
                            </label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="<?php echo $edit_method ? 'update_payment_method' : 'add_payment_method'; ?>" 
                                class="btn btn-primary" style="flex: 1; padding: 15px;">
                            <i class="fas fa-save"></i> <?php echo $edit_method ? 'Update Method' : 'Add Method'; ?>
                        </button>
                        <?php if ($edit_method): ?>
                        <a href="?page=admin/payment-methods" class="btn btn-secondary" style="padding: 15px 25px;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-list"></i> Payment Methods
                </h2>
                
                <?php if ($methods_result && $methods_result->num_rows > 0): ?>
                    <?php while ($method = $methods_result->fetch_assoc()): ?>
                    <div style="background: rgba(255, 255, 255, 0.03); border-radius: 10px; padding: 20px; margin-bottom: 15px; border: 1px solid <?php echo $method['is_active'] ? 'rgba(46,204,113,0.3)' : 'rgba(231,76,60,0.3)'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="font-size: 2rem; color: <?php 
                                    echo $method['method_name'] == 'GCash' ? '#00bfff' : 
                                        ($method['method_name'] == 'PayMaya' ? '#ff6b6b' : '#2ecc71'); 
                                ?>;">
                                    <i class="fas <?php 
                                        echo $method['method_name'] == 'GCash' ? 'fa-mobile-alt' : 
                                            ($method['method_name'] == 'PayMaya' ? 'fa-mobile-alt' : 'fa-university'); 
                                    ?>"></i>
                                </div>
                                <div>
                                    <h3 style="color: white; font-size: 1.2rem; font-weight: 700;"><?php echo $method['method_name']; ?></h3>
                                    <span style="background: <?php echo $method['is_active'] ? 'rgba(46,204,113,0.2)' : 'rgba(231,76,60,0.2)'; ?>; color: <?php echo $method['is_active'] ? '#2ecc71' : '#e74c3c'; ?>; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                        <?php echo $method['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <a href="?page=admin/payment-methods&edit_method=<?php echo $method['id']; ?>" 
                                   class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?page=admin/payment-methods&delete_method=<?php echo $method['id']; ?>" 
                                   class="btn btn-danger" style="padding: 6px 12px; font-size: 0.85rem;"
                                   onclick="return confirm('Are you sure you want to delete this payment method?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                            <div>
                                <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-bottom: 3px;">Account Name</p>
                                <p style="color: white; font-weight: 600;"><?php echo $method['account_name']; ?></p>
                            </div>
                            <div>
                                <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-bottom: 3px;">Account Number</p>
                                <p style="color: white; font-weight: 600;"><?php echo $method['account_number']; ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($method['qr_code_path'])): ?>
                        <div style="margin-top: 15px; text-align: center;">
                            <img src="<?php echo SITE_URL . $method['qr_code_path']; ?>" 
                                 alt="<?php echo $method['method_name']; ?> QR Code"
                                 style="max-width: 100px; border-radius: 8px; border: 2px solid rgba(52,152,219,0.3);">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($method['instructions'])): ?>
                        <div style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 5px;">
                            <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;"><?php echo nl2br($method['instructions']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 10px; color: rgba(255,255,255,0.5); font-size: 0.8rem;">
                            Order: <?php echo $method['display_order']; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
                        <i class="fas fa-credit-card fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                        <p>No payment methods added yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .btn {
        padding: 12px 25px;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        font-size: 1rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(52,152,219,0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #2980b9 0%, #1f639b 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(52,152,219,0.4);
    }

    .btn-secondary {
        background: rgba(255,255,255,0.1);
        color: white;
        border: 2px solid rgba(52,152,219,0.3);
    }

    .btn-secondary:hover {
        background: rgba(52,152,219,0.2);
        border-color: #3498db;
        transform: translateY(-3px);
    }

    .btn-danger {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(231,76,60,0.3);
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(231,76,60,0.4);
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        background: rgba(255,255,255,0.12);
        border-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52,152,219,0.2);
    }

    @media (max-width: 992px) {
        .admin-content > div > div {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .admin-content {
            padding: 15px;
        }
        
        div > div {
            padding: 20px;
        }
    }
</style>

<script>
document.getElementById('qrFileInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileNameDiv = document.getElementById('qrFileName');
    if (fileName) {
        fileNameDiv.innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + fileName;
    } else {
        fileNameDiv.innerHTML = '';
    }
});

document.getElementById('methodForm')?.addEventListener('submit', function(e) {
    const methodName = document.querySelector('input[name="method_name"]').value.trim();
    const accountName = document.querySelector('input[name="account_name"]').value.trim();
    const accountNumber = document.querySelector('input[name="account_number"]').value.trim();
    
    if (!methodName || !accountName || !accountNumber) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    return true;
});
</script>

</div>
</body>
</html>