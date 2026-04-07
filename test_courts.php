<?php
require_once 'config.php';

echo "<h2>Cricket Courts (sport_id = 4):</h2>";
$result = mysqli_query($conn, "SELECT id, name, sport_id, price_per_hour FROM courts WHERE sport_id = 4");

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "ID: {$row['id']} - Name: {$row['name']} - Sport ID: {$row['sport_id']} - Price: \${$row['price_per_hour']}/hr<br>";
    }
} else {
    echo "No cricket courts found!<br>";
}

echo "<br><h2>All Courts:</h2>";
$all_result = mysqli_query($conn, "SELECT c.id, c.name, s.name as sport_name, c.price_per_hour FROM courts c JOIN sports s ON c.sport_id = s.id ORDER BY s.name, c.name");

while($row = mysqli_fetch_assoc($all_result)) {
    echo "ID: {$row['id']} - {$row['name']} ({$row['sport_name']}) - \${$row['price_per_hour']}/hr<br>";
}
?>
