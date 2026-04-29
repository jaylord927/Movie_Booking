<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Owner')) {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

require_once $root_dir . '/partials/admin-header.php';

// Open database connection
$conn = get_db_connection();

$error = '';
$success = '';
$edit_plan = null;
$selected_screen_id = isset($_GET['screen_id']) ? intval($_GET['screen_id']) : 0;

// ============================================
// CREATE SEAT PLAN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_seat_plan'])) {
    $screen_id = intval($_POST['screen_id']);
    $plan_name = sanitize_input(trim($_POST['plan_name']));
    $total_rows = intval($_POST['total_rows']);
    $total_columns = intval($_POST['total_columns']);
    $seat_data = isset($_POST['seat_data']) ? json_decode($_POST['seat_data'], true) : [];
    $aisle_data = isset($_POST['aisle_data']) ? json_decode($_POST['aisle_data'], true) : [];

    if ($screen_id <= 0) {
        $error = "Please select a screen!";
    } elseif (empty($plan_name)) {
        $error = "Please enter a plan name!";
    } elseif ($total_rows < 1 || $total_rows > 50) {
        $error = "Rows must be between 1 and 50!";
    } elseif ($total_columns < 1 || $total_columns > 50) {
        $error = "Columns must be between 1 and 50!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM seat_plans WHERE screen_id = ? AND plan_name = ?");
        $check_stmt->bind_param("is", $screen_id, $plan_name);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "A seat plan with this name already exists for this screen!";
        } else {
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("INSERT INTO seat_plans (screen_id, plan_name, total_rows, total_columns, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param("issi", $screen_id, $plan_name, $total_rows, $total_columns);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to create seat plan: " . $stmt->error);
                }

                $plan_id = $stmt->insert_id;
                $stmt->close();

                // Insert aisles - handle width by creating multiple entries
                if (!empty($aisle_data)) {
                    $aisle_stmt = $conn->prepare("
                        INSERT INTO aisles (seat_plan_id, aisle_type_id, position_value, position_type, width) 
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $type_result = $conn->query("SELECT id, name FROM aisle_types WHERE is_active = 1");
                    $aisle_type_ids = [];
                    while ($type = $type_result->fetch_assoc()) {
                        $aisle_type_ids[$type['name']] = $type['id'];
                    }

                    foreach ($aisle_data as $aisle) {
                        $aisle_type_name = $aisle['type'] ?? 'Row Aisle';
                        $aisle_type_id = $aisle_type_ids[$aisle_type_name] ?? 1;
                        $position_value = intval($aisle['position']);
                        $position_type = $aisle['position_type'];
                        $width = intval($aisle['width'] ?? 1);

                        // Create multiple aisle entries based on width
                        for ($w = 0; $w < $width; $w++) {
                            $aisle_stmt->bind_param("iiisi", $plan_id, $aisle_type_id, $position_value + $w, $position_type, 1);
                            $aisle_stmt->execute();
                        }
                    }
                    $aisle_stmt->close();
                }

                // Insert seats (for all columns, aisles are separate spacing elements)
                $seat_stmt = $conn->prepare("
                    INSERT INTO seat_plan_details (seat_plan_id, seat_row, seat_column, seat_number, seat_type_id, is_enabled, custom_price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $type_result = $conn->query("SELECT id, name FROM seat_types WHERE is_active = 1");
                $seat_type_ids = [];
                while ($type = $type_result->fetch_assoc()) {
                    $seat_type_ids[$type['name']] = $type['id'];
                }

                foreach ($seat_data as $seat) {
                    $row_letter = $seat['row'];
                    $col = $seat['col'];
                    $seat_number = $row_letter . str_pad($col, 2, '0', STR_PAD_LEFT);
                    $seat_type_id = isset($seat['type']) && isset($seat_type_ids[$seat['type']]) ? $seat_type_ids[$seat['type']] : null;
                    $is_enabled = $seat['enabled'] ? 1 : 0;
                    $custom_price = !empty($seat['custom_price']) ? floatval($seat['custom_price']) : null;

                    $seat_stmt->bind_param("isissid", $plan_id, $row_letter, $col, $seat_number, $seat_type_id, $is_enabled, $custom_price);
                    $seat_stmt->execute();
                }

                $seat_stmt->close();

                $seat_count = $conn->prepare("SELECT COUNT(*) as count FROM seat_plan_details WHERE seat_plan_id = ? AND is_enabled = 1");
                $seat_count->bind_param("i", $plan_id);
                $seat_count->execute();
                $count_result = $seat_count->get_result();
                $total_seats = $count_result->fetch_assoc()['count'];
                $seat_count->close();

                $update_screen = $conn->prepare("UPDATE screens SET capacity = ? WHERE id = ?");
                $update_screen->bind_param("ii", $total_seats, $screen_id);
                $update_screen->execute();
                $update_screen->close();

                $conn->commit();
                $success = "Seat plan '$plan_name' created successfully! Total seats: " . $total_seats;
                $_POST = array();
                $selected_screen_id = 0;

            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
        $check_stmt->close();
    }
}

// ============================================
// UPDATE SEAT PLAN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_seat_plan'])) {
    $plan_id = intval($_POST['plan_id']);
    $plan_name = sanitize_input(trim($_POST['plan_name']));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $seat_data = isset($_POST['seat_data']) ? json_decode($_POST['seat_data'], true) : [];
    $aisle_data = isset($_POST['aisle_data']) ? json_decode($_POST['aisle_data'], true) : [];

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("UPDATE seat_plans SET plan_name = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sii", $plan_name, $is_active, $plan_id);
        $stmt->execute();
        $stmt->close();

        // Delete existing aisles
        $delete_aisles = $conn->prepare("DELETE FROM aisles WHERE seat_plan_id = ?");
        $delete_aisles->bind_param("i", $plan_id);
        $delete_aisles->execute();
        $delete_aisles->close();

        // Insert updated aisles - handle width by creating multiple entries
        if (!empty($aisle_data)) {
            $aisle_stmt = $conn->prepare("
                INSERT INTO aisles (seat_plan_id, aisle_type_id, position_value, position_type, width) 
                VALUES (?, ?, ?, ?, ?)
            ");

            $type_result = $conn->query("SELECT id, name FROM aisle_types WHERE is_active = 1");
            $aisle_type_ids = [];
            while ($type = $type_result->fetch_assoc()) {
                $aisle_type_ids[$type['name']] = $type['id'];
            }

            foreach ($aisle_data as $aisle) {
                $aisle_type_name = $aisle['type'] ?? 'Row Aisle';
                $aisle_type_id = $aisle_type_ids[$aisle_type_name] ?? 1;
                $position_value = intval($aisle['position']);
                $position_type = $aisle['position_type'];
                $width = intval($aisle['width'] ?? 1);

                // Create multiple aisle entries based on width
                for ($w = 0; $w < $width; $w++) {
                    $aisle_stmt->bind_param("iiisi", $plan_id, $aisle_type_id, $position_value + $w, $position_type, 1);
                    $aisle_stmt->execute();
                }
            }
            $aisle_stmt->close();
        }

        // Delete existing seats
        $delete_stmt = $conn->prepare("DELETE FROM seat_plan_details WHERE seat_plan_id = ?");
        $delete_stmt->bind_param("i", $plan_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        // Insert updated seats
        $seat_stmt = $conn->prepare("
            INSERT INTO seat_plan_details (seat_plan_id, seat_row, seat_column, seat_number, seat_type_id, is_enabled, custom_price) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $type_result = $conn->query("SELECT id, name FROM seat_types WHERE is_active = 1");
        $seat_type_ids = [];
        while ($type = $type_result->fetch_assoc()) {
            $seat_type_ids[$type['name']] = $type['id'];
        }

        foreach ($seat_data as $seat) {
            $row_letter = $seat['row'];
            $col = $seat['col'];
            $seat_number = $row_letter . str_pad($col, 2, '0', STR_PAD_LEFT);
            $seat_type_id = isset($seat['type']) && isset($seat_type_ids[$seat['type']]) ? $seat_type_ids[$seat['type']] : null;
            $is_enabled = $seat['enabled'] ? 1 : 0;
            $custom_price = !empty($seat['custom_price']) ? floatval($seat['custom_price']) : null;

            $seat_stmt->bind_param("isissid", $plan_id, $row_letter, $col, $seat_number, $seat_type_id, $is_enabled, $custom_price);
            $seat_stmt->execute();
        }

        $seat_stmt->close();

        $seat_count = $conn->prepare("SELECT COUNT(*) as count FROM seat_plan_details WHERE seat_plan_id = ? AND is_enabled = 1");
        $seat_count->bind_param("i", $plan_id);
        $seat_count->execute();
        $count_result = $seat_count->get_result();
        $total_seats = $count_result->fetch_assoc()['count'];
        $seat_count->close();

        $screen_query = $conn->prepare("SELECT screen_id FROM seat_plans WHERE id = ?");
        $screen_query->bind_param("i", $plan_id);
        $screen_query->execute();
        $screen_result = $screen_query->get_result();
        $screen = $screen_result->fetch_assoc();
        $screen_query->close();

        $update_screen = $conn->prepare("UPDATE screens SET capacity = ? WHERE id = ?");
        $update_screen->bind_param("ii", $total_seats, $screen['screen_id']);
        $update_screen->execute();
        $update_screen->close();

        $conn->commit();
        $success = "Seat plan updated successfully! Total seats: " . $total_seats;

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// ============================================
// GET SEAT PLAN FOR EDITING
// ============================================
if (isset($_GET['edit_plan']) && is_numeric($_GET['edit_plan'])) {
    $edit_id = intval($_GET['edit_plan']);
    $stmt = $conn->prepare("
        SELECT sp.*, s.screen_name, s.screen_number, v.venue_name, v.id as venue_id
        FROM seat_plans sp
        JOIN screens s ON sp.screen_id = s.id
        JOIN venues v ON s.venue_id = v.id
        WHERE sp.id = ?
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_plan = $result->fetch_assoc();
    $stmt->close();

    if ($edit_plan) {
        // Get existing seats
        $seats_stmt = $conn->prepare("
            SELECT spd.*, st.name as seat_type_name
            FROM seat_plan_details spd
            LEFT JOIN seat_types st ON spd.seat_type_id = st.id
            WHERE spd.seat_plan_id = ?
            ORDER BY spd.seat_row, spd.seat_column
        ");
        $seats_stmt->bind_param("i", $edit_id);
        $seats_stmt->execute();
        $seats_result = $seats_stmt->get_result();

        $edit_plan['seats'] = [];
        while ($seat = $seats_result->fetch_assoc()) {
            $edit_plan['seats'][] = $seat;
        }
        $seats_stmt->close();

        // Get existing aisles
        $aisles_stmt = $conn->prepare("
            SELECT a.*, at.name as aisle_type_name
            FROM aisles a
            JOIN aisle_types at ON a.aisle_type_id = at.id
            WHERE a.seat_plan_id = ?
            ORDER BY a.position_type, a.position_value
        ");
        $aisles_stmt->bind_param("i", $edit_id);
        $aisles_stmt->execute();
        $aisles_result = $aisles_stmt->get_result();

        $edit_plan['aisles'] = [];
        while ($aisle = $aisles_result->fetch_assoc()) {
            $edit_plan['aisles'][] = $aisle;
        }
        $aisles_stmt->close();
    }
}

// ============================================
// DELETE SEAT PLAN
// ============================================
if (isset($_GET['delete_plan']) && is_numeric($_GET['delete_plan'])) {
    $plan_id = intval($_GET['delete_plan']);

    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM schedules WHERE seat_plan_id = ? AND is_active = 1");
    $check_stmt->bind_param("i", $plan_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $schedule_count = $result->fetch_assoc()['count'];
    $check_stmt->close();

    if ($schedule_count > 0) {
        $error = "Cannot delete this seat plan because it is used in $schedule_count active schedule(s).";
    } else {
        // Delete aisles first
        $delete_aisles = $conn->prepare("DELETE FROM aisles WHERE seat_plan_id = ?");
        $delete_aisles->bind_param("i", $plan_id);
        $delete_aisles->execute();
        $delete_aisles->close();

        // Delete seats
        $delete_seats = $conn->prepare("DELETE FROM seat_plan_details WHERE seat_plan_id = ?");
        $delete_seats->bind_param("i", $plan_id);
        $delete_seats->execute();
        $delete_seats->close();

        // Deactivate seat plan
        $stmt = $conn->prepare("UPDATE seat_plans SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $plan_id);

        if ($stmt->execute()) {
            $success = "Seat plan deleted successfully!";
        } else {
            $error = "Failed to delete seat plan: " . $conn->error;
        }
        $stmt->close();
    }
}

// ============================================
// FETCH DATA FOR DISPLAY
// ============================================

$screens = [];
$screens_result = $conn->query("
    SELECT 
        s.id as screen_id,
        s.screen_name,
        s.screen_number,
        s.capacity,
        v.id as venue_id,
        v.venue_name,
        CONCAT(v.venue_name, ' - ', s.screen_name, ' (Screen ', s.screen_number, ')') as display_name
    FROM screens s
    JOIN venues v ON s.venue_id = v.id
    WHERE s.is_active = 1 AND v.is_active = 1
    ORDER BY v.venue_name, s.screen_number
");
if ($screens_result) {
    while ($row = $screens_result->fetch_assoc()) {
        $screens[] = $row;
    }
}

// Seat plans displayed in order by ID (ascending)
$seat_plans = [];
$plans_result = $conn->query("
    SELECT 
        sp.id,
        sp.plan_name,
        sp.total_rows,
        sp.total_columns,
        sp.is_active,
        sp.created_at,
        s.id as screen_id,
        s.screen_name,
        s.screen_number,
        v.id as venue_id,
        v.venue_name,
        COUNT(spd.id) as total_seats,
        COUNT(CASE WHEN st.name = 'Standard' THEN 1 END) as standard_seats,
        COUNT(CASE WHEN st.name = 'Premium' THEN 1 END) as premium_seats,
        COUNT(CASE WHEN st.name = 'Sweet Spot' THEN 1 END) as sweet_spot_seats
    FROM seat_plans sp
    JOIN screens s ON sp.screen_id = s.id
    JOIN venues v ON s.venue_id = v.id
    LEFT JOIN seat_plan_details spd ON sp.id = spd.seat_plan_id AND spd.is_enabled = 1
    LEFT JOIN seat_types st ON spd.seat_type_id = st.id
    WHERE sp.is_active = 1
    GROUP BY sp.id, sp.plan_name, sp.total_rows, sp.total_columns, sp.is_active, sp.created_at,
             s.id, s.screen_name, s.screen_number, v.id, v.venue_name
    ORDER BY sp.id ASC
");
if ($plans_result) {
    while ($row = $plans_result->fetch_assoc()) {
        $seat_plans[] = $row;
    }
}

$seat_types = [];
$type_result = $conn->query("SELECT id, name, default_price, color_code FROM seat_types WHERE is_active = 1 ORDER BY sort_order");
if ($type_result) {
    while ($row = $type_result->fetch_assoc()) {
        $seat_types[] = $row;
    }
}

$aisle_types = [];
$aisle_result = $conn->query("SELECT id, name, description, color_code, width FROM aisle_types WHERE is_active = 1");
if ($aisle_result) {
    while ($row = $aisle_result->fetch_assoc()) {
        $aisle_types[] = $row;
    }
}

// DO NOT CLOSE CONNECTION HERE - will close after all output
?>

<div class="admin-content" style="max-width: 1600px; margin: 0 auto; padding: 30px;">
    <div
        style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(155, 89, 182, 0.1), rgba(142, 68, 173, 0.2)); border-radius: 20px; border: 2px solid rgba(155, 89, 182, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Customizable Seat Layout
            Manager</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Design your cinema seat layout exactly as it
            appears in the physical venue</p>
    </div>

    <?php if ($error): ?>
        <div
            style="background: rgba(231, 76, 60, 0.2); color: #ff9999; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(231, 76, 60, 0.3);">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div
            style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; text-align: center; border: 1px solid rgba(46, 204, 113, 0.3);">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Seat Plan Editor -->
    <div
        style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(155, 89, 182, 0.2);">
        <h2
            style="color: white; font-size: 1.8rem; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #9b59b6; display: flex; align-items: center; gap: 10px;">
            <i class="<?php echo $edit_plan ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
            <?php echo $edit_plan ? 'Edit Seat Layout' : 'Create Custom Seat Layout'; ?>
        </h2>

        <form method="POST" action="" id="seatPlanForm">
            <?php if ($edit_plan): ?>
                <input type="hidden" name="plan_id" value="<?php echo $edit_plan['id']; ?>">
            <?php endif; ?>
            <input type="hidden" name="seat_data" id="seat_data_input">
            <input type="hidden" name="aisle_data" id="aisle_data_input">

            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                        <i class="fas fa-tv"></i> Select Screen *
                    </label>
                    <select name="screen_id" id="screen_id" required ...
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white; cursor: pointer;">
                        <option value="" style="background: #2c3e50; color: white;">-- Select Screen --</option>
                        <?php foreach ($screens as $screen): ?>
                            <option value="<?php echo $screen['screen_id']; ?>" ...
                                style="background: #2c3e50; color: white;">
                                <?php echo htmlspecialchars($screen['display_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                        <i class="fas fa-tag"></i> Plan Name *
                    </label>
                    <input type="text" name="plan_name" required
                        value="<?php echo $edit_plan ? htmlspecialchars($edit_plan['plan_name']) : ''; ?>"
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white;">
                </div>
            </div>

            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                        <i class="fas fa-arrows-alt-v"></i> Total Rows
                    </label>
                    <input type="number" id="total_rows" min="1" max="50"
                        value="<?php echo $edit_plan ? $edit_plan['total_rows'] : '8'; ?>"
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white;">
                </div>

                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                        <i class="fas fa-arrows-alt-h"></i> Total Columns
                    </label>
                    <input type="number" id="total_columns" min="1" max="50"
                        value="<?php echo $edit_plan ? $edit_plan['total_columns'] : '10'; ?>"
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white;">
                </div>
            </div>

            <!-- Multi-Selection Toolbar -->
            <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <span id="selectionCount"
                            style="background: #3498db; padding: 5px 12px; border-radius: 20px; font-weight: 600;">0
                            seats selected</span>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" id="selectAllBtn" class="btn btn-secondary" style="padding: 8px 15px;">
                            <i class="fas fa-check-double"></i> Select All Seats
                        </button>
                        <button type="button" id="clearSelectionBtn" class="btn btn-secondary"
                            style="padding: 8px 15px;">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Action Buttons for Seat Types -->
            <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="color: white; font-weight: 600;">
                        <i class="fas fa-layer-group"></i> Bulk Assign Seat Type:
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php foreach ($seat_types as $type): ?>
                            <button type="button" class="bulk-type-btn btn btn-secondary"
                                data-type="<?php echo $type['name']; ?>"
                                style="padding: 8px 15px; background: <?php echo $type['color_code']; ?>; color: white;">
                                <i class="fas fa-chair"></i> <?php echo $type['name']; ?>
                            </button>
                        <?php endforeach; ?>
                        <button type="button" id="bulkClearTypeBtn" class="btn btn-warning"
                            style="padding: 8px 15px; background: #95a5a6; color: white;">
                            <i class="fas fa-eraser"></i> Clear Type (Make Empty)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Action Buttons for Seat Status -->
            <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div style="color: white; font-weight: 600;">
                        <i class="fas fa-toggle-on"></i> Bulk Seat Status:
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" id="bulkEnableBtn" class="btn btn-success" style="padding: 8px 15px;">
                            <i class="fas fa-check-circle"></i> Enable Seats
                        </button>
                        <button type="button" id="bulkDisableBtn" class="btn btn-danger" style="padding: 8px 15px;">
                            <i class="fas fa-ban"></i> Disable Seats
                        </button>
                        <button type="button" id="bulkDeleteBtn" class="btn btn-danger" style="padding: 8px 15px;">
                            <i class="fas fa-trash"></i> Remove Seats
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row Aisle Configuration -->
            <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                <h3 style="color: #9b59b6; margin-bottom: 15px; font-size: 1.1rem;">
                    <i class="fas fa-grip-vertical"></i> Row Aisles (Horizontal gaps between rows)
                </h3>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <div>
                            <label style="color: white; display: block; margin-bottom: 5px;">Insert Aisle After Row
                                #</label>
                            <input type="number" id="new_row_aisle_position" min="1"
                                style="width: 150px; padding: 8px; background: rgba(255,255,255,0.08); border-radius: 8px; color: white;">
                        </div>
                        <div>
                            <label style="color: white; display: block; margin-bottom: 5px;">Aisle Width
                                (spaces)</label>
                            <input type="number" id="new_row_aisle_width" min="1" max="10" value="1"
                                style="width: 120px; padding: 8px; background: rgba(255,255,255,0.08); border-radius: 8px; color: white;">
                        </div>
                        <button type="button" id="addRowAisleBtn" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> Add Row Aisle
                        </button>
                    </div>
                    <div style="margin-top: 8px; color: rgba(255,255,255,0.4); font-size: 0.7rem;">
                        Width = number of empty row spaces to insert between rows (does NOT affect existing seats)
                    </div>
                </div>
                <div id="rowAislesList"
                    style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 8px; min-height: 60px;">
                </div>
            </div>

            <!-- Column Aisle Configuration (INSERTED BETWEEN columns, NOT replacing them) -->
            <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                <h3 style="color: #9b59b6; margin-bottom: 15px; font-size: 1.1rem;">
                    <i class="fas fa-grip-vertical"></i> Column Aisles (Vertical gaps between columns)
                </h3>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <div>
                            <label style="color: white; display: block; margin-bottom: 5px;">Insert Aisle After Column
                                #</label>
                            <input type="number" id="new_col_aisle_position" min="1"
                                style="width: 150px; padding: 8px; background: rgba(255,255,255,0.08); border-radius: 8px; color: white;">
                        </div>
                        <div>
                            <label style="color: white; display: block; margin-bottom: 5px;">Aisle Width
                                (spaces)</label>
                            <input type="number" id="new_col_aisle_width" min="1" max="10" value="1"
                                style="width: 120px; padding: 8px; background: rgba(255,255,255,0.08); border-radius: 8px; color: white;">
                        </div>
                        <button type="button" id="addColAisleBtn" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> Add Column Aisle
                        </button>
                    </div>
                    <div style="margin-top: 8px; color: rgba(255,255,255,0.4); font-size: 0.7rem;">
                        Width = number of empty column spaces to insert between columns (does NOT replace existing seat
                        columns)
                    </div>
                </div>
                <div id="colAislesList"
                    style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 8px; min-height: 60px;">
                </div>
            </div>

            <!-- Layout Action Buttons -->
            <div style="margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; justify-content: center;">
                <button type="button" id="generateEmptyGridBtn" class="btn btn-secondary" style="padding: 10px 20px;">
                    <i class="fas fa-th"></i> Generate Empty Grid
                </button>
                <button type="button" id="generateSeatsGridBtn" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fas fa-chair"></i> Generate Seats Grid
                </button>
                <button type="button" id="clearAllSeatsBtn" class="btn btn-danger" style="padding: 10px 20px;">
                    <i class="fas fa-trash"></i> Clear All Seats
                </button>
                <button type="button" id="fillEmptySeatsBtn" class="btn btn-success" style="padding: 10px 20px;">
                    <i class="fas fa-fill-drip"></i> Add Missing Seats
                </button>
            </div>

            <!-- Seat Layout Editor -->
            <div
                style="background: #1a1a2e; border-radius: 15px; padding: 20px; margin-bottom: 20px; overflow-x: auto;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <div
                        style="display: inline-block; background: linear-gradient(135deg, #3498db, #2980b9); padding: 15px 40px; border-radius: 10px; color: white; font-weight: 700; font-size: 1.2rem;">
                        <i class="fas fa-tv"></i> S C R E E N
                    </div>
                </div>

                <div id="seatLayoutEditor" class="seat-layout-editor"
                    style="display: flex; flex-direction: column; align-items: center; gap: 5px; min-width: 600px;">
                </div>
            </div>

            <!-- Seat Type Legend -->
            <div
                style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; margin-bottom: 20px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div
                        style="width: 30px; height: 30px; background: #444; border-radius: 6px; border: 2px dashed #888;">
                    </div>
                    <span style="color: white;">Empty Seat (No Type)</span>
                </div>
                <?php foreach ($seat_types as $type): ?>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div
                            style="width: 30px; height: 30px; background: <?php echo $type['color_code']; ?>; border-radius: 6px; border: 2px solid rgba(255,255,255,0.3);">
                        </div>
                        <span style="color: white;"><?php echo $type['name']; ?>
                            (₱<?php echo number_format($type['default_price'], 2); ?>)</span>
                    </div>
                <?php endforeach; ?>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div
                        style="width: 30px; height: 30px; background: #2c3e50; border: 2px dashed #e74c3c; border-radius: 6px;">
                    </div>
                    <span style="color: white;">Aisle Gap (Spacing)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div
                        style="width: 30px; height: 30px; background: transparent; border: 3px solid #f39c12; border-radius: 6px;">
                    </div>
                    <span style="color: #f39c12;">Selected Seat</span>
                </div>
            </div>

            <?php if ($edit_plan): ?>
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" <?php echo $edit_plan['is_active'] ? 'checked' : ''; ?>>
                        <span style="color: white;">Active (available for scheduling)</span>
                    </label>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" name="<?php echo $edit_plan ? 'update_seat_plan' : 'create_seat_plan'; ?>"
                    class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                    <i class="fas fa-save"></i> <?php echo $edit_plan ? 'Save Seat Layout' : 'Create Seat Layout'; ?>
                </button>
                <?php if ($edit_plan): ?>
                    <a href="?page=admin/manage-seats" class="btn btn-secondary"
                        style="padding: 15px 30px; margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Existing Seat Plans List (ordered by ID ascending) -->
    <div
        style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(155, 89, 182, 0.2);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px;">Existing Seat Plans
            (<?php echo count($seat_plans); ?>)</h2>

        <?php if (empty($seat_plans)): ?>
            <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                <i class="fas fa-chair fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No seat plans found. Create your first custom layout!</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                            <th style="padding: 12px; text-align: left;">ID</th>
                            <th style="padding: 12px; text-align: left;">Venue</th>
                            <th style="padding: 12px; text-align: left;">Screen</th>
                            <th style="padding: 12px; text-align: left;">Plan Name</th>
                            <th style="padding: 12px; text-align: left;">Layout</th>
                            <th style="padding: 12px; text-align: left;">Seat Distribution</th>
                            <th style="padding: 12px; text-align: left;">Total Seats</th>
                            <th style="padding: 12px; text-align: left;">Status</th>
                            <th style="padding: 12px; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seat_plans as $plan): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <td style="padding: 12px;"><?php echo $plan['id']; ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($plan['venue_name']); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($plan['screen_name']); ?> (Screen
                                    <?php echo $plan['screen_number']; ?>)</td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                                <td style="padding: 12px;">
                                    <?php echo $plan['total_rows']; ?>×<?php echo $plan['total_columns']; ?></td>
                                <td style="padding: 12px;">
                                    <span style="color: #3498db;">S:<?php echo $plan['standard_seats']; ?></span> |
                                    <span style="color: #FFD700;">P:<?php echo $plan['premium_seats']; ?></span> |
                                    <span style="color: #e74c3c;">SS:<?php echo $plan['sweet_spot_seats']; ?></span>
                                </td>
                                <td style="padding: 12px; font-weight: 700;"><?php echo number_format($plan['total_seats']); ?>
                                </td>
                                <td style="padding: 12px;">
                                    <span
                                        style="background: <?php echo $plan['is_active'] ? 'rgba(46,204,113,0.2)' : 'rgba(231,76,60,0.2)'; ?>; color: <?php echo $plan['is_active'] ? '#2ecc71' : '#e74c3c'; ?>; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem;">
                                        <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 8px;">
                                        <a href="?page=admin/manage-seats&edit_plan=<?php echo $plan['id']; ?>"
                                            class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">
                                            <i class="fas fa-edit"></i> Edit Layout
                                        </a>
                                        <a href="?page=admin/manage-seats&delete_plan=<?php echo $plan['id']; ?>"
                                            onclick="return confirm('Delete this seat plan?')" class="btn btn-danger"
                                            style="padding: 6px 12px; font-size: 0.8rem;">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .seat-layout-editor {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .seat-row {
        display: flex;
        gap: 6px;
        align-items: center;
        justify-content: center;
        margin-bottom: 8px;
    }

    .seat-row-label {
        width: 40px;
        text-align: center;
        font-weight: 700;
        color: #9b59b6;
        font-size: 0.9rem;
    }

    .seat-cell {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.7rem;
        font-weight: 600;
        position: relative;
    }

    .seat-cell:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .seat-cell.selected {
        border: 3px solid #f39c12;
        box-shadow: 0 0 0 2px rgba(243, 156, 18, 0.3);
    }

    .seat-cell.aisle-gap {
        background: #2c3e50 !important;
        border: 2px dashed #e74c3c !important;
        cursor: default;
    }

    .seat-cell.aisle-gap:hover {
        transform: none;
    }

    .seat-number {
        font-size: 0.65rem;
        opacity: 0.9;
    }

    .seat-type-badge {
        font-size: 0.6rem;
        background: rgba(0, 0, 0, 0.5);
        padding: 2px 4px;
        border-radius: 4px;
        margin-top: 2px;
    }

    .aisle-item {
        background: rgba(231, 76, 60, 0.2);
        border: 1px solid #e74c3c;
        border-radius: 8px;
        padding: 8px 15px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: white;
    }

    .aisle-item .remove-aisle {
        background: #e74c3c;
        border: none;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        cursor: pointer;
        font-size: 12px;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 2px solid rgba(155, 89, 182, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
    }

    .btn-warning {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: white;
    }

    .btn:disabled,
    .btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn:hover:not(:disabled) {
        transform: translateY(-2px);
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        .seat-cell {
            width: 35px;
            height: 45px;
            font-size: 0.6rem;
        }

        .seat-row-label {
            width: 30px;
            font-size: 0.7rem;
        }
    }
</style>

<script>
    // Data structures
    let seatData = [];
    let selectedSeatIndices = new Set();
    let rowAisles = [];
    let colAisles = [];

    // Load existing data for edit mode
    <?php if ($edit_plan && isset($edit_plan['seats'])): ?>
        seatData = <?php echo json_encode($edit_plan['seats']); ?>;
    <?php endif; ?>
    <?php if ($edit_plan && isset($edit_plan['aisles'])): ?>
        const existingAisles = <?php echo json_encode($edit_plan['aisles']); ?>;
        rowAisles = existingAisles.filter(a => a.position_type === 'row').map(a => ({ position: a.position_value, width: a.width }));
        colAisles = existingAisles.filter(a => a.position_type === 'column').map(a => ({ position: a.position_value, width: a.width }));
    <?php endif; ?>

    // Seat type config
    const seatTypeConfig = {
        <?php foreach ($seat_types as $type): ?>
        '<?php echo $type['name']; ?>': {
                color: '<?php echo $type['color_code']; ?>',
                price: <?php echo $type['default_price']; ?>,
                label: '<?php echo substr($type['name'], 0, 1); ?>'
            },
        <?php endforeach; ?>
    'empty': { color: '#444', label: 'E' }
    };

    function updateSelectionCount() {
        const count = selectedSeatIndices.size;
        document.getElementById('selectionCount').innerHTML = `${count} seat${count !== 1 ? 's' : ''} selected`;
    }

    function toggleSeatSelection(index, event) {
        if (event) event.stopPropagation();

        if (selectedSeatIndices.has(index)) {
            selectedSeatIndices.delete(index);
        } else {
            selectedSeatIndices.add(index);
        }
        updateSelectionCount();
        renderSeatLayout();
    }

    function selectAllSeats() {
        seatData.forEach((seat, index) => {
            if (seat.is_enabled) {
                selectedSeatIndices.add(index);
            }
        });
        updateSelectionCount();
        renderSeatLayout();
    }

    function clearSelection() {
        selectedSeatIndices.clear();
        updateSelectionCount();
        renderSeatLayout();
    }

    function bulkAssignSeatType(seatType) {
        if (selectedSeatIndices.size === 0) {
            alert('Please select seats first');
            return;
        }

        selectedSeatIndices.forEach(index => {
            if (seatData[index]) {
                seatData[index].seat_type_name = seatType;
            }
        });

        renderSeatLayout();
        alert(`Assigned ${seatType} to ${selectedSeatIndices.size} seats`);
    }

    function bulkClearSeatType() {
        if (selectedSeatIndices.size === 0) {
            alert('Please select seats first');
            return;
        }

        selectedSeatIndices.forEach(index => {
            if (seatData[index]) {
                seatData[index].seat_type_name = null;
            }
        });

        renderSeatLayout();
        alert(`Cleared seat type for ${selectedSeatIndices.size} seats`);
    }

    function bulkEnableSeats() {
        if (selectedSeatIndices.size === 0) {
            alert('Please select seats first');
            return;
        }

        selectedSeatIndices.forEach(index => {
            if (seatData[index]) {
                seatData[index].is_enabled = 1;
            }
        });

        renderSeatLayout();
        alert(`Enabled ${selectedSeatIndices.size} seats`);
    }

    function bulkDisableSeats() {
        if (selectedSeatIndices.size === 0) {
            alert('Please select seats first');
            return;
        }

        selectedSeatIndices.forEach(index => {
            if (seatData[index]) {
                seatData[index].is_enabled = 0;
            }
        });

        renderSeatLayout();
        alert(`Disabled ${selectedSeatIndices.size} seats`);
    }

    function bulkDeleteSeats() {
        if (selectedSeatIndices.size === 0) {
            alert('Please select seats to delete');
            return;
        }

        if (confirm(`Remove ${selectedSeatIndices.size} selected seats?`)) {
            const indicesToRemove = Array.from(selectedSeatIndices).sort((a, b) => b - a);
            indicesToRemove.forEach(index => {
                seatData.splice(index, 1);
            });

            selectedSeatIndices.clear();
            renderSeatLayout();
            updateSelectionCount();
            alert(`Removed ${indicesToRemove.length} seats`);
        }
    }

    function renderAislesLists() {
        // Render row aisles
        const rowContainer = document.getElementById('rowAislesList');
        if (rowAisles.length === 0) {
            rowContainer.innerHTML = '<div style="color: rgba(255,255,255,0.5); padding: 10px;">No row aisles configured. Add aisles between rows.</div>';
        } else {
            let html = '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
            rowAisles.forEach((aisle, idx) => {
                html += `<div class="aisle-item">
                        <i class="fas fa-grip-vertical"></i> Insert ${aisle.width} aisle row(s) after Row ${aisle.position}
                        <button type="button" class="remove-aisle" onclick="removeRowAisle(${idx})">×</button>
                    </div>`;
            });
            html += '</div>';
            rowContainer.innerHTML = html;
        }

        // Render column aisles (inserted BETWEEN columns, not replacing)
        const colContainer = document.getElementById('colAislesList');
        if (colAisles.length === 0) {
            colContainer.innerHTML = '<div style="color: rgba(255,255,255,0.5); padding: 10px;">No column aisles configured. Add aisles between columns.</div>';
        } else {
            let html = '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
            colAisles.forEach((aisle, idx) => {
                html += `<div class="aisle-item">
                        <i class="fas fa-grip-vertical"></i> Insert ${aisle.width} aisle column(s) after Column ${aisle.position}
                        <button type="button" class="remove-aisle" onclick="removeColAisle(${idx})">×</button>
                    </div>`;
            });
            html += '</div>';
            colContainer.innerHTML = html;
        }
    }

    function addRowAisle() {
        const position = parseInt(document.getElementById('new_row_aisle_position').value);
        const width = parseInt(document.getElementById('new_row_aisle_width').value);
        const rows = parseInt(document.getElementById('total_rows').value) || 0;

        if (!position || position < 1 || position >= rows) {
            alert(`Please enter a valid row number between 1 and ${rows - 1}`);
            return;
        }

        if (width < 1) {
            alert('Aisle width must be at least 1');
            return;
        }

        // Check if aisle already exists at this position
        if (!rowAisles.some(a => a.position === position)) {
            rowAisles.push({ position: position, width: width });
            rowAisles.sort((a, b) => a.position - b.position);
            renderAislesLists();
            renderSeatLayout();
        } else {
            alert('Aisle already exists after this row');
        }
    }

    function removeRowAisle(index) {
        rowAisles.splice(index, 1);
        renderAislesLists();
        renderSeatLayout();
    }

    function addColAisle() {
        const position = parseInt(document.getElementById('new_col_aisle_position').value);
        const width = parseInt(document.getElementById('new_col_aisle_width').value);
        const cols = parseInt(document.getElementById('total_columns').value) || 0;

        if (!position || position < 1 || position >= cols) {
            alert(`Please enter a valid column number between 1 and ${cols - 1}`);
            return;
        }

        if (width < 1) {
            alert('Aisle width must be at least 1');
            return;
        }

        // Check if aisle already exists at this position
        if (!colAisles.some(a => a.position === position)) {
            colAisles.push({ position: position, width: width });
            colAisles.sort((a, b) => a.position - b.position);
            renderAislesLists();
            renderSeatLayout();
        } else {
            alert('Aisle already exists after this column');
        }
    }

    function removeColAisle(index) {
        colAisles.splice(index, 1);
        renderAislesLists();
        renderSeatLayout();
    }

    // Helper function to check if a column has an aisle inserted BEFORE it
    // Column aisles are inserted BETWEEN columns, so column numbering shifts
    function getColumnMapping(totalColumns, colAisles) {
        const mapping = [];
        let currentCol = 1;

        for (let originalCol = 1; originalCol <= totalColumns; originalCol++) {
            mapping.push({ originalCol: originalCol, displayCol: currentCol });
            currentCol++;

            // Check if there's an aisle after this column
            const aisleAfter = colAisles.find(a => a.position === originalCol);
            if (aisleAfter) {
                for (let i = 0; i < aisleAfter.width; i++) {
                    mapping.push({ isAisle: true, aisleWidth: aisleAfter.width });
                    currentCol++;
                }
            }
        }

        return mapping;
    }

    function isPositionAisle(displayCol, colMapping) {
        if (displayCol > colMapping.length) return false;
        const item = colMapping[displayCol - 1];
        return item && item.isAisle === true;
    }

    function getOriginalColumn(displayCol, colMapping) {
        if (displayCol > colMapping.length) return null;
        const item = colMapping[displayCol - 1];
        return item.isAisle ? null : item.originalCol;
    }

    function renderSeatLayout() {
        const container = document.getElementById('seatLayoutEditor');
        const rows = parseInt(document.getElementById('total_rows').value) || 0;
        const cols = parseInt(document.getElementById('total_columns').value) || 0;

        if (rows === 0 || cols === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">Enter rows and columns to start designing</div>';
            return;
        }

        // Build column mapping to handle column aisles (inserted BETWEEN seats)
        const colMapping = getColumnMapping(cols, colAisles);

        let html = '';
        let currentRow = 1;
        let rowCounter = 1;

        while (currentRow <= rows) {
            const rowLetter = String.fromCharCode(64 + rowCounter);

            // Check if there's a row aisle before this row (inserted BETWEEN rows)
            const rowAisleBefore = rowAisles.find(a => a.position === currentRow - 1);
            if (rowAisleBefore && currentRow > 1) {
                const aisleWidth = rowAisleBefore.width;
                for (let i = 0; i < aisleWidth; i++) {
                    html += `<div class="seat-row" style="justify-content: center; margin: 5px 0;">
                            <div class="seat-cell aisle-gap" style="width: 100%; text-align: center; background: #2c3e50; border: 2px dashed #e74c3c;">
                                <i class="fas fa-grip-vertical"></i> AISLE
                            </div>
                        </div>`;
                }
            }

            html += `<div class="seat-row">`;
            html += `<div class="seat-row-label">${rowLetter}</div>`;

            // Render columns based on mapping (includes aisle gaps BETWEEN seats)
            for (let displayCol = 1; displayCol <= colMapping.length; displayCol++) {
                const mappingItem = colMapping[displayCol - 1];

                if (mappingItem.isAisle) {
                    // Render aisle gap (spacing between seat columns)
                    html += `<div class="seat-cell aisle-gap" style="background: #2c3e50; border: 2px dashed #e74c3c;">
                            <div class="seat-number">AISLE</div>
                        </div>`;
                } else {
                    const originalCol = mappingItem.originalCol;
                    const seatIndex = seatData.findIndex(s => s.seat_row === rowLetter && s.seat_column === originalCol);
                    const isSelected = seatIndex !== -1 && selectedSeatIndices.has(seatIndex);

                    if (seatIndex !== -1 && seatData[seatIndex].is_enabled) {
                        const typeName = seatData[seatIndex].seat_type_name;
                        if (typeName && seatTypeConfig[typeName]) {
                            const config = seatTypeConfig[typeName];
                            html += `<div class="seat-cell ${isSelected ? 'selected' : ''}" 
                                    style="background: ${config.color}; color: white; cursor: pointer; ${isSelected ? 'border: 3px solid #f39c12;' : ''}"
                                    onclick="toggleSeatSelection(${seatIndex}, event)"
                                    oncontextmenu="editSeat(${seatIndex}); return false;">
                                    <div class="seat-number">${rowLetter}${String(originalCol).padStart(2, '0')}</div>
                                    <div class="seat-type-badge">${config.label}</div>
                                </div>`;
                        } else {
                            html += `<div class="seat-cell ${isSelected ? 'selected' : ''}" 
                                    style="background: #444; color: white; cursor: pointer; ${isSelected ? 'border: 3px solid #f39c12;' : ''}"
                                    onclick="toggleSeatSelection(${seatIndex}, event)"
                                    oncontextmenu="editSeat(${seatIndex}); return false;">
                                    <div class="seat-number">${rowLetter}${String(originalCol).padStart(2, '0')}</div>
                                    <div class="seat-type-badge">EMPTY</div>
                                </div>`;
                        }
                    } else if (seatIndex !== -1 && !seatData[seatIndex].is_enabled) {
                        html += `<div class="seat-cell" style="background: #555; border: 2px dashed #888; opacity: 0.5; cursor: pointer;"
                                onclick="enableSeat(${seatIndex})">
                                <div class="seat-number">${rowLetter}${String(originalCol).padStart(2, '0')}</div>
                                <div class="seat-type-badge">DISABLED</div>
                            </div>`;
                    } else {
                        html += `<div class="seat-cell" style="background: #555; border: 2px dashed #888; opacity: 0.6; cursor: pointer;"
                                onclick="addSeat('${rowLetter}', ${originalCol})">
                                <div class="seat-number">+</div>
                                <div class="seat-type-badge">ADD</div>
                            </div>`;
                    }
                }
            }
            html += `</div>`;

            currentRow++;
            rowCounter++;
        }

        container.innerHTML = html;
        updateSelectionCount();
    }

    function addSeat(rowLetter, col) {
        seatData.push({
            seat_plan_id: 0,
            seat_row: rowLetter,
            seat_column: col,
            seat_number: `${rowLetter}${String(col).padStart(2, '0')}`,
            seat_type_name: null,
            is_enabled: 1,
            custom_price: null
        });
        renderSeatLayout();
    }

    function enableSeat(index) {
        if (seatData[index]) {
            seatData[index].is_enabled = 1;
            renderSeatLayout();
        }
    }

    function editSeat(index) {
        const seat = seatData[index];
        if (!seat) return;

        const seatTypes = <?php echo json_encode($seat_types); ?>;
        let typeOptions = '';
        typeOptions += `<button type="button" class="btn btn-warning" style="margin: 5px; padding: 10px 20px;" onclick="updateSeatType(${index}, null)">
                        <i class="fas fa-eraser"></i> Empty (No Type)
                    </button>`;
        seatTypes.forEach(type => {
            const isSelected = seat.seat_type_name === type['name'];
            typeOptions += `<button type="button" class="btn ${isSelected ? 'btn-primary' : 'btn-secondary'}" style="margin: 5px; padding: 10px 20px; background: ${isSelected ? type['color_code'] : ''};" onclick="updateSeatType(${index}, '${type['name']}')">
                            <span style="color: ${type['color_code']};">●</span> ${type['name']} (₱${type['default_price']})
                        </button>`;
        });

        const modalHtml = `
        <div id="seatEditModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10000; display: flex; justify-content: center; align-items: center;">
            <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px;">
                <h3 style="color: white; margin-bottom: 20px;">Edit Seat ${seat.seat_number}</h3>
                <div style="margin-bottom: 20px;">
                    <label style="color: white; display: block; margin-bottom: 10px;">Seat Type:</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        ${typeOptions}
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="color: white; display: block; margin-bottom: 10px;">Custom Price (optional):</label>
                    <input type="number" id="edit_custom_price" step="0.01" value="${seat.custom_price || ''}" placeholder="Use default price" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.08); border: 2px solid rgba(155,89,182,0.3); border-radius: 8px; color: white;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="seat_enabled" ${seat.is_enabled ? 'checked' : ''}>
                        <span style="color: white;">Seat Enabled (available for booking)</span>
                    </label>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button onclick="saveSeatChanges(${index})" class="btn btn-primary">Save Changes</button>
                    <button onclick="deleteSeat(${index})" class="btn btn-danger">Remove Seat</button>
                    <button onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
        </div>
    `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function updateSeatType(index, seatType) {
        if (seatData[index]) {
            seatData[index].seat_type_name = seatType;
            renderSeatLayout();
            closeEditModal();
        }
    }

    function saveSeatChanges(index) {
        const customPrice = document.getElementById('edit_custom_price').value;
        const isEnabled = document.getElementById('seat_enabled').checked;

        if (seatData[index]) {
            seatData[index].custom_price = customPrice ? parseFloat(customPrice) : null;
            seatData[index].is_enabled = isEnabled ? 1 : 0;
        }

        closeEditModal();
        renderSeatLayout();
    }

    function deleteSeat(index) {
        if (confirm('Remove this seat from the layout?')) {
            seatData.splice(index, 1);
            selectedSeatIndices.clear();
            closeEditModal();
            renderSeatLayout();
        }
    }

    function closeEditModal() {
        const modal = document.getElementById('seatEditModal');
        if (modal) modal.remove();
    }

    function generateEmptyGrid() {
        const rows = parseInt(document.getElementById('total_rows').value) || 0;
        const cols = parseInt(document.getElementById('total_columns').value) || 0;

        if (rows === 0 || cols === 0) {
            alert('Please set total rows and columns first');
            return;
        }

        seatData = [];
        selectedSeatIndices.clear();

        renderSeatLayout();
        alert(`Created empty grid layout with ${rows} rows and ${cols} columns. Add seats by clicking the + buttons.`);
    }

    function generateSeatsGrid() {
        const rows = parseInt(document.getElementById('total_rows').value) || 0;
        const cols = parseInt(document.getElementById('total_columns').value) || 0;

        if (rows === 0 || cols === 0) {
            alert('Please set total rows and columns first');
            return;
        }

        seatData = [];
        selectedSeatIndices.clear();

        for (let row = 1; row <= rows; row++) {
            const rowLetter = String.fromCharCode(64 + row);

            for (let col = 1; col <= cols; col++) {
                // Determine seat type based on row position
                let seatType = null;
                if (row <= 3) {
                    seatType = 'Premium';
                } else if (row >= rows - 2 && rows > 5) {
                    seatType = 'Sweet Spot';
                } else {
                    seatType = 'Standard';
                }

                seatData.push({
                    seat_plan_id: 0,
                    seat_row: rowLetter,
                    seat_column: col,
                    seat_number: `${rowLetter}${String(col).padStart(2, '0')}`,
                    seat_type_name: seatType,
                    is_enabled: 1,
                    custom_price: null
                });
            }
        }

        renderSeatLayout();
        alert(`Generated ${seatData.length} seats with auto-assigned types.\n- Top 3 rows: Premium\n- Last 3 rows: Sweet Spot\n- Middle rows: Standard\nSelect seats and use bulk actions to modify types as needed.`);
    }

    function clearAllSeats() {
        if (confirm('Remove ALL seats from this layout? This action cannot be undone.')) {
            seatData = [];
            selectedSeatIndices.clear();
            renderSeatLayout();
        }
    }

    function fillEmptySeats() {
        const rows = parseInt(document.getElementById('total_rows').value) || 0;
        const cols = parseInt(document.getElementById('total_columns').value) || 0;

        let addedCount = 0;

        for (let row = 1; row <= rows; row++) {
            const rowLetter = String.fromCharCode(64 + row);

            for (let col = 1; col <= cols; col++) {
                const exists = seatData.some(s => s.seat_row === rowLetter && s.seat_column === col);

                if (!exists) {
                    let seatType = null;
                    if (row <= 3) {
                        seatType = 'Premium';
                    } else if (row >= rows - 2 && rows > 5) {
                        seatType = 'Sweet Spot';
                    }

                    seatData.push({
                        seat_plan_id: 0,
                        seat_row: rowLetter,
                        seat_column: col,
                        seat_number: `${rowLetter}${String(col).padStart(2, '0')}`,
                        seat_type_name: seatType,
                        is_enabled: 1,
                        custom_price: null
                    });
                    addedCount++;
                }
            }
        }

        renderSeatLayout();
        alert(`Added ${addedCount} new seats. Total seats: ${seatData.length}`);
    }

    // Event Listeners
    document.getElementById('generateEmptyGridBtn')?.addEventListener('click', generateEmptyGrid);
    document.getElementById('generateSeatsGridBtn')?.addEventListener('click', generateSeatsGrid);
    document.getElementById('clearAllSeatsBtn')?.addEventListener('click', clearAllSeats);
    document.getElementById('fillEmptySeatsBtn')?.addEventListener('click', fillEmptySeats);
    document.getElementById('selectAllBtn')?.addEventListener('click', selectAllSeats);
    document.getElementById('clearSelectionBtn')?.addEventListener('click', clearSelection);
    document.getElementById('bulkEnableBtn')?.addEventListener('click', bulkEnableSeats);
    document.getElementById('bulkDisableBtn')?.addEventListener('click', bulkDisableSeats);
    document.getElementById('bulkDeleteBtn')?.addEventListener('click', bulkDeleteSeats);
    document.getElementById('bulkClearTypeBtn')?.addEventListener('click', bulkClearSeatType);
    document.getElementById('addRowAisleBtn')?.addEventListener('click', addRowAisle);
    document.getElementById('addColAisleBtn')?.addEventListener('click', addColAisle);

    // Bulk type buttons
    document.querySelectorAll('.bulk-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const seatType = btn.dataset.type;
            bulkAssignSeatType(seatType);
        });
    });

    document.getElementById('total_rows')?.addEventListener('input', () => {
        renderSeatLayout();
    });
    document.getElementById('total_columns')?.addEventListener('input', () => {
        renderSeatLayout();
    });

    document.getElementById('seatPlanForm')?.addEventListener('submit', function (e) {
        const seatDataInput = document.getElementById('seat_data_input');
        const seatsToSave = seatData.map(seat => ({
            row: seat.seat_row,
            col: seat.seat_column,
            type: seat.seat_type_name,
            enabled: seat.is_enabled,
            custom_price: seat.custom_price
        }));
        seatDataInput.value = JSON.stringify(seatsToSave);

        const aisleDataInput = document.getElementById('aisle_data_input');
        const aislesToSave = [];
        rowAisles.forEach(aisle => {
            aislesToSave.push({
                position_type: 'row',
                position: aisle.position,
                width: aisle.width,
                type: 'Row Aisle'
            });
        });
        colAisles.forEach(aisle => {
            aislesToSave.push({
                position_type: 'column',
                position: aisle.position,
                width: aisle.width,
                type: 'Column Aisle'
            });
        });
        aisleDataInput.value = JSON.stringify(aislesToSave);

        const rowsInput = document.createElement('input');
        rowsInput.type = 'hidden';
        rowsInput.name = 'total_rows';
        rowsInput.value = document.getElementById('total_rows').value;
        this.appendChild(rowsInput);

        const colsInput = document.createElement('input');
        colsInput.type = 'hidden';
        colsInput.name = 'total_columns';
        colsInput.value = document.getElementById('total_columns').value;
        this.appendChild(colsInput);

        return true;
    });

    // Initial render
    renderAislesLists();
    renderSeatLayout();
</script>

<?php
// Close the database connection at the very end - ONLY ONCE
if (isset($conn) && $conn) {
    $conn->close();
}
?>
</body>

</html>