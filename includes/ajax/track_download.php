<?php
/**
 * Track Document Downloads/Prints
 * TESDA-BCAT Grade Management System
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Allow any logged in user
startSession();
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $targetId = intval($_POST['target_id'] ?? 0);
    $details = sanitizeInput($_POST['details'] ?? '');
    $userId = getCurrentUserId();

    // Validate action format to prevent log pollution
    if (!str_starts_with($action, 'DOWNLOAD_') && !str_starts_with($action, 'PRINT_') && !str_starts_with($action, 'VIEW_')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit();
    }

    // Common labels for types
    $table = 'audit_logs'; 
    if ($type === 'cor') $table = 'enrollments';
    if ($type === 'transcript') $table = 'transcripts';

    logAudit($userId, $action, $table, $targetId, null, $details);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit();
}

header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>
