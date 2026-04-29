<?php
/**
 * Admin - Generate Report (CSV Export)
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$conn = getDBConnection();

$type = $_GET['type'] ?? '';

if ($type === 'students') {
    $filename = "BCAT_Students_Report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student No', 'First Name', 'Last Name', 'Year Level', 'Email', 'Contact', 'Status', 'GWA']);

    $query = "
        SELECT s.student_no, s.first_name, s.last_name, s.year_level, s.email, s.contact_number, s.status,
               SUM(g.grade * subj.units) / NULLIF(SUM(subj.units), 0) as gwa
        FROM students s
        LEFT JOIN grades g ON s.student_id = g.student_id AND g.status = 'approved'
        LEFT JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        LEFT JOIN class_sections cs ON e.section_id = cs.section_id
        LEFT JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
        LEFT JOIN subjects subj ON cur.subject_id = subj.subject_id
        GROUP BY s.student_id
        ORDER BY s.last_name ASC
    ";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['student_no'], $row['first_name'], $row['last_name'],
            $row['year_level'], $row['email'], $row['contact_number'],
            ucfirst($row['status']),
            $row['gwa'] !== null ? number_format($row['gwa'], 2) : '0.00'
        ]);
    }

    fclose($output);
    exit;
}

// Fallback to reports dashboard if unknown type
redirectWithMessage('reports.php', 'Invalid report type.', 'warning');
?>
