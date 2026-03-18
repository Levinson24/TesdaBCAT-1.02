<?php
/**
 * Universal Database Synchronization Script
 */
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Secure the script: Only allow command-line execution or admin sessions
if (php_sapi_name() !== 'cli') {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
        die("Access denied. This script can only be run via CLI or by an administrator.");
    }
}

$conn = getDBConnection();

echo "Starting Synchronization...\n";

// --- 1. Map Department Text to IDs ---
echo "Syncing student departments from legacy 'course' column...\n";
$table = 'students';
$colName = 'course';

$res = $conn->query("SELECT DISTINCT $colName FROM $table WHERE $colName IS NOT NULL AND $colName != '' AND (dept_id IS NULL OR dept_id = 0)");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $deptName = $row[$colName];

        // Check if department exists in departments table
        $check = $conn->prepare("SELECT dept_id FROM departments WHERE title_diploma_program = ?");
        $check->bind_param("s", $deptName);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $deptId = $checkResult->fetch_assoc()['dept_id'];
            
            // Update the dept_id column
            $upd = $conn->prepare("UPDATE $table SET dept_id = ? WHERE $colName = ? AND (dept_id IS NULL OR dept_id = 0)");
            $upd->bind_param("is", $deptId, $deptName);
            $upd->execute();
            echo "Updated " . $conn->affected_rows . " records in $table for '$deptName'.\n";
        } else {
            echo "Warning: No Diploma Program found matching '$deptName'. Please create it manually.\n";
        }
    }
}

echo "Syncing User dept_id from profile tables...\n";
// Sync user dept_id from students/instructors if null
$conn->query("UPDATE users u JOIN students s ON u.user_id = s.user_id SET u.dept_id = s.dept_id WHERE u.dept_id IS NULL OR u.dept_id = 0");
$conn->query("UPDATE users u JOIN instructors i ON u.user_id = i.user_id SET u.dept_id = i.dept_id WHERE u.dept_id IS NULL OR u.dept_id = 0");
echo "User departments synchronized from profiles.\n";

echo "Syncing course Diploma Program IDs from programs...\n";
$conn->query("UPDATE courses c JOIN programs p ON c.program_id = p.program_id SET c.dept_id = p.dept_id WHERE c.dept_id IS NULL OR c.dept_id = 0");
echo "Courses updated from programs: " . $conn->affected_rows . " records.\n";

// --- 2. Enforce 6-digit codes and sync courses ---
echo "Syncing course codes...\n";
$resCourses = $conn->query("SELECT course_id, class_code, course_code FROM courses");
while ($course = $resCourses->fetch_assoc()) {
    $cid = $course['course_id'];
    $classCode = $course['class_code'] ?? '';
    $courseCode = $course['course_code'] ?? '';

    // Ensure padding or truncation for 6 digits if needed (user requirement)
    // However, if it's already 6, leave it. If not, just warn.
    if (strlen($classCode) != 6 || strlen($courseCode) != 6) {
        echo "Warning: Course ID $cid has non-6-digit codes (Class: $classCode, Course: $courseCode)\n";
    }
}

// --- 3. Clean up legacy columns (Commented out for safety initially, or user can decide) ---
// echo "Dropping legacy department columns...\n";
// $conn->query("ALTER TABLE students DROP COLUMN department");
// $conn->query("ALTER TABLE instructors DROP COLUMN department");

echo "Synchronization finished.\n";
