<?php
/**
 * AJAX Endpoint for Dashboard Charts
 * Provides data for enrollment trends and student distribution
 */
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Secure the endpoint
startSession();
if (!isLoggedIn() || !hasRole(['admin', 'registrar', 'dept_head'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();
$response = [];

// 1. Enrollment by Diploma Program (Departments)
$progQuery = "
    SELECT d.title_diploma_program as name, COUNT(s.student_id) as count
    FROM departments d
    LEFT JOIN students s ON d.dept_id = s.dept_id AND s.status = 'active'
    WHERE d.status = 'active'
    GROUP BY d.dept_id
    ORDER BY count DESC
";
$progResult = $conn->query($progQuery);
$response['programs'] = [];
while ($row = $progResult->fetch_assoc()) {
    $response['programs'][] = [
        'label' => $row['name'],
        'value' => (int)$row['count']
    ];
}

// 2. Student Distribution by Year Level
$yearQuery = "
    SELECT year_level, COUNT(student_id) as count
    FROM students
    WHERE status = 'active'
    GROUP BY year_level
    ORDER BY year_level ASC
";
$yearResult = $conn->query($yearQuery);
$response['yearLevels'] = [];
while ($row = $yearResult->fetch_assoc()) {
    $response['yearLevels'][] = [
        'label' => 'Year ' . $row['year_level'],
        'value' => (int)$row['count']
    ];
}

// 3. Grade Submission Status (Overall Recent)
$gradeQuery = "
    SELECT status, COUNT(*) as count
    FROM grades
    GROUP BY status
";
$gradeResult = $conn->query($gradeQuery);
$response['grades'] = [
    'labels' => [],
    'values' => []
];
while ($row = $gradeResult->fetch_assoc()) {
    $response['grades']['labels'][] = ucfirst($row['status']);
    $response['grades']['values'][] = (int)$row['count'];
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
