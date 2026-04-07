<?php
// Suppress warnings/notices to ensure clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once 'config.php';

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = $data['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

// Check if API key is set
if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== 'sk-your-api-key-here') {
    $apiKey = OPENAI_API_KEY;
    $url = 'https://api.openai.com/v1/chat/completions';

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];

    $body = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant for a sports stadium booking website called InBook. help users with booking courts, checking prices, and finding information about sports. Be concise and friendly.'],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'max_tokens' => 150
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // FIX FOR WAMP: Disable SSL verification locally
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        error_log("OpenAI cURL Error: " . $error_msg);
        echo json_encode(['reply' => 'Connection Error: ' . $error_msg]);
    } else {
        $decoded = json_decode($response, true);
        
        // Check for OpenAI API specific errors
        if (isset($decoded['error'])) {
            $apiError = $decoded['error']['message'] ?? 'Unknown API Error';
            error_log("OpenAI API Error: " . $apiError);
            echo json_encode(['reply' => 'API Error: ' . $apiError]);
        } elseif (isset($decoded['choices'][0]['message']['content'])) {
            echo json_encode(['reply' => $decoded['choices'][0]['message']['content']]);
        } else {
            error_log("OpenAI Unexpected Response: " . $response);
            echo json_encode(['reply' => 'I received an unexpected response from the brain.']);
        }
    }
    curl_close($ch);

} else {
    // Fallback to local rule-based system if no API key
    $lowerInput = strtolower($userMessage);
    $reply = "I'm not sure about that. Try asking about booking, prices, or sports!";

    if (strpos($lowerInput, 'hello') !== false || strpos($lowerInput, 'hi') !== false) {
        $reply = "Hi there! Ready to play?";
    } elseif (strpos($lowerInput, 'book') !== false || strpos($lowerInput, 'reservation') !== false) {
        $reply = "To book a court, simply log in and select your preferred sport and time on the homepage! You can login at: login.php";
    } elseif (strpos($lowerInput, 'price') !== false || strpos($lowerInput, 'cost') !== false) {
        $reply = "Pricing varies by sport and time.\nFootball: ~$50/hr\nCricket: ~$60/hr\nTennis: ~$40/hr\nYou can see exact prices when booking.";
    } elseif (strpos($lowerInput, 'sport') !== false || strpos($lowerInput, 'game') !== false) {
        $reply = "We have Football, Cricket, Basketball, Tennis, and Volleyball courts available!";
    } elseif (strpos($lowerInput, 'location') !== false || strpos($lowerInput, 'where') !== false) {
        $reply = "We are located at 123 Sports Avenue, close to the City Center.";
    } elseif (strpos($lowerInput, 'time') !== false || strpos($lowerInput, 'open') !== false) {
        $reply = "We are open 24/7! Night floodlights are available for all outdoor courts.";
    } elseif (strpos($lowerInput, 'contact') !== false || strpos($lowerInput, 'support') !== false) {
        $reply = "You can reach us at support@inbook.com or call +1 234 567 890.";
    }

    echo json_encode(['reply' => $reply]);
}
?>
