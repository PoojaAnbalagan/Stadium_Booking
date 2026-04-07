<?php
require_once 'config.php';

function getRecommendations() {
    global $conn;
    $recommendations = [];

    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        
        // 1. Personalized: Find the most booked sport by this user
        $query = "SELECT s.id, s.name as sport_name, COUNT(b.id) as booking_count 
                  FROM bookings b 
                  JOIN courts c ON b.court_id = c.id 
                  JOIN sports s ON c.sport_id = s.id 
                  WHERE b.user_id = ? 
                  GROUP BY s.id 
                  ORDER BY booking_count DESC 
                  LIMIT 1";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $most_booked = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($most_booked) {
            // Suggest another court of the same sport OR a popular choice for them
            $sport_id = $most_booked['id'];
            $recommendations['personalized'] = [
                'type' => 'Based on your activity',
                'title' => "You love " . $most_booked['sport_name'] . "!",
                'subtitle' => "Try booking a session for this weekend.",
                'sport_id' => $sport_id,
                'icon' => getSportIcon($most_booked['sport_name'])
            ];
        }
    }

    // 2. Global: Find the trending court (most booked in last 7 days)
    $trending_query = "SELECT c.id as court_id, c.name as court_name, s.name as sport_name, COUNT(b.id) as volume 
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
            'sport_name' => $trending['sport_name'],
            'icon' => getSportIcon($trending['sport_name'])
        ];
    } else {
        // Fallback popular choice if no recent bookings
        $recommendations['trending'] = [
            'type' => 'Recommended',
            'title' => 'Main Football Turf',
            'subtitle' => 'Our highest rated premium facility.',
            'court_id' => 1,
            'sport_name' => 'Football',
            'icon' => 'fa-futbol'
        ];
    }

    return $recommendations;
}

function getSportIcon($name) {
    $icons = [
        'Football' => 'fa-futbol',
        'Basketball' => 'fa-basketball',
        'Tennis' => 'fa-table-tennis-paddle-ball',
        'Cricket' => 'fa-baseball-bat-ball',
        'Volleyball' => 'fa-volleyball-ball',
        'Badminton' => 'fa-shuttlecock'
    ];
    return $icons[$name] ?? 'fa-star';
}
?>
