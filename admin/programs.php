<?php
/**
 * Admin - Program (Course) Management
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $name = sanitizeInput($_POST['program_name']);
        $code = strtoupper(sanitizeInput($_POST['program_code']));
        $deptId = intval($_POST['dept_id']);

        $stmt = $conn->prepare("INSERT INTO programs (program_name, program_code, dept_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $code, $deptId);
        if ($stmt->execute()) {
            redirectWithMessage('programs.php', 'Program created successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['program_id']);
        $name = sanitizeInput($_POST['program_name']);
        $code = strtoupper(sanitizeInput($_POST['program_code']));
        $deptId = intval($_POST['dept_id']);
        $status = sanitizeInput($_POST['status']);

        $stmt = $conn->prepare("UPDATE programs SET program_name = ?, program_code = ?, dept_id = ?, status = ? WHERE program_id = ?");
        $stmt->bind_param("ssisi", $name, $code, $deptId, $status, $id);
        if ($stmt->execute()) {
            redirectWithMessage('programs.php', 'Program updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['program_id']);
        // Check if anything is using this program
        $check1 = $conn->query("SELECT COUNT(*) FROM courses WHERE program_id = $id")->fetch_row()[0];
        $check2 = $conn->query("SELECT COUNT(*) FROM students WHERE program_id = $id")->fetch_row()[0];

        if ($check1 + $check2 > 0) {
            redirectWithMessage('programs.php', 'Cannot delete program: It is currently linked to subjects or students.', 'danger');
        }
        else {
            $conn->query("DELETE FROM programs WHERE program_id = $id");
            redirectWithMessage('programs.php', 'Program deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Programs (Courses)';
require_once '../includes/header.php';

$depts = $conn->query("SELECT dept_id, title_diploma_program FROM departments WHERE status = 'active' ORDER BY title_diploma_program ASC");
$programs = $conn->query("
    SELECT p.*, d.title_diploma_program as dept_name,
           (SELECT COUNT(*) FROM courses WHERE program_id = p.program_id) as subject_count,
           (SELECT COUNT(*) FROM students WHERE program_id = p.program_id) as student_count
    FROM programs p
    JOIN departments d ON p.dept_id = d.dept_id
    ORDER BY p.program_name ASC
");
?>

<style>
    .program-icon-box {
        width: 42px; height: 42px;
        background: #f1f5f9;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: #0038A8;
        margin-right: 1rem;
        border: 1px solid #e2e8f0;
        font-size: 1.1rem;
    }
    .stat-pill {
        padding: 0.35rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .stat-blue { background: #eff6ff; color: #1e40af; }
    .stat-rose { background: #fff1f2; color: #9f1239; }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }
</style>

<div class="card premium-card shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-graduation-cap me-2 text-info"></i> Specific Programs (Courses)
        </h5>
        
        <div class="search-box-container">
            <div class="input-group input-group-sm rounded-pill overflow-hidden border-0 shadow-sm" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                <span class="input-group-text bg-transparent border-0 text-white-50 ps-3">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="programSearchInput" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="Search Program Name or Code..." onkeyup="filterPrograms()" style="box-shadow: none;">
                <span class="input-group-text bg-transparent border-0 text-white-50 pe-3" id="searchCounter" style="font-size: 0.75rem; font-weight: 600;"></span>
            </div>
        </div>

        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add Program
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 programs-table premium-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">PROGRAM IDENTITY</th>
                        <th>DIPLOMA PROGRAM</th>
                        <th class="text-center">SUBJECTS</th>
                        <th class="text-center">STUDENTS</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">MANAGE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $programs->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="program-icon-box">
                                    <?php echo substr($p['program_code'], 0, 1); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark fs-6 lh-1"><?php echo htmlspecialchars($p['program_name']); ?></div>
                                    <div class="badge bg-light text-muted border mt-1" style="font-size: 0.65rem; font-weight: 700;">
                                        <?php echo htmlspecialchars($p['program_code']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center text-muted">
                                <i class="fas fa-building me-2 small"></i>
                                <span class="small fw-semibold"><?php echo htmlspecialchars($p['dept_name']); ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="stat-pill stat-blue">
                                <i class="fas fa-book-open"></i> <?php echo $p['subject_count']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="stat-pill stat-rose">
                                <i class="fas fa-users"></i> <?php echo $p['student_count']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (($p['status'] ?? 'active') === 'active'): ?>
                                <div class="status-pill status-active">
                                    <div class="status-dot" style="background: #22c55e;"></div> Active
                                </div>
                            <?php else: ?>
                                <div class="status-pill status-inactive">
                                    <div class="status-dot" style="background: #94a3b8;"></div> Inactive
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn-premium-edit" onclick='editProgram(<?php echo json_encode($p); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-premium-delete" onclick="deleteProgram(<?php echo $p['program_id']; ?>, '<?php echo addslashes($p['program_name']); ?>')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off" class="w-100">
                <input type="hidden" name="action" value="create">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Add Program (Course)</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-info-circle me-2"></i>Program Details</span>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Program Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="program_code" class="form-control" placeholder="e.g. BSIT" required>
                                    <i class="fas fa-barcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="premium-input-group">
                                <label>Program Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="program_name" class="form-control" placeholder="e.g. Bachelor of Science in IT" required>
                                    <i class="fas fa-certificate"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Diploma Program</label>
                                <div class="input-wrapper">
                                    <select name="dept_id" class="form-select" required>
                                        <option value="">-- Select Diploma Program --</option>
                                        <?php
                                        $depts->data_seek(0);
                                        while ($d = $depts->fetch_assoc()): ?>
                                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-save me-2"></i>Save Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off" class="w-100">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="program_id" id="edit_id">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i>
                        <span>Edit Program</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-sliders-h me-2"></i>Update Details</span>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Program Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="program_code" id="edit_code" class="form-control" required>
                                    <i class="fas fa-barcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Program Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="program_name" id="edit_name" class="form-control" required>
                                    <i class="fas fa-certificate"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Diploma Program</label>
                                <div class="input-wrapper">
                                    <select name="dept_id" id="edit_dept_id" class="form-select" required>
                                        <?php
                                        $depts->data_seek(0);
                                        while ($d = $depts->fetch_assoc()): ?>
                                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
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
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-sync me-2"></i>Update Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProgram(p) {
    document.getElementById('edit_id').value = p.program_id;
    document.getElementById('edit_code').value = p.program_code;
    document.getElementById('edit_name').value = p.program_name;
    document.getElementById('edit_dept_id').value = p.dept_id;
    document.getElementById('edit_status').value = p.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteProgram(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"? This will also affect subjects linked to this program.')) {
        const form = document.getElementById('deleteForm');
        form.querySelector('input[name="program_id"]').value = id;
        form.submit();
    }
}
// Filter programs using DataTables API
function filterPrograms() {
    const input = document.getElementById('programSearchInput');
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

<form id="deleteForm" method="POST" style="display: none;">
    <?php csrfField(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="program_id" value="">
</form>

<?php require_once '../includes/footer.php'; ?>
