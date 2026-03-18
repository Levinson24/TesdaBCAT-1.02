<?php
/**
 * AJAX - Get Recent System Activities
 * Admin Module
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Only admins can access this data
if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();

// Fetch 10 most recent activities
$recentActivities = $conn->query("
    SELECT a.*, u.username 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
    LIMIT 10
");

$activities = [];
while ($activity = $recentActivities->fetch_assoc()) {
    $badgeClass = 'bg-info bg-opacity-10 text-info';
    $icon = 'fa-info-circle';
    
    $action = $activity['action'];
    if (stripos($action, 'create') !== false) {
        $badgeClass = 'bg-success bg-opacity-10 text-success';
        $icon = 'fa-plus-circle';
    } elseif (stripos($action, 'delete') !== false) {
        $badgeClass = 'bg-danger bg-opacity-10 text-danger';
        $icon = 'fa-trash-alt';
    } elseif (stripos($action, 'print') !== false || stripos($action, 'view') !== false) {
        $badgeClass = 'bg-primary bg-opacity-10 text-primary';
        $icon = 'fa-file-alt';
    } elseif (stripos($action, 'update') !== false) {
        $badgeClass = 'bg-warning bg-opacity-10 text-warning';
        $icon = 'fa-edit';
    }

    $activities[] = [
        'username' => $activity['username'] ?? 'System',
        'action' => $action,
        'badgeClass' => $badgeClass,
        'icon' => $icon,
        'details' => $activity['new_values'] ?? '-',
        'datetime' => formatDateTime($activity['created_at'])
    ];
}

echo json_encode($activities);
?>
