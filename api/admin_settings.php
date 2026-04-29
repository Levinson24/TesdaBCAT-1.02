<?php
require_once 'api_bootstrap.php';
check_api_auth();

try {
    $conn = get_api_conn();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        $stmt = $conn->query("SELECT * FROM system_settings");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode(["success" => true, "settings" => $settings]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($data as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        $conn->commit();
        echo json_encode(["success" => true, "message" => "System Settings Updated"]);
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
