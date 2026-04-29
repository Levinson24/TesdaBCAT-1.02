<?php
require_once 'api_bootstrap.php';
check_api_auth();

$user_id = $_SESSION['user_id'];

try {
    $conn = get_api_conn();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_latest':
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$user_id]);
            $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtCount = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmtCount->execute([$user_id]);
            $unread = $stmtCount->fetchColumn();
            
            echo json_encode(["success" => true, "notifications" => $notifs, "unread_count" => $unread]);
            break;

        case 'mark_read':
            $notif_id = $_GET['id'] ?? null;
            if ($notif_id) {
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND user_id = ?");
                $stmt->execute([$notif_id, $user_id]);
            } else {
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
            echo json_encode(["success" => true]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Unknown action"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
