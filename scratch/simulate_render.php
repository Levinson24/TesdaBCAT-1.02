<?php
// Simulate loading users.php to see errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Temporarily override production mode
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';
$_SERVER['SCRIPT_NAME'] = '/TesdaBCAT-1.02/admin/users.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Start a session like the page would
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['last_activity_time'] = time();
$_SESSION['created'] = time();

// Load dependencies
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

// Run the query
$users = $conn->query("
    SELECT u.*, 
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

echo "Query OK. Rows: " . $users->num_rows . "\n";

// Now test the table rendering logic
echo "\n--- Rendering table rows ---\n";
while ($user = $users->fetch_assoc()) {
    echo "Rendering user #{$user['user_id']}...\n";
    
    // Test the problematic comparison
    $currentUserId = getCurrentUserId();
    echo "  getCurrentUserId() = " . var_export($currentUserId, true) . " (type: " . gettype($currentUserId) . ")\n";
    echo "  user_id = " . var_export($user['user_id'], true) . " (type: " . gettype($user['user_id']) . ")\n";
    echo "  user_id !== getCurrentUserId(): " . var_export($user['user_id'] !== $currentUserId, true) . "\n";
    
    // Test timeAgo
    $lastActivity = $user['last_activity'] ?? null;
    try {
        $timeStr = !empty($lastActivity) ? timeAgo($lastActivity) : 'Long time';
        echo "  timeAgo: $timeStr\n";
    } catch (Exception $e) {
        echo "  timeAgo ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "  OK\n";
}
echo "\nAll rows rendered successfully!\n";
?>
