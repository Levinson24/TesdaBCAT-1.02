<?php
$conn = new mysqli('localhost', 'root', '', 'tesda_db');

$res = $conn->query("SELECT * FROM grades ORDER BY grade_id DESC LIMIT 5");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- Enrollments ---\n";
$res2 = $conn->query("SELECT e.enrollment_id, e.student_id, e.section_id, s.dept_id as s_dept_id, s.program_id as s_program_id, c.dept_id as c_dept_id FROM enrollments e JOIN students s ON e.student_id = s.student_id JOIN class_sections cs ON e.section_id = cs.section_id JOIN courses c ON cs.course_id = c.course_id ORDER BY e.enrollment_id DESC LIMIT 5");
while ($r2 = $res2->fetch_assoc()) {
    print_r($r2);
}

echo "\n--- vw_student_grades ---\n";
$res3 = $conn->query("SELECT * FROM vw_student_grades ORDER BY student_id DESC LIMIT 5");
if (!$res3) { echo $conn->error; }
while ($r3 = $res3->fetch_assoc()) {
    print_r($r3);
}

$res4 = $conn->query("SELECT g.grade_id, g.status FROM grades g");
echo "\nTotal grades: " . $res4->num_rows . "\n";
