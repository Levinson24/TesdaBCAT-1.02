<?php
/**
 * Class Section Management
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('sections.php', 'Invalid security token. Please try again.', 'danger');
    }

    // Enforce department restriction for Registrar Staff
    if ($isStaff) {
        // Verify course_id if provided
        if (isset($_POST['course_id'])) {
            $cCheck = $conn->prepare("SELECT dept_id FROM courses WHERE course_id = ?");
            $cCheck->bind_param("i", $_POST['course_id']);
            $cCheck->execute();
            $cRes = $cCheck->get_result()->fetch_assoc();
            if ($cRes && $cRes['dept_id'] != $deptId) {
                redirectWithMessage('sections.php', 'Unauthorized: Subject belongs to another department.', 'danger');
            }
        }
        
        // If updating or deleting, verify the section belongs to the staff's department
        if (isset($_POST['section_id'])) {
            $checkStmt = $conn->prepare("SELECT c.dept_id FROM class_sections cs JOIN courses c ON cs.course_id = c.course_id WHERE cs.section_id = ?");
            $checkStmt->bind_param("i", $_POST['section_id']);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result()->fetch_assoc();
            if ($checkRes && $checkRes['dept_id'] != $deptId) {
                redirectWithMessage('sections.php', 'Unauthorized: Section belongs to another department.', 'danger');
            }
        }
    }
    if ($_POST['action'] === 'create') {
        $courseId = intval($_POST['course_id']);
        $instructorId = intval($_POST['instructor_id']);
        $sectionName = sanitizeInput($_POST['section_name']);
        $semester = sanitizeInput($_POST['semester']);
        $schoolYear = sanitizeInput($_POST['school_year']);
        $schedule = sanitizeInput($_POST['schedule']);
        $room = sanitizeInput($_POST['room']);

        // Conflict Detection
        $conflict = checkSectionConflict($instructorId, $room, $schedule, $semester, $schoolYear);
        if ($conflict) {
            redirectWithMessage('sections.php', "Conflict Detected ({$conflict['type']}): " . $conflict['msg'], 'danger');
        }

        $stmt = $conn->prepare("INSERT INTO class_sections (course_id, instructor_id, section_name, semester, school_year, schedule, room, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("iisssss", $courseId, $instructorId, $sectionName, $semester, $schoolYear, $schedule, $room);
        try {
            if ($stmt->execute()) {
                logAudit(getCurrentUserId(), 'CREATE', 'class_sections', $conn->insert_id, null, "Created class section: $sectionName");
                redirectWithMessage('sections.php', 'Section created successfully', 'success');
            }
            else {
                redirectWithMessage('sections.php', 'Error creating section: ' . $conn->error, 'danger');
            }
        }
        catch (mysqli_sql_exception $e) {
            $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? 'Duplicate entry detected.' : $e->getMessage();
            redirectWithMessage('sections.php', 'Error: ' . $msg, 'danger');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $sectionId = intval($_POST['section_id']);
        $courseId = intval($_POST['course_id']);
        $instructorId = intval($_POST['instructor_id']);
        $sectionName = sanitizeInput($_POST['section_name']);
        $semester = sanitizeInput($_POST['semester']);
        $schoolYear = sanitizeInput($_POST['school_year']);
        $schedule = sanitizeInput($_POST['schedule']);
        $room = sanitizeInput($_POST['room']);
        $status = sanitizeInput($_POST['status']);

        // Conflict Detection
        $conflict = checkSectionConflict($instructorId, $room, $schedule, $semester, $schoolYear, $sectionId);
        if ($conflict) {
            redirectWithMessage('sections.php', "Conflict Detected ({$conflict['type']}): " . $conflict['msg'], 'danger');
        }

        $stmt = $conn->prepare("UPDATE class_sections SET course_id = ?, instructor_id = ?, section_name = ?, semester = ?, school_year = ?, schedule = ?, room = ?, status = ? WHERE section_id = ?");
        $stmt->bind_param("iissssssi", $courseId, $instructorId, $sectionName, $semester, $schoolYear, $schedule, $room, $status, $sectionId);
        try {
            if ($stmt->execute()) {
                logAudit(getCurrentUserId(), 'UPDATE', 'class_sections', $sectionId, null, "Updated class section: $sectionName");
                redirectWithMessage('sections.php', 'Section updated successfully', 'success');
            }
            else {
                redirectWithMessage('sections.php', 'Error updating section: ' . $conn->error, 'danger');
            }
        }
        catch (mysqli_sql_exception $e) {
            $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? 'Duplicate entry detected.' : $e->getMessage();
            redirectWithMessage('sections.php', 'Error: ' . $msg, 'danger');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        if (getCurrentUserRole() !== 'registrar') {
            redirectWithMessage('sections.php', 'Unauthorized: Only the Head Registrar can delete sections.', 'danger');
        }
        $sectionId = intval($_POST['section_id']);

        // Check if there are enrollments
        $check = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE section_id = ?");
        $check->bind_param("i", $sectionId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            redirectWithMessage('sections.php', 'Cannot delete section because students are already enrolled.', 'danger');
        }
        $check->close();

        $stmt = $conn->prepare("DELETE FROM class_sections WHERE section_id = ?");
        $stmt->bind_param("i", $sectionId);
        if ($stmt->execute()) {
            logAudit(getCurrentUserId(), 'DELETE', 'class_sections', $sectionId, null, "Deleted class section ID: $sectionId");
            redirectWithMessage('sections.php', 'Section deleted successfully.', 'success');
        }
        else {
            redirectWithMessage('sections.php', 'Error deleting section: ' . $conn->error, 'danger');
        }
    }
    // Legacy quick_create_course removed in favor of AJAX at ajax/quick_subject.php
}

$pageTitle = 'Manage Class Sections';
require_once '../includes/header.php';

// Fetch lists for forms
$courseWhere = $isStaff ? " WHERE dept_id = $deptId AND status = 'active'" : " WHERE status = 'active'";
$courses_res = $conn->query("SELECT * FROM courses $courseWhere ORDER BY course_code");

$instructorWhere = $isStaff ? " WHERE dept_id = $deptId AND status = 'active'" : " WHERE status = 'active'";
$instructors_res = $conn->query("SELECT * FROM instructors $instructorWhere ORDER BY last_name");

$sectionWhere = $isStaff ? " WHERE c.dept_id = $deptId" : "";
$sections = $conn->query("
    SELECT cs.*, c.course_code, c.course_name, i.first_name, i.last_name,
    (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id) as enrolled_count
    FROM class_sections cs 
    JOIN courses c ON cs.course_id = c.course_id 
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    $sectionWhere
    ORDER BY cs.school_year DESC, cs.semester, c.course_code
");

$courses = $conn->query("
    SELECT c.*, p.program_name 
    FROM courses c 
    LEFT JOIN programs p ON c.program_id = p.program_id 
    WHERE c.status = 'active'
    ORDER BY p.program_name, c.course_code
");
$instructors = $conn->query("SELECT instructor_id, CONCAT(first_name, ' ', last_name) as name FROM instructors WHERE status = 'active'");

// Fetch programs for quick create
$prog_res = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' ORDER BY p.program_name ASC");
$programs_list = [];
while ($p = $prog_res->fetch_assoc()) {
    $programs_list[] = $p;
}

// === Premium Styles ===
?>
<style>
    .premium-card {
        border-radius: 1rem;
    }
    .bg-dark-navy {
        background-color: #0f172a !important;
    }
    .sections-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .sections-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        font-size: 0.85rem;
    }
    .sect-icon-box {
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
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }
    .btn-premium-delete {
        background-color: #fef2f2;
        color: #ef4444 !important;
    }
    .btn-premium-delete:hover {
        background-color: #ef4444;
        color: #fff !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }
</style>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-layer-group me-2 text-warning"></i> Class Sections Registry
        </h5>
        <div>
            <button class="btn btn-outline-info bg-info bg-opacity-10 btn-sm rounded-pill px-3 me-2 fw-bold border-0" data-bs-toggle="modal" data-bs-target="#quickCourseModal">
                <i class="fas fa-plus-circle me-1"></i> Quick Subject
            </button>
            <button class="btn btn-light btn-sm rounded-pill px-3 shadow-sm fw-bold border-0 text-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-1"></i> Add Section
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 sections-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">SUBJECT / SECTION</th>
                        <th>INSTRUCTOR</th>
                        <th>SCHEDULE & ROOM</th>
                        <th class="text-center">ENROLLED</th>
                        <th class="text-center">ACADEMIC PERIOD</th>
                        <th class="text-end pe-4">STATUS</th>
                        <th class="text-end pe-4">ACTIONS</th>
                    </tr>
                </thead>
            <tbody>
                <?php while ($s = $sections->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="sect-icon-box">
                                    <?php echo substr($s['section_name'] ?? 'S', 0, 1); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($s['section_name'] ?? ''); ?></div>
                                    <small class="text-muted d-block" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($s['course_name'] ?? ''); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')); ?></div>
                            <small class="text-muted">Faculty ID: <?php echo htmlspecialchars($s['instructor_id'] ?? 'N/A'); ?></small>
                        </td>
                        <td>
                            <div class="fw-bold text-indigo" style="font-size: 0.8rem;"><i class="far fa-clock me-1"></i> <?php echo htmlspecialchars($s['schedule'] ?? 'TBA'); ?></div>
                            <div class="text-muted small"><i class="fas fa-door-open me-1"></i> <?php echo htmlspecialchars($s['room'] ?? 'TBA'); ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-info bg-opacity-10 text-info px-3">
                                <?php echo $s['enrolled_count']; ?> Studs
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['semester'] ?? ''); ?> Sem</div>
                            <div class="text-muted small">SY <?php echo htmlspecialchars($s['school_year'] ?? ''); ?></div>
                        </td>
                        <td class="text-end">
                            <?php $status = $s['status'] ?? 'active'; ?>
                            <span class="badge rounded-pill <?php echo $status === 'active' ? 'bg-success' : ($status === 'completed' ? 'bg-primary' : 'bg-secondary'); ?> bg-opacity-10 text-<?php echo $status === 'active' ? 'success' : ($status === 'completed' ? 'primary' : 'secondary'); ?> px-3">
                                <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                    <td class="text-nowrap pe-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn-premium-edit" onclick='editSection(<?php echo json_encode($s); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if (getCurrentUserRole() === 'registrar'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this section?')">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="section_id" value="<?php echo $s['section_id']; ?>">
                                <button type="submit" class="btn-premium-delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                            <?php endif; ?>
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
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-content">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2 text-warning"></i>Add Class Section</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Description</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php $courses->data_seek(0);
while ($c = $courses->fetch_assoc()): ?>
                            <option value="<?php echo $c['course_id']; ?>">
                                [<?php echo htmlspecialchars($c['program_name'] ?? 'No Program'); ?>] 
                                <?php echo htmlspecialchars(($c['course_code'] ?? '') . ' - ' . ($c['course_name'] ?? '')); ?>
                            </option>
                            <?php
endwhile; ?>
                        </select>
                        <div class="form-text mt-1">
                            Can't find the subject? <a href="javascript:void(0)" onclick="openQuickCourse()" class="text-primary decoration-none fw-bold">Quick Create Subject</a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Instructor</label>
                        <select name="instructor_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php $instructors->data_seek(0);
while ($i = $instructors->fetch_assoc()): ?>
                            <option value="<?php echo $i['instructor_id']; ?>"><?php echo htmlspecialchars($i['name'] ?? ''); ?></option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Section Name</label>
                        <input type="text" name="section_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>School Year</label>
                        <input type="text" name="school_year" class="form-control" value="<?php echo getSetting('academic_year', '2024-2025'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Semester</label>
                        <select name="semester" class="form-select" required>
                            <option value="1st">1st</option>
                            <option value="2nd">2nd</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Schedule (e.g. MW 8:00-9:30)</label>
                            <input type="text" name="schedule" class="form-control" placeholder="TBA">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Room</label>
                            <input type="text" name="room" class="form-control" placeholder="TBA">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> Create Section
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Edit Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="section_id" id="edit_section_id">
            <div class="modal-content">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2 text-warning"></i>Edit Class Section</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Description</label>
                        <select name="course_id" id="edit_course_id" class="form-select" required>
                            <?php $courses->data_seek(0);
while ($c = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $c['course_id']; ?>">
                                    [<?php echo htmlspecialchars($c['program_name'] ?? 'No Program'); ?>] 
                                    <?php echo htmlspecialchars(($c['course_code'] ?? '') . ' - ' . ($c['course_name'] ?? '')); ?>
                                </option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Instructor</label>
                        <select name="instructor_id" id="edit_instructor_id" class="form-select" required>
                            <?php $instructors->data_seek(0);
while ($i = $instructors->fetch_assoc()): ?>
                                <option value="<?php echo $i['instructor_id']; ?>"><?php echo htmlspecialchars($i['name'] ?? ''); ?></option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Section Name</label>
                        <input type="text" name="section_name" id="edit_section_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>School Year</label>
                            <input type="text" name="school_year" id="edit_school_year" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Semester</label>
                            <select name="semester" id="edit_semester" class="form-select" required>
                                <option value="1st">1st</option>
                                <option value="2nd">2nd</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Schedule</label>
                            <input type="text" name="schedule" id="edit_schedule" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Room</label>
                            <input type="text" name="room" id="edit_room" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> Update Section
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editSection(data) {
    document.getElementById('edit_section_id').value = data.section_id;
    document.getElementById('edit_course_id').value = data.course_id;
    document.getElementById('edit_instructor_id').value = data.instructor_id;
    document.getElementById('edit_section_name').value = data.section_name;
    document.getElementById('edit_school_year').value = data.school_year;
    document.getElementById('edit_semester').value = data.semester;
    document.getElementById('edit_schedule').value = data.schedule || '';
    document.getElementById('edit_room').value = data.room || '';
    document.getElementById('edit_status').value = data.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function openQuickCourse() {
    // Hide addModal cleanly using Bootstrap class/method
    const addModalEl = document.getElementById('addModal');
    const addModal = bootstrap.Modal.getInstance(addModalEl);
    if (addModal) {
        addModal.hide();
    }
    
    // Show quickCourseModal with a small delay to allow addModal to close
    setTimeout(() => {
        const quickModal = new bootstrap.Modal(document.getElementById('quickCourseModal'));
        quickModal.show();
    }, 400);
}

// AJAX Quick Subject Submission
document.getElementById('quickSubjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    const alertBox = document.getElementById('quickSubjectAlert');
    
    // Reset alert
    alertBox.classList.add('d-none');
    alertBox.classList.remove('alert-success', 'alert-danger');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    
    const formData = new FormData(form);
    
    fetch('ajax/quick_subject.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 1. Show success in modal
            alertBox.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message;
            alertBox.classList.add('alert-success');
            alertBox.classList.remove('d-none');
            
            // 2. Append new option to BOTH course_id selects
            const newOption = new Option(data.display, data.course_id);
            const addSelect = document.querySelector('select[name="course_id"]');
            const editSelect = document.getElementById('edit_course_id');
            
            if (addSelect) {
                addSelect.add(newOption.cloneNode(true));
                addSelect.value = data.course_id;
            }
            if (editSelect) editSelect.add(newOption.cloneNode(true));
            
            // 3. Delayed closing
            setTimeout(() => {
                const quickModalEl = document.getElementById('quickCourseModal');
                const quickModal = bootstrap.Modal.getInstance(quickModalEl);
                if (quickModal) quickModal.hide();
                
                form.reset();
                alertBox.classList.add('d-none');
                
                // 4. Re-open addModal with slight delay
                setTimeout(() => {
                    const addModal El = document.getElementById('addModal');
                    const addModal = new bootstrap.Modal(addModalEl);
                    addModal.show();
                }, 400);
            }, 1000);
            
        } else {
            alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + data.message;
            alertBox.classList.add('alert-danger');
            alertBox.classList.remove('d-none');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alertBox.innerHTML = '<i class="fas fa-times-circle me-2"></i> Connection error. Please try again.';
        alertBox.classList.add('alert-danger');
        alertBox.classList.remove('d-none');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>

<!-- Quick Create Subject Modal -->
<div class="modal fade" id="quickCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="quickSubjectForm" method="POST">
            <?php csrfField(); ?>
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-book-plus me-2 text-warning"></i>Quick Create Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="quickSubjectAlert" class="alert d-none py-2 border-0 shadow-sm small" style="border-radius: 0.75rem;"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class Code</label>
                            <input type="text" name="class_code" class="form-control" placeholder="6 chars" required maxlength="6" minlength="6">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Code</label>
                            <input type="text" name="course_code" class="form-control" placeholder="6 chars" required maxlength="6" minlength="6">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Description</label>
                        <input type="text" name="course_name" class="form-control" placeholder="Complete course title" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Units</label>
                            <input type="number" name="units" class="form-control" value="3" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Program (Course)</label>
                            <select name="program_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($programs_list as $prog): ?>
                                    <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['program_name'] ?? ''); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> Create Subject
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
