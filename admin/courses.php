<?php
/**
 * Admin - Subject Management (Curriculum)
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $subjectId = strtoupper(sanitizeInput($_POST['subject_id']));
        $subjectName = sanitizeInput($_POST['subject_name']);
        $units = intval($_POST['units']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $desc = sanitizeInput($_POST['description']);

        $conn->begin_transaction();
        try {
            // 1. Ensure Subject exists
            $checkSubj = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
            $checkSubj->bind_param("s", $subjectId);
            $checkSubj->execute();
            if ($checkSubj->get_result()->num_rows === 0) {
                $insSubj = $conn->prepare("INSERT INTO subjects (subject_id, subject_name, units, description) VALUES (?, ?, ?, ?)");
                $insSubj->bind_param("ssis", $subjectId, $subjectName, $units, $desc);
                $insSubj->execute();
            }

            // 2. Create Curriculum entry
            $stmt = $conn->prepare("INSERT INTO curriculum (class_code, subject_id, program_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $classCode, $subjectId, $programId);
            $stmt->execute();
            
            $conn->commit();
            redirectWithMessage('courses.php', 'Subject added to curriculum successfully', 'success');
        } catch (Exception $e) {
            $conn->rollback();
            redirectWithMessage('courses.php', 'Error: ' . $e->getMessage(), 'danger');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['curriculum_id']);
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $subjectId = strtoupper(sanitizeInput($_POST['subject_id']));
        $subjectName = sanitizeInput($_POST['subject_name']);
        $units = intval($_POST['units']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;

        $conn->begin_transaction();
        try {
            // Update Curriculum
            $stmt = $conn->prepare("UPDATE curriculum SET class_code = ?, subject_id = ?, program_id = ? WHERE curriculum_id = ?");
            $stmt->bind_param("ssii", $classCode, $subjectId, $programId, $id);
            $stmt->execute();

            // Optionally update global subject info
            $stmtSubj = $conn->prepare("UPDATE subjects SET subject_name = ?, units = ? WHERE subject_id = ?");
            $stmtSubj->bind_param("sis", $subjectName, $units, $subjectId);
            $stmtSubj->execute();

            $conn->commit();
            redirectWithMessage('courses.php', 'Subject updated successfully', 'success');
        } catch (Exception $e) {
            $conn->rollback();
            redirectWithMessage('courses.php', 'Error: ' . $e->getMessage(), 'danger');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['curriculum_id']);
        
        // Check for dependencies: are there any class sections for this curriculum entry?
        $check = $conn->prepare("SELECT COUNT(*) FROM class_sections WHERE curriculum_id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $count = $check->get_result()->fetch_row()[0];
        $check->close();

        if ($count > 0) {
            redirectWithMessage('courses.php', "Cannot remove: It is assigned to $count class section(s).", 'danger');
        } else {
            $stmt = $conn->prepare("DELETE FROM curriculum WHERE curriculum_id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logAudit(getCurrentUserId(), 'DELETE', 'curriculum', $id, null, "Removed curriculum entry ID: $id");
                redirectWithMessage('courses.php', 'Subject removed from curriculum', 'success');
            } else {
                redirectWithMessage('courses.php', 'Error: ' . $conn->error, 'danger');
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

$curriculum = $conn->query("
    SELECT c.*, s.subject_name, s.units, s.description, p.program_name 
    FROM curriculum c 
    JOIN subjects s ON c.subject_id = s.subject_id
    LEFT JOIN programs p ON c.program_id = p.program_id 
    ORDER BY s.subject_id
");

$all_subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_id");
$subjects_list = [];
while ($s = $all_subjects->fetch_assoc()) {
    $subjects_list[] = $s;
}
?>

<style>
    .subject-icon-box {
        width: 32px;
        height: 32px;
        background: #f1f5f9;
        color: #6366f1;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        margin-right: 12px;
        flex-shrink: 0;
        border: 1px solid #e2e8f0;
        font-size: 0.8rem;
    }
    .stat-pill {
        font-weight: 700;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .stat-blue { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
</style>

<div class="card premium-card shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-book me-2 text-info"></i> Curriculum Management
        </h5>
        <button class="btn btn-light btn-sm rounded px-3 shadow-sm fw-bold border-0" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1 text-primary"></i> Add Subject to Program
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 subjects-table premium-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">CLASS CODE</th>
                        <th>SUBJECT ID</th>
                        <th>SUBJECT NAME</th>
                        <th>PROGRAM</th>
                        <th class="text-center">UNITS</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">MANAGE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $curriculum->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="subject-icon-box">
                                    <?php echo substr($c['class_code'] ?? 'S', 0, 1); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark lh-1"><?php echo htmlspecialchars($c['class_code'] ?? ''); ?></div>
                                    <div class="text-muted small mt-1" style="font-size: 0.65rem;">CUR: #<?php echo str_pad($c['curriculum_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-primary border px-2 py-1" style="font-size: 0.75rem;"><?php echo htmlspecialchars($c['subject_id'] ?? ''); ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark lh-sm"><?php echo htmlspecialchars($c['subject_name'] ?? ''); ?></div>
                            <div class="text-muted small text-truncate mt-1" style="max-width: 250px; font-size: 0.75rem;">
                                <?php echo htmlspecialchars($c['description'] ?? 'Global Subject Definition'); ?>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center text-muted">
                                <i class="fas fa-graduation-cap me-2 small"></i>
                                <span class="small fw-semibold"><?php echo htmlspecialchars($c['program_name'] ?? 'General'); ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="stat-pill stat-blue"><?php echo $c['units']; ?></span>
                        </td>
                        <td>
                            <span class="status-pill <?php echo ($c['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <div class="status-dot" style="background: <?php echo ($c['status'] ?? 'active') === 'active' ? '#22c55e' : '#94a3b8'; ?>;"></div> <?php echo ucfirst($c['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4" data-label="Manage">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn-premium-edit" onclick='editSubject(<?php echo json_encode($c); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-premium-delete" onclick="deleteSubject(<?php echo $c['curriculum_id']; ?>, '<?php echo addslashes($c['subject_name']); ?>')">
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

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form method="POST" autocomplete="off" class="w-100">
            <input type="hidden" name="action" value="create">
            <div class="modal-content border-0">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-book"></i>
                        <span>Add Subject to Curriculum</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-info-circle me-2"></i>Subject Assignment</span>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Class Instance Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="class_code" class="form-control" placeholder="e.g. MATH-101A" required>
                                    <i class="fas fa-barcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Subject ID (Uniform Code)</label>
                                <div class="input-wrapper">
                                    <input type="text" name="subject_id" id="add_subject_id" list="subjects_datalist" class="form-control" placeholder="e.g. MATH101" required onchange="fillSubjectDetails(this.value)">
                                    <i class="fas fa-id-card"></i>
                                    <datalist id="subjects_datalist">
                                        <?php foreach ($subjects_list as $s): ?>
                                            <option value="<?php echo $s['subject_id']; ?>"><?php echo htmlspecialchars($s['subject_name']); ?></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Units</label>
                                <div class="input-wrapper">
                                    <input type="number" name="units" id="add_units" class="form-control" value="3" required>
                                    <i class="fas fa-sort-numeric-up"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Subject Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="subject_name" id="add_subject_name" class="form-control" placeholder="e.g. College Algebra" required>
                                    <i class="fas fa-quote-left"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Target Program</label>
                                <div class="input-wrapper">
                                    <select name="program_id" class="form-select">
                                        <option value="">-- General / All --</option>
                                        <?php foreach ($programs as $p): ?>
                                            <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="premium-input-group">
                                <label>Description</label>
                                <div class="input-wrapper">
                                    <input type="text" name="description" id="add_description" class="form-control" placeholder="Brief subject description...">
                                    <i class="fas fa-align-left"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-save me-2"></i>Assign Subject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form method="POST" autocomplete="off" class="w-100">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="curriculum_id" id="edit_curriculum_id">
            <div class="modal-content border-0">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i>
                        <span>Edit Curriculum Entry</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Class Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="class_code" id="edit_class_code" class="form-control" required>
                                    <i class="fas fa-barcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Subject ID</label>
                                <div class="input-wrapper">
                                    <input type="text" name="subject_id" id="edit_subject_id" class="form-control" readonly>
                                    <i class="fas fa-id-card"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Units</label>
                                <div class="input-wrapper">
                                    <input type="number" name="units" id="edit_units" class="form-control" required>
                                    <i class="fas fa-sort-numeric-up"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Subject Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="subject_name" id="edit_subject_name" class="form-control" required>
                                    <i class="fas fa-quote-left"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Program</label>
                                <div class="input-wrapper">
                                    <select name="program_id" id="edit_program_id" class="form-select">
                                        <option value="">-- General --</option>
                                        <?php foreach ($programs as $p): ?>
                                            <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-sync me-2"></i>Update Subject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const subjectsData = <?php echo json_encode($subjects_list); ?>;

function fillSubjectDetails(val) {
    const subj = subjectsData.find(s => s.subject_id === val);
    if (subj) {
        document.getElementById('add_subject_name').value = subj.subject_name;
        document.getElementById('add_units').value = subj.units;
        document.getElementById('add_description').value = subj.description || '';
    }
}

function editSubject(c) {
    document.getElementById('edit_curriculum_id').value = c.curriculum_id;
    document.getElementById('edit_class_code').value = c.class_code || '';
    document.getElementById('edit_subject_id').value = c.subject_id;
    document.getElementById('edit_subject_name').value = c.subject_name;
    document.getElementById('edit_program_id').value = c.program_id || '';
    document.getElementById('edit_units').value = c.units;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteSubject(id, name) {
    if (confirm('Are you sure you want to remove "' + name + '" from this curriculum?')) {
        const form = document.getElementById('deleteForm');
        form.querySelector('input[name="curriculum_id"]').value = id;
        form.submit();
    }
}
</script>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="curriculum_id" value="">
</form>

<?php require_once '../includes/footer.php'; ?>
