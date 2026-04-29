<?php
require_once 'api_bootstrap.php';
check_api_auth();

try {
    $conn = get_api_conn();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        $stmt = $conn->query("SELECT * FROM courses ORDER BY course_name ASC");
        echo json_encode(["success" => true, "courses" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        switch ($action) {
            case 'create':
                $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description) VALUES (?, ?, ?)");
                $stmt->execute([$data['course_code'], $data['course_name'], $data['description']]);
                echo json_encode(["success" => true, "message" => "Course Added"]);
                break;
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
                $stmt->execute([$data['course_id']]);
                echo json_encode(["success" => true, "message" => "Course Removed"]);
                break;
        }
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
