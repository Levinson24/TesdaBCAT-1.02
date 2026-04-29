<?php
require_once 'config/database.php';
$conn = getDBConnection();
$tables = ['users', 'students', 'instructors', 'departments'];
foreach ($tables as $t) {
    echo "\nTable: $t\n";
    $res = $conn->query("DESCRIBE $t");
    while ($row = $res->fetch_assoc()) echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
