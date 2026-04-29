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
        // Verify curriculum_id if provided
        if (isset($_POST['curriculum_id'])) {
            $cCheck = $conn->prepare("SELECT dept_id FROM curriculum WHERE curriculum_id = ?");
            $cCheck->bind_param("i", $_POST['curriculum_id']);
            $cCheck->execute();
            $cRes = $cCheck->get_result()->fetch_assoc();
            if ($cRes && $cRes['dept_id'] != $deptId) {
                redirectWithMessage('sections.php', 'Unauthorized: Subject belongs to another department.', 'danger');
            }
        }

        // If updating or deleting, verify the section belongs to the staff's department
        if (isset($_POST['section_id'])) {
            $checkStmt = $conn->prepare("SELECT cur.dept_id FROM class_sections cs JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id WHERE cs.section_id = ?");
            $checkStmt->bind_param("i", $_POST['section_id']);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result()->fetch_assoc();
            if ($checkRes && $checkRes['dept_id'] != $deptId) {
                redirectWithMessage('sections.php', 'Unauthorized: Section belongs to another department.', 'danger');
            }
        }
    }
    if ($_POST['action'] === 'create') {
        $curriculumId = intval($_POST['curriculum_id']);
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

        $stmt = $conn->prepare("INSERT INTO class_sections (curriculum_id, instructor_id, section_name, semester, school_year, schedule, room, dept_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("iisssssi", $curriculumId, $instructorId, $sectionName, $semester, $schoolYear, $schedule, $room, $deptId);
        try {
            if ($stmt->execute()) {
                logAudit(getCurrentUserId(), 'CREATE', 'class_sections', $conn->insert_id, null, "Created class section: $sectionName");
                redirectWithMessage('sections.php', 'Section created successfully', 'success');
            } else {
                redirectWithMessage('sections.php', 'Error creating section: ' . $conn->error, 'danger');
            }
        } catch (mysqli_sql_exception $e) {
            $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? 'Duplicate entry detected.' : $e->getMessage();
            redirectWithMessage('sections.php', 'Error: ' . $msg, 'danger');
        }
    } elseif ($_POST['action'] === 'update') {
        $sectionId = intval($_POST['section_id']);
        $curriculumId = intval($_POST['curriculum_id']);
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

        $stmt = $conn->prepare("UPDATE class_sections SET curriculum_id = ?, instructor_id = ?, section_name = ?, semester = ?, school_year = ?, schedule = ?, room = ?, status = ? WHERE section_id = ?");
        $stmt->bind_param("iissssssi", $curriculumId, $instructorId, $sectionName, $semester, $schoolYear, $schedule, $room, $status, $sectionId);
        try {
            if ($stmt->execute()) {
                logAudit(getCurrentUserId(), 'UPDATE', 'class_sections', $sectionId, null, "Updated class section: $sectionName");
                redirectWithMessage('sections.php', 'Section updated successfully', 'success');
            } else {
                redirectWithMessage('sections.php', 'Error updating section: ' . $conn->error, 'danger');
            }
        } catch (mysqli_sql_exception $e) {
            $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? 'Duplicate entry detected.' : $e->getMessage();
            redirectWithMessage('sections.php', 'Error: ' . $msg, 'danger');
        }
    } elseif ($_POST['action'] === 'delete') {
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
        } else {
            redirectWithMessage('sections.php', 'Error deleting section: ' . $conn->error, 'danger');
        }
    }
    // Legacy quick_create_course removed in favor of AJAX at ajax/quick_subject.php
}

$additionalCSS = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.select2-container--default .select2-selection--single {
    height: 3.5rem;
    border-radius: 1rem;
    border: 1.5px solid rgba(0, 56, 168, 0.15);
    background-color: transparent;
    display: flex;
    align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: normal;
    padding-left: 3.5rem;
    color: #1e293b;
    font-weight: 500;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 100%;
    right: 1.25rem;
}
.select2-dropdown {
    border-radius: 0.75rem;
    border: 1px solid rgba(0, 56, 168, 0.15);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.select2-search__field {
    border-radius: 0.5rem !important;
}
</style>
';

$pageTitle = 'Manage Class Sections';
require_once '../includes/header.php';

// Fetch lists for forms
$curriculumWhere = $isStaff ? " WHERE dept_id = $deptId AND status = 'active'" : " WHERE status = 'active'";
$curriculum_res = $conn->query("SELECT cur.*, s.subject_name, s.subject_id FROM curriculum cur JOIN subjects s ON cur.subject_id = s.subject_id $curriculumWhere ORDER BY s.subject_id");

// Safety Check
if (!$curriculum_res) {
    die("Error fetching curriculum: " . $conn->error);
}

$instructorWhere = $isStaff ? " WHERE dept_id = $deptId AND status = 'active'" : " WHERE status = 'active'";
$instructors_res = $conn->query("SELECT * FROM instructors $instructorWhere ORDER BY last_name");

// Safety Check
if (!$instructors_res) {
    die("Error fetching instructors: " . $conn->error);
}

$sectionWhere = $isStaff ? " WHERE cur.dept_id = $deptId" : "";
$sections = $conn->query("
    SELECT cs.*, s.subject_id, s.subject_name, i.first_name, i.last_name,
    (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id) as enrolled_count
    FROM class_sections cs 
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects s ON cur.subject_id = s.subject_id 
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    $sectionWhere
    ORDER BY cs.school_year DESC, cs.semester, s.subject_id
");

// Safety Check
if (!$sections) {
    die("Error fetching sections: " . $conn->error);
}

$curriculum_list = $conn->query("
    SELECT cur.*, s.subject_name, p.program_name 
    FROM curriculum cur 
    JOIN subjects s ON cur.subject_id = s.subject_id
    LEFT JOIN programs p ON cur.program_id = p.program_id 
    WHERE cur.status = 'active'
    " . ($isStaff ? " AND cur.dept_id = $deptId" : "") . "
    ORDER BY p.program_name, s.subject_id
");

// Safety Check
if (!$curriculum_list) {
    die("Error fetching active curriculum: " . $conn->error);
}
$instructorFilter = $isStaff ? " AND dept_id = $deptId" : "";
$instructors = $conn->query("SELECT instructor_id, CONCAT(first_name, ' ', last_name) as name FROM instructors WHERE status = 'active' $instructorFilter ORDER BY last_name");

// Fetch programs for quick create
$progFilter = $isStaff ? " AND p.dept_id = $deptId" : "";
$prog_res = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' $progFilter ORDER BY p.program_name ASC");
$programs_list = [];
while ($p = $prog_res->fetch_assoc()) {
    $programs_list[] = $p;
}

// === Premium Styles ===
?>
<style>
    
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

    .premium-input-group label {
        display: block;
        font-size: 0.75rem;
        font-weight: 800;
        color: #0038A8 !important;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
        letter-spacing: 0.05em;
    }

    /* ──── STICKY REGISTRY HEADERS ──── */
    .premium-card .card-header {
        /* position: sticky; */
        /* top: -1.5rem; */
        z-index: 1020;
        box-shadow: 0 4px 20px rgba(30, 41, 59, 0.1);
    }
    
    .sections-table thead th {
        /* position: sticky; */
        /* top: 5.3rem; */
        background: #ffffff;
        /* z-index: 1010; */
        box-shadow: inset 0 -1.5px 0 #e2e8f0;
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
    }
    
    .table-responsive {
        /* overflow: visible !important; */
    }
    
</style>

<div class="card premium-card border-0 shadow-sm rounded-4 mb-4 flex-grow-1 d-flex flex-column">
    <div
        class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top-4 gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-list-ol me-2 text-warning"></i> Class Sections Registry
        </h5>

        <div class="search-box-container">
            <div class="search-box-premium">
                <i class="fas fa-search"></i>
                <input type="text" id="sectionSearchInput" class="form-control" placeholder="Search sections..."
                    onkeyup="filterSections()">
                <span class="ps-2 pe-3 text-white-50" id="searchCounter"
                    style="font-size: 0.75rem; font-weight: 600; white-space: nowrap;"></span>
            </div>
        </div>

        <button class="btn-premium-secondary py-2"
            style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: white !important;"
            data-bs-toggle="modal" data-bs-target="#bulkGenerateModal">
            <i class="fas fa-magic"></i> Bulk Generate
        </button>
        <button class="btn-premium-action px-4 py-2"
            style="background: white; color: var(--primary-indigo) !important; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"
            data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle"></i> Add Section
        </button>
    </div>
    <div class="card-body p-0 flex-grow-1 d-flex flex-column">
        <div class="table-responsive flex-grow-1">
            <table class="table table-hover align-middle mb-0 sections-table premium-table data-table">
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
                                    <div class="sect-icon-box" style="flex-direction: column; justify-content: center;">
                                        <span><?php echo substr($s['section_name'] ?? 'S', 0, 1); ?></span>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-primary d-flex align-items-center">
                                            <?php echo htmlspecialchars($s['section_name'] ?? ''); ?>
                                            <span
                                                class="badge bg-secondary bg-opacity-25 text-secondary ms-2 border border-secondary border-opacity-25"
                                                style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                                SEC-<?php echo str_pad($s['section_id'], 4, '0', STR_PAD_LEFT); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted d-block"
                                            style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <strong
                                                class="text-dark"><?php echo htmlspecialchars($s['subject_id'] ?? ''); ?></strong>:
                                            <?php echo htmlspecialchars($s['subject_name'] ?? ''); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?php echo htmlspecialchars(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')); ?>
                                </div>
                                <small class="text-muted">Faculty ID:
                                    <?php echo htmlspecialchars($s['instructor_id'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <div class="fw-bold text-indigo" style="font-size: 0.8rem;"><i
                                        class="far fa-clock me-1"></i>
                                    <?php echo htmlspecialchars($s['schedule'] ?? 'TBA'); ?></div>
                                <div class="text-muted small"><i class="fas fa-door-open me-1"></i>
                                    <?php echo htmlspecialchars($s['room'] ?? 'TBA'); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-info bg-opacity-10 text-info px-3">
                                    <?php echo $s['enrolled_count']; ?> Studs
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['semester'] ?? ''); ?> Sem
                                </div>
                                <div class="text-muted small">SY <?php echo htmlspecialchars($s['school_year'] ?? ''); ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <?php $status = $s['status'] ?? 'active'; ?>
                                <span
                                    class="status-pill status-<?php echo ($s['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                                    <div class="status-dot"
                                        style="background: <?php echo ($s['status'] ?? 'active') === 'active' ? '#22c55e' : '#94a3b8'; ?>;">
                                    </div> <?php echo ucfirst($s['status'] ?? 'active'); ?>
                                </span>
                            </td>
                            <td class="text-nowrap pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn-premium-edit" onclick='editSection(<?php echo json_encode($s); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (getCurrentUserRole() === 'registrar'): ?>
                                        <form method="POST" class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to delete this section?')">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="section_id" value="<?php echo $s['section_id']; ?>">
                                            <button type="submit" class="btn-premium-delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        <?php
                                    endif; ?>
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
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <form method="POST" autocomplete="off" id="addSectionForm" onsubmit="return handleAddSectionSubmit(event)">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header modal-premium-header gradient-navy py-3 px-4 border-0 rounded-top-4">
                        <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                            <i class="fas fa-plus-circle me-3 text-warning shadow-sm p-2 rounded-circle"
                                style="background: rgba(255,255,255,0.1);"></i>
                            Add Class Section
                        </h5>
                        <button type="button" class="btn-close btn-close-white opacity-75"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3 px-md-4 bg-light" style="max-height: 80vh; overflow-y: auto;">
                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-lg-6">
                                <div class="form-section-divider mb-3 mt-0">
                                    <span><i class="fas fa-book-open me-2"></i>Subject Details</span>
                                </div>

                                <div class="premium-input-group mb-4">
                                    <label>Subject Selection</label>
                                    <div class="input-wrapper position-relative">
                                        <select name="curriculum_id" class="form-select bg-white shadow-sm"
                                            style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                            required>
                                            <option value="">-- Select Subject --</option>
                                            <?php $curriculum_list->data_seek(0);
                                            while ($c = $curriculum_list->fetch_assoc()): ?>
                                                <option value="<?php echo $c['curriculum_id']; ?>" data-dept="<?php echo $c['dept_id']; ?>">
                                                    [#<?php echo str_pad($c['curriculum_id'], 3, '0', STR_PAD_LEFT); ?>] 
                                                    <?php echo htmlspecialchars(($c['subject_id'] ?? '') . ' - ' . ($c['subject_name'] ?? '')); ?> 
                                                    (<?php echo htmlspecialchars($c['program_name'] ?? 'N/A'); ?>)
                                                </option>
                                                <?php
                                            endwhile; ?>
                                        </select>
                                        <i class="fas fa-book text-primary position-absolute"
                                            style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                    </div>
                                    <div class="form-text mt-2 ms-2">
                                        <i class="fas fa-search me-1 text-muted"></i> Can't find the subject? <a
                                            href="javascript:void(0)" onclick="openQuickCourse()"
                                            class="text-primary text-decoration-none fw-bold border-bottom border-primary border-2 pb-1 hover-fx">Quick
                                            Create Subject</a>
                                    </div>
                                </div>

                                <div class="form-section-divider mb-3 mt-1">
                                    <span><i class="fas fa-map-marker-alt me-2"></i>Schedule & Location</span>
                                </div>

                                <div class="row g-4 mb-4 mb-lg-0">
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Schedule <span class="ms-1 font-monospace pb-1"
                                                    id="addScheduleFeedback"
                                                    style="display:none; font-size: 0.65rem;"></span></label>
                                            <div class="input-wrapper position-relative">
                                                <select name="schedule" id="add_schedule_select"
                                                    class="form-select bg-white shadow-sm live-schedule-check"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                    <option value="">-- Select Schedule --</option>
                                                    <option value="TBA">TBA (To Be Announced)</option>
                                                    <optgroup label="MWF Sessions">
                                                        <option value="MWF 07:30AM-09:00AM">MWF 07:30AM - 09:00AM
                                                        </option>
                                                        <option value="MWF 09:00AM-10:30AM">MWF 09:00AM - 10:30AM
                                                        </option>
                                                        <option value="MWF 10:30AM-12:00PM">MWF 10:30AM - 12:00PM
                                                        </option>
                                                        <option value="MWF 01:00PM-02:30PM">MWF 01:00PM - 02:30PM
                                                        </option>
                                                        <option value="MWF 02:30PM-04:00PM">MWF 02:30PM - 04:00PM
                                                        </option>
                                                        <option value="MWF 04:00PM-05:30PM">MWF 04:00PM - 05:30PM
                                                        </option>
                                                    </optgroup>
                                                    <optgroup label="T/Th Sessions">
                                                        <option value="TTH 07:30AM-09:00AM">TTH 07:30AM - 09:00AM
                                                        </option>
                                                        <option value="TTH 09:00AM-10:30AM">TTH 09:00AM - 10:30AM
                                                        </option>
                                                        <option value="TTH 10:30AM-12:00PM">TTH 10:30AM - 12:00PM
                                                        </option>
                                                        <option value="TTH 01:00PM-02:30PM">TTH 01:00PM - 02:30PM
                                                        </option>
                                                        <option value="TTH 02:30PM-04:00PM">TTH 02:30PM - 04:00PM
                                                        </option>
                                                        <option value="TTH 04:00PM-05:30PM">TTH 04:00PM - 05:30PM
                                                        </option>
                                                    </optgroup>
                                                </select>
                                                <i class="fas fa-clock text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Room</label>
                                            <div class="input-wrapper position-relative">
                                                <input type="text" name="room" class="form-control bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    placeholder="TBA">
                                                <i class="fas fa-door-open text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <div class="form-section-divider mb-3 mt-0">
                                    <span><i class="fas fa-users-class me-2"></i>Class Information</span>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Instructor</label>
                                            <div class="input-wrapper position-relative">
                                                <select name="instructor_id" class="form-select bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                    <option value="">-- Select --</option>
                                                    <?php $instructors->data_seek(0);
                                                    while ($i = $instructors->fetch_assoc()): ?>
                                                        <option value="<?php echo $i['instructor_id']; ?>">
                                                            <?php echo htmlspecialchars($i['name'] ?? ''); ?></option>
                                                        <?php
                                                    endwhile; ?>
                                                </select>
                                                <i class="fas fa-chalkboard-teacher text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Section Name</label>
                                            <div class="input-wrapper position-relative">
                                                <input type="text" name="section_name"
                                                    class="form-control bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                <i class="fas fa-users text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-0 mt-2">
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>School Year</label>
                                            <div class="input-wrapper position-relative">
                                                <input type="text" name="school_year"
                                                    class="form-control bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    value="<?php echo getSetting('academic_year', '2024-2025'); ?>"
                                                    required>
                                                <i class="fas fa-calendar-alt text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Semester</label>
                                            <div class="input-wrapper position-relative">
                                                <select name="semester" class="form-select bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                    <option value="1st">1st</option>
                                                    <option value="2nd">2nd</option>
                                                    <option value="Summer">Summer</option>
                                                </select>
                                                <i class="fas fa-hourglass-half text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="modal-footer bg-white border-top-0 py-3 px-4 px-md-5 rounded-bottom-4 d-flex justify-content-between">
                        <button type="button" class="btn-premium-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-premium-action px-5">
                            <i class="fas fa-plus-circle"></i> Create Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <form method="POST" autocomplete="off" id="editSectionForm" onsubmit="return handleEditSectionSubmit(event)">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="section_id" id="edit_section_id">
                    <div class="modal-header modal-premium-header gradient-navy py-3 px-4 border-0 rounded-top-4">
                        <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                            <i class="fas fa-edit me-3 text-warning shadow-sm p-2 rounded-circle"
                                style="background: rgba(255,255,255,0.1);"></i>
                            Edit Class Section
                        </h5>
                        <button type="button" class="btn-close btn-close-white opacity-75"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3 px-md-4 bg-light" style="max-height: 80vh; overflow-y: auto;">
                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-lg-6">
                                <div class="form-section-divider mb-3 mt-0">
                                    <span><i class="fas fa-book-open me-2"></i>Subject Details</span>
                                </div>

                                <div class="premium-input-group mb-4">
                                    <label>Subject Selection</label>
                                    <div class="input-wrapper position-relative">
                                        <select name="curriculum_id" id="edit_curriculum_id"
                                            class="form-select bg-white shadow-sm"
                                            style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                            required>
                                            <option value="">-- Select Subject --</option>
                                            <?php $curriculum_list->data_seek(0);
                                            while ($c = $curriculum_list->fetch_assoc()): ?>
                                                <option value="<?php echo $c['curriculum_id']; ?>">
                                                    [#<?php echo str_pad($c['curriculum_id'], 3, '0', STR_PAD_LEFT); ?>] 
                                                    <?php echo htmlspecialchars(($c['subject_id'] ?? '') . ' - ' . ($c['subject_name'] ?? '')); ?> 
                                                    (<?php echo htmlspecialchars($c['program_name'] ?? 'N/A'); ?>)
                                                </option>
                                                <?php
                                            endwhile; ?>
                                        </select>
                                        <i class="fas fa-book text-primary position-absolute"
                                            style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                    </div>
                                </div>

                                <div class="form-section-divider mb-3 mt-1">
                                    <span><i class="fas fa-map-marker-alt me-2"></i>Schedule & Location</span>
                                </div>

                                <div class="row g-4 mb-4 mb-lg-0">
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Schedule <span class="ms-1 font-monospace pb-1"
                                                    id="editScheduleFeedback"
                                                    style="display:none; font-size: 0.65rem;"></span></label>
                                            <div class="input-wrapper position-relative">
                                                <select name="schedule" id="edit_schedule"
                                                    class="form-select bg-white shadow-sm live-schedule-check edit-group"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                    <option value="">-- Select Schedule --</option>
                                                    <option value="TBA">TBA (To Be Announced)</option>
                                                    <optgroup label="MWF Sessions">
                                                        <option value="MWF 07:30AM-09:00AM">MWF 07:30AM - 09:00AM
                                                        </option>
                                                        <option value="MWF 09:00AM-10:30AM">MWF 09:00AM - 10:30AM
                                                        </option>
                                                        <option value="MWF 10:30AM-12:00PM">MWF 10:30AM - 12:00PM
                                                        </option>
                                                        <option value="MWF 01:00PM-02:30PM">MWF 01:00PM - 02:30PM
                                                        </option>
                                                        <option value="MWF 02:30PM-04:00PM">MWF 02:30PM - 04:00PM
                                                        </option>
                                                        <option value="MWF 04:00PM-05:30PM">MWF 04:00PM - 05:30PM
                                                        </option>
                                                    </optgroup>
                                                    <optgroup label="T/Th Sessions">
                                                        <option value="TTH 07:30AM-09:00AM">TTH 07:30AM - 09:00AM
                                                        </option>
                                                        <option value="TTH 09:00AM-10:30AM">TTH 09:00AM - 10:30AM
                                                        </option>
                                                        <option value="TTH 10:30AM-12:00PM">TTH 10:30AM - 12:00PM
                                                        </option>
                                                        <option value="TTH 01:00PM-02:30PM">TTH 01:00PM - 02:30PM
                                                        </option>
                                                        <option value="TTH 02:30PM-04:00PM">TTH 02:30PM - 04:00PM
                                                        </option>
                                                        <option value="TTH 04:00PM-05:30PM">TTH 04:00PM - 05:30PM
                                                        </option>
                                                    </optgroup>
                                                </select>
                                                <i class="fas fa-clock text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Room</label>
                                            <div class="input-wrapper position-relative">
                                                <input type="text" name="room" id="edit_room"
                                                    class="form-control bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;">
                                                <i class="fas fa-door-open text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <div class="form-section-divider mb-3 mt-0">
                                    <span><i class="fas fa-users-class me-2"></i>Class Information</span>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Instructor</label>
                                            <div class="input-wrapper position-relative">
                                                <select name="instructor_id" id="edit_instructor_id"
                                                    class="form-select bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                    <?php $instructors->data_seek(0);
                                                    while ($i = $instructors->fetch_assoc()): ?>
                                                        <option value="<?php echo $i['instructor_id']; ?>">
                                                            <?php echo htmlspecialchars($i['name'] ?? ''); ?></option>
                                                        <?php
                                                    endwhile; ?>
                                                </select>
                                                <i class="fas fa-chalkboard-teacher text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Section Name</label>
                                            <div class="input-wrapper position-relative">
                                                <input type="text" name="section_name" id="edit_section_name"
                                                    class="form-control bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                <i class="fas fa-users text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>School Year</label>
                                            <div class="input-wrapper position-relative">
                                                <input type="text" name="school_year" id="edit_school_year"
                                                    class="form-control bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                <i class="fas fa-calendar-alt text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="premium-input-group">
                                            <label>Semester</label>
                                            <div class="input-wrapper position-relative">
                                                <select name="semester" id="edit_semester"
                                                    class="form-select bg-white shadow-sm"
                                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                                    required>
                                                    <option value="1st">1st</option>
                                                    <option value="2nd">2nd</option>
                                                    <option value="Summer">Summer</option>
                                                </select>
                                                <i class="fas fa-hourglass-half text-primary position-absolute"
                                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="premium-input-group mb-3">
                                    <label>Status</label>
                                    <div class="input-wrapper position-relative">
                                        <select name="status" id="edit_status" class="form-select bg-white shadow-sm"
                                            style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                            required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                        <i class="fas fa-toggle-on text-primary position-absolute"
                                            style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="modal-footer bg-white border-top-0 py-3 px-4 px-md-5 rounded-bottom-4 d-flex justify-content-between">
                        <button type="button" class="btn-premium-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-premium-action px-5">
                            <i class="fas fa-save"></i> Update Section
                        </button>
                    </div>
                </form>
            </div>
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
            if (addModalEl && typeof bootstrap !== 'undefined') {
                const addModal = bootstrap.Modal.getInstance(addModalEl) || new bootstrap.Modal(addModalEl);
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
            <div class="modal-content border-0 shadow-lg rounded-4">
                <form id="quickSubjectForm" method="POST" autocomplete="off">
                    <?php csrfField(); ?>
                    <div class="modal-header modal-premium-header gradient-navy py-3 px-4 border-0 rounded-top-4">
                        <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                            <i class="fas fa-book-medical me-3 text-warning shadow-sm p-2 rounded-circle"
                                style="background: rgba(255,255,255,0.1);"></i>
                            Quick Create Subject
                        </h5>
                        <button type="button" class="btn-close btn-close-white opacity-75"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3 px-md-4 bg-light" style="max-height: 80vh; overflow-y: auto;">
                        <div id="quickSubjectAlert"
                            class="alert d-none py-3 border-0 shadow-sm rounded-4 mb-4 fw-medium text-center"></div>

                        <div class="form-section-divider mb-4">
                            <span><i class="fas fa-info-circle me-2"></i>Subject Identifiers</span>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <label>Class Code</label>
                                    <div class="input-wrapper position-relative">
                                        <input type="text" name="class_code" class="form-control bg-white shadow-sm"
                                            style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                            placeholder="6 chars" required maxlength="6" minlength="6">
                                        <i class="fas fa-barcode text-primary position-absolute"
                                            style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <label>Subject Code</label>
                                    <div class="input-wrapper position-relative">
                                        <input type="text" name="course_code" class="form-control bg-white shadow-sm"
                                            style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                            placeholder="6 chars" required maxlength="6" minlength="6">
                                        <i class="fas fa-qrcode text-primary position-absolute"
                                            style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="premium-input-group mb-4">
                            <label>Subject Description</label>
                            <div class="input-wrapper position-relative">
                                <input type="text" name="course_name" class="form-control bg-white shadow-sm"
                                    style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                    placeholder="Complete course title" required>
                                <i class="fas fa-book text-primary position-absolute"
                                    style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                            </div>
                        </div>

                        <div class="form-section-divider mb-4">
                            <span><i class="fas fa-graduation-cap me-2"></i>Academic Details</span>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <label>Units</label>
                                    <div class="input-wrapper position-relative">
                                        <input type="number" name="units"
                                            class="form-control bg-white shadow-sm border-0"
                                            style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                            value="3" required>
                                        <i class="fas fa-calculator text-primary position-absolute"
                                            style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <label>Program (Course)</label>
                                    <div class="input-wrapper position-relative">
                                        <select name="program_id" class="form-select bg-white shadow-sm"
                                            style="padding-left: 2.75rem; height: 2.85rem; border-radius: 0.75rem;"
                                            required>
                                            <option value="">-- Select --</option>
                                            <?php foreach ($programs_list as $prog): ?>
                                                <option value="<?php echo $prog['program_id']; ?>">
                                                    <?php echo htmlspecialchars($prog['program_name'] ?? ''); ?></option>
                                                <?php
                                            endforeach; ?>
                                        </select>
                                        <i class="fas fa-graduation-cap text-primary position-absolute"
                                            style="left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div
                        class="modal-footer bg-white border-top-0 py-3 px-4 px-md-5 rounded-bottom-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"
                            style="background: linear-gradient(135deg, #0038A8 0%, #002366 100%);">
                            <i class="fas fa-plus-circle me-2"></i> Create Subject
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $additionalJS = <<<EOT


?><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js">
$(document).ready(function() {
    if ($.fn.select2) {
        $('#addModal select[name="curriculum_id"]').select2({
            dropdownParent: $('#addModal'),
            width: '100%',
            placeholder: "-- Select Subject --"
        });
        $('#edit_curriculum_id').select2({
            dropdownParent: $('#editModal'),
            width: '100%',
            placeholder: "-- Select Subject --"
        });
    }
    
    // Also apply to instructors for consistency
    $('#addModal select[name="instructor_id"]').select2({
        dropdownParent: $('#addModal'),
        width: '100%'
    });
    $('#edit_instructor_id').select2({
        dropdownParent: $('#editModal'),
        width: '100%'
    });
});
    // Real-time Schedule Validation
    function checkScheduleConflict(modalPrefix) {
        var instr_id_sel = modalPrefix === 'add' ? '' : 'edit_';
        var sched_el_id = modalPrefix === 'add' ? 'add_schedule_select' : 'edit_schedule';
        var room_el_id = modalPrefix === 'add' ? '' : 'edit_';
        
        var instructor_id = $('#' + instr_id_sel + 'instructor_id').val() || $('#' + modalPrefix + 'Modal select[name="instructor_id"]').val();
        var schedule = $('#' + sched_el_id).val();
        var room = $('#' + room_el_id + 'room').val() || $('#' + modalPrefix + 'Modal input[name="room"]').val();
        var semester = $('#' + modalPrefix + 'Modal select[name="semester"]').val();
        var school_year = $('#' + modalPrefix + 'Modal input[name="school_year"]').val();
        var section_id = modalPrefix === 'edit' ? $('#edit_section_id').val() : null;
        
        var feedbackEl = $('#' + modalPrefix + 'ScheduleFeedback');
        
        if (!instructor_id || !schedule || !semester || !school_year) {
            feedbackEl.hide();
            return;
        }

        $.ajax({
            url: 'ajax/check_schedule_conflict.php',
            type: 'POST',
            data: { 
                instructor_id: instructor_id, 
                schedule: schedule, 
                room: room, 
                semester: semester, 
                school_year: school_year, 
                section_id: section_id 
            },
            success: function(resp) {
                feedbackEl.show();
                if (resp.conflict) {
                    feedbackEl.removeClass('text-success').addClass('text-danger');
                    feedbackEl.html('<i class="fas fa-exclamation-triangle"></i> Conflict: ' + resp.type);
                    feedbackEl.attr('title', resp.msg);
                } else {
                    feedbackEl.removeClass('text-danger').addClass('text-success');
                    feedbackEl.html('<i class="fas fa-check-circle"></i> Available');
                    feedbackEl.attr('title', resp.msg);
                }
            }
        });
    }

    // Attach listeners
   


</script>
EOT;
?>
    <div class="modal fade" id="bulkGenerationModal" tabindex="1">...</div>
    <div class="modal fade" id="addModal" tabindex="1">...</div>
    <?php require_once 'includes/modals/edit_section.php'; 
    
?>