<?php
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'customer',
    ];
}

function isAdmin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function requireLogin(): void
{
    if (!currentUser()) {
        header("Location: login.php");
        exit();
    }
}

function jsonResponse(bool $success, string $message, int $statusCode = 200, array $data = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data));
    exit();
}

function generatePublicId(string $prefix = 'silm'): string
{
    return $prefix . '-' . bin2hex(random_bytes(12));
}
?>
