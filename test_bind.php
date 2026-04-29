<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '', 'tesda_db');

$stmt = $conn->prepare("INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at, approved_by, approved_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NULL, NOW(), NULL, NOW())");

$e = 13; $s = 2; $sec = 13;
$m = null; $f = null; $g = 2.5; $r = 'Good';

try {
    $stmt->bind_param("iiiddds", $e, $s, $sec, $m, $f, $g, $r);
    $stmt->execute();
    echo "Inserted successfully with nulls!\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
