<?php
// Force error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
$conn = getDBConnection();

echo "=== DATABASE: " . DB_NAME . " ===\n\n";

// 1. Check users table structure
echo "--- USERS TABLE COLUMNS ---\n";
$cols = $conn->query("SHOW COLUMNS FROM users");
if ($cols) {
    while ($c = $cols->fetch_assoc()) {
        echo "  {$c['Field']} ({$c['Type']}) Null={$c['Null']} Default={$c['Default']}\n";
    }
} else {
    echo "  ERROR: " . $conn->error . "\n";
}

// 2. Check all users
echo "\n--- ALL USERS ---\n";
$r = $conn->query("SELECT user_id, username, first_name, last_name, role, status FROM users ORDER BY user_id");
if ($r) {
    echo "  Total: " . $r->num_rows . "\n";
    while ($row = $r->fetch_assoc()) {
        echo "  ID#{$row['user_id']} | @{$row['username']} | {$row['first_name']} {$row['last_name']} | role={$row['role']} | status={$row['status']}\n";
    }
} else {
    echo "  ERROR: " . $conn->error . "\n";
}

// 3. Test the EXACT query from users.php
echo "\n--- TESTING DISPLAY QUERY ---\n";
$test = $conn->query("
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

if ($test) {
    echo "  SUCCESS! Rows: " . $test->num_rows . "\n";
    while ($row = $test->fetch_assoc()) {
        echo "  ID#{$row['user_id']} display=\"{$row['display_name']}\" role={$row['role']}\n";
    }
} else {
    echo "  QUERY FAILED: " . $conn->error . "\n";
}

// 4. Check related tables
echo "\n--- TABLE CHECK ---\n";
$tables = ['users', 'students', 'instructors', 'departments', 'audit_logs'];
foreach ($tables as $t) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM $t");
    if ($r) {
        $cnt = $r->fetch_assoc()['cnt'];
        echo "  $t: $cnt rows\n";
    } else {
        echo "  $t: ERROR - " . $conn->error . "\n";
    }
}

// 5. Check if first_name column exists
echo "\n--- COLUMN CHECK ---\n";
$check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'first_name'");
if ($check && $check->num_rows > 0) {
    echo "  first_name column EXISTS in users table\n";
} else {
    echo "  first_name column MISSING from users table!\n";
}
?>
