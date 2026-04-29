<?php
/**
 * Migration v1.05 - Create CORS Table
 * Fixes 500 error in registrar/cor_print.php
 */
require_once __DIR__ . '/../config/database.php';
$conn = getDBConnection();

$sql = "
CREATE TABLE IF NOT EXISTS cors (
    cor_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    generated_by INT(11) NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_period (school_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "SUCCESS: 'cors' table created successfully.\n";
} else {
    echo "ERROR: Could not create 'cors' table: " . $conn->error . "\n";
}
?>
