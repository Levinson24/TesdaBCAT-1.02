<?php
/**
 * AJAX Processor for Bulk Student Import
 * TESDA-BCAT Grade Management System
 */
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Use composer autoloader for PhpSpreadsheet
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

startSession();
if (!isLoggedIn() || !hasRole(['registrar', 'registrar_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['import_file'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$conn = getDBConnection();
$file = $_FILES['import_file'];
$defaultDeptId = $_POST['default_dept_id'] ?? null;
$defaultProgramId = $_POST['default_program_id'] ?? null;

// Results tracking
$counts = [
    'success' => true,
    'imported' => 0,
    'skipped' => 0,
    'errors' => []
];

try {
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();
    
    // Assume first row is header
    $headers = array_shift($rows);
    
    foreach ($rows as $index => $row) {
        $currentRow = $index + 2; // 1-indexed, +1 for header
        
        // Basic columns: Student No, First Name, Last Name, Middle Name, Gender, DOB
        $studentNo = sanitizeInput($row[0] ?? '');
        $firstName = sanitizeInput($row[1] ?? '');
        $lastName = sanitizeInput($row[2] ?? '');
        $middleName = sanitizeInput($row[3] ?? '');
        $gender = sanitizeInput($row[4] ?? '');
        $rawDob = sanitizeInput($row[5] ?? '');
        
        // Auto-generate ID if empty
        if (empty($studentNo)) {
            $studentNo = generateNextID('student');
        }

        // Validate required fields
        if (empty($firstName) || empty($lastName)) {
            $counts['errors'][] = ['row' => $currentRow, 'msg' => 'First Name or Last Name is missing'];
            continue;
        }

        // Handle Date (MM/DD/YYYY or YYYY-MM-DD)
        $dob = null;
        if (!empty($rawDob)) {
            $time = strtotime($rawDob);
            if ($time) {
                $dob = date('Y-m-d', $time);
                $formatted_dob = date('m/d/Y', $time);
            }
        }
        
        if (!$dob) {
            $counts['errors'][] = ['row' => $currentRow, 'msg' => 'Invalid or missing Date of Birth'];
            continue;
        }

        // Check for duplicate username/student_no
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check->bind_param("s", $studentNo);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $counts['skipped']++;
            continue;
        }
        $check->close();

        // 1. Create User Account
        $password = hashPassword($formatted_dob);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'student', 'active')");
        $stmt->bind_param("ss", $studentNo, $password);
        
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            
            // 2. Create Student Profile
            $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_no, first_name, last_name, middle_name, date_of_birth, gender, dept_id, program_id, year_level, enrollment_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE(), 'active')");
            $stmt2->bind_param("issssssii", $userId, $studentNo, $firstName, $lastName, $middleName, $dob, $gender, $defaultDeptId, $defaultProgramId);
            
            if ($stmt2->execute()) {
                $counts['imported']++;
                logAudit(getCurrentUserId(), 'CREATE', 'users', $userId, null, "Bulk Imported student: $studentNo");
            } else {
                $counts['errors'][] = ['row' => $currentRow, 'msg' => 'Database error creating student profile'];
            }
        } else {
            $counts['errors'][] = ['row' => $currentRow, 'msg' => 'Database error creating user account'];
        }
    }

    echo json_encode($counts);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
exit();
