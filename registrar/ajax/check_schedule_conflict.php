<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !in_array(getCurrentUserRole(), ['registrar', 'registrar_staff'])) {
    echo json_encode(['conflict' => false, 'error' => 'Unauthorized']);
    exit;
}

$instructor_id = isset($_POST['instructor_id']) ? intval($_POST['instructor_id']) : 0;
$room = isset($_POST['room']) ? sanitizeInput($_POST['room']) : '';
$schedule = isset($_POST['schedule']) ? sanitizeInput($_POST['schedule']) : '';
$semester = isset($_POST['semester']) ? sanitizeInput($_POST['semester']) : '';
$school_year = isset($_POST['school_year']) ? sanitizeInput($_POST['school_year']) : '';
$section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : null;

// Only check if we have enough parameters
if (!$instructor_id || empty($schedule) || empty($semester) || empty($school_year)) {
    echo json_encode(['conflict' => false, 'msg' => '']);
    exit;
}

$conflict = checkSectionConflict($instructor_id, $room, $schedule, $semester, $school_year, $section_id);

if ($conflict) {
    echo json_encode([
        'conflict' => true,
        'type' => $conflict['type'],
        'msg' => $conflict['msg']
    ]);
} else {
    // Also parse it to make sure format is valid
    $parsed = parseSchedule($schedule);
    if (!$parsed && strtoupper($schedule) !== 'TBA') {
        echo json_encode([
            'conflict' => true,
            'type' => 'Format',
            'msg' => 'Schedule format is unrecognized (use something like MWF 8:00AM-10:00AM).'
        ]);
    } else {
        echo json_encode([
            'conflict' => false,
            'msg' => 'Schedule is available.'
        ]);
    }
}
