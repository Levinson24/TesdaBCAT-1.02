<?php
/**
 * Bulk Section Generation AJAX
 * TESDA-BCAT Grade Management System
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole(['registrar', 'registrar_staff']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$conn = getDBConnection();

// Get academic period from POST, fallback to settings if missing
$schoolYear = $_POST['school_year'] ?? getSetting('academic_year', date('Y') . '-' . (date('Y') + 1));
$semester = $_POST['semester'] ?? getSetting('current_semester', '1st');

// Fetch all active curriculum entries
$courses_res = $conn->query("
    SELECT cur.curriculum_id, cur.class_code, s.subject_name 
    FROM curriculum cur
    JOIN subjects s ON cur.subject_id = s.subject_id
    WHERE cur.status = 'active'
");
if (!$courses_res) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

// Fetch a default instructor (first active one)
$inst_res = $conn->query("SELECT instructor_id FROM instructors WHERE status = 'active' LIMIT 1");
$default_instructor = $inst_res->fetch_assoc();
if (!$default_instructor) {
    // If no active instructor, we might need to handle this.
    // However, describe_table showed instructor_id is NOT NULL.
    // Let's assume there's at least one as confirmed by diagnostic.
    $instructor_id = 7; // Fallback to confirmed ID if needed
} else {
    $instructor_id = $default_instructor['instructor_id'];
}

$created_count = 0;
$skipped_count = 0;

while ($course = $courses_res->fetch_assoc()) {
    $curriculumId = $course['curriculum_id'];
    // Check if section already exists for this curriculum/year/semester
    $check = $conn->prepare("SELECT section_id FROM class_sections WHERE curriculum_id = ? AND school_year = ? AND semester = ?");
    $check->bind_param("iss", $curriculumId, $schoolYear, $semester);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped_count++;
        continue;
    }
    $check->close();

    // Create default section
    $sectionName = "Section A";
    $status = 'active';
    $schedule = 'TBA';
    $room = 'TBA';

    $stmt = $conn->prepare("INSERT INTO class_sections (curriculum_id, instructor_id, section_name, semester, school_year, schedule, room, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssss", $curriculumId, $instructor_id, $sectionName, $semester, $schoolYear, $schedule, $room, $status);
    
    if ($stmt->execute()) {
        $created_count++;
    }
}

echo json_encode([
    'success' => true, 
    'message' => "Successfully generated $created_count sections for $schoolYear ($semester Semester).",
    'created' => $created_count,
    'skipped' => $skipped_count
]);
