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
<style>
    .premium-card { border-radius: 1rem; }
    .bg-dark-navy { background-color: #0f172a !important; }
    .depts-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.70rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .depts-table tbody td {
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
        color: #ef4444 !important;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border: none;
        cursor: pointer;
    }
    .btn-premium-delete:hover {
        background-color: #fef2f2;
        border-color: #fecaca;
        color: #dc2626 !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }
</style>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-building me-2 text-info"></i> Academic Diploma Programs
        </h5>
        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add Portfolio
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 depts-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">DEPT CODE</th>
                        <th>DIPLOMA PROGRAM TITLE</th>
                        <th>COLLEGE AFFILIATION</th>
                        <th class="text-center">PROGRAMS</th>
                        <th class="text-center">FACULTY</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4">ACTIONS</th>
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
                                <button class="btn-premium-edit" onclick='editDept(<?php echo json_encode($d); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this diploma program?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="dept_id" value="<?php echo $d['dept_id']; ?>">
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
