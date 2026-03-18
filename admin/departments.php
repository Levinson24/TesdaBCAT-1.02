<?php
/**
 * Admin - Department Management
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $name = sanitizeInput($_POST['title_diploma_program']);
        $code = sanitizeInput($_POST['dept_code']);
        $collegeId = !empty($_POST['college_id']) ? intval($_POST['college_id']) : null;

        $stmt = $conn->prepare("INSERT INTO departments (title_diploma_program, dept_code, college_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $code, $collegeId);
        if ($stmt->execute()) {
            redirectWithMessage('departments.php', 'Department created successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['dept_id']);
        $name = sanitizeInput($_POST['title_diploma_program']);
        $code = sanitizeInput($_POST['dept_code']);
        $collegeId = !empty($_POST['college_id']) ? intval($_POST['college_id']) : null;
        $status = sanitizeInput($_POST['status']);

        $stmt = $conn->prepare("UPDATE departments SET title_diploma_program = ?, dept_code = ?, college_id = ?, status = ? WHERE dept_id = ?");
        $stmt->bind_param("ssisi", $name, $code, $collegeId, $status, $id);
        if ($stmt->execute()) {
            redirectWithMessage('departments.php', 'Department updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['dept_id']);
        // Check if anything is using this department
        $check1 = $conn->query("SELECT COUNT(*) FROM courses WHERE dept_id = $id")->fetch_row()[0];
        $check2 = $conn->query("SELECT COUNT(*) FROM students WHERE dept_id = $id")->fetch_row()[0];
        $check3 = $conn->query("SELECT COUNT(*) FROM instructors WHERE dept_id = $id")->fetch_row()[0];

        if ($check1 + $check2 + $check3 > 0) {
            redirectWithMessage('departments.php', 'Cannot delete department: It is currently linked to courses, students, or faculty.', 'danger');
        }
        else {
            $conn->query("DELETE FROM departments WHERE dept_id = $id");
            redirectWithMessage('departments.php', 'Department deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Diploma Programs';
require_once '../includes/header.php';

$departments = $conn->query("
    SELECT d.*, c.college_name,
           (SELECT COUNT(*) FROM programs WHERE dept_id = d.dept_id) as program_count,
           (SELECT COUNT(*) FROM instructors WHERE dept_id = d.dept_id) as faculty_count
    FROM departments d
    LEFT JOIN colleges c ON d.college_id = c.college_id
    ORDER BY d.title_diploma_program ASC
");

$colleges_list = $conn->query("SELECT college_id, college_name FROM colleges WHERE status = 'active' ORDER BY college_name ASC");
$colleges = [];
while ($c = $colleges_list->fetch_assoc()) {
    $colleges[] = $c;
}
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-building"></i> Academic Diploma Programs</h5>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Add Diploma Program
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-mobile-card data-table">
                <thead>
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Title Diploma Program</th>
                        <th>College</th>
                        <th>Programs</th>
                        <th>Faculty</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($d = $departments->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" data-label="Code"><strong><?php echo htmlspecialchars($d['dept_code']); ?></strong></td>
                        <td data-label="Title Diploma Program"><?php echo htmlspecialchars($d['title_diploma_program']); ?></td>
                        <td data-label="College"><span class="text-muted small"><?php echo htmlspecialchars($d['college_name'] ?? 'Unassigned'); ?></span></td>
                        <td data-label="Programs"><span class="badge bg-info bg-opacity-10 text-info"><?php echo $d['program_count']; ?></span></td>
                        <td data-label="Faculty"><span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo $d['faculty_count']; ?></span></td>
                        <td data-label="Status">
                            <span class="badge rounded-pill bg-<?php echo $d['status'] === 'active' ? 'success' : 'secondary'; ?> bg-opacity-10 text-<?php echo $d['status'] === 'active' ? 'success' : 'secondary'; ?> px-3">
                                <?php echo ucfirst($d['status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="d-flex justify-content-end gap-1">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick='editDept(<?php echo json_encode($d); ?>)'>
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this diploma program?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="dept_id" value="<?php echo $d['dept_id']; ?>">
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
                    <h5 class="modal-title">Add Diploma Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Diploma Program Code</label>
                        <input type="text" name="dept_code" class="form-control" placeholder="e.g. ICT" required>
                    </div>
                    <div class="mb-3">
                        <label>Title Diploma Program</label>
                        <input type="text" name="title_diploma_program" class="form-control" placeholder="e.g. Information & Communication Technology" required>
                    </div>
                    <div class="mb-3">
                        <label>College</label>
                        <select name="college_id" class="form-select">
                            <option value="">-- No College --</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?php echo $c['college_id']; ?>"><?php echo htmlspecialchars($c['college_name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Diploma Program</button>
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
                <input type="hidden" name="dept_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Diploma Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Diploma Program Code</label>
                        <input type="text" name="dept_code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Title Diploma Program</label>
                        <input type="text" name="title_diploma_program" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>College</label>
                        <select name="college_id" id="edit_college_id" class="form-select">
                            <option value="">-- No College --</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?php echo $c['college_id']; ?>"><?php echo htmlspecialchars($c['college_name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
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
                    <button type="submit" class="btn btn-primary">Update Diploma Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDept(d) {
    document.getElementById('edit_id').value = d.dept_id;
    document.getElementById('edit_code').value = d.dept_code;
    document.getElementById('edit_name').value = d.title_diploma_program;
    document.getElementById('edit_college_id').value = d.college_id || '';
    document.getElementById('edit_status').value = d.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
