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
$sql = "SELECT user_id, status, last_login, last_activity, session_start FROM users";
$result = $conn->query($sql);

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

    $userData[$user['user_id']] = [
        'status' => $status,
        'isOnline' => $isOnline,
        'sessionDuration' => $sessionDuration,
        'lastLogin' => $lastLoginFormatted
    ];
}

echo json_encode($userData);
?>
