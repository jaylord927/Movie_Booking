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
$selected_movie_title = '';
$selected_show_date = '';
$selected_show_time = '';

// Get all movies that have verified bookings (Present or Completed)
$movies_list = [];

$movies_stmt = $conn->prepare("
    SELECT DISTINCT b.movie_name, b.showtime, b.show_date
    FROM tbl_booking b
    WHERE b.attendance_status IN ('Present', 'Completed') AND b.payment_status = 'Paid'
    ORDER BY b.show_date DESC, b.showtime DESC, b.movie_name
");
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();

while ($row = $movies_result->fetch_assoc()) {
    // Create unique identifier combining movie name, date, and showtime
    $movie_key = $row['movie_name'] . '|' . $row['show_date'] . '|' . $row['showtime'];
    $movies_list[$movie_key] = [
        'movie_name' => $row['movie_name'],
        'showtime' => $row['showtime'],
        'show_date' => $row['show_date']
    ];
}
$movies_stmt->close();

// Get verified bookings for selected movie
$verified_bookings = [];
if ($selected_movie) {
    // Parse selected movie to get name, date, and time
    $selected_parts = explode('|', $selected_movie);
    $selected_movie_title = $selected_parts[0];
    $selected_show_date = $selected_parts[1] ?? '';
    $selected_show_time = $selected_parts[2] ?? '';
    
    $bookings_stmt = $conn->prepare("
        SELECT b.b_id, b.booking_reference, b.movie_name, b.show_date, b.showtime,
               GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
               COUNT(bs.id) as total_seats,
               SUM(bs.price) as total_price,
               u.u_name as customer_name,
               u.u_email as customer_email,
               b.attendance_status,
               b.verified_at,
               a.u_name as verified_by_name
        FROM tbl_booking b
        LEFT JOIN booked_seats bs ON b.b_id = bs.booking_id
        LEFT JOIN users u ON b.u_id = u.u_id
        LEFT JOIN users a ON b.verified_by = a.u_id
        WHERE b.movie_name = ? AND b.show_date = ? AND b.showtime = ? 
        AND b.attendance_status IN ('Present', 'Completed') AND b.payment_status = 'Paid'
        GROUP BY b.b_id
        ORDER BY b.booking_reference
    ");
    $bookings_stmt->bind_param("sss", $selected_movie_title, $selected_show_date, $selected_show_time);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    
    while ($row = $bookings_result->fetch_assoc()) {
        $verified_bookings[] = $row;
    }
    $bookings_stmt->close();
}

$conn->close();
?>

<div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
    <h2 style="color: white; font-size: 1.8rem; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-history"></i> Verified Bookings History
    </h2>
    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 25px;">View all checked-in and completed bookings</p>

    <!-- Movie Selection - Auto-submit on change -->
    <div style="background: rgba(0, 0, 0, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 25px;">
        <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
            <i class="fas fa-film"></i> Select Movie & Showtime
        </label>
        <form method="GET" action="" id="movieSelectForm">
            <input type="hidden" name="page" value="staff/verify-history">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <select name="movie" required id="movieSelect" style="flex: 1; padding: 14px 16px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 10px; color: white; font-size: 1rem; cursor: pointer;">
                    <option value="" style="background: #2c3e50;">-- Select a movie --</option>
                    <?php 
                    // Sort movies by date and time (newest first)
                    usort($movies_list, function($a, $b) {
                        $dateTimeA = strtotime($a['show_date'] . ' ' . $a['showtime']);
                        $dateTimeB = strtotime($b['show_date'] . ' ' . $b['showtime']);
                        return $dateTimeB - $dateTimeA;
                    });
                    
                    foreach ($movies_list as $key => $movie): 
                        $display_key = $movie['movie_name'] . '|' . $movie['show_date'] . '|' . $movie['showtime'];
                        $is_today = date('Y-m-d') == $movie['show_date'];
                        $show_datetime = strtotime($movie['show_date'] . ' ' . $movie['showtime']);
                        $is_past = $show_datetime < time();
                    ?>
                    <option value="<?php echo htmlspecialchars($display_key); ?>" style="background: #2c3e50;" <?php echo $selected_movie == $display_key ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($movie['movie_name']); ?> - <?php echo date('h:i A', strtotime($movie['showtime'])); ?> (<?php echo date('M d, Y', strtotime($movie['show_date'])); ?>)
                        <?php if ($is_today): ?>
                        (Today)
                        <?php elseif ($is_past): ?>
                        (Past)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        
        <?php if (empty($movies_list)): ?>
        <div style="margin-top: 15px; padding: 12px; background: rgba(241, 196, 15, 0.1); border-left: 4px solid #f39c12; border-radius: 5px;">
            <p style="color: #f39c12; font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> No verified bookings found in the history.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Verified Bookings Display -->
    <?php if ($selected_movie): ?>
        <div style="margin-top: 25px;">
            <h3 style="color: white; font-size: 1.3rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #2ecc71;">
                <i class="fas fa-check-circle"></i> Verified Bookings - <?php echo htmlspecialchars($selected_movie_title); ?>
                <span style="font-size: 0.9rem; color: #2ecc71; margin-left: 10px;"><?php echo date('h:i A', strtotime($selected_show_time)); ?> | <?php echo date('F d, Y', strtotime($selected_show_date)); ?></span>
            </h3>
            
            <!-- Live Search -->
            <div style="margin-bottom: 20px;">
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5);"></i>
                    <input type="text" id="liveSearch" placeholder="Search by booking reference, customer name, or seat number..." 
                           style="width: 100%; padding: 12px 15px 12px 45px; background: rgba(255,255,255,0.08); border: 2px solid rgba(46,204,113,0.3); border-radius: 10px; color: white; font-size: 1rem;">
                </div>
                <p style="color: rgba(255,255,255,0.5); font-size: 0.8rem; margin-top: 8px;">
                    <i class="fas fa-info-circle"></i> Type to filter bookings in real-time
                </p>
            </div>
            
            <?php if (empty($verified_bookings)): ?>
            <div style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <i class="fas fa-check-circle fa-3x" style="color: rgba(46,204,113,0.3); margin-bottom: 15px;"></i>
                <p style="color: rgba(255,255,255,0.6);">No verified bookings found for this movie showtime.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                            <th style="padding: 14px; text-align: left; color: white;">Booking Ref</th>
                            <th style="padding: 14px; text-align: left; color: white;">Customer</th>
                            <th style="padding: 14px; text-align: left; color: white;">Seats</th>
                            <th style="padding: 14px; text-align: left; color: white;">Amount</th>
                            <th style="padding: 14px; text-align: left; color: white;">Status</th>
                            <th style="padding: 14px; text-align: left; color: white;">Verified At</th>
                            <th style="padding: 14px; text-align: left; color: white;">Verified By</th>
                            <th style="padding: 14px; text-align: left; color: white;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php foreach ($verified_bookings as $booking): 
                            $status_text = $booking['attendance_status'] == 'Present' ? 'Present' : 'Completed';
                            $status_color = $booking['attendance_status'] == 'Present' ? '#2ecc71' : '#3498db';
                            $row_data = [
                                'ref' => strtolower($booking['booking_reference']),
                                'customer' => strtolower($booking['customer_name']),
                                'seats' => strtolower($booking['seat_list'] ?? '')
                            ];
                        ?>
                        <tr class="booking-row" data-ref="<?php echo $row_data['ref']; ?>" 
                            data-customer="<?php echo $row_data['customer']; ?>"
                            data-seats="<?php echo $row_data['seats']; ?>"
                            style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 12px; color: white; font-weight: 600;"><?php echo $booking['booking_reference']; ?></td>
                            <td style="padding: 12px; color: white;"><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                            <td style="padding: 12px; color: white;"><?php echo htmlspecialchars($booking['seat_list'] ?? 'N/A'); ?></td>
                            <td style="padding: 12px; color: #2ecc71; font-weight: 600;">₱<?php echo number_format($booking['total_price'] ?? 0, 2); ?></td>
                            <td style="padding: 12px;">
                                <span style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                                    <i class="fas <?php echo $booking['attendance_status'] == 'Present' ? 'fa-check-circle' : 'fa-check-double'; ?>"></i> <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: rgba(255,255,255,0.7);"><?php echo date('M d, h:i A', strtotime($booking['verified_at'])); ?></td>
                            <td style="padding: 12px; color: #2ecc71;"><?php echo htmlspecialchars($booking['verified_by_name'] ?? 'System'); ?></td>
                            <td style="padding: 12px;">
                                <button onclick="viewDetails(<?php echo $booking['b_id']; ?>)" 
                                        style="background: #3498db; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 15px; text-align: center; color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                <span id="filterCount"><?php echo count($verified_bookings); ?></span> verified booking(s) displayed
            </div>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($movies_list)): ?>
        <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
            <i class="fas fa-hand-pointer fa-3x" style="color: #2ecc71; margin-bottom: 15px;"></i>
            <p style="color: rgba(255,255,255,0.7);">Please select a movie from the dropdown above to view verified bookings.</p>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.2); border-radius: 10px;">
            <i class="fas fa-history fa-3x" style="color: #3498db; margin-bottom: 15px;"></i>
            <p style="color: rgba(255,255,255,0.7);">No verified bookings found in the history.</p>
        </div>
    <?php endif; ?>
</div>

<!-- View Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000; justify-content: center; align-items: center; padding: 20px; overflow-y: auto;">
    <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border-radius: 20px; padding: 30px; max-width: 700px; width: 100%; border: 2px solid #3498db;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: #3498db;">Booking Details</h2>
            <button onclick="closeDetailsModal()" style="background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">&times;</button>
        </div>
        <div id="detailsModalContent"></div>
    </div>
</div>

<style>
.booking-row {
    transition: background 0.2s ease;
}
.booking-row:hover {
    background: rgba(46, 204, 113, 0.1);
}

#liveSearch {
    transition: all 0.3s ease;
}

#liveSearch:focus {
    outline: none;
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
}

select, input {
    transition: all 0.3s ease;
}

select:focus, input:focus {
    outline: none;
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
}

@media (max-width: 768px) {
    table {
        font-size: 0.8rem;
    }
    
    th, td {
        padding: 8px !important;
    }
}
</style>

<script>
// Auto-submit when movie selection changes
document.getElementById('movieSelect')?.addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
    }
});

// Live search functionality
const liveSearch = document.getElementById('liveSearch');
const bookingRows = document.querySelectorAll('.booking-row');
const filterCount = document.getElementById('filterCount');

if (liveSearch) {
    liveSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        let visibleCount = 0;
        
        bookingRows.forEach(row => {
            const ref = row.dataset.ref || '';
            const customer = row.dataset.customer || '';
            const seats = row.dataset.seats || '';
            
            if (ref.includes(searchTerm) || customer.includes(searchTerm) || seats.includes(searchTerm) || searchTerm === '') {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (filterCount) {
            filterCount.textContent = visibleCount;
        }
    });
}

function viewDetails(bookingId) {
    fetch('<?php echo SITE_URL; ?>ajax/get-booking-details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'booking_id=' + bookingId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modalContent = document.getElementById('detailsModalContent');
            modalContent.innerHTML = `
                <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div><strong style="color: #3498db;">Booking Reference:</strong><br><span style="color: white;">${data.booking_reference}</span></div>
                        <div><strong style="color: #3498db;">Customer Name:</strong><br><span style="color: white;">${data.customer_name}</span></div>
                        <div><strong style="color: #3498db;">Customer Email:</strong><br><span style="color: white;">${data.customer_email}</span></div>
                        <div><strong style="color: #3498db;">Movie:</strong><br><span style="color: white;">${data.movie_name}</span></div>
                        <div><strong style="color: #3498db;">Show Date:</strong><br><span style="color: white;">${data.show_date}</span></div>
                        <div><strong style="color: #3498db;">Show Time:</strong><br><span style="color: white;">${data.show_time}</span></div>
                        <div><strong style="color: #3498db;">Selected Seats:</strong><br><span style="color: #2ecc71; font-weight: 600;">${data.seat_list}</span></div>
                        <div><strong style="color: #3498db;">Total Seats:</strong><br><span style="color: white;">${data.total_seats}</span></div>
                        <div><strong style="color: #3498db;">Total Amount:</strong><br><span style="color: #2ecc71; font-weight: 800; font-size: 1.2rem;">₱${parseFloat(data.booking_fee).toFixed(2)}</span></div>
                        <div><strong style="color: #3498db;">Payment Status:</strong><br><span style="color: #2ecc71;">✓ Paid</span></div>
                        <div><strong style="color: #3498db;">Attendance Status:</strong><br><span style="color: ${data.attendance_status === 'Present' ? '#2ecc71' : '#3498db'};">${data.attendance_status === 'Present' ? '✓ Present' : '✓ Completed'}</span></div>
                        ${data.verified_at ? `<div><strong style="color: #3498db;">Verified At:</strong><br><span style="color: white;">${new Date(data.verified_at).toLocaleString()}</span></div>` : ''}
                        ${data.verified_by ? `<div><strong style="color: #3498db;">Verified By:</strong><br><span style="color: #2ecc71;">${data.verified_by}</span></div>` : ''}
                    </div>
                </div>
            `;
            document.getElementById('detailsModal').style.display = 'flex';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to fetch booking details');
    });
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const detailsModal = document.getElementById('detailsModal');
    if (event.target == detailsModal) closeDetailsModal();
}
</script>