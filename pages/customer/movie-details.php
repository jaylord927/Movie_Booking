<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

$conn = get_db_connection();
$movie_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($movie_id <= 0) {
    header("Location: " . SITE_URL . "index.php?page=movies");
    exit();
}

// Get movie details
$stmt = $conn->prepare("
    SELECT m.*, 
           a.u_name as added_by_name,
           u.u_name as updated_by_name
    FROM movies m
    LEFT JOIN users a ON m.added_by = a.u_id
    LEFT JOIN users u ON m.updated_by = u.u_id
    WHERE m.id = ? AND m.is_active = 1
");
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: " . SITE_URL . "index.php?page=movies");
    exit();
}

$movie = $result->fetch_assoc();
$stmt->close();

// Get screens where this movie is playing with prices for each seat type
// Pivot the seat_type prices into columns
$screens_stmt = $conn->prepare("
    SELECT 
        sc.id as screen_id,
        sc.screen_name,
        sc.screen_number,
        sc.capacity,
        v.id as venue_id,
        v.venue_name,
        v.venue_location,
        v.google_maps_link,
        v.venue_photo_path,
        v.contact_number,
        v.operating_hours,
        MAX(CASE WHEN st.name = 'Standard' THEN msp.price ELSE NULL END) as standard_price,
        MAX(CASE WHEN st.name = 'Premium' THEN msp.price ELSE NULL END) as premium_price,
        MAX(CASE WHEN st.name = 'Sweet Spot' THEN msp.price ELSE NULL END) as sweet_spot_price
    FROM movie_screen_prices msp
    JOIN screens sc ON msp.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    JOIN seat_types st ON msp.seat_type_id = st.id
    WHERE msp.movie_id = ? AND msp.is_active = 1
    GROUP BY sc.id, sc.screen_name, sc.screen_number, sc.capacity, v.id, v.venue_name, v.venue_location, v.google_maps_link, v.venue_photo_path, v.contact_number, v.operating_hours
    ORDER BY v.venue_name, sc.screen_number
");
$screens_stmt->bind_param("i", $movie_id);
$screens_stmt->execute();
$screens_result = $screens_stmt->get_result();
$screens = [];
while ($row = $screens_result->fetch_assoc()) {
    $screens[] = $row;
}
$screens_stmt->close();

// Get schedules for this movie (upcoming showtimes)
$schedules_stmt = $conn->prepare("
    SELECT 
        s.id as schedule_id,
        s.show_date,
        s.showtime,
        s.base_price,
        sc.id as screen_id,
        sc.screen_name,
        sc.screen_number,
        v.id as venue_id,
        v.venue_name,
        v.venue_location,
        v.google_maps_link,
        COUNT(sa.id) as total_seats,
        COUNT(CASE WHEN sa.status = 'available' THEN 1 END) as available_seats
    FROM schedules s
    JOIN screens sc ON s.screen_id = sc.id
    JOIN venues v ON sc.venue_id = v.id
    LEFT JOIN seat_availability sa ON s.id = sa.schedule_id
    WHERE s.movie_id = ? 
    AND s.is_active = 1 
    AND s.show_date >= CURDATE()
    GROUP BY s.id, s.show_date, s.showtime, s.base_price, sc.id, sc.screen_name, sc.screen_number, v.id, v.venue_name, v.venue_location, v.google_maps_link
    ORDER BY s.show_date, s.showtime
    LIMIT 10
");
$schedules_stmt->bind_param("i", $movie_id);
$schedules_stmt->execute();
$schedules_result = $schedules_stmt->get_result();
$schedules = [];
while ($row = $schedules_result->fetch_assoc()) {
    $schedules[] = $row;
}
$schedules_stmt->close();

$conn->close();

require_once $root_dir . '/partials/header.php';
?>

<div class="movie-details-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Back Button -->
    <div style="margin-bottom: 30px;">
        <a href="javascript:history.back()" style="color: var(--light-red); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <!-- Movie Main Details -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3); margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 40px;">
            <!-- Movie Poster -->
            <div>
                <?php if (!empty($movie['poster_url'])): ?>
                    <img src="<?php echo $movie['poster_url']; ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>"
                         style="width: 100%; height: 400px; object-fit: cover; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 3px solid rgba(226, 48, 32, 0.3);">
                <?php else: ?>
                    <div style="width: 100%; height: 400px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); border-radius: 15px; display: flex; align-items: center; justify-content: center; border: 3px solid rgba(226, 48, 32, 0.3);">
                        <i class="fas fa-film" style="font-size: 4rem; color: rgba(255, 255, 255, 0.3);"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Movie Info -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <h1 style="color: white; font-size: 2.5rem; font-weight: 800; line-height: 1.2;">
                        <?php echo htmlspecialchars($movie['title']); ?>
                    </h1>
                    <div style="background: var(--primary-red); color: white; padding: 8px 20px; border-radius: 30px; font-weight: 700; font-size: 1.2rem;">
                        <?php echo $movie['rating'] ?: 'PG'; ?>
                    </div>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 25px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-clock" style="color: var(--primary-red); font-size: 1.2rem;"></i>
                        <span style="color: white; font-weight: 600;"><?php echo $movie['duration']; ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-tag" style="color: var(--primary-red); font-size: 1.2rem;"></i>
                        <span style="color: white; font-weight: 600;"><?php echo htmlspecialchars($movie['genre']); ?></span>
                    </div>
                    <?php if (!empty($movie['director'])): ?>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user" style="color: var(--primary-red); font-size: 1.2rem;"></i>
                        <span style="color: white; font-weight: 600;">Director: <?php echo htmlspecialchars($movie['director']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-calendar" style="color: var(--primary-red); font-size: 1.2rem;"></i>
                        <span style="color: white; font-weight: 600;">Added: <?php echo date('M d, Y', strtotime($movie['created_at'])); ?></span>
                    </div>
                </div>
                
                <!-- Synopsis -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.3rem; margin-bottom: 15px; font-weight: 700;">Synopsis</h3>
                    <p style="color: rgba(255,255,255,0.9); line-height: 1.8; font-size: 1rem;">
                        <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <?php if (!empty($movie['trailer_url'])): ?>
                        <a href="<?php echo $movie['trailer_url']; ?>" target="_blank" class="btn btn-secondary" style="padding: 15px 30px;">
                            <i class="fab fa-youtube"></i> Watch Trailer
                        </a>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" class="btn btn-primary" style="padding: 15px 30px;">
                            <i class="fas fa-ticket-alt"></i> Book Now
                        </a>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn btn-primary" style="padding: 15px 30px;">
                            <i class="fas fa-sign-in-alt"></i> Login to Book
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Trailer Section -->
    <?php if (!empty($movie['trailer_url'])): ?>
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3); margin-bottom: 40px;">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
            <i class="fab fa-youtube" style="color: #ff0000;"></i> Official Trailer
        </h2>
        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 15px;">
            <?php
            $youtube_id = '';
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $movie['trailer_url'], $matches)) {
                $youtube_id = $matches[1];
            }
            ?>
            <?php if ($youtube_id): ?>
                <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            <?php else: ?>
                <a href="<?php echo $movie['trailer_url']; ?>" target="_blank" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: rgba(0,0,0,0.5); color: white; text-decoration: none;">
                    <i class="fas fa-play-circle" style="font-size: 4rem; color: var(--primary-red);"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Venues Section (Screens where movie is playing) -->
    <?php if (!empty($screens)): ?>
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3); margin-bottom: 40px;">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-map-marker-alt" style="color: #e74c3c;"></i> Available Venues
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
            <?php foreach ($screens as $screen): ?>
            <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-building" style="color: white; font-size: 1.2rem;"></i>
                    </div>
                    <div>
                        <h3 style="color: white; font-size: 1.2rem; font-weight: 700;"><?php echo htmlspecialchars($screen['venue_name']); ?></h3>
                        <p style="color: var(--pale-red); font-size: 0.8rem;"><?php echo htmlspecialchars($screen['screen_name']); ?> (Screen #<?php echo $screen['screen_number']; ?>)</p>
                    </div>
                </div>
                
                <?php if (!empty($screen['venue_location'])): ?>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-bottom: 10px;">
                    <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($screen['venue_location']); ?>
                </p>
                <?php endif; ?>
                
                <!-- Price display -->
                <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 12px; margin: 10px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="color: rgba(255,255,255,0.6);"><i class="fas fa-chair"></i> Standard:</span>
                        <span style="color: #3498db; font-weight: 600;">₱<?php echo number_format($screen['standard_price'] ?? 350, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="color: rgba(255,255,255,0.6);"><i class="fas fa-crown"></i> Premium:</span>
                        <span style="color: #FFD700; font-weight: 600;">₱<?php echo number_format($screen['premium_price'] ?? 450, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: rgba(255,255,255,0.6);"><i class="fas fa-star"></i> Sweet Spot:</span>
                        <span style="color: #e74c3c; font-weight: 600;">₱<?php echo number_format($screen['sweet_spot_price'] ?? 550, 2); ?></span>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <div>
                        <span style="color: #3498db; font-size: 0.8rem;">Capacity:</span>
                        <span style="color: white; font-weight: 600;"><?php echo number_format($screen['capacity']); ?> seats</span>
                    </div>
                    <?php if (!empty($screen['google_maps_link'])): ?>
                    <a href="<?php echo $screen['google_maps_link']; ?>" target="_blank" style="color: #3498db; font-size: 0.8rem; text-decoration: none;">
                        <i class="fas fa-map-marked-alt"></i> Map
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Showtimes Section -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h2 style="color: white; font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-clock"></i> Upcoming Showtimes
            </h2>
            <?php if (!empty($schedules)): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" style="color: var(--light-red); text-decoration: none; font-weight: 600;">
                    View All Showtimes <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($schedules)): ?>
            <div style="text-align: center; padding: 50px; color: var(--pale-red);">
                <i class="fas fa-calendar-times fa-3x" style="margin-bottom: 15px; opacity: 0.7;"></i>
                <p style="font-size: 1.1rem;">No upcoming showtimes available for this movie.</p>
                <p style="font-size: 0.9rem; margin-top: 10px;">Please check back later.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach ($schedules as $schedule): 
                    $is_today = date('Y-m-d') == $schedule['show_date'];
                    $is_tomorrow = date('Y-m-d', strtotime('+1 day')) == $schedule['show_date'];
                    $show_date = date('D, M d', strtotime($schedule['show_date']));
                    $show_time = date('h:i A', strtotime($schedule['showtime']));
                    $available_percentage = $schedule['total_seats'] > 0 ? ($schedule['available_seats'] / $schedule['total_seats']) * 100 : 0;
                    $seats_left_text = $schedule['available_seats'] <= 10 ? "{$schedule['available_seats']} seats left" : '';
                ?>
                <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(226, 48, 32, 0.2); transition: all 0.3s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-size: 1.3rem; color: white; font-weight: 700;"><?php echo $show_time; ?></span>
                        <?php if ($is_today): ?>
                            <span style="background: var(--primary-red); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600;">TODAY</span>
                        <?php elseif ($is_tomorrow): ?>
                            <span style="background: #3498db; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600;">TOMORROW</span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 15px;">
                        <i class="far fa-calendar"></i> <?php echo $show_date; ?>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: rgba(255,255,255,0.7);"><?php echo htmlspecialchars($schedule['venue_name']); ?> - <?php echo htmlspecialchars($schedule['screen_name']); ?></span>
                            <span style="color: <?php echo $schedule['available_seats'] < 10 ? '#ff6b6b' : '#2ecc71'; ?>; font-weight: 700;">
                                <?php echo $schedule['available_seats']; ?>/<?php echo $schedule['total_seats']; ?>
                            </span>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: <?php echo $available_percentage > 50 ? '#2ecc71' : ($available_percentage > 20 ? '#f39c12' : '#e74c3c'); ?>; 
                                 height: 100%; width: <?php echo $available_percentage; ?>%;"></div>
                        </div>
                        <?php if ($seats_left_text): ?>
                        <div style="color: #ff6b6b; font-size: 0.7rem; margin-top: 5px;">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $seats_left_text; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <span style="color: #2ecc71; font-size: 1.1rem; font-weight: 700;">
                            ₱<?php echo number_format($schedule['base_price'], 2); ?>
                        </span>
                        <span style="color: rgba(255,255,255,0.5); font-size: 0.7rem;"> per standard seat</span>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>&schedule=<?php echo $schedule['schedule_id']; ?>" 
                       class="btn btn-primary" style="width: 100%; padding: 12px; justify-content: center;">
                        <i class="fas fa-ticket-alt"></i> Select Seats
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
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

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(226, 48, 32, 0.3);
}

.btn-secondary:hover {
    background: rgba(226, 48, 32, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-3px);
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

@media (max-width: 992px) {
    .movie-details-container > div:first-of-type > div {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .movie-details-container {
        padding: 15px;
    }
    
    h1 {
        font-size: 2rem !important;
    }
}
</style>

<?php require_once $root_dir . '/partials/footer.php'; ?>