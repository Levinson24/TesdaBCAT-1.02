<?php
require_once 'api_bootstrap.php';
check_api_auth();

try {
    $conn = get_api_conn();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('academic_year', 'current_semester', 'school_name')");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        echo json_encode(["success" => true, "settings" => $settings]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($data as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Lifecycle settings synchronized."]);
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
