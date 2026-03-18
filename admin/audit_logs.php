<?php
/**
 * Admin - System Audit Logs (Enhanced with search/filter)
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'System Audit Logs';
require_once '../includes/header.php';
requireRole('admin');

$conn = getDBConnection();

// ── Filters ──────────────────────────────────────────────
$fromDate  = sanitizeInput($_GET['from_date']  ?? '');
$toDate    = sanitizeInput($_GET['to_date']    ?? '');
$action    = sanitizeInput($_GET['action']     ?? '');
$userSearch= sanitizeInput($_GET['user_search']?? '');

$where  = [];
$params = [];
$types  = '';

if ($fromDate) { $where[] = 'DATE(a.created_at) >= ?'; $params[] = $fromDate; $types .= 's'; }
if ($toDate)   { $where[] = 'DATE(a.created_at) <= ?'; $params[] = $toDate;   $types .= 's'; }
if ($action)   { $where[] = 'a.action LIKE ?';          $params[] = "%{$action}%"; $types .= 's'; }
if ($userSearch){ $where[] = '(u.username LIKE ? OR u.role LIKE ?)'; $params[] = "%{$userSearch}%"; $params[] = "%{$userSearch}%"; $types .= 'ss'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT a.*, u.username, u.role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    {$whereSql}
    ORDER BY a.created_at DESC
    LIMIT 1000
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query($sql);
}

// Get distinct action types for the filter dropdown
$actionTypes = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-clipboard-list me-2 text-primary"></i>System Audit Logs</h4>
        <p class="text-muted mb-0">Complete activity trail for all system actions</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-primary no-print" style="border-radius:0.875rem;">
        <i class="fas fa-print me-2"></i>Print
    </button>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4 no-print" style="border-radius:1.25rem;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-600 mb-1" style="font-size:0.75rem;text-transform:uppercase;color:#64748b;">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-600 mb-1" style="font-size:0.75rem;text-transform:uppercase;color:#64748b;">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600 mb-1" style="font-size:0.75rem;text-transform:uppercase;color:#64748b;">Action Type</label>
                <select class="form-select form-select-sm" name="action">
                    <option value="">— All Actions —</option>
                    <?php while ($at = $actionTypes->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($at['action']); ?>"
                            <?php echo $action === $at['action'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($at['action']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600 mb-1" style="font-size:0.75rem;text-transform:uppercase;color:#64748b;">User / Role</label>
                <input type="text" class="form-control form-control-sm" name="user_search"
                       placeholder="Username or role..." value="<?php echo htmlspecialchars($userSearch); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1" style="border-radius:0.75rem;">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
                <a href="audit_logs.php" class="btn btn-outline-secondary btn-sm" style="border-radius:0.75rem;" title="Clear">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card premium-card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover table-mobile-card align-middle" id="auditLogsTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Timestamp</th>
                        <th>User Account</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Record #</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-3" data-label="Timestamp">
                            <div class="fw-semibold small"><?php echo formatDateTime($log['created_at']); ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></div>
                        </td>
                        <td data-label="User">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-2 bg-light text-primary d-flex align-items-center justify-content-center rounded-circle" style="width:28px;height:28px;font-size:0.7rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></div>
                                    <div class="text-muted" style="font-size:0.65rem;"><?php echo ucfirst($log['role'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Action">
                            <?php
                            $ac = $log['action'];
                            $bc = 'bg-info bg-opacity-10 text-info';
                            if (stripos($ac, 'create') !== false || stripos($ac, 'insert') !== false || stripos($ac, 'enroll') !== false) $bc = 'bg-success bg-opacity-10 text-success';
                            elseif (stripos($ac, 'delete') !== false) $bc = 'bg-danger bg-opacity-10 text-danger';
                            elseif (stripos($ac, 'update') !== false || stripos($ac, 'reset') !== false) $bc = 'bg-warning bg-opacity-10 text-warning';
                            elseif (stripos($ac, 'login') !== false) $bc = 'bg-primary bg-opacity-10 text-primary';
                            elseif (stripos($ac, 'lock') !== false || stripos($ac, 'security') !== false) $bc = 'bg-danger bg-opacity-25 text-danger';
                            ?>
                            <span class="badge rounded-pill <?php echo $bc; ?> px-2 py-1 small">
                                <?php echo htmlspecialchars($ac); ?>
                            </span>
                        </td>
                        <td data-label="Module">
                            <span class="badge bg-light text-dark border fw-normal"><?php echo htmlspecialchars($log['table_name'] ?? 'General'); ?></span>
                        </td>
                        <td data-label="Record">
                            <span class="text-muted small">#<?php echo htmlspecialchars($log['record_id'] ?? 'N/A'); ?></span>
                        </td>
                        <td data-label="Details">
                            <div class="small text-muted text-wrap" style="max-width:220px;word-break:break-word;">
                                <?php echo htmlspecialchars($log['new_values'] ?? ($log['old_values'] ?? '—')); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if ($.fn && $.fn.DataTable) {
        $('#auditLogsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: { search: "_INPUT_", searchPlaceholder: "Search results..." }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
