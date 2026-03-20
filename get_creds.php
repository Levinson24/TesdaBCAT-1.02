<?php
$c = new mysqli('localhost', 'root', '', 'tesda_db');
$r = $c->query("SELECT username, password, role FROM users WHERE role IN ('registrar', 'registrar_staff')");
while($row = $r->fetch_assoc()) {
    echo $row['username'] . " | " . $row['password'] . " | " . $row['role'] . "\n";
}
