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


<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-building me-2 text-info"></i> Academic Diploma Programs
        </h5>
        
        <div class="search-box-container">
            <div class="input-group input-group-sm rounded-pill overflow-hidden border-0 shadow-sm" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                <span class="input-group-text bg-transparent border-0 text-white-50 ps-3">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="deptSearchInput" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="Search Portfolio Code or Title..." onkeyup="filterDepts()" style="box-shadow: none;">
                <span class="input-group-text bg-transparent border-0 text-white-50 pe-3" id="searchCounter" style="font-size: 0.75rem; font-weight: 600;"></span>
            </div>
        </div>

        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add Portfolio
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 depts-table premium-table data-table">
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
                    <tr class="table-row-premium align-middle">
                        <td class="ps-4" data-label="Code"><strong><?php echo htmlspecialchars($d['dept_code']); ?></strong></td>
                        <td data-label="Title Diploma Program"><?php echo htmlspecialchars($d['title_diploma_program']); ?></td>
                        <td data-label="College"><span class="text-muted small"><?php echo htmlspecialchars($d['college_name'] ?? 'Unassigned'); ?></span></td>
                        <td data-label="Programs"><span class="badge bg-info bg-opacity-10 text-info"><?php echo $d['program_count']; ?></span></td>
                        <td data-label="Faculty"><span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo $d['faculty_count']; ?></span></td>
                        <td data-label="Status">
                            <span class="status-pill <?php echo $d['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($d['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4 py-3" data-label="Control Actions">
                            <div class="table-actions-v2">
                                <button class="btn-premium-edit" onclick='editDept(<?php echo json_encode($d); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" onsubmit="return confirm('Delete this diploma program?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="dept_id" value="<?php echo $d['dept_id']; ?>">
                                    <button type="submit" class="btn-premium-delete">
                                        <i class="fas fa-trash-alt"></i>
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
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="create">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-building"></i>
                        <span>Add Diploma Program</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-info-circle me-2"></i>Program Configuration</span>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Diploma Program Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="dept_code" class="form-control" placeholder="e.g. ICT" required>
                                    <i class="fas fa-qrcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Title Diploma Program</label>
                                <div class="input-wrapper">
                                    <input type="text" name="title_diploma_program" class="form-control" placeholder="e.g. Information Technology" required>
                                    <i class="fas fa-quote-left"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>College Affiliation</label>
                                <div class="input-wrapper">
                                    <select name="college_id" class="form-select">
                                        <option value="">-- No College --</option>
                                        <?php foreach ($colleges as $c): ?>
                                            <option value="<?php echo $c['college_id']; ?>"><?php echo htmlspecialchars($c['college_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-save me-2"></i>Save Diploma Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="dept_id" id="edit_id">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i>
                        <span>Edit Diploma Program</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-sliders-h me-2"></i>Update Configuration</span>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Diploma Program Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="dept_code" id="edit_code" class="form-control" required>
                                    <i class="fas fa-qrcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Title Diploma Program</label>
                                <div class="input-wrapper">
                                    <input type="text" name="title_diploma_program" id="edit_name" class="form-control" required>
                                    <i class="fas fa-quote-left"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>College Affiliation</label>
                                <div class="input-wrapper">
                                    <select name="college_id" id="edit_college_id" class="form-select">
                                        <option value="">-- No College --</option>
                                        <?php foreach ($colleges as $c): ?>
                                            <option value="<?php echo $c['college_id']; ?>"><?php echo htmlspecialchars($c['college_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Status</label>
                                <div class="input-wrapper">
                                    <select name="status" id="edit_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <i class="fas fa-toggle-on"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-sync me-2"></i>Update Diploma Program</button>
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
// Filter departments using DataTables API
function filterDepts() {
    const input = document.getElementById('deptSearchInput');
    const filter = input.value.trim();
    const table = $('.data-table').DataTable();
    const counter = document.getElementById('searchCounter');

    // Use DataTables search API
    table.search(filter).draw();

    // Update the counter
    const info = table.page.info();
    if (filter === "") {
        counter.textContent = "";
    } else {
        counter.textContent = info.recordsDisplay + " found";
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
