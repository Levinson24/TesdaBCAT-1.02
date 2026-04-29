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
           subj.subject_id, subj.subject_name, subj.units,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id AND status != 'dropped') AS enrolled_count,
           cs.max_students
    FROM class_sections cs
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects subj ON cur.subject_id = subj.subject_id
    WHERE cs.status = 'active'
    ORDER BY cs.school_year DESC, cs.semester, subj.subject_id
");
$sections = $sectionsStmt->fetch_all(MYSQLI_ASSOC);

$successCount = 0;
$skipCount    = 0;
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errors[0]]);
            exit;
        }
    } else {
        $sectionId   = (int)($_POST['section_id'] ?? 0);
        $studentIds  = $_POST['student_ids'] ?? [];
        $enrollDate  = date('Y-m-d');

        if (!$sectionId || empty($studentIds)) {
            $errors[] = 'Please select a section and at least one student.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $errors[0]]);
                exit;
            }
        } else {
            // Verify section exists and is active
            $secCheck = $conn->prepare("SELECT section_id, max_students FROM class_sections WHERE section_id = ? AND status = 'active'");
            $secCheck->bind_param("i", $sectionId);
            $secCheck->execute();
            $secData = $secCheck->get_result()->fetch_assoc();
            $secCheck->close();

            if (!$secData) {
                $errors[] = 'Selected section is not available.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $errors[0]]);
                    exit;
                }
            } else {
                $insertStmt = $conn->prepare(
                    "INSERT IGNORE INTO enrollments (student_id, section_id, enrollment_date, status)
                    VALUES (?, ?, ?, 'enrolled')"
                );

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

                $msg = '';
                if ($successCount > 0) {
                    $msg = "{$successCount} student(s) enrolled successfully." . ($skipCount ? " {$skipCount} already enrolled (skipped)." : '');
                } elseif ($skipCount > 0) {
                    $msg = "No new enrollments. {$skipCount} student(s) were already enrolled.";
                } else {
                    $msg = 'No students were enrolled.';
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => ($successCount > 0), 'message' => $msg, 'created' => $successCount, 'skipped' => $skipCount]);
                    exit;
                } else {
                    if ($successCount > 0) {
                        redirectWithMessage('bulk_enroll.php', $msg, 'success');
                    } else {
                        $errors[] = $msg;
                    }
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
                    <div class="p-4 bg-light rounded-4 border h-100">
                        <label for="section_id" class="form-label fw-bold small text-uppercase mb-3" style="letter-spacing:0.05em;color:#1e293b;">
                            <i class="fas fa-chalkboard me-1 text-primary"></i> Step 1 — Select Section
                        </label>
                        <select class="form-select border-0 shadow-sm mb-4 p-3 rounded-4" id="section_id" name="section_id" required onchange="loadSectionInfo(this)">
                            <option value="">— Choose a class section —</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo $sec['section_id']; ?>"
                                        data-enrolled="<?php echo $sec['enrolled_count']; ?>"
                                        data-max="<?php echo $sec['max_students']; ?>"
                                        <?php echo (isset($_POST['section_id']) && $_POST['section_id'] == $sec['section_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars("{$sec['subject_id']} — {$sec['section_name']} ({$sec['semester']} {$sec['school_year']}) [{$sec['enrolled_count']}/{$sec['max_students']}]"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="sectionInfo" class="alert alert-info border-0 shadow-sm d-none mb-0 rounded-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-2 fa-lg"></i>
                                <span id="sectionInfoText" class="small"></span>
                            </div>
                        </div>

                        <div class="mt-5 d-none d-lg-block text-center p-4">
                            <i class="fas fa-user-plus fa-5x text-primary opacity-10 mb-3"></i>
                            <p class="text-muted small">Select a target section first, then choose the students you wish to enroll collectively. The system will skip any duplicates automatically.</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Student Sele                <div class="col-lg-7">
                    <div class="p-4 bg-white rounded-4 border h-100 shadow-sm">
                        <label class="form-label fw-bold small text-uppercase mb-3" style="letter-spacing:0.05em;color:#1e293b;">
                            <i class="fas fa-user-check me-1 text-primary"></i> Step 2 — Select Students
                        </label>
                        
                        <div class="premium-input-group mb-4">
                            <div class="input-wrapper">
                                <input type="text" class="form-control" id="studentSearch" placeholder="Search by name, ID, or program..." oninput="filterStudents(this.value)">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                            <div class="btn-group rounded-pill overflow-hidden shadow-sm scale-hover">
                                <button type="button" class="btn btn-sm btn-light border-end" onclick="selectAll(true)">
                                    <i class="fas fa-check-square me-1 text-primary"></i>Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-light" onclick="selectAll(false)">
                                    <i class="fas fa-minus-square me-1 text-danger"></i>Clear
                                </button>
                            </div>
                            <span class="badge gradient-navy rounded-pill px-3 py-2 shadow-sm" id="selectedCount">0 selected</span>
                        </div>

                        <div style="max-height:450px; overflow-y:auto; border:1px solid #f1f5f9; border-radius:12px; padding:0.5rem; background: #fdfdfd;" id="studentList" class="custom-scrollbar shadow-inner">-scrollbar">
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

            <div class="mt-5 pt-4 border-top text-center text-lg-start">
                <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-lg scale-hover" id="submitBtn">
                    <i class="fas fa-user-plus me-2 text-warning fa-lg"></i> ENROLL SELECTED STUDENTS
                </button>
                <button type="reset" class="btn btn-link text-muted ms-lg-3 mt-3 mt-lg-0 text-decoration-none small fw-bold" onclick="setTimeout(updateCount, 50)">
                    <i class="fas fa-undo me-1"></i> Reset Selection
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="bulkEnrollConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow-lg border-0">
                <div class="modal-header gradient-navy text-white rounded-top-4">
                    <h5 class="modal-title">Confirm Bulk Enrollment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p id="confirmText" class="mb-3"></p>
                    <div id="confirmDetails" class="small text-muted"></div>
                </div>
                <div class="modal-footer border-top-0 p-3 rounded-bottom-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmEnrollBtn" class="btn btn-primary">Confirm and Enroll</button>
                </div>
            </div>
        </div>
    </div>

    <!-- In-page overlay for submission feedback -->
    <div id="bulkEnrollOverlay" style="display:none; position:fixed; inset:0; z-index:2000; backdrop-filter: blur(4px); background: rgba(0,0,0,0.25); align-items:center; justify-content:center;">
            <div style="background: white; padding:2rem; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); text-align:center; min-width:320px;">
                    <div class="spinner-border text-primary mb-3" role="status"><span class="visually-hidden">Loading...</span></div>
                    <div id="bulkEnrollOverlayText" style="font-weight:600;">Processing enrollment...</div>
            </div>
    </div>

<style>
.scale-hover { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
.scale-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,56,168,0.15) !important; }

.student-item:has(.student-cb:checked) {
    background: #f0f7ff !important;
    border-color: #0038A8 !important;
    transform: translateX(5px);
}
.shadow-inner { box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05); }
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

// AJAX submit with validation and overlay
// Show confirmation modal first, then perform AJAX enrollment on confirm
document.getElementById('bulkEnrollForm').addEventListener('submit', function(e){
    e.preventDefault();
    const form = this;
    const sectionEl = document.getElementById('section_id');
    const sectionId = parseInt(sectionEl.value || 0, 10);
    const selected = Array.from(document.querySelectorAll('.student-cb:checked')).map(i => i.value);
    if (!sectionId) { alert('Please select a section.'); return; }
    if (selected.length === 0) { alert('Please select at least one student.'); return; }

    // build confirmation text
    const opt = sectionEl.selectedOptions[0];
    let seatsInfo = '';
    if (opt && opt.dataset) {
        const max = parseInt(opt.dataset.max || '0', 10);
        const enrolled = parseInt(opt.dataset.enrolled || '0', 10);
        if (max > 0) seatsInfo = `${enrolled}/${max} occupied`;
    }

    const confirmText = document.getElementById('confirmText');
    const confirmDetails = document.getElementById('confirmDetails');
    confirmText.textContent = `You are about to enroll ${selected.length} student(s) into the selected section.`;
    confirmDetails.textContent = seatsInfo ? `Section occupancy: ${seatsInfo}` : '';

    // store form reference on confirm button for later
    const confirmBtn = document.getElementById('confirmEnrollBtn');
    confirmBtn._bulkEnrollForm = form;
    confirmBtn._bulkEnrollSelected = selected;

    // show bootstrap modal
    const bsModal = new bootstrap.Modal(document.getElementById('bulkEnrollConfirmModal'), { backdrop: 'static', keyboard: false });
    bsModal.show();
});

// perform the actual enrollment when user confirms
document.getElementById('confirmEnrollBtn').addEventListener('click', function(){
    const btn = this;
    const form = btn._bulkEnrollForm;
    if (!form) return;
    btn.disabled = true;

    // show overlay
    const overlay = document.getElementById('bulkEnrollOverlay');
    const overlayText = document.getElementById('bulkEnrollOverlayText');
    overlayText.textContent = `Enrolling...`;
    overlay.style.display = 'flex';

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;

    const formData = new FormData(form);
    fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            overlayText.textContent = data.message || (data.success ? 'Enrollment completed.' : 'An error occurred.');
            setTimeout(()=>{
                overlay.style.display = 'none';
                if (data.success) location.reload(); else { submitBtn.disabled = false; btn.disabled = false; }
            }, 1000);
        })
        .catch(err => {
            console.error(err);
            overlayText.textContent = 'Connection error. Please try again.';
            setTimeout(()=>{ overlay.style.display='none'; submitBtn.disabled=false; btn.disabled=false; }, 1200);
        });
    // hide the bootstrap confirmation modal if present
    try { const m = bootstrap.Modal.getInstance(document.getElementById('bulkEnrollConfirmModal')); if (m) m.hide(); } catch(e){}
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
