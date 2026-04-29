<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$conn = getDBConnection();

$username = 'admin';
$password = 'password123';
$hashed = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';

// Check if exists
$check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo "User devadmin already exists. Updating password.\n";
    $stmt = $conn->prepare("UPDATE users SET password = ?, status = 'active' WHERE username = ?");
    $stmt->bind_param("ss", $hashed, $username);
} else {
    echo "Creating user devadmin.\n";
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, ?, 'active')");
    $stmt->bind_param("sss", $username, $hashed, $role);
}

if ($stmt->execute()) {
    echo "Success! You can now log in with: \nUsername: $username \nPassword: $password\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}
$stmt->close();
?>
