<?php
/**
 * Registrar Hierarchy API
 * Fetches the nested structure of Colleges -> Departments -> Programs
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once '../config/database.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Get all Colleges
    $stmtC = $conn->query("SELECT college_id, college_name, college_code, status FROM colleges ORDER BY college_name ASC");
    $colleges = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get all Departments
    $stmtD = $conn->query("SELECT dept_id, college_id, title_diploma_program as dept_name, dept_code, status FROM departments ORDER BY title_diploma_program ASC");
    $departments = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get all Programs
    $stmtP = $conn->query("SELECT program_id, dept_id, program_name, program_code, status FROM programs ORDER BY program_name ASC");
    $programs = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Nesting logic
    $res = [];
    foreach ($colleges as $c) {
        $c['departments'] = [];
        foreach ($departments as $d) {
            if ($d['college_id'] == $c['college_id']) {
                $d['programs'] = [];
                foreach ($programs as $p) {
                    if ($p['dept_id'] == $d['dept_id']) {
                        $d['programs'][] = $p;
                    }
                }
                $c['departments'][] = $d;
            }
        }
        $res[] = $c;
    }

    echo json_encode([
        "success" => true,
        "hierarchy" => $res,
        "metadata" => [
            "total_colleges" => count($colleges),
            "total_departments" => count($departments),
            "total_programs" => count($programs)
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
