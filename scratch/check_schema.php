<?php
require_once 'config/database.php';
$conn = getDBConnection();
$res = $conn->query("DESCRIBE users");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
