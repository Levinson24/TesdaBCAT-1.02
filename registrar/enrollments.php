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
    $sectionIds = $_POST['section_ids'] ?? [];
    
    if (empty($sectionIds)) {
        redirectWithMessage('enrollments.php', 'Please select at least one class section.', 'warning');
    }

    $successCount = 0;
    $errors = [];
    $sem = getSetting('current_semester', '1st');
    $sy = getSetting('academic_year', '2024-2025');

    // Track recently added schedules during this batch to prevent internal overlaps
    $batchSchedules = [];

    foreach ($sectionIds as $sectionId) {
        $sectionId = intval($sectionId);
        
        // Fetch Section details for reporting
        $secInfoStmt = $conn->prepare("
            SELECT cs.section_name, cur.subject_id, cs.schedule 
            FROM class_sections cs 
            JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id 
            WHERE cs.section_id = ?
        ");
        $secInfoStmt->bind_param("i", $sectionId);
        $secInfoStmt->execute();
        $secInfo = $secInfoStmt->get_result()->fetch_assoc();
        $secInfoStmt->close();
        $displayName = ($secInfo['subject_id'] ?? 'Unknown') . " (" . ($secInfo['section_name'] ?? 'N/A') . ")";

        // 1. Authorization Check (Clerk Restricted)
        if ($isStaff) {
            $sCheck = $conn->prepare("SELECT dept_id FROM students WHERE student_id = ?");
            $sCheck->bind_param("i", $studentId);
            $sCheck->execute();
            $sRes = $sCheck->get_result()->fetch_assoc();
            if ($sRes && $sRes['dept_id'] != $deptId) {
                $errors[] = "$displayName: Unauthorized (Student belongs to another department).";
                continue;
            }

            $secDeptCheck = $conn->prepare("
                SELECT cur.dept_id 
                FROM class_sections cs 
                JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id 
                WHERE cs.section_id = ?
            ");
            $secDeptCheck->bind_param("i", $sectionId);
            $secDeptCheck->execute();
            $secDeptRes = $secDeptCheck->get_result()->fetch_assoc();
            if ($secDeptRes && $secDeptRes['dept_id'] != $deptId) {
                $errors[] = "$displayName: Unauthorized (Section belongs to another department).";
                continue;
            }
        }

        // 2. Duplicate Check
        $check = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND section_id = ?");
        $check->bind_param("ii", $studentId, $sectionId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "$displayName: Student is already enrolled.";
            $check->close();
            continue;
        }
        $check->close();

        // 3. Capacity Check
        $capStmt = $conn->prepare("
            SELECT cs.max_students, 
                   (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id AND status != 'dropped') as enrolled_count
            FROM class_sections cs 
            WHERE cs.section_id = ?
        ");
        $capStmt->bind_param("i", $sectionId);
        $capStmt->execute();
        $cap = $capStmt->get_result()->fetch_assoc();
        $capStmt->close();

        if ($cap && $cap['enrolled_count'] >= ($cap['max_students'] ?? 40)) {
            $errors[] = "$displayName: Full capacity ({$cap['max_students']}).";
            continue;
        }

        // 4. Schedule Conflict Check (Against existing DB records)
        $conflict = checkStudentScheduleConflict($studentId, $sectionId, $sem, $sy);
        if ($conflict) {
            $errors[] = "$displayName: Conflict with " . $conflict['msg'];
            continue;
        }

        // 5. Internal Batch Conflict Check (Against other sections selected in this group)
        $currentParsed = parseSchedule($secInfo['schedule'] ?? '');
        $batchConflict = false;
        if ($currentParsed) {
            foreach ($batchSchedules as $prev) {
                if (isScheduleOverlapping($currentParsed, $prev['parsed'])) {
                    $errors[] = "$displayName: Internal conflict with " . $prev['name'] . " scheduled at " . $prev['time'];
                    $batchConflict = true;
                    break;
                }
            }
        }
        if ($batchConflict) continue;

        // All checks passed
        $stmt = $conn->prepare("INSERT INTO enrollments (student_id, section_id, enrollment_date, status) VALUES (?, ?, CURDATE(), 'enrolled')");
        $stmt->bind_param("ii", $studentId, $sectionId);
        if ($stmt->execute()) {
            $successCount++;
            if ($currentParsed) {
                $batchSchedules[] = [
                    'parsed' => $currentParsed,
                    'name' => $displayName,
                    'time' => $secInfo['schedule']
                ];
            }
            logAudit(getCurrentUserId(), 'CREATE', 'enrollments', $conn->insert_id, null, "Enrolled Student ID: $studentId to Section ID: $sectionId (Bulk)");
        }
    }

    if ($successCount > 0 && empty($errors)) {
        redirectWithMessage('enrollments.php', "Successfully enrolled student in $successCount subject(s).", 'success');
    } elseif ($successCount > 0) {
        $msg = "Partially Successful: Enrolled in $successCount subject(s).<br><small>Failed Items:</small><ul class='mb-0 mt-1' style='font-size:0.85rem;'>";
        foreach ($errors as $err) $msg .= "<li>" . htmlspecialchars($err) . "</li>";
        $msg .= "</ul>";
        redirectWithMessage('enrollments.php', $msg, 'warning');
    } else {
        $msg = "Enrollment Failed:<ul class='mb-0 mt-1' style='font-size:0.85rem;'>";
        foreach ($errors as $err) $msg .= "<li>" . htmlspecialchars($err) . "</li>";
        $msg .= "</ul>";
        redirectWithMessage('enrollments.php', $msg, 'danger');
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
    if ($checkGrades->get_result()->num_rows > 0 && !hasRole(['registrar', 'admin', 'dept_head'])) {
        redirectWithMessage('enrollments.php', 'Cannot unenroll student because grades have already been recorded for this enrollment.', 'warning');
    }
    $checkGrades->close();

    $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
    $stmt->bind_param("i", $enrollmentId);
    if ($stmt->execute()) {
        logAudit(getCurrentUserId(), 'DELETE', 'enrollments', $enrollmentId, null, "Unenrolled ID: $enrollmentId");
        redirectWithMessage('enrollments.php', 'Student unenrolled successfully.', 'success');
    }
    else {
        redirectWithMessage('enrollments.php', 'Failed to unenroll student.', 'danger');
    }
}

$pageTitle = 'Manage Enrollments';

$additionalCSS = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 3.5rem !important;
        border: 1.5px solid rgba(0, 56, 168, 0.15) !important;
        border-radius: 1rem !important;
        background-color: rgba(0, 56, 168, 0.02) !important;
        display: flex;
        align-items: center;
        padding-left: 2.5rem !important; /* Space for icon */
    }
    .select2-selection__rendered {
        color: #334155 !important;
        font-weight: 500 !important;
        font-size: 1rem !important;
        padding-left: 0 !important;
    }
    .select2-selection__arrow {
        height: 3.5rem !important;
        right: 15px !important;
    }
    .select2-dropdown {
        border-radius: 1rem !important;
        border: 1.5px solid rgba(0, 56, 168, 0.15) !important;
        box-shadow: 0 10px 25px rgba(0, 56, 168, 0.1) !important;
        overflow: hidden;
    }
    .form-text-custom {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.5rem;
    }
    /* Highlight matching program subjects in Select2 dropdown */
    .select2-results__option--highlight-program {
        color: #0038A8 !important;
        font-weight: 700 !important;
        background-color: rgba(0, 56, 168, 0.05) !important;
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
           cur.class_code, cur.subject_id, cs.section_name, cs.semester, cs.school_year
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    $enrollmentWhere
    ORDER BY e.enrollment_date DESC
");

// Safety Check
if (!$enrollments) {
    die("Error fetching enrollments: " . $conn->error);
}

$studentWhere = $isStaff ? " WHERE dept_id = $deptId AND status = 'active'" : " WHERE status = 'active'";
$students = $conn->query("SELECT student_id, student_no, CONCAT(first_name, ' ', last_name) as name, program_id FROM students $studentWhere");

// Safety Check
if (!$students) {
    die("Error fetching students: " . $conn->error);
}

$sectionWhere = $isStaff ? " WHERE cur.dept_id = $deptId AND cs.status = 'active'" : " WHERE cs.status = 'active'";
$sections = $conn->query("
    SELECT cs.section_id, 
           CONCAT(IFNULL(cur.class_code, 'N/A'), ' | ', IFNULL(cur.subject_id, 'N/A'), ' - ', IFNULL(cs.section_name, 'N/A'), ' (', IFNULL(subj.course_type, 'Minor'), ')') as display, 
           cur.program_id, subj.course_type
    FROM class_sections cs
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects subj ON cur.subject_id = subj.subject_id
    $sectionWhere
");

// Safety Check
if (!$sections) {
    die("Error fetching sections: " . $conn->error);
}

$progWhere = $isStaff ? " AND p.dept_id = $deptId" : "";
$programs = $conn->query("SELECT p.program_id, p.program_name, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' $progWhere ORDER BY p.program_name ASC");

// Safety Check
if (!$programs) {
    die("Error fetching programs: " . $conn->error);
}
?>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2"><i class="fas fa-user-plus me-2 text-warning"></i> Enrollment Management</h5>
        <div class="d-flex align-items-center gap-3">
            <div class="search-box-container">
                <div class="input-group input-group-sm rounded-pill overflow-hidden border-0 shadow-sm" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                    <span class="input-group-text bg-transparent border-0 text-white-50 ps-3">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="enrollmentSearchInput" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="Search Enrollments..." style="box-shadow: none;">
                </div>
            </div>
            <a href="bulk_enroll.php" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold border-0">
                <i class="fas fa-users-cog me-1"></i> Bulk Enroll
            </a>
            <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus-circle me-1"></i> Enroll Student
            </button>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-hover align-middle mb-0 premium-table data-table">
            <thead>
                <tr><th>Student</th><th>Class Code</th><th>Subject Code</th><th>Section</th><th>SY/Sem</th><th>Date</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
                <?php while ($e = $enrollments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['student_name'] ?? ''); ?><br><small><?php echo $e['student_no'] ?? ''; ?></small></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($e['class_code'] ?? 'N/A'); ?></span></td>
                    <td><strong><?php echo htmlspecialchars($e['subject_id'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars($e['section_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($e['semester'] ?? '') . ' ' . ($e['school_year'] ?? '')); ?></td>
                    <td><?php echo formatDate($e['enrollment_date']); ?></td>
                    <td class="text-nowrap pe-4">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="cor_print.php?student_id=<?php echo $e['student_id']; ?>" target="_blank" class="btn-premium-print" title="Print COR">
                                <i class="fas fa-print"></i>
                            </a>
                            <?php if (getCurrentUserRole() === 'registrar'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to unenroll this student? This will remove them from the class list.')">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete_enrollment">
                                <input type="hidden" name="enrollment_id" value="<?php echo $e['enrollment_id']; ?>">
                                <button type="submit" class="btn-premium-delete" title="Unenroll">
                                    <i class="fas fa-user-minus"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td></tr>
                <?php
endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="w-100">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="enroll">
            <div class="modal-content border-0">
                <div class="modal-header modal-premium-header gradient-navy">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i>
                        <span>Enroll Student</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-filter me-2"></i>Filter Options</span>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="premium-input-group mb-0">
                                <label>Filter by Program (Optional)</label>
                                <div class="input-wrapper">
                                    <select id="program_filter" class="form-select select2" style="width:100%;">
                                        <option value="">-- All Programs --</option>
                                        <?php while ($p = $programs->fetch_assoc()): ?>
                                            <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name'] . ' (' . $p['title_diploma_program'] . ')'); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="form-text-custom ps-2"><i class="fas fa-info-circle me-1"></i> Selecting a program narrows down the student and class lists.</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-file-signature me-2"></i>Enrollment Details</span>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="premium-input-group">
                                <label>Student</label>
                                <div class="input-wrapper">
                                    <select name="student_id" id="student_select" class="form-select select2" required style="width:100%;">
                                        <option value="">-- Search Student --</option>
                                        <?php $students->data_seek(0); while ($s = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $s['student_id']; ?>" data-program="<?php echo $s['program_id']; ?>">
                                            <?php echo htmlspecialchars(($s['student_no'] ?? '') . ' - ' . ($s['name'] ?? '')); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div id="student_program_hint" class="form-text-custom ps-2 text-primary fw-medium" style="display:none;">
                                    <i class="fas fa-check-circle me-1 text-success"></i> Student is from this Program Category
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-2">
                            <div class="premium-input-group">
                                <label>Class Section(s)</label>
                                <div class="input-wrapper">
                                    <select name="section_ids[]" id="section_select" class="form-select select2" required multiple style="width:100%;" data-placeholder="Search and select one or more sections...">
                                        <?php $sections->data_seek(0); while ($sec = $sections->fetch_assoc()): ?>
                                        <option value="<?php echo $sec['section_id']; ?>" data-program="<?php echo $sec['program_id']; ?>">
                                            <?php echo htmlspecialchars($sec['display'] ?? ''); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fas fa-chalkboard"></i>
                                </div>
                                <div class="form-text-custom ps-2"><i class="fas fa-info-circle me-1"></i> You can select multiple subjects for a student at once.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile">
                        <i class="fas fa-check-circle me-2"></i>Confirm Enrollment
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$additionalJS = <<<EOT
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    function initSelect2() {
        $('#student_select, #program_filter').select2({
            dropdownParent: $('#addModal'),
            width: '100%'
        });

        $('#section_select').each(function() {
            const el = $(this);
            el.select2({
                dropdownParent: $('#addModal'),
                width: '100%',
                placeholder: el.attr('data-placeholder'),
                allowClear: true,
                closeOnSelect: false,
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    const el = $(data.element);
                    const studentProgId = $('#student_select').find(':selected').data('program');
                    const programId = $('#program_filter').val();
                    
                    let wrapper = $('<span></span>').text(data.text);
                    if (studentProgId && el.data('program') == studentProgId && !programId) {
                        wrapper.addClass('select2-results__option--highlight-program');
                    }
                    return wrapper;
                }
            });
        });
    }

    initSelect2();

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
        
        // Refresh Select2 while maintaining custom settings
        initSelect2();
    });

    // Auto-detect Program when Student is selected
    $('#student_select').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const studentProgId = selectedOption.data('program');
        
        if (studentProgId) {
            $('#student_program_hint').fadeIn();
        } else {
            $('#student_program_hint').hide();
        }
        
        // Refresh section list to update highlights
        initSelect2();
    });
});
</script>
EOT;

require_once '../includes/footer.php';
?>
