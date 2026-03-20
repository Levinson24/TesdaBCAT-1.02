<?php
require_once 'config/database.php';
$conn = getDBConnection();
$res = $conn->query("DESCRIBE programs");
while($row = $res->fetch_assoc()) {
    echo "Field: " . $row['Field'] . " | Type: " . $row['Type'] . "\n";
}
?>
