<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Staff') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/staff-header.php';

$conn = get_db_connection();
$staff_id = $_SESSION['user_id'];

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================
// Build the payment transactions query
// ============================================
$query = "
    SELECT 
        b.id as booking_id,
        b.booking_reference,
        b.total_amount,
        b.payment_status,
        b.booked_at,
        b.verified_at,
        u.u_id as customer_id,
        u.u_name as customer_name,
        u.u_email as customer_email,
        m.id as movie_id,
        m.title as movie_title,
        s.show_date,
        s.showtime,
        sc.screen_name,
        v.venue_name,
        mp.id as manual_payment_id,
        mp.reference_number,
        mp.amount as manual_amount,
        mp.status as manual_status,
        mp.created_at as payment_submitted_at,
        mp.verified_by as verified_by_admin_id,
        admin.u_name as verified_by_name,
        pm.method_name as payment_method,
        GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') as seat_list,
        COUNT(DISTINCT bs.id) as total_seats
    FROM bookings b
    JOIN users u ON b.user_id = u.u_id
    JOIN schedules s ON b.schedule_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    LEFT JOIN booked_seats bs ON b.id = bs.booking_id
    LEFT JOIN manual_payments mp ON b.id = mp.booking_id
    LEFT JOIN payment_methods pm ON mp.payment_method_id = pm.id
    LEFT JOIN users admin ON mp.verified_by = admin.u_id
    WHERE b.payment_status != 'pending'
    AND b.is_visible = 1
";

// Apply filters
if ($filter == 'paid') {
    $query .= " AND b.payment_status = 'paid'";
} elseif ($filter == 'pending_verification') {
    $query .= " AND b.payment_status = 'pending_verification'";
} elseif ($filter == 'refunded') {
    $query .= " AND b.payment_status = 'refunded'";
}

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $query .= " AND (b.booking_reference LIKE '%$search_escaped%' 
                    OR u.u_name LIKE '%$search_escaped%' 
                    OR u.u_email LIKE '%$search_escaped%'
                    OR m.title LIKE '%$search_escaped%')";
}

if (!empty($date_from)) {
    $query .= " AND DATE(b.booked_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.booked_at) <= '$date_to'";
}

$query .= " GROUP BY b.id ORDER BY b.booked_at DESC";

$transactions_result = $conn->query($query);
$transactions = [];
if ($transactions_result) {
    while ($row = $transactions_result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Get payment statistics
$stats_query = "
    SELECT 
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as total_paid,
        COUNT(CASE WHEN payment_status = 'pending_verification' THEN 1 END) as pending_verification,
        COUNT(CASE WHEN payment_status = 'refunded' THEN 1 END) as total_refunded,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN total_amount ELSE 0 END), 0) as total_refunded_amount,
        COUNT(*) as total_transactions
    FROM bookings 
    WHERE payment_status != 'pending'
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_paid' => 0, 'pending_verification' => 0, 'total_refunded' => 0,
    'total_revenue' => 0, 'total_refunded_amount' => 0, 'total_transactions' => 0
];

$conn->close();
?>

<div class="staff-container" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <!-- Header Section -->
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(155, 89, 182, 0.1), rgba(142, 68, 173, 0.2)); border-radius: 20px; border: 2px solid rgba(155, 89, 182, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">
            <i class="fas fa-credit-card"></i> Payment Transactions
        </h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">
            View all customer payment records and transaction history
        </p>
    </div>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2rem; color: #3498db; margin-bottom: 8px;"><i class="fas fa-chart-line"></i></div>
            <div style="font-size: 1.8rem; font-weight: 800; color: white;">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
            <div style="color: rgba(255,255,255,0.8);">Total Revenue</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2rem; color: #2ecc71; margin-bottom: 8px;"><i class="fas fa-check-circle"></i></div>
            <div style="font-size: 1.8rem; font-weight: 800; color: white;"><?php echo number_format($stats['total_paid']); ?></div>
            <div style="color: rgba(255,255,255,0.8);">Successful Payments</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(241, 196, 15, 0.3);">
            <div style="font-size: 2rem; color: #f39c12; margin-bottom: 8px;"><i class="fas fa-clock"></i></div>
            <div style="font-size: 1.8rem; font-weight: 800; color: white;"><?php echo number_format($stats['pending_verification']); ?></div>
            <div style="color: rgba(255,255,255,0.8);">Pending Verification</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(231, 76, 60, 0.3);">
            <div style="font-size: 2rem; color: #e74c3c; margin-bottom: 8px;"><i class="fas fa-undo-alt"></i></div>
            <div style="font-size: 1.8rem; font-weight: 800; color: white;">₱<?php echo number_format($stats['total_refunded_amount'], 2); ?></div>
            <div style="color: rgba(255,255,255,0.8);">Refunded Amount</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(155, 89, 182, 0.2);">
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <a href="?page=staff/payment-transaction&filter=all" 
               class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>" 
               style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; <?php echo $filter == 'all' ? 'background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white;' : 'background: rgba(255,255,255,0.1); color: white;'; ?>">
                All Transactions
            </a>
            <a href="?page=staff/payment-transaction&filter=paid" 
               class="filter-btn <?php echo $filter == 'paid' ? 'active' : ''; ?>" 
               style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; <?php echo $filter == 'paid' ? 'background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white;' : 'background: rgba(255,255,255,0.1); color: white;'; ?>">
                <i class="fas fa-check-circle"></i> Paid
            </a>
            <a href="?page=staff/payment-transaction&filter=pending_verification" 
               class="filter-btn <?php echo $filter == 'pending_verification' ? 'active' : ''; ?>" 
               style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; <?php echo $filter == 'pending_verification' ? 'background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white;' : 'background: rgba(255,255,255,0.1); color: white;'; ?>">
                <i class="fas fa-clock"></i> Pending Verification
            </a>
            <a href="?page=staff/payment-transaction&filter=refunded" 
               class="filter-btn <?php echo $filter == 'refunded' ? 'active' : ''; ?>" 
               style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; <?php echo $filter == 'refunded' ? 'background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white;' : 'background: rgba(255,255,255,0.1); color: white;'; ?>">
                <i class="fas fa-undo-alt"></i> Refunded
            </a>
        </div>
        
        <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="page" value="staff/payment-transaction">
            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
            
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; color: white; font-size: 0.85rem; margin-bottom: 5px;">Search</label>
                <input type="text" name="search" placeholder="Reference, customer, movie..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       style="width: 100%; padding: 10px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white;">
            </div>
            
            <div>
                <label style="display: block; color: white; font-size: 0.85rem; margin-bottom: 5px;">Date From</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                       style="padding: 10px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white;">
            </div>
            
            <div>
                <label style="display: block; color: white; font-size: 0.85rem; margin-bottom: 5px;">Date To</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                       style="padding: 10px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white;">
            </div>
            
            <div>
                <button type="submit" style="padding: 10px 25px; background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-search"></i> Filter
                </button>
                <?php if ($search || $date_from || $date_to): ?>
                <a href="?page=staff/payment-transaction&filter=<?php echo $filter; ?>" 
                   style="padding: 10px 20px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; margin-left: 10px;">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 25px; border: 1px solid rgba(155, 89, 182, 0.2);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-list"></i> Payment Transactions 
            <span style="font-size: 0.9rem; color: #9b59b6;">(<?php echo count($transactions); ?> records)</span>
        </h2>
        
        <?php if (empty($transactions)): ?>
            <div style="text-align: center; padding: 60px; color: rgba(255,255,255,0.6);">
                <i class="fas fa-credit-card fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                <p style="font-size: 1.1rem;">No payment transactions found</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                            <th style="padding: 14px; text-align: left; color: white;">Transaction ID</th>
                            <th style="padding: 14px; text-align: left; color: white;">Customer</th>
                            <th style="padding: 14px; text-align: left; color: white;">Movie</th>
                            <th style="padding: 14px; text-align: left; color: white;">Seats</th>
                            <th style="padding: 14px; text-align: left; color: white;">Amount</th>
                            <th style="padding: 14px; text-align: left; color: white;">Payment Method</th>
                            <th style="padding: 14px; text-align: left; color: white;">Date & Time</th>
                            <th style="padding: 14px; text-align: left; color: white;">Status</th>
                            <th style="padding: 14px; text-align: left; color: white;">Actions</th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): 
                            $status_color = '';
                            $status_bg = '';
                            $status_icon = '';
                            
                            switch($transaction['payment_status']) {
                                case 'paid':
                                    $status_color = '#2ecc71';
                                    $status_bg = 'rgba(46, 204, 113, 0.2)';
                                    $status_icon = 'fa-check-circle';
                                    break;
                                case 'pending_verification':
                                    $status_color = '#f39c12';
                                    $status_bg = 'rgba(243, 156, 18, 0.2)';
                                    $status_icon = 'fa-clock';
                                    break;
                                case 'refunded':
                                    $status_color = '#e74c3c';
                                    $status_bg = 'rgba(231, 76, 60, 0.2)';
                                    $status_icon = 'fa-undo-alt';
                                    break;
                                default:
                                    $status_color = '#95a5a6';
                                    $status_bg = 'rgba(149, 165, 166, 0.2)';
                                    $status_icon = 'fa-question-circle';
                            }
                        ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 12px;">
                                    <span style="color: white; font-weight: 600; font-family: monospace;"><?php echo $transaction['booking_reference']; ?></span>
                                    <?php if ($transaction['reference_number']): ?>
                                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5);">Ref: <?php echo $transaction['reference_number']; ?></div>
                                    <?php endif; ?>
                                 </td>
                                <td style="padding: 12px;">
                                    <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($transaction['customer_name']); ?></div>
                                    <div style="color: rgba(255,255,255,0.6); font-size: 0.75rem;"><?php echo htmlspecialchars($transaction['customer_email']); ?></div>
                                 </td>
                                <td style="padding: 12px;">
                                    <div style="color: white;"><?php echo htmlspecialchars($transaction['movie_title']); ?></div>
                                    <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;">
                                        <?php echo date('M d, h:i A', strtotime($transaction['show_date'] . ' ' . $transaction['showtime'])); ?>
                                    </div>
                                 </td>
                                <td style="padding: 12px;">
                                    <span style="color: #3498db;"><?php echo $transaction['seat_list'] ?: 'N/A'; ?></span>
                                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5);"><?php echo $transaction['total_seats']; ?> seat(s)</div>
                                 </td>
                                <td style="padding: 12px;">
                                    <span style="color: #2ecc71; font-size: 1.1rem; font-weight: 700;">₱<?php echo number_format($transaction['total_amount'], 2); ?></span>
                                 </td>
                                <td style="padding: 12px;">
                                    <?php if ($transaction['payment_method']): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(52,152,219,0.2); color: #3498db; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem;">
                                        <i class="fas <?php echo $transaction['payment_method'] == 'GCash' ? 'fa-mobile-alt' : ($transaction['payment_method'] == 'PayMaya' ? 'fa-mobile-alt' : 'fa-credit-card'); ?>"></i>
                                        <?php echo $transaction['payment_method']; ?>
                                    </span>
                                    <?php else: ?>
                                    <span style="color: #3498db;">PayMongo</span>
                                    <?php endif; ?>
                                 </td>
                                <td style="padding: 12px;">
                                    <div style="color: white;"><?php echo date('M d, Y', strtotime($transaction['booked_at'])); ?></div>
                                    <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;"><?php echo date('h:i A', strtotime($transaction['booked_at'])); ?></div>
                                 </td>
                                <td style="padding: 12px;">
                                    <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; padding: 5px 12px; border-radius: 15px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['payment_status'])); ?>
                                    </span>
                                    <?php if ($transaction['verified_by_name']): ?>
                                    <div style="font-size: 0.65rem; color: rgba(255,255,255,0.4); margin-top: 4px;">
                                        Verified by: <?php echo $transaction['verified_by_name']; ?>
                                    </div>
                                    <?php endif; ?>
                                 </td>
                                <td style="padding: 12px;">
                                    <button onclick="viewTransactionDetails(<?php echo htmlspecialchars(json_encode($transaction)); ?>)" 
                                            style="background: #3498db; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 0.75rem;">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                 </td>
                             </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            </div>
            <div style="margin-top: 15px; text-align: center; color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                Showing <?php echo count($transactions); ?> transaction(s)
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Transaction Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000; justify-content: center; align-items: center; padding: 20px; overflow-y: auto;">
    <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border-radius: 20px; padding: 30px; max-width: 800px; width: 100%; border: 2px solid #9b59b6;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #9b59b6;">
            <h2 style="color: #9b59b6;"><i class="fas fa-receipt"></i> Transaction Details</h2>
            <button onclick="closeDetailsModal()" style="background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">&times;</button>
        </div>
        <div id="detailsModalContent"></div>
    </div>
</div>

<style>
.filter-btn {
    transition: all 0.3s ease;
}

.filter-btn:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}

tr:hover {
    background: rgba(155, 89, 182, 0.1);
}

@media (max-width: 768px) {
    .staff-container {
        padding: 15px;
    }
    
    table {
        font-size: 0.8rem;
    }
    
    th, td {
        padding: 8px !important;
    }
}
</style>

<script>
function viewTransactionDetails(transaction) {
    const modalContent = document.getElementById('detailsModalContent');
    
    const getStatusBadge = (status) => {
        const statuses = {
            'paid': { color: '#2ecc71', icon: 'check-circle', text: 'Paid' },
            'pending_verification': { color: '#f39c12', icon: 'clock', text: 'Pending Verification' },
            'refunded': { color: '#e74c3c', icon: 'undo-alt', text: 'Refunded' }
        };
        const s = statuses[status] || { color: '#95a5a6', icon: 'question-circle', text: status };
        return `<span style="background: ${s.color}20; color: ${s.color}; padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                    <i class="fas fa-${s.icon}"></i> ${s.text}
                </span>`;
    };
    
    modalContent.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 20px;">
                <h3 style="color: #9b59b6; margin-bottom: 15px;"><i class="fas fa-user"></i> Customer Information</h3>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Name:</span>
                    <span style="color: white; font-weight: 600;">${escapeHtml(transaction.customer_name)}</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Email:</span>
                    <span style="color: white;">${escapeHtml(transaction.customer_email)}</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Customer ID:</span>
                    <span style="color: white;">#${transaction.customer_id}</span>
                </div>
            </div>
            
            <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 20px;">
                <h3 style="color: #9b59b6; margin-bottom: 15px;"><i class="fas fa-ticket-alt"></i> Booking Information</h3>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Booking Reference:</span>
                    <span style="color: white; font-family: monospace; font-weight: 600;">${escapeHtml(transaction.booking_reference)}</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Movie:</span>
                    <span style="color: white;">${escapeHtml(transaction.movie_title)}</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Show Date & Time:</span>
                    <span style="color: white;">${new Date(transaction.show_date + ' ' + transaction.showtime).toLocaleString()}</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Venue:</span>
                    <span style="color: white;">${escapeHtml(transaction.venue_name)} - ${escapeHtml(transaction.screen_name)}</span>
                </div>
                <div>
                    <span style="color: rgba(255,255,255,0.6);">Seats:</span>
                    <span style="color: #3498db; font-weight: 600;">${escapeHtml(transaction.seat_list || 'N/A')}</span>
                    <span style="color: rgba(255,255,255,0.5);"> (${transaction.total_seats} seats)</span>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 20px;">
                <h3 style="color: #9b59b6; margin-bottom: 15px;"><i class="fas fa-credit-card"></i> Payment Details</h3>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Amount:</span>
                    <span style="color: #2ecc71; font-size: 1.2rem; font-weight: 700;">₱${parseFloat(transaction.total_amount).toFixed(2)}</span>
                </div>
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Payment Method:</span>
                    <span style="color: #3498db;">${transaction.payment_method || 'PayMongo'}</span>
                </div>
                ${transaction.reference_number ? `
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Reference Number:</span>
                    <span style="color: white; font-family: monospace;">${escapeHtml(transaction.reference_number)}</span>
                </div>
                ` : ''}
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Transaction Date:</span>
                    <span style="color: white;">${new Date(transaction.booked_at).toLocaleString()}</span>
                </div>
                <div>
                    <span style="color: rgba(255,255,255,0.6);">Status:</span>
                    ${getStatusBadge(transaction.payment_status)}
                </div>
            </div>
            
            <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 20px;">
                <h3 style="color: #9b59b6; margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Verification Details</h3>
                ${transaction.verified_at ? `
                <div style="margin-bottom: 10px;">
                    <span style="color: rgba(255,255,255,0.6);">Verified At:</span>
                    <span style="color: white;">${new Date(transaction.verified_at).toLocaleString()}</span>
                </div>
                <div>
                    <span style="color: rgba(255,255,255,0.6);">Verified By:</span>
                    <span style="color: white;">${escapeHtml(transaction.verified_by_name || 'System')}</span>
                </div>
                ` : '<p style="color: rgba(255,255,255,0.5);">Not yet verified</p>'}
                ${transaction.manual_status ? `
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <p style="color: #f39c12; margin-bottom: 5px;"><i class="fas fa-hand-holding-usd"></i> Manual Payment Status: ${transaction.manual_status}</p>
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">Submitted: ${new Date(transaction.payment_submitted_at).toLocaleString()}</p>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 25px;">
            <button onclick="closeDetailsModal()" class="btn btn-secondary" style="padding: 12px 30px;">
                Close
            </button>
        </div>
    `;
    
    document.getElementById('detailsModal').style.display = 'flex';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target == modal) {
        closeDetailsModal();
    }
}
</script>

