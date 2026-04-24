<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Booking ID required']);
    exit();
}

$booking_id = intval($_POST['booking_id']);

$conn = get_db_connection();

$stmt = $conn->prepare("
    SELECT b.*, 
           GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
           COUNT(bs.id) as total_seats,
           u.u_name as customer_name,
           u.u_email as customer_email
    FROM tbl_booking b
    LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
    LEFT JOIN users u ON b.u_id = u.u_id
    WHERE b.b_id = ?
    GROUP BY b.b_id
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $booking = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'booking_reference' => $booking['booking_reference'],
        'customer_name' => $booking['customer_name'],
        'customer_email' => $booking['customer_email'],
        'movie_name' => $booking['movie_name'],
        'show_date' => $booking['show_date'],
        'show_time' => date('h:i A', strtotime($booking['showtime'])),
        'seat_list' => $booking['seat_list'],
        'total_seats' => $booking['total_seats'],
        'booking_fee' => $booking['booking_fee'],
        'attendance_status' => $booking['attendance_status']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
}

$stmt->close();
$conn->close();
?>