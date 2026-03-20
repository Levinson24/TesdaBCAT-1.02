<?php
/**
 * Course Management
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff']);
$conn = getDBConnection();

$userRole = getCurrentUserRole();
$userProfile = getUserProfile(getCurrentUserId(), $userRole);
$deptId = $userProfile['dept_id'] ?? 0;
$isStaff = ($userRole === 'registrar_staff');

// Handle form submissions BEFORE header output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('courses.php', 'Invalid security token. Please try again.', 'danger');
    }

    // Enforce department restriction for Registrar Staff
    if ($isStaff) {
        $checkDeptId = $deptId;
        
        // If updating or deleting, verify the course belongs to the staff's department
        if (isset($_POST['course_id'])) {
            $checkStmt = $conn->prepare("SELECT dept_id FROM courses WHERE course_id = ?");
            $checkStmt->bind_param("i", $_POST['course_id']);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result()->fetch_assoc();
            if ($checkRes && $checkRes['dept_id'] != $deptId) {
                redirectWithMessage('courses.php', 'Unauthorized: Subject belongs to another department.', 'danger');
            }
        }
    }
    if ($_POST['action'] === 'create') {
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $courseCode = strtoupper(sanitizeInput($_POST['course_code']));

        if (strlen($classCode) !== 6 || strlen($courseCode) !== 6) {
            redirectWithMessage('courses.php', 'Class Code and Subject Code must be exactly 6 characters.', 'danger');
            exit;
        }

        $courseName = sanitizeInput($_POST['course_name']);
        $preReq = sanitizeInput($_POST['pre_requisites']);
        $units = floatval($_POST['units']);
        $lecHrs = floatval($_POST['lec_hrs']);
        $labHrs = floatval($_POST['lab_hrs']);
        $lecU = floatval($_POST['lec_units']);
        $labU = floatval($_POST['lab_units']);
        $courseType = sanitizeInput($_POST['course_type']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $description = sanitizeInput($_POST['description']);

        // Get dept_id from program if not explicitly set (or reinforce it)
        $courseDeptId = null;
        if (!empty($programId)) {
            $pStmt = $conn->prepare("SELECT dept_id FROM programs WHERE program_id = ?");
            $pStmt->bind_param("i", $programId);
            $pStmt->execute();
            $pRes = $pStmt->get_result()->fetch_assoc();
            $courseDeptId = $pRes['dept_id'] ?? null;
        }
        
        if ($isStaff) {
            $courseDeptId = $deptId; // Always force their department
        }

        $stmt = $conn->prepare("INSERT INTO courses (class_code, course_code, course_name, pre_requisites, units, lec_hrs, lab_hrs, lec_units, lab_units, course_type, program_id, dept_id, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssssdddddsiss", $classCode, $courseCode, $courseName, $preReq, $units, $lecHrs, $labHrs, $lecU, $labU, $courseType, $programId, $courseDeptId, $description);
        if ($stmt->execute()) {
            logAudit(getCurrentUserId(), 'CREATE', 'courses', $conn->insert_id, null, "Created course: $courseCode");
            redirectWithMessage('courses.php', 'Course created successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $courseId = intval($_POST['course_id']);
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $courseCode = strtoupper(sanitizeInput($_POST['course_code']));

        if (strlen($classCode) !== 6 || strlen($courseCode) !== 6) {
            redirectWithMessage('courses.php', 'Class Code and Subject Code must be exactly 6 characters.', 'danger');
            exit;
        }

        $courseName = sanitizeInput($_POST['course_name']);
        $preReq = sanitizeInput($_POST['pre_requisites']);
        $units = floatval($_POST['units']);
        $lecHrs = floatval($_POST['lec_hrs']);
        $labHrs = floatval($_POST['lab_hrs']);
        $lecU = floatval($_POST['lec_units']);
        $labU = floatval($_POST['lab_units']);
        $courseType = sanitizeInput($_POST['course_type']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $description = sanitizeInput($_POST['description']);
        $status = sanitizeInput($_POST['status']);

        // Get dept_id from program
        $courseDeptId = null;
        if (!empty($programId)) {
            $pStmt = $conn->prepare("SELECT dept_id FROM programs WHERE program_id = ?");
            $pStmt->bind_param("i", $programId);
            $pStmt->execute();
            $pRes = $pStmt->get_result()->fetch_assoc();
            $courseDeptId = $pRes['dept_id'] ?? null;
        }

        if ($isStaff) {
            $courseDeptId = $deptId; // Always force their department
        }

        $stmt = $conn->prepare("UPDATE courses SET class_code = ?, course_code = ?, course_name = ?, pre_requisites = ?, units = ?, lec_hrs = ?, lab_hrs = ?, lec_units = ?, lab_units = ?, course_type = ?, program_id = ?, dept_id = ?, description = ?, status = ? WHERE course_id = ?");
        $stmt->bind_param("ssssdddddsisssi", $classCode, $courseCode, $courseName, $preReq, $units, $lecHrs, $labHrs, $lecU, $labU, $courseType, $programId, $courseDeptId, $description, $status, $courseId);
        if ($stmt->execute()) {
            logAudit(getCurrentUserId(), 'UPDATE', 'courses', $courseId, null, "Updated course: $courseCode");
            redirectWithMessage('courses.php', 'Course updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        if (getCurrentUserRole() !== 'registrar') {
            redirectWithMessage('courses.php', 'Unauthorized: Only the Head Registrar can delete subjects.', 'danger');
        }
        $courseId = intval($_POST['course_id']);
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->bind_param("i", $courseId);
        if ($stmt->execute()) {
            logAudit(getCurrentUserId(), 'DELETE', 'courses', $courseId, null, "Deleted course ID: $courseId");
            redirectWithMessage('courses.php', 'Course deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Courses';
require_once '../includes/header.php';

// === Premium Styles ===
?>
<style>
    .premium-card {
        border-radius: 1rem;
    }
    .bg-dark-navy {
        background-color: #0f172a !important;
    }
    .courses-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .courses-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        font-size: 0.85rem;
    }
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
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.25rem 0.75rem;
        border-radius: 2rem;
    }
    .status-pill-active { background-color: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
    .status-pill-inactive { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }
    
    .btn-action-edit { color: #2563eb; }
    .btn-action-edit:hover { background: #2563eb; color: white; }
    .btn-action-delete { color: #dc2626; }
    .btn-action-delete:hover { background: #dc2626; color: white; }

    /* Premium Action Buttons */
    .btn-premium-edit, .btn-premium-delete {
        width: 32px; height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    .btn-premium-edit {
        background-color: #eff6ff;
        color: #2563eb !important;
    }
    .btn-premium-edit:hover {
        background-color: #2563eb;
        color: #fff !important;
        box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
    }
    .btn-premium-delete {
        background-color: #fef2f2;
        color: #ef4444 !important;
    }
    .btn-premium-delete:hover {
        background-color: #ef4444;
        color: #fff !important;
        box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
    }
</style>
<?php
// === Data Fetching (Re-inserting what was accidentally removed) ===
$progWhere = $isStaff ? " AND p.dept_id = $deptId" : "";
$prog_res = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' $progWhere ORDER BY p.program_name ASC");
$programs_list = [];
while ($p = $prog_res->fetch_assoc()) {
    $programs_list[] = $p;
}

$courseWhere = $isStaff ? " WHERE c.dept_id = $deptId " : "";
$courses = $conn->query("
    SELECT c.*, p.program_name 
    FROM courses c 
    LEFT JOIN programs p ON c.program_id = p.program_id 
    $courseWhere
    ORDER BY c.course_code
");
?>

<div class="card premium-card shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-book-bookmark me-2 text-warning"></i> Subject Registry
        </h5>
        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add Subject
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 courses-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">CLASSIFICATION</th>
                        <th>SUBJECT CODE</th>
                        <th>SUBJ DESCRIPTION</th>
                        <th>TYPE</th>
                        <th>PROGRAM</th>
                        <th class="text-center">UNITS</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">MANAGE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $courses->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="subject-icon-box bg-primary bg-opacity-10 text-primary border-0">
                                    <i class="fas <?php echo ($c['course_type'] === 'Major') ? 'fa-award' : 'fa-book'; ?> small px-1"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark lh-1"><?php echo htmlspecialchars($c['class_code'] ?? 'N/A'); ?></div>
                                    <div class="text-muted small mt-1" style="font-size: 0.65rem;">ID: #<?php echo str_pad($c['course_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-primary border px-2 py-1" style="font-size: 0.75rem;"><?php echo htmlspecialchars($c['course_code'] ?? ''); ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark lh-sm"><?php echo htmlspecialchars($c['course_name'] ?? ''); ?></div>
                            <div class="text-muted small text-truncate mt-1" style="max-width: 250px; font-size: 0.75rem;">
                                <?php echo htmlspecialchars($c['description'] ?? 'Standard Subject Curriculum'); ?>
                            </div>
                        </td>
                        <td>
                            <?php if (($c['course_type'] ?? 'Minor') === 'Major'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border-0 px-2 fw-bold" style="font-size: 0.7rem;">MAJOR</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border-0 px-2 fw-bold" style="font-size: 0.7rem;">MINOR</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center text-muted">
                                <i class="fas fa-graduation-cap me-2 small"></i>
                                <span class="small fw-semibold"><?php echo htmlspecialchars($c['program_name'] ?? 'Unassigned'); ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="stat-pill stat-blue"><?php echo $c['units']; ?></span>
                        </td>
                        <td>
                            <?php if (($c['status'] ?? 'active') === 'active'): ?>
                                <div class="status-pill status-pill-active">
                                    <div class="status-dot" style="background: #22c55e;"></div> Active
                                </div>
                            <?php else: ?>
                                <div class="status-pill status-pill-inactive">
                                    <div class="status-dot" style="background: #94a3b8;"></div> Inactive
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn-premium-edit" onclick='editCourse(<?php echo json_encode($c); ?>)' title="Edit Course">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (getCurrentUserRole() === 'registrar'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this course?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?php echo $c['course_id']; ?>">
                                    <button type="submit" class="btn-premium-delete btn-action-delete" title="Delete Course">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-book-plus me-2 text-warning"></i>Add Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                        <label>Pre-requisite(s)</label>
                        <input type="text" name="pre_requisites" class="form-control" placeholder="e.g. MATH 1, or None">
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label>Lec Hours</label>
                            <input type="number" name="lec_hrs" class="form-control calc-hrs-add" step="0.5" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Lec Units</label>
                            <input type="number" name="lec_units" class="form-control calc-units-add" step="0.5" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Lab Hours</label>
                            <input type="number" name="lab_hrs" class="form-control calc-hrs-add" step="0.5" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Lab Units</label>
                            <input type="number" name="lab_units" class="form-control calc-units-add" step="0.5" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Total Units Earned</label>
                        <input type="number" name="units" id="add_total_units" class="form-control bg-light" value="0" readonly>
                        <small class="text-muted">Auto-calculated: Lec Units + Lab Units</small>
                    </div>
                    <div class="mb-3">
                        <label class="d-block">Subject Type</label>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="radio" name="course_type" id="add_type_minor" value="Minor" checked>
                            <label class="form-check-label" for="add_type_minor">Minor / Gen Ed</label>
                        </div>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="radio" name="course_type" id="add_type_major" value="Major">
                            <label class="form-check-label" for="add_type_major">Major Subject</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Program (Course)</label>
                        <select name="program_id" class="form-select" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs_list as $prog): ?>
                                <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['program_name'] . ' (' . $prog['title_diploma_program'] . ')'); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> Create Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2 text-warning"></i>Edit Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Class Code (Exactly 6 chars)</label>
                        <input type="text" name="class_code" id="edit_class_code" class="form-control" placeholder="e.g. IT101A" required maxlength="6" minlength="6" pattern=".{6,6}" title="Must be exactly 6 characters">
                    </div>
                    <div class="mb-3">
                        <label>Subject Code (Exactly 6 chars)</label>
                        <input type="text" name="course_code" id="edit_course_code" class="form-control" required maxlength="6" minlength="6" pattern=".{6,6}" title="Must be exactly 6 characters">
                    </div>
                    <div class="mb-3">
                        <label>Subject Description</label>
                        <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Pre-requisite(s)</label>
                        <input type="text" name="pre_requisites" id="edit_pre_requisites" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label>Lec Hours</label>
                            <input type="number" name="lec_hrs" id="edit_lec_hrs" class="form-control calc-hrs-edit" step="0.5">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Lec Units</label>
                            <input type="number" name="lec_units" id="edit_lec_units" class="form-control calc-units-edit" step="0.5">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Lab Hours</label>
                            <input type="number" name="lab_hrs" id="edit_lab_hrs" class="form-control calc-hrs-edit" step="0.5">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Lab Units</label>
                            <input type="number" name="lab_units" id="edit_lab_units" class="form-control calc-units-edit" step="0.5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Total Units Earned</label>
                        <input type="number" name="units" id="edit_units" class="form-control bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="d-block">Subject Type</label>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="radio" name="course_type" id="edit_type_minor" value="Minor">
                            <label class="form-check-label" for="edit_type_minor">Minor / Gen Ed</label>
                        </div>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="radio" name="course_type" id="edit_type_major" value="Major">
                            <label class="form-check-label" for="edit_type_major">Major Subject</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Program (Course)</label>
                        <select name="program_id" id="edit_program_id" class="form-select" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs_list as $prog): ?>
                                <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['program_name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> Update Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCourse(data) {
    document.getElementById('edit_course_id').value = data.course_id;
    document.getElementById('edit_class_code').value = data.class_code || '';
    document.getElementById('edit_course_code').value = data.course_code;
    document.getElementById('edit_course_name').value = data.course_name;
    document.getElementById('edit_pre_requisites').value = data.pre_requisites || '';
    
    document.getElementById('edit_lec_hrs').value = data.lec_hrs || 0;
    document.getElementById('edit_lec_units').value = data.lec_units || 0;
    document.getElementById('edit_lab_hrs').value = data.lab_hrs || 0;
    document.getElementById('edit_lab_units').value = data.lab_units || 0;
    document.getElementById('edit_units').value = data.units;
    
    if (data.course_type === 'Major') {
        document.getElementById('edit_type_major').checked = true;
    } else {
        document.getElementById('edit_type_minor').checked = true;
    }

    document.getElementById('edit_program_id').value = data.program_id || '';
    document.getElementById('edit_description').value = data.description || '';
    document.getElementById('edit_status').value = data.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Auto-calculation logic
document.querySelectorAll('.calc-units-add').forEach(el => {
    el.addEventListener('input', () => {
        let lec = parseFloat(document.querySelector('input[name="lec_units"]').value) || 0;
        let lab = parseFloat(document.querySelector('input[name="lab_units"]').value) || 0;
        document.getElementById('add_total_units').value = lec + lab;
    });
});

document.querySelectorAll('.calc-units-edit').forEach(el => {
    el.addEventListener('input', () => {
        let lec = parseFloat(document.getElementById('edit_lec_units').value) || 0;
        let lab = parseFloat(document.getElementById('edit_lab_units').value) || 0;
        document.getElementById('edit_units').value = lec + lab;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
