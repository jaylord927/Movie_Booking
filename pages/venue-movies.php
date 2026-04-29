<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/header.php';

$conn = get_db_connection();

// Get venue by name from URL
$venue_name = isset($_GET['venue']) ? urldecode($_GET['venue']) : '';

if (empty($venue_name)) {
    header("Location: " . SITE_URL . "index.php?page=venue");
    exit();
}

// First, get the venue from the venues table
$venue_stmt = $conn->prepare("
    SELECT id, venue_name, venue_location, google_maps_link, venue_photo_path, contact_number, operating_hours, is_active
    FROM venues 
    WHERE venue_name = ? AND is_active = 1
");
$venue_stmt->bind_param("s", $venue_name);
$venue_stmt->execute();
$venue_result = $venue_stmt->get_result();
$venue = $venue_result->fetch_assoc();
$venue_stmt->close();

// If venue not found, redirect to venues page
if (!$venue) {
    header("Location: " . SITE_URL . "index.php?page=venue");
    exit();
}

// Generate embed URL from Google Maps link for visual map display
$embed_url = '';
$has_valid_map = false;

if (!empty($venue['google_maps_link'])) {
    if (preg_match('/q=([0-9.-]+),([0-9.-]+)/', $venue['google_maps_link'], $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
        $embed_url = "https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed";
        $has_valid_map = true;
    } elseif (preg_match('/@([0-9.-]+),([0-9.-]+)/', $venue['google_maps_link'], $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
        $embed_url = "https://maps.google.com/maps?q={$lat},{$lng}&z=15&output=embed";
        $has_valid_map = true;
    } else {
        $embed_url = "https://maps.google.com/maps?q=" . urlencode($venue['venue_location'] ?? $venue['venue_name'] ?? '') . "&z=15&output=embed";
        $has_valid_map = true;
    }
}

// Get all movies for this venue through screens
$movies_stmt = $conn->prepare("
    SELECT DISTINCT 
        m.id,
        m.title,
        m.director,
        m.genre,
        m.duration,
        m.rating,
        m.description,
        m.poster_url,
        m.trailer_url,
        m.is_active,
        m.created_at,
        GROUP_CONCAT(DISTINCT sc.screen_name ORDER BY sc.screen_number SEPARATOR ', ') as screens
    FROM movies m
    JOIN schedules s ON m.id = s.movie_id
    JOIN screens sc ON s.screen_id = sc.id
    WHERE sc.venue_id = ? 
    AND m.is_active = 1 
    AND s.is_active = 1
    GROUP BY m.id, m.title, m.director, m.genre, m.duration, m.rating, m.description, m.poster_url, m.trailer_url, m.is_active, m.created_at
    ORDER BY m.created_at DESC
");
$movies_stmt->bind_param("i", $venue['id']);
$movies_stmt->execute();
$movies_result = $movies_stmt->get_result();

$movies = [];
while ($row = $movies_result->fetch_assoc()) {
    $movies[] = $row;
}
$movies_stmt->close();

// Fetch schedules for each movie
$schedules = [];
foreach ($movies as $movie) {
    $schedule_stmt = $conn->prepare("
        SELECT 
            s.id as schedule_id,
            s.show_date,
            s.showtime,
            s.base_price,
            sc.screen_name,
            sc.screen_number,
            COUNT(sa.id) as total_seats,
            COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats
        FROM schedules s
        JOIN screens sc ON s.screen_id = sc.id
        LEFT JOIN seat_availability sa ON s.id = sa.schedule_id
        WHERE s.movie_id = ? 
        AND sc.venue_id = ?
        AND s.is_active = 1 
        AND s.show_date >= CURDATE()
        GROUP BY s.id, s.show_date, s.showtime, s.base_price, sc.screen_name, sc.screen_number
        HAVING available_seats > 0
        ORDER BY s.show_date, s.showtime
        LIMIT 10
    ");
    $schedule_stmt->bind_param("ii", $movie['id'], $venue['id']);
    $schedule_stmt->execute();
    $schedules_result = $schedule_stmt->get_result();
    $movie_schedules = [];
    while ($row = $schedules_result->fetch_assoc()) {
        $movie_schedules[] = $row;
    }
    $schedules[$movie['id']] = $movie_schedules;
    $schedule_stmt->close();
}

// Check which movies have schedules (Now Showing)
$now_showing_ids = [];
foreach ($movies as $movie) {
    if (!empty($schedules[$movie['id']])) {
        $now_showing_ids[] = $movie['id'];
    }
}

$conn->close();
?>

<div class="venue-movies-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Back Button -->
    <div style="margin-bottom: 20px;">
        <a href="javascript:history.back()" style="color: var(--light-red); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> Back to Venues
        </a>
    </div>

    <!-- Venue Header with Venue Photo and Map side by side -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; margin-bottom: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <!-- Venue Title and Info -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; margin-bottom: 25px;">
            <div>
                <h1 style="color: white; font-size: 2rem; margin-bottom: 10px; font-weight: 800;">
                    <i class="fas fa-building" style="color: var(--primary-red);"></i> 
                    <?php echo htmlspecialchars($venue['venue_name']); ?>
                </h1>
                <?php if (!empty($venue['venue_location'])): ?>
                <p style="color: var(--pale-red); font-size: 1rem; margin-bottom: 10px;">
                    <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($venue['venue_location']); ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($venue['operating_hours'])): ?>
                <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">
                    <i class="fas fa-clock"></i> <?php echo htmlspecialchars($venue['operating_hours']); ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($movies)): ?>
                <p style="color: #2ecc71; font-size: 0.9rem; margin-top: 8px;">
                    <i class="fas fa-film"></i> <?php echo count($movies); ?> movie<?php echo count($movies) != 1 ? 's' : ''; ?> currently available
                </p>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if (!empty($venue['google_maps_link'])): ?>
                <a href="<?php echo htmlspecialchars($venue['google_maps_link']); ?>" target="_blank" 
                   style="background: #e74c3c; color: white; padding: 12px 25px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease;"
                   onmouseover="this.style.background='#c0392b'; this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.background='#e74c3c'; this.style.transform='translateY(0)';">
                    <i class="fas fa-map-marked-alt"></i> Open in Google Maps
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Two Column Layout: Venue Photo + Google Map -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <!-- Venue Photo Column -->
            <div>
                <?php if (!empty($venue['venue_photo_path']) && file_exists($root_dir . '/' . $venue['venue_photo_path'])): ?>
                <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 20px; height: 100%;">
                    <div style="color: white; font-weight: 600; margin-bottom: 12px; font-size: 0.9rem;">
                        <i class="fas fa-camera"></i> Venue Photo
                    </div>
                    <div style="text-align: center; overflow: hidden; border-radius: 10px; cursor: pointer;" 
                         onclick="openFullImage('<?php echo SITE_URL . $venue['venue_photo_path']; ?>', '<?php echo htmlspecialchars($venue['venue_name']); ?>')">
                        <img src="<?php echo SITE_URL . $venue['venue_photo_path']; ?>" 
                             alt="<?php echo htmlspecialchars($venue['venue_name']); ?> Photo"
                             style="width: 100%; height: 250px; object-fit: cover; border-radius: 10px; border: 2px solid rgba(226, 48, 32, 0.3); transition: transform 0.3s ease; cursor: pointer;"
                             onmouseover="this.style.transform='scale(1.02)';"
                             onmouseout="this.style.transform='scale(1)';">
                    </div>
                    <div style="margin-top: 10px; text-align: center;">
                        <span style="color: var(--pale-red); font-size: 0.75rem; cursor: pointer;" onclick="openFullImage('<?php echo SITE_URL . $venue['venue_photo_path']; ?>', '<?php echo htmlspecialchars($venue['venue_name']); ?>')">
                            <i class="fas fa-search-plus"></i> Click photo to view full size
                        </span>
                    </div>
                </div>
                <?php else: ?>
                <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 20px; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px;">
                    <i class="fas fa-camera" style="font-size: 3rem; color: rgba(255,255,255,0.15); margin-bottom: 10px;"></i>
                    <p style="color: rgba(255,255,255,0.3); font-size: 0.9rem;">No venue photo available</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Google Maps Column -->
            <div>
                <?php if ($has_valid_map && !empty($embed_url)): ?>
                <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 20px; height: 100%; display: flex; flex-direction: column;">
                    <div style="color: white; font-weight: 600; margin-bottom: 12px; font-size: 0.9rem;">
                        <i class="fas fa-map-marked-alt"></i> Location Map
                    </div>
                    <div style="position: relative; border-radius: 10px; overflow: hidden; border: 2px solid rgba(226, 48, 32, 0.3); flex: 1;">
                        <iframe 
                            src="<?php echo $embed_url; ?>"
                            style="width: 100%; height: 250px; border: 0; display: block;"
                            allowfullscreen="" 
                            loading="lazy">
                        </iframe>
                    </div>
                    <div style="margin-top: 10px; text-align: center;">
                        <a href="<?php echo htmlspecialchars($venue['google_maps_link']); ?>" target="_blank" 
                           style="color: var(--light-red); font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"
                           onmouseover="this.style.textDecoration='underline';"
                           onmouseout="this.style.textDecoration='none';">
                            <i class="fas fa-external-link-alt"></i> Open in Google Maps
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 20px; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px;">
                    <i class="fas fa-map-marker-alt" style="font-size: 3rem; color: rgba(255,255,255,0.15); margin-bottom: 10px;"></i>
                    <p style="color: rgba(255,255,255,0.3); font-size: 0.9rem; text-align: center;">No map link available</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($movies)): ?>
        <div style="text-align: center; padding: 60px; background: rgba(226, 48, 32, 0.05); border-radius: 15px; border: 2px dashed rgba(226, 48, 32, 0.3);">
            <i class="fas fa-film fa-3x" style="color: var(--primary-red); margin-bottom: 20px; opacity: 0.8;"></i>
            <h3 style="color: white; margin-bottom: 15px; font-size: 1.8rem;">No Movies Available</h3>
            <p style="color: var(--pale-red); margin-bottom: 25px;">
                No movies are currently available at this venue.
            </p>
            <a href="<?php echo SITE_URL; ?>index.php?page=venue" class="btn btn-primary" style="padding: 12px 30px;">
                <i class="fas fa-arrow-left"></i> Back to Venues
            </a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px;">
            <?php foreach ($movies as $movie): 
                $is_now_showing = in_array($movie['id'], $now_showing_ids);
            ?>
            <div class="movie-card" style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; overflow: hidden; transition: all 0.3s ease; border: 1px solid rgba(226, 48, 32, 0.2); position: relative; display: flex; flex-direction: column; height: 100%;"
                 onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 20px 40px rgba(226,48,32,0.2)'; this.style.borderColor='#e23020';"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'; this.style.borderColor='rgba(226,48,32,0.2)';">
                
                <?php if (!empty($movie['poster_url'])): ?>
                    <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" style="width: 100%; height: 320px; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 100%; height: 320px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
                    </div>
                <?php endif; ?>
                
                <div style="position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 8px;">
                    <span style="background: var(--primary-red); color: white; font-weight: 700; font-size: 0.8rem; padding: 6px 12px; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); text-align: center; min-width: 40px;"><?php echo $movie['rating'] ?: 'PG'; ?></span>
                    <?php if ($movie['genre']): ?>
                    <span style="background: rgba(0,0,0,0.7); color: white; font-weight: 600; font-size: 0.75rem; padding: 5px 10px; border-radius: 15px; display: flex; align-items: center; gap: 5px;"><i class="fas fa-tag"></i> <?php echo explode(',', $movie['genre'])[0]; ?></span>
                    <?php endif; ?>
                    <?php if (!$is_now_showing): ?>
                    <span style="background: rgba(241, 196, 15, 0.2); color: #f1c40f; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(241, 196, 15, 0.3);">
                        <i class="fas fa-hourglass-half"></i> Coming Soon
                    </span>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 25px; flex: 1; display: flex; flex-direction: column;">
                    <h3 style="color: white; font-size: 1.3rem; font-weight: 800; margin-bottom: 10px; line-height: 1.4;"><?php echo htmlspecialchars($movie['title']); ?></h3>
                    
                    <div style="margin-bottom: 15px;">
                        <?php if ($movie['duration']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 5px;"><i class="fas fa-clock"></i> <?php echo $movie['duration']; ?></div>
                        <?php endif; ?>
                        <?php if ($movie['genre']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); font-size: 0.9rem;"><i class="fas fa-film"></i> <?php echo htmlspecialchars($movie['genre']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($movie['screens'])): ?>
                        <div style="display: flex; align-items: center; gap: 5px; color: #3498db; font-size: 0.75rem; margin-top: 5px;">
                            <i class="fas fa-tv"></i> <?php echo htmlspecialchars($movie['screens']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($movie['description']): ?>
                    <div style="margin-bottom: 15px; flex: 1;">
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($movie['description']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Schedule Dropdown -->
                    <?php if ($is_now_showing && !empty($schedules[$movie['id']])): ?>
                    <div style="margin-bottom: 15px; width: 100%;">
                        <select class="schedule-select" data-movie-id="<?php echo $movie['id']; ?>" style="width: 100%; padding: 12px 14px; background: rgba(255, 255, 255, 0.15); border: 2px solid rgba(226, 48, 32, 0.4); border-radius: 8px; color: white; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 14px center; background-size: 14px;">
                            <option value="" style="background: var(--bg-card); color: white;">Select a showtime</option>
                            <?php foreach ($schedules[$movie['id']] as $schedule): 
                                $show_date = date('D, M d', strtotime($schedule['show_date']));
                                $show_time = date('h:i A', strtotime($schedule['showtime']));
                                $is_today = date('Y-m-d') == $schedule['show_date'] ? ' (Today)' : '';
                            ?>
                            <option value="<?php echo $schedule['schedule_id']; ?>" style="background: var(--bg-card); color: white;">
                                <?php echo $show_date . $is_today . ' • ' . $show_time . ' • ' . $schedule['available_seats'] . ' seats'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Buttons -->
                    <div style="display: flex; gap: 5px; margin-top: auto; min-height: 45px;">
                        <?php if (!empty($movie['trailer_url'])): ?>
                        <a href="<?php echo $movie['trailer_url']; ?>" target="_blank" style="background: rgba(255, 0, 0, 0.2); color: #ff0000; border: 2px solid rgba(255, 0, 0, 0.3); padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; width: 45px; text-decoration: none;" title="Watch Trailer"
                           onmouseover="this.style.background='rgba(255,0,0,0.3)'; this.style.transform='translateY(-2px)';"
                           onmouseout="this.style.background='rgba(255,0,0,0.2)'; this.style.transform='translateY(0)';">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/movie-details&id=<?php echo $movie['id']; ?>" style="flex: 1; background: rgba(255,255,255,0.1); color: white; border: 2px solid rgba(226, 48, 32, 0.3); padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;"
                           onmouseover="this.style.background='rgba(226,48,32,0.2)'; this.style.borderColor='var(--primary-red)'; this.style.transform='translateY(-2px)';"
                           onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(226,48,32,0.3)'; this.style.transform='translateY(0)';">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                        <?php if ($is_now_showing && !empty($schedules[$movie['id']])): ?>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" class="book-now-btn" data-movie-id="<?php echo $movie['id']; ?>" style="flex: 1; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;"
                                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(226,48,32,0.4)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-ticket-alt"></i> Book Now
                                </a>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=login" style="flex: 1; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;"
                                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(226,48,32,0.4)';"
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <i class="fas fa-sign-in-alt"></i> Login to Book
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Venue badge on movie card -->
                    <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <div style="display: flex; align-items: center; gap: 5px; color: var(--pale-red); font-size: 0.7rem;">
                            <i class="fas fa-building"></i> Playing at: <?php echo htmlspecialchars($venue['venue_name']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: rgba(226, 48, 32, 0.05); border-radius: 10px; border: 1px solid rgba(226, 48, 32, 0.2);">
            <p style="color: var(--pale-red); font-size: 1rem;">
                Showing <strong style="color: white;"><?php echo count($movies); ?></strong> movie(s) at 
                <strong style="color: var(--primary-red);"><?php echo htmlspecialchars($venue['venue_name']); ?></strong>
            </p>
            <?php if ($has_valid_map && !empty($venue['google_maps_link'])): ?>
            <p style="margin-top: 10px;">
                <a href="<?php echo htmlspecialchars($venue['google_maps_link']); ?>" target="_blank" 
                   style="color: #3498db; text-decoration: none; font-size: 0.85rem;">
                    <i class="fas fa-directions"></i> Get Directions to this Venue
                </a>
            </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Full Image Modal -->
<div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10000; justify-content: center; align-items: center; cursor: pointer; padding: 20px;"
     onclick="closeFullImage()">
    <div style="max-width: 90%; max-height: 90%; text-align: center;">
        <img id="fullImage" src="" alt="" style="max-width: 100%; max-height: 80vh; border-radius: 10px; border: 3px solid var(--primary-red);">
        <div style="margin-top: 20px; color: white;">
            <p id="imageCaption" style="margin-bottom: 10px;"></p>
            <span style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 30px; font-size: 0.9rem;">
                <i class="fas fa-times-circle"></i> Click anywhere to close
            </span>
        </div>
    </div>
</div>

<style>
.movie-card {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.schedule-select:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(226, 48, 32, 0.2);
}

.schedule-select:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 3px rgba(226, 48, 32, 0.3);
}

.schedule-select option {
    padding: 12px;
    font-size: 0.95rem;
    background: var(--bg-card);
    color: white;
}

.btn {
    padding: 12px 25px;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(226, 48, 32, 0.4);
}

:root {
    --primary-red: #e23020;
    --dark-red: #c11b18;
    --deep-red: #a80f0f;
    --light-red: #ff6b6b;
    --pale-red: #ff9999;
    --bg-dark: #0f0f23;
    --bg-darker: #1a1a2e;
    --bg-card: #3a0b07;
    --bg-card-light: #6b140e;
}

@media (max-width: 768px) {
    .venue-movies-container {
        padding: 15px;
    }
    
    .venue-movies-container > div:first-child > div:first-child > div:first-child {
        grid-template-columns: 1fr;
    }
    
    .venue-movies-container > div:last-child {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 576px) {
    .venue-movies-container > div:last-child {
        grid-template-columns: 1fr;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

#imageModal {
    animation: fadeIn 0.3s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const movieCards = document.querySelectorAll('.movie-card');
    movieCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Handle schedule selection
    const scheduleSelects = document.querySelectorAll('.schedule-select');
    scheduleSelects.forEach(select => {
        select.addEventListener('change', function() {
            const movieId = this.dataset.movieId;
            const scheduleId = this.value;
            const bookBtn = document.querySelector(`.book-now-btn[data-movie-id="${movieId}"]`);
            
            if (bookBtn && scheduleId) {
                bookBtn.href = '<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=' + movieId + '&schedule=' + scheduleId;
            }
        });
    });
});

function openFullImage(imageUrl, venueName) {
    const modal = document.getElementById('imageModal');
    const fullImage = document.getElementById('fullImage');
    const caption = document.getElementById('imageCaption');
    
    fullImage.src = imageUrl;
    caption.innerHTML = `<i class="fas fa-building"></i> ${escapeHtml(venueName)} - Venue Photo`;
    modal.style.display = 'flex';
}

function closeFullImage() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFullImage();
    }
});
</script>

<?php require_once $root_dir . '/partials/footer.php'; ?>