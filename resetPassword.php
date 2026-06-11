<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/passwordResetHelpers.php';

$token = trim($_POST['token'] ?? $_GET['token'] ?? '');
$message = '';
$messageClass = '';
$resetData = null;
$passwordUpdated = false;

if ($token === '') {
    $message = "Invalid password reset link.";
    $messageClass = "error";
} elseif (!ensurePasswordResetTable($conn)) {
    $message = "Password reset is not available right now. Please try again later.";
    $messageClass = "error";
} else {
    $resetData = getPasswordResetByToken($conn, $token);

    if (!$resetData) {
        $message = "Invalid or expired password reset link.";
        $messageClass = "error";
    } elseif (strtotime($resetData['expires_at']) < time()) {
        $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        if ($deleteStmt) {
            $deleteStmt->bind_param("s", $token);
            $deleteStmt->execute();
        }

        $resetData = null;
        $message = "This password reset link has expired.";
        $messageClass = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $resetData) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageClass = "error";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageClass = "error";
    } else {
        $newPassword = password_hash($password, PASSWORD_DEFAULT);
        $email = $resetData['email'];

        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");

        if (!$updateStmt) {
            $message = "Unable to update your password. Please try again.";
            $messageClass = "error";
        } else {
            $updateStmt->bind_param("ss", $newPassword, $email);

            if ($updateStmt->execute()) {
                $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                if ($deleteStmt) {
                    $deleteStmt->bind_param("s", $token);
                    $deleteStmt->execute();
                }

                $passwordUpdated = true;
                $resetData = null;
                $message = "Password updated successfully.";
                $messageClass = "success";
            } else {
                $message = "Unable to update your password. Please try again.";
                $messageClass = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | SILM</title>
    <link rel="stylesheet" href="styles.css">
    <script src="script.js" defer></script>
</head>
<body>
<header class="header">
    <div class="container nav">
        <a href="index.php" class="logo-link" aria-label="Silm home">
            <h2 class="logo">Silm</h2>
        </a>
        <button class="hamburger" aria-label="Menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="login.php" class="nav-auth">Login</a></li>
                <li><a href="account.html" class="nav-auth nav-auth-primary">Create Account</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="overlay"></div>

<main>
    <div class="container auth-container">
        <div class="card auth-card">
            <h2 class="card-title">Reset Password</h2>

            <?php if ($message): ?>
                <p class="form-message <?php echo h($messageClass); ?>"><?php echo h($message); ?></p>
            <?php endif; ?>

            <?php if ($passwordUpdated): ?>
                <p class="auth-footer">
                    <a href="login.php">Login with your new password</a>
                </p>
            <?php elseif ($resetData): ?>
                <form method="POST" action="resetPassword.php" class="form">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">

                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label><br>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-input"
                            placeholder="New password"
                            minlength="6"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label><br>
                        <input
                            type="password"
                            name="confirm_password"
                            id="confirm_password"
                            class="form-input"
                            placeholder="Confirm password"
                            minlength="6"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            <?php else: ?>
                <p class="auth-footer">
                    <a href="forgotPassword.php">Request a new reset link</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
