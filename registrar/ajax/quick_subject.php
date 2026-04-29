<?php
/**
 * AJAX Quick Subject Creation
 * TESDA-BCAT Grade Management System
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check authorization
if (!hasRole(['registrar', 'registrar_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $conn = getDBConnection();
    
    // Get user profile for dept_id enforcement
    $userRole = getCurrentUserRole();
    $userProfile = getUserProfile(getCurrentUserId(), $userRole);
    $deptId = $userProfile['dept_id'] ?? null;
    $isStaff = ($userRole === 'registrar_staff');

    $classCode = strtoupper(sanitizeInput($_POST['class_code']));
    $courseCode = strtoupper(sanitizeInput($_POST['course_code']));
    
    if (strlen($classCode) !== 6 || strlen($courseCode) !== 6) {
        echo json_encode(['success' => false, 'message' => 'Codes must be exactly 6 characters']);
        exit;
    }

    $courseName = sanitizeInput($_POST['course_name']);
    $units = intval($_POST['units']);
    $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;

    // Enforce dept_id for staff
    $courseDeptId = $deptId; 
    if (!$isStaff && !empty($programId)) {
        // If registrar, they might be creating for a specific program's dept
        $pStmt = $conn->prepare("SELECT dept_id FROM programs WHERE program_id = ?");
        $pStmt->bind_param("i", $programId);
        $pStmt->execute();
        $pRes = $pStmt->get_result()->fetch_assoc();
        $courseDeptId = $pRes['dept_id'] ?? $deptId;
    }

    $conn->begin_transaction();
    try {
        // 1. Ensure Subject exists (Using course_code as subject_id)
        $checkSubj = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
        $checkSubj->bind_param("s", $courseCode);
        $checkSubj->execute();
        if ($checkSubj->get_result()->num_rows === 0) {
            $insSubj = $conn->prepare("INSERT INTO subjects (subject_id, subject_name, units) VALUES (?, ?, ?)");
            $insSubj->bind_param("ssi", $courseCode, $courseName, $units);
            $insSubj->execute();
        }

        // 2. Create Curriculum entry
        $stmt = $conn->prepare("INSERT INTO curriculum (class_code, subject_id, program_id, dept_id, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssii", $classCode, $courseCode, $programId, $courseDeptId);
        $stmt->execute();
        $newId = $conn->insert_id;
        
        $conn->commit();
        logAudit(getCurrentUserId(), 'CREATE', 'curriculum', $newId, null, "Quick created curriculum entry for $courseCode via AJAX");
        
        echo json_encode([
            'success' => true, 
            'curriculum_id' => $newId, 
            'display' => "[$courseCode] $courseName",
            'message' => 'Subject created and assigned successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? 'Duplicate entry detected.' : $e->getMessage();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $msg]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
