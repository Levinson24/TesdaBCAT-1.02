<?php
require_once 'api_bootstrap.php';
check_api_auth();

try {
    $conn = get_api_conn();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case 'get_users':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
            $offset = ($page - 1) * $limit;

            $count = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
            
            $stmt = $conn->prepare("
                SELECT u.user_id, u.username, u.role, u.status, u.created_at,
                       COALESCE(CONCAT(s.first_name, ' ', s.last_name), CONCAT(i.first_name, ' ', i.last_name), u.username) as full_name,
                       COALESCE(d1.title_diploma_program, d2.title_diploma_program, '-') as dept
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN programs p ON s.program_id = p.program_id
                LEFT JOIN departments d1 ON p.dept_id = d1.dept_id
                LEFT JOIN instructors i ON u.user_id = i.user_id
                LEFT JOIN departments d2 ON i.dept_id = d2.dept_id
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode([
                "success" => true, 
                "users" => $stmt->fetchAll(PDO::FETCH_ASSOC),
                "pagination" => [
                    "total" => (int)$count,
                    "pages" => ceil($count / $limit),
                    "page" => $page
                ]
            ]);
            break;
        case 'create_user':
            $hashed = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$input['username'], $hashed, $input['role']]);
            echo json_encode(["success" => true, "message" => "Account Created"]);
            break;
        case 'update_user':
            $params = [$input['username'], $input['role'], $input['user_id']];
            $sql = "UPDATE users SET username = ?, role = ? ";
            if (!empty($input['password'])) {
                $sql .= ", password = ? ";
                $params = [$input['username'], $input['role'], password_hash($input['password'], PASSWORD_DEFAULT), $input['user_id']];
            }
            $sql .= " WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(["success" => true, "message" => "Account Updated"]);
            break;
        case 'delete_user':
            $user_id = $_GET['user_id'];
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(["success" => true, "message" => "Account Deleted"]);
            break;
        case 'get_stats':
            $stats = [
                'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'active_students' => $conn->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn(),
                'total_instructors' => $conn->query("SELECT COUNT(*) FROM instructors")->fetchColumn(),
                'total_colleges' => $conn->query("SELECT COUNT(*) FROM colleges")->fetchColumn(),
                'total_departments' => $conn->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
                'total_programs' => $conn->query("SELECT COUNT(*) FROM programs")->fetchColumn(),
                'total_courses' => $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
                'total_sections' => $conn->query("SELECT COUNT(*) FROM class_sections")->fetchColumn(),
                'total_enrollments' => $conn->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(),
                'total_grades' => $conn->query("SELECT COUNT(*) FROM grades")->fetchColumn(),
                'total_logs' => $conn->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn(),
                'total_notifications' => $conn->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
                'enrollment_distribution' => $conn->query("
                    SELECT p.program_code as label, COUNT(s.student_id) as value 
                    FROM programs p 
                    LEFT JOIN students s ON p.program_id = s.program_id 
                    GROUP BY p.program_id 
                    LIMIT 5
                ")->fetchAll(PDO::FETCH_ASSOC)
            ];
            echo json_encode(["success" => true, "stats" => $stats]);
            break;
        case 'get_logs':
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;

            $count = $conn->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
            
            $stmt = $conn->prepare("
                SELECT l.log_id, l.action, l.created_at as time, 
                       COALESCE(u.username, 'System') as username,
                       l.details
                FROM audit_logs l
                LEFT JOIN users u ON l.user_id = u.user_id
                ORDER BY l.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode([
                "success" => true, 
                "logs" => $stmt->fetchAll(PDO::FETCH_ASSOC),
                "pagination" => [
                    "total" => (int)$count,
                    "pages" => ceil($count / $limit),
                    "page" => $page
                ]
            ]);
            break;
        case 'get_activity':
            $stmt = $conn->query("SELECT log_id, action, created_at as time, 'System' as username FROM audit_logs ORDER BY created_at DESC LIMIT 10");
            echo json_encode(["success" => true, "activity" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        default:
            echo json_encode(["success" => false, "message" => "Unknown action"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
