<?php
require_once 'api_bootstrap.php';
check_api_auth();

try {
    $conn = get_api_conn();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        switch ($action) {
            case 'list_programs':
                $stmt = $conn->query("SELECT p.*, d.dept_code FROM programs p LEFT JOIN departments d ON p.dept_id = d.dept_id ORDER BY p.program_name ASC");
                echo json_encode(["success" => true, "programs" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            case 'list_departments':
                $stmt = $conn->query("SELECT dept_id, dept_code, title_diploma_program FROM departments WHERE status = 'active'");
                echo json_encode(["success" => true, "departments" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        switch ($action) {
            case 'create':
                $stmt = $conn->prepare("INSERT INTO programs (dept_id, program_name, program_code, description, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$data['dept_id'], $data['program_name'], $data['program_code'], $data['description']]);
                echo json_encode(["success" => true, "message" => "Program Created"]);
                break;
            case 'update':
                $stmt = $conn->prepare("UPDATE programs SET dept_id = ?, program_name = ?, program_code = ?, description = ?, status = ? WHERE program_id = ?");
                $stmt->execute([$data['dept_id'], $data['program_name'], $data['program_code'], $data['description'], $data['status'], $data['program_id']]);
                echo json_encode(["success" => true, "message" => "Program Updated"]);
                break;
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM programs WHERE program_id = ?");
                $stmt->execute([$data['program_id']]);
                echo json_encode(["success" => true, "message" => "Program Removed"]);
                break;
        }
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
