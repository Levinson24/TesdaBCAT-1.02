<?php
/**
 * Admin - System Audit Logs
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'System Audit Logs';
require_once '../includes/header.php';
requireRole('admin');

$conn = getDBConnection();

// Fetch all audit logs with user details
$logs = $conn->query("
    SELECT a.*, u.username, u.role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
");
?>

<div class="card premium-card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent border-0 p-4">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-primary">
                <i class="fas fa-clipboard-list me-2 text-accent-indigo"></i> Comprehensive Audit Trail
            </h5>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-primary btn-sm rounded-pill px-3 no-print">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover table-mobile-card align-middle" id="auditLogsTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Timestamp</th>
                        <th>User Account</th>
                        <th>Action Perform</th>
                        <th>Affected Module</th>
                        <th>Record ID</th>
                        <th>Details/Updates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-3" data-label="Timestamp">
                            <div class="fw-semibold small"><?php echo formatDateTime($log['created_at']); ?></div>
                        </td>
                        <td data-label="User Account">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-2 bg-light text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 28px; height: 28px; font-size: 0.7rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($log['username'] ?? 'System/Automated'); ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;"><?php echo ucfirst($log['role'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Action Perform">
                            <?php
    $badgeClass = 'bg-info bg-opacity-10 text-info';
    $action = $log['action'];
    if (stripos($action, 'create') !== false || stripos($action, 'insert') !== false) {
        $badgeClass = 'bg-success bg-opacity-10 text-success';
    }
    elseif (stripos($action, 'delete') !== false) {
        $badgeClass = 'bg-danger bg-opacity-10 text-danger';
    }
    elseif (stripos($action, 'update') !== false) {
        $badgeClass = 'bg-warning bg-opacity-10 text-warning';
    }
    elseif (stripos($action, 'login') !== false) {
        $badgeClass = 'bg-primary bg-opacity-10 text-primary';
    }
?>
                            <span class="badge rounded-pill <?php echo $badgeClass; ?> px-2 py-1 small">
                                <?php echo htmlspecialchars($action); ?>
                            </span>
                        </td>
                        <td data-label="Affected Module">
                            <span class="badge bg-light text-dark border fw-normal"><?php echo htmlspecialchars($log['table_name'] ?? 'General'); ?></span>
                        </td>
                        <td data-label="Record ID">
                            <span class="text-muted small">#<?php echo htmlspecialchars($log['record_id'] ?? 'N/A'); ?></span>
                        </td>
                        <td data-label="Details/Updates">
                            <div class="small text-muted text-wrap" style="max-width: 100%; word-break: break-word; min-width: 150px;">
                                <?php echo htmlspecialchars($log['new_values'] ?? '-'); ?>
                            </div>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if ($.fn.DataTable) {
        $('#auditLogsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search audit logs..."
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
