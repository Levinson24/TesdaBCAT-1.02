<?php
/**
 * Registrar Transcript API
 * Lists students eligible for Official Transcript of Records
 */
require_once 'api_bootstrap.php';
check_api_auth(['admin', 'registrar']);

try {
    $conn = get_api_conn();
    
    // Get students with at least 1 approved grade
    $stmt = $conn->query("
        SELECT s.student_id, s.student_no, CONCAT(s.first_name, ' ', s.last_name) as name,
               d.title_diploma_program as dept_name,
               COUNT(g.grade_id) as approved_subjects
        FROM students s
        JOIN departments d ON s.dept_id = d.dept_id
        JOIN grades g ON s.student_id = g.student_id
        WHERE g.status = 'approved'
        GROUP BY s.student_id
        ORDER BY s.last_name ASC
    ");
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true, 
        "students" => $students,
        "base_url" => "/registrar/transcript_print.php"
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
