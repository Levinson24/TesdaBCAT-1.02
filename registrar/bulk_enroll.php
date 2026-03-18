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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-users-cog me-2 text-primary"></i>Bulk Enrollment</h4>
        <p class="text-muted mb-0">Enroll multiple students into a section at once</p>
    </div>
    <a href="enrollments.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Enrollments
    </a>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger mb-3" style="border-radius:1rem;"><?php echo htmlspecialchars($err); ?></div>
<?php endforeach; ?>

<div class="card border-0 shadow-sm mb-4" style="border-radius:1.25rem;">
    <div class="card-body p-4">
        <form method="POST" id="bulkEnrollForm">
            <?php csrfField(); ?>

            <!-- Step 1: Select Section -->
            <div class="mb-4">
                <label for="section_id" class="form-label fw-700 text-uppercase" style="font-size:0.75rem;letter-spacing:0.05em;color:#64748b;">
                    <i class="fas fa-chalkboard me-1 text-primary"></i> Step 1 — Select Section
                </label>
                <select class="form-select" id="section_id" name="section_id" required onchange="loadSectionInfo(this)">
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
                <div id="sectionInfo" class="mt-2 small text-muted d-none">
                    <i class="fas fa-info-circle me-1"></i>
                    <span id="sectionInfoText"></span>
                </div>
            </div>

            <!-- Step 2: Select Students -->
            <div class="mb-4">
                <label class="form-label fw-700 text-uppercase" style="font-size:0.75rem;letter-spacing:0.05em;color:#64748b;">
                    <i class="fas fa-user-check me-1 text-primary"></i> Step 2 — Select Students
                </label>

                <!-- Search filter -->
                <div class="mb-2">
                    <input type="text" class="form-control" id="studentSearch" placeholder="🔍 Filter students by name, No., or program..."
                           style="border-radius:0.75rem;" oninput="filterStudents(this.value)">
                </div>

                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll(true)" style="border-radius:0.5rem;">
                        <i class="fas fa-check-double me-1"></i>Select All Visible
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(false)" style="border-radius:0.5rem;">
                        <i class="fas fa-times me-1"></i>Clear Selection
                    </button>
                    <span class="ms-auto text-muted my-auto small" id="selectedCount">0 selected</span>
                </div>

                <div style="max-height:340px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:1rem;padding:0.5rem;" id="studentList">
                    <?php foreach ($students as $stu):
                        $fullName = htmlspecialchars("{$stu['last_name']}, {$stu['first_name']}");
                        $prog     = htmlspecialchars($stu['program_name'] ?? '');
                    ?>
                    <label class="d-flex align-items-center gap-3 p-2 student-item"
                           style="border-radius:0.5rem;cursor:pointer;transition:background 0.15s;"
                           onmouseover="this.style.background='#f0f4f8'" onmouseout="this.style.background=''"
                           data-search="<?php echo strtolower($fullName . ' ' . $stu['student_no'] . ' ' . $prog); ?>">
                        <input type="checkbox" name="student_ids[]" value="<?php echo $stu['student_id']; ?>"
                               class="form-check-input student-cb" onchange="updateCount()" style="width:18px;height:18px;">
                        <div class="flex-grow-1">
                            <div class="fw-600"><?php echo $fullName; ?></div>
                            <div class="text-muted small"><?php echo $stu['student_no']; ?> &nbsp;|&nbsp; Yr <?php echo $stu['year_level']; ?> &nbsp;|&nbsp; <?php echo $prog; ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary px-5" style="border-radius:0.875rem;font-weight:700;" id="submitBtn">
                <i class="fas fa-users-cog me-2"></i>Enroll Selected Students
            </button>
        </form>
    </div>
</div>

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
