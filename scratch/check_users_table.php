<?php
require_once 'config/database.php';
$conn = getDBConnection();
$r = $conn->query("SHOW CREATE TABLE users");
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'] ?? $row['Create View'] ?? 'Unknown';
} else {
    echo "ERROR: " . $conn->error;
}
?>
