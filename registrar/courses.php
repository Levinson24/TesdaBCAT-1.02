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
        
        // If updating or deleting, verify the curriculum entry belongs to the staff's department
        if (isset($_POST['curriculum_id'])) {
            $checkStmt = $conn->prepare("SELECT dept_id FROM curriculum WHERE curriculum_id = ?");
            $checkStmt->bind_param("i", $_POST['curriculum_id']);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result()->fetch_assoc();
            if ($checkRes && $checkRes['dept_id'] != $deptId) {
                redirectWithMessage('courses.php', 'Unauthorized: Subject belongs to another department.', 'danger');
            }
        }
    }
    if ($_POST['action'] === 'create') {
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $subjectId = strtoupper(sanitizeInput($_POST['subject_id'])); // This was course_code

        if (strlen($classCode) !== 6 || strlen($subjectId) !== 6) {
            redirectWithMessage('courses.php', 'Class Code and Subject ID must be exactly 6 characters.', 'danger');
            exit;
        }

        $subjectName = sanitizeInput($_POST['subject_name']);
        $preReq = sanitizeInput($_POST['pre_requisites']);
        $units = floatval($_POST['units']);
        $lecHrs = floatval($_POST['lec_hrs']);
        $labHrs = floatval($_POST['lab_hrs']);
        $lecU = floatval($_POST['lec_units']);
        $labU = floatval($_POST['lab_units']);
        $courseType = sanitizeInput($_POST['course_type']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $description = sanitizeInput($_POST['description']);

        $conn->begin_transaction();
        try {
            // 1. Ensure Subject exists
            $checkSubj = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
            $checkSubj->bind_param("s", $subjectId);
            $checkSubj->execute();
            if ($checkSubj->get_result()->num_rows === 0) {
                $insSubj = $conn->prepare("INSERT INTO subjects (subject_id, subject_name, units, description, pre_requisites, lec_hrs, lab_hrs, lec_units, lab_units, course_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insSubj->bind_param("ssdssddddd", $subjectId, $subjectName, $units, $description, $preReq, $lecHrs, $labHrs, $lecU, $labU, $courseType);
                $insSubj->execute();
            }

            // 2. Create Curriculum entry
            $courseDeptId = null;
            if (!empty($programId)) {
                $pStmt = $conn->prepare("SELECT dept_id FROM programs WHERE program_id = ?");
                $pStmt->bind_param("i", $programId);
                $pStmt->execute();
                $pRes = $pStmt->get_result()->fetch_assoc();
                $courseDeptId = $pRes['dept_id'] ?? null;
            }
            if ($isStaff) $courseDeptId = $deptId;

            $insCur = $conn->prepare("INSERT INTO curriculum (class_code, subject_id, program_id, dept_id, status) VALUES (?, ?, ?, ?, 'active')");
            $insCur->bind_param("ssii", $classCode, $subjectId, $programId, $courseDeptId);
            $insCur->execute();
            $newId = $conn->insert_id;

            $conn->commit();
            logAudit(getCurrentUserId(), 'CREATE', 'curriculum', $newId, null, "Created curriculum entry for subject: $subjectId");
            redirectWithMessage('courses.php', 'Subject assigned to curriculum successfully', 'success');
        } catch (Exception $e) {
            $conn->rollback();
            redirectWithMessage('courses.php', 'Error: ' . $e->getMessage(), 'danger');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $curriculumId = intval($_POST['curriculum_id']);
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $subjectId = strtoupper(sanitizeInput($_POST['subject_id']));

        if (strlen($classCode) !== 6 || strlen($subjectId) !== 6) {
            redirectWithMessage('courses.php', 'Codes must be exactly 6 characters.', 'danger');
            exit;
        }

        $subjectName = sanitizeInput($_POST['subject_name']);
        $units = floatval($_POST['units']);
        $courseType = sanitizeInput($_POST['course_type']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $status = sanitizeInput($_POST['status']);

        $conn->begin_transaction();
        try {
            // Update Subject details
            $updSubj = $conn->prepare("UPDATE subjects SET subject_name = ?, units = ?, course_type = ?, description = ?, pre_requisites = ?, lec_hrs = ?, lab_hrs = ?, lec_units = ?, lab_units = ? WHERE subject_id = ?");
            $desc = sanitizeInput($_POST['description']);
            $pre = sanitizeInput($_POST['pre_requisites']);
            $lHrs = floatval($_POST['lec_hrs']);
            $bHrs = floatval($_POST['lab_hrs']);
            $lU = floatval($_POST['lec_units']);
            $bU = floatval($_POST['lab_units']);
            $updSubj->bind_param("sdssssddds", $subjectName, $units, $courseType, $desc, $pre, $lHrs, $bHrs, $lU, $bU, $subjectId);
            $updSubj->execute();

            // Update Curriculum entry
            $updCur = $conn->prepare("UPDATE curriculum SET class_code = ?, subject_id = ?, program_id = ?, status = ? WHERE curriculum_id = ?");
            $updCur->bind_param("ssisi", $classCode, $subjectId, $programId, $status, $curriculumId);
            $updCur->execute();

            $conn->commit();
            logAudit(getCurrentUserId(), 'UPDATE', 'curriculum', $curriculumId, null, "Updated curriculum entry for subject: $subjectId");
            redirectWithMessage('courses.php', 'Update successful', 'success');
        } catch (Exception $e) {
            $conn->rollback();
            redirectWithMessage('courses.php', 'Error: ' . $e->getMessage(), 'danger');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        if (getCurrentUserRole() !== 'registrar') {
            redirectWithMessage('courses.php', 'Unauthorized: Only the Head Registrar can delete subjects.', 'danger');
        }
        $curriculumId = intval($_POST['curriculum_id']);
        $stmt = $conn->prepare("DELETE FROM curriculum WHERE curriculum_id = ?");
        $stmt->bind_param("i", $curriculumId);
        if ($stmt->execute()) {
            logAudit(getCurrentUserId(), 'DELETE', 'curriculum', $curriculumId, null, "Deleted curriculum entry ID: $curriculumId");
            redirectWithMessage('courses.php', 'Subject removed from curriculum successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Courses';
require_once '../includes/header.php';

// === Premium Styles ===
?>
<<style>
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
<?php
// === Data Fetching (Re-inserting what was accidentally removed) ===
$progWhere = $isStaff ? " AND p.dept_id = $deptId" : "";
$prog_res = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' $progWhere ORDER BY p.program_name ASC");
$programs_list = [];
while ($p = $prog_res->fetch_assoc()) {
    $programs_list[] = $p;
}

$courseWhere = $isStaff ? " WHERE cur.dept_id = $deptId " : "";
$courses = $conn->query("
    SELECT 
        cur.*, 
        s.subject_name, 
        s.units, 
        s.course_type, 
        s.description, 
        s.pre_requisites, 
        s.lec_hrs, 
        s.lab_hrs, 
        s.lec_units, 
        s.lab_units,
        p.program_name 
    FROM curriculum cur
    JOIN subjects s ON cur.subject_id = s.subject_id
    LEFT JOIN programs p ON cur.program_id = p.program_id 
    $courseWhere
    ORDER BY cur.subject_id
");
?>

<div class="card premium-card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top-4 gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-book me-2 text-warning"></i> Subject Registry
        </h5>
        
        <div class="search-box-container">
            <div class="input-group input-group-sm rounded-pill overflow-hidden border-0 shadow-sm" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                <span class="input-group-text bg-transparent border-0 text-white-50 ps-3">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="courseSearchInput" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="Search Code, Name or Program..." onkeyup="filterCourses()" style="box-shadow: none;">
                <span class="input-group-text bg-transparent border-0 text-white-50 pe-3" id="searchCounter" style="font-size: 0.75rem; font-weight: 600;"></span>
            </div>
        </div>

        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-1"></i> Add Subject
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 courses-table premium-table data-table">
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
                                    <div class="text-muted small mt-1" style="font-size: 0.65rem;">CURR-ID: #<?php echo str_pad($c['curriculum_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-primary border px-2 py-1" style="font-size: 0.75rem;"><?php echo htmlspecialchars($c['subject_id'] ?? ''); ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark lh-sm"><?php echo htmlspecialchars($c['subject_name'] ?? ''); ?></div>
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
                            <span class="status-pill status-<?php echo ($c['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                                <div class="status-dot" style="background: <?php echo ($c['status'] ?? 'active') === 'active' ? '#22c55e' : '#94a3b8'; ?>;"></div> <?php echo ucfirst($c['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn-premium-edit" onclick='editCourse(<?php echo json_encode($c); ?>)' title="Edit Course">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (getCurrentUserRole() === 'registrar'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this subject from the curriculum?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="curriculum_id" value="<?php echo $c['curriculum_id']; ?>">
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
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off" class="w-100">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header modal-premium-header gradient-navy">
                    <h5 class="modal-title">
                        <i class="fas fa-book-plus"></i>
                        <span>Add Subject</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-info-circle me-2"></i>Subject Identity</span>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Class Code (6 chars)</label>
                                <div class="input-wrapper">
                                    <input type="text" name="class_code" class="form-control" placeholder="e.g. IT101A" required maxlength="6" minlength="6" pattern=".{6,6}">
                                    <i class="fas fa-hashtag"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Subject ID (6 chars)</label>
                                <div class="input-wrapper">
                                    <input type="text" name="subject_id" class="form-control" required placeholder="e.g. ITE101" maxlength="6" minlength="6" pattern=".{6,6}">
                                    <i class="fas fa-barcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Subject Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="subject_name" class="form-control" required>
                                    <i class="fas fa-font"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="premium-input-group">
                                <label>Full Description (Optional)</label>
                                <div class="input-wrapper">
                                    <textarea name="description" class="form-control pt-3" style="min-height: 80px;"></textarea>
                                    <i class="fas fa-align-left" style="top: 1.5rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-graduation-cap me-2"></i>Academic Requirements</span>
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="premium-input-group">
                                <label>Pre-requisite(s)</label>
                                <div class="input-wrapper">
                                    <input type="text" name="pre_requisites" class="form-control" placeholder="e.g. MATH 1, or None">
                                    <i class="fas fa-link"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Program (Course)</label>
                                <div class="input-wrapper">
                                    <select name="program_id" class="form-select" required>
                                        <option value="">-- Select Program --</option>
                                        <?php foreach ($programs_list as $prog): ?>
                                            <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['program_name'] . ' (' . $prog['title_diploma_program'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-certificate"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Subject Type</label>
                                <div class="input-wrapper">
                                    <select name="course_type" class="form-select" required>
                                        <option value="Minor">Minor / Gen Ed</option>
                                        <option value="Major">Major Subject</option>
                                    </select>
                                    <i class="fas fa-tag"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-calculator me-2"></i>Unit Computation</span>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lec Hours</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lec_hrs" class="form-control calc-hrs-add" step="0.5" value="0">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lec Units</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lec_units" class="form-control calc-units-add" step="0.5" value="0">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lab Hours</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lab_hrs" class="form-control calc-hrs-add" step="0.5" value="0">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lab Units</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lab_units" class="form-control calc-units-add" step="0.5" value="0">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Total Units Earned</label>
                                <div class="input-wrapper">
                                    <input type="number" name="units" id="add_total_units" class="form-control bg-light" value="0" readonly>
                                    <i class="fas fa-check-circle text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-plus-circle me-2"></i>Create Subject</button>
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
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="curriculum_id" id="edit_curriculum_id">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="modal-header modal-premium-header gradient-navy">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i>
                        <span>Edit Subject</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-info-circle me-2"></i>Subject Identity</span>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Class Code (6 chars)</label>
                                <div class="input-wrapper">
                                    <input type="text" name="class_code" id="edit_class_code" class="form-control" required maxlength="6" minlength="6" pattern=".{6,6}">
                                    <i class="fas fa-hashtag"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Subject ID</label>
                                <div class="input-wrapper">
                                    <input type="text" id="edit_subject_id_display" class="form-control bg-light" readonly>
                                    <i class="fas fa-barcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Subject Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="subject_name" id="edit_subject_name" class="form-control" required>
                                    <i class="fas fa-font"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="premium-input-group">
                                <label>Full Description (Optional)</label>
                                <div class="input-wrapper">
                                    <textarea name="description" id="edit_description" class="form-control pt-3" style="min-height: 80px;"></textarea>
                                    <i class="fas fa-align-left" style="top: 1.5rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-graduation-cap me-2"></i>Academic Requirements</span>
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="premium-input-group">
                                <label>Pre-requisite(s)</label>
                                <div class="input-wrapper">
                                    <input type="text" name="pre_requisites" id="edit_pre_requisites" class="form-control">
                                    <i class="fas fa-link"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Program (Course)</label>
                                <div class="input-wrapper">
                                    <select name="program_id" id="edit_program_id" class="form-select" required>
                                        <option value="">-- Select Program --</option>
                                        <?php foreach ($programs_list as $prog): ?>
                                            <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['program_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-certificate"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Subject Type</label>
                                <div class="input-wrapper">
                                    <select name="course_type" id="edit_course_type_select" class="form-select" required>
                                        <option value="Minor">Minor / Gen Ed</option>
                                        <option value="Major">Major Subject</option>
                                    </select>
                                    <i class="fas fa-tag"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-calculator me-2"></i>Unit Computation</span>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lec Hours</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lec_hrs" id="edit_lec_hrs" class="form-control calc-hrs-edit" step="0.5">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lec Units</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lec_units" id="edit_lec_units" class="form-control calc-units-edit" step="0.5">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lab Hours</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lab_hrs" id="edit_lab_hrs" class="form-control calc-hrs-edit" step="0.5">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Lab Units</label>
                                <div class="input-wrapper">
                                    <input type="number" name="lab_units" id="edit_lab_units" class="form-control calc-units-edit" step="0.5">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Total Units</label>
                                <div class="input-wrapper">
                                    <input type="number" name="units" id="edit_units" class="form-control bg-light" readonly>
                                    <i class="fas fa-check-circle text-success"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Status</label>
                                <div class="input-wrapper">
                                    <select name="status" id="edit_status" class="form-select" required>
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
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-sync me-2"></i>Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCourse(data) {
    document.getElementById('edit_curriculum_id').value = data.curriculum_id;
    document.getElementById('edit_subject_id').value = data.subject_id;
    document.getElementById('edit_subject_id_display').value = data.subject_id;
    document.getElementById('edit_class_code').value = data.class_code || '';
    document.getElementById('edit_subject_name').value = data.subject_name;
    document.getElementById('edit_pre_requisites').value = data.pre_requisites || '';
    
    document.getElementById('edit_lec_hrs').value = data.lec_hrs || 0;
    document.getElementById('edit_lec_units').value = data.lec_units || 0;
    document.getElementById('edit_lab_hrs').value = data.lab_hrs || 0;
    document.getElementById('edit_lab_units').value = data.lab_units || 0;
    document.getElementById('edit_units').value = data.units;
    
    document.getElementById('edit_course_type_select').value = data.course_type;

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
// Filter courses locally
function filterCourses() {
    const input = document.getElementById('courseSearchInput');
    const filter = input.value.toLowerCase().trim();
    const table = document.querySelector('table');
    if (!table) return;
    const tr = table.getElementsByTagName('tr');
    const counter = document.getElementById('searchCounter');
    let visibleCount = 0;

    for (let i = 1; i < tr.length; i++) {
        let rowMatch = false;
        const tds = tr[i].getElementsByTagName('td');
        for (let j = 0; j < tds.length; j++) {
            if (tds[j].textContent.toLowerCase().indexOf(filter) > -1) {
                rowMatch = true;
                break;
            }
        }
        
        if (rowMatch) {
            tr[i].style.display = "";
            visibleCount++;
        } else {
            tr[i].style.display = "none";
        }
    }

    if (filter === "") {
        counter.textContent = "";
    } else {
        counter.textContent = visibleCount + " found";
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
