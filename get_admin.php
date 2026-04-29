<?php
require_once 'config/database.php';
$conn = getDBConnection();
$result = $conn->query("SELECT username, role FROM users WHERE role = 'admin' LIMIT 1");
if ($row = $result->fetch_assoc()) {
    echo "Username: {$row['username']}\n";
    echo "Role: {$row['role']}\n";
} else {
    echo "No admin user found.\n";
}
?>
