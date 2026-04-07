<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if ($user_id === 0) {
    redirect('logout.php'); // Invalid session
}

// Fetch user details
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);

if (!$user_result || mysqli_num_rows($user_result) === 0) {
    // User not found in DB (possibly deleted)
    redirect('logout.php');
}

$user = mysqli_fetch_assoc($user_result);

// Fetch upcoming bookings
$upcoming_query = "SELECT b.*, c.name as court_name, s.name as sport_name 
                   FROM bookings b
                   JOIN courts c ON b.court_id = c.id
                   JOIN sports s ON c.sport_id = s.id
                   WHERE b.user_id = $user_id 
                   AND b.booking_date >= CURDATE()
                   AND b.status = 'confirmed'
                   ORDER BY b.booking_date, b.start_time";
$upcoming_result = mysqli_query($conn, $upcoming_query);

if (!$upcoming_result) {
    // Graceful fallback for bookings query error
    $upcoming_result = false; 
    // echo mysqli_error($conn); // Debug if needed
}

// Fetch past bookings
$past_query = "SELECT b.*, c.name as court_name, s.name as sport_name 
               FROM bookings b
               JOIN courts c ON b.court_id = c.id
               JOIN sports s ON c.sport_id = s.id
               WHERE b.user_id = $user_id 
               AND b.booking_date < CURDATE()
               ORDER BY b.booking_date DESC, b.start_time DESC
               LIMIT 10";
$past_result = mysqli_query($conn, $past_query);

if (!$past_result) {
    $past_result = false;
}

// Smart Reminder Logic
$reminder_data = null;
$reminder_type = null;

// Check for upcoming bookings within 24 hours
$upcoming_soon_query = "SELECT b.*, c.name as court_name, s.name as sport_name 
                        FROM bookings b
                        JOIN courts c ON b.court_id = c.id
                        JOIN sports s ON c.sport_id = s.id
                        WHERE b.user_id = $user_id 
                        AND b.status = 'confirmed'
                        AND b.booking_date >= CURDATE()
                        AND CONCAT(b.booking_date, ' ', b.start_time) <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                        ORDER BY b.booking_date, b.start_time
                        LIMIT 1";
$upcoming_soon_result = mysqli_query($conn, $upcoming_soon_query);

if ($upcoming_soon_result && mysqli_num_rows($upcoming_soon_result) > 0) {
    $reminder_data = mysqli_fetch_assoc($upcoming_soon_result);
    $reminder_type = 'upcoming';
} else {
    // Check if user hasn't booked in the last 7 days
    $last_booking_query = "SELECT MAX(booking_date) as last_date FROM bookings 
                           WHERE user_id = $user_id AND status = 'confirmed'";
    $last_booking_result = mysqli_query($conn, $last_booking_query);
    $last_booking = mysqli_fetch_assoc($last_booking_result);
    
    if ($last_booking && $last_booking['last_date']) {
        $days_since = (strtotime('now') - strtotime($last_booking['last_date'])) / (60 * 60 * 24);
        if ($days_since > 7) {
            $reminder_type = 'inactive';
            $reminder_data = ['days' => floor($days_since)];
        }
    } else {
        $reminder_type = 'new_user';
    }
}

// Fetch recent bookings for re-book functionality
$recent_bookings_query = "SELECT b.*, c.name as court_name, c.id as court_id, s.name as sport_name 
                          FROM bookings b
                          JOIN courts c ON b.court_id = c.id
                          JOIN sports s ON c.sport_id = s.id
                          WHERE b.user_id = $user_id 
                          AND b.status = 'confirmed'
                          ORDER BY b.booking_date DESC
                          LIMIT 5";
$recent_bookings_result = mysqli_query($conn, $recent_bookings_query);

// Recommendation System - Find courts user hasn't tried
$favorite_sport_query = "SELECT s.id as sport_id, s.name as sport_name, COUNT(*) as count 
                         FROM bookings b
                         JOIN courts c ON b.court_id = c.id
                         JOIN sports s ON c.sport_id = s.id
                         WHERE b.user_id = $user_id
                         GROUP BY s.id
                         ORDER BY count DESC
                         LIMIT 1";
$favorite_sport_result = mysqli_query($conn, $favorite_sport_query);
$favorite_sport_data = ($favorite_sport_result && mysqli_num_rows($favorite_sport_result) > 0) 
                       ? mysqli_fetch_assoc($favorite_sport_result) 
                       : null;

$recommendations = [];
if ($favorite_sport_data) {
    // Find courts with user's favorite sport that they haven't booked
    $recommendation_query = "SELECT c.id, c.name, c.price_per_hour, s.name as sport_name
                             FROM courts c
                             JOIN sports s ON c.sport_id = s.id
                             WHERE s.id = {$favorite_sport_data['sport_id']}
                             AND c.id NOT IN (
                                 SELECT DISTINCT court_id FROM bookings WHERE user_id = $user_id
                             )
                             LIMIT 4";
    $recommendation_result = mysqli_query($conn, $recommendation_query);
    
    if ($recommendation_result) {
        while ($rec = mysqli_fetch_assoc($recommendation_result)) {
            $recommendations[] = $rec;
        }
    }
}

// Handle profile update
$profile_message = '';
if (isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    $update_query = "UPDATE users SET full_name = '$full_name', email = '$email', phone = '$phone' 
                     WHERE id = $user_id";
    if (mysqli_query($conn, $update_query)) {
        $profile_message = 'Profile updated successfully!';
        // Refresh user data
        $user['full_name'] = $full_name;
        $user['email'] = $email;
        $user['phone'] = $phone;
    } else {
        $profile_message = 'Error updating profile.';
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_query = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
            if (mysqli_query($conn, $password_query)) {
                $profile_message = 'Password changed successfully!';
            } else {
                $profile_message = 'Error changing password.';
            }
        } else {
            $profile_message = 'New passwords do not match.';
        }
    } else {
        $profile_message = 'Current password is incorrect.';
    }
}

// Handle booking cancellation
if (isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    $cancel_query = "UPDATE bookings SET status = 'cancelled' 
                     WHERE id = $booking_id AND user_id = $user_id";
    mysqli_query($conn, $cancel_query);
    redirect('profile.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Stadium Booking</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="src/profile_revamp.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
</head>
<body>
    <!-- Background Animation -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo-text">INBOOK</a>
            <div class="nav-links">
                <a href="index.php">HOME</a>
                <a href="profile.php" class="active">PROFILE</a>
                <a href="logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- User Profile Header -->
        <div class="card">
            <div class="profile-header-content">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?> &nbsp;|&nbsp; <i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></p>
                </div>
            </div>
        </div>

        <!-- Smart Reminder Card -->
        <?php if ($reminder_type): ?>
        <div class="reminder-card">
            <?php if ($reminder_type === 'upcoming'): ?>
                <div class="reminder-icon"><i class="fas fa-bolt"></i></div>
                <div class="reminder-content">
                    <div class="reminder-title"><?= htmlspecialchars($reminder_data['court_name']) ?></div>
                    <div class="reminder-text">
                        <?= date('M j', strtotime($reminder_data['booking_date'])) ?> • <?= date('g:i A', strtotime($reminder_data['start_time'])) ?>
                    </div>
                    <a href="#upcoming-content" class="btn btn-primary reminder-action">View</a>
                </div>
            <?php elseif ($reminder_type === 'inactive'): ?>
                <div class="reminder-icon"><i class="fas fa-calendar-times"></i></div>
                <div class="reminder-content">
                    <div class="reminder-title">Come Back!</div>
                    <div class="reminder-text"><?= $reminder_data['days'] ?> days ago</div>
                    <a href="index.php" class="btn btn-primary reminder-action">Book</a>
                </div>
            <?php elseif ($reminder_type === 'new_user'): ?>
                <div class="reminder-icon"><i class="fas fa-star"></i></div>
                <div class="reminder-content">
                    <div class="reminder-title">Start Booking</div>
                    <div class="reminder-text">Find your court</div>
                    <a href="index.php" class="btn btn-primary reminder-action">Explore</a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Re-Book Section -->
        <?php if ($recent_bookings_result && mysqli_num_rows($recent_bookings_result) > 0): ?>
        <div class="card">
            <h3 class="section-title"><i class="fas fa-trophy"></i> Recent</h3>
            <div class="rebook-grid">
                <?php 
                mysqli_data_seek($recent_bookings_result, 0); // Reset pointer
                while ($booking = mysqli_fetch_assoc($recent_bookings_result)): 
                ?>
                <div class="rebook-card">
                    <div class="rebook-title"><?= htmlspecialchars($booking['court_name']) ?></div>
                    <div class="rebook-detail">
                        <i class="fas fa-dumbbell"></i> <?= htmlspecialchars($booking['sport_name']) ?><br>
                        <i class="fas fa-calendar-day"></i> <?= date('M j', strtotime($booking['booking_date'])) ?><br>
                        <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($booking['start_time'])) ?>
                    </div>
                    <form action="calendar.php" method="GET">
                        <input type="hidden" name="court_id" value="<?= $booking['court_id'] ?>">
                        <input type="hidden" name="sport" value="<?= $booking['sport_name'] ?>">
                        <button type="submit" class="btn btn-primary btn-small"><i class="fas fa-redo"></i> Book</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recommendations Section -->
        <?php if (!empty($recommendations)): ?>
        <div class="card">
            <h3 class="section-title"><i class="fas fa-target"></i> Try These</h3>
            <div class="recommendation-grid">
                <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-card">
                    <span class="recommendation-sport"><i class="fas fa-medal"></i> <?= htmlspecialchars($rec['sport_name']) ?></span>
                    <div class="recommendation-name"><?= htmlspecialchars($rec['name']) ?></div>
                    <div class="recommendation-price">
                        <i class="fas fa-dollar-sign"></i><?= number_format($rec['price_per_hour'], 0) ?>/hr
                    </div>
                    <form action="calendar.php" method="GET">
                        <input type="hidden" name="court_id" value="<?= $rec['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-small"><i class="fas fa-play"></i> Try</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>



        <!-- Bookings History -->
        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('upcoming')">Upcoming Bookings</button>
                <button class="tab-btn" onclick="switchTab('past')">Past History</button>
            </div>

            <!-- Upcoming Content -->
            <div id="upcoming-content" class="tab-content active">
                <?php if ($upcoming_result && mysqli_num_rows($upcoming_result) > 0): ?>
                    <?php while ($booking = mysqli_fetch_assoc($upcoming_result)): ?>
                        <div class="booking-item">
                            <div class="booking-info">
                                <h3><?= htmlspecialchars($booking['court_name']) ?></h3>
                                <p>
                                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($booking['booking_date'])) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($booking['start_time'])) ?></span>
                                    <span><i class="fas fa-running"></i> <?= htmlspecialchars($booking['sport_name']) ?></span>
                                </p>
                            </div>
                            <div class="booking-actions">
                                <form method="POST" onsubmit="return confirm('Cancel this booking?');" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <button type="submit" name="cancel_booking" class="btn-cancel">Cancel</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No upcoming bookings found. Book a court above!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Past Content -->
            <div id="past-content" class="tab-content">
                <?php if ($past_result && mysqli_num_rows($past_result) > 0): ?>
                    <?php while ($booking = mysqli_fetch_assoc($past_result)): ?>
                        <div class="booking-item past">
                            <div class="booking-info">
                                <h3><?= htmlspecialchars($booking['court_name']) ?></h3>
                                <p>
                                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($booking['booking_date'])) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($booking['start_time'])) ?></span>
                                </p>
                            </div>
                            <span class="booking-status status-<?= $booking['status'] ?>">
                                <?= ucfirst($booking['status']) ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No booking history available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Settings Section -->
        <div class="card settings-section">
            <h3 class="section-title"><i class="fas fa-cog"></i> Account Settings</h3>
            
            <?php if ($profile_message): ?>
                <div class="profile-update-msg" style="background: <?= strpos($profile_message, 'success') !== false ? 'rgba(0, 255, 136, 0.1)' : 'rgba(255, 85, 85, 0.1)' ?>; color: <?= strpos($profile_message, 'success') !== false ? 'var(--primary-green)' : '#ff5555' ?>;">
                    <?= htmlspecialchars($profile_message) ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-btn active" onclick="switchSettingsTab('profile')">Update Profile</button>
                <button class="tab-btn" onclick="switchSettingsTab('password')">Change Password</button>
            </div>

            <!-- Profile Update Form -->
            <div id="profile-settings" class="tab-content active">
                <form method="POST" class="booking-form">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>

            <!-- Password Change Form -->
            <div id="password-settings" class="tab-content">
                <form method="POST" class="booking-form">
                    <!-- Hidden username for password managers -->
                    <input type="text" name="username" value="<?= htmlspecialchars($user['email']) ?>" autocomplete="username" style="display:none;" aria-hidden="true">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Page Transition
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
        });

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tab + '-content').classList.add('active');
        }

        function switchSettingsTab(tab) {
            // Get all tab buttons in settings section
            const settingsButtons = document.querySelectorAll('.settings-section .tab-btn');
            settingsButtons.forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            // Switch content
            document.getElementById('profile-settings').classList.remove('active');
            document.getElementById('password-settings').classList.remove('active');
            document.getElementById(tab + '-settings').classList.add('active');
        }
    </script>
</body>
</html>