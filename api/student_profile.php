<?php
require_once 'api_bootstrap.php';
check_api_auth();

$user_id = $_SESSION['user_id'];

try {
    $conn = get_api_conn();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $conn->prepare("
            SELECT 
                s.*, 
                p.program_name, 
                d.title_diploma_program as dept_name,
                c.college_name,
                u.username,
                u.created_at as account_created,
                u.last_login,
                u.role
            FROM students s
            LEFT JOIN programs p ON s.program_id = p.program_id
            LEFT JOIN departments d ON s.dept_id = d.dept_id
            LEFT JOIN colleges c ON d.college_id = c.college_id
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE s.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "student" => $student]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $stmt = $conn->prepare("
            UPDATE students SET 
                middle_name = ?,
                date_of_birth = ?,
                gender = ?,
                address = ?, 
                municipality = ?,
                contact_number = ?, 
                email = ?, 
                religion = ?,
                elem_school = ?,
                elem_year = ?,
                secondary_school = ?,
                secondary_year = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $data['middle_name'],
            $data['date_of_birth'],
            $data['gender'],
            $data['address'],
            $data['municipality'],
            $data['contact_number'],
            $data['email'],
            $data['religion'],
            $data['elem_school'],
            $data['elem_year'],
            $data['secondary_school'],
            $data['secondary_year'],
            $user_id
        ]);
        
        echo json_encode(["success" => true, "message" => "Institutional Profile Updated Successfully"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
