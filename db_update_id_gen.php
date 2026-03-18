<?php
/**
 * Database Update Script - User Management & ID Generation
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

// Add ID generation settings
$settings = [
    ['student_id_prefix', 'STU-', 'Prefix for auto-generated student numbers (e.g. STU-)'],
    ['student_id_counter', '1', 'Current counter for auto-generated student numbers'],
    ['instructor_id_prefix', 'INS-', 'Prefix for auto-generated instructor IDs'],
    ['instructor_id_counter', '1', 'Current counter for auto-generated instructor IDs']
];

foreach ($settings as $s) {
    $check = $conn->prepare("SELECT * FROM system_settings WHERE setting_key = ?");
    $check->bind_param("s", $s[0]);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $s[0], $s[1], $s[2]);
        $stmt->execute();
        $stmt->close();
    }
    $check->close();
}

echo "Database successfully updated with ID generation settings!";
?>
