<?php
require_once 'api_bootstrap.php';
check_api_auth();

try {
    $conn = get_api_conn();
    $type = $_GET['type'] ?? '';

    switch ($type) {
        case 'students':
            $stmt = $conn->query("
                SELECT s.student_no, s.first_name, s.last_name, s.gender, s.birth_date, s.contact_no, s.status,
                       p.program_name, d.title_diploma_program as department
                FROM students s
                LEFT JOIN programs p ON s.program_id = p.program_id
                LEFT JOIN departments d ON s.dept_id = d.dept_id
                ORDER BY s.last_name ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'instructors':
            $stmt = $conn->query("
                SELECT i.first_name, i.last_name, i.gender, i.specialization, d.title_diploma_program as department
                FROM instructors i
                LEFT JOIN departments d ON i.dept_id = d.dept_id
                ORDER BY i.last_name ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'sections':
            $stmt = $conn->query("
                SELECT s.section_name, s.semester, s.school_year, s.schedule, s.status,
                       c.course_name, c.course_code,
                       CONCAT(i.first_name, ' ', i.last_name) as instructor
                FROM class_sections s
                JOIN courses c ON s.course_id = c.course_id
                LEFT JOIN instructors i ON s.instructor_id = i.instructor_id
                ORDER BY s.school_year DESC, s.section_name ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        default:
            echo json_encode(["success" => false, "message" => "Invalid report type"]);
            exit;
    }

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
