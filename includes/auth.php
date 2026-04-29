<?php
/**
 * Authentication and Session Management
 * TESDA-BCAT Grade Management System
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Start secure session
 */
function startSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        // Support Cloudflare/Proxy HTTPS detection for cookie security
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || 
                    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        // Set session cookie parameters for high-compatibility
        session_set_cookie_params([
            'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => $isHttps ? 'None' : 'Lax' // Correct: 'None' for Tunnels, 'Lax' for local
        ]);

        // Production Security Headers
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');

        session_start();

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        }
        else if (time() - $_SESSION['created'] > (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600)) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }

        // Initialize CSRF token if not set
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

/**
 * Get CSRF token
 * @return string
 */
function getCSRFToken()
{
    startSession();
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token)
{
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper to output CSRF hidden input
 */
function csrfField()
{
    echo '<input type="hidden" name="csrf_token" value="' . getCSRFToken() . '">';
}

/**
 * Authenticate user login
 * @param string $username
 * @param string $password
 * @return array|string User data on success, error string on failure
 */
function authenticateUser($username, $password)
{
    $conn = getDBConnection();

    // Include lockout columns if they exist
    $stmt = $conn->prepare("
        SELECT user_id, username, password, role, status,
               COALESCE(failed_attempts, 0) AS failed_attempts,
               lockout_until
        FROM users WHERE username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        return 'invalid_credentials';
    }

    $user = $result->fetch_assoc();

    // Check account status
    if ($user['status'] !== 'active') {
        return 'account_inactive';
    }

    // Check brute-force lockout
    if (!empty($user['lockout_until']) && strtotime($user['lockout_until']) > time()) {
        $remaining = ceil((strtotime($user['lockout_until']) - time()) / 60);
        return 'locked:' . $remaining;
    }

    // Verify password
    if (password_verify($password, $user['password'])) {
        // Reset failed attempts and update login timestamps
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $updateStmt = $conn->prepare("
            UPDATE users 
            SET last_login = NOW(), session_start = NOW(), last_activity = NOW(),
                failed_attempts = 0, lockout_until = NULL, last_ip = ?
            WHERE user_id = ?
        ");
        $updateStmt->bind_param("si", $ip, $user['user_id']);
        $updateStmt->execute();
        $updateStmt->close();

        // Log successful login
        logAudit($user['user_id'], 'LOGIN', 'users', $user['user_id'], null, 'Successful login');

        return $user;
    }

    // Wrong password — increment failed attempts
    $maxAttempts  = 5;
    $lockoutMins  = 15;
    $newAttempts  = ($user['failed_attempts'] ?? 0) + 1;
    $lockoutUntil = null;

    if ($newAttempts >= $maxAttempts) {
        $lockoutUntil = date('Y-m-d H:i:s', time() + ($lockoutMins * 60));
        logAudit($user['user_id'], 'ACCOUNT_LOCKED', 'users', $user['user_id'], null,
            "Account locked after {$maxAttempts} failed attempts");
    } else {
        logAudit($user['user_id'], 'FAILED_LOGIN', 'users', $user['user_id'], null,
            "Failed login attempt {$newAttempts}/{$maxAttempts}");
    }

    $failStmt = $conn->prepare("
        UPDATE users SET failed_attempts = ?, lockout_until = ? WHERE user_id = ?
    ");
    $failStmt->bind_param("isi", $newAttempts, $lockoutUntil, $user['user_id']);
    $failStmt->execute();
    $failStmt->close();

    if ($lockoutUntil) {
        return 'locked:' . $lockoutMins;
    }

    $attemptsLeft = $maxAttempts - $newAttempts;
    return 'invalid_credentials:' . $attemptsLeft;
}

/**
 * Create user session
 * @param array $user User data
 */
function createUserSession($user)
{
    startSession();
    // Regenerate session ID on login to prevent session fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity_time'] = time();
    
    // Security Fingerprint (Flexible for mobile roaming)
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Store UA and IP separately to allow "Soft Matching"
    $_SESSION['ua_fingerprint'] = hash('sha256', $userAgent . 'TESDA_SECRET_UA_2024');
    $_SESSION['ip_fingerprint'] = $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn()
{
    startSession();
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user has specific role
 * @param string|array $allowedRoles
 * @return bool
 */
function hasRole($allowedRoles)
{
    startSession();

    if (!isLoggedIn()) {
        return false;
    }

    $userRole = $_SESSION['role'];

    if (is_array($allowedRoles)) {
        return in_array($userRole, $allowedRoles);
    }

    return $userRole === $allowedRoles;
}

/**
 * Require login - redirect if not authenticated
 * @param string $redirectUrl
 */
function requireLogin($redirectUrl = '../index.php')
{
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (!isLoggedIn()) {
        if ($isAjax) {
            header('HTTP/1.1 403 Forbidden');
            $debugMsg = !isset($_COOKIE[session_name()]) ? "Cookie missing" : "Session data lost";
            echo json_encode(['success' => false, 'message' => "Session expired ($debugMsg). Please refresh."]);
            exit();
        }
        header("Location: $redirectUrl");
        exit();
    }

    // 60-Minute Inactivity Timeout Check (Increased for Mobile Stability)
    $timeout_duration = 3600; // 3600 seconds = 60 minutes
    if (isset($_SESSION['last_activity_time']) && (time() - $_SESSION['last_activity_time']) > $timeout_duration) {
        $userId = $_SESSION['user_id'] ?? 0;
        logAudit($userId, 'TIMEOUT', 'sessions', $userId, null, 'User session timed out due to 10 minutes of inactivity');
        
        // Update database to explicitly clear active session footprint
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET session_start = NULL, last_activity = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        session_unset();
        session_destroy();
        
        header("Location: $redirectUrl?error=timeout");
        exit();
    }
    
    // Refresh last activity for current request
    $_SESSION['last_activity_time'] = time();

    // Security Fingerprint (Bypassed for High Mobile Compatibility)
    /*
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $currentUAFingerprint = hash('sha256', $userAgent . 'TESDA_SECRET_UA_2024');
    
    // We strictly verify the User Agent, but we allow for minor shifts common in mobile viewports/pickers
    if (!isset($_SESSION['ua_fingerprint']) || $_SESSION['ua_fingerprint'] !== $currentUAFingerprint) {
        // Log the change for debugging but don't kill the session if it's very recent activity
        $isRecent = (time() - ($_SESSION['last_activity_time'] ?? 0)) < 300; // 5 minutes grace
        
        if ($isRecent) {
            // Soft match: If UA changed slightly but session is very fresh, just update the fingerprint
            $_SESSION['ua_fingerprint'] = $currentUAFingerprint;
            $_SESSION['ip_fingerprint'] = $_SERVER['REMOTE_ADDR'] ?? '';
        } else {
            $userId = $_SESSION['user_id'] ?? 0;
            logAudit($userId, 'SECURITY_RENEWAL_REQUIRED', 'sessions', $userId, null, 'Session fingerprint mismatch - renewal required');
            
            if ($isAjax) {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['success' => false, 'message' => 'Security state changed. Please refresh the page.']);
                exit();
            }
            
            session_unset();
            session_destroy();
            header("Location: $redirectUrl?error=session_renewal");
            exit();
        }
    }
    */

    // Verify user still exists and is active in the database
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0 || $result->fetch_assoc()['status'] !== 'active') {
        $stmt->close();
        // Force logout if user was deleted or deactivated
        session_unset();
        session_destroy();
        header("Location: $redirectUrl?error=invalid_session");
        exit();
    }
    $stmt->close();

    // Continuously update last_activity footprint
    $userId = $_SESSION['user_id'];
    // Avoid hammering the DB too often; update every 2 minutes minimum
    if (!isset($_SESSION['last_activity_push']) || (time() - $_SESSION['last_activity_push'] > 120)) {
        // Fix: Also populate session_start if it is NULL for this active user
        $updateActivity = $conn->prepare("UPDATE users SET last_activity = NOW(), session_start = COALESCE(session_start, NOW()) WHERE user_id = ?");
        $updateActivity->bind_param("i", $userId);
        $updateActivity->execute();
        $updateActivity->close();
        
        $_SESSION['last_activity_push'] = time();
    }
}

/**
 * Require specific role - redirect if unauthorized
 * @param string|array $allowedRoles
 * @param string $redirectUrl
 */
function requireRole($allowedRoles, $redirectUrl = '../index.php')
{
    requireLogin($redirectUrl);

    if (!hasRole($allowedRoles)) {
        header("Location: $redirectUrl?error=unauthorized");
        exit();
    }
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId()
{
    startSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 * @return string|null
 */
function getCurrentUserRole()
{
    startSession();
    return $_SESSION['role'] ?? null;
}

/**
 * Get user profile data based on role
 * @param int $userId
 * @param string $role
 * @return array|null
 */
function getUserProfile($userId, $role)
{
    $conn = getDBConnection();

    switch ($role) {
        case 'student':
            $stmt = $conn->prepare("
                SELECT s.*, u.username, u.status, u.profile_image 
                FROM students s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE s.user_id = ?
            ");
            break;

        case 'instructor':
            $stmt = $conn->prepare("
                SELECT i.*, u.username, u.status, u.profile_image 
                FROM instructors i 
                JOIN users u ON i.user_id = u.user_id 
                WHERE i.user_id = ?
            ");
            break;

        case 'dept_head':
        case 'registrar':
        case 'registrar_staff':
            $stmt = $conn->prepare("
                SELECT u.*, d.title_diploma_program as dept_name 
                FROM users u 
                LEFT JOIN departments d ON u.dept_id = d.dept_id 
                WHERE u.user_id = ?
            ");
            break;

        default:
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            break;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();

    // Global Activity Heartbeat: Update last access timestamp on every page load
    if ($profile) {
        $conn->query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = " . intval($userId));
    }

    return $profile;
}

/**
 * Logout user
 * @param bool $redirect Whether to automatically redirect to index.php
 */
function logout($redirect = true)
{
    startSession();

    // Log logout action
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET session_start = NULL, last_activity = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        
        logAudit($userId, 'LOGOUT', 'users', $userId, null, 'User logged out');
    }

    // Destroy session
    session_unset();
    session_destroy();

    // Optional Redirect
    if ($redirect) {
        header("Location: index.php");
        exit();
    }
}

/**
 * Hash password
 * @param string $password
 * @return string
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Generate random password
 * @param int $length
 * @return string
 */
function generateRandomPassword($length = 10)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    $max = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }

    return $password;
}

/**
 * Log audit trail
 * @param int $userId
 * @param string $action
 * @param string $tableName
 * @param int $recordId
 * @param string $oldValues
 * @param string $newValues
 */
function logAudit($userId, $action, $tableName, $recordId, $oldValues = null, $newValues = null)
{
    $conn = getDBConnection();

    // Verify user exists to prevent foreign key errors (if user was deleted)
    if ($userId !== null) {
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $userId = null;
        }
        $checkStmt->close();
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Types: int, string, string, int, string, string, string, string
        $stmt->bind_param("ississss", $userId, $action, $tableName, $recordId, $oldValues, $newValues, $ipAddress, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
    catch (Exception $e) {
    // Silently fail audit logging on error rather than crashing the application
    }
}

/**
 * Maintenance Utility: Cleanup old audit logs
 * Keeps the system snappy by rotating logs older than 90 days
 */
function cleanupAuditLogs($days = 90) {
    echo "Starting audit log cleanup (Retention: $days days)...";
    $conn = getDBConnection();
    $days = intval($days);
    $stmt = $conn->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}
?>
