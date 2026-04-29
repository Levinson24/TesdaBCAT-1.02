<?php
require_once 'config/database.php';
$conn = getDBConnection();
$tables = ['students', 'instructors', 'departments'];
foreach($tables as $t) {
    $r = $conn->query("SHOW CREATE TABLE $t");
    if($r) {
        $row = $r->fetch_assoc();
        echo $row['Create Table'] ?? $row['Create View'] ?? 'Unknown';
        echo "\n\n";
    }
}
?>
