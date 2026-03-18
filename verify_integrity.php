<?php
/**
 * Database Integrity Verification
 */
require_once 'config/database.php';
$conn = getDBConnection();

echo "Running Integrity Check...\n";

// 1. Check for Students without Dept ID
$res = $conn->query("SELECT COUNT(*) FROM students WHERE dept_id IS NULL OR dept_id = 0");
$count = $res->fetch_row()[0];
if ($count > 0) {
    echo "[FAIL] $count students are missing a Diploma Program (dept_id).\n";
}
else {
    echo "[PASS] All students have a Diploma Program assigned.\n";
}

// 2. Check for Instructors without Dept ID
$res = $conn->query("SELECT COUNT(*) FROM instructors WHERE dept_id IS NULL OR dept_id = 0");
$count = $res->fetch_row()[0];
if ($count > 0) {
    echo "[FAIL] $count instructors are missing a Diploma Program (dept_id).\n";
}
else {
    echo "[PASS] All instructors have a Diploma Program assigned.\n";
}

// 3. Check for Users without Dept ID (excluding admin/registrar)
$res = $conn->query("SELECT COUNT(*) FROM users WHERE role NOT IN ('admin', 'registrar') AND (dept_id IS NULL OR dept_id = 0)");
$count = $res->fetch_row()[0];
if ($count > 0) {
    echo "[FAIL] $count non-admin users are missing a Diploma Program (dept_id).\n";
}
else {
    echo "[PASS] All relevant users have a Diploma Program assigned.\n";
}

// 4. Check for Courses without Dept ID
$res = $conn->query("SELECT COUNT(*) FROM courses WHERE dept_id IS NULL OR dept_id = 0");
$count = $res->fetch_row()[0];
if ($count > 0) {
    echo "[FAIL] $count courses are missing a Diploma Program (dept_id).\n";
}
else {
    echo "[PASS] All courses have a Diploma Program assigned.\n";
}

// 5. Check for incomplete course data (Class Code / course_type)
$res = $conn->query("SELECT COUNT(*) FROM courses WHERE class_code IS NULL OR course_type IS NULL");
$count = $res->fetch_row()[0];
if ($count > 0) {
    echo "[FAIL] $count courses are missing class_code or course_type.\n";
}
else {
    echo "[PASS] All courses have class_code and course_type.\n";
}

// 6. Security Scan: Detect Unauthorized Login Bypass Scripts
echo "\nRunning Security Scan...\n";
$suspiciousFiles = [];
$safeFiles = [
    'index.php',
    'includes/auth.php',
    'auth.php', // some includes might be simple
    'config/database.php',
    'logout.php',
    'verify_integrity.php'
];

$files = glob("*.php");
// Also scan common subdirectories if needed, but root is most common for bypass scripts
foreach ($files as $file) {
    // Skip safe files
    $isSafe = false;
    foreach ($safeFiles as $safe) {
        if (basename($file) === basename($safe)) {
            $isSafe = true;
            break;
        }
    }
    if ($isSafe) continue;

    $content = file_get_contents($file);
    // Look for manual session assignments that mimic login
    if (preg_match('/\$_SESSION\s*\[\s*[\'"]user_id[\'"]\s*\]\s*=/', $content) || 
        preg_match('/\$_SESSION\s*\[\s*[\'"]logged_in[\'"]\s*\]\s*=/', $content)) {
        $suspiciousFiles[] = $file;
    }
}

if (!empty($suspiciousFiles)) {
    echo "[BLOCK] " . count($suspiciousFiles) . " suspicious login bypass script(s) detected!\n";
    foreach ($suspiciousFiles as $sf) {
        echo "      -> $sf\n";
    }
    echo "[WARNING] These files should be deleted immediately as they may allow unauthorized access.\n";
} else {
    echo "[PASS] No unauthorized login bypass scripts detected in root directory.\n";
}

echo "\nVerification Finished.\n";
