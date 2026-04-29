<?php
/**
 * Bulk Generate Sections AJAX Handler
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Check authentication
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized');
    }

    $userRole = getCurrentUserRole();
    $userProfile = getUserProfile(getCurrentUserId(), $userRole);
    $deptId = $userProfile['dept_id'] ?? 0;
    $isStaff = ($userRole === 'registrar_staff');

    // Verify role
    if (!in_array($userRole, ['registrar', 'registrar_staff'])) {
        throw new Exception('Insufficient permissions');
    }

    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token');
    }

    $conn = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $schoolYear = sanitizeInput($_POST['school_year'] ?? '');
    $semester = sanitizeInput($_POST['semester'] ?? '');

    if (empty($schoolYear) || empty($semester)) {
        throw new Exception('School Year and Semester are required');
    }

    // Get all active curricula
    $query = "SELECT curriculum_id, dept_id FROM curriculum WHERE status = 'active'";
    
    if ($isStaff) {
        $query .= " AND dept_id = " . intval($deptId);
    }

    $result = $conn->query($query);
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $created = 0;
    $skipped = 0;
    $errors = [];

    while ($curriculum = $result->fetch_assoc()) {
        $curriculumId = $curriculum['curriculum_id'];
        $deptCheck = $curriculum['dept_id'];

        // Staff can only work with their department
        if ($isStaff && $deptCheck != $deptId) {
            continue;
        }

        // Check if "Section A" already exists for this curriculum, year, and semester
        $checkStmt = $conn->prepare("SELECT section_id FROM class_sections WHERE curriculum_id = ? AND section_name = 'Section A' AND school_year = ? AND semester = ?");
        if (!$checkStmt) {
            $errors[] = "Database error: " . $conn->error;
            continue;
        }

        $checkStmt->bind_param("iss", $curriculumId, $schoolYear, $semester);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $skipped++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();

        // Insert new section
        $insertStmt = $conn->prepare("INSERT INTO class_sections (curriculum_id, instructor_id, section_name, semester, school_year, schedule, room, dept_id, status) VALUES (?, 0, 'Section A', ?, ?, '', '', ?, 'active')");
        if (!$insertStmt) {
            $errors[] = "Database error: " . $conn->error;
            continue;
        }

        $insertStmt->bind_param("issi", $curriculumId, $semester, $schoolYear, $deptCheck);
        
        if ($insertStmt->execute()) {
            $created++;
            logAudit(getCurrentUserId(), 'CREATE', 'class_sections', $conn->insert_id, null, "Bulk-created Section A for curriculum $curriculumId, SY: $schoolYear, Sem: $semester");
        } else {
            $errors[] = "Failed to create section for curriculum $curriculumId: " . $conn->error;
        }
        $insertStmt->close();
    }

    $message = "Sections generated: $created created, $skipped skipped.";
    if (!empty($errors)) {
        $message .= " Errors: " . implode("; ", $errors);
    }

    echo json_encode([
        'success' => true,
        'created' => $created,
        'skipped' => $skipped,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
