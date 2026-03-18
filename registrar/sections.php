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
    elseif ($_POST['action'] === 'quick_create_course') {
        $classCode = strtoupper(sanitizeInput($_POST['class_code']));
        $courseCode = strtoupper(sanitizeInput($_POST['course_code']));
        if (strlen($classCode) !== 6 || strlen($courseCode) !== 6) {
            redirectWithMessage('sections.php', 'Codes must be exactly 6 characters.', 'danger');
        }

        $courseName = sanitizeInput($_POST['course_name']);
        $units = intval($_POST['units']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;

        $stmt = $conn->prepare("INSERT INTO courses (class_code, course_code, course_name, units, program_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("sssii", $classCode, $courseCode, $courseName, $units, $programId);
        try {
            if ($stmt->execute()) {
                logAudit(getCurrentUserId(), 'CREATE', 'courses', $conn->insert_id, null, "Quick created course: $courseCode");
                redirectWithMessage('sections.php', 'Subject created successfully. You can now assign it to a section.', 'success');
            }
            else {
                redirectWithMessage('sections.php', 'Error creating subject: ' . $conn->error, 'danger');
            }
        }
        catch (mysqli_sql_exception $e) {
            $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? 'Duplicate entry detected.' : $e->getMessage();
            redirectWithMessage('sections.php', 'Error: ' . $msg, 'danger');
        }
    }
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
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-layer-group"></i> Class Sections</h5>
        <div>
            <button class="btn btn-outline-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#quickCourseModal">
                <i class="fas fa-book-plus"></i> Quick Create Subject
            </button>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus"></i> Add Section
            </button>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-hover data-table">
            <thead>
                <tr><th>Subject Description</th><th>Section</th><th>Instructor</th><th>Schedule / Room</th><th>SY/Semester</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($s = $sections->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars(($s['course_code'] ?? '') . ' - ' . ($s['course_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($s['section_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($s['instructor_name'] ?? ''); ?></td>
                    <td>
                        <div class="small fw-bold text-primary"><?php echo htmlspecialchars($s['schedule'] ?? 'TBA'); ?></div>
                        <div class="small text-muted"><i class="fas fa-door-open me-1"></i><?php echo htmlspecialchars($s['room'] ?? 'TBA'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars(($s['semester'] ?? '') . ' ' . ($s['school_year'] ?? '')); ?></td>
                    <td><span class="badge bg-success"><?php echo ucfirst($s['status']); ?></span></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-info mb-1" onclick='editSection(<?php echo json_encode($s); ?>)' title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (getCurrentUserRole() === 'registrar'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this section? This action cannot be undone.')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="section_id" value="<?php echo $s['section_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger mb-1" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
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
                <div class="modal-header">
                    <h5>Add Class Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            Can't find the subject? <a href="#" data-bs-toggle="modal" data-bs-target="#quickCourseModal" class="text-primary decoration-none">Quick Create Subject</a>
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
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create Section</button>
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
                <div class="modal-header">
                    <h5>Edit Class Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Section</button>
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
</script>

<!-- Quick Create Subject Modal -->
<div class="modal fade" id="quickCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="quick_create_course">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-book-plus me-2"></i>Quick Create Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info px-4">Create Subject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
