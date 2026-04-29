<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Override header to not actually include HTML or exit
function requireRole() {}
function getCurrentUserId() { return 1; }
function csrfField() {}
function getSetting($k, $d) { return $d; }
function timeAgo($d) { return "1 min ago"; }

ob_start();
try {
    include 'admin/users.php';
} catch (Throwable $e) {
    echo "\n\n=== FATAL ERROR ===\n" . $e->getMessage() . " on line " . $e->getLine();
}
$output = ob_get_clean();

// Find where it stops
$tbody_pos = strpos($output, '<tbody>');
if ($tbody_pos !== false) {
    echo "Output after <tbody>:\n";
    echo substr($output, $tbody_pos, 2000);
} else {
    echo "Could not find <tbody>. Full output length: " . strlen($output);
}
?>
