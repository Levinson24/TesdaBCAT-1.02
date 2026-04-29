<?php
/**
 * AJAX - Get User Online Status
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
$sql = "SELECT user_id, username, role, status, last_login, last_activity, session_start, last_ip FROM users";
$result = $conn->query($sql);

// Fetch last document actions for all users
$docActions = [];
$docSql = "
    SELECT al.user_id, al.action, al.created_at 
    FROM audit_logs al
    INNER JOIN (
        SELECT user_id, MAX(log_id) as max_id 
        FROM audit_logs 
        WHERE action LIKE 'PRINT_%' OR action LIKE 'DOWNLOAD_%' OR action = 'VIEW_COR'
        GROUP BY user_id
    ) latest ON al.log_id = latest.max_id";
$docResult = $conn->query($docSql);
while ($doc = $docResult->fetch_assoc()) {
    $docActions[$doc['user_id']] = [
        'action' => str_replace('_', ' ', $doc['action']),
        'time' => $doc['created_at']
    ];
}

$userData = [];
$currentTime = time();

while ($user = $result->fetch_assoc()) {
    $status = $user['status'] ?? 'inactive';
    $isOnline = false;
    $sessionDuration = '';
    
    if (!empty($user['last_activity'])) {
        $lastActivityTime = strtotime($user['last_activity']);
        
        // Consider online if active in the last 5 minutes (300 seconds)
        if (($currentTime - $lastActivityTime) <= 300) {
            $isOnline = true;
            
            if (!empty($user['session_start'])) {
                $sessionStartTime = strtotime($user['session_start']);
                // Calculate H:M:S duration
                $durationSecs = $currentTime - $sessionStartTime;
                $hours = floor($durationSecs / 3600);
                $minutes = floor(($durationSecs % 3600) / 60);
                $seconds = $durationSecs % 60;
                $sessionDuration = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
            }
        }
    }

    $lastLoginFormatted = !empty($user['last_login']) ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never logged in';

    $lastDoc = 'No document activity';
    if (isset($docActions[$user['user_id']])) {
        $timeDiff = $currentTime - strtotime($docActions[$user['user_id']]['time']);
        if ($timeDiff < 60) $lastDoc = $docActions[$user['user_id']]['action'] . ' just now';
        elseif ($timeDiff < 3600) $lastDoc = $docActions[$user['user_id']]['action'] . ' ' . floor($timeDiff/60) . 'm ago';
        elseif ($timeDiff < 86400) $lastDoc = $docActions[$user['user_id']]['action'] . ' ' . floor($timeDiff/3600) . 'h ago';
        else $lastDoc = $docActions[$user['user_id']]['action'] . ' on ' . date('M d', strtotime($docActions[$user['user_id']]['time']));
    }

    $userData[$user['user_id']] = [
        'username' => $user['username'],
        'role' => $user['role'],
        'status' => $status,
        'isOnline' => $isOnline,
        'sessionDuration' => $sessionDuration,
        'lastLogin' => $lastLoginFormatted,
        'lastIP' => $user['last_ip'] ?? 'N/A',
        'lastDoc' => $lastDoc
    ];
}

echo json_encode($userData);
?>
