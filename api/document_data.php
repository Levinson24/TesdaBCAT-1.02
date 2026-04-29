<?php
require_once 'api_bootstrap.php';
check_api_auth();

$student_id = $_GET['student_id'] ?? 0;
if (!$student_id) die(json_encode(["success" => false, "message" => "Student ID required"]));

try {
    $conn = get_api_conn();
    
    // 1. Student Profile
    $stmt = $conn->prepare("
        SELECT s.*, p.program_name, d.title_diploma_program as dept_name,
               c.college_name, c.college_code
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.program_id
        LEFT JOIN departments d ON s.dept_id = d.dept_id
        LEFT JOIN colleges c ON d.college_id = c.college_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) die(json_encode(["success" => false, "message" => "Profile not found"]));

    // 2. Academic Records (Grades)
    $stmt = $conn->prepare("
        SELECT g.*, c.course_code, c.course_name, c.units,
               cs.semester, cs.school_year,
               CONCAT(i.first_name, ' ', i.last_name) as instructor
        FROM grades g
        JOIN class_sections cs ON g.section_id = cs.section_id
        JOIN courses c ON cs.course_id = c.course_id
        JOIN instructors i ON cs.instructor_id = i.instructor_id
        WHERE g.student_id = ? AND g.status = 'approved'
        ORDER BY cs.school_year DESC, cs.semester DESC
    ");
    $stmt->execute([$student_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. System Branding (with fallbacks)
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('school_name', 'school_address', 'school_region')");
    $dbBranding = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $branding = array_merge([
        'school_name' => 'BALICUATRO COLLEGE OF ARTS AND TRADES',
        'school_address' => 'Allen, Northern Samar, Philippines',
        'school_region' => 'Region VIII - Eastern Visayas'
    ], $dbBranding);

    echo json_encode([
        "success" => true,
        "profile" => $profile,
        "records" => $records,
        "branding" => $branding,
        "timestamp" => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
