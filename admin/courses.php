<?php
/**
 * Admin - Course Management
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $code = strtoupper(sanitizeInput($_POST['course_code']));

        if (strlen($classCode) !== 6 || strlen($code) !== 6) {
            redirectWithMessage('courses.php', 'Class Code and Subject Code must be exactly 6 characters.', 'danger');
            exit;
        }

        $name = sanitizeInput($_POST['course_name']);
        $units = intval($_POST['units']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $desc = sanitizeInput($_POST['description']);

        $stmt = $conn->prepare("INSERT INTO courses (class_code, course_code, course_name, program_id, units, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiis", $classCode, $code, $name, $programId, $units, $desc);
        if ($stmt->execute()) {
            redirectWithMessage('courses.php', 'Course created successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['course_id']);
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $code = strtoupper(sanitizeInput($_POST['course_code']));

        if (strlen($classCode) !== 6 || strlen($code) !== 6) {
            redirectWithMessage('courses.php', 'Class Code and Subject Code must be exactly 6 characters.', 'danger');
            exit;
        }

        $name = sanitizeInput($_POST['course_name']);
        $units = intval($_POST['units']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;

        $stmt = $conn->prepare("UPDATE courses SET class_code = ?, course_code = ?, course_name = ?, program_id = ?, units = ? WHERE course_id = ?");
        $stmt->bind_param("sssiii", $classCode, $code, $name, $programId, $units, $id);
        if ($stmt->execute()) {
            redirectWithMessage('courses.php', 'Course updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['course_id']);
        
        // Check for dependencies: are there any class sections for this course?
        $check = $conn->prepare("SELECT COUNT(*) FROM class_sections WHERE course_id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $count = $check->get_result()->fetch_row()[0];
        $check->close();

        if ($count > 0) {
            redirectWithMessage('courses.php', "Cannot delete subject: It is currently assigned to $count class section(s).", 'danger');
        } else {
            $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logAudit(getCurrentUserId(), 'DELETE', 'courses', $id, null, "Deleted subject ID: $id");
                redirectWithMessage('courses.php', 'Subject deleted successfully', 'success');
            } else {
                redirectWithMessage('courses.php', 'Error deleting subject: ' . $conn->error, 'danger');
            }
            $stmt->close();
        }
    }
}

$pageTitle = 'Manage Subjects';
require_once '../includes/header.php';

$programs = [];
$prog_res = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' ORDER BY p.program_name");
while ($p = $prog_res->fetch_assoc()) {
    $programs[] = $p;
}

$courses = $conn->query("
    SELECT c.*, p.program_name 
    FROM courses c 
    LEFT JOIN programs p ON c.program_id = p.program_id 
    ORDER BY c.course_code
");
?>

<style>
    .subjects-table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        padding: 1.25rem 1rem;
        border-top: none;
    }
    .subjects-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        font-size: 0.9rem;
    }
    .subject-icon {
        width: 38px;
        height: 38px;
        background: #f1f5f9;
        color: var(--primary-indigo);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 12px;
        flex-shrink: 0;
    }
    .badge-unit {
        background: rgba(79, 70, 229, 0.1);
        color: var(--primary-indigo);
        font-weight: 700;
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
    }
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
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
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
    <div class="card-header bg-primary text-white d-flex justify-content-between">
        <h5 class="mb-0"><i class="fas fa-book"></i> Subject Management</h5>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Add Subject
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-mobile-card subjects-table data-table">
            <thead>
                <tr>
                    <th class="ps-4">Classification</th>
                    <th>Subject Code</th>
                    <th>Subj Description</th>
                    <th>Program Assignment</th>
                    <th class="text-center">Units</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Manage</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($c = $courses->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4" data-label="Classification">
                        <div class="d-flex align-items-center">
                            <div class="subject-icon">
                                <?php echo substr($c['class_code'] ?? 'S', 0, 1); ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['class_code'] ?? ''); ?></div>
                                <div class="text-xs text-muted">ID: #<?php echo str_pad($c['course_id'], 4, '0', STR_PAD_LEFT); ?></div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Subject Code">
                        <span class="badge bg-light text-primary border px-2 py-1"><?php echo htmlspecialchars($c['course_code'] ?? ''); ?></span>
                    </td>
                    <td data-label="Subject Description">
                        <div class="fw-semibold"><?php echo htmlspecialchars($c['course_name'] ?? ''); ?></div>
                        <div class="small text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($c['description'] ?? 'No additional details provided.'); ?>
                        </div>
                    </td>
                    <td data-label="Program">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-graduation-cap me-2 text-muted" style="font-size: 0.8rem;"></i>
                            <span class="text-muted small"><?php echo htmlspecialchars($c['program_name'] ?? 'General/N/A'); ?></span>
                        </div>
                    </td>
                    <td class="text-center" data-label="Units">
                        <span class="badge-unit"><?php echo $c['units']; ?></span>
                    </td>
                    <td data-label="Status">
                        <?php if (($c['status'] ?? 'active') === 'active'): ?>
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
                            <button class="btn-action btn-edit-lite" onclick='editCourse(<?php echo json_encode($c); ?>)' title="Edit Subject">
                                <i class="fas fa-pen-fancy"></i>
                            </button>
                            <button class="btn-action btn-delete-lite" onclick="deleteCourse(<?php echo $c['course_id']; ?>, '<?php echo addslashes($c['course_name']); ?>')" title="Remove Subject">
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

<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Add Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Class Code (Exactly 6 chars)</label>
                        <input type="text" name="class_code" class="form-control" placeholder="e.g. IT101A" required maxlength="6" minlength="6" pattern=".{6,6}" title="Must be exactly 6 characters">
                    </div>
                    <div class="mb-3">
                        <label>Subject Code (Exactly 6 chars)</label>
                        <input type="text" name="course_code" class="form-control" required placeholder="e.g. ITE101" maxlength="6" minlength="6" pattern=".{6,6}" title="Must be exactly 6 characters">
                    </div>
                    <div class="mb-3">
                        <label>Subject Description</label>
                        <input type="text" name="course_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Program (Course)</label>
                        <select name="program_id" class="form-select">
                            <option value="">-- No Program --</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name'] . ' (' . $p['title_diploma_program'] . ')'); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Units</label>
                        <input type="number" name="units" class="form-control" value="3" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="course_id" id="edit_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Class Code (Exactly 6 chars)</label>
                        <input type="text" name="class_code" id="edit_class_code" class="form-control" placeholder="e.g. IT101A" required maxlength="6" minlength="6" pattern=".{6,6}" title="Must be exactly 6 characters">
                    </div>
                    <div class="mb-3">
                        <label>Subject Code (Exactly 6 chars)</label>
                        <input type="text" name="course_code" id="edit_code" class="form-control" required maxlength="6" minlength="6" pattern=".{6,6}" title="Must be exactly 6 characters">
                    </div>
                    <div class="mb-3">
                        <label>Subject Description</label>
                        <input type="text" name="course_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Program (Course)</label>
                        <select name="program_id" id="edit_program_id" class="form-select">
                            <option value="">-- No Program --</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Units</label>
                        <input type="number" name="units" id="edit_units" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editCourse(c) {
    document.getElementById('edit_id').value = c.course_id;
    document.getElementById('edit_class_code').value = c.class_code || '';
    document.getElementById('edit_code').value = c.course_code;
    document.getElementById('edit_name').value = c.course_name;
    document.getElementById('edit_program_id').value = c.program_id || '';
    document.getElementById('edit_units').value = c.units;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteCourse(id, name) {
    if (confirm('Are you sure you want to remove "' + name + '"? This action cannot be undone.')) {
        const form = document.getElementById('deleteForm');
        form.querySelector('input[name="course_id"]').value = id;
        form.submit();
    }
}
</script>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="course_id" value="">
</form>

<?php require_once '../includes/footer.php'; ?>
