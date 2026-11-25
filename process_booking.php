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
    $booking_date = trim($_POST['booking_date']);
    $start_time = trim($_POST['start_time']);
    $duration = max(1, min(4, intval($_POST['duration'] ?? 1)));
    $payment_method = trim($_POST['payment_method'] ?? '');

    // Server-Side Dynamic Price Verification (Anti-Tampering)
    // Query court's hourly rate directly from database, ignoring any client-sent price
    $rate_stmt = mysqli_prepare($conn, "SELECT c.id, c.name, c.name as court_name, c.price_per_hour, s.name as sport_name 
                                         FROM courts c 
                                         JOIN sports s ON c.sport_id = s.id 
                                         WHERE c.id = ?");
    mysqli_stmt_bind_param($rate_stmt, "i", $court_id);
    mysqli_stmt_execute($rate_stmt);
    $rate_res = mysqli_stmt_get_result($rate_stmt);
    $court_data = mysqli_fetch_assoc($rate_res);
    mysqli_stmt_close($rate_stmt);

    if (!$court_data) {
        $error = 'Invalid court selected. Please restart your booking.';
    } else {
        $verified_rate = floatval($court_data['price_per_hour']);
        $total_price = $verified_rate * $duration;

        // Validate Payment Method & Dynamic Processing
        if ($payment_method !== 'debit_card' && $payment_method !== 'cash') {
            $error = 'Please select a valid payment method: Debit Card or Cash (On the Spot).';
        } elseif ($payment_method === 'debit_card') {
            // Debit Card Details Validation
            $card_name = trim($_POST['card_name'] ?? '');
            $card_number = preg_replace('/[\s\-]+/', '', $_POST['card_number'] ?? '');
            $card_expiry = trim($_POST['card_expiry'] ?? '');
            $card_cvv = trim($_POST['card_cvv'] ?? '');

            if (empty($card_name) || strlen($card_name) < 3) {
                $error = 'Please enter a valid Cardholder Name (minimum 3 characters).';
            } elseif (!preg_match('/^\d{15,19}$/', $card_number)) {
                $error = 'Please enter a valid 16-digit Debit Card number.';
            } elseif (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $card_expiry, $exp_matches)) {
                $error = 'Please enter a valid expiry date in MM/YY format.';
            } else {
                $exp_month = intval($exp_matches[1]);
                $exp_year = intval('20' . $exp_matches[2]);
                $cur_year = intval(date('Y'));
                $cur_month = intval(date('m'));

                if ($exp_year < $cur_year || ($exp_year === $cur_year && $exp_month < $cur_month)) {
                    $error = 'Debit card has expired. Please use a valid card.';
                } elseif (!preg_match('/^\d{3,4}$/', $card_cvv)) {
                    $error = 'Please enter a valid 3 or 4 digit CVV code.';
                }
            }

            if (empty($error)) {
                // Online Debit Card Gateway Simulation
                $transaction_id = 'TXN-DC-' . date('YmdHis') . '-' . rand(1000, 9999);
                $status = 'confirmed';
                $payment_status = 'completed';
            }
        } elseif ($payment_method === 'cash') {
            // Cash on the Spot / Physical Booking Flow
            $transaction_id = 'CASH-' . date('YmdHis') . '-' . rand(1000, 9999);
            $status = 'confirmed'; // Court slot is confirmed and reserved
            $payment_status = 'pending'; // To be paid physically at arena (fits MySQL ENUM/VARCHAR)
        }

        // Overlap verification if no error so far
        if (empty($error)) {
            $end_time = date('H:i:s', strtotime($start_time . " +$duration hour"));

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
                $error = 'One or more hours in this selection are already booked! Please choose another slot.';
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                $user_id = $_SESSION['user_id'];

                // Insert booking into DB
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

                    // Fetch user info for notification
                    $user_email_query = "SELECT email, full_name, phone FROM users WHERE id = ?";
                    $user_stmt = mysqli_prepare($conn, $user_email_query);
                    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
                    mysqli_stmt_execute($user_stmt);
                    $user_result = mysqli_stmt_get_result($user_stmt);
                    $user_data = mysqli_fetch_assoc($user_result);
                    mysqli_stmt_close($user_stmt);

                    $to = $user_data['email'];
                    $subject = ($payment_method === 'cash') 
                               ? "Court Reservation Reserved (Pay at Venue) - InBook Sports" 
                               : "Booking & Payment Confirmed - InBook Sports";

                    $payment_label = ($payment_method === 'cash') 
                                     ? "Cash (Pay at Venue Counter upon arrival)" 
                                     : "Debit Card (Paid Online)";

                    $payment_instruction = ($payment_method === 'cash')
                                           ? "<div style='background-color:#fff8e6; padding:12px; border-radius:6px; border-left:4px solid #f39c12; margin-top:15px; color:#b7791f;'><strong>Note:</strong> Please arrive 15 minutes before your slot and pay <strong>Rs. " . number_format($total_price, 2) . "</strong> in cash at the stadium front desk.</div>"
                                           : "<div style='background-color:#e8f8f0; padding:12px; border-radius:6px; border-left:4px solid #00cc6a; margin-top:15px; color:#1e7e34;'><strong>Payment Complete:</strong> Transaction ID {$transaction_id} verified.</div>";

                    // Email HTML Template in Sri Lankan Rupees
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
                                <p><strong>Time:</strong> " . date('g:i A', strtotime($start_time)) . " - " . date('g:i A', strtotime($end_time)) . "</p>
                                <p><strong>Duration:</strong> {$duration} " . ($duration > 1 ? 'Hours' : 'Hour') . "</p>
                                <p><strong>Total Amount:</strong> Rs. " . number_format($total_price, 2) . "</p>
                                <p><strong>Payment Method:</strong> {$payment_label}</p>
                                <p><strong>Reference ID:</strong> {$transaction_id}</p>
                            </div>

                            {$payment_instruction}

                            <p style='margin-top:20px;'>We look forward to seeing you at the arena! 🏟️</p>
                        </div>
                    </body>
                    </html>";

                    // 1. Send Email Notification
                    if (defined('SMTP_USER') && !empty(SMTP_USER) && SMTP_USER !== 'your-email@gmail.com') {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = SMTP_HOST;
                            $mail->SMTPAuth   = true;
                            $mail->Username   = SMTP_USER;
                            $mail->Password   = SMTP_PASS;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = SMTP_PORT;
                            $mail->CharSet    = 'UTF-8';
                            $mail->Timeout    = 15;

                            // Windows WAMP SSL bypass
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                ]
                            ];

                            // Gmail requires From address to match authenticated account
                            $mail->setFrom(SMTP_USER, SMTP_NAME);
                            $mail->addReplyTo(SMTP_USER, SMTP_NAME);
                            $mail->addAddress($to, $user_data['full_name']);
                            $mail->isHTML(true);
                            $mail->Subject = $subject;
                            $mail->Body    = $email_message;
                            $mail->send();
                            
                            $_SESSION['mail_sent'] = true;
                            file_put_contents('debug_log.txt', "[" . date('Y-m-d H:i:s') . "] EMAIL SUCCESS to: $to | Subject: $subject\n", FILE_APPEND);
                        } catch (Exception $e) {
                            $_SESSION['mail_sent'] = false;
                            $_SESSION['mail_error'] = $mail->ErrorInfo;
                            $err_msg = "PHPMailer Error: " . $mail->ErrorInfo;
                            error_log($err_msg);
                            file_put_contents('debug_log.txt', "[" . date('Y-m-d H:i:s') . "] EMAIL FAILED to: $to | Error: " . $mail->ErrorInfo . "\n", FILE_APPEND);
                        }
                    }

                    // 2. Send SMS Notification via Twilio
                    if (defined('SMS_ENABLED') && SMS_ENABLED) {
                        $customer_phone = $user_data['phone'];
                        $sms_action = ($payment_method === 'cash')
                                      ? "reserved! Pay Rs. " . number_format($total_price, 2) . " cash at venue counter"
                                      : "confirmed! Paid Rs. " . number_format($total_price, 2) . " via Debit Card";
                        $sms_message = "Hi {$user_data['full_name']}, your booking for {$court_data['court_name']} on " . date('M j', strtotime($booking_date)) . " at " . date('g:i A', strtotime($start_time)) . " is {$sms_action}. Ref: {$transaction_id} - InBook Sports";

                        $id = TWILIO_SID;
                        $token = TWILIO_TOKEN;
                        $from = TWILIO_FROM;
                        $url = "https://api.twilio.com/2010-04-01/Accounts/$id/Messages.json";
                        $data = [
                            'From' => $from,
                            'To' => $customer_phone,
                            'Body' => $sms_message,
                        ];

                        $x = curl_init($url);
                        curl_setopt($x, CURLOPT_POST, true);
                        curl_setopt($x, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($x, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($x, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                        curl_setopt($x, CURLOPT_USERPWD, "$id:$token");
                        curl_setopt($x, CURLOPT_POSTFIELDS, http_build_query($data));

                        $response = curl_exec($x);
                        $err = curl_error($x);
                        curl_close($x);

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
    }
}

// Get and verify booking details for view
if (!isset($_POST['court_id']) || !isset($_POST['booking_date']) || !isset($_POST['start_time'])) {
    redirect('index.php');
}

$court_id = intval($_POST['court_id']);
$booking_date = sanitize($_POST['booking_date']);
$start_time = sanitize($_POST['start_time']);
$duration = max(1, min(4, intval($_POST['duration'] ?? 1)));

// Server-side direct DB price lookup (ignoring client price)
$court_query = "SELECT c.*, s.name as sport_name 
                FROM courts c 
                JOIN sports s ON c.sport_id = s.id 
                WHERE c.id = ?";
$court_stmt = mysqli_prepare($conn, $court_query);
mysqli_stmt_bind_param($court_stmt, "i", $court_id);
mysqli_stmt_execute($court_stmt);
$court_res = mysqli_stmt_get_result($court_stmt);
$court = mysqli_fetch_assoc($court_res);
mysqli_stmt_close($court_stmt);

if (!$court) {
    redirect('index.php');
}

$base_price = floatval($court['price_per_hour']);
$price = $base_price * $duration;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout & Payment - InBook Sports</title>
    <link rel="stylesheet" href="style.css?v=1.3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .payment-options-dual {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin: 20px 0 25px;
        }

        .payment-choice-card {
            background: rgba(30, 30, 30, 0.7);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px 18px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .payment-choice-card:hover {
            border-color: var(--primary-green);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 255, 136, 0.15);
        }

        .payment-choice-card.selected {
            border-color: var(--primary-green);
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.15) 0%, rgba(0, 204, 106, 0.08) 100%);
            box-shadow: 0 0 25px rgba(0, 255, 136, 0.25);
        }

        .payment-choice-card .choice-icon {
            font-size: 38px;
            color: var(--primary-green);
            margin-bottom: 12px;
            transition: transform 0.3s ease;
        }

        .payment-choice-card.selected .choice-icon {
            transform: scale(1.15);
        }

        .payment-choice-card .choice-title {
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .payment-choice-card .choice-desc {
            color: var(--text-gray);
            font-size: 0.82rem;
            line-height: 1.35;
        }

        .payment-choice-card .badge-selected {
            display: none;
            position: absolute;
            top: 10px;
            right: 12px;
            background: var(--primary-green);
            color: var(--black);
            font-size: 0.7rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 20px;
        }

        .payment-choice-card.selected .badge-selected {
            display: inline-block;
        }

        /* Dynamic Details Containers */
        .details-panel {
            display: none;
            background: rgba(20, 20, 20, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
            animation: fadeIn 0.4s ease;
        }

        .details-panel.active {
            display: block;
        }

        .card-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .card-input-group {
            margin-bottom: 15px;
        }

        .card-input-group.full-width {
            grid-column: 1 / -1;
        }

        .card-input-group label {
            display: block;
            color: var(--text-gray);
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .card-input-box {
            position: relative;
        }

        .card-input-box i.input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            font-size: 16px;
        }

        .card-input-box input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 12px 14px 12px 42px;
            color: var(--white);
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .card-input-box input:focus {
            outline: none;
            border-color: var(--primary-green);
            background: rgba(0, 0, 0, 0.4);
            box-shadow: 0 0 12px rgba(0, 255, 136, 0.2);
        }

        .cash-banner {
            border-left: 4px solid #ffaa00;
            background: rgba(255, 170, 0, 0.08);
            border-radius: 12px;
            padding: 18px;
        }

        .cash-banner h4 {
            color: #ffaa00;
            margin: 0 0 8px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cash-banner ul {
            margin: 0;
            padding-left: 20px;
            color: #ddd;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .cash-banner ul li strong {
            color: var(--white);
        }

        .price-badge-lkr {
            color: var(--primary-green);
            font-weight: 800;
            font-size: 1.4rem;
        }

        @media (max-width: 600px) {
            .payment-options-dual {
                grid-template-columns: 1fr;
            }
            .card-fields-grid {
                grid-template-columns: 1fr;
            }
            .details-panel {
                padding: 18px 14px;
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

    <nav class="navbar">
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

    <div class="payment-container">
        <div class="container">
            <div class="payment-card">
                <div class="payment-header">
                    <h2>Complete Your Booking</h2>
                    <p>Review details, choose payment method, and confirm your court</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
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
                        <span class="summary-value"><?= date('l, F j, Y', strtotime($booking_date)) ?></span>
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
                        <span class="summary-label">Hourly Rate:</span>
                        <span class="summary-value">Rs. <?= number_format($base_price, 2) ?>/hr</span>
                    </div>
                    <div class="summary-row" style="border-top:1px solid rgba(0,255,136,0.3); padding-top:14px; margin-top:10px;">
                        <span class="summary-label" style="font-size:1.1rem; color:var(--white);">Total Amount:</span>
                        <span class="summary-value price-badge-lkr">Rs. <?= number_format($price, 2) ?></span>
                    </div>
                </div>

                <!-- Payment Form -->
                <form method="POST" id="checkoutForm" onsubmit="return validateCheckout(event)">
                    <input type="hidden" name="court_id" value="<?= $court_id ?>">
                    <input type="hidden" name="booking_date" value="<?= $booking_date ?>">
                    <input type="hidden" name="start_time" value="<?= $start_time ?>">
                    <input type="hidden" name="duration" value="<?= $duration ?>">
                    <input type="hidden" name="payment_method" id="selected_payment" value="<?= htmlspecialchars($_POST['payment_method'] ?? '') ?>">

                    <div class="payment-methods">
                        <h3><i class="fas fa-wallet"></i> Choose Payment Method</h3>
                        
                        <div class="payment-options-dual">
                            <!-- Debit Card Option -->
                            <div class="payment-choice-card <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'debit_card') ? 'selected' : '' ?>" 
                                 id="optDebitCard" 
                                 onclick="chooseMethod('debit_card')">
                                <span class="badge-selected">SELECTED</span>
                                <div class="choice-icon"><i class="fas fa-credit-card"></i></div>
                                <div class="choice-title">Debit Card</div>
                                <div class="choice-desc">Instant online payment via Visa, Mastercard, or LankaPay Debit Cards.</div>
                            </div>

                            <!-- Cash on Spot Option -->
                            <div class="payment-choice-card <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash') ? 'selected' : '' ?>" 
                                 id="optCash" 
                                 onclick="chooseMethod('cash')">
                                <span class="badge-selected">SELECTED</span>
                                <div class="choice-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="choice-title">Cash (On the Spot)</div>
                                <div class="choice-desc">Physical booking. Reserve slot now and pay cash at the stadium counter.</div>
                            </div>
                        </div>

                        <!-- Dynamic Debit Card Details Panel -->
                        <div class="details-panel <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'debit_card') ? 'active' : '' ?>" id="panelDebitCard">
                            <h4 style="color:var(--white); margin:0 0 16px; font-size:1.05rem; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-shield-alt" style="color:var(--primary-green);"></i> Debit Card Details
                            </h4>
                            <div class="card-fields-grid">
                                <div class="card-input-group full-width">
                                    <label>Cardholder Name</label>
                                    <div class="card-input-box">
                                        <i class="fas fa-user input-icon"></i>
                                        <input type="text" name="card_name" id="cardName" placeholder="e.g. Kasun Perera" value="<?= htmlspecialchars($_POST['card_name'] ?? '') ?>" autocomplete="cc-name">
                                    </div>
                                </div>
                                <div class="card-input-group full-width">
                                    <label>Debit Card Number</label>
                                    <div class="card-input-box">
                                        <i class="fas fa-credit-card input-icon"></i>
                                        <input type="text" name="card_number" id="cardNumber" placeholder="4111 2222 3333 4444" maxlength="19" value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>" autocomplete="cc-number" oninput="formatCardNumber(this)">
                                    </div>
                                </div>
                                <div class="card-input-group">
                                    <label>Expiry Date</label>
                                    <div class="card-input-box">
                                        <i class="fas fa-calendar-alt input-icon"></i>
                                        <input type="text" name="card_expiry" id="cardExpiry" placeholder="MM/YY" maxlength="5" value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>" autocomplete="cc-exp" oninput="formatExpiry(this)">
                                    </div>
                                </div>
                                <div class="card-input-group">
                                    <label>CVV / CVC</label>
                                    <div class="card-input-box">
                                        <i class="fas fa-lock input-icon"></i>
                                        <input type="password" name="card_cvv" id="cardCvv" placeholder="123" maxlength="4" value="<?= htmlspecialchars($_POST['card_cvv'] ?? '') ?>" autocomplete="cc-csc">
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top:10px; color:var(--text-gray); font-size:0.78rem; display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-check-circle" style="color:var(--primary-green);"></i> 256-bit SSL encrypted dynamic transaction gateway simulation.
                            </div>
                        </div>

                        <!-- Dynamic Cash Info Panel -->
                        <div class="details-panel <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash') ? 'active' : '' ?>" id="panelCash">
                            <div class="cash-banner">
                                <h4><i class="fas fa-store"></i> Physical Cash on the Spot Booking</h4>
                                <ul>
                                    <li>Your court slot is <strong>immediately reserved</strong> upon submission.</li>
                                    <li>Please bring <strong>Rs. <?= number_format($price, 2) ?></strong> in cash to the stadium reception counter upon arrival.</li>
                                    <li>Arrive at least <strong>15 minutes prior</strong> to your start time to settle payment and collect your court pass.</li>
                                    <li>A reservation reference code will be generated and dispatched to your email and phone.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="payment-actions">
                        <button type="button" class="btn-action btn-cancel" onclick="window.history.back()">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back</span>
                        </button>
                        <button type="submit" name="process_payment" class="btn-action btn-pay" id="payBtn" disabled>
                            <span id="payBtnText">Select a Payment Method</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>

                    <div class="secure-badge">
                        <i class="fas fa-lock"></i>
                        <span>Guaranteed Safe & Verified Reservation</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const totalAmountStr = 'Rs. <?= number_format($price, 2) ?>';

        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
            // Check if there was a pre-selected method on validation error
            const initialMethod = document.getElementById('selected_payment').value;
            if (initialMethod) {
                chooseMethod(initialMethod);
            }
        });

        function chooseMethod(method) {
            document.getElementById('selected_payment').value = method;

            const optDebit = document.getElementById('optDebitCard');
            const optCash = document.getElementById('optCash');
            const panelDebit = document.getElementById('panelDebitCard');
            const panelCash = document.getElementById('panelCash');
            const payBtn = document.getElementById('payBtn');
            const payBtnText = document.getElementById('payBtnText');

            // Reset selection classes
            optDebit.classList.remove('selected');
            optCash.classList.remove('selected');
            panelDebit.classList.remove('active');
            panelCash.classList.remove('active');

            if (method === 'debit_card') {
                optDebit.classList.add('selected');
                panelDebit.classList.add('active');
                payBtnText.innerText = `Pay ${totalAmountStr} with Debit Card`;
                payBtn.disabled = false;
            } else if (method === 'cash') {
                optCash.classList.add('selected');
                panelCash.classList.add('active');
                payBtnText.innerText = `Confirm Booking (Pay ${totalAmountStr} at Venue)`;
                payBtn.disabled = false;
            }
        }

        // Format Debit Card Number with 4-digit spacing (e.g. 1234 5678 9012 3456)
        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '');
            let formatted = '';
            for (let i = 0; i < value.length && i < 16; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            input.value = formatted;
        }

        // Format Card Expiry MM/YY
        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 2) {
                input.value = value.substring(0, 2) + '/' + value.substring(2, 4);
            } else {
                input.value = value;
            }
        }

        // Client-side Validation Before Submit
        function validateCheckout(event) {
            const method = document.getElementById('selected_payment').value;
            if (!method) {
                alert('Please select a payment method (Debit Card or Cash).');
                event.preventDefault();
                return false;
            }

            if (method === 'debit_card') {
                const name = document.getElementById('cardName').value.trim();
                const num = document.getElementById('cardNumber').value.replace(/\s+/g, '');
                const exp = document.getElementById('cardExpiry').value.trim();
                const cvv = document.getElementById('cardCvv').value.trim();

                if (!name || name.length < 3) {
                    alert('Please enter a valid Cardholder Name.');
                    document.getElementById('cardName').focus();
                    event.preventDefault();
                    return false;
                }
                if (!/^\d{15,19}$/.test(num)) {
                    alert('Please enter a valid 16-digit Debit Card number.');
                    document.getElementById('cardNumber').focus();
                    event.preventDefault();
                    return false;
                }
                if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(exp)) {
                    alert('Please enter a valid expiry date (MM/YY).');
                    document.getElementById('cardExpiry').focus();
                    event.preventDefault();
                    return false;
                }
                if (!/^\d{3,4}$/.test(cvv)) {
                    alert('Please enter a valid 3 or 4-digit CVV.');
                    document.getElementById('cardCvv').focus();
                    event.preventDefault();
                    return false;
                }
            }
            return true;
        }

        function toggleMenu() {
            const nav = document.querySelector('.nav-links');
            if (nav) nav.classList.toggle('active');
        }
    </script>
</body>
</html>
