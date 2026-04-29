<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
$c = getDBConnection();
$r = $c->query("SELECT * FROM system_settings WHERE setting_key LIKE '%id_%'");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo $row['setting_key'] . ' = ' . $row['setting_value'] . "\n";
    }
} else {
    echo "No ID settings found. Checking table...\n";
    $t = $c->query("SHOW TABLES LIKE 'system_settings'");
    echo "Table exists: " . ($t->num_rows > 0 ? 'YES' : 'NO') . "\n";
    if ($t->num_rows > 0) {
        $all = $c->query("SELECT * FROM system_settings LIMIT 10");
        while ($row = $all->fetch_assoc()) {
            echo "  " . $row['setting_key'] . ' = ' . $row['setting_value'] . "\n";
        }
    }
}
?>
