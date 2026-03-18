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
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-university"></i> Colleges</h5>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Add College
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-mobile-card data-table">
                <thead>
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>College Name</th>
                        <th>Diploma Programs</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
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
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick='editCollege(<?php echo json_encode($c); ?>)'>
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this college?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="college_id" value="<?php echo $c['college_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
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
