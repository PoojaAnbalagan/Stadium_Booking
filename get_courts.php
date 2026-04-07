<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_GET['sport_id'])) {
    $sport_id = intval($_GET['sport_id']);
    
    $query = "SELECT id, name, type, price_per_hour 
              FROM courts 
              WHERE sport_id = $sport_id 
              ORDER BY name";
    
    $result = mysqli_query($conn, $query);
    
    $courts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $courts[] = $row;
    }
    
    echo json_encode($courts);
} else {
    echo json_encode([]);
}
?>