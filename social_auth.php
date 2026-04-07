<?php
require_once 'config.php';

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';

// 1. Redirect to Google Consent Screen
if (!isset($_GET['code'])) {
    if ($provider === 'google') {
        $params = [
            'response_type' => 'code',
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'scope'         => 'email profile',
            'access_type'   => 'online',
            'prompt'        => 'consent'
        ];
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
        exit;
    } elseif ($provider === 'apple') {
        echo "Apple Login is not configured yet.";
        exit;
    } else {
        // Unknown or missing provider
        header("Location: login.php");
        exit;
    }
}

// 2. Handle Callback from Google
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // 3. Exchange Code for Access Token
    $token_url = 'https://oauth2.googleapis.com/token';
    $params = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Fix for local SSL issues (WAMP/XAMPP often lack proper CA bundles)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    
    // Capture cURL errors
    if ($response === false) {
        die("cURL Error: " . curl_error($ch));
    }
    
    curl_close($ch);

    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        $access_token = $token_data['access_token'];

        // 4. Get User Profile Information
        $user_info_url = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $access_token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Fix for local SSL issues here as well
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $user_response = curl_exec($ch);
        
        if ($user_response === false) {
            die("cURL Error (User Info): " . curl_error($ch));
        }
        
        curl_close($ch);

        $google_user = json_decode($user_response, true);

        if (isset($google_user['email'])) {
            $email = $google_user['email'];
            $name = $google_user['name'];
            $google_id = $google_user['id'];
            // $picture = $google_user['picture']; // Optional

            // 5. Check if user exists
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $existing_user = mysqli_fetch_assoc($result);

            if ($existing_user) {
                // User exists -> Log them in & Update Google ID if missing
                $_SESSION['user_id'] = $existing_user['id'];
                $_SESSION['user_name'] = $name;

                // Update google_id linkage
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET google_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "si", $google_id, $existing_user['id']);
                mysqli_stmt_execute($update_stmt);
                
                redirect('index.php');
            } else {
                // New User -> Register them automatically
                $phone = ''; // Phone not provided by Google
                $password_hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT); // Random password

                $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, phone, password, google_id) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insert_stmt, "sssss", $name, $email, $phone, $password_hash, $google_id);
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    $_SESSION['user_id'] = mysqli_insert_id($conn);
                    $_SESSION['user_name'] = $name;
                    // Debug: confirm session set
                    // var_dump($_SESSION); exit; 
                    redirect('index.php');
                } else {
                    die("Registration failed: " . mysqli_error($conn));
                }
            }
        } else {
            die("Could not retrieve user profile. Response: " . htmlspecialchars($user_response));
        }
    } else {
        die("Token exchange failed. Google Response: " . htmlspecialchars($response));
    }
}
?>
