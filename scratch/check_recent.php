<?php
require_once 'config/database.php';
$conn = getDBConnection();
$res = $conn->query("SELECT * FROM users ORDER BY user_id DESC LIMIT 5");
echo "USERS:\n";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$res = $conn->query("SELECT * FROM instructors ORDER BY user_id DESC LIMIT 5");
echo "INSTRUCTORS:\n";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
