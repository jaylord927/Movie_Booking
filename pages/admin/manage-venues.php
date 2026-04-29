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

// Open connection
$conn = get_db_connection();

$error = '';
$success = '';
$edit_venue = null;
$add_screen_venue_id = isset($_GET['add_screen']) ? intval($_GET['add_screen']) : 0;
$edit_screen_id = isset($_GET['edit_screen']) ? intval($_GET['edit_screen']) : 0;

// ============================================
// VENUE MANAGEMENT
// ============================================

// Add Venue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_venue'])) {
    $venue_name = htmlspecialchars(trim($_POST['venue_name']), ENT_QUOTES, 'UTF-8');
    $venue_location = htmlspecialchars(trim($_POST['venue_location']), ENT_QUOTES, 'UTF-8');
    $google_maps_link = trim($_POST['google_maps_link'] ?? '');
    $contact_number = htmlspecialchars(trim($_POST['contact_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $operating_hours = htmlspecialchars(trim($_POST['operating_hours'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    $venue_upload_dir = $root_dir . "/uploads/venue/";
    if (!file_exists($venue_upload_dir)) {
        mkdir($venue_upload_dir, 0777, true);
    }
    
    $venue_photo_path = '';
    if (isset($_FILES['venue_photo']) && $_FILES['venue_photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        if (in_array($_FILES['venue_photo']['type'], $allowed_types) && $_FILES['venue_photo']['size'] <= 5000000) {
            $extension = pathinfo($_FILES['venue_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'venue_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            if (move_uploaded_file($_FILES['venue_photo']['tmp_name'], $venue_upload_dir . $filename)) {
                $venue_photo_path = 'uploads/venue/' . $filename;
            } else {
                $error = "Failed to upload venue photo.";
            }
        } else {
            $error = "Invalid file type or file too large (max 5MB).";
        }
    }
    
    if (empty($venue_name) || empty($venue_location)) {
        $error = "Venue name and location are required!";
    } elseif (empty($error)) {
        $check_stmt = $conn->prepare("SELECT id FROM venues WHERE venue_name = ?");
        $check_stmt->bind_param("s", $venue_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "A venue with this name already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO venues (venue_name, venue_location, google_maps_link, venue_photo_path, contact_number, operating_hours, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssssss", $venue_name, $venue_location, $google_maps_link, $venue_photo_path, $contact_number, $operating_hours);
            if ($stmt->execute()) {
                $success = "Venue added successfully!";
                $_POST = array();
            } else {
                $error = "Failed to add venue: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Edit Venue - Load data
if (isset($_GET['edit_venue']) && is_numeric($_GET['edit_venue'])) {
    $edit_id = intval($_GET['edit_venue']);
    $stmt = $conn->prepare("SELECT * FROM venues WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_venue = $result->fetch_assoc();
    $stmt->close();
}

// Update Venue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_venue'])) {
    $id = intval($_POST['id']);
    $venue_name = htmlspecialchars(trim($_POST['venue_name']), ENT_QUOTES, 'UTF-8');
    $venue_location = htmlspecialchars(trim($_POST['venue_location']), ENT_QUOTES, 'UTF-8');
    $google_maps_link = trim($_POST['google_maps_link'] ?? '');
    $contact_number = htmlspecialchars(trim($_POST['contact_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $operating_hours = htmlspecialchars(trim($_POST['operating_hours'] ?? ''), ENT_QUOTES, 'UTF-8');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $venue_upload_dir = $root_dir . "/uploads/venue/";
    if (!file_exists($venue_upload_dir)) {
        mkdir($venue_upload_dir, 0777, true);
    }
    
    $venue_photo_path = null;
    $has_new_photo = false;
    
    if (isset($_FILES['venue_photo']) && $_FILES['venue_photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        if (in_array($_FILES['venue_photo']['type'], $allowed_types) && $_FILES['venue_photo']['size'] <= 5000000) {
            $extension = pathinfo($_FILES['venue_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'venue_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            if (move_uploaded_file($_FILES['venue_photo']['tmp_name'], $venue_upload_dir . $filename)) {
                $venue_photo_path = 'uploads/venue/' . $filename;
                $has_new_photo = true;
            } else {
                $error = "Failed to upload venue photo.";
            }
        } else {
            $error = "Invalid file type or file too large (max 5MB).";
        }
    }
    
    $remove_photo = isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1';
    
    if (empty($venue_name) || empty($venue_location)) {
        $error = "Venue name and location are required!";
    } elseif (empty($error)) {
        // Check if venue name exists for other venues
        $check_stmt = $conn->prepare("SELECT id FROM venues WHERE venue_name = ? AND id != ?");
        $check_stmt->bind_param("si", $venue_name, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "A venue with this name already exists!";
        } else {
            if ($has_new_photo || $remove_photo) {
                // Get old photo to delete
                $old_photo_stmt = $conn->prepare("SELECT venue_photo_path FROM venues WHERE id = ?");
                $old_photo_stmt->bind_param("i", $id);
                $old_photo_stmt->execute();
                $old_photo_result = $old_photo_stmt->get_result();
                $old_photo_data = $old_photo_result->fetch_assoc();
                $old_photo_stmt->close();
                
                if ($old_photo_data && !empty($old_photo_data['venue_photo_path']) && file_exists($root_dir . '/' . $old_photo_data['venue_photo_path'])) {
                    unlink($root_dir . '/' . $old_photo_data['venue_photo_path']);
                }
            }
            
            if ($has_new_photo) {
                $stmt = $conn->prepare("UPDATE venues SET venue_name = ?, venue_location = ?, google_maps_link = ?, venue_photo_path = ?, contact_number = ?, operating_hours = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("ssssssii", $venue_name, $venue_location, $google_maps_link, $venue_photo_path, $contact_number, $operating_hours, $is_active, $id);
            } elseif ($remove_photo) {
                $stmt = $conn->prepare("UPDATE venues SET venue_name = ?, venue_location = ?, google_maps_link = ?, venue_photo_path = NULL, contact_number = ?, operating_hours = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("sssssii", $venue_name, $venue_location, $google_maps_link, $contact_number, $operating_hours, $is_active, $id);
            } else {
                $stmt = $conn->prepare("UPDATE venues SET venue_name = ?, venue_location = ?, google_maps_link = ?, contact_number = ?, operating_hours = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("sssssii", $venue_name, $venue_location, $google_maps_link, $contact_number, $operating_hours, $is_active, $id);
            }
            
            if ($stmt->execute()) {
                $success = "Venue updated successfully!";
                $edit_venue = null;
            } else {
                $error = "Failed to update venue: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Delete Venue
if (isset($_GET['delete_venue']) && is_numeric($_GET['delete_venue'])) {
    $id = intval($_GET['delete_venue']);
    
    // Check if venue has screens
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM screens WHERE venue_id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $screen_count = $result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($screen_count > 0) {
        $error = "Cannot delete venue with $screen_count screen(s). Delete the screens first.";
    } else {
        // Get photo to delete
        $photo_stmt = $conn->prepare("SELECT venue_photo_path FROM venues WHERE id = ?");
        $photo_stmt->bind_param("i", $id);
        $photo_stmt->execute();
        $photo_result = $photo_stmt->get_result();
        $photo_data = $photo_result->fetch_assoc();
        $photo_stmt->close();
        
        if ($photo_data && !empty($photo_data['venue_photo_path']) && file_exists($root_dir . '/' . $photo_data['venue_photo_path'])) {
            unlink($root_dir . '/' . $photo_data['venue_photo_path']);
        }
        
        $stmt = $conn->prepare("DELETE FROM venues WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Venue deleted successfully!";
        } else {
            $error = "Failed to delete venue: " . $conn->error;
        }
        $stmt->close();
    }
}

// ============================================
// SCREEN MANAGEMENT
// ============================================

// Add Screen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_screen'])) {
    $venue_id = intval($_POST['venue_id']);
    $screen_name = htmlspecialchars(trim($_POST['screen_name']), ENT_QUOTES, 'UTF-8');
    $screen_number = intval($_POST['screen_number']);
    $capacity = intval($_POST['capacity']);
    
    $check_stmt = $conn->prepare("SELECT id FROM screens WHERE venue_id = ? AND screen_number = ?");
    $check_stmt->bind_param("ii", $venue_id, $screen_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = "Screen number $screen_number already exists for this venue!";
    } else {
        $stmt = $conn->prepare("INSERT INTO screens (venue_id, screen_name, screen_number, capacity, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("isii", $venue_id, $screen_name, $screen_number, $capacity);
        if ($stmt->execute()) {
            $success = "Screen added successfully! Now you can create a seat plan for this screen.";
            $add_screen_venue_id = 0;
        } else {
            $error = "Failed to add screen: " . $conn->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Update Screen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_screen'])) {
    $screen_id = intval($_POST['screen_id']);
    $screen_name = htmlspecialchars(trim($_POST['screen_name']), ENT_QUOTES, 'UTF-8');
    $screen_number = intval($_POST['screen_number']);
    $capacity = intval($_POST['capacity']);
    $is_active = isset($_POST['is_active_screen']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE screens SET screen_name = ?, screen_number = ?, capacity = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("siiii", $screen_name, $screen_number, $capacity, $is_active, $screen_id);
    if ($stmt->execute()) {
        $success = "Screen updated successfully!";
        $edit_screen_id = 0;
    } else {
        $error = "Failed to update screen: " . $conn->error;
    }
    $stmt->close();
}

// Delete Screen
if (isset($_GET['delete_screen']) && is_numeric($_GET['delete_screen'])) {
    $screen_id = intval($_GET['delete_screen']);
    
    // Check if screen has schedules
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM schedules WHERE screen_id = ? AND is_active = 1");
    $check_stmt->bind_param("i", $screen_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $schedule_count = $result->fetch_assoc()['count'];
    $check_stmt->close();
    
    // Check if screen has seat plans
    $plan_stmt = $conn->prepare("SELECT COUNT(*) as count FROM seat_plans WHERE screen_id = ?");
    $plan_stmt->bind_param("i", $screen_id);
    $plan_stmt->execute();
    $plan_result = $plan_stmt->get_result();
    $plan_count = $plan_result->fetch_assoc()['count'];
    $plan_stmt->close();
    
    if ($schedule_count > 0) {
        $error = "Cannot delete screen with $schedule_count active schedule(s).";
    } elseif ($plan_count > 0) {
        $error = "Cannot delete screen with $plan_count seat plan(s). Delete the seat plans first.";
    } else {
        $stmt = $conn->prepare("DELETE FROM screens WHERE id = ?");
        $stmt->bind_param("i", $screen_id);
        if ($stmt->execute()) {
            $success = "Screen deleted successfully!";
        } else {
            $error = "Failed to delete screen: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get edit screen data if needed
$edit_screen_data = null;
if ($edit_screen_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, v.venue_name FROM screens s JOIN venues v ON s.venue_id = v.id WHERE s.id = ?");
    $stmt->bind_param("i", $edit_screen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_screen_data = $result->fetch_assoc();
    $stmt->close();
}

// ============================================
// FETCH DATA FOR DISPLAY
// ============================================

// Get all venues with screen and seat plan counts
$venues_result = $conn->query("
    SELECT 
        v.*, 
        COUNT(DISTINCT s.id) as screen_count,
        COUNT(DISTINCT sp.id) as seat_plan_count,
        SUM(s.capacity) as total_capacity
    FROM venues v
    LEFT JOIN screens s ON v.id = s.venue_id AND s.is_active = 1
    LEFT JOIN seat_plans sp ON s.id = sp.screen_id AND sp.is_active = 1
    WHERE v.is_active = 1
    GROUP BY v.id
    ORDER BY v.created_at DESC
");

$venues = [];
if ($venues_result) {
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
}

// Get screens for a specific venue (for add screen modal)
$venue_screens = null;
if ($add_screen_venue_id > 0) {
    $screens_stmt = $conn->prepare("SELECT * FROM screens WHERE venue_id = ? AND is_active = 1 ORDER BY screen_number");
    $screens_stmt->bind_param("i", $add_screen_venue_id);
    $screens_stmt->execute();
    $venue_screens = $screens_stmt->get_result();
    $screens_stmt->close();
}

// Close connection at the very end
// Note: Don't close yet - admin-header.php needs it
// $conn->close(); - Will close after all output
?>

<div class="admin-content" style="max-width: 1400px; margin: 0 auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 40px; padding: 30px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); border-radius: 20px; border: 2px solid rgba(52, 152, 219, 0.3);">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Manage Venues & Screens</h1>
        <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">Each physical location is a unique venue with its own screens and seat layouts</p>
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

    <!-- Add/Edit Venue Form -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas <?php echo $edit_venue ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
            <?php echo $edit_venue ? 'Edit Venue' : 'Add New Venue'; ?>
        </h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="venueForm">
            <?php if ($edit_venue): ?>
            <input type="hidden" name="id" value="<?php echo $edit_venue['id']; ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-building"></i> Venue Name *
                    </label>
                    <input type="text" name="venue_name" required 
                           value="<?php echo $edit_venue ? htmlspecialchars($edit_venue['venue_name']) : (isset($_POST['venue_name']) ? htmlspecialchars($_POST['venue_name']) : ''); ?>"
                           placeholder="e.g., SM Cinema – SM Seaside"
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-map-marker-alt"></i> Location *
                    </label>
                    <input type="text" name="venue_location" required 
                           value="<?php echo $edit_venue ? htmlspecialchars($edit_venue['venue_location']) : (isset($_POST['venue_location']) ? htmlspecialchars($_POST['venue_location']) : ''); ?>"
                           placeholder="Full address"
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-phone"></i> Contact Number
                    </label>
                    <input type="text" name="contact_number" 
                           value="<?php echo $edit_venue ? htmlspecialchars($edit_venue['contact_number'] ?? '') : (isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''); ?>"
                           placeholder="e.g., (032) 123-4567"
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
                </div>
                
                <div>
                    <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-clock"></i> Operating Hours
                    </label>
                    <input type="text" name="operating_hours" 
                           value="<?php echo $edit_venue ? htmlspecialchars($edit_venue['operating_hours'] ?? '') : (isset($_POST['operating_hours']) ? htmlspecialchars($_POST['operating_hours']) : ''); ?>"
                           placeholder="e.g., 10:00 AM - 9:00 PM"
                           style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                    <i class="fas fa-map"></i> Google Maps Link
                </label>
                <input type="text" name="google_maps_link" 
                       value="<?php echo $edit_venue ? htmlspecialchars($edit_venue['google_maps_link'] ?? '') : (isset($_POST['google_maps_link']) ? htmlspecialchars($_POST['google_maps_link']) : ''); ?>"
                       placeholder="https://www.google.com/maps/..."
                       style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
            </div>
            
            <!-- Venue Photo Upload -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                    <i class="fas fa-camera"></i> Venue Photo
                </label>
                <?php if ($edit_venue && !empty($edit_venue['venue_photo_path'])): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 8px; display: flex; align-items: center; gap: 15px;">
                    <img src="<?php echo SITE_URL . $edit_venue['venue_photo_path']; ?>" 
                         alt="Current Venue Photo" style="width: 80px; height: 60px; object-fit: cover; border-radius: 5px;">
                    <div>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">Current photo</p>
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="checkbox" name="remove_photo" value="1">
                            <span style="color: #e74c3c; font-size: 0.8rem;">Remove current photo</span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" name="venue_photo" accept="image/*"
                       style="width: 100%; padding: 10px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 8px; color: white;">
                <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem; margin-top: 5px;">
                    JPG, PNG, GIF, WEBP (Max 5MB)
                </div>
            </div>
            
            <?php if ($edit_venue): ?>
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">
                    <i class="fas fa-toggle-on"></i> Status
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?php echo $edit_venue['is_active'] ? 'checked' : ''; ?>>
                    <span style="color: white;">Active</span>
                </label>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" name="<?php echo $edit_venue ? 'update_venue' : 'add_venue'; ?>" style="padding: 12px 30px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
                    <i class="fas <?php echo $edit_venue ? 'fa-save' : 'fa-plus'; ?>"></i> <?php echo $edit_venue ? 'Update Venue' : 'Add Venue'; ?>
                </button>
                <?php if ($edit_venue): ?>
                <a href="?page=admin/manage-venues" style="padding: 12px 30px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 8px; margin-left: 10px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Venues List -->
    <div style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 30px; border: 1px solid rgba(52, 152, 219, 0.2);">
        <h2 style="color: white; font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-building"></i> Venues (<?php echo count($venues); ?>)
        </h2>
        
        <?php if (empty($venues)): ?>
            <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
                <i class="fas fa-building fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No venues found. Add your first venue!</p>
            </div>
        <?php else: ?>
            <?php foreach ($venues as $venue): ?>
                <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid rgba(52,152,219,0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <h3 style="color: #3498db; font-size: 1.3rem; margin: 0;">
                                    <?php echo htmlspecialchars($venue['venue_name']); ?>
                                </h3>
                                <span style="background: <?php echo $venue['is_active'] ? 'rgba(46,204,113,0.2)' : 'rgba(231,76,60,0.2)'; ?>; 
                                      color: <?php echo $venue['is_active'] ? '#2ecc71' : '#e74c3c'; ?>; 
                                      padding: 3px 10px; border-radius: 15px; font-size: 0.7rem; font-weight: 600;">
                                    <?php echo $venue['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <p style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-top: 5px;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($venue['venue_location']); ?>
                            </p>
                            <?php if (!empty($venue['contact_number'])): ?>
                                <p style="color: rgba(255,255,255,0.5); font-size: 0.8rem; margin-top: 3px;">
                                    <i class="fas fa-phone"></i> <?php echo $venue['contact_number']; ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($venue['operating_hours'])): ?>
                                <p style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">
                                    <i class="fas fa-clock"></i> <?php echo $venue['operating_hours']; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <div style="display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; justify-content: flex-end;">
                                <span style="background: rgba(52,152,219,0.2); color: #3498db; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;">
                                    <i class="fas fa-tv"></i> <?php echo $venue['screen_count']; ?> Screens
                                </span>
                                <span style="background: rgba(155,89,182,0.2); color: #9b59b6; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;">
                                    <i class="fas fa-chair"></i> <?php echo $venue['seat_plan_count']; ?> Seat Plans
                                </span>
                                <span style="background: rgba(46,204,113,0.2); color: #2ecc71; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;">
                                    <i class="fas fa-users"></i> Capacity: <?php echo number_format($venue['total_capacity']); ?>
                                </span>
                            </div>
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <a href="?page=admin/manage-venues&edit_venue=<?php echo $venue['id']; ?>" style="color: #3498db; text-decoration: none; font-size: 0.8rem;">
                                    <i class="fas fa-edit"></i> Edit Venue
                                </a>
                                <a href="?page=admin/manage-venues&add_screen=<?php echo $venue['id']; ?>" style="color: #2ecc71; text-decoration: none; font-size: 0.8rem;">
                                    <i class="fas fa-plus-circle"></i> Add Screen
                                </a>
                                <a href="?page=admin/manage-venues&delete_venue=<?php echo $venue['id']; ?>" onclick="return confirm('Delete venue \'<?php echo addslashes($venue['venue_name']); ?>\'? This will also delete all screens and seat plans.')" style="color: #e74c3c; text-decoration: none; font-size: 0.8rem;">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Screens for this venue -->
                    <?php
                    // Get screens for this venue
                    $screens_query = $conn->prepare("
                        SELECT s.*, COUNT(sp.id) as has_seat_plan 
                        FROM screens s
                        LEFT JOIN seat_plans sp ON s.id = sp.screen_id AND sp.is_active = 1
                        WHERE s.venue_id = ? AND s.is_active = 1
                        GROUP BY s.id
                        ORDER BY s.screen_number
                    ");
                    $screens_query->bind_param("i", $venue['id']);
                    $screens_query->execute();
                    $screens_for_venue = $screens_query->get_result();
                    $screens_query->close();
                    ?>
                    
                    <?php if ($screens_for_venue && $screens_for_venue->num_rows > 0): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <h4 style="color: #2ecc71; font-size: 0.9rem; margin-bottom: 10px;">Screens / Auditoriums:</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px;">
                                <?php while ($screen = $screens_for_venue->fetch_assoc()): ?>
                                    <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="color: white;"><?php echo htmlspecialchars($screen['screen_name']); ?></strong>
                                                <span style="color: #3498db; font-size: 0.7rem;">(#<?php echo $screen['screen_number']; ?>)</span>
                                                <div style="font-size: 0.7rem; color: rgba(255,255,255,0.5);">
                                                    <i class="fas fa-users"></i> Capacity: <?php echo number_format($screen['capacity']); ?> seats
                                                </div>
                                            </div>
                                            <div style="display: flex; gap: 8px;">
                                                <?php if ($screen['has_seat_plan'] > 0): ?>
                                                    <span style="background: #2ecc71; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.6rem;">
                                                        <i class="fas fa-check"></i> Plan Ready
                                                    </span>
                                                <?php else: ?>
                                                    <a href="?page=admin/manage-seats&screen_id=<?php echo $screen['id']; ?>" style="background: #f39c12; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.6rem; text-decoration: none;">
                                                        <i class="fas fa-plus"></i> Create Plan
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?page=admin/manage-venues&edit_screen=<?php echo $screen['id']; ?>" style="color: #3498db; font-size: 0.7rem; text-decoration: none;" title="Edit Screen">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?page=admin/manage-venues&delete_screen=<?php echo $screen['id']; ?>" onclick="return confirm('Delete screen \'<?php echo addslashes($screen['screen_name']); ?>\'? This will also delete any seat plans.')" style="color: #e74c3c; font-size: 0.7rem; text-decoration: none;" title="Delete Screen">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Add Screen Modal (shown when add_screen parameter is present) -->
    <?php if ($add_screen_venue_id > 0): ?>
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; display: flex; justify-content: center; align-items: center; padding: 20px;">
            <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; border: 2px solid #3498db;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: #3498db;">Add New Screen</h3>
                    <a href="?page=admin/manage-venues" style="color: white; font-size: 1.5rem; text-decoration: none;">&times;</a>
                </div>
                
                <?php
                // Get venue name for display
                $venue_name_query = $conn->prepare("SELECT venue_name FROM venues WHERE id = ?");
                $venue_name_query->bind_param("i", $add_screen_venue_id);
                $venue_name_query->execute();
                $venue_name_result = $venue_name_query->get_result();
                $current_venue = $venue_name_result->fetch_assoc();
                $venue_name_query->close();
                ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="venue_id" value="<?php echo $add_screen_venue_id; ?>">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Venue</label>
                        <input type="text" value="<?php echo htmlspecialchars($current_venue['venue_name']); ?>" disabled style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Screen Name *</label>
                        <input type="text" name="screen_name" required placeholder="e.g., Cinema 1, Hall A"
                               style="width: 100%; padding: 10px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Screen Number *</label>
                        <input type="number" name="screen_number" required min="1"
                               style="width: 100%; padding: 10px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Capacity (Total Seats) *</label>
                        <input type="number" name="capacity" required min="1"
                               style="width: 100%; padding: 10px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" name="add_screen" style="padding: 10px 25px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-save"></i> Add Screen
                        </button>
                        <a href="?page=admin/manage-venues" style="padding: 10px 25px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit Screen Modal -->
    <?php if ($edit_screen_id > 0 && $edit_screen_data): ?>
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; display: flex; justify-content: center; align-items: center; padding: 20px;">
            <div style="background: #2c3e50; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; border: 2px solid #3498db;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: #3498db;">Edit Screen</h3>
                    <a href="?page=admin/manage-venues" style="color: white; font-size: 1.5rem; text-decoration: none;">&times;</a>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="screen_id" value="<?php echo $edit_screen_data['id']; ?>">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Venue</label>
                        <input type="text" value="<?php echo htmlspecialchars($edit_screen_data['venue_name']); ?>" disabled style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Screen Name *</label>
                        <input type="text" name="screen_name" required value="<?php echo htmlspecialchars($edit_screen_data['screen_name']); ?>"
                               style="width: 100%; padding: 10px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Screen Number *</label>
                        <input type="number" name="screen_number" required min="1" value="<?php echo $edit_screen_data['screen_number']; ?>"
                               style="width: 100%; padding: 10px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Capacity *</label>
                        <input type="number" name="capacity" required min="1" value="<?php echo $edit_screen_data['capacity']; ?>"
                               style="width: 100%; padding: 10px; background: rgba(255,255,255,0.08); border: 2px solid rgba(52,152,219,0.3); border-radius: 6px; color: white;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; color: white; font-weight: 600; margin-bottom: 8px;">Status</label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_active_screen" value="1" <?php echo $edit_screen_data['is_active'] ? 'checked' : ''; ?>>
                            <span style="color: white;">Active</span>
                        </label>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" name="update_screen" style="padding: 10px 25px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-save"></i> Update Screen
                        </button>
                        <a href="?page=admin/manage-venues" style="padding: 10px 25px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; margin-left: 10px;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Note about seat plans -->
    <div style="margin-top: 30px; padding: 20px; background: rgba(52, 152, 219, 0.05); border-radius: 10px; border-left: 4px solid #3498db;">
        <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
            <i class="fas fa-info-circle" style="color: #3498db;"></i> 
            <strong>How it works:</strong> 
            1. Add a Venue → 2. Add Screens to the venue → 3. Create Seat Plans for each screen → 4. Add Movies → 5. Create Schedules
        </p>
    </div>
</div>

<style>
input:focus, select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,0.2);
}
button:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}
.venue-item:hover {
    background: rgba(52,152,219,0.05);
}
@media (max-width: 768px) {
    .admin-content { padding: 15px; }
    .admin-content > div { padding: 20px; }
}
</style>

<script>
document.getElementById('venueForm')?.addEventListener('submit', function(e) {
    const venueName = document.querySelector('input[name="venue_name"]')?.value.trim();
    const venueLocation = document.querySelector('input[name="venue_location"]')?.value.trim();
    
    if (!venueName || !venueLocation) {
        e.preventDefault();
        alert('Venue name and location are required!');
        return false;
    }
    return true;
});
</script>

<?php
// Close the database connection
if (isset($conn) && $conn) {
    $conn->close();
}
?>

</body>
</html>