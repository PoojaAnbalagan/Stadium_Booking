<?php
require_once 'config.php';

// PHPMailer Classes
require 'lib/PHPMailer/Exception.php';
require 'lib/PHPMailer/PHPMailer.php';
require 'lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if (!isLoggedIn()) {
    redirect('login.php');
}

$error = '';
$success = '';

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $court_id = intval($_POST['court_id']);
    $booking_date = trim($_POST['booking_date']); // Basic trim, handled by prepared stmt
    $start_time = trim($_POST['start_time']);
    $base_price = floatval($_POST['price']);
    $payment_method = trim($_POST['payment_method']);
    
    $duration = intval($_POST['duration'] ?? 1);
    $total_price = $base_price * $duration;
    
    // Calculate end time based on duration
    $end_time = date('H:i:s', strtotime($start_time . " +$duration hour"));
    
    // Check if ANY part of this slot is booked (Proper Overlap Check)
    // Overlap exists if: (Existing_Start < New_End) AND (New_Start < Existing_End)
    $check_query = "SELECT id FROM bookings 
                    WHERE court_id = ? 
                    AND booking_date = ? 
                    AND start_time < ? 
                    AND end_time > ? 
                    AND status = 'confirmed'";
    
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "isss", $court_id, $booking_date, $end_time, $start_time);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $error = 'One or more hours in this selection are already booked!';
        mysqli_stmt_close($check_stmt);
    } else {
        mysqli_stmt_close($check_stmt);

        // Generate transaction ID
        $transaction_id = 'TXN' . time() . rand(1000, 9999);
        $user_id = $_SESSION['user_id'];
        $status = 'confirmed';
        $payment_status = 'completed';
        
        // Insert booking (Using Prepared Statement)
        $insert_query = "INSERT INTO bookings (user_id, court_id, booking_date, start_time, end_time, 
                        total_price, payment_status, payment_method, transaction_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "iisssdssss", 
            $user_id, $court_id, $booking_date, $start_time, $end_time, 
            $total_price, $payment_status, $payment_method, $transaction_id, $status
        );
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $booking_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insert_stmt);
            
            // Send Email Notification
            // Fetch user info
            $user_email_query = "SELECT email, full_name, phone FROM users WHERE id = ?";
            $user_stmt = mysqli_prepare($conn, $user_email_query);
            mysqli_stmt_bind_param($user_stmt, "i", $user_id);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user_data = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);
            
            // Fetch court details
            $court_email_query = "SELECT c.name as court_name, s.name as sport_name 
                                  FROM courts c 
                                  JOIN sports s ON c.sport_id = s.id 
                                  WHERE c.id = ?";
            $court_stmt = mysqli_prepare($conn, $court_email_query);
            mysqli_stmt_bind_param($court_stmt, "i", $court_id);
            mysqli_stmt_execute($court_stmt);
            $court_result = mysqli_stmt_get_result($court_stmt);
            $court_data = mysqli_fetch_assoc($court_result);
            mysqli_stmt_close($court_stmt);

            $to = $user_data['email'];
            $subject = "Booking Confirmation - InBook Sports";
            
            // Email HTML Template
            $email_message = "
            <html>
            <head>
                <title>Booking Confirmation</title>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                    .container { background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; border-top: 5px solid #00ff88; }
                    h1 { color: #00cc6a; margin-top: 0; }
                    p { font-size: 16px; color: #333333; line-height: 1.5; }
                    .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 4px solid #00ff88; }
                    .details p { margin: 8px 0; }
                    .footer { margin-top: 30px; font-size: 12px; color: #888888; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h1>Booking Confirmed! ✅</h1>
                    <p>Dear {$user_data['full_name']},</p>
                    <p>Your booking at <strong>InBook Sports</strong> has been successfully confirmed.</p>
                    
                    <div class='details'>
                        <p><strong>Sport:</strong> {$court_data['sport_name']}</p>
                        <p><strong>Court:</strong> {$court_data['court_name']}</p>
                        <p><strong>Date:</strong> " . date('F j, Y', strtotime($booking_date)) . "</p>
                        <p><strong>Time:</strong> " . date('g:i A', strtotime($start_time)) . "</p>
                        <p><strong>Total Price:</strong> $" . number_format($total_price, 2) . "</p>
                    </div>

                    <p>We look forward to seeing you at the arena! 🏟️</p>
                </div>
            </body>
            </html>";

            // 1. Send Email Notification
            if (defined('SMTP_USER') && SMTP_USER !== 'your-email@gmail.com') {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USER;
                    $mail->Password   = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = SMTP_PORT;

                    $mail->setFrom(SMTP_FROM, SMTP_NAME);
                    $mail->addAddress($to, $user_data['full_name']);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $email_message;
                    $mail->send();
                } catch (Exception $e) {
                    error_log("PHPMailer Error: " . $mail->ErrorInfo);
                }
            }

            // 2. Send SMS Notification via Twilio REST API
            if (defined('SMS_ENABLED') && SMS_ENABLED) {
                $customer_phone = $user_data['phone'];
                $sms_message = "Hi {$user_data['full_name']}, your booking for {$court_data['court_name']} on " . date('M j', strtotime($booking_date)) . " at " . date('g:i A', strtotime($start_time)) . " is confirmed! - InBook Sports";
                
                $id = TWILIO_SID;
                $token = TWILIO_TOKEN;
                $from = TWILIO_FROM;
                
                $url = "https://api.twilio.com/2010-04-01/Accounts/$id/Messages.json";
                
                $data = [
                    'From' => $from,
                    'To' => $customer_phone,
                    'Body' => $sms_message,
                ];
                
                $post = http_build_query($data);
                $x = curl_init($url);
                curl_setopt($x, CURLOPT_POST, true);
                curl_setopt($x, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($x, CURLOPT_SSL_VERIFYPEER, false); // For local testing
                curl_setopt($x, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($x, CURLOPT_USERPWD, "$id:$token");
                curl_setopt($x, CURLOPT_POSTFIELDS, $post);
                
                $response = curl_exec($x);
                $err = curl_error($x);
                curl_close($x);
                
                // Detailed debug logging to help identify the problem
                $log_msg = "[" . date('Y-m-d H:i:s') . "] TO: $customer_phone | Response: " . $response . ($err ? " | cURL Error: $err" : "") . "\n";
                file_put_contents('debug_log.txt', $log_msg, FILE_APPEND);
            }

            $_SESSION['booking_success'] = $booking_id;
            redirect('booking_confirmation.php?id=' . $booking_id);
        } else {
            $error = 'Booking failed. Please try again.';
        }
    }
}

// Get booking details
if (!isset($_POST['court_id']) || !isset($_POST['booking_date']) || !isset($_POST['start_time'])) {
    redirect('index.php');
}

$court_id = intval($_POST['court_id']);
$booking_date = sanitize($_POST['booking_date']);
$start_time = sanitize($_POST['start_time']);
$duration = intval($_POST['duration'] ?? 1);
$base_price = floatval($_POST['price']);
$price = $base_price * $duration;

// Fetch court details
$court_query = "SELECT c.*, s.name as sport_name 
                FROM courts c 
                JOIN sports s ON c.sport_id = s.id 
                WHERE c.id = $court_id";
$court_result = mysqli_query($conn, $court_query);
$court = mysqli_fetch_assoc($court_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Stadium Booking</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>
<body>
    <!-- Floating Background Shapes -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo-text">STADIUM</a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="payment-container">
        <div class="container">
            <div class="payment-card">
                <div class="payment-header">
                    <h2>Complete Your Booking</h2>
                    <p>Review details and proceed with payment</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Booking Summary -->
                <div class="booking-summary">
                    <div class="summary-row">
                        <span class="summary-label">Court:</span>
                        <span class="summary-value"><?= htmlspecialchars($court['name']) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Sport:</span>
                        <span class="summary-value"><?= htmlspecialchars($court['sport_name']) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Date:</span>
                        <span class="summary-value"><?= date('F j, Y', strtotime($booking_date)) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Time:</span>
                        <span class="summary-value"><?= date('g:i A', strtotime($start_time)) ?> - <?= date('g:i A', strtotime($start_time . " +$duration hour")) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Duration:</span>
                        <span class="summary-value"><?= $duration ?> <?= $duration > 1 ? 'Hours' : 'Hour' ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Total Amount:</span>
                        <span class="summary-value total-price">$<?= number_format($price, 2) ?></span>
                    </div>
                </div>

                <!-- Payment Methods -->
                <form method="POST">
                    <input type="hidden" name="court_id" value="<?= $court_id ?>">
                    <input type="hidden" name="booking_date" value="<?= $booking_date ?>">
                    <input type="hidden" name="start_time" value="<?= $start_time ?>">
                    <input type="hidden" name="duration" value="<?= $duration ?>">
                    <input type="hidden" name="price" value="<?= $base_price ?>">
                    <input type="hidden" name="payment_method" id="selected_payment" required>

                    <div class="payment-methods">
                        <h3><i class="fas fa-wallet"></i> Select Payment Method</h3>
                        <div class="payment-options">
                            <div class="payment-option" onclick="selectPayment(this, 'credit_card')">
                                <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                                <div class="payment-name">Credit Card</div>
                            </div>
                            <div class="payment-option" onclick="selectPayment(this, 'debit_card')">
                                <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                                <div class="payment-name">Debit Card</div>
                            </div>
                            <div class="payment-option" onclick="selectPayment(this, 'paypal')">
                                <div class="payment-icon"><i class="fab fa-paypal"></i></div>
                                <div class="payment-name">PayPal</div>
                            </div>
                            <div class="payment-option" onclick="selectPayment(this, 'wallet')">
                                <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                                <div class="payment-name">Digital Wallet</div>
                            </div>
                        </div>
                    </div>

                    <div class="payment-actions">
                        <button type="button" class="btn-action btn-cancel" onclick="window.history.back()">
                            <i class="fas fa-arrow-left"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" name="process_payment" class="btn-action btn-pay" id="payBtn" disabled>
                            <span>Confirm & Pay $<?= number_format($price, 2) ?></span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>

                    <div class="secure-badge">
                        <i class="fas fa-lock"></i>
                        <span>Secure Payment Processing</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => document.body.classList.add('loaded'));

        let selectedMethod = null;

        function selectPayment(element, method) {
            // Remove previous selection
            if (selectedMethod) {
                selectedMethod.classList.remove('selected');
            }

            // Add selection
            element.classList.add('selected');
            selectedMethod = element;

            // Update hidden input
            document.getElementById('selected_payment').value = method;
            
            // Enable pay button
            document.getElementById('payBtn').disabled = false;
        }
    </script>
</body>
</html>