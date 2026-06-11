<?php
$isJsonRequest = (
    strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
);

define('SILM_JSON_RESPONSE', $isJsonRequest);

require_once __DIR__ . '/db.php';

function respond(bool $success, string $message, int $statusCode = 200, array $data = []): void
{
    global $isJsonRequest;

    http_response_code($statusCode);

    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $data));
        exit();
    }

    if ($success) {
        header("Location: index.php?success=1#contact");
        exit();
    }

    echo htmlspecialchars($message);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request.", 405);
}

$honeypot = trim($_POST['company'] ?? '');

if ($honeypot !== '') {
    respond(true, "Message sent successfully. We will get back to you soon.");
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$topic = trim($_POST['topic'] ?? 'General project');
$source = trim($_POST['source'] ?? 'contact_form');
$conversationId = trim($_POST['conversation_id'] ?? '');
$mediaType = trim($_POST['media_type'] ?? '');
$mediaName = trim($_POST['media_name'] ?? '');
$mediaSize = trim($_POST['media_size'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    respond(false, "Please fill in your name, email, and message.", 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Please enter a valid email address.", 422);
}

$name = substr($name, 0, 120);
$email = substr($email, 0, 180);
$topic = substr($topic, 0, 120);
$source = substr($source, 0, 60);
$conversationId = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $conversationId), 0, 80);
$mediaType = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $mediaType), 0, 40);
$mediaName = substr($mediaName, 0, 180);
$mediaSize = substr(preg_replace('/[^0-9]/', '', $mediaSize), 0, 20);
$message = substr($message, 0, 3000);
$mediaPath = '';

if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        respond(false, "We could not receive the media file. Please try again.", 422);
    }

    if ($_FILES['media_file']['size'] > 20 * 1024 * 1024) {
        respond(false, "Please upload media under 20 MB.", 422);
    }

    $tmpPath = $_FILES['media_file']['tmp_name'];
    $detectedMime = mime_content_type($tmpPath) ?: ($_FILES['media_file']['type'] ?? '');

    if (strpos($detectedMime, 'audio/') !== 0 && strpos($detectedMime, 'video/') !== 0) {
        respond(false, "Only audio and video files are allowed.", 422);
    }

    $uploadDir = __DIR__ . '/uploads/messages';

    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        respond(false, "Media uploads are not available right now.", 500);
    }

    $originalName = $_FILES['media_file']['name'] ?? 'media';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === '') {
        $extension = strpos($detectedMime, 'video/') === 0 ? 'mp4' : 'webm';
    }

    $extension = preg_replace('/[^a-z0-9]/', '', $extension);
    $safeName = ($conversationId !== '' ? $conversationId : 'conversation') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        respond(false, "We could not save the media file. Please try again.", 500);
    }

    $mediaPath = 'uploads/messages/' . $safeName;
    $mediaType = $mediaType !== '' ? $mediaType : (strpos($detectedMime, 'video/') === 0 ? 'video' : 'audio');
    $mediaName = $mediaName !== '' ? $mediaName : substr($originalName, 0, 180);
    $mediaSize = $mediaSize !== '' ? $mediaSize : (string) $_FILES['media_file']['size'];
}

$storedMessage = "Source: " . $source . "\n";

if ($conversationId !== '') {
    $storedMessage .= "Conversation ID: " . $conversationId . "\n";
}

if ($topic !== '') {
    $storedMessage .= "Topic: " . $topic . "\n";
}

if ($mediaType !== '') {
    $storedMessage .= "Media Type: " . $mediaType . "\n";
}

if ($mediaName !== '') {
    $storedMessage .= "Media Name: " . $mediaName . "\n";
}

if ($mediaSize !== '') {
    $storedMessage .= "Media Size: " . $mediaSize . " bytes\n";
}

if ($mediaPath !== '') {
    $storedMessage .= "Media File: " . $mediaPath . "\n";
}

$storedMessage .= "\n" . $message;

$stmt = $conn->prepare(
    "INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)"
);

if (!$stmt) {
    respond(false, "We could not prepare your message. Please try again soon.", 500);
}

$stmt->bind_param("sss", $name, $email, $storedMessage);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    respond(false, "We could not save your message. Please try again soon.", 500);
}

$stmt->close();
$conn->close();

respond(true, "Message sent successfully. We will get back to you soon.", 200, [
    'conversation_id' => $conversationId,
    'media_path' => $mediaPath,
    'received_at' => date(DATE_ATOM)
]);
?>
