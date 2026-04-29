<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$conn = getDBConnection();

$res = $conn->query("SELECT MAX(student_no) as max_stu FROM students");
$row = $res->fetch_assoc();
echo "Max Student No in students table: " . ($row['max_stu'] ?? 'None') . "\n";

$res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'student_id_counter'");
$row = $res->fetch_assoc();
echo "student_id_counter in system_settings: " . ($row['setting_value'] ?? 'None') . "\n";

$res = $conn->query("SELECT MAX(instructor_id_no) as max_inst FROM instructors");
$row = $res->fetch_assoc();
echo "Max Instructor ID in instructors table: " . ($row['max_inst'] ?? 'None') . "\n";

$res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'instructor_id_counter'");
$row = $res->fetch_assoc();
echo "instructor_id_counter in system_settings: " . ($row['setting_value'] ?? 'None') . "\n";
?>
