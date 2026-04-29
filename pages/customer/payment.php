<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$conn = get_db_connection();  
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$booking = null;

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

// ============================================
// TIME CHECK - PHILIPPINES TIME (Asia/Manila)
// ============================================
date_default_timezone_set('Asia/Manila');
$current_hour = (int)date('H');
$current_time_display = date('h:i A');
$is_admin_hours = ($current_hour >= 8 && $current_hour < 17);

// If NOT admin hours, block manual payment access
if (!$is_admin_hours) {

    $manual_payment_blocked = true;
} else {
    $manual_payment_blocked = false;
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

// ============================================
// REDIRECT TO MANUAL PAYMENT PAGE IF ADMIN HOURS
// ============================================
// If we're not already on manual-payment page and it's admin hours,
// redirect to the dedicated manual payment page
if ($is_admin_hours && !$manual_payment_blocked && !isset($_GET['manual'])) {
    header("Location: " . SITE_URL . "index.php?page=customer/payment-manual&booking_id=" . $booking_id);
    exit();
}

// Get payment methods (for reference, though we redirect away)
$payment_methods = $conn->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY display_order");

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="payment-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
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

    <!-- Time Status Message -->
    <div style="background: <?php echo $is_admin_hours ? 'rgba(46, 204, 113, 0.1)' : 'rgba(241, 196, 15, 0.1)'; ?>; 
         border-radius: 10px; padding: 15px 20px; margin-bottom: 25px; 
         border-left: 4px solid <?php echo $is_admin_hours ? '#2ecc71' : '#f39c12'; ?>;">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <i class="fas fa-clock" style="color: <?php echo $is_admin_hours ? '#2ecc71' : '#f39c12'; ?>; font-size: 1.5rem;"></i>
            <div>
                <p style="color: white; font-weight: 600; margin-bottom: 5px;">
                    Current Time: <strong><?php echo $current_time_display; ?></strong> (Philippines Time)
                </p>
                <p style="color: var(--pale-red); font-size: 0.9rem;">
                    <?php if ($is_admin_hours): ?>
                        ✅ Admin hours are <strong>8:00 AM - 5:00 PM</strong>. You will be redirected to manual payment page.
                    <?php else: ?>
                        ⚠️ Admin hours are <strong>8:00 AM - 5:00 PM</strong>. Manual payment is currently unavailable.
                        Please use <strong>PayMongo</strong> for instant payment, or wait until admin hours.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Payment Options Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- PayMongo Card (Always Available 24/7) -->
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
                Get your booking confirmed instantly! Fast and secure payment available 24/7.
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
                ₱<?php echo number_format($booking['total_amount'], 2); ?>
            </div>
            <div style="margin-top: auto;">
                <span style="background: #e23020; color: white; padding: 12px 30px; border-radius: 30px; font-weight: 600; display: inline-block; width: 100%; text-align: center; box-sizing: border-box;">
                    Pay with PayMongo
                </span>
            </div>
        </div>

        <!-- Manual Payment Card (Conditionally Available) -->
        <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
             border-radius: 15px; padding: 30px; border: 2px solid <?php echo $is_admin_hours ? 'rgba(226, 48, 32, 0.3)' : 'rgba(149, 165, 166, 0.3)'; ?>; 
             text-align: center; transition: all 0.3s ease; display: flex; flex-direction: column; height: 100%;
             <?php echo !$is_admin_hours ? 'opacity: 0.7; cursor: not-allowed;' : 'cursor: pointer;'; ?>"
             <?php if ($is_admin_hours): ?>
             onclick="window.location.href='<?php echo SITE_URL; ?>index.php?page=customer/payment-manual&booking_id=<?php echo $booking_id; ?>'"
             onmouseover="this.style.transform='translateY(-10px)'; this.style.borderColor='#e23020';"
             onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(226, 48, 32, 0.3)';"
             <?php else: ?>
             onmouseover="this.style.transform='none';"
             onmouseout="this.style.transform='none';"
             <?php endif; ?>>
            <div style="font-size: 4rem; color: <?php echo $is_admin_hours ? '#e23020' : '#95a5a6'; ?>; margin-bottom: 20px;">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <h2 style="color: white; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700;">
                Manual Payment
            </h2>
            <p style="color: var(--pale-red); margin-bottom: 20px; line-height: 1.6; flex: 1;">
                Pay via GCash, PayMaya, or bank transfer. Upload your proof of payment for verification.
            </p>
            
            <div style="background: <?php echo $is_admin_hours ? 'rgba(46, 204, 113, 0.1)' : 'rgba(241, 196, 15, 0.1)'; ?>; 
                 padding: 15px; border-radius: 8px; margin-bottom: 20px; 
                 border-left: 4px solid <?php echo $is_admin_hours ? '#2ecc71' : '#f39c12'; ?>;">
                <div style="display: flex; align-items: center; gap: 10px; color: <?php echo $is_admin_hours ? '#2ecc71' : '#f39c12'; ?>; margin-bottom: 8px;">
                    <i class="fas fa-clock fa-lg"></i>
                    <span style="font-weight: 600;">Admin Hours: 8:00 AM - 5:00 PM</span>
                </div>
                <p style="color: var(--pale-red); margin-top: 8px; font-size: 0.85rem;">
                    Current time: <strong><?php echo $current_time_display; ?></strong>
                </p>
                <?php if (!$is_admin_hours): ?>
                <p style="color: #f39c12; margin-top: 8px; font-size: 0.85rem; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 5px;">
                    <i class="fas fa-lock"></i> <strong>Manual payment is currently unavailable.</strong><br>
                    Please come back during admin hours (8:00 AM - 5:00 PM) or use PayMongo for instant payment.
                </p>
                <?php else: ?>
                <p style="color: #2ecc71; margin-top: 8px; font-size: 0.85rem; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 5px;">
                    <i class="fas fa-check-circle"></i> <strong>Manual payment is available now!</strong><br>
                    Click to proceed with manual payment.
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
                ₱<?php echo number_format($booking['total_amount'], 2); ?>
            </div>
            <div style="margin-top: auto;">
                <span style="background: <?php echo $is_admin_hours ? '#e23020' : '#95a5a6'; ?>; color: white; padding: 12px 30px; border-radius: 30px; font-weight: 600; display: inline-block; width: 100%; text-align: center; box-sizing: border-box;">
                    <?php echo $is_admin_hours ? 'Continue with Manual Payment' : 'Unavailable - Outside Admin Hours'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div style="text-align: center; margin-top: 30px;">
        <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary" style="padding: 12px 30px;">
            <i class="fas fa-arrow-left"></i> Back to My Bookings
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

@media (max-width: 992px) {
    .payment-container > div:nth-child(5) {
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

<script>
// Countdown timer for payment expiry
const hoursSinceBooking = <?php echo $hours_since_booking; ?>;
const expiryHours = 3;
const remainingHours = Math.max(0, expiryHours - hoursSinceBooking);
const remainingMinutes = Math.floor((remainingHours - Math.floor(remainingHours)) * 60);

if (remainingHours > 0) {
    console.log(`Payment must be completed within ${Math.floor(remainingHours)} hours ${remainingMinutes} minutes`);
}

// Update countdown timer every minute
function updateCountdown() {
    const remainingElem = document.querySelector('.fa-hourglass-half')?.parentElement;
    if (remainingElem) {
        // Force refresh page when time is about to expire (5 minutes left)
        if (remainingHours <= 0.08) { // ~5 minutes
            location.reload();
        }
    }
}

setInterval(updateCountdown, 60000);
</script>

<?php
require_once $root_dir . '/partials/footer.php';
?>