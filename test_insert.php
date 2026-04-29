<?php
$conn = new mysqli('localhost', 'root', '', 'tesda_db');

$enrollmentId = 13;
$stuId = 2;
$secId = 13;
$midterm = null;
$final = null;
$finalGrade = 2.5;
$remarks = 'Good';
$instructorId = 1;

$insertStmt = $conn->prepare("
    INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at, approved_by, approved_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?, NOW())
");
if (!$insertStmt) {
    die("Prepare failed: " . $conn->error);
}

// Notice passing essentially the same thing as the script
$success = $insertStmt->bind_param("iiidddsii", $enrollmentId, $stuId, $secId, $midterm, $final, $finalGrade, $remarks, $instructorId, $instructorId);
if (!$success) {
    die("Bind param failed: " . $insertStmt->error);
}

if (!$insertStmt->execute()) {
    die("Execute failed: " . $insertStmt->error);
} else {
    echo "Inserted successfully!";
}
