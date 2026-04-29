<?php
require_once 'api_bootstrap.php';
check_api_auth();

// Verify user is a department head
if ($_SESSION['role'] !== 'dept_head') {
    echo json_encode(["success" => false, "message" => "Access denied"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$dept_id = $_SESSION['dept_id'] ?? null;

try {
    $conn = get_api_conn();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_submissions':
            // Get sections in this dept that have submitted grades
            $stmt = $conn->prepare("
                SELECT DISTINCT s.section_id, s.section_name, c.course_name, i.first_name, i.last_name, g.status, g.submitted_at
                FROM class_sections s
                JOIN courses c ON s.course_id = c.course_id
                JOIN instructors i ON s.instructor_id = i.instructor_id
                JOIN grades g ON s.section_id = g.section_id
                WHERE (c.dept_id = ? OR s.instructor_id IN (SELECT instructor_id FROM instructors WHERE dept_id = ?))
                AND g.status = 'submitted'
            ");
            $stmt->execute([$dept_id, $dept_id]);
            echo json_encode(["success" => true, "submissions" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'process_approval':
            $data = json_decode(file_get_contents('php://input'), true);
            $section_id = $data['section_id'];
            $status = $data['status']; // 'approved' or 'rejected'
            $comment = $data['comment'] ?? '';

            $stmt = $conn->prepare("
                UPDATE grades 
                SET status = ?, approved_by = ?, approved_at = NOW(), comments = ? 
                WHERE section_id = ? AND status = 'submitted'
            ");
            $stmt->execute([$status, $user_id, $comment, $section_id]);
            api_log_audit($conn, 'PROCESS_GRADE_APPROVAL', 'grades', $section_id, "Result: " . strtoupper($status) . " | Comment: $comment");

            // Create notification for the instructor
            $stmtNotif = $conn->prepare("
                INSERT INTO notifications (user_id, message, type)
                SELECT i.user_id, ?, ?
                FROM class_sections s
                JOIN instructors i ON s.instructor_id = i.instructor_id
                WHERE s.section_id = ?
            ");
            $msg = "Your grades for section ID $section_id have been " . $status;
            $type = ($status === 'approved') ? 'success' : 'error';
            $stmtNotif->execute([$msg, $type, $section_id]);

            echo json_encode(["success" => true, "message" => "Grades " . ucfirst($status)]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Invalid action"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
