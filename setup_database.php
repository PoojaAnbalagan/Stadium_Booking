<?php
require_once 'config.php';

// Enable error reporting
mysqli_report(MYSQLI_REPORT_OFF);

echo "<h1>Database Setup</h1>";

// 1. Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "<p>✅ Database Connected</p>";

// 2. Add google_id column
$sql = "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER password";
if (mysqli_query($conn, $sql)) {
    echo "<p>✅ Successfully added 'google_id' column.</p>";
} else {
    // Check if error is "Duplicate column name"
    if (mysqli_errno($conn) == 1060) {
        echo "<p>ℹ️ Column 'google_id' already exists.</p>";
    } else {
        echo "<p>❌ Error adding column: " . mysqli_error($conn) . "</p>";
    }
}

echo "<br><h3><a href='index.php'>Go Back Home</a></h3>";
?>
