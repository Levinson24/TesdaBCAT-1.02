<?php
require_once 'config/database.php';
$conn = getDBConnection();
$res = $conn->query("SELECT user_id, username, role, dept_id FROM users WHERE role IN ('registrar', 'admin')");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['user_id']}, User: {$row['username']}, Role: {$row['role']}, Dept: " . ($row['dept_id'] ?? 'NULL') . "\n";
}
echo "--- Students samples ---\n";
$res2 = $conn->query("SELECT student_id, first_name, dept_id FROM students LIMIT 1");
while($row = $res2->fetch_assoc()) {
    echo "Student ID: {$row['student_id']}, Name: {$row['first_name']}, Dept: {$row['dept_id']}\n";
}
