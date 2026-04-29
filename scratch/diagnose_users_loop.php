<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
$conn = getDBConnection();

try {
    $users = $conn->query("
        SELECT u.user_id, u.username, u.first_name, u.last_name, u.middle_name, 
               u.role, u.email, u.profile_image, u.dept_id, u.status,
               u.reset_requested, u.last_activity, u.last_ip, u.created_at, u.last_login,
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
    
    if (!$users) {
        echo "QUERY ERROR: " . $conn->error . "\n";
        exit;
    }
    
    echo "Query successful. Num rows: " . $users->num_rows . "\n";
    
    while ($user = $users->fetch_assoc()) {
        echo "Fetched user ID: " . $user['user_id'] . "\n";
    }
    
    echo "Loop finished.\n";
} catch (Exception $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "\n";
}
?>
