<?php
require_once '../config.php';
require_once 'auth_session.php';

// Fetch Statistics
$today = date('Y-m-d');

// 1. Total Bookings
$bookings_query = "SELECT COUNT(*) as total FROM bookings";
$bookings_result = mysqli_query($conn, $bookings_query);
$total_bookings = mysqli_fetch_assoc($bookings_result)['total'];

// 2. Total Users
$users_query = "SELECT COUNT(*) as total FROM users";
$users_result = mysqli_query($conn, $users_query);
$total_users = mysqli_fetch_assoc($users_result)['total'];

// 3. Total Revenue
$revenue_query = "SELECT SUM(total_price) as total FROM bookings WHERE payment_status = 'completed'";
$revenue_result = mysqli_query($conn, $revenue_query);
$total_revenue = mysqli_fetch_assoc($revenue_result)['total'] ?? 0;

// 4. Today's Bookings
$today_bookings_query = "SELECT COUNT(*) as total FROM bookings WHERE booking_date = '$today'";
$today_bookings_result = mysqli_query($conn, $today_bookings_query);
$today_bookings = mysqli_fetch_assoc($today_bookings_result)['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - InBook</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Specific Overrides */
        body {
            display: block; /* Override flex from dashboard.php original */
            background-color: var(--black);
            min-height: 100vh;
        }

        .admin-stat-card {
            background: rgba(34, 34, 34, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            transition: all 0.3s ease;
            position: relative; 
            overflow: hidden;
        }
        
        .admin-stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-green);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.1);
        }

        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--white);
            line-height: 1.2;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            display: block;
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--primary-green);
            position: absolute;
            top: 30px;
            right: 30px;
            opacity: 0.8;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .data-table th {
            color: var(--text-gray);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }

        .data-table td {
            background: rgba(255, 255, 255, 0.03);
            padding: 20px 15px;
            color: var(--white);
            vertical-align: middle;
        }

        .data-table tr td:first-child {
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            border-left: 2px solid transparent;
        }

        .data-table tr td:last-child {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .data-table tr:hover td {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .data-table tr:hover td:first-child {
            border-left-color: var(--primary-green);
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-confirmed {
            background: rgba(0, 255, 136, 0.15);
            color: var(--primary-green);
            border: 1px solid rgba(0, 255, 136, 0.2);
        }

        .status-cancelled {
            background: rgba(255, 85, 85, 0.15);
            color: #ff5555;
            border: 1px solid rgba(255, 85, 85, 0.2);
        }
    </style>
</head>
<body>
    <!-- 3D Animated Background -->
    <div class="bg-3d"></div>
    <div class="bg-overlay"></div>

    <!-- Navigation -->
    <nav class="navbar glass-panel">
        <div class="container">
            <a href="dashboard.php" class="logo-text">INBOOK <span style="font-size: 0.5em; vertical-align: middle; opacity: 0.7; margin-left: 5px; color: var(--white);">ADMIN</span></a>
            
            <div class="nav-links">
                <a href="dashboard.php" class="active">DASHBOARD</a>
                <a href="manage_bookings.php">BOOKINGS</a>
                <a href="manage_courts.php">COURTS</a>
                <a href="manage_users.php">USERS</a>
                <a href="logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="padding-top: 40px; padding-bottom: 60px;">
        
        <!-- Welcome Section -->
        <div class="section-header reveal active" style="margin-bottom: 40px;">
            <div>
                <p class="section-subtitle" style="color: var(--primary-green);">Overview</p>
                <h2 class="section-title">Welcome back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></h2>
            </div>
        </div>

        <!-- Stats Grid (Using grid layout similar to bento but equal sized) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 50px;">
            
            <!-- Revenue -->
            <div class="admin-stat-card reveal active">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-value">$<?= number_format($total_revenue, 0) ?></div>
                <span class="stat-label">Total Revenue</span>
            </div>

            <!-- Total Bookings -->
            <div class="admin-stat-card reveal active">
                <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="stat-value"><?= $total_bookings ?></div>
                <span class="stat-label">Total Bookings</span>
            </div>

            <!-- Today's Bookings -->
            <div class="admin-stat-card reveal active">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?= $today_bookings ?></div>
                <span class="stat-label">Bookings Today</span>
            </div>

            <!-- Users -->
            <div class="admin-stat-card reveal active">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?= $total_users ?></div>
                <span class="stat-label">Registered Users</span>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="glass-panel reveal active" style="padding: 40px; border-radius: 24px; border: 1px solid rgba(255,255,255,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h3 style="font-size: 1.5rem; color: var(--white); font-weight: 700;">Recent Bookings</h3>
                <a href="manage_bookings.php" class="btn btn-primary" style="padding: 10px 25px; font-size: 0.9rem; width: auto;">View All</a>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Book ID</th>
                            <th>User</th>
                            <th>Court</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Joining all necessary tables
                        $recent_query = "SELECT b.*, u.full_name, c.name as court_name 
                                       FROM bookings b 
                                       JOIN users u ON b.user_id = u.id 
                                       JOIN courts c ON b.court_id = c.id 
                                       ORDER BY b.created_at DESC LIMIT 5";
                        $recent_result = mysqli_query($conn, $recent_query);
                        
                        if ($recent_result && mysqli_num_rows($recent_result) > 0) {
                            while($row = mysqli_fetch_assoc($recent_result)): 
                        ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td style="font-weight: 600; color: var(--white);"><?= htmlspecialchars($row['full_name']) ?></td>
                            <td style="color: var(--primary-green);"><?= htmlspecialchars($row['court_name']) ?></td>
                            <td><?= date('M j, Y', strtotime($row['booking_date'])) ?></td>
                            <td>$<?= number_format($row['total_price'], 2) ?></td>
                            <td>
                                <span class="status-badge status-<?= $row['status'] == 'confirmed' ? 'confirmed' : 'cancelled' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 40px; color: var(--text-gray);'>No bookings found yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        // Copy Index Spotlight Logic
        const bg = document.querySelector('.bg-3d');
        const overlay = document.querySelector('.bg-overlay');

        setTimeout(() => {
            bg.classList.add('hero-animate');
        }, 100);

        document.addEventListener('mousemove', (e) => {
            const xVal = e.clientX;
            const yVal = e.clientY;
            overlay.style.background = `radial-gradient(circle at ${xVal}px ${yVal}px, transparent 15%, rgba(17, 17, 17, 0.85) 50%, rgba(17, 17, 17, 1) 100%)`;
        });
        
        // Simple Reveal
        document.body.style.opacity = '1';
    </script>
</body>
</html>