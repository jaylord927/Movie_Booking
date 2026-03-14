<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$conn = get_db();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$booking = null;

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$current_hour = (int)date('H');
$is_manual_payment_available = ($current_hour >= 8 && $current_hour < 17);

if (!$is_manual_payment_available) {
    header("Location: " . SITE_URL . "index.php?page=customer/payment&booking_id=" . $booking_id . "&error=manual_unavailable");
    exit();
}

$stmt = $conn->prepare("
    SELECT b.*, m.poster_url, m.genre, m.duration, m.rating
    FROM tbl_booking b
    LEFT JOIN movies m ON b.movie_name = m.title
    WHERE b.b_id = ? AND b.u_id = ? AND b.payment_status = 'Pending'
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

$payment_methods = $conn->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY display_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_manual_payment'])) {
    $payment_method_id = intval($_POST['payment_method_id']);
    $reference_number = sanitize_input($_POST['reference_number'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (empty($reference_number)) {
        $error = "Please enter your payment reference number.";
    } elseif ($amount <= 0) {
        $error = "Invalid payment amount.";
    } else {
        $target_dir = $root_dir . "/uploads/payments/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $screenshot_path = '';
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $file_type = $_FILES['payment_screenshot']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = "Only JPG, PNG, and GIF files are allowed.";
            } elseif ($_FILES['payment_screenshot']['size'] > 5000000) {
                $error = "File size must be less than 5MB.";
            } else {
                $extension = pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION);
                $filename = 'payment_' . $booking['booking_reference'] . '_' . time() . '.' . $extension;
                $target_file = $target_dir . $filename;
                
                if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target_file)) {
                    $screenshot_path = 'uploads/payments/' . $filename;
                } else {
                    $error = "Failed to upload screenshot. Please try again.";
                }
            }
        } else {
            $error = "Please upload a screenshot of your payment.";
        }
        
        if (empty($error)) {
            $insert_stmt = $conn->prepare("
                INSERT INTO manual_payments (booking_id, user_id, payment_method_id, reference_number, amount, screenshot_path)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->bind_param("iiisds", $booking_id, $user_id, $payment_method_id, $reference_number, $amount, $screenshot_path);
            
            if ($insert_stmt->execute()) {
                $update_stmt = $conn->prepare("
                    UPDATE tbl_booking 
                    SET payment_status = 'Pending Verification'
                    WHERE b_id = ? AND u_id = ?
                ");
                $update_stmt->bind_param("ii", $booking_id, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $success = "Your payment has been submitted successfully! Our team will verify your payment within minutes to hours.";
            } else {
                $error = "Failed to submit payment. Please try again.";
            }
            $insert_stmt->close();
        }
    }
}

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="payment-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="font-size: 2.5rem; color: var(--primary-red);">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div>
                <h1 style="color: white; font-size: 2rem; margin-bottom: 5px; font-weight: 800;">
                    Manual Payment
                </h1>
                <p style="color: var(--pale-red); font-size: 1rem;">
                    Booking Reference: <strong><?php echo $booking['booking_reference']; ?></strong>
                </p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(226, 48, 32, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(226, 48, 32, 0.3); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                <i class="fas fa-check-circle fa-2x"></i>
                <div style="font-size: 1.1rem; font-weight: 600;"><?php echo $success; ?></div>
            </div>
            <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px; margin-top: 15px;">
                <p style="color: white; margin-bottom: 10px; font-weight: 600;">
                    <i class="fas fa-info-circle"></i> What happens next?
                </p>
                <p style="color: var(--pale-red); margin-bottom: 5px;">
                    • Our team will verify your payment within minutes to hours.
                </p>
                <p style="color: var(--pale-red); margin-bottom: 5px;">
                    • You'll receive a notification once your payment is verified.
                </p>
                <p style="color: var(--pale-red);">
                    • Your booking reference <strong><?php echo $booking['booking_reference']; ?></strong> will be your ticket.
                </p>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-primary" style="padding: 12px 30px;">
                    <i class="fas fa-ticket-alt"></i> My Bookings
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($success)): ?>

    <div style="background: rgba(241, 196, 15, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px; border-left: 4px solid #f39c12;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <i class="fas fa-exclamation-triangle" style="color: #f39c12; font-size: 1.5rem;"></i>
            <div>
                <p style="color: white; font-weight: 600; margin-bottom: 5px;">Please double-check before sending payment</p>
                <p style="color: var(--pale-red); font-size: 0.9rem;">
                    Make sure you're sending to the correct account details below. 
                    We are not responsible for payments sent to wrong accounts.
                </p>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div>
            <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; font-weight: 700;">
                <i class="fas fa-info-circle"></i> Payment Details
            </h2>
            
            <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <span style="color: var(--pale-red);">Amount to Pay:</span>
                    <span style="color: #2ecc71; font-size: 1.8rem; font-weight: 800;">₱<?php echo number_format($booking['booking_fee'], 2); ?></span>
                </div>
                
                <div style="margin-top: 20px; background: rgba(52, 152, 219, 0.1); padding: 15px; border-radius: 8px;">
                    <p style="color: white; margin-bottom: 10px; font-weight: 600;">
                        <i class="fas fa-clock"></i> Verification Time:
                    </p>
                    <p style="color: var(--pale-red);">
                        Within minutes to hours during office hours (8:00 AM - 5:00 PM)
                    </p>
                </div>
            </div>

            <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px;">
                <h3 style="color: white; font-size: 1.2rem; margin-bottom: 15px; font-weight: 600;">Accepted Payment Methods</h3>
                
                <div style="display: grid; gap: 20px;">
                    <?php while ($method = $payment_methods->fetch_assoc()): ?>
                    <div style="background: rgba(255, 255, 255, 0.03); border-radius: 10px; padding: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                            <div style="font-size: 2rem; color: <?php 
                                echo $method['method_name'] == 'GCash' ? '#00bfff' : 
                                    ($method['method_name'] == 'PayMaya' ? '#ff6b6b' : '#2ecc71'); 
                            ?>;">
                                <i class="fas <?php 
                                    echo $method['method_name'] == 'GCash' ? 'fa-mobile-alt' : 
                                        ($method['method_name'] == 'PayMaya' ? 'fa-mobile-alt' : 'fa-university'); 
                                ?>"></i>
                            </div>
                            <h4 style="color: white; font-size: 1.2rem; font-weight: 600;"><?php echo $method['method_name']; ?></h4>
                        </div>
                        
                        <?php if (!empty($method['qr_code_path'])): ?>
                        <div style="text-align: center; margin: 15px 0;">
                            <img src="<?php echo SITE_URL . $method['qr_code_path']; ?>" 
                                 alt="<?php echo $method['method_name']; ?> QR Code"
                                 style="max-width: 150px; border-radius: 10px; border: 2px solid rgba(226, 48, 32, 0.3);">
                        </div>
                        <?php endif; ?>
                        
                        <div style="background: rgba(0, 0, 0, 0.3); padding: 10px; border-radius: 6px; margin-top: 10px;">
                            <p style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Account Name:</p>
                            <p style="color: white; font-weight: 600; margin-bottom: 10px;"><?php echo $method['account_name']; ?></p>
                            
                            <p style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Account Number:</p>
                            <p style="color: white; font-weight: 600; margin-bottom: 10px;"><?php echo $method['account_number']; ?></p>
                            
                            <?php if (!empty($method['instructions'])): ?>
                            <p style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Instructions:</p>
                            <p style="color: white; font-size: 0.9rem;"><?php echo $method['instructions']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div>
            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                 border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
                <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; font-weight: 700;">
                    <i class="fas fa-upload"></i> Upload Payment Proof
                </h2>

                <form method="POST" action="" enctype="multipart/form-data" id="paymentForm">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Payment Method *
                        </label>
                        <select name="payment_method_id" required style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                            <option value="" style="background: #2c3e50; color: white;">Select payment method</option>
                            <?php 
                            $payment_methods->data_seek(0);
                            while ($method = $payment_methods->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $method['id']; ?>" style="background: #2c3e50; color: white;">
                                <?php echo $method['method_name']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Reference/Confirmation Number *
                        </label>
                        <input type="text" name="reference_number" required 
                               style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 8px; color: white; font-size: 1rem;"
                               placeholder="Enter GCash/Maya/Bank reference number">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Amount Paid *
                        </label>
                        <input type="number" name="amount" step="0.01" value="<?php echo $booking['booking_fee']; ?>" required 
                               style="width: 100%; padding: 12px 15px; background: rgba(255, 255, 255, 0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 8px; color: white; font-size: 1rem;">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                            Payment Screenshot *
                        </label>
                        <div style="border: 2px dashed rgba(226, 48, 32, 0.3); border-radius: 8px; padding: 30px; text-align: center; background: rgba(255, 255, 255, 0.02);">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--pale-red); margin-bottom: 10px;"></i>
                            <p style="color: white; margin-bottom: 10px;">Drag and drop or click to upload</p>
                            <p style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 15px;">JPG, PNG, GIF (Max 5MB)</p>
                            <input type="file" name="payment_screenshot" accept="image/*" required style="display: none;" id="fileInput">
                            <button type="button" onclick="document.getElementById('fileInput').click()" class="btn btn-secondary" style="padding: 10px 20px;">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                            <div id="fileName" style="margin-top: 10px; color: #2ecc71; font-size: 0.9rem;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; color: white; cursor: pointer;">
                            <input type="checkbox" name="confirm" required style="width: 18px; height: 18px; accent-color: #e23020;">
                            <span>I confirm that I have sent the payment to the correct account details above.</span>
                        </label>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <button type="submit" name="submit_manual_payment" class="btn btn-primary" style="flex: 1; padding: 15px;">
                            <i class="fas fa-paper-plane"></i> Submit Payment
                        </button>
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/payment&booking_id=<?php echo $booking_id; ?>" class="btn btn-secondary" style="padding: 15px 25px;">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(226, 48, 32, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(226, 48, 32, 0.3);
}

.btn-secondary:hover {
    background: rgba(226, 48, 32, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-3px);
}

:root {
    --primary-red: #e23020;
    --dark-red: #c11b18;
    --deep-red: #a80f0f;
    --light-red: #ff6b6b;
    --pale-red: #ff9999;
    --bg-dark: #0f0f23;
    --bg-darker: #1a1a2e;
    --bg-card: #3a0b07;
    --bg-card-light: #6b140e;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.12);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 4px rgba(226, 48, 32, 0.2);
}

@media (max-width: 992px) {
    .payment-container > div > div {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .payment-container {
        padding: 15px;
    }
}
</style>

<script>
document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileNameDiv = document.getElementById('fileName');
    if (fileName) {
        fileNameDiv.innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + fileName;
    } else {
        fileNameDiv.innerHTML = '';
    }
});

document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
    const fileInput = document.getElementById('fileInput');
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please upload a payment screenshot.');
        return false;
    }
    return true;
});
</script>

<?php
require_once $root_dir . '/partials/footer.php';
?>