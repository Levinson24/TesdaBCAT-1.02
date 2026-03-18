<?php
/**
 * Enrollment Management
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('enrollments.php', 'Invalid security token. Please try again.', 'danger');
    }
    $studentId = intval($_POST['student_id']);
    $sectionId = intval($_POST['section_id']);

    // Enforce department restriction for Registrar Staff
    if ($isStaff) {
        $sCheck = $conn->prepare("SELECT dept_id FROM students WHERE student_id = ?");
        $sCheck->bind_param("i", $studentId);
        $sCheck->execute();
        $sRes = $sCheck->get_result()->fetch_assoc();
        if ($sRes && $sRes['dept_id'] != $deptId) {
            redirectWithMessage('enrollments.php', 'Unauthorized: Student belongs to another department.', 'danger');
        }

        $secCheck = $conn->prepare("SELECT c.dept_id FROM class_sections cs JOIN courses c ON cs.course_id = c.course_id WHERE cs.section_id = ?");
        $secCheck->bind_param("i", $sectionId);
        $secCheck->execute();
        $secRes = $secCheck->get_result()->fetch_assoc();
        if ($secRes && $secRes['dept_id'] != $deptId) {
            redirectWithMessage('enrollments.php', 'Unauthorized: Section belongs to another department.', 'danger');
        }
    }

    // Check for Duplicate enrollment handled above
    $check = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND section_id = ?");
    $check->bind_param("ii", $studentId, $sectionId);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        redirectWithMessage('enrollments.php', 'This student is already enrolled in the selected section.', 'warning');
    }
    $check->close();

    // 1. Capacity Check
    $capStmt = $conn->prepare("
        SELECT cs.max_students, cs.section_name, 
               (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id AND status != 'dropped') as enrolled_count
        FROM class_sections cs 
        WHERE cs.section_id = ?
    ");
    $capStmt->bind_param("i", $sectionId);
    $capStmt->execute();
    $cap = $capStmt->get_result()->fetch_assoc();
    $capStmt->close();

    if ($cap && $cap['enrolled_count'] >= ($cap['max_students'] ?? 40)) {
        redirectWithMessage('enrollments.php', "Enrollment Failed: Section '{$cap['section_name']}' is already at full capacity ({$cap['max_students']}).", 'danger');
    }

    // 2. Schedule Conflict Check
    $sem = getSetting('current_semester', '1st');
    $sy = getSetting('academic_year', '2024-2025');

    $conflict = checkStudentScheduleConflict($studentId, $sectionId, $sem, $sy);
    if ($conflict) {
        // Fetch recommendations for this course
        $secInfo = $conn->query("SELECT course_id FROM class_sections WHERE section_id = $sectionId")->fetch_assoc();
        $recs = getScheduleRecommendations($secInfo['course_id'], $studentId, $sem, $sy);
        $recHtml = showRecommendations($recs);

        redirectWithMessage('enrollments.php', "<strong>Conflict Detected:</strong> " . $conflict['msg'] . "<br><br>" . $recHtml, 'danger text-start');
    }

    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, section_id, enrollment_date, status) VALUES (?, ?, CURDATE(), 'enrolled')");
    $stmt->bind_param("ii", $studentId, $sectionId);
    if ($stmt->execute()) {
        logAudit(getCurrentUserId(), 'CREATE', 'enrollments', $conn->insert_id, null, "Enrolled Student ID: $studentId to Section ID: $sectionId");
        redirectWithMessage('enrollments.php', 'Student enrolled successfully', 'success');
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_enrollment') {
    if (getCurrentUserRole() !== 'registrar') {
        redirectWithMessage('enrollments.php', 'Unauthorized: Only the Head Registrar can unenroll students.', 'danger');
    }
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('enrollments.php', 'Invalid security token. Please try again.', 'danger');
    }
    $enrollmentId = intval($_POST['enrollment_id']);
    
    // Check if grades already exist for this enrollment
    $checkGrades = $conn->prepare("SELECT grade_id FROM grades WHERE enrollment_id = ?");
    $checkGrades->bind_param("i", $enrollmentId);
    $checkGrades->execute();
    if ($checkGrades->get_result()->num_rows > 0) {
        redirectWithMessage('enrollments.php', 'Cannot unenroll student: Grades have already been recorded for this subject.', 'danger');
    }
    $checkGrades->close();

    $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
    $stmt->bind_param("i", $enrollmentId);
    if ($stmt->execute()) {
        logAudit(getCurrentUserId(), 'DELETE', 'enrollments', $enrollmentId, null, "Unenrolled ID: $enrollmentId");
        redirectWithMessage('enrollments.php', 'Student unenrolled successfully.', 'success');
    } else {
        redirectWithMessage('enrollments.php', 'Failed to unenroll student.', 'danger');
    }
}

$pageTitle = 'Manage Enrollments';

$additionalCSS = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 45px !important;
        border: 1px solid #dee2e6 !important;
        border-radius: 10px !important;
        padding-top: 5px !important;
    }
    .select2-selection__rendered {
        line-height: 35px !important;
        color: #1a3a5c !important;
        font-weight: 500 !important;
    }
    .select2-selection__arrow {
        height: 43px !important;
    }
    .select2-dropdown {
        border-radius: 12px !important;
        border: 1px solid #dee2e6 !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
</style>';

require_once '../includes/header.php';

if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_enrollment') {
    $enrollId = intval($_POST['enrollment_id']);
    $checkStmt = $conn->prepare("SELECT s.dept_id FROM enrollments e JOIN students s ON e.student_id = s.student_id WHERE e.enrollment_id = ?");
    $checkStmt->bind_param("i", $enrollId);
    $checkStmt->execute();
    $res = $checkStmt->get_result()->fetch_assoc();
    if ($res && $res['dept_id'] != $deptId) {
        redirectWithMessage('enrollments.php', 'Unauthorized: Enrollment belongs to another department.', 'danger');
    }
}

$enrollmentWhere = $isStaff ? " WHERE s.dept_id = $deptId" : "";
$enrollments = $conn->query("
    SELECT e.*, s.student_no, CONCAT(s.first_name, ' ', s.last_name) as student_name,
           c.class_code, c.course_code, cs.section_name, cs.semester, cs.school_year
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    $enrollmentWhere
    ORDER BY e.enrollment_date DESC
");

$studentWhere = $isStaff ? " WHERE dept_id = $deptId AND status = 'active'" : " WHERE status = 'active'";
$students = $conn->query("SELECT student_id, student_no, CONCAT(first_name, ' ', last_name) as name, program_id FROM students $studentWhere");

$sectionWhere = $isStaff ? " WHERE c.dept_id = $deptId AND cs.status = 'active'" : " WHERE cs.status = 'active'";
$sections = $conn->query("
    SELECT cs.section_id, 
           CONCAT(IFNULL(c.class_code, 'N/A'), ' | ', IFNULL(c.course_code, 'N/A'), ' - ', IFNULL(cs.section_name, 'N/A'), ' (', IFNULL(c.course_type, 'Minor'), ')') as display, 
           c.program_id, c.course_type
    FROM class_sections cs
    JOIN courses c ON cs.course_id = c.course_id
    $sectionWhere
");

$progWhere = $isStaff ? " AND p.dept_id = $deptId" : "";
$programs = $conn->query("SELECT p.program_id, p.program_name, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' $progWhere ORDER BY p.program_name ASC");
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between">
        <h5 class="mb-0"><i class="fas fa-user-plus"></i> Enrollment Management</h5>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Enroll Student
        </button>
    </div>
    <div class="card-body">
        <table class="table table-hover data-table">
            <thead>
                <tr><th>Student</th><th>Class Code</th><th>Subject Code</th><th>Section</th><th>SY/Sem</th><th>Date</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
                <?php while ($e = $enrollments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['student_name'] ?? ''); ?><br><small><?php echo $e['student_no'] ?? ''; ?></small></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($e['class_code'] ?? 'N/A'); ?></span></td>
                    <td><strong><?php echo htmlspecialchars($e['course_code'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars($e['section_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($e['semester'] ?? '') . ' ' . ($e['school_year'] ?? '')); ?></td>
                    <td><?php echo formatDate($e['enrollment_date']); ?></td>
                    <td class="text-nowrap">
                        <a href="cor_print.php?student_id=<?php echo $e['student_id']; ?>" target="_blank" class="btn btn-sm btn-info" title="Print COR">
                            <i class="fas fa-print me-1"></i> COR
                        </a>
                        <?php if (getCurrentUserRole() === 'registrar'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to unenroll this student? This will remove them from the class list.')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="delete_enrollment">
                            <input type="hidden" name="enrollment_id" value="<?php echo $e['enrollment_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Unenroll">
                                <i class="fas fa-user-minus"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td></tr>
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
            <input type="hidden" name="action" value="enroll">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Enroll Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary"><i class="fas fa-filter me-1"></i> Filter by Program (Optional)</label>
                        <select id="program_filter" class="form-select select2">
                            <option value="">-- All Programs --</option>
                            <?php while ($p = $programs->fetch_assoc()): ?>
                                <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name'] . ' (' . $p['title_diploma_program'] . ')'); ?></option>
                            <?php
endwhile; ?>
                        </select>
                        <div class="form-text">Selecting a program will narrow down the student and class lists.</div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label>Student</label>
                        <select name="student_id" id="student_select" class="form-select select2" required>
                            <option value="">-- Search Student --</option>
                            <?php $students->data_seek(0);
while ($s = $students->fetch_assoc()): ?>
                            <option value="<?php echo $s['student_id']; ?>" data-program="<?php echo $s['program_id']; ?>">
                                <?php echo htmlspecialchars(($s['student_no'] ?? '') . ' - ' . ($s['name'] ?? '')); ?>
                            </option>
                            <?php
endwhile; ?>
                        </select>
                        <div id="student_program_hint" class="mt-1 small text-primary" style="display:none;">
                            <i class="fas fa-graduation-cap me-1"></i> Student is from this Program Category
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Class Section</label>
                        <select name="section_id" id="section_select" class="form-select select2" required>
                            <option value="">-- Select Section --</option>
                            <?php $sections->data_seek(0);
while ($sec = $sections->fetch_assoc()): ?>
                            <option value="<?php echo $sec['section_id']; ?>" data-program="<?php echo $sec['program_id']; ?>">
                                <?php echo htmlspecialchars($sec['display'] ?? ''); ?>
                            </option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Enroll</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        dropdownParent: $('#addModal'),
        width: '100%'
    });

    // Program Filter Logic
    $('#program_filter').on('change', function() {
        const programId = $(this).val();
        const studentSelect = $('#student_select');
        const sectionSelect = $('#section_select');
        
        // Filter Students
        studentSelect.find('option').each(function() {
            const optionProgId = $(this).data('program');
            if (!programId || !optionProgId || optionProgId == programId) {
                $(this).prop('disabled', false);
            } else {
                $(this).prop('disabled', true);
            }
        });
        
        // Filter Sections
        sectionSelect.find('option').each(function() {
            const optionProgId = $(this).data('program');
            if (!programId || !optionProgId || optionProgId == programId) {
                $(this).prop('disabled', false);
            } else {
                $(this).prop('disabled', true);
            }
        });

        // Reset selections if they are now disabled
        if (studentSelect.find(':selected').prop('disabled')) studentSelect.val('').trigger('change');
        if (sectionSelect.find(':selected').prop('disabled')) sectionSelect.val('').trigger('change');
        
        // Refresh Select2 to show disabled states
        studentSelect.select2({ dropdownParent: $('#addModal'), width: '100%' });
        sectionSelect.select2({ dropdownParent: $('#addModal'), width: '100%' });
    });

    // Auto-detect Program when Student is selected
    $('#student_select').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const studentProgId = selectedOption.data('program');
        const programFilter = $('#program_filter');
        const sectionSelect = $('#section_select');
        
        if (studentProgId) {
            $('#student_program_hint').fadeIn();
            
            // If program filter isn't set, we can highlight matching sections
            if (!programFilter.val()) {
                sectionSelect.find('option').each(function() {
                    const sectionProgId = $(this).data('program');
                    if (sectionProgId && sectionProgId == studentProgId) {
                        $(this).css('color', '#1a3a5c').css('font-weight', 'bold');
                    } else {
                        $(this).css('color', '').css('font-weight', '');
                    }
                });
            }
        } else {
            $('#student_program_hint').hide();
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
