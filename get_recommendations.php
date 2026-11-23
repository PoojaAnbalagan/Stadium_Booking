<?php
require_once 'config.php';

function getRecommendations() {
    global $conn;
    $recommendations = [];

    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        
        // 1. Personalized: Find the most booked court/sport by this user
        $query = "SELECT s.id as sport_id, s.name as sport_name, c.id as court_id, COUNT(b.id) as booking_count 
                  FROM bookings b 
                  JOIN courts c ON b.court_id = c.id 
                  JOIN sports s ON c.sport_id = s.id 
                  WHERE b.user_id = ? 
                  GROUP BY c.id 
                  ORDER BY booking_count DESC 
                  LIMIT 1";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $most_booked = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($most_booked) {
            // Suggest the same court they used before
            $sport_id = $most_booked['sport_id'];
            $court_id = $most_booked['court_id'];
            $recommendations['personalized'] = [
                'type' => 'Based on your activity',
                'title' => "You love " . $most_booked['sport_name'] . "!",
                'subtitle' => "Book your favorite court again.",
                'sport_id' => $sport_id,
                'court_id' => $court_id,
                'icon' => getSportIcon($most_booked['sport_name'])
            ];
        }
    }

    // 2. Global: Find the trending court (most booked in last 7 days)
    $trending_query = "SELECT c.id as court_id, c.name as court_name, s.name as sport_name, s.id as sport_id, COUNT(b.id) as volume 
                       FROM bookings b 
                       JOIN courts c ON b.court_id = c.id 
                       JOIN sports s ON c.sport_id = s.id 
                       WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                       GROUP BY c.id 
                       ORDER BY volume DESC 
                       LIMIT 1";
    
    $trending_result = mysqli_query($conn, $trending_query);
    $trending = mysqli_fetch_assoc($trending_result);

    if ($trending) {
        $recommendations['trending'] = [
            'type' => 'Trending Now',
            'title' => $trending['court_name'],
            'subtitle' => "Currently the most popular " . $trending['sport_name'] . " court.",
            'court_id' => $trending['court_id'],
            'sport_id' => $trending['sport_id'],
            'sport_name' => $trending['sport_name'],
            'icon' => getSportIcon($trending['sport_name'])
        ];
    } else {
        // Fallback dynamic choice: fetch the premier court from database
        $fallback_query = "SELECT c.id as court_id, c.name as court_name, s.name as sport_name, s.id as sport_id 
                           FROM courts c 
                           JOIN sports s ON c.sport_id = s.id 
                           ORDER BY c.price_per_hour DESC 
                           LIMIT 1";
        $fallback_res = mysqli_query($conn, $fallback_query);
        if ($fallback_res && ($fallback_court = mysqli_fetch_assoc($fallback_res))) {
            $recommendations['trending'] = [
                'type' => 'Recommended',
                'title' => $fallback_court['court_name'],
                'subtitle' => 'Our premier facility for ' . $fallback_court['sport_name'] . '.',
                'court_id' => $fallback_court['court_id'],
                'sport_id' => $fallback_court['sport_id'],
                'sport_name' => $fallback_court['sport_name'],
                'icon' => getSportIcon($fallback_court['sport_name'])
            ];
        }
    }

    return $recommendations;
}

function getSportIcon($name) {
    $normalized = strtolower(trim($name));
    $icons = [
        'football' => 'fa-futbol',
        'soccer' => 'fa-futbol',
        'basketball' => 'fa-basketball',
        'tennis' => 'fa-table-tennis-paddle-ball',
        'cricket' => 'fa-baseball-bat-ball',
        'volleyball' => 'fa-volleyball',
        'badminton' => 'fa-feather-pointed',
        'table tennis' => 'fa-table-tennis-paddle-ball',
        'squash' => 'fa-table-tennis-paddle-ball'
    ];
    return $icons[$normalized] ?? 'fa-trophy';
}
?>
