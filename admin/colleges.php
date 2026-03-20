<?php
/**
 * Admin - College Management
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $name = sanitizeInput($_POST['college_name']);
        $code = strtoupper(sanitizeInput($_POST['college_code']));

        $stmt = $conn->prepare("INSERT INTO colleges (college_name, college_code) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $code);
        if ($stmt->execute()) {
            redirectWithMessage('colleges.php', 'College created successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['college_id']);
        $name = sanitizeInput($_POST['college_name']);
        $code = strtoupper(sanitizeInput($_POST['college_code']));
        $status = sanitizeInput($_POST['status']);

        $stmt = $conn->prepare("UPDATE colleges SET college_name = ?, college_code = ?, status = ? WHERE college_id = ?");
        $stmt->bind_param("sssi", $name, $code, $status, $id);
        if ($stmt->execute()) {
            redirectWithMessage('colleges.php', 'College updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['college_id']);
        // Check if anything is using this college
        $check1 = $conn->query("SELECT COUNT(*) FROM departments WHERE college_id = $id")->fetch_row()[0];

        if ($check1 > 0) {
            redirectWithMessage('colleges.php', 'Cannot delete college: It is currently linked to diploma programs.', 'danger');
        }
        else {
            $conn->query("DELETE FROM colleges WHERE college_id = $id");
            redirectWithMessage('colleges.php', 'College deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Colleges';
require_once '../includes/header.php';

$colleges = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM departments WHERE college_id = c.college_id) as dept_count
    FROM colleges c
    ORDER BY c.college_name ASC
");
// === Premium Styles ===
?>
<style>
    .premium-card { border-radius: 1rem; }
    .bg-dark-navy { background-color: #0f172a !important; }
    .colleges-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .colleges-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        font-size: 0.85rem;
    }
    /* Premium Action Buttons */
    .btn-premium-edit {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 1.2rem;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50px;
        color: #334155 !important;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        text-decoration: none !important;
        cursor: pointer;
    }
    .btn-premium-edit:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e0;
        color: #1e293b !important;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }
    .btn-premium-edit i { color: #2563eb; margin-right: 0.5rem; }

    .btn-premium-delete {
        width: 36px; height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50%;
        color: #ef4444;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border: none;
        cursor: pointer;
    }
    .btn-premium-delete:hover {
        background-color: #fef2f2;
        border-color: #fecaca;
        color: #dc2626;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }
</style>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-university me-2 text-info"></i> Higher Education Colleges
        </h5>
        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add College
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 colleges-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">INSTITUTION CODE</th>
                        <th>COLLEGE NAME</th>
                        <th class="text-center">PROGRAMS</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $colleges->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" data-label="Code"><strong><?php echo htmlspecialchars($c['college_code']); ?></strong></td>
                        <td data-label="College Name"><?php echo htmlspecialchars($c['college_name']); ?></td>
                        <td data-label="Diploma Programs"><span class="badge bg-info bg-opacity-10 text-info"><?php echo $c['dept_count']; ?></span></td>
                        <td data-label="Status">
                            <span class="badge rounded-pill bg-<?php echo $c['status'] === 'active' ? 'success' : 'secondary'; ?> bg-opacity-10 text-<?php echo $c['status'] === 'active' ? 'success' : 'secondary'; ?> px-3">
                                <?php echo ucfirst($c['status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="d-flex justify-content-end gap-1">
                                <button class="btn-premium-edit" onclick='editCollege(<?php echo json_encode($c); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this college?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="college_id" value="<?php echo $c['college_id']; ?>">
                                    <button type="submit" class="btn-premium-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add College</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>College Code</label>
                        <input type="text" name="college_code" class="form-control" placeholder="e.g. BCAT" required>
                    </div>
                    <div class="mb-3">
                        <label>College Name</label>
                        <input type="text" name="college_name" class="form-control" placeholder="e.g. Balicuatro College of Arts and Trades" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save College</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="college_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit College</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>College Code</label>
                        <input type="text" name="college_code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>College Name</label>
                        <input type="text" name="college_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update College</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCollege(c) {
    document.getElementById('edit_id').value = c.college_id;
    document.getElementById('edit_code').value = c.college_code;
    document.getElementById('edit_name').value = c.college_name;
    document.getElementById('edit_status').value = c.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
