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

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

// Update booking payment status to Paid
$update_stmt = $conn->prepare("
    UPDATE tbl_booking 
    SET payment_status = 'Paid'
    WHERE b_id = ? AND u_id = ? AND payment_status = 'Pending'
");
$update_stmt->bind_param("ii", $booking_id, $user_id);
$update_stmt->execute();
$update_stmt->close();

// Get booking details for display
$booking_stmt = $conn->prepare("
    SELECT 
        b.*,
        GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        m.poster_url
    FROM tbl_booking b
    LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
    LEFT JOIN movies m ON b.movie_name = m.title
    WHERE b.b_id = ? AND b.u_id = ?
    GROUP BY b.b_id
");
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$booking = $booking_result->fetch_assoc();
$booking_stmt->close();
$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="payment-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, rgba(46, 204, 113, 0.2), rgba(39, 174, 96, 0.3)); 
         border-radius: 15px; padding: 40px; text-align: center; border: 2px solid #2ecc71; margin-bottom: 30px;">
        <div style="font-size: 5rem; color: #2ecc71; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px; font-weight: 800;">
            Payment Successful!
        </h1>
        <p style="color: var(--pale-red); font-size: 1.2rem;">
            Your booking has been confirmed. Thank you for your payment.
        </p>
    </div>

    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-receipt"></i> Booking Details
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div>
                <p style="color: var(--pale-red); margin-bottom: 5px;">Booking Reference</p>
                <p style="color: white; font-size: 1.2rem; font-weight: 700;"><?php echo $booking['booking_reference']; ?></p>
            </div>
            <div>
                <p style="color: var(--pale-red); margin-bottom: 5px;">Movie</p>
                <p style="color: white; font-size: 1.2rem;"><?php echo $booking['movie_name']; ?></p>
            </div>
            <div>
                <p style="color: var(--pale-red); margin-bottom: 5px;">Show Date & Time</p>
                <p style="color: white;">
                    <?php echo date('M d, Y', strtotime($booking['show_date'])); ?> at 
                    <?php echo date('h:i A', strtotime($booking['showtime'])); ?>
                </p>
            </div>
            <div>
                <p style="color: var(--pale-red); margin-bottom: 5px;">Seats</p>
                <p style="color: white;"><?php echo $booking['seat_list']; ?></p>
            </div>
            <div>
                <p style="color: var(--pale-red); margin-bottom: 5px;">Total Amount Paid</p>
                <p style="color: #2ecc71; font-size: 1.5rem; font-weight: 800;">
                    ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                </p>
            </div>
            <div>
                <p style="color: var(--pale-red); margin-bottom: 5px;">Payment Status</p>
                <p style="color: #2ecc71; font-weight: 600;">✓ Paid</p>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo SITE_URL; ?>index.php?page=customer/receipt&id=<?php echo $booking_id; ?>" class="btn btn-primary" style="padding: 15px 30px; margin-right: 10px;">
                <i class="fas fa-print"></i> View Receipt
            </a>
            <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-secondary" style="padding: 15px 30px;">
                <i class="fas fa-ticket-alt"></i> My Bookings
            </a>
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

@media (max-width: 768px) {
    .payment-container > div > div {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
require_once $root_dir . '/partials/footer.php';
?>