<?php
require_once 'api_bootstrap.php';
check_api_auth();
$user_id = $_SESSION['user_id'];

try {
    $conn = get_api_conn();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case 'get_classes':
            $stmt = $conn->prepare("
                SELECT s.section_id, s.section_name, s.status, s.semester, s.school_year, s.schedule,
                       c.course_name, c.course_code,
                       (SELECT COUNT(*) FROM enrollments WHERE section_id = s.section_id) as total_students
                FROM class_sections s 
                JOIN courses c ON s.course_id = c.course_id 
                WHERE s.instructor_id = ?
            ");
            $stmt->execute([$user_id]);
            echo json_encode(["success" => true, "classes" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'get_students_by_section':
            $section_id = $_GET['section_id'];
            $stmt = $conn->prepare("
                SELECT g.grade_id, g.midterm, g.final, g.grade, g.remarks, g.status as grade_status,
                       CONCAT(s.last_name, ', ', s.first_name) as student_name, s.student_no
                FROM grades g
                JOIN students s ON g.student_id = s.student_id
                WHERE g.section_id = ?
                ORDER BY s.last_name ASC
            ");
            $stmt->execute([$section_id]);
            echo json_encode(["success" => true, "students" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'submit_grades':
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE grades SET midterm = ?, final = ?, grade = ?, remarks = ? WHERE grade_id = ? AND status = 'pending'");
            foreach ($input['updates'] as $row) {
                if (empty($row['grade_id'])) continue;
                $midterm = floatval($row['midterm'] ?? 0);
                $final = floatval($row['final'] ?? 0);
                $avg = ($midterm + $final) / 2;
                $stmt->execute([$midterm, $final, $avg, $avg >= 75 ? 'Passed' : 'Failed', $row['grade_id']]);
            }
            api_log_audit($conn, 'SYNC_GRADES', 'grades', 0, "Synchronized class list for multiple students");
            $conn->commit();
            echo json_encode(["success" => true]);
            break;
        case 'finalize_submission':
            $section_id = $_GET['section_id'];
            $stmt = $conn->prepare("UPDATE grades SET status = 'submitted', submitted_at = NOW(), submitted_by = ? WHERE section_id = ? AND status = 'pending'");
            $stmt->execute([$user_id, $section_id]);
            api_log_audit($conn, 'FINALIZE_GRADES', 'class_sections', $section_id, "Official grade submission for review");
            echo json_encode(["success" => true, "message" => "Grades submitted to Dept Head"]);
            break;
        case 'import_grades':
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE grades SET midterm = ?, final = ?, grade = ?, remarks = ?, status = 'pending' WHERE section_id = ? AND student_id = (SELECT student_id FROM students WHERE student_no = ?)");
            $count = 0;
            foreach ($input['data'] as $row) {
                if (empty($row['student_no'])) continue;
                $midterm = floatval($row['midterm'] ?? 0);
                $final = floatval($row['final'] ?? 0);
                $avg = ($midterm + $final) / 2;
                $remarks = $avg >= 75 ? 'Passed' : 'Failed';
                if (!empty($row['remarks']) && in_array($row['remarks'], ['INC', 'Dropped'])) {
                    $remarks = $row['remarks'];
                    $avg = 0;
                }
                $stmt->execute([$midterm, $final, $avg, $remarks, $input['section_id'], $row['student_no']]);
                $count++;
            }
            $conn->commit();
            api_log_audit($conn, 'IMPORT_GRADES', 'class_sections', $input['section_id'], "Bulk imported marks for $count students");
            echo json_encode(["success" => true, "count" => $count]);
            break;
        default:
            echo json_encode(["success" => false, "message" => "Unknown action"]);
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
