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
$selected_movie = isset($_GET['movie']) ? urldecode($_GET['movie']) : '';
$selected_schedule = isset($_GET['schedule']) ? intval($_GET['schedule']) : 0;
$error = '';
$success = '';

// Get today's date
$today = date('Y-m-d');
$current_time = date('H:i:s');

// Get movies that have verified (Present) bookings
$movies_stmt = $conn->prepare("
    SELECT DISTINCT 
        b.movie_name,
        b.show_date,
        b.showtime,
        TIME_TO_SEC(TIMEDIFF(b.showtime, NOW())) as seconds_until_show
    FROM tbl_booking b
    WHERE b.show_date = CURDATE() 
    AND b.attendance_status = 'Present'
    AND b.payment_status = 'Paid'
    ORDER BY b.showtime
");
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();
$movies = [];
while ($row = $movies_result->fetch_assoc()) {
    $movies[] = $row;
}
$movies_stmt->close();

// Get verified bookings for selected movie
$bookings = [];
$selected_movie_title = '';
$selected_show_time = '';
$selected_show_date = '';

if ($selected_movie) {
    $selected_movie_title = $selected_movie;
    
    // First get the showtime and date for this movie
    $showtime_stmt = $conn->prepare("
        SELECT DISTINCT showtime, show_date 
        FROM tbl_booking 
        WHERE movie_name = ? AND show_date = CURDATE() AND attendance_status = 'Present'
        LIMIT 1
    ");
    $showtime_stmt->bind_param("s", $selected_movie);
    $showtime_stmt->execute();
    $showtime_result = $showtime_stmt->get_result();
    if ($showtime_data = $showtime_result->fetch_assoc()) {
        $selected_show_time = $showtime_data['showtime'];
        $selected_show_date = $showtime_data['show_date'];
    }
    $showtime_stmt->close();
    
    // Get all verified bookings for this movie with individual seats
    $bookings_stmt = $conn->prepare("
        SELECT 
            b.b_id, 
            b.booking_reference, 
            b.movie_name, 
            b.show_date, 
            b.showtime,
            bs.seat_number,
            bs.seat_type,
            bs.price,
            u.u_name as customer_name,
            u.u_email as customer_email,
            b.attendance_status,
            b.verified_at
        FROM tbl_booking b
        LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
        LEFT JOIN users u ON b.u_id = u.u_id
        WHERE b.movie_name = ? 
        AND b.show_date = CURDATE() 
        AND b.attendance_status = 'Present' 
        AND b.payment_status = 'Paid'
        ORDER BY b.booking_reference, bs.seat_number
    ");
    $bookings_stmt->bind_param("s", $selected_movie);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    
    while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $bookings_stmt->close();
}

// Group bookings by customer
$grouped_bookings = [];
foreach ($bookings as $booking) {
    $key = $booking['booking_reference'];
    if (!isset($grouped_bookings[$key])) {
        $grouped_bookings[$key] = [
            'booking_reference' => $booking['booking_reference'],
            'customer_name' => $booking['customer_name'],
            'customer_email' => $booking['customer_email'],
            'movie_name' => $booking['movie_name'],
            'show_date' => $booking['show_date'],
            'showtime' => $booking['showtime'],
            'verified_at' => $booking['verified_at'],
            'seats' => []
        ];
    }
    $grouped_bookings[$key]['seats'][] = [
        'seat_number' => $booking['seat_number'],
        'seat_type' => $booking['seat_type'],
        'price' => $booking['price']
    ];
}

$conn->close();
?>

<div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
    <h2 style="color: white; font-size: 1.8rem; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-print"></i> Print Tickets
    </h2>
    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 25px;">Select a movie to print verified tickets (customers who have checked in)</p>

    <!-- Movie Selection -->
    <div style="background: rgba(0, 0, 0, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 25px;">
        <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
            <i class="fas fa-film"></i> Select Movie
        </label>
        <form method="GET" action="" id="movieSelectForm">
            <input type="hidden" name="page" value="staff/print-ticket">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <select name="movie" required id="movieSelect" style="flex: 1; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                    <option value="" style="background: #2c3e50;">-- Select a movie --</option>
                    <?php foreach ($movies as $movie): ?>
                    <option value="<?php echo htmlspecialchars($movie['movie_name']); ?>" style="background: #2c3e50;" <?php echo $selected_movie == $movie['movie_name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($movie['movie_name']); ?> - <?php echo date('h:i A', strtotime($movie['showtime'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="padding: 14px 30px; background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-search"></i> Load Tickets
                </button>
            </div>
        </form>
    </div>

    <!-- Tickets Display -->
    <?php if ($selected_movie && !empty($grouped_bookings)): ?>
        <div style="margin-top: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h3 style="color: #2ecc71; font-size: 1.3rem;">
                    <i class="fas fa-check-circle"></i> Verified Tickets - <?php echo htmlspecialchars($selected_movie_title); ?>
                    <span style="font-size: 0.9rem; color: #3498db; margin-left: 10px;"><?php echo date('F d, Y'); ?> | <?php echo date('h:i A', strtotime($selected_show_time)); ?></span>
                </h3>
                <button onclick="printAllTickets()" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-print"></i> Print All Tickets
                </button>
            </div>
            
            <!-- Hand Mark Sample / Visual Guide - UPDATED with movie title and time -->
            <div style="background: rgba(46, 204, 113, 0.1); border: 2px solid #2ecc71; border-radius: 15px; padding: 20px; margin-bottom: 25px;">
                <div style="display: flex; align-items: center; gap: 25px; flex-wrap: wrap;">
                    <!-- Hand Mark Stamp Sample -->
                    <div style="text-align: center;">
                        <div style="background: #2ecc71; color: #1a1a2e; padding: 15px 25px; border-radius: 12px; font-weight: 800; text-align: center; min-width: 180px; box-shadow: 0 4px 15px rgba(46,204,113,0.3);">
                            <i class="fas fa-hand-peace" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                            <span style="font-size: 1.2rem;">VERIFIED</span>
                            <div style="font-size: 0.75rem; margin-top: 5px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 5px;">
                                <?php echo date('M d, Y', strtotime($selected_show_date)); ?> | <?php echo date('h:i A', strtotime($selected_show_time)); ?>
                            </div>
                            <div style="font-size: 0.7rem; margin-top: 3px; font-style: italic;">
                                <?php echo substr(htmlspecialchars($selected_movie_title), 0, 25); ?>
                            </div>
                        </div>
                        <p style="color: #2ecc71; font-size: 0.8rem; margin-top: 8px;">
                            <i class="fas fa-hand-peace"></i> Hand Mark Sample
                        </p>
                    </div>
                    
                    <!-- Description -->
                    <div style="flex: 1; color: rgba(255,255,255,0.85); font-size: 0.95rem; line-height: 1.5;">
                        <i class="fas fa-info-circle" style="color: #2ecc71; margin-right: 8px;"></i> 
                        <strong>Staff should stamp/mark the hand with this symbol after ticket verification.</strong><br>
                        This serves as proof for re-entry if the customer needs to step out temporarily.
                        <div style="margin-top: 10px; display: flex; gap: 15px; flex-wrap: wrap;">
                            <span style="background: rgba(46,204,113,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                <i class="fas fa-film"></i> <?php echo htmlspecialchars($selected_movie_title); ?>
                            </span>
                            <span style="background: rgba(46,204,113,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($selected_show_time)); ?>
                            </span>
                            <span style="background: rgba(46,204,113,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($selected_show_date)); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php foreach ($grouped_bookings as $booking): ?>
                <div class="ticket-group" style="margin-bottom: 30px; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #2ecc71;">
                        <div>
                            <strong style="color: #2ecc71;">Customer:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?><br>
                            <strong style="color: #2ecc71;">Booking Ref:</strong> <?php echo $booking['booking_reference']; ?>
                        </div>
                        <div style="text-align: right;">
                            <strong style="color: #2ecc71;">Verified At:</strong> <?php echo date('h:i A', strtotime($booking['verified_at'])); ?>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <?php foreach ($booking['seats'] as $index => $seat): ?>
                        <div class="ticket-card" id="ticket-<?php echo $booking['booking_reference'] . '-' . $index; ?>" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                            <!-- Ticket Stub (Left side - perforated style) -->
                            <div style="display: flex;">
                                <!-- Stub / Tear-off section -->
                                <div style="width: 80px; background: linear-gradient(135deg, #e23020 0%, #c11b18 100%); color: white; padding: 15px 10px; text-align: center; display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <i class="fas fa-ticket-alt" style="font-size: 1.5rem;"></i>
                                        <div style="font-size: 0.7rem; margin-top: 5px;">ADMIT ONE</div>
                                    </div>
                                    <div style="border-top: 2px dashed rgba(255,255,255,0.3); margin-top: 10px; padding-top: 10px;">
                                        <div style="font-size: 0.7rem;">Seat</div>
                                        <div style="font-size: 1.2rem; font-weight: 800;"><?php echo $seat['seat_number']; ?></div>
                                    </div>
                                </div>
                                
                                <!-- Main Ticket Body -->
                                <div style="flex: 1; padding: 15px;">
                                    <!-- Hand Mark Stamp Area -->
                                    <div style="background: #f0f0f0; border-radius: 8px; padding: 8px; margin-bottom: 12px; text-align: center; border: 2px solid #2ecc71;">
                                        <div style="background: #2ecc71; color: #1a1a2e; padding: 5px 10px; border-radius: 5px; display: inline-block; font-weight: 800; font-size: 0.8rem;">
                                            <i class="fas fa-hand-peace"></i> VERIFIED
                                        </div>
                                        <div style="font-size: 0.7rem; color: #666; margin-top: 5px;">
                                            <?php echo date('M d, Y', strtotime($booking['show_date'])) . ' | ' . date('h:i A', strtotime($booking['showtime'])); ?>
                                        </div>
                                        <div style="font-size: 0.65rem; color: #888; margin-top: 3px;">
                                            <?php echo htmlspecialchars($booking['movie_name']); ?>
                                        </div>
                                    </div>
                                    
                                    <div style="text-align: center;">
                                        <h3 style="color: #e23020; font-size: 1rem; margin-bottom: 5px;"><?php echo htmlspecialchars($booking['movie_name']); ?></h3>
                                        <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 10px; flex-wrap: wrap;">
                                            <span style="background: #3498db; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.7rem;">Seat: <?php echo $seat['seat_number']; ?></span>
                                            <span style="background: <?php echo $seat['seat_type'] == 'Premium' ? '#FFD700' : ($seat['seat_type'] == 'Sweet Spot' ? '#e74c3c' : '#3498db'); ?>; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.7rem;"><?php echo $seat['seat_type']; ?></span>
                                        </div>
                                        <div style="color: #666; font-size: 0.75rem;">
                                            <div><?php echo date('l, F d, Y', strtotime($booking['show_date'])); ?></div>
                                            <div><?php echo date('h:i A', strtotime($booking['showtime'])); ?></div>
                                        </div>
                                        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #ddd; font-size: 0.7rem; color: #999;">
                                            <i class="fas fa-qrcode"></i> <?php echo $booking['booking_reference']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ticket Footer / Terms -->
                            <div style="background: #f8f9fa; padding: 8px; text-align: center; font-size: 0.6rem; color: #666; border-top: 1px solid #ddd;">
                                <i class="fas fa-hand-peace"></i> Hand stamp required for re-entry • No refunds after show starts • Keep ticket for duration
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Print button for this customer's tickets -->
                    <div style="margin-top: 15px; text-align: right;">
                        <button onclick="printCustomerTickets('<?php echo $booking['booking_reference']; ?>')" style="background: #3498db; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-print"></i> Print Tickets for <?php echo htmlspecialchars($booking['customer_name']); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($selected_movie): ?>
        <div style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 10px;">
            <i class="fas fa-check-circle fa-3x" style="color: rgba(46,204,113,0.3); margin-bottom: 15px;"></i>
            <p style="color: rgba(255,255,255,0.6);">No verified tickets found for this movie.</p>
            <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem;">Only customers who have checked in (Present status) will appear here.</p>
        </div>
    <?php elseif (!empty($movies)): ?>
        <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
            <i class="fas fa-hand-pointer fa-3x" style="color: #2ecc71; margin-bottom: 15px;"></i>
            <p style="color: rgba(255,255,255,0.7);">Please select a movie from the dropdown above to view verified tickets.</p>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
            <i class="fas fa-ticket-alt fa-3x" style="color: #f39c12; margin-bottom: 15px;"></i>
            <p style="color: rgba(255,255,255,0.7);">No verified tickets available for today.</p>
            <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem;">Customers need to check in first before tickets can be printed.</p>
        </div>
    <?php endif; ?>
</div>

<style media="print">
    @page {
        size: A4;
        margin: 10mm;
    }
    
    body * {
        visibility: hidden;
    }
    
    .ticket-card, .ticket-card * {
        visibility: visible;
    }
    
    .ticket-card {
        page-break-inside: avoid;
        break-inside: avoid;
        margin-bottom: 15px;
    }
    
    .staff-header, .staff-content > div > div:first-child, .staff-content > div > div:nth-child(2), .staff-content > div > div:last-child > div:first-child, .staff-content > div > div:last-child > div:last-child > button {
        display: none;
    }
    
    .ticket-group {
        margin-bottom: 20px;
        background: none !important;
        padding: 0 !important;
    }
    
    .ticket-group > div:first-child {
        display: none;
    }
    
    .ticket-group > div:last-child {
        display: none;
    }
</style>

<style>
.ticket-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ticket-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

@media (max-width: 768px) {
    .ticket-card {
        min-width: 100%;
    }
    
    .ticket-card > div {
        flex-direction: column;
    }
    
    .ticket-card > div > div:first-child {
        width: 100%;
        flex-direction: row;
        padding: 10px;
    }
    
    .ticket-card > div > div:first-child > div:last-child {
        border-top: none;
        border-left: 2px dashed rgba(255,255,255,0.3);
        margin-top: 0;
        margin-left: 10px;
        padding-top: 0;
        padding-left: 10px;
    }
}

select, button {
    transition: all 0.3s ease;
}

select:focus, button:focus {
    outline: none;
}

select:hover, button:hover {
    transform: translateY(-2px);
}
</style>

<script>
function printAllTickets() {
    window.print();
}

function printCustomerTickets(bookingRef) {
    // Hide all other ticket groups
    const allGroups = document.querySelectorAll('.ticket-group');
    let targetGroup = null;
    
    allGroups.forEach(group => {
        if (group.querySelector('.ticket-card') && group.innerHTML.includes(bookingRef)) {
            targetGroup = group;
        }
    });
    
    if (targetGroup) {
        // Create a print-only clone
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Tickets - ${bookingRef}</title>
                <style>
                    @page {
                        size: A4;
                        margin: 10mm;
                    }
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        padding: 20px;
                        background: white;
                    }
                    .ticket-card {
                        margin-bottom: 20px;
                        page-break-inside: avoid;
                        break-inside: avoid;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    .ticket-card > div {
                        display: flex;
                    }
                    .ticket-card .stub {
                        width: 80px;
                        background: linear-gradient(135deg, #e23020 0%, #c11b18 100%);
                        color: white;
                        padding: 15px 10px;
                        text-align: center;
                    }
                    .ticket-card .main-body {
                        flex: 1;
                        padding: 15px;
                    }
                    @media (max-width: 600px) {
                        .ticket-card > div {
                            flex-direction: column;
                        }
                        .ticket-card .stub {
                            width: 100%;
                            flex-direction: row;
                        }
                    }
                </style>
            </head>
            <body>
                ${targetGroup.outerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(() => { window.close(); }, 500);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
}

document.getElementById('movieSelect')?.addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
    }
});
</script>

