<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Only allow root-level access if possible, or run via CLI
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['user_id']) || getCurrentUserRole() !== 'admin')) {
    die("Access Denied.");
}

$conn = getDBConnection();
echo "--- TESDA-BCAT Database Health Check ---\n";

$tables = $conn->query("SHOW TABLES");
while($row = $tables->fetch_array()) {
    $table = $row[0];
    echo "Checking table: $table ... ";
    
    $check = $conn->query("CHECK TABLE $table");
    $res = $check->fetch_assoc();
    
    if ($res['Msg_type'] === 'status' && $res['Msg_text'] === 'OK') {
        echo "[OK]\n";
    } else {
        echo "[ERROR: {$res['Msg_text']}]\n";
        echo "  Attempting REPAIR... ";
        $repair = $conn->query("REPAIR TABLE $table EXTENDED");
        $repRes = $repair->fetch_assoc();
        echo "[{$repRes['Msg_text']}]\n";
        
        echo "  Attempting OPTIMIZE... ";
        $opt = $conn->query("OPTIMIZE TABLE $table");
        $optRes = $opt->fetch_assoc();
        echo "[{$optRes['Msg_text']}]\n";
    }
}

echo "\n--- Health Check Complete ---\n";
?>
