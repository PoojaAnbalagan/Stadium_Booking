<?php
require_once 'config.php';

if (!isset($_GET['sport']) || !isset($_GET['court']) || !isset($_GET['date'])) {
    redirect('index.php');
}

$sport_id = intval($_GET['sport']);
$court_id = intval($_GET['court']);
$booking_date = sanitize($_GET['date']);

// Fetch court (same logic)
$court_query = "SELECT c.*, s.name as sport_name FROM courts c JOIN sports s ON c.sport_id = s.id WHERE c.id = $court_id";
$court_result = mysqli_query($conn, $court_query);
$court = mysqli_fetch_assoc($court_result);

if (!$court) redirect('index.php');

// Fetch bookings (same logic)
$bookings_query = "SELECT start_time FROM bookings WHERE court_id = $court_id AND booking_date = '$booking_date' AND status = 'confirmed'";
$bookings_result = mysqli_query($conn, $bookings_query);
$booked_slots = [];
while ($booking = mysqli_fetch_assoc($bookings_result)) {
    $booked_slots[] = $booking['start_time'];
}

// Generate slots 8 AM - 10 PM
$time_slots = [];
for ($hour = 8; $hour < 22; $hour++) {
    $time_slots[] = sprintf("%02d:00:00", $hour);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Time - InBook</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- 3D Animated Background -->
    <div class="bg-3d"></div>
    <div class="bg-overlay"></div>

    <!-- Nav -->
    <nav class="navbar glass-panel">
        <div class="container">
            <a href="index.php" class="logo-text">INBOOK</a>
            <div class="nav-links">
                <a href="index.php">HOME</a>
                <a href="profile.php">PROFILE</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="calendar-wrapper model-zoom reveal active">
            <div class="cal-header">
                <h2 class="cal-title"><?= htmlspecialchars($court['name']) ?></h2>
                <p class="cal-subtitle">
                    <i class="fas fa-calendar-alt"></i> <?= date('l, F j, Y', strtotime($booking_date)) ?>
                </p>
                <div style="margin-top:10px; color:var(--text-gray);">
                    <i class="fas fa-money-bill"></i> Price: <span style="color:var(--white); font-weight:bold;"><?= number_format($court['price_per_hour']) ?>/hr</span>
                </div>
            </div>

            <form action="process_booking.php" method="POST" id="slotForm">
                <input type="hidden" name="court_id" value="<?= $court_id ?>">
                <input type="hidden" name="booking_date" value="<?= $booking_date ?>">
                <input type="hidden" name="start_time" id="selectedTimeInput">
                <input type="hidden" name="price" id="basePrice" value="<?= $court['price_per_hour'] ?>">

                <!-- Duration Selector -->
                <div class="duration-selector glass-panel reveal active" style="margin-bottom: 30px; padding: 20px; border-radius: 15px;">
                    <label style="color:var(--text-gray); font-weight:bold; display:block; margin-bottom:10px;">HOW LONG WILL YOU PLAY?</label>
                    <div class="duration-options">
                        <select name="duration" id="bookingDuration" class="booking-input" style="background:rgba(255,255,255,0.05); color:var(--white); border:1px solid rgba(255,255,255,0.1); width:100%; padding:12px; border-radius:10px;" onchange="updateTotalPrice()">
                            <option value="1">1 Hour (Standard)</option>
                            <option value="2">2 Hours</option>
                            <option value="3">3 Hours</option>
                            <option value="4">4 Hours</option>
                        </select>
                    </div>
                </div>

                <div class="slot-grid">
                    <?php foreach ($time_slots as $slot): 
                        $is_booked = in_array($slot, $booked_slots);
                        $display_time = date('g:i A', strtotime($slot));
                    ?>
                        <div class="time-box <?= $is_booked ? 'booked' : '' ?>" 
                             onclick="<?= $is_booked ? '' : "selectSlot(this, '$slot')" ?>">
                            <div style="font-size:1.1rem; font-weight:bold;"><?= $display_time ?></div>
                            <div style="font-size:0.8rem; margin-top:5px; text-transform:uppercase;">
                                <?= $is_booked ? 'Booked' : 'Available' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align:center; margin-top:40px; ">
                    <a href="index.php" class="btn" style="background:var(--medium-gray); color:var(--white);text-decoration:none; margin-right:20px; margin-bottom:20px;">BACK</a>
                    <button type="button" class="btn btn-primary" id="bookBtn" onclick="submitBooking()" disabled>
                        PROCEED TO BOOK
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification">
        <i class="fas fa-exclamation-circle"></i> 
        <span>Please login to complete your booking</span>
    </div>

    <script>
        const isLoggedIn = <?= isLoggedIn() ? 'true' : 'false' ?>;

        // Page Transition
        document.addEventListener('DOMContentLoaded', () => document.body.classList.add('loaded'));
        
        // ... link transition code ...

        function selectSlot(el, time) {
            document.querySelectorAll('.time-box').forEach(box => box.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selectedTimeInput').value = time;
            document.getElementById('bookBtn').disabled = false;
            updateTotalPrice();
        }

        function updateTotalPrice() {
            const basePrice = parseFloat(document.getElementById('basePrice').value);
            const duration = parseInt(document.getElementById('bookingDuration').value);
            const total = basePrice * duration;
            const bookBtn = document.getElementById('bookBtn');
            
            if (!bookBtn.disabled) {
                bookBtn.innerHTML = `PROCEED TO BOOK ($${total})`;
            }
        }

        function submitBooking() {
            if (!isLoggedIn) {
                const toast = document.getElementById('toast');
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3000);
                setTimeout(() => window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href), 1500);
                return;
            }
            document.getElementById('slotForm').submit();
        }

    </script>
</body>
</html>