<?php
/**
 * Lean Bootstrap for REST APIs
 * Avoids loading heavy HTML-oriented includes
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/database.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function get_api_conn() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database Connection Failed"]);
        exit;
    }
}

function api_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function check_api_auth($allowedRoles = []) {
    api_session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit;
    }
    
    if (!empty($allowedRoles)) {
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Forbidden: Insufficient permissions"]);
            exit;
        }
    }
}

/**
 * Modern Audit Engine (PDO-Direct)
 */
function api_log_audit($conn, $action, $table, $record_id = 0, $details = "") {
    api_session_start();
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'system';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'direct';

    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $table, $record_id, $details, $ip, $agent]);
    } catch (Exception $e) {
        // Silent fail for non-blocking logs
    }
}
?>
