<?php
require_once 'config/database.php';
$conn = getDBConnection();
$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    $tables[] = $row[0];
}
foreach ($tables as $table) {
    echo "Table: $table\n";
    $res2 = $conn->query("DESCRIBE $table");
    while ($row2 = $res2->fetch_assoc()) {
        echo "  " . $row2['Field'] . " - " . $row2['Type'] . "\n";
    }
}
