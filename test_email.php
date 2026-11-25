<?php
require_once 'config.php';
require 'lib/PHPMailer/Exception.php';
require 'lib/PHPMailer/PHPMailer.php';
require 'lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Testing PHPMailer SMTP Connection...</h2>";
echo "<p>Host: " . SMTP_HOST . " | Port: " . SMTP_PORT . " | User: " . SMTP_USER . "</p>";

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
    $mail->Debugoutput = function($str, $level) {
        echo "<pre style='margin:2px 0; color:#333; background:#f4f4f4; padding:2px;'>[$level] " . htmlspecialchars($str) . "</pre>";
        file_put_contents('debug_log.txt', "[SMTP $level] $str\n", FILE_APPEND);
    };

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->Timeout    = 15;

    // SSL options for WAMP on Windows
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->setFrom(SMTP_USER, SMTP_NAME);
    $target_email = isset($_GET['to']) ? $_GET['to'] : SMTP_USER;
    $mail->addAddress($target_email, 'Test Recipient');

    $mail->isHTML(true);
    $mail->Subject = 'Test Email from InBook Sports';
    $mail->Body    = '<h3>SMTP Test Successful!</h3><p>Your InBook Sports PHPMailer setup is working properly.</p>';

    $mail->send();
    echo "<h3 style='color:green;'>SUCCESS: Test email sent successfully to $target_email!</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red;'>FAILED: " . htmlspecialchars($mail->ErrorInfo) . "</h3>";
    echo "<p>Exception message: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
