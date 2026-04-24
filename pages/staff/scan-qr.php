<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/staff-header.php';

$conn = get_db_connection();
$staff_id = $_SESSION['user_id'];
$error = '';
$success = '';
$booking_data = null;
$scanned_ref = '';

// Handle QR code scan submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['qr_data'])) {
        $qr_data = $_POST['qr_data'];
        $decoded = json_decode($qr_data, true);
        
        if ($decoded && isset($decoded['booking_ref'])) {
            $scanned_ref = $decoded['booking_ref'];
            
            // Fetch booking details
            $stmt = $conn->prepare("
                SELECT b.*, 
                       GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
                       COUNT(bs.id) as total_seats,
                       u.u_name as customer_name,
                       u.u_email as customer_email
                FROM tbl_booking b
                LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
                LEFT JOIN users u ON b.u_id = u.u_id
                WHERE b.booking_reference = ? AND b.payment_status = 'Paid'
                GROUP BY b.b_id
            ");
            $stmt->bind_param("s", $scanned_ref);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $booking_data = $result->fetch_assoc();
                
                if ($booking_data['attendance_status'] == 'Pending') {
                    $success = "Booking verified successfully! Ready for check-in.";
                } elseif ($booking_data['attendance_status'] == 'Present') {
                    $error = "Customer has already checked in.";
                } elseif ($booking_data['attendance_status'] == 'Completed') {
                    $error = "This booking has already been completed.";
                }
            } else {
                $error = "Invalid QR code or booking not paid.";
            }
            $stmt->close();
        } else {
            $error = "Invalid QR code format.";
        }
    }
    
    // Handle check-in
    if (isset($_POST['check_in']) && isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        
        $check_stmt = $conn->prepare("
            SELECT attendance_status, payment_status FROM tbl_booking WHERE b_id = ?
        ");
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $booking_check = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($booking_check && $booking_check['payment_status'] == 'Paid' && $booking_check['attendance_status'] == 'Pending') {
            $update_stmt = $conn->prepare("
                UPDATE tbl_booking 
                SET attendance_status = 'Present', verified_at = NOW(), verified_by = ?
                WHERE b_id = ?
            ");
            $update_stmt->bind_param("ii", $staff_id, $booking_id);
            
            if ($update_stmt->execute()) {
                // Log staff activity
                $log_stmt = $conn->prepare("
                    INSERT INTO staff_activity_log (staff_id, action, booking_id, details)
                    VALUES (?, 'CHECK_IN', ?, ?)
                ");
                $details = "Checked in customer via QR scan";
                $log_stmt->bind_param("iis", $staff_id, $booking_id, $details);
                $log_stmt->execute();
                $log_stmt->close();
                
                $success = "Customer checked in successfully! Physical ticket issued.";
                $booking_data = null;
                $scanned_ref = '';
            } else {
                $error = "Failed to check in customer.";
            }
            $update_stmt->close();
        } else {
            $error = "Booking cannot be checked in at this time.";
        }
    }
}

$conn->close();
?>

<div class="staff-container" style="max-width: 900px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: white; font-size: 2rem; margin-bottom: 10px;">
            <i class="fas fa-qrcode"></i> Scan QR Code
        </h1>
        <p style="color: var(--pale-red);">Position the QR code in front of your camera or enter manually</p>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(231, 76, 60, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(231, 76, 60, 0.3);">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3);">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Camera Scanner Section -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(46, 204, 113, 0.2);">
        <h2 style="color: white; margin-bottom: 20px;">
            <i class="fas fa-camera"></i> Camera Scanner
        </h2>
        <div style="text-align: center;">
            <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
            <p style="color: var(--pale-red); margin-top: 15px; font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> Allow camera access when prompted
            </p>
            <button id="startScanner" style="margin-top: 15px; padding: 10px 25px; background: #2ecc71; color: white; border: none; border-radius: 8px; cursor: pointer; display: none;">
                <i class="fas fa-play"></i> Start Scanner
            </button>
        </div>
    </div>

    <!-- Manual Entry Form -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(46, 204, 113, 0.2);">
        <h2 style="color: white; margin-bottom: 20px;">Or Enter Booking Reference Manually</h2>
        <form method="POST" action="" id="manualForm">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <input type="text" name="qr_data" id="bookingRef" placeholder="Enter Booking Reference" value="<?php echo htmlspecialchars($scanned_ref); ?>"
                       style="flex: 1; padding: 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 10px; color: white; font-size: 1rem;">
                <button type="submit" style="padding: 15px 30px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-search"></i> Verify
                </button>
            </div>
        </form>
    </div>

    <!-- Booking Details Display -->
    <?php if ($booking_data): ?>
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 30px; border: 2px solid #2ecc71;">
        <h2 style="color: #2ecc71; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> Booking Verified
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 25px;">
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Booking Reference</div>
                <div style="color: white; font-size: 1.2rem; font-weight: 700;"><?php echo $booking_data['booking_reference']; ?></div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Customer Name</div>
                <div style="color: white; font-size: 1.2rem; font-weight: 700;"><?php echo htmlspecialchars($booking_data['customer_name']); ?></div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Customer Email</div>
                <div style="color: white;"><?php echo htmlspecialchars($booking_data['customer_email']); ?></div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Movie</div>
                <div style="color: white;"><?php echo htmlspecialchars($booking_data['movie_name']); ?></div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Show Date & Time</div>
                <div style="color: white;"><?php echo date('M d, Y', strtotime($booking_data['show_date'])); ?> at <?php echo date('h:i A', strtotime($booking_data['showtime'])); ?></div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Seats</div>
                <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($booking_data['seat_list']); ?></div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Total Seats</div>
                <div style="color: white;"><?php echo $booking_data['total_seats']; ?> seat(s)</div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Total Amount</div>
                <div style="color: #2ecc71; font-size: 1.3rem; font-weight: 800;">₱<?php echo number_format($booking_data['booking_fee'], 2); ?></div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Payment Status</div>
                <div style="color: #2ecc71; font-weight: 600;">✓ Paid</div>
            </div>
            <div>
                <div style="color: var(--pale-red); font-size: 0.9rem;">Attendance Status</div>
                <div style="color: #f39c12; font-weight: 600;">⏳ Pending Check-in</div>
            </div>
        </div>
        
        <form method="POST" action="" onsubmit="return confirm('Issue physical ticket for this customer?')">
            <input type="hidden" name="booking_id" value="<?php echo $booking_data['b_id']; ?>">
            <input type="hidden" name="check_in" value="1">
            <input type="hidden" name="qr_data" value='<?php echo json_encode(['booking_ref' => $booking_data['booking_reference']]); ?>'>
            <button type="submit" style="width: 100%; padding: 15px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer;">
                <i class="fas fa-ticket-alt"></i> Issue Physical Ticket & Check In
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Include HTML5 QR Code Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
// Initialize QR Scanner
let html5QrCode = null;

function startScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            initScanner();
        }).catch(err => {
            console.error("Error stopping scanner:", err);
            initScanner();
        });
    } else {
        initScanner();
    }
}

function initScanner() {
    html5QrCode = new Html5Qrcode("reader");
    const qrCodeSuccessCallback = (decodedText, decodedResult) => {
        // Stop scanning
        html5QrCode.stop();
        
        // Process the QR code data
        try {
            const qrData = JSON.parse(decodedText);
            if (qrData.booking_ref) {
                // Submit the form with the QR data
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'qr_data';
                input.value = decodedText;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Invalid QR code format. Please scan a valid booking QR code.');
                startScanner();
            }
        } catch (e) {
            alert('Invalid QR code format. Please scan a valid booking QR code.');
            startScanner();
        }
    };
    
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    
    html5QrCode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback)
        .catch(err => {
            console.error("Unable to start scanning:", err);
            document.getElementById('reader').innerHTML = '<p style="color: #e74c3c;">Camera access denied or not available. Please use manual entry.</p>';
        });
}

document.getElementById('startScanner')?.addEventListener('click', startScanner);

// Auto-start scanner on page load
document.addEventListener('DOMContentLoaded', function() {
    startScanner();
});

document.getElementById('manualForm')?.addEventListener('submit', function(e) {
    const ref = document.getElementById('bookingRef').value.trim();
    if (!ref) {
        e.preventDefault();
        alert('Please enter a booking reference');
        return false;
    }
    
    const qrData = JSON.stringify({ booking_ref: ref });
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'qr_data';
    hiddenInput.value = qrData;
    this.appendChild(hiddenInput);
    
    return true;
});
</script>

