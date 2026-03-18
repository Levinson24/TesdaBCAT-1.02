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
    .programs-table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        padding: 1.25rem 1rem;
        border-top: none;
    }
    .programs-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        font-size: 0.9rem;
    }
    .program-icon {
        width: 42px;
        height: 42px;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        margin-right: 14px;
        flex-shrink: 0;
        border: 1px solid #dbeafe;
    }
    .stat-badge {
        font-weight: 700;
        padding: 0.4rem 0.75rem;
        border-radius: 0.6rem;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .stat-subjects { background: #f0f9ff; color: #0369a1; border: 1px solid #e0f2fe; }
    .stat-students { background: #fdf2f8; color: #9d174d; border: 1px solid #fce7f3; }
    
    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.35rem 0.75rem;
        border-radius: 2rem;
    }
    .status-active {
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .status-inactive {
        background-color: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
    .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    .dot-active { background-color: #22c55e; }
    .dot-inactive { background-color: #94a3b8; }
    
    .btn-action {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: all 0.2s;
    }
    .btn-edit-lite {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
    }
    .btn-edit-lite:hover {
        background: #2563eb;
        color: white;
    }
    .btn-delete-lite {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fee2e2;
    }
    .btn-delete-lite:hover {
        background: #dc2626;
        color: white;
    }
</style>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Programs (Courses)</h5>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Add Program
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-mobile-card programs-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">Program Identity</th>
                        <th>Diploma Program</th>
                        <th class="text-center">Subjects</th>
                        <th class="text-center">Students</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $programs->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" data-label="Program Identity">
                            <div class="d-flex align-items-center">
                                <div class="program-icon">
                                    <?php echo substr($p['program_code'], 0, 1); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($p['program_name']); ?></div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border-primary border-opacity-25 mt-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                        <?php echo htmlspecialchars($p['program_code']); ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Diploma Program">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-building me-2 text-muted" style="font-size: 0.8rem;"></i>
                                <span class="text-muted small fw-medium"><?php echo htmlspecialchars($p['dept_name']); ?></span>
                            </div>
                        </td>
                        <td class="text-center" data-label="Subjects">
                            <span class="stat-badge stat-subjects">
                                <i class="fas fa-book-open"></i> <?php echo $p['subject_count']; ?>
                            </span>
                        </td>
                        <td class="text-center" data-label="Students">
                            <span class="stat-badge stat-students">
                                <i class="fas fa-users"></i> <?php echo $p['student_count']; ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <?php if (($p['status'] ?? 'active') === 'active'): ?>
                                <div class="status-indicator status-active">
                                    <div class="dot dot-active"></div> Active
                                </div>
                            <?php else: ?>
                                <div class="status-indicator status-inactive">
                                    <div class="dot dot-inactive"></div> Inactive
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4" data-label="Manage">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn-action btn-edit-lite" onclick='editProgram(<?php echo json_encode($p); ?>)' title="Edit Program">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-delete-lite" onclick="deleteProgram(<?php echo $p['program_id']; ?>, '<?php echo addslashes($p['program_name']); ?>')" title="Delete Program">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
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
