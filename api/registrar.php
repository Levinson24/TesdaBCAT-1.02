<?php
/**
 * Registrar REST API Endpoint
 * Handles Class Sections, Enrollments, and Reports
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'api_bootstrap.php';

try {
    $conn = get_api_conn();

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case 'get_sections':
            api_log_audit($conn, 'READ_SECTIONS', 'class_sections', 0, 'Accessed curriculum catalog');
            $stmt = $conn->prepare("
                SELECT cs.section_id, cs.section_name, cs.semester, cs.school_year, 
                       c.course_code, c.course_name,
                       CONCAT(i.first_name, ' ', i.last_name) as instructor_name,
                       cs.status, cs.course_id, cs.instructor_id
                FROM class_sections cs
                JOIN courses c ON cs.course_id = c.course_id
                JOIN instructors i ON cs.instructor_id = i.instructor_id
                ORDER BY cs.created_at DESC
            ");
            $stmt->execute();
            echo json_encode(["success" => true, "sections" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'create_section':
            $stmt = $conn->prepare("INSERT INTO class_sections (section_name, course_id, instructor_id, semester, school_year, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$input['section_name'], $input['course_id'], $input['instructor_id'], $input['semester'], $input['school_year']]);
            api_log_audit($conn, 'CREATE_SECTION', 'class_sections', $conn->lastInsertId(), "New section: {$input['section_name']}");
            echo json_encode(["success" => true, "id" => $conn->lastInsertId()]);
            break;

        case 'update_section':
            $stmt = $conn->prepare("UPDATE class_sections SET section_name = ?, course_id = ?, instructor_id = ?, semester = ?, school_year = ?, status = ? WHERE section_id = ?");
            $stmt->execute([
                $input['section_name'], 
                $input['course_id'], 
                $input['instructor_id'], 
                $input['semester'], 
                $input['school_year'],
                $input['status'] ?? 'active',
                $input['section_id']
            ]);
            api_log_audit($conn, 'UPDATE_SECTION', 'class_sections', $input['section_id'], "Modified metadata for {$input['section_name']}");
            echo json_encode(["success" => true]);
            break;

        case 'delete_section':
            $section_id = $_GET['section_id'];
            $stmt = $conn->prepare("DELETE FROM class_sections WHERE section_id = ?");
            if ($stmt->execute([$section_id])) {
                api_log_audit($conn, 'DELETE_SECTION', 'class_sections', $section_id, "Purged section record");
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false, "message" => "Deletion failed"]);
            }
            break;
        case 'bulk_generate_sections':
            $data = json_decode(file_get_contents("php://input"), true);
            $semester = $data['semester'];
            $sy = $data['school_year'];
            
            // Get all active courses
            $stmt = $conn->query("SELECT course_id, course_code FROM courses WHERE status = 'active'");
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Default instructor (first admin or specified in settings)
            $instStmt = $conn->query("SELECT instructor_id FROM instructors LIMIT 1");
            $defaultInst = $instStmt->fetchColumn() ?: 1;
            
            $conn->beginTransaction();
            $count = 0;
            $insStmt = $conn->prepare("INSERT IGNORE INTO class_sections (course_id, instructor_id, section_name, semester, school_year, status) VALUES (?, ?, ?, ?, ?, 'active')");
            
            foreach ($courses as $c) {
                $sectionName = "Section A"; // Default bulk name
                $insStmt->execute([$c['course_id'], $defaultInst, $sectionName, $semester, $sy]);
                if ($insStmt->rowCount() > 0) $count++;
            }
            
            $conn->commit();
            api_log_audit($conn, 'BULK_GENERATE_SECTIONS', 'class_sections', 0, "Automatically provisioned $count sections for $sy - $semester");
            echo json_encode(["success" => true, "count" => $count]);
            break;
            
        case 'get_courses':
            $stmt = $conn->prepare("SELECT course_id, course_name, course_code FROM courses ORDER BY course_name");
            $stmt->execute();
            echo json_encode(["success" => true, "courses" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
            
        case 'get_instructors':
            $stmt = $conn->prepare("SELECT instructor_id, CONCAT(first_name, ' ', last_name) as instructor_name FROM instructors ORDER BY last_name");
            $stmt->execute();
            echo json_encode(["success" => true, "instructors" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Unknown action"]);
            break;
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
