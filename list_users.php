d<?php
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost:8080';
    $_SERVER['DOCUMENT_ROOT'] = 'c:/xampp/htdocs';
    $_SERVER['REQUEST_URI'] = '/TesdaBCAT-1.02/list_users.php';
}
require_once 'config/database.php';
$conn = getDBConnection();
$result = $conn->query("SELECT id, username, first_name, last_name, role FROM users");
echo "ID | Username | Name | Role\n";
echo "-------------------------------\n";
while ($row = $result->fetch_assoc()) {
    echo "{$row['id']} | {$row['username']} | {$row['first_name']} {$row['last_name']} | {$row['role']}\n";
}
?>
