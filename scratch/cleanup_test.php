<?php
require_once 'config/database.php';
$c = getDBConnection();
$c->query('DELETE FROM instructors WHERE user_id IN (4)');
$c->query('DELETE FROM users WHERE user_id IN (3,4,5)');
echo "Cleaned test records.\n";
$r = $c->query('SELECT user_id, username, role, first_name, last_name FROM users');
while($row = $r->fetch_assoc()) {
    echo "  ID#{$row['user_id']} @{$row['username']} ({$row['role']}) - {$row['first_name']} {$row['last_name']}\n";
}
?>
