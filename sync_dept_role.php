<?php
/**
 * Department Head Role Migration
 * Updates roles and links entities to dept_id
 */
require_once 'config/database.php';
$conn = getDBConnection();

echo "Starting synchronization...\n";

// 1. Update user roles ENUM
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'registrar', 'instructor', 'student', 'dept_head') NOT NULL";
if ($conn->query($sql)) {
    echo "- Role 'dept_head' added to 'users' table.\n";
}
else {
    echo "- Error updating roles: " . $conn->error . "\n";
}

// 2. Add dept_id to users, students, and instructors
$tables = ['users', 'students', 'instructors'];
foreach ($tables as $table) {
    $check_col = $conn->query("SHOW COLUMNS FROM $table LIKE 'dept_id'");
    if ($check_col->num_rows == 0) {
        $sql = "ALTER TABLE $table ADD COLUMN dept_id INT AFTER " . ($table == 'users' ? 'role' : 'department');
        if ($conn->query($sql)) {
            echo "- Column 'dept_id' added to '$table'.\n";
        }
        else {
            echo "- Error adding 'dept_id' to '$table': " . $conn->error . "\n";
        }
    }
    else {
        echo "- Column 'dept_id' already exists in '$table'.\n";
    }
}

// 3. Map existing department text to IDs
$dept_map = [];
$res = $conn->query("SELECT dept_id, title_diploma_program FROM departments");
while ($row = $res->fetch_assoc()) {
    $dept_map[$row['title_diploma_program']] = $row['dept_id'];
}

foreach (['students', 'instructors'] as $table) {
    echo "- Syncing $table department data...\n";
    $departmentColumn = $conn->query("SHOW COLUMNS FROM $table LIKE 'department'");
    if ($departmentColumn && $departmentColumn->num_rows > 0) {
        foreach ($dept_map as $name => $id) {
            $stmt = $conn->prepare("UPDATE $table SET dept_id = ? WHERE department = ?");
            $stmt->bind_param("is", $id, $name);
            $stmt->execute();
            echo "  - Map '$name' -> ID $id (" . $stmt->affected_rows . " rows)\n";
        }
    } else {
        echo "- Skipping department sync for $table because column 'department' does not exist.\n";
    }
}

// 4. Update courses if needed (already has dept_id from previous migration, but check consistency)
echo "Synchronization completed.\n";
?>
