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

$current_hour = (int)date('H');
$current_time = date('h:i A');
$is_manual_payment_available = ($current_hour >= 8 && $current_hour < 17);

$payment_method = isset($_GET['method']) ? $_GET['method'] : '';

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="payment-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="font-size: 2.5rem; color: var(--primary-red);">
                <i class="fas fa-credit-card"></i>
            </div>
            <div>
                <h1 style="color: white; font-size: 2rem; margin-bottom: 5px; font-weight: 800;">
                    Complete Your Payment
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
                    <i class="fas fa-info-circle"></i> Important Information:
                </p>
                <p style="color: var(--pale-red); margin-bottom: 5px;">
                    • Please present your booking reference <strong><?php echo $booking['booking_reference']; ?></strong> at the cinema counter.
                </p>
                <p style="color: var(--pale-red); margin-bottom: 5px;">
                    • You can also show the receipt or screenshot of this payment.
                </p>
                <p style="color: var(--pale-red);">
                    • This will be your ticket for entry.
                </p>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/receipt&id=<?php echo $booking_id; ?>" class="btn btn-primary" style="padding: 12px 30px;">
                    <i class="fas fa-print"></i> View Receipt
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary" style="padding: 12px 30px; margin-left: 10px;">
                    <i class="fas fa-ticket-alt"></i> My Bookings
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($payment_method) && empty($success)): ?>
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- PayMongo Card -->
                <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                     border-radius: 15px; padding: 30px; border: 2px solid rgba(226, 48, 32, 0.3); text-align: center;
                     transition: all 0.3s ease; cursor: pointer; display: flex; flex-direction: column; height: 100%;"
                     onclick="window.location.href='<?php echo SITE_URL; ?>index.php?page=customer/paymongo&booking_id=<?php echo $booking_id; ?>'"
                     onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='#e23020';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';">
                    <div style="font-size: 4rem; color: #e23020; margin-bottom: 20px;">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700;">
                        PayMongo
                    </h2>
                    <p style="color: var(--pale-red); margin-bottom: 20px; line-height: 1.6; flex: 1;">
                        Get your booking confirmed instantly! Fast and secure payment available anytime.
                    </p>
                    <div style="background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px; color: white; margin-bottom: 8px;">
                            <i class="fas fa-check-circle" style="color: #2ecc71;"></i>
                            <span>Available 24/7 - No waiting!</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; color: white; margin-bottom: 8px;">
                            <i class="fas fa-check-circle" style="color: #2ecc71;"></i>
                            <span>Instant Confirmation</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; color: white;">
                            <i class="fas fa-check-circle" style="color: #2ecc71;"></i>
                            <span>Credit/Debit Cards, GCash, PayMaya</span>
                        </div>
                    </div>
                    <div style="font-size: 1.5rem; color: #2ecc71; font-weight: 700; margin-bottom: 20px;">
                        ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                    </div>
                    <div style="margin-top: auto;">
                        <span style="background: #e23020; color: white; padding: 12px 30px; border-radius: 30px; font-weight: 600; display: inline-block; width: 100%; text-align: center; box-sizing: border-box;">
                            Pay with PayMongo
                        </span>
                    </div>
                </div>

                <!-- Manual Payment Card -->
                <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                     border-radius: 15px; padding: 30px; border: 2px solid rgba(226, 48, 32, 0.3); text-align: center;
                     transition: all 0.3s ease; cursor: pointer; display: flex; flex-direction: column; height: 100%;"
                     onclick="window.location.href='<?php echo SITE_URL; ?>index.php?page=customer/payment-manual&booking_id=<?php echo $booking_id; ?>'"
                     onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='#e23020';"
                     onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';">
                    <div style="font-size: 4rem; color: #e23020; margin-bottom: 20px;">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700;">
                        Manual Payment
                    </h2>
                    <p style="color: var(--pale-red); margin-bottom: 20px; line-height: 1.6; flex: 1;">
                        Pay via GCash, PayMaya, or bank transfer. Upload your proof of payment for verification.
                    </p>
                    
                    <div style="background: <?php echo $is_manual_payment_available ? 'rgba(46, 204, 113, 0.1)' : 'rgba(241, 196, 15, 0.1)'; ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid <?php echo $is_manual_payment_available ? '#2ecc71' : '#f39c12'; ?>;">
                        <div style="display: flex; align-items: center; gap: 10px; color: <?php echo $is_manual_payment_available ? '#2ecc71' : '#f39c12'; ?>; margin-bottom: 8px;">
                            <i class="fas fa-clock fa-lg"></i>
                            <span style="font-weight: 600;">Admin Hours: 8:00 AM - 5:00 PM</span>
                        </div>
                        <p style="color: var(--pale-red); margin-top: 8px; font-size: 0.9rem;">
                            Current time: <strong><?php echo $current_time; ?></strong>
                        </p>
                        <?php if (!$is_manual_payment_available): ?>
                        <p style="color: #f39c12; margin-top: 8px; font-size: 0.9rem; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 5px;">
                            <i class="fas fa-lightbulb"></i> <strong>Quick tip:</strong> PayMongo gives you instant confirmation 24/7. Manual payments will be processed when admin is back online.
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px; color: white; margin-bottom: 8px;">
                            <i class="fas fa-check-circle" style="color: #f39c12;"></i>
                            <span>GCash, PayMaya, Bank Transfer</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; color: white; margin-bottom: 8px;">
                            <i class="fas fa-check-circle" style="color: #f39c12;"></i>
                            <span>Upload payment screenshot</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; color: white;">
                            <i class="fas fa-check-circle" style="color: #f39c12;"></i>
                            <span>Verification within minutes to hours</span>
                        </div>
                    </div>
                    
                    <div style="font-size: 1.5rem; color: #2ecc71; font-weight: 700; margin-bottom: 20px;">
                        ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                    </div>
                    <div style="margin-top: auto;">
                        <span style="background: #e23020; color: white; padding: 12px 30px; border-radius: 30px; font-weight: 600; display: inline-block; width: 100%; text-align: center; box-sizing: border-box;">
                            Continue with Manual Payment
                        </span>
                    </div>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary" style="padding: 12px 30px;">
                    <i class="fas fa-arrow-left"></i> Back to My Bookings
                </a>
            </div>
        </div>

    <?php elseif ($payment_method === 'paymongo'): ?>
        <!-- This section is now handled by paymongo.php, so we redirect -->
        <?php header("Location: " . SITE_URL . "index.php?page=customer/paymongo&booking_id=" . $booking_id); ?>
        exit();
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

@media (max-width: 768px) {
    .payment-container > div > div {
        grid-template-columns: 1fr;
    }
    
    .payment-container {
        padding: 15px;
    }
    
    h1 {
        font-size: 1.8rem !important;
    }
}
</style>

<?php
require_once $root_dir . '/partials/footer.php';
?>