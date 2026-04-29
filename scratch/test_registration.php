<?php
// Simulate the registration flow to verify it works
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$conn = getDBConnection();

echo "=== TEST 1: Register Instructor ===\n";
$role = 'instructor';
$firstName = 'Juan';
$lastName = 'Dela Cruz';
$middleName = '';
$dob = '1990-05-15';
$email = 'juan@test.com';
$contactNumber = '09171234567';
$specialization = 'Information Technology';
$deptId = null;
$programId = null;

// Check if dept exists
$deptCheck = $conn->query("SELECT dept_id FROM departments LIMIT 1");
if ($deptCheck && $deptCheck->num_rows > 0) {
    $deptId = $deptCheck->fetch_assoc()['dept_id'];
}

$username = generateNextID('instructor');
$formatted_dob = date('m/d/Y', strtotime($dob));
$password = password_hash($formatted_dob, PASSWORD_DEFAULT);

echo "Generated username: $username\n";

$stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, middle_name, password, role, status, dept_id, email, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$status = 'active';
$profileImage = null;
$stmt->bind_param("sssssssiss", $username, $firstName, $lastName, $middleName, $password, $role, $status, $deptId, $email, $profileImage);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;
    echo "SUCCESS: User created with ID=$userId\n";
    
    // Also insert into instructors table
    $stmt2 = $conn->prepare("INSERT INTO instructors (user_id, instructor_id_no, first_name, last_name, middle_name, date_of_birth, dept_id, specialization, contact_number, email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt2->bind_param("isssssisss", $userId, $username, $firstName, $lastName, $middleName, $dob, $deptId, $specialization, $contactNumber, $email);
    if ($stmt2->execute()) {
        echo "SUCCESS: Instructor profile created\n";
    } else {
        echo "ERROR: Instructor profile failed: " . $conn->error . "\n";
    }
    $stmt2->close();
} else {
    echo "ERROR: " . $conn->error . "\n";
}
$stmt->close();

echo "\n=== TEST 2: Register Admin ===\n";
$role2 = 'admin';
$firstName2 = 'TestAdmin';
$lastName2 = '';
$middleName2 = '';
$username2 = 'testadmin2026';
$password2 = password_hash('admin123', PASSWORD_DEFAULT);
$email2 = '';
$deptId2 = null;
$profileImage2 = null;
$status2 = 'active';

$stmt3 = $conn->prepare("INSERT INTO users (username, first_name, last_name, middle_name, password, role, status, dept_id, email, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt3->bind_param("sssssssiss", $username2, $firstName2, $lastName2, $middleName2, $password2, $role2, $status2, $deptId2, $email2, $profileImage2);

if ($stmt3->execute()) {
    echo "SUCCESS: Admin user created with ID=" . $stmt3->insert_id . "\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
$stmt3->close();

echo "\n=== VERIFY: Display names in SELECT query ===\n";
$users = $conn->query("
    SELECT u.user_id, u.username, u.first_name, u.last_name, u.role,
           CASE 
               WHEN u.first_name IS NOT NULL AND u.first_name != '' THEN CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))
               WHEN u.role = 'student' THEN COALESCE(CONCAT(s.first_name, ' ', s.last_name), u.username)
               WHEN u.role = 'instructor' THEN COALESCE(CONCAT(i.first_name, ' ', i.last_name), u.username)
               ELSE u.username
           END as display_name
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN instructors i ON u.user_id = i.user_id
    ORDER BY u.created_at DESC
");

while ($row = $users->fetch_assoc()) {
    echo "  ID#{$row['user_id']} @{$row['username']} ({$row['role']}) -> display_name: \"{$row['display_name']}\"\n";
}
?>
