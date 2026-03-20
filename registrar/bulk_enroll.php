<?php
/**
 * Bulk Enrollment — Registrar Module
 * Allows enrollment of multiple students into a section at once
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['registrar', 'registrar_staff'], '../index.php');

$pageTitle = 'Bulk Enrollment';
$conn = getDBConnection();

// Load available sections
$sectionsStmt = $conn->query("
    SELECT cs.section_id, cs.section_name, cs.semester, cs.school_year,
           c.course_code, c.course_name, c.units,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id AND status != 'dropped') AS enrolled_count,
           cs.max_students
    FROM class_sections cs
    JOIN courses c ON cs.course_id = c.course_id
    WHERE cs.status = 'active'
    ORDER BY cs.school_year DESC, cs.semester, c.course_code
");
$sections = $sectionsStmt->fetch_all(MYSQLI_ASSOC);

$successCount = 0;
$skipCount    = 0;
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $sectionId   = (int)($_POST['section_id'] ?? 0);
        $studentIds  = $_POST['student_ids'] ?? [];
        $enrollDate  = date('Y-m-d');

        if (!$sectionId || empty($studentIds)) {
            $errors[] = 'Please select a section and at least one student.';
        } else {
            // Verify section exists and is active
            $secCheck = $conn->prepare("SELECT section_id, max_students FROM class_sections WHERE section_id = ? AND status = 'active'");
            $secCheck->bind_param("i", $sectionId);
            $secCheck->execute();
            $secData = $secCheck->get_result()->fetch_assoc();
            $secCheck->close();

            if (!$secData) {
                $errors[] = 'Selected section is not available.';
            } else {
                $insertStmt = $conn->prepare("
                    INSERT IGNORE INTO enrollments (student_id, section_id, enrollment_date, status)
                    VALUES (?, ?, ?, 'enrolled')
                ");

                foreach ($studentIds as $sid) {
                    $sid = (int)$sid;
                    if (!$sid) continue;

                    // Check if already enrolled
                    $dupCheck = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND section_id = ?");
                    $dupCheck->bind_param("ii", $sid, $sectionId);
                    $dupCheck->execute();
                    if ($dupCheck->get_result()->num_rows > 0) {
                        $skipCount++;
                        $dupCheck->close();
                        continue;
                    }
                    $dupCheck->close();

                    $insertStmt->bind_param("iis", $sid, $sectionId, $enrollDate);
                    if ($insertStmt->execute()) {
                        logAudit(getCurrentUserId(), 'BULK_ENROLL', 'enrollments', $insertStmt->insert_id, null,
                            "Student {$sid} enrolled in section {$sectionId}");
                        $successCount++;
                    }
                }
                $insertStmt->close();

                if ($successCount > 0) {
                    redirectWithMessage('bulk_enroll.php',
                        "{$successCount} student(s) enrolled successfully." . ($skipCount ? " {$skipCount} already enrolled (skipped)." : ''),
                        'success');
                }
            }
        }
    }
}

// Load all active students for the multi-select
$studentsResult = $conn->query("
    SELECT s.student_id, s.student_no, s.first_name, s.last_name, s.year_level,
           p.program_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE s.status = 'active'
    ORDER BY s.last_name, s.first_name
");
$students = $studentsResult->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card premium-card overflow-hidden shadow-sm border-0">
            <div class="card-header gradient-navy p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-white fw-bold"><i class="fas fa-users-cog me-2 text-warning"></i>Bulk Enrollment Registry</h4>
                    <p class="text-white opacity-75 mb-0 small">Enroll multiple students into a section at once</p>
                </div>
                <a href="enrollments.php" class="btn btn-outline-light rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i>Back to Enrollments
                </a>
            </div>
        </div>
    </div>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger mb-3" style="border-radius:1rem;"><?php echo htmlspecialchars($err); ?></div>
<?php endforeach; ?>

    <div class="card-body p-4 pt-0">
        <form method="POST" id="bulkEnrollForm">
            <?php csrfField(); ?>

            <div class="row g-4">
                <!-- Left Column: Section Selection -->
                <div class="col-lg-5">
                    <div class="p-3 bg-light rounded-4 border h-100">
                        <label for="section_id" class="form-label fw-700 text-uppercase mb-3" style="font-size:0.75rem;letter-spacing:0.05em;color:#1e293b;">
                            <i class="fas fa-chalkboard me-1 text-primary"></i> Step 1 — Select Section
                        </label>
                        <select class="form-select border-0 shadow-sm mb-3" id="section_id" name="section_id" required onchange="loadSectionInfo(this)" style="height: 50px; border-radius: 12px;">
                            <option value="">— Choose a class section —</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo $sec['section_id']; ?>"
                                        data-enrolled="<?php echo $sec['enrolled_count']; ?>"
                                        data-max="<?php echo $sec['max_students']; ?>"
                                        <?php echo (isset($_POST['section_id']) && $_POST['section_id'] == $sec['section_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars("{$sec['course_code']} — {$sec['section_name']} ({$sec['semester']} {$sec['school_year']}) [{$sec['enrolled_count']}/{$sec['max_students']}]"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="sectionInfo" class="alert alert-info border-0 shadow-sm d-none mb-0" style="border-radius: 12px;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-2 fa-lg"></i>
                                <span id="sectionInfoText" class="small"></span>
                            </div>
                        </div>

                        <div class="mt-4 d-none d-lg-block">
                            <div class="text-center p-4">
                                <i class="fas fa-user-plus fa-4x text-primary opacity-10 mb-3"></i>
                                <p class="text-muted small">Select a section to begin enrolling students. Make sure to check available slots.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Student Selection -->
                <div class="col-lg-7">
                    <div class="p-3 bg-white rounded-4 border h-100">
                        <label class="form-label fw-700 text-uppercase mb-3" style="font-size:0.75rem;letter-spacing:0.05em;color:#1e293b;">
                            <i class="fas fa-user-check me-1 text-primary"></i> Step 2 — Select Students
                        </label>

                        <!-- Search filter -->
                        <div class="input-group mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                            <span class="input-group-text border-0 bg-white pe-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-0 ps-2" id="studentSearch" placeholder="Filter students by name, No., or program..."
                                   style="height: 45px;" oninput="filterStudents(this.value)">
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                            <div class="btn-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                <button type="button" class="btn btn-sm btn-light border-end" onclick="selectAll(true)">
                                    <i class="fas fa-check-square me-1 text-primary"></i>Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-light" onclick="selectAll(false)">
                                    <i class="fas fa-minus-square me-1 text-danger"></i>Clear
                                </button>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3 py-2" id="selectedCount">0 selected</span>
                        </div>

                        <div style="max-height:450px; overflow-y:auto; border:1px solid #f1f5f9; border-radius:12px; padding:0.5rem; background: #fdfdfd;" id="studentList" class="custom-scrollbar">
                            <?php foreach ($students as $stu):
                                $fullName = htmlspecialchars("{$stu['last_name']}, {$stu['first_name']}");
                                $prog     = htmlspecialchars($stu['program_name'] ?? '');
                            ?>
                            <label class="d-flex align-items-center gap-3 p-3 mb-2 student-item"
                                   style="border-radius:12px; cursor:pointer; transition:all 0.2s; border: 1px solid #f1f5f9; background: white;"
                                   onmouseover="this.style.borderColor='#3b82f6'; this.style.background='#f8fbff';" 
                                   onmouseout="this.style.borderColor='#f1f5f9'; this.style.background='white';"
                                   data-search="<?php echo strtolower($fullName . ' ' . $stu['student_no'] . ' ' . $prog); ?>">
                                <input type="checkbox" name="student_ids[]" value="<?php echo $stu['student_id']; ?>"
                                       class="form-check-input student-cb mt-0" onchange="updateCount()" style="width:20px; height:20px; cursor: pointer;">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo $fullName; ?></div>
                                    <div class="text-muted small d-flex flex-wrap gap-2 mt-1">
                                        <span class="badge bg-light text-dark fw-normal border"><?php echo $stu['student_no']; ?></span>
                                        <span class="badge bg-light text-dark fw-normal border">Yr <?php echo $stu['year_level']; ?></span>
                                        <span class="text-primary fw-600"><?php echo $prog; ?></span>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-top text-center text-lg-start">
                <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-lg transform-hover" id="submitBtn">
                    <i class="fas fa-user-plus me-2 text-warning fa-lg"></i> ENROLL SELECTED STUDENTS
                </button>
                <button type="reset" class="btn btn-link text-muted ms-lg-3 mt-3 mt-lg-0 text-decoration-none small" onclick="setTimeout(updateCount, 50)">
                    <i class="fas fa-undo me-1"></i> Reset Form
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.fw-700 { font-weight: 700; }
.fw-600 { font-weight: 600; }
.transform-hover { transition: transform 0.2s; }
.transform-hover:hover { box-shadow: 0 10px 20px rgba(0,56,168,0.2); }

.student-item:has(.student-cb:checked) {
    background: #eff6ff !important;
    border-color: #3b82f6 !important;
}
</style>

<script>
function loadSectionInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) { document.getElementById('sectionInfo').classList.add('d-none'); return; }
    const enrolled = opt.dataset.enrolled, max = opt.dataset.max;
    const avail = max - enrolled;
    const infoEl = document.getElementById('sectionInfo');
    document.getElementById('sectionInfoText').innerHTML =
        `<strong>${enrolled}</strong> / <strong>${max}</strong> students enrolled &mdash; <strong>${avail}</strong> slot(s) available.`;
    infoEl.classList.remove('d-none');
}

function filterStudents(query) {
    query = query.toLowerCase();
    document.querySelectorAll('.student-item').forEach(item => {
        item.style.display = (!query || item.dataset.search.includes(query)) ? '' : 'none';
    });
}

function selectAll(checked) {
    document.querySelectorAll('.student-item').forEach(item => {
        if (item.style.display !== 'none') {
            item.querySelector('.student-cb').checked = checked;
        }
    });
    updateCount();
}

function updateCount() {
    const n = document.querySelectorAll('.student-cb:checked').length;
    document.getElementById('selectedCount').textContent = n + ' selected';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
