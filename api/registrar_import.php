<?php
/**
 * Registrar Student Import API
 * Processes CSV files for bulk student registration
 */
require_once 'api_bootstrap.php';
check_api_auth();

if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'registrar_staff' && $_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

try {
    $conn = get_api_conn();
    $action = $_GET['action'] ?? '';

    if ($action === 'download_template') {
        $headers = ['Student No', 'First Name', 'Last Name', 'Middle Name', 'Gender', 'DOB (YYYY-MM-DD)', 'Program ID', 'Dept ID', 'Address', 'Email'];
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="student_import_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        fputcsv($out, ['2024-001', 'John', 'Doe', 'Smith', 'Male', '2005-05-20', '1', '1', '123 Tech St', 'john@example.com']);
        fclose($out);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['file'])) {
            echo json_encode(["success" => false, "message" => "No file uploaded"]);
            exit;
        }

        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header
        fgetcsv($handle);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 2;

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 7) {
                $errors[] = ["row" => $rowNum, "msg" => "Incomplete columns"];
                $rowNum++;
                continue;
            }

            $student_no = trim($data[0]);
            $first_name = trim($data[1]);
            $last_name = trim($data[2]);
            $middle_name = trim($data[3]);
            $gender = trim($data[4]);
            $dob = trim($data[5]);
            $program_id = trim($data[6]);
            $dept_id = trim($data[7]);
            $address = trim($data[8] ?? '');
            $email = trim($data[9] ?? '');

            // Check duplicate
            $check = $conn->prepare("SELECT COUNT(*) FROM students WHERE student_no = ?");
            $check->execute([$student_no]);
            if ($check->fetchColumn() > 0) {
                $skipped++;
                $rowNum++;
                continue;
            }

            try {
                // Insert Student
                $stmt = $conn->prepare("
                    INSERT INTO students (student_no, first_name, last_name, middle_name, gender, date_of_birth, program_id, dept_id, address, contact_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$student_no, $first_name, $last_name, $middle_name, $gender, $dob, $program_id, $dept_id, $address, $email]);
                
                // Create User Account (optional, usually needed)
                $dob_password = str_replace('-', '', $dob);
                $hashed = password_hash($dob_password ? $dob_password : '123456', PASSWORD_DEFAULT);
                $uStmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'student')");
                $uStmt->execute([$student_no, $hashed]);
                $user_id = $conn->lastInsertId();
                
                $conn->prepare("UPDATE students SET user_id = ? WHERE student_no = ?")->execute([$user_id, $student_no]);

                $imported++;
            } catch (Exception $e) {
                $errors[] = ["row" => $rowNum, "msg" => $e->getMessage()];
            }
            $rowNum++;
        }
        fclose($handle);

        echo json_encode([
            "success" => true,
            "imported" => $imported,
            "skipped" => $skipped,
            "errors" => $errors
        ]);
        exit;
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
