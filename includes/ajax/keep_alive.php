<?php
/**
 * Keep-Alive Endpoint — Resets session activity timer
 * Called by the session timeout warning modal via AJAX
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

startSession();

if (!isLoggedIn()) {
    echo json_encode(['status' => 'expired']);
    exit();
}

// Touch session to reset timer
$_SESSION['last_activity_push'] = 0; // Force re-push on next request
$userId = getCurrentUserId();

if ($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['status' => 'ok', 'time' => time()]);
?>
