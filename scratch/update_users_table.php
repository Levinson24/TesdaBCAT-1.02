<?php
require_once 'config/database.php';
$conn = getDBConnection();
$sql = "ALTER TABLE users 
        ADD COLUMN first_name VARCHAR(100) AFTER username, 
        ADD COLUMN last_name VARCHAR(100) AFTER first_name, 
        ADD COLUMN middle_name VARCHAR(100) AFTER last_name";
if ($conn->query($sql)) {
    echo "SUCCESS: Users table updated with name columns.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
