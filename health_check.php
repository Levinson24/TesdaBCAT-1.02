<?php
require_once 'config/database.php';

echo "<h1>System Health Check</h1>";

// 1. PHP Version
echo "<h2>1. PHP Environment</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
$extensions = ['mysqli', 'pdo', 'gd', 'mbstring', 'curl'];
foreach ($extensions as $ext) {
    echo "Extension '$ext': " . (extension_loaded($ext) ? "✅ Loaded" : "❌ MISSING") . "<br>";
}

// 2. Database Connection
echo "<h2>2. Database Connectivity</h2>";
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$name = DB_NAME;

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    echo "❌ MySQL Server Connection Failed: " . $conn->connect_error . "<br>";
} else {
    echo "✅ MySQL Server Connection: OK<br>";
    if ($conn->select_db($name)) {
        echo "✅ Database '$name': Found<br>";
        
        // Check core tables
        $tables = ['users', 'students', 'instructors', 'courses', 'enrollments', 'grades'];
        echo "<h3>Checking Tables:</h3>";
        foreach ($tables as $table) {
            $res = $conn->query("SHOW TABLES LIKE '$table'");
            if ($res->num_rows > 0) {
                echo "- Table '$table': ✅ Exists<br>";
            } else {
                echo "- Table '$table': ❌ MISSING<br>";
            }
        }
    } else {
        echo "❌ Database '$name': NOT FOUND<br>";
        echo "<i>Suggestion: Import database_schema.sql into your MySQL server.</i>";
    }
}

// 3. File Permissions
echo "<h2>3. Folder Permissions</h2>";
$folders = ['uploads', 'exports', 'logs'];
foreach ($folders as $folder) {
    if (is_writable($folder)) {
        echo "- Folder '$folder': ✅ Writable<br>";
    } else {
        echo "- Folder '$folder': ❌ NOT WRITABLE<br>";
    }
}
?>
