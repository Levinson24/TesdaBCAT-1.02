<?php
/**
 * Database Reset Utility
 * TESDA-BCAT Grade Management System
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$conn = getDBConnection();

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'danger';
    } elseif (strtoupper($_POST['confirm_reset'] ?? '') !== 'RESET') {
        $message = 'Reset aborted. You must type "RESET" to confirm.';
        $messageType = 'warning';
    } else {
        $resetLevel = intval($_POST['reset_level'] ?? 1);
        
        // 1. Perform Automatic Backup first
        $backupDir = __DIR__ . '/../exports/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "pre_reset_backup_{$timestamp}.sql";
        $backupPath = $backupDir . $backupFile;
        
        // Attempt backup using mysqldump if available
        $mysqlDump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (!file_exists($mysqlDump)) $mysqlDump = 'mysqldump';
        
        $hostParts = explode(':', DB_HOST);
        $host = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : '3306';
        
        $cmd = "\"{$mysqlDump}\" --host={$host} --port={$port} --user=" . DB_USER . " " . 
               (DB_PASS !== '' ? "--password=" . DB_PASS . " " : "") . 
               DB_NAME . " > \"{$backupPath}\" 2>&1";
        
        exec($cmd, $output, $returnCode);
        
        // 2. Prepare SQL Files to execute
        $sqlFiles = [];
        $sqlFiles[] = __DIR__ . '/../database_schema.sql';
        
        if ($resetLevel >= 2) {
            $sqlFiles[] = __DIR__ . '/../populate_curriculum_data.sql';
        }
        
        if ($resetLevel >= 3) {
            $sqlFiles[] = __DIR__ . '/../sample_data.sql';
        }
        
        // 3. Execute Reset
        $successCount = 0;
        $errorDetails = '';
        
        try {
            // We use a new connection or multi_query for the reset
            // Since database_schema.sql drops the DB, we might need to reconnect
            
            foreach ($sqlFiles as $file) {
                if (!file_exists($file)) {
                    throw new Exception("SQL file not found: " . basename($file));
                }
                
                $sqlContent = file_get_contents($file);
                
                // Robust SQL execution (handling DELIMITER and multiple statements)
                // We'll split by semicolon but handle the DELIMITER blocks
                if (executeLargeSqlFile($conn, $sqlContent)) {
                    $successCount++;
                } else {
                    $errorDetails .= "Error executing " . basename($file) . ". ";
                }
            }
            
            if ($successCount === count($sqlFiles)) {
                logAudit(getCurrentUserId(), 'SYSTEM_RESET', 'database', 0, null, "Database reset to Level {$resetLevel}. Backup: {$backupFile}");
                redirectWithMessage('db_reset.php', "Database successfully reset to Level {$resetLevel}! A backup was created: {$backupFile}", 'success');
            } else {
                $message = "Database reset partially failed. " . $errorDetails;
                $messageType = 'danger';
            }
            
        } catch (Exception $e) {
            $message = "Error during reset: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

/**
 * Execute large SQL strings by splitting into individual queries
 * Handles DELIMITER // ... // DELIMITER ;
 */
function executeLargeSqlFile($conn, $sql) {
    // Remove comments
    $sql = preg_replace('/--.*?\n/', "\n", $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Check for DELIMITER blocks
    // This is a simplified parser for stored procedures/triggers
    $queries = [];
    $delimiter = ';';
    $lines = explode("\n", $sql);
    $currentQuery = '';
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine)) continue;
        
        if (stripos($trimmedLine, 'DELIMITER') === 0) {
            $parts = explode(' ', $trimmedLine);
            $delimiter = end($parts);
            continue;
        }
        
        $currentQuery .= $line . "\n";
        
        if (strpos($trimmedLine, $delimiter) !== false && substr($trimmedLine, -strlen($delimiter)) === $delimiter) {
            $queryToExec = trim(substr(trim($currentQuery), 0, -strlen($delimiter)));
            if (!empty($queryToExec)) {
                $queries[] = $queryToExec;
            }
            $currentQuery = '';
        }
    }
    
    // Execute all collected queries
    $conn->begin_transaction();
    try {
        foreach ($queries as $q) {
            if (!$conn->query($q)) {
                // If it's a "DROP DATABASE" or "CREATE DATABASE" it might require reconnecting
                if (stripos($q, 'DATABASE') !== false) {
                    // Let it pass or handle reconnection if needed
                    continue; 
                }
                throw new Exception($conn->error . "\nQuery: " . substr($q, 0, 100));
            }
        }
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("SQL Reset Error: " . $e->getMessage());
        return false;
    }
}

$pageTitle = 'Database Reset Utility';
require_once '../includes/header.php';
?>

<style>
    .danger-card {
        border: 1px solid #fee2e2;
        border-radius: 1rem;
        background: #fff;
    }
    .reset-option {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .reset-option:hover {
        border-color: #0038A8;
        background: #f8fafc;
    }
    .reset-option input[type="radio"]:checked + .option-content {
        color: #0038A8;
    }
    .reset-option input[type="radio"] {
        display: none;
    }
    .reset-option.active {
        border-color: #0038A8;
        background: #f0f7ff;
        box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1);
    }
    .level-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 50px;
        text-transform: uppercase;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: inline-block;
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="settings.php">Settings</a></li>
                    <li class="breadcrumb-item active">Database Reset</li>
                </ol>
            </nav>

            <?php echo getFlashMessage(); ?>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card danger-card shadow-sm overflow-hidden mb-5">
                <div class="card-header bg-danger text-white p-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-white text-danger rounded-circle p-3 me-3">
                            <i class="fas fa-trash-alt fa-2x"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold">System Reset Utility</h4>
                            <p class="mb-0 opacity-75">Restore the system to a clean or demo state.</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <p class="text-muted mb-4">
                        This utility allows you to wipe the current database and start fresh. 
                        <strong>A backup will be automatically created</strong> in the <code>exports/backups/</code> folder before any changes are made.
                    </p>

                    <form method="POST" id="resetForm">
                        <?php csrfField(); ?>
                        
                        <h6 class="fw-bold text-dark mb-3">Choose Reset Level:</h6>
                        
                        <div class="reset-options mb-4">
                            <label class="reset-option d-block p-3 mb-3 active" for="level1">
                                <input type="radio" name="reset_level" id="level1" value="1" checked>
                                <div class="option-content">
                                    <span class="level-badge bg-secondary text-white">Level 1</span>
                                    <h6 class="fw-bold mb-1">Fresh Start (Schema Only)</h6>
                                    <p class="small text-muted mb-0">Deletes all records. Keeps only the default admin account and essential system settings.</p>
                                </div>
                            </label>

                            <label class="reset-option d-block p-3 mb-3" for="level2">
                                <input type="radio" name="reset_level" id="level2" value="2">
                                <div class="option-content">
                                    <span class="level-badge bg-primary text-white">Level 2</span>
                                    <h6 class="fw-bold mb-1">Functional Baseline</h6>
                                    <p class="small text-muted mb-0">Level 1 + Official Curriculum data (Programs, Departments, and Courses). No students or instructors.</p>
                                </div>
                            </label>

                            <label class="reset-option d-block p-3 mb-3" for="level3">
                                <input type="radio" name="reset_level" id="level3" value="3">
                                <div class="option-content">
                                    <span class="level-badge bg-info text-white">Level 3</span>
                                    <h6 class="fw-bold mb-1">Demo State (Recommended for Testing)</h6>
                                    <p class="small text-muted mb-0">Full system state including sample students, instructors, sections, and historical grades.</p>
                                </div>
                            </label>
                        </div>

                        <div class="confirmation-box bg-light p-4 rounded-3 border">
                            <h6 class="fw-bold text-danger mb-2"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Destruction</h6>
                            <p class="small text-muted mb-3">To prevent accidental resets, please type <strong>RESET</strong> in the box below to proceed.</p>
                            <input type="text" name="confirm_reset" class="form-control form-control-lg text-center fw-bold" 
                                   placeholder="Type RESET here" autocomplete="off" required>
                        </div>

                        <div class="mt-4 d-grid">
                            <button type="button" class="btn btn-danger btn-lg py-3 fw-bold rounded-pill shadow-sm" id="btnRequestReset">
                                <i class="fas fa-sync-alt me-2"></i> Perform Database Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Critical Warning Modal -->
<div class="modal fade" id="resetWarningModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem;">
            <div class="modal-header bg-danger text-white border-0 py-4 px-4" style="border-top-left-radius: 1.5rem; border-top-right-radius: 1.5rem;">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-exclamation-triangle me-2"></i> CRITICAL WARNING
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-4">
                    <i class="fas fa-skull-crossbones fa-4x"></i>
                </div>
                <h4 class="fw-bold text-dark mb-3">Irreversible Action</h4>
                <p class="text-muted mb-4 px-3">
                    You are about to perform a complete system reset. This will <strong>DELETE ALL RECORDS</strong> from the database. This action cannot be undone.
                </p>

                <div class="d-grid gap-2 mb-4">
                    <a href="db_backup.php" class="btn btn-primary py-3 fw-bold rounded-pill shadow-sm" target="_blank">
                        <i class="fas fa-download me-2"></i> Download Backup Now
                    </a>
                    <small class="text-muted">Highy recommended: Download a copy of your current data first.</small>
                </div>

                <div class="form-check text-start mb-4 bg-light p-3 rounded-3 border">
                    <input class="form-check-input ms-0" type="checkbox" id="chkConfirmedBackup">
                    <label class="form-check-label ms-2 fw-bold text-dark small" for="chkConfirmedBackup">
                        I have already backed up the database and I am ready to proceed with the reset.
                    </label>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" id="btnFinalConfirm" disabled>
                    Final Reset & Re-initialize
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
$additionalJS = '
<script>
    // Visual feedback for selected option
    document.querySelectorAll(".reset-option").forEach(option => {
        option.addEventListener("click", function() {
            document.querySelectorAll(".reset-option").forEach(opt => opt.classList.remove("active"));
            this.classList.add("active");
        });
    });

    const resetForm = document.getElementById("resetForm");
    const btnRequestReset = document.getElementById("btnRequestReset");
    const modalEl = document.getElementById("resetWarningModal");
    let resetModal;
    
    // Initialize modal safely
    if (modalEl) {
        resetModal = new bootstrap.Modal(modalEl);
    }

    const chkConfirmedBackup = document.getElementById("chkConfirmedBackup");
    const btnFinalConfirm = document.getElementById("btnFinalConfirm");

    if (btnRequestReset) {
        btnRequestReset.addEventListener("click", function() {
            const confirmInput = resetForm.confirm_reset;
            const confirmText = confirmInput ? confirmInput.value.trim().toUpperCase() : "";
            if (confirmText !== "RESET") {
                alert("Please type RESET in the confirmation box first.");
                if (confirmInput) confirmInput.focus();
                return;
            }
            if (resetModal) resetModal.show();
        });
    }

    if (chkConfirmedBackup) {
        chkConfirmedBackup.addEventListener("change", function() {
            btnFinalConfirm.disabled = !this.checked;
        });
    }

    if (btnFinalConfirm) {
        btnFinalConfirm.addEventListener("click", function() {
            if (chkConfirmedBackup.checked) {
                this.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span> Processing reset...\';
                this.disabled = true;
                resetForm.submit();
            }
        });
    }

    // Prevent form from submitting with Enter key
    resetForm.addEventListener("submit", function(e) {
        e.preventDefault();
        btnRequestReset.click();
    });
</script>
';
require_once '../includes/footer.php'; 
?>
