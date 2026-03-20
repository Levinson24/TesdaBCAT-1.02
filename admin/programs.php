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
    .premium-card {
        border-radius: 1rem;
        transition: transform 0.2s;
    }
    .bg-dark-navy {
        background-color: #002366 !important;
    }
    .programs-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
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
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-pill-active { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .status-pill-inactive { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }

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

<div class="card premium-card shadow-sm border-0">
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-graduation-cap me-2 text-info"></i> Programs (Courses)
        </h5>
        <button class="btn btn-light btn-sm rounded px-3 shadow-sm fw-bold border-0" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1 text-primary"></i> Add Program
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 programs-table data-table">
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
                                <div class="status-pill status-pill-active">
                                    <div class="status-dot" style="background: #22c55e;"></div> Active
                                </div>
                            <?php else: ?>
                                <div class="status-pill status-pill-inactive">
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add Program (Course)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Program Code</label>
                        <input type="text" name="program_code" class="form-control" placeholder="e.g. BSIT" required>
                    </div>
                    <div class="mb-3">
                        <label>Program Name</label>
                        <input type="text" name="program_name" class="form-control" placeholder="e.g. Bachelor of Science in Information Technology" required>
                    </div>
                    <div class="mb-3">
                        <label>Diploma Program</label>
                        <select name="dept_id" class="form-select" required>
                            <option value="">-- Select Diploma Program --</option>
                            <?php
$depts->data_seek(0);
while ($d = $depts->fetch_assoc()): ?>
                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Program</button>
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
                <input type="hidden" name="program_id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Program Code</label>
                        <input type="text" name="program_code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Program Name</label>
                        <input type="text" name="program_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Diploma Program</label>
                        <select name="dept_id" id="edit_dept_id" class="form-select" required>
                            <?php
$depts->data_seek(0);
while ($d = $depts->fetch_assoc()): ?>
                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                            <?php
endwhile; ?>
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
                    <button type="submit" class="btn btn-primary">Update Program</button>
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
</script>

<form id="deleteForm" method="POST" style="display: none;">
    <?php csrfField(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="program_id" value="">
</form>

<?php require_once '../includes/footer.php'; ?>
