<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        $sql = "INSERT INTO reviews (user_id, rating, comment) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $user_id, $rating, $comment);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['review_thanks'] = "Thank you for your feedback!";
        }
        mysqli_stmt_close($stmt);
    }
}

redirect('index.php');
?>
