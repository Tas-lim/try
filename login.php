<?php
require_once __DIR__ . '/session.php';

include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $users = $result->fetch_assoc();

        if (password_verify($password, $users['password'])) {

            $_SESSION['user_id'] = $users['id'];
            $_SESSION['user_name'] = $users['name'];

            header("Location: index.php");
            exit();

        } else {
            header("Location: login.php?error=invalid");
            exit();
        }

    } else {
        header("Location: login.php?error=user_not_found");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SILM</title>
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
            <!--<li><a href="#features">Features</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>-->

            <!--<li><a href="login.php" class="nav-auth">Login</a></li>
    <li><a href="account.html" class="nav-auth nav-auth-primary">Create Account</a></li>-->
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="dashboard.php" class="nav-auth">Dashboard</a></li>
                <li><a href="logout.php" class="nav-auth nav-auth-primary">Logout</a></li>
                <li>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></li>
            <?php else: ?>
                <!--<li><a href="login.php" class="nav-auth">Login</a></li>-->
                <li><a href="account.html" class="nav-auth nav-auth-primary">Create Account</a></li>
            <?php endif; ?>
        </ul>
        </nav>
    </div>
</header>

<div class="overlay"></div>
<main>
    <div class="container auth-container">

        <div class="card auth-card">

            <h2 class="card-title">Login</h2>

                <?php
                if (isset($_GET['error'])) {
                    if ($_GET['error'] == 'invalid') {
                        echo "<p style='color:red;'>Wrong password.</p>";
                    } elseif ($_GET['error'] == 'user_not_found') {
                        echo "<p style='color:red;'>User not found.</p>";
                    }
                }
                ?>

            <form method="POST" class="form">

                <div class="form-group">
                    <label for="email" class="form-label">Email</label> <br>
                    <input type="email" name="email" id="email" class="form-input" required placeholder="Enter your email">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label> <br>
                    <input type="password" name="password" id="password" class="form-input" required placeholder="Enter your password">
                </div>

                <button type="submit" class="btn btn-primary">Login</button>

            </form>
            <p class="auth-footer">
                Don't have an account? <a href="account.html">Create Account</a>
            </p>
            <p class="auth-footer">
                <a href="forgotPassword.php">Forgot Password?</a>
            </p>


        </div>

    </div>
</main>
</body>
</html>
