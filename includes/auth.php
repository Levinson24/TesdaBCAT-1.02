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
        // Set secure session cookie parameters
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        session_set_cookie_params([
            'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

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
        $updateStmt = $conn->prepare("
            UPDATE users 
            SET last_login = NOW(), session_start = NOW(), last_activity = NOW(),
                failed_attempts = 0, lockout_until = NULL
            WHERE user_id = ?
        ");
        $updateStmt->bind_param("i", $user['user_id']);
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
    
    // Security Fingerprint (IP + User Agent)
    // This prevents simple bypass scripts from "creating" a session without a valid fingerprint
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['fingerprint'] = hash('sha256', $userAgent . $ip . 'TESDA_SALT_2024');
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
    if (!isLoggedIn()) {
        header("Location: $redirectUrl");
        exit();
    }

    // Verify Session Fingerprint
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $currentFingerprint = hash('sha256', $userAgent . $ip . 'TESDA_SALT_2024');

    if (!isset($_SESSION['fingerprint']) || $_SESSION['fingerprint'] !== $currentFingerprint) {
        // Potential session hijack or unauthorized bypass attempt
        $userId = $_SESSION['user_id'] ?? 0;
        logAudit($userId, 'SECURITY_VIOLATION', 'sessions', $userId, null, 'Invalid session fingerprint - potential bypass attempt blocked');
        
        session_unset();
        session_destroy();
        header("Location: $redirectUrl?error=invalid_session_security");
        exit();
    }

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
                SELECT s.*, u.username, u.status 
                FROM students s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE s.user_id = ?
            ");
            break;

        case 'instructor':
            $stmt = $conn->prepare("
                SELECT i.*, u.username, u.status 
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

    return $profile;
}

/**
 * Logout user
 */
function logout()
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

    // Redirect to login
    header("Location: index.php");
    exit();
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
?>
