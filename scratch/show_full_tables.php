<?php
require_once 'config/database.php';
$conn = getDBConnection();

$res = $conn->query("SHOW FULL TABLES");
while ($row = $res->fetch_row()) {
    echo $row[0] . ' - ' . $row[1] . "\n";
}
?>
