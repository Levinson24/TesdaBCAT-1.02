<?php
require_once 'config/database.php';
$conn = getDBConnection();
$res = $conn->query("SELECT DISTINCT role FROM users");
while($row = $res->fetch_assoc()) {
    echo $row['role'] . "\n";
}
