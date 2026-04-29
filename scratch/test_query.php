<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
$conn = getDBConnection();

// Check audit_logs structure
echo "--- AUDIT_LOGS COLUMNS ---\n";
$cols = $conn->query("SHOW COLUMNS FROM audit_logs");
while ($c = $cols->fetch_assoc()) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}

// Check students structure  
echo "\n--- STUDENTS COLUMNS ---\n";
$cols = $conn->query("SHOW COLUMNS FROM students");
while ($c = $cols->fetch_assoc()) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}

// Check instructors structure
echo "\n--- INSTRUCTORS COLUMNS ---\n";
$cols = $conn->query("SHOW COLUMNS FROM instructors");
while ($c = $cols->fetch_assoc()) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}

// Try the query with error mode
echo "\n--- TEST QUERY ---\n";
try {
    $result = $conn->query("
        SELECT u.user_id, u.username, u.first_name, u.last_name, u.role, u.status,
               u.profile_image, u.dept_id, u.reset_requested, u.last_activity, u.last_ip, u.created_at,
               CASE 
                   WHEN u.first_name IS NOT NULL AND u.first_name != '' THEN CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))
                   WHEN u.role = 'student' THEN COALESCE(CONCAT(s.first_name, ' ', s.last_name), u.username)
                   WHEN u.role = 'instructor' THEN COALESCE(CONCAT(i.first_name, ' ', i.last_name), u.username)
                   ELSE u.username
               END as display_name,
               d.title_diploma_program as dept_name,
               s.program_id
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id
        LEFT JOIN instructors i ON u.user_id = i.user_id
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        ORDER BY u.created_at DESC
        LIMIT 500
    ");
    echo "SUCCESS! Rows: " . $result->num_rows . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "  #{$row['user_id']} {$row['display_name']} ({$row['role']})\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
