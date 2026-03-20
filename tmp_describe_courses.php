<?php
require_once 'config/database.php';
$conn = getDBConnection();
$res = $conn->query("DESCRIBE courses");
while($row = $res->fetch_assoc()) {
    echo "Field: " . $row['Field'] . " | Type: " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . " | Default: " . $row['Default'] . "\n";
}
?>
