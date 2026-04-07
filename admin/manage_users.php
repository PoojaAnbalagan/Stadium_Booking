<?php
require_once '../config.php';
require_once 'auth_session.php';

if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = intval($_POST['id']);
    // Optional: Delete related bookings first or use cascade
    mysqli_query($conn, "DELETE FROM bookings WHERE user_id = $id");
    mysqli_query($conn, "DELETE FROM users WHERE id = $id");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - InBook</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: var(--black); min-height: 100vh; display: block; }
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .data-table th { color: var(--text-gray); font-weight: 600; padding: 15px; text-align: left; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; }
        .data-table td { background: rgba(255, 255, 255, 0.03); padding: 20px 15px; color: var(--white); vertical-align: middle; }
        .data-table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; border-left: 2px solid transparent; }
        .data-table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        .data-table tr:hover td { background: rgba(255, 255, 255, 0.08); }
        .data-table tr:hover td:first-child { border-left-color: var(--primary-green); }

        .btn-icon { background: none; border: none; cursor: pointer; color: var(--text-gray); font-size: 1.1rem; margin: 0 8px; transition: 0.2s; }
        .btn-icon:hover { color: #ff5555; transform: scale(1.1); }
    </style>
</head>
<body>
    <div class="bg-3d"></div>
    <div class="bg-overlay"></div>

    <nav class="navbar glass-panel">
        <div class="container">
            <a href="dashboard.php" class="logo-text">INBOOK <span style="font-size: 0.5em; vertical-align: middle; opacity: 0.7; margin-left: 5px; color: var(--white);">ADMIN</span></a>
            <div class="nav-links">
                <a href="dashboard.php">DASHBOARD</a>
                <a href="manage_bookings.php">BOOKINGS</a>
                <a href="manage_courts.php">COURTS</a>
                <a href="manage_users.php" class="active">USERS</a>
                <a href="logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="container" style="padding-top: 40px; padding-bottom: 60px;">
        <div class="section-header reveal active" style="margin-bottom: 40px;">
            <div>
                <p class="section-subtitle" style="color: var(--primary-green);">Community</p>
                <h2 class="section-title">Registered Users</h2>
            </div>
        </div>

        <div class="glass-panel reveal active" style="padding: 40px; border-radius: 24px;">
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM users ORDER BY created_at DESC";
                        $result = mysqli_query($conn, $query);
                        
                        if ($result && mysqli_num_rows($result) > 0) {
                            while($row = mysqli_fetch_assoc($result)):
                        ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td style="font-weight: 600; color: var(--white);"><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure? This will delete all user bookings too.');">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="action" value="delete" class="btn-icon delete" title="Delete User">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 40px; color: var(--text-gray);'>No users found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        const bg = document.querySelector('.bg-3d');
        const overlay = document.querySelector('.bg-overlay');
        setTimeout(() => { bg.classList.add('hero-animate'); }, 100);
        document.addEventListener('mousemove', (e) => {
            overlay.style.background = `radial-gradient(circle at ${e.clientX}px ${e.clientY}px, transparent 15%, rgba(17, 17, 17, 0.85) 50%, rgba(17, 17, 17, 1) 100%)`;
        });
        document.body.style.opacity = '1';
    </script>
</body>
</html>
