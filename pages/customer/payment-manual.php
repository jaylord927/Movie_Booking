<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

// ============================================
// TIME CHECK - PHILIPPINES TIME (Asia/Manila)
// ============================================
date_default_timezone_set('Asia/Manila');
$current_hour = (int)date('H');
$current_time_display = date('h:i A');
$is_admin_hours = ($current_hour >= 8 && $current_hour < 17);

// If NOT admin hours, redirect back to payment page with error message
if (!$is_admin_hours) {
    header("Location: " . SITE_URL . "index.php?page=customer/payment&booking_id=" . ($_GET['booking_id'] ?? 0) . "&error=manual_unavailable");
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

// Get booking details with normalized schema
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.booking_reference,
        b.total_amount,
        b.payment_status,
        b.status,
        b.booked_at,
        s.id as schedule_id,
        s.show_date,
        s.showtime,
        m.title as movie_title,
        m.poster_url,
        m.rating,
        m.duration,
        v.venue_name,
        v.venue_location,
        sc.screen_name,
        GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        COUNT(DISTINCT bs.id) as total_seats,
        TIMESTAMPDIFF(HOUR, b.booked_at, NOW()) as hours_since_booking
    FROM bookings b
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    LEFT JOIN booked_seats bs ON b.id = bs.booking_id
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'pending'
    GROUP BY b.id
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

// Check if booking is expired (more than 3 hours)
$hours_since_booking = $booking['hours_since_booking'];
$is_expired = $hours_since_booking >= 3;

if ($is_expired) {
    // Auto-cancel expired booking
    $cancel_stmt = $conn->prepare("
        UPDATE bookings 
        SET status = 'cancelled', payment_status = 'refunded' 
        WHERE id = ? AND user_id = ?
    ");
    $cancel_stmt->bind_param("ii", $booking_id, $user_id);
    $cancel_stmt->execute();
    $cancel_stmt->close();
    
    // Release seats back to availability
    $release_seats = $conn->prepare("
        UPDATE seat_availability sa
        JOIN booked_seats bs ON sa.id = bs.seat_availability_id
        SET sa.status = 'available', sa.locked_by = NULL, sa.locked_at = NULL
        WHERE bs.booking_id = ?
    ");
    $release_seats->bind_param("i", $booking_id);
    $release_seats->execute();
    $release_seats->close();
    
    $conn->close();
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings&error=payment_expired");
    exit();
}

// Get payment methods
$payment_methods = $conn->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY display_order");

// Handle manual payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_manual_payment'])) {
    $payment_method_id = intval($_POST['payment_method_id']);
    $reference_number = sanitize_input($_POST['reference_number'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (empty($reference_number)) {
        $error = "Please enter your payment reference number.";
    } elseif ($amount <= 0) {
        $error = "Invalid payment amount.";
    } elseif ($amount != $booking['total_amount']) {
        $error = "Amount paid must match the booking total of ₱" . number_format($booking['total_amount'], 2);
    } else {
        $target_dir = $root_dir . "/uploads/payments/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $screenshot_path = '';
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $file_type = $_FILES['payment_screenshot']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = "Only JPG, PNG, GIF, and WEBP files are allowed.";
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
            $conn->begin_transaction();
            
            try {
                $insert_stmt = $conn->prepare("
                    INSERT INTO manual_payments (booking_id, user_id, payment_method_id, reference_number, amount, screenshot_path, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ");
                $insert_stmt->bind_param("iiisds", $booking_id, $user_id, $payment_method_id, $reference_number, $amount, $screenshot_path);
                
                if (!$insert_stmt->execute()) {
                    throw new Exception("Failed to submit payment: " . $insert_stmt->error);
                }
                $insert_stmt->close();
                
                $update_stmt = $conn->prepare("
                    UPDATE bookings 
                    SET payment_status = 'pending_verification'
                    WHERE id = ? AND user_id = ?
                ");
                $update_stmt->bind_param("ii", $booking_id, $user_id);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update booking status");
                }
                $update_stmt->close();
                
                $conn->commit();
                $success = "Your payment has been submitted successfully! Our team will verify your payment within minutes to hours.";
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="payment-manual-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
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

    <!-- Time Status Message -->
    <div style="background: rgba(46, 204, 113, 0.1); border-radius: 10px; padding: 15px 20px; margin-bottom: 25px; border-left: 4px solid #2ecc71;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <i class="fas fa-clock" style="color: #2ecc71; font-size: 1.5rem;"></i>
            <div>
                <p style="color: white; font-weight: 600; margin-bottom: 5px;">
                    Admin Hours: <strong>8:00 AM - 5:00 PM</strong> (Philippines Time)
                </p>
                <p style="color: #2ecc71; font-size: 0.9rem;">
                    <i class="fas fa-check-circle"></i> Current time: <strong><?php echo $current_time_display; ?></strong> - Manual payment is available
                </p>
            </div>
        </div>
    </div>

    <!-- Booking Summary -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-receipt"></i> Booking Summary
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div>
                <div style="display: flex; gap: 15px; align-items: flex-start;">
                    <?php if (!empty($booking['poster_url'])): ?>
                    <img src="<?php echo $booking['poster_url']; ?>" 
                         alt="<?php echo htmlspecialchars($booking['movie_title']); ?>"
                         style="width: 60px; height: 80px; object-fit: cover; border-radius: 8px;">
                    <?php else: ?>
                    <div style="width: 60px; height: 80px; background: rgba(226,48,32,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-film" style="color: rgba(255,255,255,0.3);"></i>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div style="color: white; font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($booking['movie_title']); ?></div>
                        <div style="color: var(--pale-red); font-size: 0.85rem;">
                            <?php echo $booking['rating']; ?> • <?php echo $booking['duration']; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <div style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 5px;">Show Date & Time</div>
                <div style="color: white; font-weight: 600;"><?php echo date('h:i A', strtotime($booking['showtime'])); ?></div>
                <div style="color: var(--pale-red); font-size: 0.85rem;"><?php echo date('D, M d, Y', strtotime($booking['show_date'])); ?></div>
            </div>
            
            <div>
                <div style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 5px;">Venue & Screen</div>
                <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($booking['venue_name']); ?></div>
                <div style="color: var(--pale-red); font-size: 0.85rem;"><?php echo htmlspecialchars($booking['screen_name']); ?></div>
            </div>
            
            <div>
                <div style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 5px;">Seats</div>
                <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($booking['seat_list']); ?></div>
                <div style="color: var(--pale-red); font-size: 0.85rem;"><?php echo $booking['total_seats']; ?> seat(s)</div>
            </div>
        </div>
        
        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(226,48,32,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <span style="color: white; font-weight: 700;">Total Amount:</span>
                <span style="color: #2ecc71; font-size: 1.8rem; font-weight: 800;">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
            </div>
            <div style="margin-top: 10px; background: rgba(241,196,15,0.1); padding: 12px; border-radius: 8px; border-left: 4px solid #f1c40f;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-hourglass-half" style="color: #f1c40f;"></i>
                    <span style="color: white; font-size: 0.85rem;">
                        Complete payment within <strong><?php echo max(0, 3 - $hours_since_booking); ?> hour(s)</strong> or booking will be automatically cancelled.
                    </span>
                </div>
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
    
    <!-- Payment Warning -->
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

    <!-- Accepted Payment Methods -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px;">
        <?php 
        $payment_methods->data_seek(0);
        while ($method = $payment_methods->fetch_assoc()): 
        ?>
        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(226, 48, 32, 0.2);">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                <div style="font-size: 2rem; color: <?php 
                    echo $method['method_name'] == 'GCash' ? '#00bfff' : 
                        ($method['method_name'] == 'PayMaya' ? '#ff6b6b' : '#2ecc71'); 
                ?>;">
                    <i class="fas <?php 
                        echo $method['method_name'] == 'GCash' ? 'fa-mobile-alt' : 
                            ($method['method_name'] == 'PayMaya' ? 'fa-mobile-alt' : 'fa-university'); 
                    ?>"></i>
                </div>
                <h3 style="color: white; font-size: 1.3rem; font-weight: 700;"><?php echo $method['method_name']; ?></h3>
            </div>
            
            <?php if (!empty($method['qr_code_path']) && file_exists($root_dir . '/' . $method['qr_code_path'])): ?>
            <div style="text-align: center; margin: 15px 0;">
                <img src="<?php echo SITE_URL . $method['qr_code_path']; ?>" 
                     alt="<?php echo $method['method_name']; ?> QR Code"
                     style="max-width: 150px; border-radius: 10px; border: 2px solid rgba(226,48,32,0.3);">
            </div>
            <?php endif; ?>
            
            <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; margin-top: 10px;">
                <p style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 5px;">Account Name:</p>
                <p style="color: white; font-weight: 600; margin-bottom: 8px;"><?php echo htmlspecialchars($method['account_name']); ?></p>
                
                <p style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 5px;">Account Number:</p>
                <p style="color: white; font-weight: 600; margin-bottom: 8px;"><?php echo htmlspecialchars($method['account_number']); ?></p>
                
                <?php if (!empty($method['instructions'])): ?>
                <p style="color: var(--pale-red); font-size: 0.85rem; margin-bottom: 5px;">Instructions:</p>
                <p style="color: white; font-size: 0.85rem;"><?php echo nl2br(htmlspecialchars($method['instructions'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Upload Payment Proof Form -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-upload"></i> Upload Payment Proof
        </h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="manualPaymentForm">
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                    Payment Method *
                </label>
                <select name="payment_method_id" id="paymentMethodSelect" required 
                        style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226,48,32,0.3); border-radius: 8px; color: white; font-size: 1rem;">
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
                <input type="text" name="reference_number" id="referenceNumber" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226,48,32,0.3); border-radius: 8px; color: white; font-size: 1rem;"
                       placeholder="Enter GCash/Maya/Bank reference number">
                <div style="color: var(--pale-red); font-size: 0.75rem; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> This is the transaction ID/reference number from your payment app
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                    Amount Paid *
                </label>
                <input type="number" name="amount" id="amountPaid" step="0.01" value="<?php echo $booking['total_amount']; ?>" required 
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226,48,32,0.3); border-radius: 8px; color: white; font-size: 1rem;">
                <div style="color: var(--pale-red); font-size: 0.75rem; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Amount should match the total: <strong>₱<?php echo number_format($booking['total_amount'], 2); ?></strong>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                    Payment Screenshot *
                </label>
                <div style="border: 2px dashed rgba(226,48,32,0.3); border-radius: 8px; padding: 30px; text-align: center; background: rgba(255,255,255,0.02);">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--pale-red); margin-bottom: 10px;"></i>
                    <p style="color: white; margin-bottom: 10px;">Drag and drop or click to upload</p>
                    <p style="color: var(--pale-red); font-size: 0.8rem; margin-bottom: 15px;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    <input type="file" name="payment_screenshot" accept="image/*" required style="display: none;" id="fileInput">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="btn btn-secondary" style="padding: 10px 20px;">
                        <i class="fas fa-folder-open"></i> Choose File
                    </button>
                    <div id="fileName" style="margin-top: 10px; color: #2ecc71; font-size: 0.85rem;"></div>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; color: white; cursor: pointer;">
                    <input type="checkbox" name="confirm" id="confirmCheckbox" required style="width: 18px; height: 18px; accent-color: #e23020;">
                    <span>I confirm that I have sent the payment to the correct account details above.</span>
                </label>
            </div>

            <div style="background: rgba(52, 152, 219, 0.1); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="color: #3498db; font-size: 0.85rem; margin-bottom: 5px;">
                    <i class="fas fa-info-circle"></i> <strong>Important Notes:</strong>
                </p>
                <ul style="color: var(--pale-red); font-size: 0.8rem; padding-left: 20px; margin-top: 5px;">
                    <li>Please send the exact amount shown above</li>
                    <li>Upload a clear screenshot of your payment confirmation</li>
                    <li>Include the reference number in your payment</li>
                    <li>Verification typically takes minutes to hours during admin hours</li>
                </ul>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" name="submit_manual_payment" class="btn btn-primary" style="flex: 1; padding: 15px;">
                    <i class="fas fa-paper-plane"></i> Submit Payment
                </button>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/payment&booking_id=<?php echo $booking_id; ?>" class="btn btn-secondary" style="padding: 15px 25px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </form>
    </div>
    
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary" style="padding: 12px 30px;">
            <i class="fas fa-ticket-alt"></i> My Bookings
        </a>
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
    .payment-manual-container > div:nth-child(5) {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .payment-manual-container {
        padding: 15px;
    }
    
    h1 {
        font-size: 1.8rem !important;
    }
}
</style>

<script>
// File input handler
document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileNameDiv = document.getElementById('fileName');
    if (fileName) {
        const fileSize = (e.target.files[0].size / 1024 / 1024).toFixed(2);
        fileNameDiv.innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + fileName + ' (' + fileSize + ' MB)';
        fileNameDiv.style.color = '#2ecc71';
    } else {
        fileNameDiv.innerHTML = '';
    }
});

// Amount validation
document.getElementById('amountPaid')?.addEventListener('change', function() {
    const totalAmount = <?php echo $booking['total_amount']; ?>;
    const enteredAmount = parseFloat(this.value);
    
    if (enteredAmount !== totalAmount) {
        this.style.borderColor = '#e74c3c';
        const errorDiv = document.getElementById('amountError') || document.createElement('div');
        errorDiv.id = 'amountError';
        errorDiv.style.color = '#e74c3c';
        errorDiv.style.fontSize = '0.75rem';
        errorDiv.style.marginTop = '5px';
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Amount should be ₱' + totalAmount.toFixed(2);
        if (!document.getElementById('amountError')) {
            this.parentNode.appendChild(errorDiv);
        }
    } else {
        this.style.borderColor = '#2ecc71';
        const errorDiv = document.getElementById('amountError');
        if (errorDiv) errorDiv.remove();
    }
});

// Form validation
document.getElementById('manualPaymentForm')?.addEventListener('submit', function(e) {
    const fileInput = document.getElementById('fileInput');
    const paymentMethod = document.getElementById('paymentMethodSelect').value;
    const referenceNumber = document.getElementById('referenceNumber').value.trim();
    const amountPaid = parseFloat(document.getElementById('amountPaid').value);
    const confirmCheckbox = document.getElementById('confirmCheckbox');
    const totalAmount = <?php echo $booking['total_amount']; ?>;
    
    if (!paymentMethod) {
        e.preventDefault();
        alert('Please select a payment method.');
        return false;
    }
    
    if (!referenceNumber) {
        e.preventDefault();
        alert('Please enter your payment reference number.');
        return false;
    }
    
    if (amountPaid !== totalAmount) {
        e.preventDefault();
        alert('Amount paid must match the booking total of ₱' + totalAmount.toFixed(2));
        return false;
    }
    
    if (!fileInput.files || fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please upload a payment screenshot.');
        return false;
    }
    
    const file = fileInput.files[0];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        e.preventDefault();
        alert('Please upload a valid image file (JPG, PNG, GIF, or WEBP).');
        return false;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        e.preventDefault();
        alert('File size must be less than 5MB.');
        return false;
    }
    
    if (!confirmCheckbox.checked) {
        e.preventDefault();
        alert('Please confirm that you have sent the payment to the correct account.');
        return false;
    }
    
    return true;
});

// Countdown timer for payment expiry
const hoursSinceBooking = <?php echo $hours_since_booking; ?>;
const expiryHours = 3;
const remainingHours = Math.max(0, expiryHours - hoursSinceBooking);

if (remainingHours <= 0.16) { // ~10 minutes
    const warningDiv = document.createElement('div');
    warningDiv.style.background = 'rgba(231, 76, 60, 0.2)';
    warningDiv.style.color = '#ff9999';
    warningDiv.style.padding = '15px 20px';
    warningDiv.style.borderRadius = '10px';
    warningDiv.style.marginBottom = '25px';
    warningDiv.style.border = '1px solid rgba(231, 76, 60, 0.3)';
    warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> Your booking will expire in less than 10 minutes. Please complete payment immediately.';
    
    const container = document.querySelector('.payment-manual-container');
    if (container && container.firstChild) {
        container.insertBefore(warningDiv, container.firstChild.nextSibling);
    }
}
</script>

<?php
require_once $root_dir . '/partials/footer.php';
?>