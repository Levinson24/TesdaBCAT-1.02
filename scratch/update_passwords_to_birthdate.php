<?php
require_once 'config/database.php';
$conn = getDBConnection();

$admin_default_password = 'admin'; // Typical default
$admin_hashed = password_hash($admin_default_password, PASSWORD_DEFAULT);

// 1. Update Admins
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE role = 'admin'");
$stmt->bind_param("s", $admin_hashed);
$stmt->execute();
echo "Admins updated to default password 'admin'.\n";

// 2. Update Students
$res = $conn->query("SELECT user_id, date_of_birth FROM students WHERE user_id IS NOT NULL");
$student_count = 0;
while ($student = $res->fetch_assoc()) {
    if (!empty($student['date_of_birth'])) {
        $dob_password = str_replace('-', '', $student['date_of_birth']);
        $hashed = password_hash($dob_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upd->bind_param("si", $hashed, $student['user_id']);
        $upd->execute();
        $student_count++;
    }
}
echo "Updated $student_count student passwords to their birthdate (YYYYMMDD format).\n";

// 3. Update Instructors
$res = $conn->query("SELECT user_id, date_of_birth FROM instructors WHERE user_id IS NOT NULL");
$instructor_count = 0;
while ($instructor = $res->fetch_assoc()) {
    if (!empty($instructor['date_of_birth'])) {
        $dob_password = str_replace('-', '', $instructor['date_of_birth']);
        $hashed = password_hash($dob_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upd->bind_param("si", $hashed, $instructor['user_id']);
        $upd->execute();
        $instructor_count++;
    }
}
echo "Updated $instructor_count instructor passwords to their birthdate (YYYYMMDD format).\n";

// 4. Update other roles
$other_roles = ['registrar', 'registrar_staff', 'dept_head'];
foreach ($other_roles as $role) {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE role = ? AND user_id NOT IN (SELECT user_id FROM students WHERE user_id IS NOT NULL) AND user_id NOT IN (SELECT user_id FROM instructors WHERE user_id IS NOT NULL)");
    $stmt->bind_param("ss", $admin_hashed, $role);
    $stmt->execute();
    echo "Updated $role passwords to default 'admin' if they had no birthdate.\n";
}

echo "Done.\n";
?>
