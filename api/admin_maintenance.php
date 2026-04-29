<?php
/**
 * Admin Maintenance API
 * Handles Database Backups and System Optimizations
 */
require_once 'api_bootstrap.php';
check_api_auth();

// Ensure only admin can access
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit;
}

try {
    $conn = get_api_conn();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_backups':
            $backupDir = __DIR__ . '/../exports/backups/';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            
            $files = array_diff(scandir($backupDir), array('.', '..'));
            $backups = [];
            foreach ($files as $file) {
                if (strpos($file, '.sql') !== false) {
                    $backups[] = [
                        'filename' => $file,
                        'size' => round(filesize($backupDir . $file) / 1024, 2) . ' KB',
                        'date' => date('Y-m-d H:i:s', filemtime($backupDir . $file))
                    ];
                }
            }
            usort($backups, function($a, $b) { return $b['date'] <=> $a['date']; });
            echo json_encode(["success" => true, "backups" => $backups]);
            break;

        case 'create_backup':
            $backupDir = __DIR__ . '/../exports/backups/';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "tesda_db_manual_{$timestamp}.sql";
            $filepath = $backupDir . $filename;
            
            // For portability, we'll use a PHP-based dump (similar to the legacy fallback)
            // This is more reliable across different MySQL environments without needing mysqldump.exe path
            $sql = "-- TesdaBCAT Automated Backup\n-- Generated: {$timestamp}\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $createRes = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sql .= "-- Table: $table\n";
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $createRes['Create Table'] . ";\n\n";

                $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $values = array_map(function($v) use ($conn) {
                        return $v === null ? 'NULL' : $conn->quote($v);
                    }, $row);
                    $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($filepath, $sql);
            
            // Log to audit
            $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'DATABASE_BACKUP', "Created backup file: $filename"]);
            
            echo json_encode(["success" => true, "filename" => $filename]);
            break;

        case 'optimize_db':
            $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $conn->query("OPTIMIZE TABLE `$table` text");
            }
            echo json_encode(["success" => true, "message" => "Database optimization complete"]);
            break;

        case 'delete_backup':
            $file = $_GET['filename'];
            $backupDir = __DIR__ . '/../exports/backups/';
            if (strpos($file, '..') === false && file_exists($backupDir . $file)) {
                unlink($backupDir . $file);
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false, "message" => "Invalid file"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Invalid maintenance action"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
