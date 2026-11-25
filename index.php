<?php
require_once 'config.php';
require_once 'get_recommendations.php';

// 1. Fetch dynamic sports list with court counts and minimum price
$sports_query = "SELECT s.id, s.name, 
                        COUNT(c.id) as court_count, 
                        COALESCE(MIN(c.price_per_hour), 0) as min_price
                 FROM sports s 
                 LEFT JOIN courts c ON s.id = c.sport_id 
                 GROUP BY s.id, s.name 
                 ORDER BY court_count DESC, s.name ASC";
$sports_result = mysqli_query($conn, $sports_query);
$sports_list = [];
if ($sports_result) {
    while ($row = mysqli_fetch_assoc($sports_result)) {
        $sports_list[] = $row;
    }
}

// 2. Fetch live stats
$stat_courts_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM courts");
$stat_courts = ($stat_courts_res) ? (int)mysqli_fetch_assoc($stat_courts_res)['total'] : 0;

$stat_users_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
$stat_users = ($stat_users_res) ? (int)mysqli_fetch_assoc($stat_users_res)['total'] : 0;

$stat_bookings_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE status = 'confirmed'");
$stat_bookings = ($stat_bookings_res) ? (int)mysqli_fetch_assoc($stat_bookings_res)['total'] : 0;

// 3. Fetch dynamic recommendations
$recs = getRecommendations();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InBook - The Arena</title>
    <link rel="stylesheet" href="style.css?v=1.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Small overrides or critical inline styles can stay if absolutely necessary, 
           but we've moved the main ones to style.css */
    </style>
</head>
<body>
    <!-- 3D Animated Background -->
    <div class="bg-3d"></div>
    <div class="bg-overlay"></div>

    <!-- Navigation -->
    <nav class="navbar glass-panel">
        <div class="container">
            <a href="index.php" class="logo-text">INBOOK</a>
            
            <div class="menu-toggle" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </div>

            <div class="nav-links">
                <a href="index.php" class="active">HOME</a>
                <?php if (isLoggedIn()): ?>
                    <a href="profile.php">PROFILE</a>
                    <a href="logout.php" class="btn-logout">LOGOUT</a>
                <?php else: ?>
                    <a href="login.php" class="btn-logout">LOGIN</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Hero Section + Booking Bar -->
        <div class="hero reveal active hero-section">
            <div class="hero-content">
                <h1 class="hero-title-main">
                    UNLEASH
                </h1>
                <h1 class="hero-title-outline">
                    YOUR GAME
                </h1>
                <!-- Hero Subtitle removed as requested -->
            </div>

            <!-- Booking Bar -->
            <div class="booking-bar-container">
                <!-- Removed small title row, moved to main header above -->
                
                <form action="calendar.php" method="GET" class="booking-form-inline" id="bookingBarForm">
                    <!-- Date -->
                    <div class="booking-field">
                        <label>WHEN?</label>
                        <div class="input-group">
                            <i class="far fa-calendar-alt"></i>
                            <input type="date" name="date" class="booking-input" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <!-- Sport -->
                    <div class="booking-field">
                        <label>WHAT SPORT?</label>
                        <div class="input-group">
                            <i class="fas fa-running"></i>
                            <select name="sport" id="barSport" class="booking-input" onchange="loadBarCourts(this.value)" required>
                                <option value="">Select Sport...</option>
                                <?php foreach ($sports_list as $sport_item): ?>
                                    <option value="<?= (int)$sport_item['id'] ?>"><?= htmlspecialchars($sport_item['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Court -->
                    <div class="booking-field">
                        <label>WHICH COURT?</label>
                        <div class="input-group">
                            <i class="fas fa-map-marker-alt"></i>
                            <select name="court" id="barCourt" class="booking-input" required>
                                <option value="">Select Sport First</option>
                            </select>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="booking-field">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-book-bar">
                            BOOK NOW <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recommendations Section -->
        <div class="recommendations-section reveal active">
            <div class="recommendations-grid">
                <?php if (isset($recs['personalized'])): 
                    $p_url = "calendar.php?sport=" . $recs['personalized']['sport_id'] . "&court=" . $recs['personalized']['court_id'] . "&date=" . date('Y-m-d');
                ?>
                    <a href="<?= isLoggedIn() ? $p_url : 'login.php?redirect=' . urlencode($p_url) ?>" class="recommendation-card" <?= !isLoggedIn() ? 'onclick="showToast();"' : '' ?>>
                        <div class="rec-type-badge"><?= $recs['personalized']['type'] ?></div>
                        <div class="rec-icon">
                            <i class="fas <?= $recs['personalized']['icon'] ?>"></i>
                        </div>
                        <div class="rec-content">
                            <h4><?= $recs['personalized']['title'] ?></h4>
                            <p><?= $recs['personalized']['subtitle'] ?></p>
                        </div>
                        <div class="rec-action">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (isset($recs['trending'])): 
                    $t_url = "calendar.php?court=" . $recs['trending']['court_id'] . "&date=" . date('Y-m-d') . "&sport=" . $recs['trending']['sport_id'];
                ?>
                    <a href="<?= isLoggedIn() ? $t_url : 'login.php?redirect=' . urlencode($t_url) ?>" class="recommendation-card" <?= !isLoggedIn() ? 'onclick="showToast();"' : '' ?>>
                        <div class="rec-type-badge"><?= $recs['trending']['type'] ?></div>
                        <div class="rec-icon">
                            <i class="fas <?= $recs['trending']['icon'] ?>"></i>
                        </div>
                        <div class="rec-content">
                            <h4><?= $recs['trending']['title'] ?></h4>
                            <p><?= $recs['trending']['subtitle'] ?></p>
                        </div>
                        <div class="rec-action">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- The Bento Grid (Sports Selection) -->
        <div class="bento-grid">
            <?php 
            $layout_classes = ['bento-large', 'bento-tall', 'bento-normal', 'bento-normal', 'bento-tall', 'bento-large'];
            foreach ($sports_list as $idx => $sport_item):
                $layout_class = $layout_classes[$idx % count($layout_classes)];
                $icon_class = getSportIcon($sport_item['name']);
                if ($sport_item['court_count'] > 0) {
                    $subtitle = $sport_item['court_count'] . ($sport_item['court_count'] == 1 ? ' Court' : ' Courts') . ' • From Rs. ' . number_format($sport_item['min_price'], 0) . '/hr';
                } else {
                    $subtitle = 'Courts Coming Soon';
                }
            ?>
                <div class="bento-item <?= $layout_class ?> reveal <?= !isLoggedIn() ? 'locked' : '' ?>" 
                     data-id="<?= (int)$sport_item['id'] ?>" 
                     data-sport="<?= htmlspecialchars(strtolower($sport_item['name'])) ?>" 
                     onclick="handleCardClick(this)">
                    <div class="bento-content">
                        <i class="fas <?= $icon_class ?> sport-icon icon-large"></i>
                        <h2 class="sport-title"><?= htmlspecialchars($sport_item['name']) ?></h2>
                        <p style="color:var(--text-gray); margin-top:10px;"><?= htmlspecialchars($subtitle) ?></p>
                    </div>
                    <?php if (!isLoggedIn()): ?>
                        <div style="position:absolute; bottom:20px; color:var(--text-gray); font-size:24px;">
                            <i class="fas fa-lock"></i>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Live Stats Ticker -->
        <div class="stats-strip glass-panel reveal" style="border-radius: 20px;">
            <div class="stat-item">
                <span class="stat-number" data-target="<?= max($stat_courts, 1) ?>">0</span>
                <span class="stat-label">Courts Available</span>
            </div>
            <div class="stat-item">
                <span class="stat-number" data-target="<?= max($stat_users, 1) ?>">0</span>
                <span class="stat-label">Active Players</span>
            </div>
            <div class="stat-item">
                <span class="stat-number" data-target="<?= max($stat_bookings, 1) ?>">0</span>
                <span class="stat-label">Bookings Made</span>
            </div>
        </div>

        <!-- Features Section -->
        <div class="section-header reveal">
            <p class="section-subtitle">Why Choose Us</p>
            <h2 class="section-title">Premium Facilities</h2>
        </div>

        <div class="feature-grid">
            <div class="feature-card reveal">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 style="color:var(--white); margin-bottom:15px;">FIFA-Grade Turf</h3>
                <p style="color:var(--text-gray);">Play on the same surface as the pros. Our turfs are maintained daily for perfect bounce.</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 style="color:var(--white); margin-bottom:15px;">Night Floodlights</h3>
                <p style="color:var(--text-gray);">Don't let the sun stop you. Our stadium-grade lighting ensures clear vision 24/7.</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 style="color:var(--white); margin-bottom:15px;">Secure Lockers</h3>
                <p style="color:var(--text-gray);">Keep your gear safe. Digital keypad lockers are included with every booking.</p>
            </div>
        </div>

        <!-- Dynamic Testimonials -->
        <div class="section-header reveal">
            <p class="section-subtitle">Community Love</p>
            <h2 class="section-title">Player Stories</h2>
        </div>

        <div class="testimonials-grid reveal">
            <?php
            // Fetch 3 latest reviews with user's full name
            $review_query = "SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 3";
            $review_result = mysqli_query($conn, $review_query);
            
            if ($review_result && mysqli_num_rows($review_result) > 0):
                while ($review = mysqli_fetch_assoc($review_result)):
            ?>
                <div class="testimonial-card">
                    <div class="user-profile">
                        <div class="user-avatar"><?= strtoupper(substr($review['full_name'], 0, 1)) ?></div>
                        <div>
                            <h4 style="color:var(--white);"><?= htmlspecialchars($review['full_name']) ?></h4>
                            <div class="stars">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <p class="quote">"<?= htmlspecialchars($review['comment']) ?>"</p>
                </div>
            <?php 
                endwhile;
            else:
                // Fallback if no reviews yet
            ?>
                <div class="testimonial-card">
                    <div class="user-profile">
                        <div class="user-avatar">I</div>
                        <div>
                            <h4 style="color:var(--white);">Admin</h4>
                            <div class="stars">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="quote">"Be the first to share your experience after booking a court!"</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Site Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <h2>INBOOK</h2>
                    <p>The ultimate platform for sports enthusiasts to book and play. Premium courts, instant confirmation.</p>
                </div>
                <div class="footer-links">
                    <h3>Sports</h3>
                    <ul>
                        <?php foreach (array_slice($sports_list, 0, 4) as $s_item): ?>
                            <li><a href="#bookingBarForm" onclick="document.getElementById('barSport').value = '<?= $s_item['id'] ?>'; loadBarCourts('<?= $s_item['id'] ?>');"><?= htmlspecialchars($s_item['name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-links">
                    <h3>Contact</h3>
                    <ul>
                        <li><a href="#">support@inbook.com</a></li>
                        <li><a href="#">+1 234 567 890</a></li>
                        <li><a href="#">Location Map</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> InBook Sports. All Rights Reserved.
            </div>
        </div>
    </footer>

    <!-- Booking Modal (Still kept for Card Clicks) -->
    <div class="home-modal-overlay" id="bookingModal">
        <div class="home-modal glass-panel">
            <div class="modal-header">
                <h3 class="modal-title">Book Court</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="process_booking.php" method="POST" id="modalForm">
                <div class="form-group">
                    <label class="form-label">Select Court</label>
                    <select id="courtSelect" name="court_id" class="form-control" required>
                        <option value="">Loading courts...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Date</label>
                    <input type="date" id="dateSelect" name="booking_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                </div>

                <button type="button" class="btn btn-primary" onclick="proceedToCalendar()">
                    FIND TIME SLOTS
                </button>
            </form>
        </div>
    </div>

    <!-- Toast Notification for Guests -->
    <div id="toast" class="toast-notification">
        <i class="fas fa-lock"></i> 
        <span>Login required to book a court</span>
    </div>

    <script>
        const isLoggedIn = <?= isLoggedIn() ? 'true' : 'false' ?>;

        // ================= Page Transitions ================= //
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
        });

        // Intercept links for smooth exit
        document.querySelectorAll('a, button[type=submit]').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if(href && href.startsWith('#')) return; // Ignore anchors
                if(this.target === '_blank') return;
                
                // Allow form submit to handle its own thing unless it's a direct link behavior
                if(this.tagName === 'BUTTON' && !href) return;

                e.preventDefault();
                document.body.classList.remove('loaded');
                document.body.classList.add('fade-out');
                
                setTimeout(() => {
                    if(href) window.location.href = href;
                }, 500); // Wait for transition
            });
        });

        // ================= Scroll Reveal ================= //
        const observerOptions = { threshold: 0.1 };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) entry.target.classList.add('active');
            });
        }, observerOptions);
        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

        // ================= Modern Spotlight & Tilt Effect ================= //
        const bg = document.querySelector('.bg-3d');
        const overlay = document.querySelector('.bg-overlay');

        setTimeout(() => {
            bg.classList.add('hero-animate');
            bg.style.animation = 'none';
            void bg.offsetWidth; 
            bg.style.animation = null; 
        }, 1500);

        document.addEventListener('mousemove', (e) => {
            const xVal = e.clientX;
            const yVal = e.clientY;
            
            // 1. Dynamic Spotlight (Lighting)
            // Moves the center of the radial gradient to cursor position
            overlay.style.background = `radial-gradient(circle at ${xVal}px ${yVal}px, transparent 15%, rgba(17, 17, 17, 0.85) 50%, rgba(17, 17, 17, 1) 100%)`;

            // 2. Subtle 3D Tilt
            const centerX = window.innerWidth / 2;
            const centerY = window.innerHeight / 2;
            
            // Max rotation degrees
            const maxTilt = 2; 
            
            const rotateY = ((xVal - centerX) / centerX) * maxTilt;
            const rotateX = ((centerY - yVal) / centerY) * maxTilt;

            // Apply rotation (Keep scale from floating animation concept)
            // Note: We modify transform here, but we need to preserve the CSS animation.
            // A cleaner way for simple tilt + CSS animation is using CSS variables or a wrapper.
            // Since we want simple, let's target specific vars if possible, or just overwrite transform safely
            // For now, let's use a VERY subtle translate parallax instead of rotation to avoid fighting the 'floating' keyframe transform
            // actually, let's just do lighting + verify subtle parallax
            
            // Let's stick to the Spotlight as the main "New Trend" feature. 
            // It looks like a flashlight revealing the player in the dark.
        });

        // ================= Interactivity ================= //
        function handleCardClick(card) {
            if (!isLoggedIn) {
                card.classList.remove('shake');
                void card.offsetWidth;
                card.classList.add('shake');
                showToast();
                return;
            }
            openModal(card.dataset.id, card.querySelector('.sport-title').innerText);
        }

        function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // ================= Modal Logic ================= //
        const modalOverlay = document.getElementById('bookingModal');
        let currentSportId = null;

        function openModal(sportId, title) {
            currentSportId = sportId;
            document.querySelector('.modal-title').innerText = `Book ${title} Court`;
            modalOverlay.classList.add('active');
            const select = document.getElementById('courtSelect');
            select.innerHTML = '<option>Loading...</option>';
            fetchCourts(sportId, select);
        }

        function closeModal() {
            modalOverlay.classList.remove('active');
        }

        function proceedToCalendar() {
            const courtId = document.getElementById('courtSelect').value;
            const date = document.getElementById('dateSelect').value;
            if (!courtId || !date) { alert('Please select both a court and a date'); return; }
            window.location.href = `calendar.php?sport=${currentSportId}&court=${courtId}&date=${date}`;
        }

        // ================= Menu Logic ================= //
        function toggleMenu() {
            const nav = document.querySelector('.nav-links');
            nav.classList.toggle('active');
        }

        // ================= Bar Logic ================= //
        function loadBarCourts(sportId) {
            const select = document.getElementById('barCourt');
            if(!sportId) {
                select.innerHTML = '<option value="">Select Sport First</option>';
                return;
            }
            select.innerHTML = '<option>Checking availability...</option>';
            fetchCourts(sportId, select); // Unified fetch function
        }

        function fetchCourts(sportId, selectElement) {
            fetch(`get_courts.php?sport_id=${sportId}`)
                .then(response => response.json())
                .then(data => {
                    selectElement.innerHTML = '';
                    if(data.length === 0) {
                        selectElement.innerHTML = '<option value="">Fully Booked</option>';
                    } else {
                        data.forEach(court => {
                            selectElement.innerHTML += `<option value="${court.id}">${court.name} - Rs. ${court.price_per_hour}/hr</option>`;
                        });
                    }
                })
                .catch(err => {
                    selectElement.innerHTML = '<option>Error loading courts</option>';
                });
        }

        document.getElementById('bookingBarForm').addEventListener('submit', function(e) {
            if (!isLoggedIn) {
                e.preventDefault();
                showToast(); 
                const sport = document.getElementById('barSport').value;
                const court = document.getElementById('barCourt').value;
                const date = document.querySelector('input[name="date"]').value;
                const targetUrl = `calendar.php?sport=${sport}&court=${court}&date=${date}`;
                setTimeout(() => {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(targetUrl);
                }, 1000);
            }
        });

        // ================= Stats ================= //
        const stats = document.querySelectorAll('.stat-number');
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    animateValue(entry.target, 0, parseInt(entry.target.getAttribute('data-target')), 2000);
                    statsObserver.unobserve(entry.target);
                }
            });
        });
        stats.forEach(stat => statsObserver.observe(stat));

        function animateValue(obj, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerHTML = Math.floor(progress * (end - start) + start) + (end > 100 ? '+' : '');
                if (progress < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        }
    </script>
    <?php include 'chatbot.php'; ?>
</body>
</html>