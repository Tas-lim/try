<?php
require_once __DIR__ . '/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<h1>Welcome <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
<a href="logout.php">Logout</a>
