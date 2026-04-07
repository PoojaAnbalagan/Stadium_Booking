<?php
require_once '../config.php';
require_once 'auth_session.php';

// Handle Add/Delete Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);
            mysqli_query($conn, "DELETE FROM courts WHERE id = $id");
        } elseif ($_POST['action'] == 'add') {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $sport_id = intval($_POST['sport_id']);
            $price = floatval($_POST['price']);
            
            mysqli_query($conn, "INSERT INTO courts (name, sport_id, price_per_hour) VALUES ('$name', $sport_id, $price)");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courts - InBook</title>
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
        
        /* Add Form Styles */
        .add-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: center;
            background: rgba(0, 255, 136, 0.05);
            padding: 20px;
            border-radius: 15px;
            border: 1px solid rgba(0, 255, 136, 0.2);
            margin-bottom: 30px;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--white);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.1);
        }

        @media (max-width: 768px) {
            .add-form { grid-template-columns: 1fr; }
        }
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
                <a href="manage_courts.php" class="active">COURTS</a>
                <a href="manage_users.php">USERS</a>
                <a href="logout.php" class="btn-logout">LOGOUT</a>
            </div>
        </div>
    </nav>

    <div class="container" style="padding-top: 40px; padding-bottom: 60px;">
        <div class="section-header reveal active" style="margin-bottom: 40px;">
            <div>
                <p class="section-subtitle" style="color: var(--primary-green);">Facilities</p>
                <h2 class="section-title">Manage Courts</h2>
            </div>
        </div>

        <div class="glass-panel reveal active" style="padding: 40px; border-radius: 24px;">
            
            <!-- Add Court Form -->
            <form method="POST" class="add-form">
                <input type="hidden" name="action" value="add">
                
                <input type="text" name="name" placeholder="New Court Name (e.g. Football Turf B)" class="form-input" required>
                
                <select name="sport_id" class="form-input" required>
                    <?php
                    $sports = mysqli_query($conn, "SELECT * FROM sports");
                    while($s = mysqli_fetch_assoc($sports)) {
                        echo "<option value='{$s['id']}'>{$s['name']}</option>";
                    }
                    ?>
                </select>
                
                <div style="position: relative;">
                    <span style="position: absolute; left: 10px; top: 12px; color: var(--text-gray);">$</span>
                    <input type="number" step="0.01" name="price" placeholder="Price/Hr" class="form-input" style="padding-left: 25px;" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 12px 20px; white-space: nowrap;">
                    <i class="fas fa-plus"></i> Add Court
                </button>
            </form>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Court Name</th>
                            <th>Sport</th>
                            <th>Price / Hour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT c.*, s.name as sport_name FROM courts c JOIN sports s ON c.sport_id = s.id";
                        $result = mysqli_query($conn, $query);
                        
                        if ($result && mysqli_num_rows($result) > 0) {
                            while($row = mysqli_fetch_assoc($result)):
                        ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td style="font-weight: 600; color: var(--white);"><?= htmlspecialchars($row['name']) ?></td>
                            <td style="color: var(--primary-green);"><?= htmlspecialchars($row['sport_name']) ?></td>
                            <td>$<?= number_format($row['price_per_hour'], 2) ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this court?');">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="action" value="delete" class="btn-icon delete" title="Delete Court">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding: 40px; color: var(--text-gray);'>No courts found.</td></tr>";
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
