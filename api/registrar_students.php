<?php
/**
 * Registrar Students API 
 * Handles Student Registry, Creation, and Form Hydration
 */
require_once 'api_bootstrap.php';
check_api_auth(['admin', 'registrar', 'registrar_staff']);

try {
    $conn = get_api_conn();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        switch ($action) {
            case 'get_students':
                api_log_audit($conn, 'READ_STUDENTS', 'students', 0, 'Accessed student registry catalog');
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
                $search = $_GET['search'] ?? '';
                $dept_id = $_GET['dept_id'] ?? '';
                $offset = ($page - 1) * $limit;

                $where = ["1=1"];
                $params = [];
                if ($search) {
                    $where[] = "(s.student_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
                    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
                }
                if ($dept_id) {
                    $where[] = "s.dept_id = ?";
                    $params[] = $dept_id;
                }

                $whereClause = implode(" AND ", $where);
                
                $countStmt = $conn->prepare("SELECT COUNT(*) FROM students s WHERE $whereClause");
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();

                $stmt = $conn->prepare("
                    SELECT s.*, p.program_name, d.title_diploma_program as dept_name
                    FROM students s
                    LEFT JOIN programs p ON s.program_id = p.program_id
                    LEFT JOIN departments d ON s.dept_id = d.dept_id
                    WHERE $whereClause
                    ORDER BY s.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                
                foreach ($params as $i => $val) {
                    $stmt->bindValue($i + 1, $val);
                }
                $stmt->bindValue(count($params) + 1, (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);
                $stmt->execute();

                echo json_encode([
                    "success" => true,
                    "students" => $stmt->fetchAll(PDO::FETCH_ASSOC),
                    "page" => $page
                ]);
                break;

        case 'list_students':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
            $search = $_GET['search'] ?? '';
            $program_id = $_GET['program_id'] ?? '';
            $year_level = $_GET['year_level'] ?? '';
            $status = $_GET['status'] ?? '';
            $offset = ($page - 1) * $limit;

            $where = ["1=1"];
            $params = [];
            if ($search) {
                $where[] = "(s.student_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($program_id) { $where[] = "s.program_id = ?"; $params[] = $program_id; }
            if ($year_level) { $where[] = "s.year_level = ?"; $params[] = $year_level; }
            if ($status) { $where[] = "s.status = ?"; $params[] = $status; }

            $clause = implode(" AND ", $where);
            $count = $conn->prepare("SELECT COUNT(*) FROM students s WHERE $clause");
            $count->execute($params);
            $total = $count->fetchColumn();

            $stmt = $conn->prepare("
                SELECT s.*, p.program_name, d.title_diploma_program as dept_name,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.student_id) as enrollment_count
                FROM students s
                LEFT JOIN programs p ON s.program_id = p.program_id
                LEFT JOIN departments d ON s.dept_id = d.dept_id
                WHERE $clause
                ORDER BY s.last_name ASC
                LIMIT ? OFFSET ?
            ");
            foreach ($params as $i => $v) $stmt->bindValue($i+1, $v);
            $stmt->bindValue(count($params)+1, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(count($params)+2, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(["success" => true, "students" => $stmt->fetchAll(PDO::FETCH_ASSOC), "pagination" => ["total" => (int)$total, "pages" => ceil($total/$limit), "page" => $page]]);
            break;

        case 'get_enrollable_sections':
            // Get current semester from settings
            $sStmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_semester'");
            $semester = $sStmt->fetchColumn() ?: '1st';
            $yStmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year'");
            $sy = $yStmt->fetchColumn() ?: '2024-2025';

            $stmt = $conn->prepare("
                SELECT cs.section_id, cs.section_name, c.course_name, CONCAT(i.first_name, ' ', i.last_name) as instructor_name
                FROM class_sections cs
                JOIN courses c ON cs.course_id = c.course_id
                LEFT JOIN instructors i ON cs.instructor_id = i.instructor_id
                WHERE cs.semester = ? AND cs.school_year = ? AND cs.status = 'active'
            ");
            $stmt->execute([$semester, $sy]);
            echo json_encode(["success" => true, "sections" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_filter_options':
            $progs = $conn->query("SELECT program_id, program_name FROM programs WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
            $depts = $conn->query("SELECT dept_id, title_diploma_program FROM departments WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "programs" => $progs, "departments" => $depts]);
            break;

            case 'get_form_data':
                $depts = $conn->query("SELECT dept_id as id, title_diploma_program as name FROM departments WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
                $programs = $conn->query("SELECT program_id as id, dept_id, program_name as name FROM programs WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "departments" => $depts, "programs" => $programs]);
                break;

            case 'delete_student':
                $student_id = $_GET['student_id'];
                // Delete user account first (foreign key Cascade should handle profile if set, but we do it manually to be safe)
                $sStmt = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
                $sStmt->execute([$student_id]);
                $user_id = $sStmt->fetchColumn();
                
                if ($user_id) {
                    $conn->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);
                }
                $conn->prepare("DELETE FROM students WHERE student_id = ?")->execute([$student_id]);
                api_log_audit($conn, 'PURGE_STUDENT', 'students', $student_id, "Permanent removal of student ID #$student_id");
                echo json_encode(["success" => true, "message" => "Record purged successfully"]);
                break;

            case 'unenroll_student':
                $student_id = $_GET['student_id'];
                $section_id = $_GET['section_id'];
                $conn->prepare("DELETE FROM enrollments WHERE student_id = ? AND section_id = ?")->execute([$student_id, $section_id]);
                api_log_audit($conn, 'UNENROLL_STUDENT', 'enrollments', 0, "Student #$student_id removed from Section #$section_id");
                echo json_encode(["success" => true, "message" => "Enrollment Revoked"]);
                break;

            default:
                echo json_encode(["success" => false, "message" => "Unknown action"]);
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if ($action === 'update_student') {
            $conn->beginTransaction();
            $sStmt = $conn->prepare("
                UPDATE students SET 
                    first_name = ?, last_name = ?, middle_name = ?, 
                    dept_id = ?, program_id = ?, enrollment_date = ?, year_level = ?,
                    gender = ?, date_of_birth = ?, religion = ?, contact_number = ?, address = ?,
                    municipality = ?, province = ?, elem_school = ?, elem_year = ?,
                    secondary_school = ?, secondary_year = ?
                WHERE student_id = ?
            ");
            
            $sStmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['middle_name'] ?? '',
                $data['dept_id'],
                $data['program_id'],
                $data['admission_date'] ?: date('Y-m-d'),
                str_replace(['st Year', 'nd Year', 'rd Year', 'th Year'], '', $data['year_level'] ?? '1'),
                $data['gender'] ?? 'Male',
                $data['birth_date'] ?: null,
                $data['religion'] ?? '',
                $data['contact_number'] ?? $data['contact_no'] ?? '',
                $data['home_address'] ?? $data['address'] ?? '',
                $data['municipality_city'] ?? $data['municipality'] ?? '',
                $data['province'] ?? '',
                $data['elementary_school'] ?? $data['elem_school'] ?? '',
                $data['elem_grad_year'] ?? $data['elem_year'] ?? null,
                $data['secondary_school'] ?? '',
                $data['sec_grad_year'] ?? $data['secondary_year'] ?? null,
                $data['student_id']
            ]);
            api_log_audit($conn, 'UPDATE_STUDENT', 'students', $data['student_id'], "Modified demographic/academic profile");
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Student Record Updated"]);
            exit;
        }

        if ($action === 'enroll_student') {
            $conn->beginTransaction();
            // Check if already enrolled
            $check = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND section_id = ?");
            $check->execute([$data['student_id'], $data['section_id']]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(["success" => false, "message" => "Student already enrolled in this section"]);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, section_id, enrollment_date, status) VALUES (?, ?, NOW(), 'enrolled')");
            $stmt->execute([$data['student_id'], $data['section_id']]);
            $enrollment_id = $conn->lastInsertId();

            // Provision Grade Record
            $gStmt = $conn->prepare("INSERT INTO grades (enrollment_id, student_id, section_id, status) VALUES (?, ?, ?, 'pending')");
            $gStmt->execute([$enrollment_id, $data['student_id'], $data['section_id']]);

            api_log_audit($conn, 'ENROLL_STUDENT', 'enrollments', $enrollment_id, "Section attachment: #".$data['section_id']);
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Enrollment Processed Successfully"]);
            exit;
        }

        // Default Registration Case
        $conn->beginTransaction();
        // 1. Create User Account
        $birth_date = $data['birth_date'] ?: null;
        $password = password_hash($birth_date ? str_replace('-', '', $birth_date) : '123456', PASSWORD_DEFAULT);
        $username = $data['student_no'] ?: 'STU-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        $uStmt = $conn->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'student', 'active')");
        $uStmt->execute([$username, $password]);
        $user_id = $conn->lastInsertId();

        // 2. Create Student Profile - Corrected Column Mapping
        $sStmt = $conn->prepare("
            INSERT INTO students (
                user_id, student_no, first_name, last_name, middle_name, 
                dept_id, program_id, enrollment_date, year_level,
                gender, date_of_birth, religion, contact_number, address,
                municipality, province, elem_school, elem_year,
                secondary_school, secondary_year, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $sStmt->execute([
            $user_id,
            $username,
            $data['first_name'],
            $data['last_name'],
            $data['middle_name'] ?? '',
            $data['dept_id'],
            $data['program_id'],
            $data['admission_date'] ?: date('Y-m-d'),
            str_replace(['st Year', 'nd Year', 'rd Year', 'th Year'], '', $data['year_level'] ?? '1'),
            $data['gender'] ?? 'Male',
            $birth_date,
            $data['religion'] ?? '',
            $data['contact_number'] ?? $data['contact_no'] ?? '',
            $data['home_address'] ?? $data['address'] ?? '',
            $data['municipality_city'] ?? $data['municipality'] ?? '',
            $data['province'] ?? '',
            $data['elementary_school'] ?? $data['elem_school'] ?? '',
            $data['elem_grad_year'] ?? $data['elem_year'] ?? null,
            $data['secondary_school'] ?? '',
            $data['sec_grad_year'] ?? $data['secondary_year'] ?? null
        ]);
        $student_id = $conn->lastInsertId();
        api_log_audit($conn, 'REGISTER_STUDENT', 'students', $student_id, "Created new credential: $username");
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Student Registered Successfully", "student_no" => $username]);
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
