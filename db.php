<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "sil";

$conn = new mysqli($host, $user, $pass, $db, 3307);

if ($conn->connect_error) {
    if (defined('SILM_JSON_RESPONSE') && SILM_JSON_RESPONSE) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'We could not connect to the message service. Please try again soon.'
        ]);
        exit();
    }

    die("Connection failed: " . $conn->connect_error);
}
?>
