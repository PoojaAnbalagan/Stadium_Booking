<?php
require_once '../config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Admin Database Setup</h1>";

// 1. Create admins table
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "<p>✅ Table 'admins' checked/created.</p>";
} else {
    echo "<p>❌ Error creating table: " . mysqli_error($conn) . "</p>";
}

// 2. Create or Update default admin
$password = password_hash('admin123', PASSWORD_DEFAULT);
$email = 'admin@stadium.com';
$full_name = 'Super Admin';

$check_sql = "SELECT id FROM admins WHERE email = '$email'";
$result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($result) == 0) {
    $insert_sql = "INSERT INTO admins (full_name, email, password) VALUES ('$full_name', '$email', '$password')";
    if (mysqli_query($conn, $insert_sql)) {
        echo "<p>✅ Default admin created (Email: <strong>$email</strong>, Pass: <strong>admin123</strong>)</p>";
    } else {
        echo "<p>❌ Error creating admin: " . mysqli_error($conn) . "</p>";
    }
} else {
    // Force update password
    $update_sql = "UPDATE admins SET password = '$password' WHERE email = '$email'";
    if (mysqli_query($conn, $update_sql)) {
        echo "<p>✅ Admin already exists. Password reset to: <strong>admin123</strong></p>";
    } else {
        echo "<p>❌ Error updating password: " . mysqli_error($conn) . "</p>";
    }
}

echo "<br><h3><a href='login.php'>Go to Admin Login</a></h3>";
?>
