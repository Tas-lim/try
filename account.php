<?php
require_once __DIR__ . '/db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    //prepare and execute query 
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email); // Use prepared statements to prevent SQL injection
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        echo "User email already exists. Please enter another email or <a href='login.php'>Login</a>.";
    } else {
        // Insert new user into the database 
        $stmt = $conn->prepare("INSERT INTO users (name, password, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $hashed_password, $email);
        if ($stmt->execute()) {
            echo "Account created successfully. <a href='login.php'>Login</a>.";
        } else {
            echo "Error: " . $conn->error;
        }
    }
    $stmt->close();
    $conn->close();
} else {
    header("Location: account.html");
exit();
}
?>