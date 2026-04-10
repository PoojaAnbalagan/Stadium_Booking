<?php
require_once 'config.php';

$date = '2026-04-23';
$start_time = '08:00:00';
$end_time = '10:00:00';

// Find the booking
$query = "SELECT b.id, b.total_price, c.price_per_hour 
          FROM bookings b 
          JOIN courts c ON b.court_id = c.id 
          WHERE b.booking_date = '$date' 
          AND b.start_time = '$start_time' 
          AND b.end_time = '$end_time'";

$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $correct_price = $row['price_per_hour'] * 2;
    $booking_id = $row['id'];
    echo "Updating booking ID $booking_id from {$row['total_price']} to $correct_price\n";
    $update_query = "UPDATE bookings SET total_price = $correct_price WHERE id = $booking_id";
    mysqli_query($conn, $update_query);
}
?>
