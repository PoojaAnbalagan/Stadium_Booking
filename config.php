<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'stadium_booking');

// Google Console Credentials - User to replace these
define('GOOGLE_CLIENT_ID', '788204422594-8mpip219110vo4q8djqgeag1el2vhkmb.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/Stadium_Booking/social_auth.php');

// OpenAI Configuration - Replace with your API Key
// define('OPENAI_API_KEY', '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');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4
mysqli_set_charset($conn, "utf8mb4");

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Helper function to redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Helper function to sanitize input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
}
?>