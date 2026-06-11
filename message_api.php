<?php
define('SILM_JSON_RESPONSE', true);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/db.php';

function cleanText(string $value, int $length): string
{
    return substr(trim($value), 0, $length);
}

function cleanPublicId(string $value): string
{
    return substr(preg_replace('/[^a-zA-Z0-9_-]/', '', trim($value)), 0, 80);
}

function findConversationByPublicId(mysqli $conn, string $publicId): ?array
{
    if ($publicId === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM conversations WHERE public_id = ? LIMIT 1");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $publicId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows === 1 ? $result->fetch_assoc() : null;
}

function canAccessConversation(array $conversation): bool
{
    $user = currentUser();

    if (isAdmin()) {
        return true;
    }

    if ($user && (int) $conversation['user_id'] === (int) $user['id']) {
        return true;
    }

    return !$user;
}

function mediaKindFromMime(string $mime, string $fallback): string
{
    if (strpos($mime, 'video/') === 0) {
        return 'video';
    }

    if ($fallback === 'voice') {
        return 'voice';
    }

    return 'audio';
}

function saveUploadedMedia(string $conversationPublicId, string $requestedType): array
{
    if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return [
            'media_type' => 'text',
            'media_path' => null,
            'media_name' => null,
            'media_size' => null,
        ];
    }

    if ($_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, "We could not receive the media file. Please try again.", 422);
    }

    if ($_FILES['media_file']['size'] > 20 * 1024 * 1024) {
        jsonResponse(false, "Please upload media under 20 MB.", 422);
    }

    $tmpPath = $_FILES['media_file']['tmp_name'];
    $detectedMime = mime_content_type($tmpPath) ?: ($_FILES['media_file']['type'] ?? '');

    if (strpos($detectedMime, 'audio/') !== 0 && strpos($detectedMime, 'video/') !== 0) {
        jsonResponse(false, "Only audio and video files are allowed.", 422);
    }

    $uploadDir = __DIR__ . '/uploads/messages';

    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        jsonResponse(false, "Media uploads are not available right now.", 500);
    }

    $originalName = $_FILES['media_file']['name'] ?? 'media';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === '') {
        $extension = strpos($detectedMime, 'video/') === 0 ? 'mp4' : 'webm';
    }

    $extension = preg_replace('/[^a-z0-9]/', '', $extension);
    $safeName = $conversationPublicId . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        jsonResponse(false, "We could not save the media file. Please try again.", 500);
    }

    return [
        'media_type' => mediaKindFromMime($detectedMime, $requestedType),
        'media_path' => 'uploads/messages/' . $safeName,
        'media_name' => substr($originalName, 0, 180),
        'media_size' => (int) $_FILES['media_file']['size'],
    ];
}

function messageRows(mysqli $conn, int $conversationId): array
{
    $stmt = $conn->prepare(
        "SELECT id, sender_type, body, media_type, media_path, media_name, media_size, created_at
         FROM conversation_messages
         WHERE conversation_id = ?
         ORDER BY created_at ASC, id ASC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => 'server-' . $row['id'],
            'type' => $row['sender_type'] === 'admin' ? 'agent' : 'user',
            'sender_type' => $row['sender_type'],
            'text' => $row['body'],
            'timestamp' => date(DATE_ATOM, strtotime($row['created_at'])),
            'status' => $row['sender_type'] === 'admin' ? 'received' : 'sent',
            'media' => $row['media_path'] ? [
                'kind' => $row['media_type'],
                'url' => $row['media_path'],
                'name' => $row['media_name'] ?: ucfirst($row['media_type']) . ' message',
                'size' => (int) $row['media_size'],
            ] : null,
        ];
    }

    return $messages;
}

function createConversation(mysqli $conn, string $publicId, ?int $userId, string $name, string $email, string $topic): ?array
{
    $stmt = $conn->prepare(
        "INSERT INTO conversations (public_id, user_id, name, email, topic, status, last_message_at)
         VALUES (?, ?, ?, ?, ?, 'open', NOW())"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("sisss", $publicId, $userId, $name, $email, $topic);

    if (!$stmt->execute()) {
        return null;
    }

    return findConversationByPublicId($conn, $publicId);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'send';

if ($action === 'fetch') {
    $publicId = cleanPublicId($_GET['conversation_id'] ?? '');
    $conversation = findConversationByPublicId($conn, $publicId);

    if (!$conversation) {
        jsonResponse(true, "No conversation yet.", 200, [
            'conversation_id' => $publicId,
            'messages' => [],
        ]);
    }

    if (!canAccessConversation($conversation)) {
        jsonResponse(false, "You cannot access this conversation.", 403);
    }

    jsonResponse(true, "Messages loaded.", 200, [
        'conversation_id' => $conversation['public_id'],
        'status' => $conversation['status'],
        'messages' => messageRows($conn, (int) $conversation['id']),
    ]);
}

if ($action === 'reply') {
    if (!isAdmin()) {
        jsonResponse(false, "Only an admin can reply.", 403);
    }

    $publicId = cleanPublicId($_POST['conversation_id'] ?? '');
    $body = cleanText($_POST['message'] ?? '', 3000);
    $conversation = findConversationByPublicId($conn, $publicId);

    if (!$conversation || $body === '') {
        jsonResponse(false, "Conversation and message are required.", 422);
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $mediaType = 'text';
    $stmt = $conn->prepare(
        "INSERT INTO conversation_messages (conversation_id, sender_type, sender_user_id, body, media_type)
         VALUES (?, 'admin', ?, ?, ?)"
    );

    if (!$stmt) {
        jsonResponse(false, "Could not prepare reply.", 500);
    }

    $conversationId = (int) $conversation['id'];
    $stmt->bind_param("iiss", $conversationId, $userId, $body, $mediaType);

    if (!$stmt->execute()) {
        jsonResponse(false, "Could not save reply.", 500);
    }

    $update = $conn->prepare("UPDATE conversations SET status = 'waiting_customer', last_message_at = NOW() WHERE id = ?");
    if ($update) {
        $update->bind_param("i", $conversationId);
        $update->execute();
    }

    jsonResponse(true, "Reply sent.", 200, [
        'conversation_id' => $conversation['public_id'],
        'messages' => messageRows($conn, $conversationId),
    ]);
}

if ($action === 'close') {
    if (!isAdmin()) {
        jsonResponse(false, "Only an admin can close conversations.", 403);
    }

    $publicId = cleanPublicId($_POST['conversation_id'] ?? '');
    $conversation = findConversationByPublicId($conn, $publicId);

    if (!$conversation) {
        jsonResponse(false, "Conversation not found.", 404);
    }

    $conversationId = (int) $conversation['id'];
    $stmt = $conn->prepare("UPDATE conversations SET status = 'closed' WHERE id = ?");

    if ($stmt) {
        $stmt->bind_param("i", $conversationId);
        $stmt->execute();
    }

    jsonResponse(true, "Conversation closed.", 200, [
        'conversation_id' => $conversation['public_id'],
    ]);
}

$honeypot = trim($_POST['company'] ?? '');

if ($honeypot !== '') {
    jsonResponse(true, "Message sent.", 200);
}

$user = currentUser();
$publicId = cleanPublicId($_POST['conversation_id'] ?? '');
$topic = cleanText($_POST['topic'] ?? 'General project', 120);
$body = cleanText($_POST['message'] ?? '', 3000);
$requestedMediaType = cleanText($_POST['media_type'] ?? '', 40);

if ($publicId === '') {
    $publicId = generatePublicId('conv');
}

if ($user) {
    $name = cleanText($user['name'], 120);
    $email = cleanText($user['email'], 180);
    $userId = (int) $user['id'];
} else {
    $name = cleanText($_POST['name'] ?? '', 120);
    $email = cleanText($_POST['email'] ?? '', 180);
    $userId = null;
}

if ($name === '' || $email === '') {
    jsonResponse(false, "Please enter your name and email first.", 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, "Please enter a valid email address.", 422);
}

$conversation = findConversationByPublicId($conn, $publicId);

if (!$conversation) {
    $conversation = createConversation($conn, $publicId, $userId, $name, $email, $topic);
}

if (!$conversation) {
    jsonResponse(false, "Could not create conversation.", 500);
}

if (!canAccessConversation($conversation)) {
    jsonResponse(false, "You cannot send to this conversation.", 403);
}

$media = saveUploadedMedia($conversation['public_id'], $requestedMediaType);

if ($body === '' && $media['media_path']) {
    $body = ucfirst($media['media_type']) . " message";
}

if ($body === '') {
    jsonResponse(false, "Type a message or attach media.", 422);
}

$conversationId = (int) $conversation['id'];
$stmt = $conn->prepare(
    "INSERT INTO conversation_messages
        (conversation_id, sender_type, sender_user_id, body, media_type, media_path, media_name, media_size)
     VALUES (?, 'customer', ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    jsonResponse(false, "Could not prepare message.", 500);
}

$mediaSize = $media['media_size'];
$stmt->bind_param(
    "iissssi",
    $conversationId,
    $userId,
    $body,
    $media['media_type'],
    $media['media_path'],
    $media['media_name'],
    $mediaSize
);

if (!$stmt->execute()) {
    jsonResponse(false, "Could not save message.", 500);
}

if ($userId) {
    $update = $conn->prepare(
        "UPDATE conversations
         SET name = ?, email = ?, topic = ?, user_id = COALESCE(user_id, ?), status = 'open', last_message_at = NOW()
         WHERE id = ?"
    );

    if ($update) {
        $update->bind_param("sssii", $name, $email, $topic, $userId, $conversationId);
        $update->execute();
    }
} else {
    $update = $conn->prepare(
        "UPDATE conversations
         SET name = ?, email = ?, topic = ?, status = 'open', last_message_at = NOW()
         WHERE id = ?"
    );

    if ($update) {
        $update->bind_param("sssi", $name, $email, $topic, $conversationId);
        $update->execute();
    }
}

jsonResponse(true, "Message sent.", 200, [
    'conversation_id' => $conversation['public_id'],
    'messages' => messageRows($conn, $conversationId),
]);
?>
