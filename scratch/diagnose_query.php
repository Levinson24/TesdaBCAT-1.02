<?php
require_once 'config/database.php';
$conn = getDBConnection();

// 1. What users exist?
echo "=== USERS IN DATABASE ===\n";
$r = $conn->query("SELECT user_id, username, first_name, last_name, role FROM users");
if ($r) {
    echo "Count: " . $r->num_rows . "\n";
    while ($row = $r->fetch_assoc()) {
        echo "  ID#{$row['user_id']} @{$row['username']} ({$row['role']}) - {$row['first_name']} {$row['last_name']}\n";
    }
} else {
    echo "ERROR on simple query: " . $conn->error . "\n";
}

// 2. Test the exact query from users.php
echo "\n=== TESTING FULL DISPLAY QUERY ===\n";
$users = $conn->query("
    SELECT u.*, 
           CASE 
               WHEN u.first_name IS NOT NULL AND u.first_name != '' THEN CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))
               WHEN u.role = 'student' THEN COALESCE(CONCAT(s.first_name, ' ', s.last_name), u.username)
               WHEN u.role = 'instructor' THEN COALESCE(CONCAT(i.first_name, ' ', i.last_name), u.username)
               ELSE u.username
           END as display_name,
           d.title_diploma_program as dept_name,
           s.program_id,
           (SELECT al.action FROM audit_logs al 
            WHERE al.user_id = u.user_id 
            AND (al.action LIKE 'PRINT_%' OR al.action LIKE 'DOWNLOAD_%' OR al.action = 'VIEW_COR')
            ORDER BY al.log_id DESC LIMIT 1) as last_doc_action,
           (SELECT al.created_at FROM audit_logs al 
            WHERE al.user_id = u.user_id 
            AND (al.action LIKE 'PRINT_%' OR al.action LIKE 'DOWNLOAD_%' OR al.action = 'VIEW_COR')
            ORDER BY al.log_id DESC LIMIT 1) as last_doc_time
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN instructors i ON u.user_id = i.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.created_at DESC
    LIMIT 500
");

if ($users) {
    echo "Query SUCCESS. Rows: " . $users->num_rows . "\n";
    while ($row = $users->fetch_assoc()) {
        echo "  ID#{$row['user_id']} display_name=\"{$row['display_name']}\" role={$row['role']}\n";
    }
} else {
    echo "Query FAILED: " . $conn->error . "\n";
}

// 3. Check columns
echo "\n=== USERS TABLE COLUMNS ===\n";
$cols = $conn->query("SHOW COLUMNS FROM users");
while ($c = $cols->fetch_assoc()) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}
?>
