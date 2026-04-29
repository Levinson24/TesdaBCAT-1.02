<?php
/**
 * Registrar Structural CRUD API
 * Handles Add/Edit/Delete for Colleges, Departments, and Programs
 */
require_once 'api_bootstrap.php';
check_api_auth(['admin', 'registrar']);

try {
    $conn = get_api_conn();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        // --- COLLEGES ---
        case 'add_college':
            $stmt = $conn->prepare("INSERT INTO colleges (college_name, college_code) VALUES (?, ?)");
            $stmt->execute([$input['name'], $input['code']]);
            echo json_encode(["success" => true, "id" => $conn->lastInsertId()]);
            break;
        case 'edit_college':
            $stmt = $conn->prepare("UPDATE colleges SET college_name = ?, college_code = ?, status = ? WHERE college_id = ?");
            $stmt->execute([$input['name'], $input['code'], $input['status'], $input['id']]);
            echo json_encode(["success" => true]);
            break;
        case 'delete_college':
            $stmt = $conn->prepare("DELETE FROM colleges WHERE college_id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(["success" => true]);
            break;

        // --- DEPARTMENTS ---
        case 'add_dept':
            $stmt = $conn->prepare("INSERT INTO departments (college_id, title_diploma_program, dept_code) VALUES (?, ?, ?)");
            $stmt->execute([$input['college_id'], $input['name'], $input['code']]);
            echo json_encode(["success" => true, "id" => $conn->lastInsertId()]);
            break;
        case 'edit_dept':
            $stmt = $conn->prepare("UPDATE departments SET title_diploma_program = ?, dept_code = ?, status = ? WHERE dept_id = ?");
            $stmt->execute([$input['name'], $input['code'], $input['status'], $input['id']]);
            echo json_encode(["success" => true]);
            break;
        case 'delete_dept':
            $stmt = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(["success" => true]);
            break;

        // --- PROGRAMS ---
        case 'add_program':
            $stmt = $conn->prepare("INSERT INTO programs (dept_id, program_name, program_code) VALUES (?, ?, ?)");
            $stmt->execute([$input['dept_id'], $input['name'], $input['code']]);
            echo json_encode(["success" => true, "id" => $conn->lastInsertId()]);
            break;
        case 'edit_program':
            $stmt = $conn->prepare("UPDATE programs SET program_name = ?, program_code = ?, status = ? WHERE program_id = ?");
            $stmt->execute([$input['name'], $input['code'], $input['status'], $input['id']]);
            echo json_encode(["success" => true]);
            break;
        case 'delete_program':
            $stmt = $conn->prepare("DELETE FROM programs WHERE program_id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(["success" => true]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Structural action not found"]);
    }
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        echo json_encode(["success" => false, "message" => "Integrity Constraint: This entity has active children and cannot be deleted."]);
    } else {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}
?>
