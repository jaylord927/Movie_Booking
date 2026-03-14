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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $payment_id = intval($_POST['payment_id']);
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_input($_POST['status']);
    $admin_notes = sanitize_input($_POST['admin_notes'] ?? '');
    
    $conn->begin_transaction();
    
    try {
        if ($status === 'Verified') {
            $update_payment = $conn->prepare("
                UPDATE manual_payments 
                SET status = ?, admin_notes = ?, verified_by = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $update_payment->bind_param("ssii", $status, $admin_notes, $_SESSION['user_id'], $payment_id);
            
            if (!$update_payment->execute()) {
                throw new Exception("Failed to update payment record");
            }
            $update_payment->close();
            
            $update_booking = $conn->prepare("
                UPDATE tbl_booking 
                SET payment_status = 'Paid'
                WHERE b_id = ?
            ");
            $update_booking->bind_param("i", $booking_id);
            
            if (!$update_booking->execute()) {
                throw new Exception("Failed to update booking status");
            }
            $update_booking->close();
            
            $success = "Payment verified successfully! Booking has been marked as paid.";
            
        } elseif ($status === 'Rejected') {
            $payment_info = $conn->prepare("
                SELECT mp.*, b.movie_name, b.show_date, b.showtime,
                       ms.id as schedule_id
                FROM manual_payments mp
                JOIN tbl_booking b ON mp.booking_id = b.b_id
                LEFT JOIN movie_schedules ms ON b.movie_name = ms.movie_title 
                    AND b.show_date = ms.show_date 
                    AND b.showtime = ms.showtime
                WHERE mp.id = ?
            ");
            $payment_info->bind_param("i", $payment_id);
            $payment_info->execute();
            $info_result = $payment_info->get_result();
            $payment_data = $info_result->fetch_assoc();
            $payment_info->close();
            
            $get_seats_stmt = $conn->prepare("
                SELECT seat_number 
                FROM booked_seats 
                WHERE booking_id = ?
            ");
            $get_seats_stmt->bind_param("i", $booking_id);
            $get_seats_stmt->execute();
            $seats_result = $get_seats_stmt->get_result();
            $seat_numbers = [];
            while ($seat_row = $seats_result->fetch_assoc()) {
                $seat_numbers[] = $seat_row['seat_number'];
            }
            $get_seats_stmt->close();
            
            $update_payment = $conn->prepare("
                UPDATE manual_payments 
                SET status = ?, admin_notes = ?, verified_by = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $update_payment->bind_param("ssii", $status, $admin_notes, $_SESSION['user_id'], $payment_id);
            
            if (!$update_payment->execute()) {
                throw new Exception("Failed to update payment record");
            }
            $update_payment->close();
            
            $update_booking = $conn->prepare("
                UPDATE tbl_booking 
                SET status = 'Cancelled', payment_status = 'Refunded'
                WHERE b_id = ?
            ");
            $update_booking->bind_param("i", $booking_id);
            
            if (!$update_booking->execute()) {
                throw new Exception("Failed to update booking status");
            }
            $update_booking->close();
            
            if (!empty($seat_numbers)) {
                foreach ($seat_numbers as $seat_number) {
                    $seat_update = $conn->prepare("
                        UPDATE seat_availability 
                        SET is_available = 1, booking_id = NULL
                        WHERE schedule_id = ? 
                        AND seat_number = ?
                    ");
                    $seat_update->bind_param("is", $payment_data['schedule_id'], $seat_number);
                    
                    if (!$seat_update->execute()) {
                        throw new Exception("Failed to update seat availability");
                    }
                    $seat_update->close();
                }
                
                $seat_count = count($seat_numbers);
                $update_schedule = $conn->prepare("
                    UPDATE movie_schedules 
                    SET available_seats = available_seats + ?
                    WHERE id = ?
                ");
                $update_schedule->bind_param("ii", $seat_count, $payment_data['schedule_id']);
                
                if (!$update_schedule->execute()) {
                    throw new Exception("Failed to update schedule");
                }
                $update_schedule->close();
            }
            
            $success = "Payment rejected. Booking has been cancelled and seats released.";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$requests_query = "
    SELECT 
        mp.*,
        u.u_name as customer_name,
        u.u_email as customer_email,
        pm.method_name,
        pm.account_name,
        pm.account_number,
        b.booking_reference,
        b.movie_name,
        b.show_date,
        b.showtime,
        b.booking_fee,
        b.status as booking_status,
        b.payment_status as booking_payment_status,
        a.u_name as verified_by_name,
        GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        COUNT(bs.id) as total_seats
    FROM manual_payments mp
    JOIN users u ON mp.user_id = u.u_id
    JOIN payment_methods pm ON mp.payment_method_id = pm.id
    JOIN tbl_booking b ON mp.booking_id = b.b_id
    LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
    LEFT JOIN users a ON mp.verified_by = a.u_id
    WHERE 1=1
";

if ($filter === 'pending') {
    $requests_query .= " AND mp.status = 'Pending'";
} elseif ($filter === 'verified') {
    $requests_query .= " AND mp.status = 'Verified'";
} elseif ($filter === 'rejected') {
    $requests_query .= " AND mp.status = 'Rejected'";
}

if (!empty($search)) {
    $requests_query .= " AND (b.booking_reference LIKE '%$search%' OR u.u_name LIKE '%$search%' OR u.u_email LIKE '%$search%' OR b.movie_name LIKE '%$search%')";
}

$requests_query .= " GROUP BY mp.id ORDER BY mp.created_at DESC";

$requests_result = $conn->query($requests_query);

$counts_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM manual_payments
";
$counts_result = $conn->query($counts_query);
$counts = $counts_result->fetch_assoc();

$conn->close();
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Payments</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Review and verify payment requests from customers</p>
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
           style="padding: 12px 25px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-clock"></i> Payment Requests
            <?php if ($counts['pending'] > 0): ?>
            <span style="background: #f39c12; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><?php echo $counts['pending']; ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=admin/payment-methods" 
           style="padding: 12px 25px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;"
           onmouseover="this.style.background='rgba(52,152,219,0.2)'"
           onmouseout="this.style.background='rgba(255,255,255,0.1)'">
            <i class="fas fa-credit-card"></i> Payment Methods
        </a>
    </div>

    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="?page=admin/manage-payments&filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 10px 20px;">
                    <i class="fas fa-clock"></i> Pending (<?php echo $counts['pending']; ?>)
                </a>
                <a href="?page=admin/manage-payments&filter=verified" class="btn <?php echo $filter === 'verified' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 10px 20px;">
                    <i class="fas fa-check-circle"></i> Verified (<?php echo $counts['verified']; ?>)
                </a>
                <a href="?page=admin/manage-payments&filter=rejected" class="btn <?php echo $filter === 'rejected' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 10px 20px;">
                    <i class="fas fa-times-circle"></i> Rejected (<?php echo $counts['rejected']; ?>)
                </a>
                <a href="?page=admin/manage-payments" class="btn <?php echo $filter === '' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 10px 20px;">
                    <i class="fas fa-list"></i> All
                </a>
            </div>
            
            <form method="GET" action="" style="display: flex; gap: 10px;">
                <input type="hidden" name="page" value="admin/manage-payments">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <input type="text" name="search" placeholder="Search by reference, customer, movie..." value="<?php echo htmlspecialchars($search); ?>" 
                       style="padding: 10px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white; min-width: 250px;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
    </div>

    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #3498db; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-credit-card"></i> Payment Requests
            <?php if ($filter === 'pending'): ?>
            <span style="background: #f39c12; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; margin-left: 15px;">Pending</span>
            <?php elseif ($filter === 'verified'): ?>
            <span style="background: #2ecc71; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; margin-left: 15px;">Verified</span>
            <?php elseif ($filter === 'rejected'): ?>
            <span style="background: #e74c3c; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; margin-left: 15px;">Rejected</span>
            <?php endif; ?>
        </h2>
        
        <?php if ($requests_result && $requests_result->num_rows > 0): ?>
            <?php while ($payment = $requests_result->fetch_assoc()): 
                $status_color = '';
                $status_bg = '';
                
                switch($payment['status']) {
                    case 'Pending':
                        $status_color = '#f39c12';
                        $status_bg = 'rgba(243, 156, 18, 0.2)';
                        break;
                    case 'Verified':
                        $status_color = '#2ecc71';
                        $status_bg = 'rgba(46, 204, 113, 0.2)';
                        break;
                    case 'Rejected':
                        $status_color = '#e74c3c';
                        $status_bg = 'rgba(231, 76, 60, 0.2)';
                        break;
                }
                
                $seat_list = $payment['seat_list'] ?? 'No seats assigned';
                $total_seats = $payment['total_seats'] ?? 0;
            ?>
            <div style="background: rgba(255, 255, 255, 0.03); border-radius: 12px; padding: 25px; margin-bottom: 20px; border: 1px solid rgba(52, 152, 219, 0.2);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas <?php echo $payment['status'] == 'Pending' ? 'fa-clock' : ($payment['status'] == 'Verified' ? 'fa-check-circle' : 'fa-times-circle'); ?>"></i>
                            <?php echo $payment['status']; ?>
                        </span>
                        <span style="color: #3498db; font-size: 1.1rem; font-weight: 700;">
                            <i class="fas fa-hashtag"></i> <?php echo $payment['booking_reference']; ?>
                        </span>
                        <span style="color: rgba(255,255,255,0.6);">
                            <i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($payment['created_at'])); ?>
                        </span>
                    </div>
                    
                    <?php if ($payment['status'] === 'Pending'): ?>
                    <button onclick="openPaymentModal(<?php echo htmlspecialchars(json_encode($payment)); ?>)" 
                            class="btn btn-primary" style="padding: 10px 25px;">
                        <i class="fas fa-check-circle"></i> Review Payment
                    </button>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h4 style="color: #3498db; font-size: 1rem; margin-bottom: 10px;">Customer Information</h4>
                        <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px;">
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-user" style="color: #3498db; width: 20px;"></i> 
                                <?php echo htmlspecialchars($payment['customer_name']); ?>
                            </p>
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-envelope" style="color: #3498db; width: 20px;"></i> 
                                <?php echo htmlspecialchars($payment['customer_email']); ?>
                            </p>
                        </div>
                    </div>

                    <div>
                        <h4 style="color: #3498db; font-size: 1rem; margin-bottom: 10px;">Booking Details</h4>
                        <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px;">
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-film" style="color: #3498db; width: 20px;"></i> 
                                <?php echo htmlspecialchars($payment['movie_name']); ?>
                            </p>
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-calendar" style="color: #3498db; width: 20px;"></i> 
                                <?php echo date('M d, Y', strtotime($payment['show_date'])); ?> at <?php echo date('h:i A', strtotime($payment['showtime'])); ?>
                            </p>
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-chair" style="color: #3498db; width: 20px;"></i> 
                                <?php echo htmlspecialchars($seat_list); ?> (<?php echo $total_seats; ?> seat(s))
                            </p>
                            <p style="color: #2ecc71; font-weight: 700;">
                                <i class="fas fa-tag" style="color: #2ecc71; width: 20px;"></i> 
                                ₱<?php echo number_format($payment['amount'], 2); ?>
                            </p>
                        </div>
                    </div>

                    <div>
                        <h4 style="color: #3498db; font-size: 1rem; margin-bottom: 10px;">Payment Details</h4>
                        <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px;">
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-mobile-alt" style="color: #3498db; width: 20px;"></i> 
                                <?php echo $payment['method_name']; ?>
                            </p>
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-user-circle" style="color: #3498db; width: 20px;"></i> 
                                <?php echo $payment['account_name']; ?>
                            </p>
                            <p style="color: white; margin-bottom: 5px;">
                                <i class="fas fa-hashtag" style="color: #3498db; width: 20px;"></i> 
                                <?php echo $payment['account_number']; ?>
                            </p>
                            <p style="color: white;">
                                <i class="fas fa-qrcode" style="color: #3498db; width: 20px;"></i> 
                                Ref: <?php echo $payment['reference_number']; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4 style="color: #3498db; font-size: 1rem; margin-bottom: 10px;">Payment Screenshot</h4>
                        <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                            <?php if (!empty($payment['screenshot_path']) && file_exists($root_dir . '/' . $payment['screenshot_path'])): ?>
                                <a href="<?php echo SITE_URL . $payment['screenshot_path']; ?>" target="_blank">
                                    <img src="<?php echo SITE_URL . $payment['screenshot_path']; ?>" 
                                         alt="Payment Screenshot"
                                         style="max-width: 100%; max-height: 200px; border-radius: 8px; cursor: pointer; border: 2px solid rgba(52,152,219,0.3);"
                                         onmouseover="this.style.transform='scale(1.02)';"
                                         onmouseout="this.style.transform='scale(1)';">
                                </a>
                                <p style="color: #3498db; margin-top: 10px;">
                                    <i class="fas fa-external-link-alt"></i> Click image to view full size
                                </p>
                            <?php else: ?>
                                <p style="color: rgba(255,255,255,0.6);">
                                    <i class="fas fa-times-circle"></i> Screenshot not available
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <h4 style="color: #3498db; font-size: 1rem; margin-bottom: 10px;">Admin Notes</h4>
                        <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; min-height: 100px;">
                            <?php if (!empty($payment['admin_notes'])): ?>
                                <p style="color: white; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($payment['admin_notes'])); ?></p>
                                <?php if ($payment['verified_by_name']): ?>
                                <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 10px;">
                                    <i class="fas fa-user-check"></i> Verified by: <?php echo $payment['verified_by_name']; ?> 
                                    at <?php echo date('M d, Y h:i A', strtotime($payment['verified_at'])); ?>
                                </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p style="color: rgba(255,255,255,0.4); font-style: italic;">No admin notes yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; color: rgba(255,255,255,0.6);">
                <i class="fas fa-credit-card fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                <p style="font-size: 1.2rem;">No payment requests found</p>
                <p style="font-size: 0.95rem;">There are no <?php echo $filter; ?> payment requests to display.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Review Payment Modal -->
<div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; justify-content: center; align-items: center; padding: 20px; overflow-y: auto;">
    <div style="background: #2c3e50; border-radius: 20px; padding: 30px; max-width: 800px; width: 100%; border: 1px solid rgba(52,152,219,0.3); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #3498db;">
            <h3 style="color: #3498db; font-size: 1.5rem; font-weight: 700;">
                <i class="fas fa-check-circle"></i> Review Payment
            </h3>
            <button onclick="closeModal()" style="background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">&times;</button>
        </div>

        <div id="modalContent"></div>

        <form method="POST" action="" id="reviewForm" style="margin-top: 20px;">
            <input type="hidden" name="payment_id" id="modalPaymentId">
            <input type="hidden" name="booking_id" id="modalBookingId">
            <input type="hidden" name="update_payment_status" value="1">

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">Decision</label>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 10px; color: white; cursor: pointer; padding: 10px 20px; background: rgba(46,204,113,0.1); border-radius: 8px; border: 2px solid transparent;" 
                           onmouseover="this.style.borderColor='#2ecc71'" 
                           onmouseout="this.style.borderColor='transparent'">
                        <input type="radio" name="status" value="Verified" required style="width: 18px; height: 18px; accent-color: #2ecc71;">
                        <i class="fas fa-check-circle" style="color: #2ecc71;"></i>
                        <span>Verify Payment (Mark as Paid)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; color: white; cursor: pointer; padding: 10px 20px; background: rgba(231,76,60,0.1); border-radius: 8px; border: 2px solid transparent;"
                           onmouseover="this.style.borderColor='#e74c3c'" 
                           onmouseout="this.style.borderColor='transparent'">
                        <input type="radio" name="status" value="Rejected" required style="width: 18px; height: 18px; accent-color: #e74c3c;">
                        <i class="fas fa-times-circle" style="color: #e74c3c;"></i>
                        <span>Reject Payment (Cancel Booking)</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">Admin Notes</label>
                <textarea name="admin_notes" rows="4" style="width: 100%; padding: 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 10px; color: white; font-size: 1rem; resize: vertical;" 
                          placeholder="Add notes about this payment (optional)"></textarea>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
                <button type="submit" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                    <i class="fas fa-save"></i> Submit Decision
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="padding: 15px 40px; font-size: 1.1rem;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
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

    input:focus, select:focus, textarea:focus {
        outline: none;
        background: rgba(255,255,255,0.12);
        border-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52,152,219,0.2);
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
function openPaymentModal(payment) {
    document.getElementById('modalPaymentId').value = payment.id;
    document.getElementById('modalBookingId').value = payment.booking_id;
    
    const modalContent = document.getElementById('modalContent');
    modalContent.innerHTML = `
        <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <p style="color: #3498db; font-weight: 600; margin-bottom: 5px;">Customer</p>
                    <p style="color: white;">${payment.customer_name}</p>
                    <p style="color: rgba(255,255,255,0.7);">${payment.customer_email}</p>
                </div>
                <div>
                    <p style="color: #3498db; font-weight: 600; margin-bottom: 5px;">Booking Reference</p>
                    <p style="color: white; font-weight: 700;">${payment.booking_reference}</p>
                    <p style="color: rgba(255,255,255,0.7);">Amount: ₱${parseFloat(payment.amount).toFixed(2)}</p>
                </div>
            </div>
            
            <div style="background: rgba(52,152,219,0.1); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <p style="color: white; margin-bottom: 10px;"><i class="fas fa-info-circle" style="color: #3498db;"></i> <strong>Payment Details:</strong></p>
                <p style="color: rgba(255,255,255,0.9);">Method: ${payment.method_name}</p>
                <p style="color: rgba(255,255,255,0.9);">Account: ${payment.account_name} (${payment.account_number})</p>
                <p style="color: rgba(255,255,255,0.9);">Reference: ${payment.reference_number}</p>
                <p style="color: rgba(255,255,255,0.9);">Submitted: ${new Date(payment.created_at).toLocaleString()}</p>
            </div>
            
            <div style="text-align: center;">
                <p style="color: #3498db; font-weight: 600; margin-bottom: 10px;">Payment Screenshot</p>
                ${payment.screenshot_path ? 
                    `<a href="<?php echo SITE_URL; ?>${payment.screenshot_path}" target="_blank">
                        <img src="<?php echo SITE_URL; ?>${payment.screenshot_path}" 
                             alt="Payment Screenshot"
                             style="max-width: 100%; max-height: 300px; border-radius: 10px; border: 3px solid rgba(52,152,219,0.3);">
                    </a>` : 
                    '<p style="color: rgba(255,255,255,0.5);">No screenshot available</p>'
                }
            </div>
        </div>
    `;
    
    document.getElementById('paymentModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target == modal) {
        closeModal();
    }
}

document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
    const status = document.querySelector('input[name="status"]:checked');
    if (!status) {
        e.preventDefault();
        alert('Please select a decision (Verify or Reject)');
        return false;
    }
    
    if (status.value === 'Rejected') {
        if (!confirm('Warning: Rejecting this payment will cancel the booking and release the seats. Continue?')) {
            e.preventDefault();
            return false;
        }
    } else {
        if (!confirm('Verify this payment? The booking will be marked as paid.')) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});
</script>

</div>
</body>
</html>