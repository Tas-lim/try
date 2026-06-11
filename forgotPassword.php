<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/passwordResetHelpers.php';

$message = '';
$messageClass = '';
$resetLink = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageClass = "error";
    } elseif (!ensurePasswordResetTable($conn)) {
        $message = "Password reset is not available right now. Please try again later.";
        $messageClass = "error";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");

        if (!$stmt) {
            $message = "Password reset is not available right now. Please try again later.";
            $messageClass = "error";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $accountExists = $result->num_rows === 1;
            $linkCreated = false;

            if ($accountExists) {
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", time() + (15 * 60));

                $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                if ($deleteStmt) {
                    $deleteStmt->bind_param("s", $email);
                    $deleteStmt->execute();
                }

                $insertStmt = $conn->prepare(
                    "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
                );

                if ($insertStmt) {
                    $insertStmt->bind_param("sss", $email, $token, $expires);

                    if ($insertStmt->execute()) {
                        $resetLink = currentBaseUrl() . "/resetPassword.php?token=" . urlencode($token);
                        $linkCreated = true;
                    }
                }
            }

            if ($accountExists && !$linkCreated) {
                $message = "Unable to create a password reset link. Please try again.";
                $messageClass = "error";
            } else {
                $message = "If an account exists for that email, a password reset link has been created.";
                $messageClass = "success";
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
    <title>Forgot Password | SILM</title>
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
            <h2 class="card-title">Forgot Password</h2>

            <?php if ($message): ?>
                <p class="form-message <?php echo h($messageClass); ?>"><?php echo h($message); ?></p>
            <?php endif; ?>

            <?php if ($resetLink): ?>
                <p class="auth-footer">
                    Password reset link:
                    <a href="<?php echo h($resetLink); ?>"><?php echo h($resetLink); ?></a>
                </p>
            <?php endif; ?>

            <form method="POST" class="form">
                <div class="form-group">
                    <label for="email" class="form-label">Email</label><br>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-input"
                        placeholder="Enter your email"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary">Create Reset Link</button>
            </form>

            <p class="auth-footer">
                Remembered your password? <a href="login.php">Login</a>
            </p>
        </div>
    </div>
</main>
</body>
</html>
