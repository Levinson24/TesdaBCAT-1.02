<?php
/**
 * Master Search API 
 * Performs cross-table searching for Students, Instructors, and Sections
 */
header("Content-Type: application/json");
require_once '../config/database.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $_GET['q'] ?? '';
    $term = "%$query%";

    // 1. Search Students
    $stmtS = $conn->prepare("SELECT student_id as id, CONCAT(first_name, ' ', last_name) as name, student_no as no FROM students WHERE first_name LIKE ? OR last_name LIKE ? OR student_no LIKE ? LIMIT 5");
    $stmtS->execute([$term, $term, $term]);
    $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    // 2. Search Instructors
    $stmtI = $conn->prepare("
        SELECT i.instructor_id as id, CONCAT(i.first_name, ' ', i.last_name) as name, d.title_diploma_program as dept 
        FROM instructors i
        LEFT JOIN departments d ON i.dept_id = d.dept_id
        WHERE i.first_name LIKE ? OR i.last_name LIKE ? OR d.title_diploma_program LIKE ? 
        LIMIT 5
    ");
    $stmtI->execute([$term, $term, $term]);
    $instructors = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // 3. Search Sections
    $stmtX = $conn->prepare("SELECT section_id as id, section_name as name, school_year as course FROM class_sections WHERE section_name LIKE ? LIMIT 5");
    $stmtX->execute([$term]);
    $sections = $stmtX->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "results" => [
            "students" => $students,
            "instructors" => $instructors,
            "sections" => $sections
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
