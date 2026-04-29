<?php
/**
 * Registrar Curriculum Evaluation API
 * Compiles subjects, grades, and progress by Year and Semester
 */
require_once 'api_bootstrap.php';
check_api_auth();

$student_id = $_GET['student_id'] ?? null;
if (!$student_id) {
    echo json_encode(["success" => false, "message" => "Student ID required"]);
    exit;
}

try {
    $conn = get_api_conn();
    
    // Fetch student basic info
    $stStmt = $conn->prepare("
        SELECT s.*, p.program_name, d.title_diploma_program as dept_name 
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.program_id
        LEFT JOIN departments d ON s.dept_id = d.dept_id
        WHERE s.student_id = ?
    ");
    $stStmt->execute([$student_id]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(["success" => false, "message" => "Student record not found"]);
        exit;
    }

    // Fetch all grades with subject details
    $stmt = $conn->prepare("
        SELECT g.*, c.course_code, c.course_name, c.lec_hrs, c.lab_hrs, c.lec_units, c.lab_units, c.units as total_units,
               cs.semester, cs.school_year,
               CASE 
                   WHEN cs.semester = '1st' THEN 1
                   WHEN cs.semester = '2nd' THEN 2
                   ELSE 3
               END as sem_num
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        JOIN class_sections cs ON e.section_id = cs.section_id
        JOIN courses c ON cs.course_id = c.course_id
        WHERE g.student_id = ?
        ORDER BY cs.school_year ASC, sem_num ASC
    ");
    $stmt->execute([$student_id]);
    $gradeRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouping logic (simplified)
    // In a real system, we'd use a 'curriculum_subjects' table to show missing subjects too.
    // For now, we group what they have taken.
    $hierarchy = [];
    $totalUnits = 0;
    $passedUnits = 0;
    $gpaPoints = 0;
    $gpaUnits = 0;

    foreach ($gradeRecords as $row) {
        $yearKey = $row['school_year'];
        $semKey = $row['semester'];
        
        if (!isset($hierarchy[$yearKey])) $hierarchy[$yearKey] = [];
        if (!isset($hierarchy[$yearKey][$semKey])) $hierarchy[$yearKey][$semKey] = [];
        
        $hierarchy[$yearKey][$semKey][] = $row;
        
        // Stats
        $units = (float)$row['total_units'];
        $grade = (float)$row['grade'];
        
        if ($grade > 0) {
            $totalUnits += $units;
            if ($grade >= 75) $passedUnits += $units;
            
            $gpaPoints += ($grade * $units);
            $gpaUnits += $units;
        }
    }

    $evaluation = [
        "student" => $student,
        "timeline" => $hierarchy,
        "summary" => [
            "total_units_taken" => $totalUnits,
            "passed_units" => $passedUnits,
            "gpa" => $gpaUnits > 0 ? round($gpaPoints / $gpaUnits, 2) : 0,
            "curriculum_progress" => (function($conn, $student, $passedUnits) {
                if (!$student['program_id']) return 0;
                $totalUnitsRaw = $conn->prepare("SELECT SUM(units) FROM courses WHERE program_id = ? AND status = 'active'");
                $totalUnitsRaw->execute([$student['program_id']]);
                $total = (float)$totalUnitsRaw->fetchColumn();
                return $total > 0 ? round(($passedUnits / $total) * 100, 1) : 0;
            })($conn, $student, $passedUnits)
        ]
    ];

    echo json_encode(["success" => true, "evaluation" => $evaluation]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
