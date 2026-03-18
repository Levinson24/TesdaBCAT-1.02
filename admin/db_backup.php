<?php
/**
 * Database Backup Script
 * Admin-only: creates a timestamped mysqldump of tesda_db
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin', '../index.php');

$backupDir = __DIR__ . '/../exports/backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp  = date('Y-m-d_H-i-s');
$filename   = "tesda_db_backup_{$timestamp}.sql";
$filepath   = $backupDir . $filename;

// Build mysqldump command
$host     = DB_HOST;
$user     = DB_USER;
$pass     = DB_PASS;
$dbname   = DB_NAME;

// Use mysqldump (available in XAMPP)
$cmd = "mysqldump --host={$host} --user={$host} --password={$pass} {$dbname} > \"{$filepath}\" 2>&1";
// Safer: use full path for XAMPP
$mysqlDump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
if (file_exists($mysqlDump)) {
    $cmd = "\"{$mysqlDump}\" --host={$host} --user={$user} " .
           ($pass !== '' ? "--password={$pass} " : '') .
           "{$dbname} > \"{$filepath}\" 2>&1";
} else {
    $cmd = "mysqldump --host={$host} --user={$user} " .
           ($pass !== '' ? "--password={$pass} " : '') .
           "{$dbname} > \"{$filepath}\" 2>&1";
}

exec($cmd, $output, $returnCode);

if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
    logAudit(getCurrentUserId(), 'DB_BACKUP', 'system', 0, null, "Database backup created: {$filename}");

    // Stream file to browser as download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);

    // Optionally delete the temp file after sending
    // unlink($filepath);
    exit;
} else {
    // Fallback: PHP-level dump (basic, no stored procedures/triggers)
    $conn = getDBConnection();
    $sql  = "-- TesdaBCAT Database Backup\n-- Generated: {$timestamp}\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $conn->query("SHOW TABLES");
    while ($row = $tables->fetch_array()) {
        $table   = $row[0];
        $createQ = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch_assoc();
        $sql    .= "-- Table: {$table}\n";
        $sql    .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql    .= $createQ['Create Table'] . ";\n\n";

        $rows = $conn->query("SELECT * FROM `{$table}`");
        while ($dataRow = $rows->fetch_assoc()) {
            $values = array_map(function ($v) use ($conn) {
                return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
            }, $dataRow);
            $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($filepath, $sql);
    logAudit(getCurrentUserId(), 'DB_BACKUP_PHP', 'system', 0, null, "PHP-level backup created: {$filename}");

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}
?>
