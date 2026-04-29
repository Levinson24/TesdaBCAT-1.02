<?php
define('APP_NAME', 'TESDA-BCAT GMS');

$conn = new mysqli('localhost', 'root', '', 'tesda_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create admin_messages table
$sql = "CREATE TABLE IF NOT EXISTS admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(100) NOT NULL,
    sender_email VARCHAR(100) NOT NULL,
    sender_contact VARCHAR(50) DEFAULT NULL,
    system_role VARCHAR(50) DEFAULT 'Guest',
    message_body TEXT NOT NULL,
    status ENUM('unread', 'read', 'archived') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<div style='color: green; font-weight: bold;'>[OK] Table 'admin_messages' successfully created or already exists.</div>";
} else {
    echo "<div style='color: red; font-weight: bold;'>[ERROR] Error creating table 'admin_messages': " . $conn->error . "</div>";
}

$conn->close();
?>
