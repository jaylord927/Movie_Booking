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

    <?php if (!$is_manual_payment_available): ?>
    <div style="background: rgba(52, 152, 219, 0.1); border: 2px solid #3498db; border-radius: 15px; padding: 20px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div style="font-size: 2.5rem; color: #3498db;">
            <i class="fas fa-info-circle"></i>
        </div>
        <div style="flex: 1;">
            <h3 style="color: white; font-size: 1.2rem; margin-bottom: 5px; font-weight: 700;">Quick Tip for Faster Processing ✨</h3>
            <p style="color: var(--pale-red); margin-bottom: 5px;">
                While you can still use manual payment, we recommend using <strong>PayMongo</strong> for instant confirmation - it's available 24/7!
            </p>
            <p style="color: #3498db; font-size: 0.95rem;">
                <i class="fas fa-clock"></i> Manual payment is best during office hours (8:00 AM - 5:00 PM). Current time: <strong><?php echo $current_time; ?></strong>
            </p>
        </div>
    </div>
    <?php endif; ?>

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
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                 border-radius: 15px; padding: 30px; border: 2px solid rgba(226, 48, 32, 0.3); text-align: center;
                 transition: all 0.3s ease; cursor: pointer;"
                 onclick="window.location.href='?page=payment&booking_id=<?php echo $booking_id; ?>&method=paymongo'"
                 onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='#e23020';"
                 onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';">
                <div style="font-size: 4rem; color: #e23020; margin-bottom: 20px;">
                    <i class="fas fa-bolt"></i>
                </div>
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700;">
                    PayMongo
                </h2>
                <p style="color: var(--pale-red); margin-bottom: 20px; line-height: 1.6;">
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
                <div style="font-size: 1.5rem; color: #2ecc71; font-weight: 700;">
                    ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                </div>
                <div style="margin-top: 20px;">
                    <span style="background: #e23020; color: white; padding: 10px 30px; border-radius: 30px; font-weight: 600; display: inline-block;">
                        Pay with PayMongo
                    </span>
                </div>
            </div>

            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                 border-radius: 15px; padding: 30px; border: 2px solid rgba(226, 48, 32, 0.3); text-align: center;
                 transition: all 0.3s ease; cursor: pointer;"
                 onclick="window.location.href='<?php echo SITE_URL; ?>index.php?page=customer/payment-manual&booking_id=<?php echo $booking_id; ?>'"
                 onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='#e23020';"
                 onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';">
                <div style="font-size: 4rem; color: #e23020; margin-bottom: 20px;">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700;">
                    Manual Payment
                </h2>
                <p style="color: var(--pale-red); margin-bottom: 20px; line-height: 1.6;">
                    Pay via GCash, PayMaya, or bank transfer. Upload your proof of payment for verification.
                </p>
                
                <div style="background: <?php echo $is_manual_payment_available ? 'rgba(46, 204, 113, 0.1)' : 'rgba(52, 152, 219, 0.1)'; ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid <?php echo $is_manual_payment_available ? '#2ecc71' : '#3498db'; ?>;">
                    <div style="display: flex; align-items: center; gap: 10px; color: <?php echo $is_manual_payment_available ? '#2ecc71' : '#3498db'; ?>; margin-bottom: 8px;">
                        <i class="fas fa-clock fa-lg"></i>
                        <span style="font-weight: 600;">Office Hours: 8:00 AM - 5:00 PM</span>
                    </div>
                    <p style="color: var(--pale-red); margin-top: 8px; font-size: 0.9rem;">
                        Current time: <strong><?php echo $current_time; ?></strong>
                    </p>
                    <?php if (!$is_manual_payment_available): ?>
                    <p style="color: #3498db; margin-top: 8px; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> You can still use manual payment, but PayMongo gives you instant confirmation!
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
                
                <div style="font-size: 1.5rem; color: #2ecc71; font-weight: 700;">
                    ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                </div>
                <div style="margin-top: 20px;">
                    <span style="background: #e23020; color: white; padding: 10px 30px; border-radius: 30px; font-weight: 600; display: inline-block;">
                        Continue with Manual Payment
                    </span>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary" style="padding: 12px 30px;">
                <i class="fas fa-arrow-left"></i> Back to My Bookings
            </a>
        </div>

    <?php elseif ($payment_method === 'paymongo'): ?>
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
             border-radius: 15px; padding: 50px; text-align: center; border: 1px solid rgba(226, 48, 32, 0.3);">
            <i class="fas fa-tools" style="font-size: 4rem; color: #f39c12; margin-bottom: 20px;"></i>
            <h2 style="color: white; font-size: 2rem; margin-bottom: 15px;">PayMongo Coming Soon!</h2>
            <p style="color: var(--pale-red); font-size: 1.1rem; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                We're working hard to bring you instant payments! In the meantime, you can use manual payment.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/payment-manual&booking_id=<?php echo $booking_id; ?>" class="btn btn-primary" style="padding: 12px 30px;">
                    <i class="fas fa-hand-holding-usd"></i> Use Manual Payment
                </a>
                <a href="?page=payment&booking_id=<?php echo $booking_id; ?>" class="btn btn-secondary" style="padding: 12px 30px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
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
    
    h1 {
        font-size: 1.8rem !important;
    }
}
</style>

<?php
require_once $root_dir . '/partials/footer.php';
?>