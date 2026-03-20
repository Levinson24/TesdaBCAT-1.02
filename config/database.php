<?php
/**
 * Database Configuration File
 * TESDA-BCAT Grade Management System
 */

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tesda_db');
define('DB_CHARSET', 'utf8mb4');

// Environment settings
define('ENVIRONMENT', 'production'); // Change to 'production' on live server

// Application settings
define('APP_NAME', 'TESDA-BCAT GMS');
define('APP_VERSION', '1.0.2');

// Dynamic BASE_URL detection
if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Calculate base path based on the location of this file (config/database.php)
    $project_root = str_replace('\\', '/', dirname(__DIR__));
    $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    
    // The part of project_root that follows doc_root is our subfolder path
    $sub_folder = str_replace($doc_root, '', $project_root);
    $base_path = '/' . trim($sub_folder, '/') . '/';
    // If running from root, trim could make it empty, so ensure at least a slash
    if ($base_path === '//') $base_path = '/';
    
    define('BASE_URL', $protocol . "://" . $host . $base_path);
}

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// Pagination
define('ITEMS_PER_PAGE', 20);

// File upload settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx']);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/logs/error.log');
}
else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

/**
 * Create database connection
 * @return mysqli|false
 */
function getDBConnection()
{
    static $conn = null;

    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset(DB_CHARSET);
        }
        catch (Exception $e) {
            // Production-safe error message
            if (ENVIRONMENT === 'production') {
                die("The system is currently experiencing technical difficulties. Please try again later.");
            }
            else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }

    return $conn;
}

/**
 * Close database connection
 */
function closeDBConnection()
{
    $conn = getDBConnection();
    if ($conn) {
        $conn->close();
    }
}
?>
