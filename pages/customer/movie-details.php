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

$schedules_stmt = $conn->prepare("
    SELECT * FROM movie_schedules 
    WHERE movie_id = ? 
    AND is_active = 1 
    AND show_date >= CURDATE()
    AND available_seats > 0
    ORDER BY show_date, showtime
    LIMIT 5
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
    <div style="margin-bottom: 30px;">
        <a href="javascript:history.back()" style="color: var(--light-red); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3); margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 40px;">
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
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-calendar" style="color: var(--primary-red); font-size: 1.2rem;"></i>
                        <span style="color: white; font-weight: 600;">Added: <?php echo date('M d, Y', strtotime($movie['created_at'])); ?></span>
                    </div>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <h3 style="color: white; font-size: 1.3rem; margin-bottom: 15px; font-weight: 700;">Synopsis</h3>
                    <p style="color: rgba(255,255,255,0.9); line-height: 1.8; font-size: 1rem;">
                        <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
                    </p>
                </div>
                
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
                <a href="<?php echo $movie['trailer_url']; ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: rgba(0,0,0,0.5); color: white; text-decoration: none;">
                    <i class="fas fa-play-circle" style="font-size: 4rem; color: var(--primary-red);"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Venue Information Section -->
    <?php if (!empty($movie['venue_name']) || !empty($movie['venue_location']) || !empty($movie['google_maps_link'])): ?>
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3); margin-bottom: 40px;">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-map-marker-alt" style="color: #e74c3c;"></i> Venue Information
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
            <div>
                <?php if (!empty($movie['venue_name'])): ?>
                <div style="margin-bottom: 20px;">
                    <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Venue Name</div>
                    <div style="color: white; font-size: 1.3rem; font-weight: 700;"><?php echo htmlspecialchars($movie['venue_name']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($movie['venue_location'])): ?>
                <div style="margin-bottom: 20px;">
                    <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Location</div>
                    <div style="color: white; font-size: 1.1rem;"><?php echo htmlspecialchars($movie['venue_location']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($movie['google_maps_link'])): ?>
                <a href="<?php echo $movie['google_maps_link']; ?>" target="_blank" 
                   style="display: inline-flex; align-items: center; gap: 10px; background: #e74c3c; color: white; padding: 15px 30px; border-radius: 10px; text-decoration: none; font-weight: 600; margin-top: 15px; transition: all 0.3s ease;">
                    <i class="fas fa-map-marked-alt"></i> Open in Google Maps
                    <i class="fas fa-external-link-alt" style="font-size: 0.8rem;"></i>
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($movie['google_maps_link'])): 
                $coordinates = '';
                if (preg_match('/q=([0-9.-]+),([0-9.-]+)/', $movie['google_maps_link'], $matches)) {
                    $coordinates = $matches[1] . ', ' . $matches[2];
                }
            ?>
            <div style="background: rgba(0,0,0,0.3); border-radius: 15px; padding: 20px;">
                <div style="color: white; font-weight: 600; margin-bottom: 15px;">📍 Coordinates</div>
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; font-family: monospace; font-size: 1.1rem; color: #f1c40f;">
                    <?php echo $coordinates ?: '10.273055307646723, 123.7611768131498'; ?>
                </div>
                <div style="color: var(--pale-red); font-size: 0.85rem; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Click the map button above to view exact location
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Price Information Section -->
    <?php if (isset($movie['standard_price']) || isset($movie['premium_price']) || isset($movie['sweet_spot_price'])): ?>
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 20px; padding: 30px; border: 1px solid rgba(226, 48, 32, 0.3); margin-bottom: 40px;">
        <h2 style="color: white; font-size: 1.8rem; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-tags" style="color: #f39c12;"></i> Ticket Prices
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="background: rgba(52, 152, 219, 0.2); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
                <div style="width: 50px; height: 50px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <i class="fas fa-chair" style="color: white; font-size: 1.5rem;"></i>
                </div>
                <h3 style="color: white; font-size: 1.2rem; margin-bottom: 10px; font-weight: 700;">Standard</h3>
                <div style="color: #3498db; font-size: 1.8rem; font-weight: 800;">₱<?php echo number_format($movie['standard_price'] ?? 350, 2); ?></div>
                <div style="color: var(--pale-red); font-size: 0.9rem; margin-top: 10px;">per seat</div>
            </div>
            
            <div style="background: rgba(255, 215, 0, 0.2); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(255, 215, 0, 0.3);">
                <div style="width: 50px; height: 50px; background: #FFD700; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <i class="fas fa-crown" style="color: #333; font-size: 1.5rem;"></i>
                </div>
                <h3 style="color: white; font-size: 1.2rem; margin-bottom: 10px; font-weight: 700;">Premium</h3>
                <div style="color: #FFD700; font-size: 1.8rem; font-weight: 800;">₱<?php echo number_format($movie['premium_price'] ?? 450, 2); ?></div>
                <div style="color: var(--pale-red); font-size: 0.9rem; margin-top: 10px;">per seat</div>
            </div>
            
            <div style="background: rgba(231, 76, 60, 0.2); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(231, 76, 60, 0.3);">
                <div style="width: 50px; height: 50px; background: #e74c3c; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <i class="fas fa-star" style="color: white; font-size: 1.5rem;"></i>
                </div>
                <h3 style="color: white; font-size: 1.2rem; margin-bottom: 10px; font-weight: 700;">Sweet Spot</h3>
                <div style="color: #e74c3c; font-size: 1.8rem; font-weight: 800;">₱<?php echo number_format($movie['sweet_spot_price'] ?? 550, 2); ?></div>
                <div style="color: var(--pale-red); font-size: 0.9rem; margin-top: 10px;">per seat</div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; color: var(--pale-red); font-size: 0.95rem;">
            <i class="fas fa-info-circle"></i> Prices are per ticket and may vary by showtime
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
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                <?php foreach ($schedules as $schedule): 
                    $is_today = date('Y-m-d') == $schedule['show_date'];
                    $is_tomorrow = date('Y-m-d', strtotime('+1 day')) == $schedule['show_date'];
                    $show_date = date('D, M d', strtotime($schedule['show_date']));
                    $show_time = date('h:i A', strtotime($schedule['showtime']));
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
                    
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: rgba(255,255,255,0.7);">Available Seats:</span>
                            <span style="color: <?php echo $schedule['available_seats'] < 10 ? '#ff6b6b' : '#2ecc71'; ?>; font-weight: 700;">
                                <?php echo $schedule['available_seats']; ?>/<?php echo $schedule['total_seats']; ?>
                                <?php if ($seats_left_text): ?>
                                <span style="color: #ff6b6b; font-weight: 700;"> (<?php echo $seats_left_text; ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: <?php echo ($schedule['available_seats'] / $schedule['total_seats']) > 0.5 ? '#2ecc71' : (($schedule['available_seats'] / $schedule['total_seats']) > 0.2 ? '#f39c12' : '#e74c3c'); ?>; 
                                 height: 100%; width: <?php echo ($schedule['available_seats'] / $schedule['total_seats']) * 100; ?>%;"></div>
                        </div>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>&schedule=<?php echo $schedule['id']; ?>" 
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
    .movie-details-container > div:first-of-type {
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