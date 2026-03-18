<?php
/**
 * Diploma Program Head - Manage Student Schedule (Section Reassignment)
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userProfile = getUserProfile($_SESSION['user_id'], 'dept_head');
$deptId = $userProfile['dept_id'] ?? 0;

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$studentId) {
    redirectWithMessage('students.php', 'Invalid student ID.', 'danger');
}

// Verify student belongs to this department
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? AND dept_id = ?");
$stmt->bind_param("ii", $studentId, $deptId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    redirectWithMessage('students.php', 'Student not found in your diploma program.', 'danger');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Invalid security token. Please try again.', 'danger');
    }
    
    // Handle section reassignment
    if (isset($_POST['action']) && $_POST['action'] === 'reassign') {
    $enrollmentId = intval($_POST['enrollment_id']);
    $newSectionId = intval($_POST['new_section_id']);

    // Verify enrollment belongs to this student
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE enrollment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $enrollmentId, $studentId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();

        // Check if there are existing grades for this enrollment
        $checkGrades = $conn->prepare("SELECT grade_id FROM grades WHERE enrollment_id = ?");
        $checkGrades->bind_param("i", $enrollmentId);
        $checkGrades->execute();
        $hasGrades = $checkGrades->get_result()->num_rows > 0;
        $checkGrades->close();

        if ($hasGrades) {
            redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Cannot reassign section because grades have already been submitted/recorded.', 'warning');
        }

        // Check if student is already enrolled in the new section
        $checkDup = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND section_id = ? AND enrollment_id != ?");
        $checkDup->bind_param("iii", $studentId, $newSectionId, $enrollmentId);
        $checkDup->execute();
        if ($checkDup->get_result()->num_rows > 0) {
            $checkDup->close();
            redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Update failed: Student is already enrolled in that section.', 'danger');
        }
        $checkDup->close();

        // 1. Capacity Check
        $capStmt = $conn->prepare("
            SELECT cs.max_students, cs.section_name, 
                   (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id AND status != 'dropped') as enrolled_count
            FROM class_sections cs 
            WHERE cs.section_id = ?
        ");
        $capStmt->bind_param("i", $newSectionId);
        $capStmt->execute();
        $cap = $capStmt->get_result()->fetch_assoc();
        $capStmt->close();

        if ($cap && $cap['enrolled_count'] >= ($cap['max_students'] ?? 40)) {
            redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, "Update Failed: New section '{$cap['section_name']}' is already at full capacity.", 'danger');
        }

        // 2. Schedule Conflict Check
        $sem = getSetting('current_semester', '1st');
        $sy = getSetting('academic_year', '2024-2025');

        $conflict = checkStudentScheduleConflict($studentId, $newSectionId, $sem, $sy);
        if ($conflict) {
            $secInfo = $conn->query("SELECT course_id FROM class_sections WHERE section_id = $newSectionId")->fetch_assoc();
            $recs = getScheduleRecommendations($secInfo['course_id'], $studentId, $sem, $sy);
            $recHtml = showRecommendations($recs);
            redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, "<strong>Conflict:</strong> " . $conflict['msg'] . "<br><br>" . $recHtml, 'danger text-start');
        }

        $update = $conn->prepare("UPDATE enrollments SET section_id = ? WHERE enrollment_id = ?");
        $update->bind_param("ii", $newSectionId, $enrollmentId);
        if ($update->execute()) {
            logAudit(getCurrentUserId(), 'UPDATE', 'enrollments', $enrollmentId, null, "Reassigned Student ID: $studentId to Section ID: $newSectionId");
            redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Student reassigned successfully.', 'success');
        }
    }
    $stmt->close();
}

    // Handle dropping a subject (unload)
    if (isset($_POST['action']) && $_POST['action'] === 'drop') {
    $enrollmentId = intval($_POST['enrollment_id']);

    // Verify enrollment belongs to this student
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE enrollment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $enrollmentId, $studentId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();

        // Check for grades
        $checkGrades = $conn->prepare("SELECT grade_id FROM grades WHERE enrollment_id = ?");
        $checkGrades->bind_param("i", $enrollmentId);
        $checkGrades->execute();
        if ($checkGrades->get_result()->num_rows > 0) {
            redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Cannot drop subject because grades have already been recorded.', 'warning');
        }
        $checkGrades->close();

        // Delete enrollment
        $delete = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
        $delete->bind_param("i", $enrollmentId);
        if ($delete->execute()) {
            logAudit(getCurrentUserId(), 'DELETE', 'enrollments', $enrollmentId, null, "Dropped Student ID: $studentId from Subject");
            redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Subject unloaded successfully.', 'success');
        }
    }
    $stmt->close();
}

    // Handle adding a subject (add load)
    if (isset($_POST['action']) && $_POST['action'] === 'enroll') {
    $sectionId = intval($_POST['section_id']);

    // Check if already enrolled in this section
    $check = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND section_id = ?");
    $check->bind_param("ii", $studentId, $sectionId);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Student is already enrolled in this section.', 'warning');
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
        redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, "Enrollment Failed: Section '{$cap['section_name']}' is full.", 'danger');
    }

    // 2. Schedule Conflict Check
    $sem = getSetting('current_semester', '1st');
    $sy = getSetting('academic_year', '2024-2025');

    $conflict = checkStudentScheduleConflict($studentId, $sectionId, $sem, $sy);
    if ($conflict) {
        $secInfo = $conn->query("SELECT course_id FROM class_sections WHERE section_id = $sectionId")->fetch_assoc();
        $recs = getScheduleRecommendations($secInfo['course_id'], $studentId, $sem, $sy);
        $recHtml = showRecommendations($recs);
        redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, "<strong>Conflict:</strong> " . $conflict['msg'] . "<br><br>" . $recHtml, 'danger text-start');
    }

    // Enroll
    $enroll = $conn->prepare("INSERT INTO enrollments (student_id, section_id, enrollment_date, status) VALUES (?, ?, CURDATE(), 'enrolled')");
    $enroll->bind_param("ii", $studentId, $sectionId);
    if ($enroll->execute()) {
        logAudit(getCurrentUserId(), 'CREATE', 'enrollments', $conn->insert_id, null, "Enrolled Student ID: $studentId to Section ID: $sectionId");
        redirectWithMessage('manage_student_schedule.php?student_id=' . $studentId, 'Subject added successfully.', 'success');
    }
    }
}

$pageTitle = 'Manage Student Enrollment';

$additionalCSS = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 40px !important;
        border: 1px solid #dee2e6 !important;
        border-radius: 8px !important;
    }
    .select2-selection__rendered {
        line-height: 38px !important;
    }
    .select2-selection__arrow {
        height: 38px !important;
    }
</style>';

require_once '../includes/header.php';

// Get current active enrollments
$enrollments = $conn->prepare("
    SELECT e.enrollment_id, e.section_id, c.course_id, c.course_code, c.course_name, 
           cs.section_name, cs.schedule, cs.room, cs.semester, cs.school_year,
           CONCAT(IFNULL(i.first_name,''), ' ', IFNULL(i.last_name,'')) as instructor_name,
           p.program_name
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN programs p ON c.program_id = p.program_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE e.student_id = ? AND cs.status = 'active'
    ORDER BY cs.semester DESC
");
$enrollments->bind_param("i", $studentId);
$enrollments->execute();
$currentEnrollments = $enrollments->get_result();
$enrollments->close();

// Get available sections for ALL departments to Add Load
$availableSections = $conn->prepare("
    SELECT cs.section_id, cs.section_name, cs.schedule, cs.room, cs.semester, cs.school_year,
           c.course_code, c.course_name, p.dept_id, c.course_type,
           CONCAT(IFNULL(i.first_name,''), ' ', IFNULL(i.last_name,'')) as instructor_name,
           d.title_diploma_program as dept_name
    FROM class_sections cs
    JOIN courses c ON cs.course_id = c.course_id
    JOIN programs p ON c.program_id = p.program_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    JOIN departments d ON p.dept_id = d.dept_id
    WHERE cs.status = 'active' 
      AND cs.section_id NOT IN (SELECT section_id FROM enrollments WHERE student_id = ?)
    ORDER BY cs.school_year DESC, cs.semester DESC, d.title_diploma_program, c.course_code
");
$availableSections->bind_param("i", $studentId);
$availableSections->execute();
$sectionsList = $availableSections->get_result();
$availableSections->close();

$deptsQuery = $conn->query("
    SELECT d.dept_id, d.title_diploma_program as dept_name, COUNT(cs.section_id) as subject_count
    FROM departments d
    JOIN programs p ON d.dept_id = p.dept_id
    JOIN courses c ON p.program_id = c.program_id
    JOIN class_sections cs ON c.course_id = cs.course_id
    WHERE d.status = 'active' AND cs.status = 'active'
    GROUP BY d.dept_id
    ORDER BY d.title_diploma_program ASC
");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                <li class="breadcrumb-item active">Manage Schedule</li>
            </ol>
        </nav>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white p-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Current Enrollment: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                <button class="btn btn-light btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addLoadModal">
                    <i class="fas fa-plus me-1"></i> Add Subject / Load
                </button>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info py-2 small mb-4">
                    <i class="fas fa-info-circle me-1"></i> You can add new subjects, reassign sections, or drop subjects for this student. <strong>Subjects with recorded grades cannot be dropped or reassigned.</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Subject Description</th>
                                <th>Current Section</th>
                                <th>Schedule / Room</th>
                                <th>Instructor</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
$modalHtml = ""; // Buffer for modals
if ($currentEnrollments->num_rows > 0):
?>
                                <?php while ($e = $currentEnrollments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($e['course_code']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($e['course_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($e['section_name']); ?></span>
                                        <div class="small text-muted mt-1"><?php echo htmlspecialchars($e['semester'] . ' ' . $e['school_year']); ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold"><?php echo htmlspecialchars($e['schedule'] ?? 'TBA'); ?></div>
                                        <div class="small text-muted"><i class="fas fa-door-open me-1"></i><?php echo htmlspecialchars($e['room'] ?? 'TBA'); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($e['instructor_name']); ?></td>
                                    <td class="text-end">
                                        <?php
        // Get ALL other schedules for this student for the same semester/SY to check for conflicts
        $otherSchedsQuery = $conn->prepare("
                                            SELECT cs.schedule, c.course_code 
                                            FROM enrollments e
                                            JOIN class_sections cs ON e.section_id = cs.section_id
                                            JOIN courses c ON cs.course_id = c.course_id
                                            WHERE e.student_id = ? 
                                              AND cs.semester = ? 
                                              AND cs.school_year = ? 
                                              AND e.enrollment_id != ?
                                        ");
        $otherSchedsQuery->bind_param("issi", $studentId, $e['semester'], $e['school_year'], $e['enrollment_id']);
        $otherSchedsQuery->execute();
        $otherSchedsRes = $otherSchedsQuery->get_result();
        $studentCurrentSchedules = [];
        while ($os = $otherSchedsRes->fetch_assoc()) {
            if ($os['schedule'])
                $studentCurrentSchedules[] = $os;
        }
        $otherSchedsQuery->close();

        // Get alternative sections with student count
        $altSessions = $conn->prepare("
                                            SELECT cs.section_id, cs.section_name, cs.schedule, cs.room,
                                                   CONCAT(i.first_name, ' ', i.last_name) as instructor_name,
                                                   (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id AND status = 'enrolled') as student_count
                                            FROM class_sections cs
                                            JOIN instructors i ON cs.instructor_id = i.instructor_id
                                            WHERE cs.course_id = ? 
                                              AND cs.semester = ? 
                                              AND cs.school_year = ? 
                                              AND cs.section_id != ?
                                              AND cs.status = 'active'
                                        ");
        $altSessions->bind_param("issi", $e['course_id'], $e['semester'], $e['school_year'], $e['section_id']);
        $altSessions->execute();
        $alternatives = $altSessions->get_result();

        if ($alternatives->num_rows > 0):
            $altList = [];
            while ($alt = $alternatives->fetch_assoc())
                $altList[] = $alt;
?>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" 
                                                    data-bs-toggle="modal" data-bs-target="#reassignModal<?php echo $e['enrollment_id']; ?>">
                                                <i class="fas fa-exchange-alt me-1"></i> Reassign
                                            </button>

                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" 
                                                    data-bs-toggle="modal" data-bs-target="#dropModal<?php echo $e['enrollment_id']; ?>">
                                                <i class="fas fa-trash-alt me-1"></i> Drop
                                            </button>

                                            <?php
            // Start buffering Drop Modal
            ob_start();
?>
                                            <div class="modal fade" id="dropModal<?php echo $e['enrollment_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <form method="POST">
                                                        <?php csrfField(); ?>
                                                        <input type="hidden" name="action" value="drop">
                                                        <input type="hidden" name="enrollment_id" value="<?php echo $e['enrollment_id']; ?>">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Unload Subject</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-start">
                                                                <p>Are you sure you want to unload/drop <strong><?php echo htmlspecialchars($e['course_code'] . ' - ' . $e['course_name']); ?></strong> for this student?</p>
                                                                <div class="alert alert-warning py-2 small">
                                                                    <i class="fas fa-exclamation-triangle"></i> This action will remove all enrollment records for this subject.
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger px-4 rounded-pill">Confirm Drop</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>

                                            <div class="modal fade text-start" id="reassignModal<?php echo $e['enrollment_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <form method="POST">
                                                        <?php csrfField(); ?>
                                                        <input type="hidden" name="action" value="reassign">
                                                        <input type="hidden" name="enrollment_id" value="<?php echo $e['enrollment_id']; ?>">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Reassign Section: <?php echo htmlspecialchars($e['course_code']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <?php if (!empty($studentCurrentSchedules)): ?>
                                                                    <div class="mb-3 p-3 bg-light border rounded">
                                                                        <h6 class="text-sm font-bold text-dark mb-2"><i class="fas fa-clock text-primary me-2"></i>Student's Existing Class Times:</h6>
                                                                        <div class="d-flex flex-wrap gap-2">
                                                                            <?php foreach ($studentCurrentSchedules as $os): ?>
                                                                                <span class="badge bg-white text-dark border p-2">
                                                                                    <strong><?php echo htmlspecialchars($os['course_code']); ?>:</strong> <?php echo htmlspecialchars($os['schedule']); ?>
                                                                                </span>
                                                                            <?php
                endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php
            endif; ?>

                                                                <p>Select a new section for <strong><?php echo htmlspecialchars($e['course_name']); ?></strong>:</p>
                                                                <div class="list-group">
                                                                    <?php
            $hasConflictOption = false;
            foreach ($altList as $alt):
                $isConflict = false;
                foreach ($studentCurrentSchedules as $os) {
                    if ($os['schedule'] !== 'TBA' && $alt['schedule'] !== 'TBA' && $os['schedule'] === $alt['schedule']) {
                        $isConflict = true;
                        $hasConflictOption = true;
                        break;
                    }
                }
?>
                                                                        <label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 <?php echo $isConflict ? 'list-group-item-warning opacity-75' : ''; ?>">
                                                                            <div class="d-flex align-items-center">
                                                                                <input class="form-check-input me-3" type="radio" name="new_section_id" value="<?php echo $alt['section_id']; ?>" required>
                                                                                <div>
                                                                                    <div class="fw-bold text-primary">
                                                                                        Section: <?php echo htmlspecialchars($alt['section_name']); ?>
                                                                                        <?php if ($isConflict): ?>
                                                                                            <span class="badge bg-danger ms-2"><i class="fas fa-exclamation-triangle"></i> SCHEDULE CONFLICT</span>
                                                                                        <?php
                endif; ?>
                                                                                    </div>
                                                                                    <div class="small <?php echo $isConflict ? 'text-danger fw-bold' : ''; ?>">
                                                                                        <i class="fas fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($alt['schedule'] ?? 'TBA'); ?> | 
                                                                                        <i class="fas fa-door-open me-1"></i> <?php echo htmlspecialchars($alt['room'] ?? 'TBA'); ?>
                                                                                    </div>
                                                                                    <div class="small text-muted">
                                                                                        <i class="fas fa-chalkboard-teacher me-1"></i> <?php echo htmlspecialchars($alt['instructor_name']); ?>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="text-end">
                                                                                <span class="badge rounded-pill bg-light text-dark border">
                                                                                    <i class="fas fa-users me-1 text-primary"></i> <?php echo htmlspecialchars($alt['student_count']); ?> Students
                                                                                </span>
                                                                            </div>
                                                                        </label>
                                                                    <?php
            endforeach; ?>
                                                                </div>
                                                                
                                                                <?php if ($hasConflictOption): ?>
                                                                    <div class="alert alert-warning mt-3 py-2 small">
                                                                        <i class="fas fa-exclamation-circle me-1"></i> Warning: Some sections may overlap with the student's existing schedule.
                                                                    </div>
                                                                <?php
            endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary px-4 rounded-pill">Confirm Reassign</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php
            $modalHtml .= ob_get_clean();
        else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" 
                                                    data-bs-toggle="modal" data-bs-target="#dropModal<?php echo $e['enrollment_id']; ?>">
                                                <i class="fas fa-trash-alt me-1"></i> Drop
                                            </button>
                                            <?php
            ob_start();
?>
                                            <div class="modal fade" id="dropModal<?php echo $e['enrollment_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <form method="POST">
                                                        <?php csrfField(); ?>
                                                        <input type="hidden" name="action" value="drop">
                                                        <input type="hidden" name="enrollment_id" value="<?php echo $e['enrollment_id']; ?>">
                                                        <div class="modal-content text-start">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Unload Subject</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to drop <strong><?php echo htmlspecialchars($e['course_code']); ?></strong>?</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger px-4 rounded-pill">Confirm Drop</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php
            $modalHtml .= ob_get_clean();
        endif;
        $altSessions->close();
?>
                                    </td>
                                </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted italic">
                                        No active enrollments found for this student.
                                    </td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3">
                <div class="d-flex justify-content-end">
                    <a href="students.php" class="btn btn-secondary btn-sm rounded-pill px-4">
                        <i class="fas fa-arrow-left me-1"></i> Back to Student List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Load Modal -->
<div class="modal fade" id="addLoadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="enroll">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Subject / Load</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 px-1">
                        <label class="form-label fw-bold text-primary small"><i class="fas fa-filter me-1"></i> Filter by Diploma Program / College</label>
                        <select id="modal_dept_filter" class="form-select select2">
                            <option value="">-- All Diploma Programs --</option>
                            <?php
$deptsQuery->data_seek(0);
while ($d = $deptsQuery->fetch_assoc()):
?>
                                <option value="<?php echo $d['dept_id']; ?>" <?php echo($d['dept_id'] == $deptId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['dept_name']); ?> (<?php echo $d['subject_count']; ?>)
                                </option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3 px-1">
                        <label class="form-label fw-bold text-primary small"><i class="fas fa-tags me-1"></i> Filter by Subject Type</label>
                        <select id="modal_type_filter" class="form-select select2">
                            <option value="">-- All Types --</option>
                            <option value="Major">Major Subjects</option>
                            <option value="Minor">Minor / Gen Ed</option>
                        </select>
                    </div>
                    <hr class="my-3">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fas fa-info-circle me-1"></i> Showing available subjects. You can now select from any diploma program.
                    </div>
                    <div class="list-group">
                        <?php if ($sectionsList->num_rows > 0): ?>
                            <?php
    $sectionsList->data_seek(0);
    while ($sec = $sectionsList->fetch_assoc()):
?>
                                <label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 subject-item" 
                                       data-dept="<?php echo $sec['dept_id']; ?>" 
                                       data-type="<?php echo $sec['course_type'] ?? 'Minor'; ?>">
                                    <div class="d-flex align-items-center">
                                        <input class="form-check-input me-3" type="radio" name="section_id" value="<?php echo $sec['section_id']; ?>" required>
                                        <div>
                                            <div class="fw-bold text-primary">
                                                <?php echo htmlspecialchars($sec['course_code']); ?> - <?php echo htmlspecialchars($sec['course_name']); ?>
                                            </div>
                                            <div class="small mb-1">
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo htmlspecialchars($sec['dept_name']); ?></span>
                                                <?php if (($sec['course_type'] ?? 'Minor') === 'Major'): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Major</span>
                                                <?php
        else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Minor</span>
                                                <?php
        endif; ?>
                                            </div>
                                            <div class="small text-dark">
                                                Section: <span class="badge bg-secondary"><?php echo htmlspecialchars($sec['section_name']); ?></span> | 
                                                SY/Sem: <span class="text-muted"><?php echo htmlspecialchars($sec['school_year'] . ' ' . $sec['semester']); ?></span>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <i class="fas fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($sec['schedule'] ?? 'TBA'); ?> | 
                                                <i class="fas fa-door-open me-1"></i> <?php echo htmlspecialchars($sec['room'] ?? 'TBA'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small fw-bold text-muted"><?php echo htmlspecialchars($sec['instructor_name']); ?></div>
                                    </div>
                                </label>
                            <?php
    endwhile; ?>
                        <?php
else: ?>
                            <div class="text-center py-4 text-muted">No other subjects available to add for this department.</div>
                        <?php
endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill" <?php echo($sectionsList->num_rows == 0) ? 'disabled' : ''; ?>>Add Subject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php

// Output all collected modals at the top level
echo $modalHtml;

?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Small delay to ensure Select2 script is fully loaded and attached to jQuery
    setTimeout(function() {
        if ($.fn.select2) {
            // Initialize Select2
            $('.select2').select2({
                dropdownParent: $('#addLoadModal'),
                width: '100%'
            });

            // Combined Filter Logic
            function filterSubjects() {
                const deptId = $('#modal_dept_filter').val();
                const typeFilter = $('#modal_type_filter').val();
                let visibleCount = 0;

                $('.subject-item').each(function() {
                    const itemDeptId = $(this).data('dept');
                    const itemType = $(this).data('type');
                    
                    const matchDept = !deptId || itemDeptId == deptId;
                    const matchType = !typeFilter || itemType == typeFilter;

                    if (matchDept && matchType) {
                        $(this).show();
                        visibleCount++;
                    } else {
                        $(this).hide();
                        $(this).find('input[type="radio"]').prop('checked', false);
                    }
                });

                // Show/hide "No subjects" message
                if (visibleCount === 0) {
                    if (!$('#no_subjects_msg').length) {
                        $('.list-group').append('<div id="no_subjects_msg" class="text-center py-4 text-muted">No subjects match your filters.</div>');
                    } else {
                        $('#no_subjects_msg').show();
                    }
                } else {
                    $('#no_subjects_msg').hide();
                }
            }

            $('#modal_dept_filter, #modal_type_filter').on('change', filterSubjects);
            
            // Run filter once on load to show current dept by default
            filterSubjects();
        } else {
            console.error('Select2 library not loaded.');
        }
    }, 100);
});
</script>

<?php require_once '../includes/footer.php'; ?>
