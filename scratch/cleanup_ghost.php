<?php
require_once 'config/database.php';
$conn = getDBConnection();

// Delete users with empty usernames (ghost records from previous bugs)
$result = $conn->query("SELECT user_id, username, role, first_name, last_name FROM users WHERE username = '' OR username IS NULL");
echo "Ghost users found: " . $result->num_rows . "\n";
while ($row = $result->fetch_assoc()) {
    echo "  - ID#{$row['user_id']} role={$row['role']} name={$row['first_name']} {$row['last_name']}\n";
}

$conn->query("DELETE FROM users WHERE username = '' OR username IS NULL");
echo "Cleaned up " . $conn->affected_rows . " ghost records.\n";

// Verify remaining users
$result2 = $conn->query("SELECT user_id, username, first_name, last_name, role FROM users ORDER BY user_id");
echo "\nRemaining users:\n";
while ($row = $result2->fetch_assoc()) {
    echo "  ID#{$row['user_id']} @{$row['username']} ({$row['role']}) - {$row['first_name']} {$row['last_name']}\n";
}
?>
