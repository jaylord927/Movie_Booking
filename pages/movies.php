<?php
$root_dir = dirname(__DIR__);
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/partials/header.php';

$conn = get_db_connection();

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Fetch all active movies
$movies_result = $conn->query("SELECT m.* FROM movies m WHERE m.is_active = 1 ORDER BY m.created_at DESC");
$all_movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $all_movies[] = $row;
    }
}

// Separate movies with and without schedules
$now_showing = [];
$coming_soon = [];

foreach ($all_movies as $movie) {
    $schedule_check = $conn->prepare("SELECT id FROM movie_schedules WHERE movie_id = ? AND is_active = 1 AND show_date >= CURDATE() LIMIT 1");
    $schedule_check->bind_param("i", $movie['id']);
    $schedule_check->execute();
    $schedule_result = $schedule_check->get_result();
    
    if ($schedule_result->num_rows > 0) {
        $now_showing[] = $movie;
    } else {
        $coming_soon[] = $movie;
    }
    $schedule_check->close();
}

// Determine which movies to display based on filter
if ($filter === 'now-showing') {
    $movies = $now_showing;
    $display_title = "Now Showing";
    $display_message = "Movies currently playing in cinemas";
} elseif ($filter === 'coming-soon') {
    $movies = $coming_soon;
    $display_title = "Coming Soon";
    $display_message = "Upcoming movies - stay tuned for showtimes";
} else {
    $movies = $all_movies;
    $display_title = "All Movies";
    $display_message = "Browse our complete collection of movies";
}

// Get all unique genres from displayed movies
$allGenres = [];
foreach ($movies as $movie) {
    if ($movie['genre']) {
        $genres = explode(',', $movie['genre']);
        foreach ($genres as $genre) {
            $genre = trim($genre);
            if ($genre && !in_array($genre, $allGenres)) {
                $allGenres[] = $genre;
            }
        }
    }
}
sort($allGenres);

// Get filter parameters
$searchTerm = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$selectedGenre = isset($_GET['genre']) ? sanitize_input($_GET['genre']) : '';
$selectedRating = isset($_GET['rating']) ? sanitize_input($_GET['rating']) : '';

// Filter movies
$filteredMovies = $movies;

if (!empty($searchTerm)) {
    $filteredMovies = array_filter($filteredMovies, function($movie) use ($searchTerm) {
        return stripos($movie['title'], $searchTerm) !== false || 
               stripos($movie['description'], $searchTerm) !== false;
    });
}

if (!empty($selectedGenre) && $selectedGenre !== 'all') {
    $filteredMovies = array_filter($filteredMovies, function($movie) use ($selectedGenre) {
        if (empty($movie['genre'])) return false;
        $genres = array_map('trim', explode(',', $movie['genre']));
        return in_array($selectedGenre, $genres);
    });
}

if (!empty($selectedRating) && $selectedRating !== 'all') {
    $filteredMovies = array_filter($filteredMovies, function($movie) use ($selectedRating) {
        if (empty($movie['rating'])) return false;
        return $movie['rating'] === $selectedRating;
    });
}

$filteredMovies = array_values($filteredMovies);

// Fetch schedules for movies
$schedules = [];
foreach ($filteredMovies as $movie) {
    $schedule_stmt = $conn->prepare("
        SELECT * FROM movie_schedules 
        WHERE movie_id = ? AND is_active = 1 AND show_date >= CURDATE() 
        ORDER BY show_date, showtime
    ");
    $schedule_stmt->bind_param("i", $movie['id']);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    $movie_schedules = [];
    while ($row = $schedule_result->fetch_assoc()) {
        $movie_schedules[] = $row;
    }
    $schedules[$movie['id']] = $movie_schedules;
    $schedule_stmt->close();
}

$conn->close();
?>

<div class="main-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">Movies</h1>
        <p style="color: var(--pale-red); font-size: 1.1rem; max-width: 600px; margin: 0 auto;"><?php echo $display_message; ?></p>
    </div>

    <!-- Filter Navigation -->
    <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 40px; flex-wrap: wrap;">
        <a href="?page=movies&filter=all<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $selectedGenre ? '&genre=' . urlencode($selectedGenre) : ''; ?><?php echo $selectedRating ? '&rating=' . urlencode($selectedRating) : ''; ?>" 
           class="filter-btn" style="padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 1.1rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; background: <?php echo $filter === 'all' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255, 255, 255, 0.1)'; ?>; color: white; border: 2px solid <?php echo $filter === 'all' ? 'transparent' : 'rgba(226, 48, 32, 0.3)'; ?>; box-shadow: <?php echo $filter === 'all' ? '0 4px 15px rgba(226, 48, 32, 0.3)' : 'none'; ?>;">
            <i class="fas fa-film"></i> All Movies
        </a>
        <a href="?page=movies&filter=now-showing<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $selectedGenre ? '&genre=' . urlencode($selectedGenre) : ''; ?><?php echo $selectedRating ? '&rating=' . urlencode($selectedRating) : ''; ?>" 
           class="filter-btn" style="padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 1.1rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; background: <?php echo $filter === 'now-showing' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255, 255, 255, 0.1)'; ?>; color: white; border: 2px solid <?php echo $filter === 'now-showing' ? 'transparent' : 'rgba(226, 48, 32, 0.3)'; ?>; box-shadow: <?php echo $filter === 'now-showing' ? '0 4px 15px rgba(226, 48, 32, 0.3)' : 'none'; ?>;">
            <i class="fas fa-play-circle"></i> Now Showing
        </a>
        <a href="?page=movies&filter=coming-soon<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $selectedGenre ? '&genre=' . urlencode($selectedGenre) : ''; ?><?php echo $selectedRating ? '&rating=' . urlencode($selectedRating) : ''; ?>" 
           class="filter-btn" style="padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 1.1rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; background: <?php echo $filter === 'coming-soon' ? 'linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%)' : 'rgba(255, 255, 255, 0.1)'; ?>; color: white; border: 2px solid <?php echo $filter === 'coming-soon' ? 'transparent' : 'rgba(226, 48, 32, 0.3)'; ?>; box-shadow: <?php echo $filter === 'coming-soon' ? '0 4px 15px rgba(226, 48, 32, 0.3)' : 'none'; ?>;">
            <i class="fas fa-clock"></i> Coming Soon
        </a>
    </div>

    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; padding: 30px; margin-bottom: 40px; border: 1px solid rgba(226, 48, 32, 0.2);">
        <div style="margin-bottom: 25px;">
            <form method="GET" action="">
                <input type="hidden" name="page" value="movies">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <div style="display: flex; gap: 10px; position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.6); font-size: 1.2rem;"></i>
                    <input type="text" name="search" placeholder="Search movies by title or description..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="flex: 1; padding: 15px 20px 15px 50px; background: rgba(255,255,255,0.08); border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px; color: white; font-size: 1rem;" autocomplete="off">
                    <button type="submit" style="padding: 15px 30px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;"><i class="fas fa-search"></i> Search</button>
                    <?php if ($searchTerm || $selectedGenre || $selectedRating): ?>
                    <a href="?page=movies&filter=<?php echo $filter; ?>" style="padding: 15px 20px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 10px; border: 2px solid rgba(226, 48, 32, 0.3); display: flex; align-items: center; gap: 8px; font-weight: 600;"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div>
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;"><i class="fas fa-film"></i> Filter by Genre</label>
                <select id="genreSelect" style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.15); border: 2px solid rgba(226, 48, 32, 0.4); border-radius: 10px; color: white; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 16px center; background-size: 16px;">
                    <option value="all" style="background: var(--bg-card); color: white;" <?php echo !$selectedGenre ? 'selected' : ''; ?>>All Genres</option>
                    <?php foreach ($allGenres as $genre): ?>
                    <option value="<?php echo urlencode($genre); ?>" style="background: var(--bg-card); color: white;" <?php echo $selectedGenre === $genre ? 'selected' : ''; ?>><?php echo htmlspecialchars($genre); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px; font-size: 1rem;"><i class="fas fa-star"></i> Filter by Rating</label>
                <select id="ratingSelect" style="width: 100%; padding: 14px 16px; background: rgba(255, 255, 255, 0.15); border: 2px solid rgba(226, 48, 32, 0.4); border-radius: 10px; color: white; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 16px center; background-size: 16px;">
                    <option value="all" style="background: var(--bg-card); color: white;" <?php echo !$selectedRating ? 'selected' : ''; ?>>All Ratings</option>
                    <option value="G" style="background: var(--bg-card); color: white;" <?php echo $selectedRating === 'G' ? 'selected' : ''; ?>>G - General</option>
                    <option value="PG" style="background: var(--bg-card); color: white;" <?php echo $selectedRating === 'PG' ? 'selected' : ''; ?>>PG</option>
                    <option value="PG-13" style="background: var(--bg-card); color: white;" <?php echo $selectedRating === 'PG-13' ? 'selected' : ''; ?>>PG-13</option>
                    <option value="R" style="background: var(--bg-card); color: white;" <?php echo $selectedRating === 'R' ? 'selected' : ''; ?>>R</option>
                    <option value="NC-17" style="background: var(--bg-card); color: white;" <?php echo $selectedRating === 'NC-17' ? 'selected' : ''; ?>>NC-17</option>
                </select>
            </div>
        </div>

        <?php if ($searchTerm || $selectedGenre || $selectedRating): ?>
        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(226, 48, 32, 0.2);">
            <div style="color: white; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fas fa-filter"></i> Active Filters:</div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <?php if ($searchTerm): ?>
                <span style="background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 4px 10px rgba(226, 48, 32, 0.2);">Search: "<?php echo htmlspecialchars($searchTerm); ?>" <a href="?page=movies&filter=<?php echo $filter; ?><?php echo $selectedGenre ? '&genre=' . urlencode($selectedGenre) : ''; echo $selectedRating ? '&rating=' . urlencode($selectedRating) : ''; ?>" style="color: white; text-decoration: none; font-weight: 700; margin-left: 5px; padding: 2px 6px; background: rgba(0,0,0,0.2); border-radius: 50%;">×</a></span>
                <?php endif; ?>
                <?php if ($selectedGenre): ?>
                <span style="background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 4px 10px rgba(226, 48, 32, 0.2);">Genre: <?php echo htmlspecialchars($selectedGenre); ?> <a href="?page=movies&filter=<?php echo $filter; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; echo $selectedRating ? '&rating=' . urlencode($selectedRating) : ''; ?>" style="color: white; text-decoration: none; font-weight: 700; margin-left: 5px; padding: 2px 6px; background: rgba(0,0,0,0.2); border-radius: 50%;">×</a></span>
                <?php endif; ?>
                <?php if ($selectedRating): ?>
                <span style="background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 4px 10px rgba(226, 48, 32, 0.2);">Rating: <?php echo htmlspecialchars($selectedRating); ?> <a href="?page=movies&filter=<?php echo $filter; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; echo $selectedGenre ? '&genre=' . urlencode($selectedGenre) : ''; ?>" style="color: white; text-decoration: none; font-weight: 700; margin-left: 5px; padding: 2px 6px; background: rgba(0,0,0,0.2); border-radius: 50%;">×</a></span>
                <?php endif; ?>
                <span style="margin-left: auto; color: var(--light-red); font-weight: 700; font-size: 1.1rem;"><?php echo count($filteredMovies); ?> movies found</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($filteredMovies)): ?>
        <div style="text-align: center; padding: 60px; background: rgba(226, 48, 32, 0.05); border-radius: 15px; border: 2px dashed rgba(226, 48, 32, 0.3);">
            <i class="fas fa-search fa-3x" style="color: var(--primary-red); margin-bottom: 20px; opacity: 0.8;"></i>
            <h3 style="color: white; margin-bottom: 15px; font-size: 1.8rem;">No Movies Found</h3>
            <p style="color: var(--pale-red); margin-bottom: 25px; max-width: 400px; margin-left: auto; margin-right: auto;">
                <?php if ($filter === 'now-showing'): ?>
                    No movies are currently showing. Check out Coming Soon!
                <?php elseif ($filter === 'coming-soon'): ?>
                    No upcoming movies announced yet. Check back later!
                <?php else: ?>
                    No movies match your search criteria.
                <?php endif; ?>
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="?page=movies&filter=all" class="btn btn-primary" style="padding: 12px 30px;">
                    <i class="fas fa-film"></i> View All Movies
                </a>
                <a href="?page=movies&filter=now-showing" class="btn btn-secondary" style="padding: 12px 30px;">
                    <i class="fas fa-play-circle"></i> Now Showing
                </a>
                <a href="?page=movies&filter=coming-soon" class="btn btn-secondary" style="padding: 12px 30px;">
                    <i class="fas fa-clock"></i> Coming Soon
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="movies-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px;">
            <?php foreach ($filteredMovies as $movie): ?>
            <div class="movie-card" style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); border-radius: 15px; overflow: hidden; transition: all 0.3s ease; border: 1px solid rgba(226, 48, 32, 0.2); position: relative; display: flex; flex-direction: column; height: 100%;">
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
                    <?php if ($filter === 'coming-soon' || (!in_array($movie['id'], array_column($now_showing, 'id')) && $filter !== 'now-showing')): ?>
                    <span style="background: rgba(241, 196, 15, 0.2); color: #f1c40f; padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(241, 196, 15, 0.3);">
                        <i class="fas fa-hourglass-half"></i> Coming Soon
                    </span>
                    <?php endif; ?>
                </div>
                
                <div style="padding: 25px; flex: 1; display: flex; flex-direction: column;">
                    <h3 style="color: white; font-size: 1.3rem; font-weight: 800; margin-bottom: 10px; line-height: 1.4;"><?php echo htmlspecialchars($movie['title']); ?></h3>
                    
                    <!-- Venue Information -->
                    <?php if (!empty($movie['venue_name']) || !empty($movie['venue_location'])): ?>
                    <div style="background: rgba(255, 255, 255, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 15px; border-left: 3px solid var(--primary-red);">
                        <?php if (!empty($movie['venue_name'])): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: white; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px;">
                            <i class="fas fa-building" style="color: var(--primary-red);"></i> <?php echo htmlspecialchars($movie['venue_name']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($movie['venue_location'])): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: var(--pale-red); font-size: 0.85rem;">
                            <i class="fas fa-map-marker-alt" style="color: var(--primary-red);"></i> <?php echo htmlspecialchars($movie['venue_location']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-bottom: 15px;">
                        <?php if ($movie['duration']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 5px;"><i class="fas fa-clock"></i> <?php echo $movie['duration']; ?></div>
                        <?php endif; ?>
                        <?php if ($movie['genre']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); font-size: 0.9rem;"><i class="fas fa-film"></i> <?php echo htmlspecialchars($movie['genre']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($movie['description']): ?>
                    <div style="margin-bottom: 15px; flex: 1;">
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($movie['description']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Schedule Dropdown - Directly above buttons with consistent spacing -->
                    <?php if (($filter === 'now-showing' || $filter === 'all') && !empty($schedules[$movie['id']])): ?>
                    <div style="margin-bottom: 15px; width: 100%;">
                        <select class="schedule-select" data-movie-id="<?php echo $movie['id']; ?>" style="width: 100%; padding: 12px 14px; background: rgba(255, 255, 255, 0.15); border: 2px solid rgba(226, 48, 32, 0.4); border-radius: 8px; color: white; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"white\" viewBox=\"0 0 20 20\"><path d=\"M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z\"/></svg>'); background-repeat: no-repeat; background-position: right 14px center; background-size: 14px;">
                            <option value="" style="background: var(--bg-card); color: white;">Select a showtime</option>
                            <?php foreach ($schedules[$movie['id']] as $schedule): 
                                $show_date = date('D, M d', strtotime($schedule['show_date']));
                                $show_time = date('h:i A', strtotime($schedule['showtime']));
                                $is_today = date('Y-m-d') == $schedule['show_date'] ? ' (Today)' : '';
                            ?>
                            <option value="<?php echo $schedule['id']; ?>" style="background: var(--bg-card); color: white;">
                                <?php echo $show_date . $is_today . ' • ' . $show_time . ' • ' . $schedule['available_seats'] . ' seats'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Buttons - Fixed height container to ensure consistent alignment -->
                    <div style="display: flex; gap: 5px; margin-top: auto; min-height: 45px;">
                        <?php if (!empty($movie['trailer_url'])): ?>
                        <a href="<?php echo $movie['trailer_url']; ?>" target="_blank" style="background: rgba(255, 0, 0, 0.2); color: #ff0000; border: 2px solid rgba(255, 0, 0, 0.3); padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; width: 45px; text-decoration: none;" title="Watch Trailer">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/movie-details&id=<?php echo $movie['id']; ?>" style="flex: 1; background: rgba(255,255,255,0.1); color: white; border: 2px solid rgba(226, 48, 32, 0.3); padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                        <?php if ($filter === 'now-showing' || ($filter === 'all' && !empty($schedules[$movie['id']]))): ?>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" class="book-now-btn" data-movie-id="<?php echo $movie['id']; ?>" style="flex: 1; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;">
                                    <i class="fas fa-ticket-alt"></i> Book
                                </a>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <a href="<?php echo SITE_URL; ?>index.php?page=login" style="flex: 1; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%); color: white; border: none; padding: 8px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; text-decoration: none; font-size: 0.9rem; height: 45px;">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: rgba(226, 48, 32, 0.05); border-radius: 10px; border: 1px solid rgba(226, 48, 32, 0.2);">
            <p style="color: var(--pale-red); font-size: 1.1rem;">
                Showing <strong style="color: white;"><?php echo count($filteredMovies); ?></strong> 
                <?php 
                if ($filter === 'now-showing') echo 'now showing';
                elseif ($filter === 'coming-soon') echo 'coming soon';
                else echo 'movies';
                ?>
                <?php if ($searchTerm || $selectedGenre || $selectedRating): ?>
                    (filtered from <?php echo count($movies); ?> total)
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<style>
.movie-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(226, 48, 32, 0.2);
    border-color: #e23020;
}

.filter-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(226, 48, 32, 0.2);
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

#genreSelect:hover, #ratingSelect:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(226, 48, 32, 0.2);
}

#genreSelect:focus, #ratingSelect:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.2);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 3px rgba(226, 48, 32, 0.3);
}

@media (max-width: 768px) {
    .movies-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 576px) {
    .movies-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const movieCards = document.querySelectorAll('.movie-card');
    movieCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'fadeInUp 0.5s ease forwards';
        card.style.opacity = '0';
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    const genreSelect = document.getElementById('genreSelect');
    const ratingSelect = document.getElementById('ratingSelect');
    const filter = '<?php echo $filter; ?>';
    
    genreSelect.addEventListener('change', function() {
        const genre = this.value;
        const rating = ratingSelect.value;
        let url = '?page=movies&filter=' + filter;
        
        if (genre && genre !== 'all') {
            url += '&genre=' + encodeURIComponent(genre);
        }
        
        if (rating && rating !== 'all') {
            url += '&rating=' + encodeURIComponent(rating);
        }
        
        window.location.href = url;
    });
    
    ratingSelect.addEventListener('change', function() {
        const rating = this.value;
        const genre = genreSelect.value;
        let url = '?page=movies&filter=' + filter;
        
        if (genre && genre !== 'all') {
            url += '&genre=' + encodeURIComponent(genre);
        }
        
        if (rating && rating !== 'all') {
            url += '&rating=' + encodeURIComponent(rating);
        }
        
        window.location.href = url;
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
    
    const style = document.createElement('style');
    style.textContent = `
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
        .movie-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        select option {
            padding: 12px !important;
            font-size: 1rem !important;
        }
        select option:hover {
            background: rgba(226, 48, 32, 0.3) !important;
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php require_once $root_dir . '/partials/footer.php'; ?>