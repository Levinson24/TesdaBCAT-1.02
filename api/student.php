<?php
require_once 'api_bootstrap.php';
check_api_auth();
$user_id = $_SESSION['user_id'];

try {
    $conn = get_api_conn();
    $action = $_GET['action'] ?? '';

    if ($action === 'get_grades') {
        $stmt = $conn->prepare("SELECT course_code, course_name, units, midterm, final, grade, remarks, school_year, semester FROM vw_student_grades WHERE student_id = (SELECT student_id FROM students WHERE user_id = ?) ORDER BY school_year DESC, semester DESC");
        $stmt->execute([$user_id]);
        echo json_encode(["success" => true, "grades" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } elseif ($action === 'get_profile') {
        $stmt = $conn->prepare("SELECT student_id, student_no, first_name, last_name FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode(["success" => true, "profile" => $stmt->fetch(PDO::FETCH_ASSOC)]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
