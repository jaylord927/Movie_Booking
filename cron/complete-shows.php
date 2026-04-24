<?php
// Run this script via cron job every hour
// Command: 0 * * * * php /path/to/cron/complete-shows.php

$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

$conn = get_db_connection();

// Update shows that have ended to "Completed"
$update_stmt = $conn->prepare("
    UPDATE tbl_booking 
    SET attendance_status = 'Completed' 
    WHERE attendance_status = 'Present' 
    AND CONCAT(show_date, ' ', showtime) < NOW()
");
$update_stmt->execute();
$affected = $update_stmt->affected_rows;
$update_stmt->close();

echo "Updated $affected bookings to Completed status.\n";

$conn->close();
?>