<?php
require_once 'config.php';

if (!isLoggedIn()) {
    $redirect_url = 'calendar.php?' . ($_SERVER['QUERY_STRING'] ?? '');
    redirect('login.php?redirect=' . urlencode($redirect_url));
}

if (!isset($_GET['sport']) || !isset($_GET['court']) || !isset($_GET['date'])) {
    redirect('index.php');
}

$sport_id = intval($_GET['sport']);
$court_id = intval($_GET['court']);
$booking_date = sanitize($_GET['date']);

// Prevent booking for dates in the past
if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
    redirect("calendar.php?sport=$sport_id&court=$court_id&date=" . date('Y-m-d'));
}

// Fetch court details
$court_query = "SELECT c.*, s.name as sport_name FROM courts c JOIN sports s ON c.sport_id = s.id WHERE c.id = $court_id";
$court_result = mysqli_query($conn, $court_query);
$court = mysqli_fetch_assoc($court_result);

if (!$court) redirect('index.php');

// Fetch all bookings for this court and date to detect booked time ranges
$bookings_query = "SELECT start_time, end_time FROM bookings 
                   WHERE court_id = $court_id 
                   AND booking_date = '$booking_date' 
                   AND status = 'confirmed'";
$bookings_result = mysqli_query($conn, $bookings_query);
$confirmed_bookings = [];
if ($bookings_result) {
    while ($booking = mysqli_fetch_assoc($bookings_result)) {
        $start_t = $booking['start_time'];
        $end_t = (!empty($booking['end_time']) && $booking['end_time'] != '00:00:00') 
                 ? $booking['end_time'] 
                 : date('H:i:s', strtotime($start_t . ' +1 hour'));
        $confirmed_bookings[] = [
            'start' => $start_t,
            'end' => $end_t
        ];
    }
}

// 1. Dynamic Operating Hours logic
$is_weekend = in_array(date('N', strtotime($booking_date)), [6, 7]);
$default_open = $is_weekend ? 6 : 7; // 6 AM on weekends, 7 AM on weekdays
$default_close = 23; // 11 PM

$open_hour = isset($court['open_time']) && !empty($court['open_time']) 
             ? (int)date('G', strtotime($court['open_time'])) 
             : (isset($court['opening_hour']) ? (int)$court['opening_hour'] : $default_open);

$close_hour = isset($court['close_time']) && !empty($court['close_time']) 
              ? (int)date('G', strtotime($court['close_time'])) 
              : (isset($court['closing_hour']) ? (int)$court['closing_hour'] : $default_close);

if ($close_hour <= $open_hour) {
    $close_hour = 23;
}

// Generate time slots dynamically
$time_slots = [];
for ($hour = $open_hour; $hour < $close_hour; $hour++) {
    $time_slots[] = sprintf("%02d:00:00", $hour);
}

// Helper function to check slot booking overlap
function isSlotBooked($slot_start, $slot_end, $bookings) {
    foreach ($bookings as $b) {
        if ($b['start'] < $slot_end && $b['end'] > $slot_start) {
            return true;
        }
    }
    return false;
}

// 2. Current day & time filtering
$is_today = ($booking_date === date('Y-m-d'));
$current_time = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Time - InBook</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Dynamic Calendar Styles */
        .time-box {
            position: relative;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            padding: 16px 10px;
        }

        .time-box.booked {
            opacity: 0.45;
            cursor: not-allowed !important;
            background: rgba(255, 50, 50, 0.08) !important;
            border-color: rgba(255, 85, 85, 0.4) !important;
            color: #ff7777 !important;
        }

        .time-box.past {
            opacity: 0.35;
            cursor: not-allowed !important;
            background: rgba(120, 120, 120, 0.08) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #888888 !important;
        }

        .time-box.duration-blocked {
            opacity: 0.4;
            cursor: not-allowed !important;
            background: rgba(255, 170, 0, 0.06) !important;
            border-color: rgba(255, 170, 0, 0.35) !important;
            color: #ffbb55 !important;
        }

        .time-box.hover-preview {
            background: rgba(0, 255, 136, 0.2) !important;
            border-color: var(--primary-green) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 255, 136, 0.25);
        }

        .time-box.selected {
            background: var(--primary-green) !important;
            color: var(--black) !important;
            border-color: var(--primary-green) !important;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.5) !important;
            transform: translateY(-2px);
        }

        .time-box.selected-range {
            background: rgba(0, 255, 136, 0.82) !important;
            color: var(--black) !important;
            border-color: var(--primary-green) !important;
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.3) !important;
        }

        .time-box .slot-status {
            font-size: 0.75rem;
            margin-top: 6px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .cal-hours-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.25);
            color: var(--primary-green);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 12px;
        }

        .slot-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 25px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .legend-dot.avail { background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(0, 255, 136, 0.5); }
        .legend-dot.sel { background: var(--primary-green); }
        .legend-dot.bkd { background: #ff5555; }
        .legend-dot.pst { background: #777; }
        .legend-dot.blk { background: #ffaa00; }
    </style>
</head>
<body>
    <!-- 3D Animated Background -->
    <div class="bg-3d"></div>
    <div class="bg-overlay"></div>

    <!-- Nav -->
    <nav class="navbar glass-panel">
        <div class="container">
            <a href="index.php" class="logo-text">INBOOK</a>
            <div class="menu-toggle" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </div>
            <div class="nav-links">
                <a href="index.php">HOME</a>
                <a href="profile.php">PROFILE</a>
                <a href="logout.php" class="btn-logout">LOGOUT</a>
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
                    <i class="fas fa-tag"></i> Hourly Rate: <span style="color:var(--white); font-weight:bold;">$<?= number_format($court['price_per_hour'], 2) ?>/hr</span> (Fixed Base Rate)
                </div>
                <div class="cal-hours-badge">
                    <i class="fas fa-clock"></i> Operating Hours: <?= date('g:i A', strtotime(sprintf('%02d:00:00', $open_hour))) ?> - <?= date('g:i A', strtotime(sprintf('%02d:00:00', $close_hour))) ?>
                </div>
            </div>

            <form action="process_booking.php" method="POST" id="slotForm">
                <input type="hidden" name="court_id" value="<?= $court_id ?>">
                <input type="hidden" name="booking_date" value="<?= $booking_date ?>">
                <input type="hidden" name="start_time" id="selectedTimeInput">
                <input type="hidden" name="price" id="basePrice" value="<?= $court['price_per_hour'] ?>">

                <!-- Duration Selector -->
                <div class="duration-selector glass-panel reveal active" style="margin-bottom: 25px; padding: 20px; border-radius: 15px;">
                    <label style="color:var(--text-gray); font-weight:bold; display:block; margin-bottom:10px;">
                        <i class="fas fa-stopwatch"></i> HOW LONG WILL YOU PLAY?
                    </label>
                    <div class="duration-options">
                        <select name="duration" id="bookingDuration" class="booking-input" style="background:rgba(255,255,255,0.05); color:var(--white); border:1px solid rgba(255,255,255,0.1); width:100%; padding:12px; border-radius:10px;" onchange="onDurationChange()">
                            <option value="1">1 Hour (Standard)</option>
                            <option value="2">2 Hours</option>
                            <option value="3">3 Hours</option>
                            <option value="4">4 Hours</option>
                        </select>
                    </div>
                </div>

                <!-- Slot Legend -->
                <div class="slot-legend">
                    <div class="legend-item"><span class="legend-dot avail"></span> Available</div>
                    <div class="legend-item"><span class="legend-dot sel"></span> Selected</div>
                    <div class="legend-item"><span class="legend-dot bkd"></span> Booked</div>
                    <?php if ($is_today): ?>
                        <div class="legend-item"><span class="legend-dot pst"></span> Passed Today</div>
                    <?php endif; ?>
                    <div class="legend-item"><span class="legend-dot blk"></span> Duration Conflict</div>
                </div>

                <div class="slot-grid" id="slotGrid">
                    <?php foreach ($time_slots as $slot): 
                        $slot_hour = (int)substr($slot, 0, 2);
                        $slot_end = sprintf("%02d:00:00", $slot_hour + 1);
                        $display_time = date('g:i A', strtotime($slot));

                        $is_booked = isSlotBooked($slot, $slot_end, $confirmed_bookings);
                        $is_past = ($is_today && $slot <= $current_time);

                        $initial_class = '';
                        $initial_status = 'Available';

                        if ($is_booked) {
                            $initial_class = 'booked';
                            $initial_status = 'Booked';
                        } elseif ($is_past) {
                            $initial_class = 'past';
                            $initial_status = 'Passed';
                        }
                    ?>
                        <div class="time-box <?= $initial_class ?>" 
                             id="slot-<?= $slot_hour ?>"
                             data-slot="<?= $slot ?>"
                             data-hour="<?= $slot_hour ?>"
                             data-booked="<?= $is_booked ? 'true' : 'false' ?>"
                             data-past="<?= $is_past ? 'true' : 'false' ?>"
                             onclick="handleSlotClick(this, '<?= $slot ?>', <?= $slot_hour ?>)"
                             onmouseenter="handleSlotHover(<?= $slot_hour ?>)"
                             onmouseleave="clearHoverPreview()">
                            <div style="font-size:1.1rem; font-weight:bold;"><?= $display_time ?></div>
                            <div class="slot-status"><?= $initial_status ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Live Booking & Pricing Summary -->
                <div class="pricing-summary glass-panel reveal active" style="margin-top: 30px; padding: 20px 25px; border-radius: 15px; border: 1px solid rgba(0,255,136,0.25);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center; text-align: center;">
                        <div>
                            <span style="color:var(--text-gray); font-size:0.85rem; display:block; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Fixed Hourly Rate</span>
                            <strong style="color:var(--white); font-size:1.2rem;">$<?= number_format($court['price_per_hour'], 2) ?>/hr</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-gray); font-size:0.85rem; display:block; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Play Duration</span>
                            <strong id="summaryDurationText" style="color:var(--white); font-size:1.2rem;">1 Hour</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-gray); font-size:0.85rem; display:block; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Time Window</span>
                            <strong id="summaryTimeText" style="color:var(--primary-green); font-size:1.15rem;">None Selected</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-gray); font-size:0.85rem; display:block; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Total Amount</span>
                            <strong id="summaryTotalText" style="color:var(--primary-green); font-size:1.3rem;">$<?= number_format($court['price_per_hour'], 2) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="calendar-actions" style="text-align:center; margin-top:35px;">
                    <a href="index.php" class="btn" style="background:var(--medium-gray); color:var(--white);text-decoration:none;">BACK</a>
                    <button type="button" class="btn btn-primary" id="bookBtn" onclick="submitBooking()" disabled>
                        SELECT A TIME SLOT
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification">
        <i class="fas fa-exclamation-circle"></i> 
        <span>Notice</span>
    </div>

    <script>
        const isLoggedIn = true;
        const openHour = <?= $open_hour ?>;
        const closeHour = <?= $close_hour ?>;
        const basePrice = <?= floatval($court['price_per_hour']) ?>;
        let selectedStartHour = null;
        let selectedStartTime = null;

        // Initialize on DOM load
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
            validateSlotsForDuration();
            updatePricingDisplay();
        });

        function getDuration() {
            return parseInt(document.getElementById('bookingDuration').value) || 1;
        }

        // Called when duration dropdown changes
        function onDurationChange() {
            validateSlotsForDuration();
            updatePricingDisplay();
        }

        // ================= Multi-Hour Duration Slot Validation ================= //
        function validateSlotsForDuration() {
            const duration = getDuration();
            const boxes = document.querySelectorAll('.time-box');

            boxes.forEach(box => {
                const hour = parseInt(box.dataset.hour);
                const isBooked = box.dataset.booked === 'true';
                const isPast = box.dataset.past === 'true';
                const statusEl = box.querySelector('.slot-status');

                // Clear duration-blocked class
                box.classList.remove('duration-blocked');

                if (isBooked) {
                    statusEl.innerText = 'Booked';
                    return;
                }

                if (isPast) {
                    statusEl.innerText = 'Passed';
                    return;
                }

                // Check if starting at this hour can fit `duration` consecutive hours
                let canFit = true;
                let conflictReason = '';

                if (hour + duration > closeHour) {
                    canFit = false;
                    conflictReason = 'Exceeds Close';
                } else {
                    for (let offset = 0; offset < duration; offset++) {
                        const targetHour = hour + offset;
                        const targetBox = document.getElementById(`slot-${targetHour}`);
                        if (!targetBox || targetBox.dataset.booked === 'true' || targetBox.dataset.past === 'true') {
                            canFit = false;
                            conflictReason = 'Conflict';
                            break;
                        }
                    }
                }

                if (!canFit) {
                    box.classList.add('duration-blocked');
                    statusEl.innerText = conflictReason;
                } else {
                    statusEl.innerText = 'Available';
                }
            });

            // Check if current selection is still valid under new duration
            if (selectedStartHour !== null) {
                const selectedBox = document.getElementById(`slot-${selectedStartHour}`);
                if (!selectedBox || selectedBox.classList.contains('duration-blocked') || 
                    selectedBox.dataset.booked === 'true' || selectedBox.dataset.past === 'true') {
                    clearSelection();
                    showNotice(`The selected slot cannot accommodate ${duration} hours. Please select another slot.`);
                } else {
                    highlightSelectedRange(selectedStartHour, duration);
                }
            }
        }

        function handleSlotClick(el, time, hour) {
            if (el.dataset.booked === 'true' || el.dataset.past === 'true' || el.classList.contains('duration-blocked')) {
                return;
            }

            selectedStartHour = hour;
            selectedStartTime = time;
            document.getElementById('selectedTimeInput').value = time;

            highlightSelectedRange(hour, getDuration());
            updatePricingDisplay();
        }

        function highlightSelectedRange(startHour, duration) {
            document.querySelectorAll('.time-box').forEach(box => {
                box.classList.remove('selected', 'selected-range');
            });

            for (let offset = 0; offset < duration; offset++) {
                const h = startHour + offset;
                const box = document.getElementById(`slot-${h}`);
                if (box) {
                    if (offset === 0) {
                        box.classList.add('selected');
                    } else {
                        box.classList.add('selected-range');
                    }
                }
            }
        }

        function clearSelection() {
            selectedStartHour = null;
            selectedStartTime = null;
            document.getElementById('selectedTimeInput').value = '';
            document.querySelectorAll('.time-box').forEach(box => {
                box.classList.remove('selected', 'selected-range');
            });
            updatePricingDisplay();
        }

        function handleSlotHover(hour) {
            clearHoverPreview();
            const box = document.getElementById(`slot-${hour}`);
            if (!box || box.dataset.booked === 'true' || box.dataset.past === 'true' || box.classList.contains('duration-blocked')) {
                return;
            }

            const duration = getDuration();
            for (let offset = 0; offset < duration; offset++) {
                const h = hour + offset;
                const targetBox = document.getElementById(`slot-${h}`);
                if (targetBox && !targetBox.classList.contains('selected') && !targetBox.classList.contains('selected-range')) {
                    targetBox.classList.add('hover-preview');
                }
            }
        }

        function clearHoverPreview() {
            document.querySelectorAll('.time-box.hover-preview').forEach(box => {
                box.classList.remove('hover-preview');
            });
        }

        function formatHour(h) {
            const period = h >= 12 ? 'PM' : 'AM';
            const displayH = h % 12 === 0 ? 12 : h % 12;
            return `${displayH}:00 ${period}`;
        }

        function updatePricingDisplay() {
            const duration = getDuration();
            const total = basePrice * duration;
            const bookBtn = document.getElementById('bookBtn');
            const summaryDurationText = document.getElementById('summaryDurationText');
            const summaryTimeText = document.getElementById('summaryTimeText');
            const summaryTotalText = document.getElementById('summaryTotalText');

            if (summaryDurationText) {
                summaryDurationText.innerText = `${duration} ${duration > 1 ? 'Hours' : 'Hour'}`;
            }

            if (selectedStartHour !== null) {
                const endHour = selectedStartHour + duration;
                const timeRangeStr = `${formatHour(selectedStartHour)} - ${formatHour(endHour)}`;

                if (summaryTimeText) {
                    summaryTimeText.innerText = timeRangeStr;
                }
                if (summaryTotalText) {
                    summaryTotalText.innerText = `$${total.toFixed(2)}`;
                }

                bookBtn.disabled = false;
                bookBtn.innerHTML = `PROCEED TO BOOK (${timeRangeStr} • $${total.toFixed(2)})`;
            } else {
                if (summaryTimeText) {
                    summaryTimeText.innerText = 'Select a slot below';
                }
                if (summaryTotalText) {
                    summaryTotalText.innerText = `$${total.toFixed(2)}`;
                }

                bookBtn.disabled = true;
                bookBtn.innerHTML = 'SELECT A TIME SLOT';
            }
        }

        function showNotice(msg) {
            const toast = document.getElementById('toast');
            toast.querySelector('span').innerText = msg;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3500);
        }

        function submitBooking() {
            if (!selectedStartTime) {
                showNotice('Please select an available time slot');
                return;
            }
            document.getElementById('slotForm').submit();
        }

        function toggleMenu() {
            const nav = document.querySelector('.nav-links');
            if (nav) nav.classList.toggle('active');
        }
    </script>
</body>
</html>
