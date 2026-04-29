<?php
/**
 * AJAX Processor: Bulk Grade Import
 * TESDA-BCAT Grade Management System
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('instructor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit();
}

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error.']);
    exit();
}

$conn = getDBConnection();
$userId = getCurrentUserId();

// Get instructor ID
$stmt = $conn->prepare("SELECT instructor_id FROM instructors WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$instructorId = $stmt->get_result()->fetch_assoc()['instructor_id'] ?? 0;
$stmt->close();

$sectionId = intval($_POST['section_id']);
$file = $_FILES['import_file']['tmp_name'];

// Verify instructor owns the section
$verifyStmt = $conn->prepare("SELECT section_id FROM class_sections WHERE section_id = ? AND instructor_id = ?");
$verifyStmt->bind_param("ii", $sectionId, $instructorId);
$verifyStmt->execute();
if (!$verifyStmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Access denied to this section.']);
    exit();
}
$verifyStmt->close();

$handle = fopen($file, 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Could not open file.']);
    exit();
}

// Skip header
fgetcsv($handle);

$imported = 0;
$passingGrade = floatval(getSetting('passing_grade', 3.00));

while (($data = fgetcsv($handle)) !== FALSE) {
    if (count($data) < 5) continue;

    $enrollmentId = intval($data[0]);
    $midterm = null;
    $final = null;
    $finalGrade = is_numeric($data[3]) ? floatval($data[3]) : null;
    $specialStatus = sanitizeInput($data[4] ?? '');

    if ($specialStatus || $finalGrade !== null) {
        if ($specialStatus) {
            $finalGrade = null;
            $remarks = $specialStatus;
        } else {
            $remarks = getGradeRemark($finalGrade, $passingGrade);
        }

        // Check if enrollment exists in this section
        $checkStmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE enrollment_id = ? AND section_id = ?");
        $checkStmt->bind_param("ii", $enrollmentId, $sectionId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->fetch_assoc()) {
            $checkStmt->close();

            // Update or Insert Grade
            $gradeCheck = $conn->prepare("SELECT grade_id FROM grades WHERE enrollment_id = ?");
            $gradeCheck->bind_param("i", $enrollmentId);
            $gradeCheck->execute();
            $existing = $gradeCheck->get_result()->fetch_assoc();
            $gradeCheck->close();

            if ($existing) {
                $updateStmt = $conn->prepare("
                    UPDATE grades 
                    SET midterm = ?, final = ?, grade = ?, remarks = ?, status = 'approved', submitted_by = ?, submitted_at = NOW(), approved_by = ?, approved_at = NOW()
                    WHERE enrollment_id = ?
                ");
                $updateStmt->bind_param("dddsiii", $midterm, $final, $finalGrade, $remarks, $instructorId, $instructorId, $enrollmentId);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at, approved_by, approved_at)
                    SELECT ?, student_id, section_id, ?, ?, ?, ?, 'approved', ?, NOW(), ?, NOW()
                    FROM enrollments WHERE enrollment_id = ?
                ");
                $insertStmt->bind_param("idddsiii", $enrollmentId, $midterm, $final, $finalGrade, $remarks, $instructorId, $instructorId, $enrollmentId);
                $insertStmt->execute();
                $insertStmt->close();
            }
            $imported++;
        } else {
            $checkStmt->close();
        }
    }
}

fclose($handle);

logAudit($userId, 'IMPORT', 'grades', $sectionId, null, "Bulk imported $imported grades for section $sectionId");

echo json_encode(['success' => true, 'imported' => $imported]);
