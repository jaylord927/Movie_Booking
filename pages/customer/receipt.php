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

$conn = get_db();
$user_id = $_SESSION['user_id'];

$booking_stmt = $conn->prepare("
    SELECT 
        b.*,
        u.u_name as customer_name,
        u.u_email as customer_email,
        m.id as movie_id,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating,
        m.title as movie_title,
        m.venue_name,
        m.venue_location
    FROM tbl_booking b
    JOIN users u ON b.u_id = u.u_id
    LEFT JOIN movies m ON b.movie_name = m.title
    WHERE b.b_id = ? AND b.u_id = ?
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
$conn->close();

$booking_date = date('F d, Y', strtotime($booking['booking_date']));
$booking_time = date('h:i A', strtotime($booking['booking_date']));
$show_date = date('l, F d, Y', strtotime($booking['show_date']));
$show_time = date('h:i A', strtotime($booking['showtime']));
$seat_count = substr_count($booking['seat_no'], ',') + 1;
$is_paid = $booking['payment_status'] == 'Paid';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt - Movie Ticketing System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            font-size: 2.2rem;
            margin-bottom: 5px;
            font-weight: 800;
        }
        
        .receipt-header p {
            font-size: 0.95rem;
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
            font-size: 0.95rem;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px dashed #ddd;
        }
        
        .info-group h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .info-group .info-value {
            color: #333;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .info-group .payment-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            background: <?php echo $is_paid ? '#2ecc71' : '#e74c3c'; ?>;
            color: white;
        }
        
        .movie-details {
            display: flex;
            gap: 25px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
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
            font-size: 1.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .movie-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .movie-meta span {
            background: rgba(226, 48, 32, 0.1);
            color: #e23020;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
            font-size: 1.1rem;
            margin-bottom: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .detail-item {
            margin-bottom: 12px;
        }
        
        .detail-item .label {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 3px;
        }
        
        .detail-item .value {
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .price-summary {
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .price-summary h4 {
            color: #e23020;
            font-size: 1.2rem;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #ddd;
            color: #666;
        }
        
        .price-row.total {
            border-bottom: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 3px double #ddd;
            font-size: 1.2rem;
            font-weight: 800;
            color: #333;
        }
        
        .price-row.total .amount {
            color: #e23020;
        }
        
        .payment-status {
            text-align: right;
            margin-top: 10px;
            color: #666;
            font-size: 0.95rem;
        }
        
        .payment-status span {
            font-weight: 700;
            color: <?php echo $is_paid ? '#2ecc71' : '#e74c3c'; ?>;
        }
        
        .terms-section {
            border-top: 2px dashed #ddd;
            padding-top: 20px;
            margin-bottom: 30px;
        }
        
        .terms-section h4 {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .terms-section ul {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.6;
            padding-left: 20px;
        }
        
        .receipt-footer {
            text-align: center;
            color: #666;
            font-size: 0.85rem;
            border-top: 2px solid #e23020;
            padding-top: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn {
            padding: 14px 35px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #e23020 0%, #c11b18 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #c11b18 0%, #a80f0f 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(226, 48, 32, 0.4);
        }
        
        .btn-secondary {
            background: #3498db;
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary:hover {
            background: #2980b9;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .btn-close {
            background: #95a5a6;
            color: white;
        }
        
        .btn-close:hover {
            background: #7f8c8d;
            transform: translateY(-3px);
        }
        
        @media print {
            .action-buttons {
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
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-wrapper">
        <div class="receipt-container">
            <div class="receipt-header">
                <h1>MOVIE TICKETING</h1>
                <p>Book Your Favorite Movies</p>
                <p style="margin-top: 5px;">Ward II, Minglanilla, Cebu</p>
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
                        <div class="info-value"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                    </div>
                    <div class="info-group">
                        <h3>Payment Status</h3>
                        <span class="payment-badge"><?php echo htmlspecialchars($booking['payment_status']); ?></span>
                    </div>
                </div>
                
                <div class="movie-details">
                    <div class="movie-poster">
                        <?php if (!empty($booking['poster_url'])): ?>
                            <img src="<?php echo htmlspecialchars($booking['poster_url']); ?>" alt="<?php echo htmlspecialchars($booking['movie_name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-film"></i>
                        <?php endif; ?>
                    </div>
                    <div class="movie-info">
                        <h3><?php echo htmlspecialchars($booking['movie_name']); ?></h3>
                        <div class="movie-meta">
                            <span><i class="fas fa-star"></i> <?php echo htmlspecialchars($booking['rating'] ?: 'PG'); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($booking['duration']); ?></span>
                            <?php if ($booking['genre']): ?>
                            <span><i class="fas fa-film"></i> <?php echo htmlspecialchars($booking['genre']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
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
                            <div class="value"><?php echo htmlspecialchars($booking['seat_no']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Total Seats</div>
                            <div class="value"><?php echo $seat_count; ?> seat(s)</div>
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
                    
                    <?php if (!empty($booking['venue_name']) || !empty($booking['venue_location'])): ?>
                    <div class="detail-card">
                        <h4><i class="fas fa-map-marker-alt"></i> Venue Information</h4>
                        <?php if (!empty($booking['venue_name'])): ?>
                        <div class="detail-item">
                            <div class="label">Venue</div>
                            <div class="value"><?php echo htmlspecialchars($booking['venue_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['venue_location'])): ?>
                        <div class="detail-item">
                            <div class="label">Location</div>
                            <div class="value"><?php echo htmlspecialchars($booking['venue_location']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="price-summary">
                    <h4>PAYMENT SUMMARY</h4>
                    <div class="price-row">
                        <span>Ticket Price (<?php echo $seat_count; ?> seat(s))</span>
                        <span class="amount">₱<?php echo number_format($booking['booking_fee'], 2); ?></span>
                    </div>
                    <div class="price-row">
                        <span>Service Fee</span>
                        <span class="amount">₱0.00</span>
                    </div>
                    <div class="price-row">
                        <span>Tax (Included)</span>
                        <span class="amount">₱0.00</span>
                    </div>
                    <div class="price-row total">
                        <span>TOTAL AMOUNT</span>
                        <span class="amount">₱<?php echo number_format($booking['booking_fee'], 2); ?></span>
                    </div>
                    <div class="payment-status">
                        Payment Status: <span><?php echo htmlspecialchars($booking['payment_status']); ?></span>
                    </div>
                </div>
                
                <div class="terms-section">
                    <h4>TERMS & CONDITIONS</h4>
                    <ul>
                        <li>This ticket is non-transferable and non-refundable</li>
                        <li>Please arrive at least 30 minutes before the showtime</li>
                        <li>Valid ID required for verification</li>
                        <li>Children under 3 years are free (no seat provided)</li>
                        <li>Outside food and drinks are not allowed</li>
                    </ul>
                </div>
                
                <div class="receipt-footer">
                    <p>Thank you for choosing Movie Ticketing System!</p>
                    <p style="margin-top: 10px;">Please present this receipt at the counter</p>
                    <p style="margin-top: 5px; font-style: italic;">Enjoy the show! 🎬</p>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="window.close()" class="btn btn-close">
                <i class="fas fa-times"></i> Close
            </button>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to My Bookings
            </a>
        </div>
    </div>
    
    <script>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.close();
        }
    });
    </script>
</body>
</html>