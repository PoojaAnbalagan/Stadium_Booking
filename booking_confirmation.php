
<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch booking details
$query = "SELECT b.*, c.name as court_name, c.type as court_type, 
          s.name as sport_name, u.full_name, u.email
          FROM bookings b
          JOIN courts c ON b.court_id = c.id
          JOIN sports s ON c.sport_id = s.id
          JOIN users u ON b.user_id = u.id
          WHERE b.id = $booking_id AND b.user_id = " . $_SESSION['user_id'];

$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    redirect('profile.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - Stadium Booking</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: var(--black);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Floating Background Shapes */
        .bg-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.4;
            animation: float 25s ease-in-out infinite;
        }

        .shape-1 {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(0, 255, 136, 0.4) 0%, transparent 70%);
            top: -200px;
            left: -200px;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(0, 204, 106, 0.3) 0%, transparent 70%);
            top: 40%;
            right: -150px;
            animation-delay: 8s;
        }

        .shape-3 {
            width: 550px;
            height: 550px;
            background: radial-gradient(circle, rgba(0, 255, 136, 0.35) 0%, transparent 70%);
            bottom: -200px;
            left: 25%;
            animation-delay: 16s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            33% {
                transform: translate(60px, -60px) scale(1.15);
            }
            66% {
                transform: translate(-40px, 40px) scale(0.85);
            }
        }

        .confirmation-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 24px;
            position: relative;
            z-index: 1;
        }

        .confirmation-card {
            background: rgba(34, 34, 34, 0.15);
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border: 1px solid rgba(0, 255, 136, 0.3);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.6),
                0 0 40px rgba(0, 255, 136, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            max-width: 750px;
            width: 100%;
            overflow: hidden;
            position: relative;
            animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        @keyframes slideUpFade {
            from {
                opacity: 0;
                transform: translateY(60px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .success-header {
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.2) 0%, rgba(0, 204, 106, 0.1) 100%);
            padding: 60px 48px;
            text-align: center;
            color: var(--white);
            position: relative;
            border-bottom: 1px solid rgba(0, 255, 136, 0.2);
        }

        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 70px;
            color: var(--black);
            animation: checkmarkPop 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) 0.3s both;
            box-shadow: 
                0 10px 40px rgba(0, 255, 136, 0.4),
                0 0 60px rgba(0, 255, 136, 0.3);
            position: relative;
        }

        .success-icon::before {
            content: '';
            position: absolute;
            width: 140px;
            height: 140px;
            border: 3px solid rgba(0, 255, 136, 0.3);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes checkmarkPop {
            0% {
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.3) rotate(10deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.5;
            }
            50% {
                transform: scale(1.15);
                opacity: 0.8;
            }
        }

        .success-header h2 {
            font-size: 42px;
            margin-bottom: 12px;
            font-weight: 800;
            color: var(--white);
            animation: fadeInUp 0.6s ease 0.5s both;
        }

        .success-header p {
            font-size: 18px;
            opacity: 0.9;
            color: var(--text-gray);
            animation: fadeInUp 0.6s ease 0.6s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .booking-details {
            padding: 48px;
        }

        .detail-section {
            margin-bottom: 36px;
            animation: fadeInUp 0.6s ease both;
        }

        .detail-section:nth-child(1) { animation-delay: 0.7s; }
        .detail-section:nth-child(2) { animation-delay: 0.8s; }
        .detail-section:nth-child(3) { animation-delay: 0.9s; }

        .detail-section h3 {
            font-size: 20px;
            color: var(--primary-green);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .detail-section h3 i {
            font-size: 24px;
            transition: transform 0.3s ease;
        }

        .detail-section:hover h3 i {
            transform: scale(1.15) rotate(5deg);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .detail-row:hover {
            padding-left: 10px;
            border-bottom-color: rgba(0, 255, 136, 0.3);
            background: rgba(0, 255, 136, 0.03);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-gray);
            font-weight: 500;
            font-size: 15px;
        }

        .detail-value {
            color: var(--white);
            font-weight: 700;
            text-align: right;
            font-size: 15px;
        }

        .transaction-badge {
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.15) 0%, rgba(0, 204, 106, 0.1) 100%);
            border: 1px solid rgba(0, 255, 136, 0.3);
            padding: 24px;
            border-radius: 16px;
            text-align: center;
            margin: 36px 0;
            animation: fadeInUp 0.6s ease 1s both;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .transaction-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.2);
            border-color: var(--primary-green);
        }

        .transaction-label {
            margin: 0 0 8px;
            color: var(--text-gray);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .transaction-id {
            font-size: 22px;
            color: var(--primary-green);
            font-weight: 800;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            animation: fadeInUp 0.6s ease 1.1s both;
        }

        .btn-action {
             padding: 18px 24px;
             border: none;
             border-radius: 14px;
             font-size: 16px;
             font-weight: 700;
             cursor: pointer;
             transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
             text-align: center;
             text-decoration: none;
             display: flex;
             align-items: center;
             justify-content: center;
             gap: 10px;
             position: relative;
             overflow: hidden;
         }

        .btn-action::before {
             content: '';
             position: absolute;
             top: 50%;
             left: 50%;
             width: 0;
             height: 0;
             border-radius: 50%;
             background: rgba(255, 255, 255, 0.15);
             transform: translate(-50%, -50%);
             transition: width 0.6s, height 0.6s;
         }

        .btn-action:hover::before {
             width: 350px;
             height: 350px;
         }

        .btn-action i {
             transition: transform 0.3s ease;
             position: relative;
             z-index: 1;
         }

        .btn-action:hover i {
             transform: translateX(3px);
         }

        .btn-action span {
             position: relative;
             z-index: 1;
         }

        .btn-sms {
            background-color: #3B82F6;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }

        .btn-sms:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.6);
            background-color: #2563EB;
        }

        .btn-profile {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: var(--black);
            box-shadow: 0 5px 20px rgba(0, 255, 136, 0.3);
        }

        .btn-profile:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.5);
        }

        .btn-home {
            background: rgba(34, 34, 34, 0.5);
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
        }

        .btn-home:hover {
            transform: translateY(-3px) scale(1.02);
            background: rgba(0, 255, 136, 0.1);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.2);
        }

        .btn-action:active {
            transform: translateY(0) scale(0.98);
        }

        .info-box {
            background: rgba(0, 255, 136, 0.08);
            border-left: 4px solid var(--primary-green);
            padding: 20px;
            border-radius: 12px;
            margin-top: 28px;
            animation: fadeInUp 0.6s ease 1.2s both;
            transition: all 0.3s ease;
        }

        .info-box:hover {
            background: rgba(0, 255, 136, 0.12);
            transform: translateX(5px);
        }

        .info-box p {
            color: var(--white);
            margin: 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box i {
            color: var(--primary-green);
            font-size: 18px;
        }

        .status-completed {
            color: var(--primary-green);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-completed i {
            animation: checkSpin 0.6s ease;
        }

        @keyframes checkSpin {
            from {
                transform: rotate(-180deg) scale(0);
            }
            to {
                transform: rotate(0deg) scale(1);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .confirmation-container {
                padding: 30px 16px;
            }

            .confirmation-card {
                border-radius: 20px;
            }

            .success-header {
                padding: 40px 24px;
            }

            .success-icon {
                width: 100px;
                height: 100px;
                font-size: 60px;
            }

            .success-header h2 {
                font-size: 32px;
            }

            .booking-details {
                padding: 32px 24px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .detail-row {
                flex-direction: column;
                gap: 8px;
            }

            .detail-value {
                text-align: left;
            }
        }

        /* Page load animation */
        body {
            opacity: 0;
            animation: pageLoad 0.5s ease forwards;
        }

        @keyframes pageLoad {
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Background Shapes -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <div class="confirmation-container">
        <div class="confirmation-card">
            <!-- Success Header -->
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Booking Confirmed!</h2>
                <p>Your court has been successfully reserved</p>
            </div>

            <!-- Booking Details -->
            <div class="booking-details">
                <!-- Booking Information -->
                <div class="detail-section">
                    <h3><i class="fas fa-clipboard-list"></i> Booking Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Booking ID:</span>
                        <span class="detail-value">#<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Court:</span>
                        <span class="detail-value"><?= htmlspecialchars($booking['court_name'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Sport:</span>
                        <span class="detail-value"><?= htmlspecialchars($booking['sport_name'] ?? '') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Type:</span>
                        <span class="detail-value"><?= htmlspecialchars($booking['court_type'] ?? 'Standard') ?></span>
                    </div>
                </div>

                <!-- Date & Time -->
                <div class="detail-section">
                    <h3><i class="fas fa-calendar-check"></i> Schedule</h3>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?= date('l, F j, Y', strtotime($booking['booking_date'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Time:</span>
                        <span class="detail-value"><?= date('g:i A', strtotime($booking['start_time'])) ?> - <?= date('g:i A', strtotime($booking['end_time'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Duration:</span>
                        <span class="detail-value">1 Hour</span>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="detail-section">
                    <h3><i class="fas fa-credit-card"></i> Payment</h3>
                    <div class="detail-row">
                        <span class="detail-label">Amount Paid:</span>
                        <span class="detail-value">$<?= number_format($booking['total_price'], 2) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value"><?= ucwords(str_replace('_', ' ', $booking['payment_method'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value status-completed">
                            <i class="fas fa-check-circle"></i> Completed
                        </span>
                    </div>
                </div>

                <!-- Transaction ID -->
                <div class="transaction-badge">
                    <p class="transaction-label">Transaction ID</p>
                    <div class="transaction-id"><?= $booking['transaction_id'] ?></div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php
                        // Encode for SMS
                        $sms_body = "Booking Confirmed! Court: " . $booking['court_name'] . 
                                    ", Date: " . date('d M Y', strtotime($booking['booking_date'])) . 
                                    ", Time: " . date('g:i A', strtotime($booking['start_time'])) . 
                                    ", Ref: " . $booking['transaction_id'];
                        // Different separators for iOS (&) vs Android (?) - usually ?body works for both modern ones or just body
                        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
                        $sep = (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) ? '&' : '?';
                    ?>
                    <a href="sms:<?= $sep ?>body=<?= rawurlencode($sms_body) ?>" class="btn-action btn-sms">
                        <i class="fas fa-comment-alt"></i>
                        <span>Send via SMS</span>
                    </a>
                    
                    <a href="profile.php" class="btn-action btn-profile">
                        <i class="fas fa-user"></i>
                        <span>View My Bookings</span>
                    </a>
                    
                    <a href="index.php" class="btn-action btn-home">
                        <i class="fas fa-home"></i>
                        <span>Book Another Court</span>
                    </a>
                </div>

                <!-- Info Box -->
                <div class="info-box">
                    <p>
                        <i class="fas fa-envelope"></i>
                        A confirmation email has been sent to <strong><?= htmlspecialchars($booking['email'] ?? '') ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>