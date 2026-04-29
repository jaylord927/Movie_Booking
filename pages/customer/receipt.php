<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$conn = get_db_connection();
$user_id = $_SESSION['user_id'];

// Get booking details with venue, screen, and movie information using normalized schema
$booking_stmt = $conn->prepare("
    SELECT 
        b.id,
        b.booking_reference,
        b.total_amount,
        b.payment_status,
        b.attendance_status,
        b.status,
        b.booked_at,
        b.verified_at,
        s.id as schedule_id,
        s.show_date,
        s.showtime,
        s.base_price,
        m.id as movie_id,
        m.title as movie_title,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating,
        m.description,
        sc.id as screen_id,
        sc.screen_name,
        sc.screen_number,
        v.id as venue_id,
        v.venue_name,
        v.venue_location,
        v.google_maps_link,
        v.venue_photo_path,
        v.contact_number,
        v.operating_hours,
        GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        GROUP_CONCAT(DISTINCT st.name ORDER BY bs.seat_number SEPARATOR ', ') as seat_types,
        COUNT(DISTINCT bs.id) as total_seats,
        SUM(DISTINCT bs.price) as calculated_total,
        a.u_name as verified_by_name,
        u.u_name as customer_name,
        u.u_email as customer_email
    FROM bookings b
    JOIN users u ON b.user_id = u.u_id
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    LEFT JOIN booked_seats bs ON b.id = bs.booking_id
    LEFT JOIN seat_types st ON bs.seat_type_id = st.id
    LEFT JOIN users a ON b.verified_by = a.u_id
    WHERE b.id = ? AND b.user_id = ? AND b.payment_status = 'paid'
    GROUP BY b.id
");
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $conn->close();
    header("Location: " . SITE_URL . "index.php?page=customer/my-bookings");
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();

// Calculate additional info
$booking_date = date('F d, Y', strtotime($booking['booked_at']));
$booking_time = date('h:i A', strtotime($booking['booked_at']));
$show_date = date('l, F d, Y', strtotime($booking['show_date']));
$show_time = date('h:i A', strtotime($booking['showtime']));
$seat_count = $booking['total_seats'] ?? 0;
$seat_list = $booking['seat_list'] ?? 'No seats assigned';
$is_paid = $booking['payment_status'] == 'paid';
$attendance_status = $booking['attendance_status'] ?? 'Pending';

// Prepare QR code data
$qr_text = $booking['booking_reference'];
$booking_ref = $booking['booking_reference'];

// Generate embed URL from Google Maps link
$embed_url = '';
$has_valid_map = false;

if (!empty($booking['google_maps_link'])) {
    if (preg_match('/q=([0-9.-]+),([0-9.-]+)/', $booking['google_maps_link'], $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
        $embed_url = "https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed";
        $has_valid_map = true;
    } elseif (preg_match('/@([0-9.-]+),([0-9.-]+)/', $booking['google_maps_link'], $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
        $embed_url = "https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed";
        $has_valid_map = true;
    } else {
        $embed_url = "https://maps.google.com/maps?q=" . urlencode($booking['venue_location'] ?? $booking['venue_name'] ?? '') . "&z=15&output=embed";
        $has_valid_map = true;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt - Movie Ticketing System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .receipt-wrapper {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
        }
        
        .receipt-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
        }
        
        .receipt-header {
            background: linear-gradient(135deg, #e23020 0%, #c11b18 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .receipt-header h1 {
            font-size: 2rem;
            margin-bottom: 5px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .receipt-header p {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .receipt-body {
            padding: 40px;
        }
        
        .receipt-title {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .receipt-title h2 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .receipt-title p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px dashed #ddd;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .info-group h3 {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .info-group .info-value {
            color: #333;
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .info-group .payment-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            background: <?php echo $is_paid ? '#2ecc71' : '#e74c3c'; ?>;
            color: white;
        }
        
        .attendance-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            background: <?php echo $attendance_status == 'present' ? '#2ecc71' : ($attendance_status == 'completed' ? '#3498db' : '#f39c12'); ?>;
            color: white;
        }
        
        .movie-details {
            display: flex;
            gap: 25px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            flex-wrap: wrap;
        }
        
        .movie-poster {
            width: 100px;
            height: 140px;
            background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .movie-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .movie-poster i {
            font-size: 2.5rem;
            color: rgba(0, 0, 0, 0.2);
        }
        
        .movie-info {
            flex: 1;
        }
        
        .movie-info h3 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .movie-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .movie-meta span {
            background: rgba(226, 48, 32, 0.1);
            color: #e23020;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .qr-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
            border: 2px solid #e23020;
        }
        
        .qr-section h4 {
            color: #e23020;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .qr-code {
            display: inline-block;
            padding: 10px;
            background: white;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .qr-code canvas, .qr-code img {
            width: 150px;
            height: 150px;
        }
        
        #qrcode {
            display: flex;
            justify-content: center;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
        }
        
        .detail-card h4 {
            color: #e23020;
            font-size: 1rem;
            margin-bottom: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-item .label {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 3px;
        }
        
        .detail-item .value {
            color: #333;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .venue-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #e23020;
        }
        
        .venue-section h4 {
            color: #e23020;
            font-size: 1rem;
            margin-bottom: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .venue-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .venue-info {
            flex: 2;
        }
        
        .venue-photo {
            flex: 1;
            text-align: center;
        }
        
        .venue-photo img {
            max-width: 100%;
            max-height: 100px;
            border-radius: 8px;
            border: 2px solid rgba(226, 48, 32, 0.3);
        }
        
        .venue-address {
            color: #333;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .venue-map-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #3498db;
            text-decoration: none;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .venue-map-link:hover {
            text-decoration: underline;
        }
        
        .price-summary {
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .price-summary h4 {
            color: #e23020;
            font-size: 1.1rem;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ddd;
            color: #666;
        }
        
        .price-row.total {
            border-bottom: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 3px double #ddd;
            font-size: 1.1rem;
            font-weight: 800;
            color: #333;
        }
        
        .price-row.total .amount {
            color: #e23020;
        }
        
        .terms-section {
            border-top: 2px dashed #ddd;
            padding-top: 20px;
            margin-bottom: 30px;
        }
        
        .terms-section h4 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .terms-section ul {
            color: #666;
            font-size: 0.8rem;
            line-height: 1.6;
            padding-left: 20px;
        }
        
        .receipt-footer {
            text-align: center;
            color: #666;
            font-size: 0.8rem;
            border-top: 2px solid #e23020;
            padding-top: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #e23020 0%, #c11b18 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #c11b18 0%, #a80f0f 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(226, 48, 32, 0.4);
        }
        
        .btn-secondary {
            background: #3498db;
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        
        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }
        
        .btn-close {
            background: #95a5a6;
            color: white;
        }
        
        .btn-close:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        @media print {
            .action-buttons, .qr-section .btn {
                display: none;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .receipt-wrapper {
                max-width: 100%;
            }
            
            .receipt-container {
                box-shadow: none;
            }
        }
        
        @media (max-width: 768px) {
            .receipt-body {
                padding: 20px;
            }
            
            .movie-details {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .receipt-info {
                flex-direction: column;
                text-align: center;
            }
            
            .venue-details {
                flex-direction: column;
            }
            
            .venue-photo {
                order: -1;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-wrapper">
        <div class="receipt-container" id="receiptContent">
            <div class="receipt-header">
                <h1>
                    <span>🎬</span>
                    <span>MovieTicketBooking</span>
                </h1>
                <p>Ward II, Minglanilla, Cebu</p>
                <p>09267630945 | BSIT@movieticketing.com</p>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-title">
                    <h2>BOOKING RECEIPT</h2>
                    <p><?php echo $booking_date . ' at ' . $booking_time; ?></p>
                </div>
                
                <div class="receipt-info">
                    <div class="info-group">
                        <h3>Booking Reference</h3>
                        <div class="info-value" id="bookingReference"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                    </div>
                    <div class="info-group">
                        <h3>Payment Status</h3>
                        <span class="payment-badge"><?php echo ucfirst($booking['payment_status']); ?></span>
                    </div>
                    <div class="info-group">
                        <h3>Attendance Status</h3>
                        <span class="attendance-badge">
                            <?php echo $attendance_status == 'present' ? '✓ Checked In' : ($attendance_status == 'completed' ? 'Completed' : 'Pending'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- QR Code Section -->
                <div class="qr-section">
                    <h4><i class="fas fa-qrcode"></i> Scan for Entry</h4>
                    <div class="qr-code">
                        <div id="qrcode"></div>
                    </div>
                    <p style="color: #666; font-size: 0.8rem; margin-top: 10px;">
                        Present this QR code at the cinema entrance for verification
                    </p>
                    <p style="color: #e23020; font-size: 0.75rem; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Booking Reference: <strong><?php echo $booking['booking_reference']; ?></strong>
                    </p>
                </div>
                
                <!-- Movie Details -->
                <div class="movie-details">
                    <div class="movie-poster">
                        <?php if (!empty($booking['poster_url'])): ?>
                            <img src="<?php echo htmlspecialchars($booking['poster_url']); ?>" alt="<?php echo htmlspecialchars($booking['movie_title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-film"></i>
                        <?php endif; ?>
                    </div>
                    <div class="movie-info">
                        <h3><?php echo htmlspecialchars($booking['movie_title']); ?></h3>
                        <div class="movie-meta">
                            <span><i class="fas fa-star"></i> <?php echo $booking['rating'] ?: 'PG'; ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo $booking['duration']; ?></span>
                            <?php if (!empty($booking['genre'])): ?>
                            <span><i class="fas fa-film"></i> <?php echo htmlspecialchars($booking['genre']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Details Grid -->
                <div class="details-grid">
                    <div class="detail-card">
                        <h4><i class="fas fa-calendar-alt"></i> Show Information</h4>
                        <div class="detail-item">
                            <div class="label">Date</div>
                            <div class="value"><?php echo $show_date; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Time</div>
                            <div class="value"><?php echo $show_time; ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4><i class="fas fa-chair"></i> Seat Information</h4>
                        <div class="detail-item">
                            <div class="label">Selected Seats</div>
                            <div class="value"><?php echo htmlspecialchars($seat_list); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Total Seats</div>
                            <div class="value"><?php echo $seat_count; ?> seat(s)</div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Screen</div>
                            <div class="value"><?php echo htmlspecialchars($booking['screen_name']); ?> (Screen #<?php echo $booking['screen_number']; ?>)</div>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4><i class="fas fa-user"></i> Customer Information</h4>
                        <div class="detail-item">
                            <div class="label">Name</div>
                            <div class="value"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Email</div>
                            <div class="value"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($booking['venue_name'])): ?>
                    <div class="detail-card">
                        <h4><i class="fas fa-map-marker-alt"></i> Venue Information</h4>
                        <div class="detail-item">
                            <div class="label">Venue Name</div>
                            <div class="value"><?php echo htmlspecialchars($booking['venue_name']); ?></div>
                        </div>
                        <?php if (!empty($booking['venue_location'])): ?>
                        <div class="detail-item">
                            <div class="label">Location</div>
                            <div class="value"><?php echo htmlspecialchars($booking['venue_location']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['google_maps_link'])): ?>
                        <div class="detail-item">
                            <div class="label">Directions</div>
                            <div class="value">
                                <a href="<?php echo htmlspecialchars($booking['google_maps_link']); ?>" target="_blank" 
                                   style="color: #3498db; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-map-marked-alt"></i> Open in Google Maps
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['contact_number'])): ?>
                        <div class="detail-item">
                            <div class="label">Contact</div>
                            <div class="value"><?php echo htmlspecialchars($booking['contact_number']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Venue Photo Section -->
                <?php if (!empty($booking['venue_photo_path'])): ?>
                <div class="venue-section">
                    <h4><i class="fas fa-camera"></i> Venue Photo</h4>
                    <div class="venue-details">
                        <div class="venue-info">
                            <p class="venue-address">
                                <strong><?php echo htmlspecialchars($booking['venue_name']); ?></strong><br>
                                <?php echo htmlspecialchars($booking['venue_location']); ?>
                            </p>
                            <?php if (!empty($booking['operating_hours'])): ?>
                            <p style="color: #666; font-size: 0.8rem; margin-top: 5px;">
                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($booking['operating_hours']); ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($booking['google_maps_link'])): ?>
                            <a href="<?php echo htmlspecialchars($booking['google_maps_link']); ?>" target="_blank" class="venue-map-link">
                                <i class="fas fa-map-marked-alt"></i> Get Directions
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="venue-photo">
                            <img src="<?php echo SITE_URL . $booking['venue_photo_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($booking['venue_name']); ?> Photo"
                                 onclick="window.open(this.src, '_blank')"
                                 style="cursor: pointer;">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Embedded Map -->
                <?php if ($has_valid_map && !empty($embed_url)): ?>
                <div style="margin-bottom: 30px;">
                    <h4 style="color: #e23020; font-size: 1rem; margin-bottom: 10px;"><i class="fas fa-map-marked-alt"></i> Location Map</h4>
                    <div style="position: relative; padding-bottom: 50%; height: 0; overflow: hidden; border-radius: 10px; border: 2px solid rgba(226, 48, 32, 0.3);">
                        <iframe 
                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" 
                            src="<?php echo $embed_url; ?>" 
                            allowfullscreen="" 
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Price Summary -->
                <div class="price-summary">
                    <h4>PAYMENT SUMMARY</h4>
                    <div class="price-row">
                        <span>Ticket Price (<?php echo $seat_count; ?> seat(s))</span>
                        <span class="amount">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                    </div>
                    <div class="price-row total">
                        <span>TOTAL AMOUNT PAID</span>
                        <span class="amount">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                    </div>
                </div>
                
                <!-- Terms Section -->
                <div class="terms-section">
                    <h4>IMPORTANT INSTRUCTIONS</h4>
                    <ul>
                        <li>Please present this QR code or Booking Reference at the entrance</li>
                        <li>A staff member will scan your QR code to verify your booking</li>
                        <li>After verification, you will receive a physical ticket</li>
                        <li>Keep your physical ticket for re-entry if you need to step out</li>
                        <li>Please arrive at least 30 minutes before the showtime</li>
                        <li>Latecomers may not be admitted</li>
                        <?php if (!empty($booking['venue_name'])): ?>
                        <li><strong>Venue:</strong> <?php echo htmlspecialchars($booking['venue_name']); ?> - <?php echo htmlspecialchars($booking['venue_location']); ?></li>
                        <?php endif; ?>
                        <?php if (!empty($booking['screen_name'])): ?>
                        <li><strong>Screen:</strong> <?php echo htmlspecialchars($booking['screen_name']); ?> (Screen #<?php echo $booking['screen_number']; ?>)</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="receipt-footer">
                    <p>Thank you for choosing Movie Ticketing System!</p>
                    <p style="margin-top: 8px;">Please present this QR code at the counter for verification</p>
                    <p style="margin-top: 5px; font-style: italic;">Enjoy the show! 🎬</p>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="window.close()" class="btn btn-close">
                <i class="fas fa-times"></i> Close
            </button>
            <button onclick="downloadQRCode()" class="btn btn-success" id="downloadBtn">
                <i class="fas fa-download"></i> Save QR Code
            </button>
            <button onclick="copyBookingReference()" class="btn btn-secondary">
                <i class="fas fa-copy"></i> Copy Reference
            </button>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Receipt
            </button>
        </div>
    </div>
    
    <script>
        // Generate QR code using JavaScript
        const qrText = '<?php echo $qr_text; ?>';
        
        // Create QR code
        const qrcodeContainer = document.getElementById('qrcode');
        new QRCode(qrcodeContainer, {
            text: qrText,
            width: 150,
            height: 150,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // Function to download QR code
        function downloadQRCode() {
            const qrCanvas = document.querySelector('#qrcode canvas');
            if (qrCanvas) {
                const link = document.createElement('a');
                link.download = 'booking_<?php echo $booking['booking_reference']; ?>_qrcode.png';
                link.href = qrCanvas.toDataURL();
                link.click();
            } else {
                alert('QR code not ready yet. Please try again.');
            }
        }
        
        // Copy booking reference
        function copyBookingReference() {
            const reference = document.getElementById('bookingReference').innerText;
            navigator.clipboard.writeText(reference).then(() => {
                alert('Booking Reference copied: ' + reference);
            }).catch(() => {
                prompt('Press Ctrl+C to copy:', reference);
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.close();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>