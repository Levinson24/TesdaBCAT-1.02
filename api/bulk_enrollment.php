<?php
require_once 'api_bootstrap.php';
check_api_auth();

if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

try {
    $conn = get_api_conn();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['csv_file'])) {
            echo json_encode(["success" => false, "message" => "No file uploaded"]);
            exit;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header
        fgetcsv($handle);
        
        $conn->beginTransaction();
        
        $stmtUser = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'student')");
        $stmtStudent = $conn->prepare("
            INSERT INTO students (user_id, student_no, first_name, last_name, gender, program_id, dept_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $successCount = 0;
        $errors = [];

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $studentNo = $data[0];
            $firstName = $data[1];
            $lastName = $data[2];
            $gender = $data[3];
            $programId = $data[4];
            $deptId = $data[5];

            try {
                // Create user first
                $hashedPass = password_hash($studentNo, PASSWORD_DEFAULT);
                $stmtUser->execute([$studentNo, $hashedPass]);
                $userId = $conn->lastInsertId();

                // Create student profile
                $stmtStudent->execute([$userId, $studentNo, $firstName, $lastName, $gender, $programId, $deptId]);
                $successCount++;
            } catch (Exception $e) {
                $errors[] = "Error at Student No $studentNo: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        $conn->commit();
        
        echo json_encode([
            "success" => true, 
            "count" => $successCount, 
            "errors" => $errors,
            "message" => "Bulk processing complete"
        ]);
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
