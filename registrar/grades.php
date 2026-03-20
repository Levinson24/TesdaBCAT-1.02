<?php
/**
 * Grade Records — Registrar View
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff']);
$conn = getDBConnection();
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$userProfile = getUserProfile($userId, $userRole);
$deptId = $userProfile['dept_id'] ?? 0;
$isStaff = ($userRole === 'registrar_staff');

// ─── Handle Compliance Grade Processing ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'compliance') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('grades.php', 'Invalid security token. Please try again.', 'danger');
    }
    
    // Guard: Only Head Registrar can process compliance (auto-approves)
    if (getCurrentUserRole() !== 'registrar') {
        redirectWithMessage('grades.php', 'Unauthorized: Only the Head Registrar can process compliance records.', 'danger');
    }
    $gradeId = intval($_POST['grade_id']);
    $midterm = floatval($_POST['midterm']);
    $final = floatval($_POST['final']);

    // Get weights from settings
    $mWeight = floatval(getSetting('midterm_weight', 0.5));
    $fWeight = floatval(getSetting('final_weight', 0.5));
    $passingGrade = floatval(getSetting('passing_grade', 3.00));

    // Calculate final grade
    $finalGrade = ($midterm * $mWeight) + ($final * $fWeight);
    $remarks = getGradeRemark($finalGrade, $passingGrade);

    $stmt = $conn->prepare("
        UPDATE grades 
        SET midterm = ?, final = ?, grade = ?, remarks = ?, 
            status = 'approved', approved_by = ?, approved_at = NOW()
        WHERE grade_id = ?
    ");
    $stmt->bind_param("dddsii", $midterm, $final, $finalGrade, $remarks, $userId, $gradeId);

    if ($stmt->execute()) {
        redirectWithMessage('grades.php', 'Student compliance processed successfully. Record updated.', 'success');
    }
    else {
        redirectWithMessage('grades.php', 'Failed to process compliance. Please try again.', 'danger');
    }
    $stmt->close();
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_grade') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('grades.php', 'Invalid security token. Please try again.', 'danger');
    }
    $gradeId = intval($_POST['grade_id']);
    $midterm = floatval($_POST['midterm']);
    $final = floatval($_POST['final']);
    
    $mWeight = floatval(getSetting('midterm_weight', 0.5));
    $fWeight = floatval(getSetting('final_weight', 0.5));
    $passingGrade = floatval(getSetting('passing_grade', 3.00));
    
    $finalGrade = ($midterm * $mWeight) + ($final * $fWeight);
    $remarks = getGradeRemark($finalGrade, $passingGrade);
    $status = sanitizeInput($_POST['status'] ?? 'approved');
    
    // Guard: Staff cannot set status to approved or change existing status to anything else
    if (getCurrentUserRole() === 'registrar_staff' && $status === 'approved') {
        redirectWithMessage('grades.php', 'Unauthorized: Registrar Staff cannot approve grades.', 'danger');
    }

    $stmt = $conn->prepare("UPDATE grades SET midterm = ?, final = ?, grade = ?, remarks = ?, status = ? WHERE grade_id = ?");
    $stmt->bind_param("dddssi", $midterm, $final, $finalGrade, $remarks, $status, $gradeId);
    if ($stmt->execute()) {
        redirectWithMessage('grades.php', 'Grade updated successfully.', 'success');
    } else {
        redirectWithMessage('grades.php', 'Failed to update grade.', 'danger');
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_grade') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('grades.php', 'Invalid security token. Please try again.', 'danger');
    }
    
    // Guard: Staff cannot delete records
    if (getCurrentUserRole() === 'registrar_staff') {
        redirectWithMessage('grades.php', 'Unauthorized: Registrar Staff cannot delete grade records.', 'danger');
    }
    $gradeId = intval($_POST['grade_id']);
    $stmt = $conn->prepare("DELETE FROM grades WHERE grade_id = ?");
    $stmt->bind_param("i", $gradeId);
    if ($stmt->execute()) {
        redirectWithMessage('grades.php', 'Grade record deleted successfully.', 'success');
    } else {
        redirectWithMessage('grades.php', 'Failed to delete grade record.', 'danger');
    }
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'all';
$filterYear = $_GET['year'] ?? '';
$filterSem = $_GET['semester'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');
$activeTab = $_GET['tab'] ?? 'records';

// ─── CSV Export ───────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvWhere = $isStaff ? " WHERE s.dept_id = $deptId" : "";
    $csvResult = $conn->query("
        SELECT s.student_no, CONCAT(IFNULL(s.last_name,''),', ',IFNULL(s.first_name,'')) AS student_name,
               c.course_code, c.course_name, cs.section_name, cs.semester, cs.school_year,
               CONCAT(IFNULL(i.first_name,''),' ',IFNULL(i.last_name,'')) AS instructor_name,
               g.midterm, g.final, g.grade, g.remarks, g.status, g.submitted_at
        FROM grades g
        JOIN students s      ON g.student_id     = s.student_id
        JOIN enrollments e   ON g.enrollment_id  = e.enrollment_id
        JOIN class_sections cs ON e.section_id   = cs.section_id
        JOIN courses c       ON cs.course_id     = c.course_id
        JOIN instructors i   ON cs.instructor_id = i.instructor_id
        $csvWhere
        ORDER BY cs.school_year DESC, cs.semester, s.last_name
    ");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="grade_records_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student No', 'Student Name', 'Course Code', 'Subject Description', 'Section',
        'Semester', 'School Year', 'Instructor', 'Midterm', 'Final', 'Grade',
        'Remarks', 'Status', 'Submitted At']);
    while ($row = $csvResult->fetch_assoc()) {
        fputcsv($out, [
            $row['student_no'], $row['student_name'], $row['course_code'], $row['course_name'],
            $row['section_name'], $row['semester'], $row['school_year'], $row['instructor_name'],
            number_format($row['midterm'] ?? 0, 2), number_format($row['final'] ?? 0, 2),
            $row['grade'] !== null ? number_format($row['grade'], 2) : '—', $row['remarks'],
            ucfirst($row['status']), $row['submitted_at']
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Grade Records';
require_once '../includes/header.php';

// ─── Build WHERE clause ───────────────────────────────────────────────────────
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($isStaff) {
    $where .= " AND s.dept_id = ?";
    $params[] = $deptId;
    $types .= "i";
}
if ($filterStatus !== 'all') {
    $where .= " AND g.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}
if (!empty($filterYear)) {
    $where .= " AND cs.school_year = ?";
    $params[] = $filterYear;
    $types .= "s";
}
if (!empty($filterSem)) {
    $where .= " AND cs.semester = ?";
    $params[] = $filterSem;
    $types .= "s";
}
if (!empty($filterSearch)) {
    $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_no LIKE ? OR c.course_code LIKE ?)";
    $like = "%$filterSearch%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= "ssss";
}

$baseSql = "
    SELECT g.grade_id, g.midterm, g.final, g.grade, g.remarks, g.status, g.submitted_at,
           s.student_id, s.student_no, CONCAT(IFNULL(s.last_name,''),', ',IFNULL(s.first_name,'')) AS student_name,
           c.course_code, c.course_name, cs.section_name, cs.semester, cs.school_year,
           CONCAT(IFNULL(i.first_name,''),' ',IFNULL(i.last_name,'')) AS instructor_name
    FROM grades g
    JOIN students s      ON g.student_id     = s.student_id
    JOIN enrollments e   ON g.enrollment_id  = e.enrollment_id
    JOIN class_sections cs ON e.section_id   = cs.section_id
    JOIN courses c       ON cs.course_id     = c.course_id
    JOIN instructors i   ON cs.instructor_id = i.instructor_id
    $where ORDER BY g.submitted_at DESC
";
if (!empty($params)) {
    $stmt = $conn->prepare($baseSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $grades = $stmt->get_result();
    $stmt->close();
}
else {
    $grades = $conn->query($baseSql);
}

// ─── Stats ───────────────────────────────────────────────────────────────────
$totalGrades = $conn->query("SELECT COUNT(*) AS c FROM grades")->fetch_assoc()['c'];
$approvedCount = $conn->query("SELECT COUNT(*) AS c FROM grades WHERE status='approved'")->fetch_assoc()['c'];
$passedCount = $conn->query("SELECT COUNT(*) AS c FROM grades WHERE remarks='Passed' AND status='approved'")->fetch_assoc()['c'];
$failedCount = $conn->query("SELECT COUNT(*) AS c FROM grades WHERE remarks='Failed'  AND status='approved'")->fetch_assoc()['c'];
$incCount = $conn->query("SELECT COUNT(*) AS c FROM grades WHERE remarks='INC'     AND status='approved'")->fetch_assoc()['c'];
$droppedCount = $conn->query("SELECT COUNT(*) AS c FROM grades WHERE remarks='Dropped' AND status='approved'")->fetch_assoc()['c'];
$avgRow = $conn->query("SELECT AVG(grade) AS avg FROM grades WHERE status='approved'")->fetch_assoc();
$avgGrade = $avgRow['avg'] ? number_format($avgRow['avg'], 2) : '—';

// ─── Summary data ─────────────────────────────────────────────────────────────
$courseSummary = $conn->query("
    SELECT c.course_code, c.course_name, cs.school_year, cs.semester, cs.section_name,
           CONCAT(i.first_name,' ',i.last_name) AS instructor_name,
           COUNT(g.grade_id) AS total_students,
           SUM(g.remarks='Passed') AS passed, SUM(g.remarks='Failed') AS failed,
           ROUND(AVG(g.grade),2) AS avg_grade,
           ROUND((SUM(g.remarks='Passed')/COUNT(g.grade_id))*100,1) AS pass_rate
    FROM grades g
    JOIN enrollments e   ON g.enrollment_id  = e.enrollment_id
    JOIN class_sections cs ON e.section_id   = cs.section_id
    JOIN courses c       ON cs.course_id     = c.course_id
    JOIN instructors i   ON cs.instructor_id = i.instructor_id
    WHERE g.status IN ('approved','submitted')
    GROUP BY c.course_code, cs.section_name, cs.semester, cs.school_year
    ORDER BY cs.school_year DESC, cs.semester, c.course_code
");
$instructorSummary = $conn->query("
    SELECT CONCAT(i.first_name,' ',i.last_name) AS instructor_name,
           COUNT(g.grade_id) AS total_graded,
           SUM(g.remarks='Passed') AS passed, SUM(g.remarks='Failed') AS failed,
           ROUND(AVG(g.grade),2) AS avg_grade,
           ROUND((SUM(g.remarks='Passed')/COUNT(g.grade_id))*100,1) AS pass_rate
    FROM grades g
    JOIN enrollments e   ON g.enrollment_id  = e.enrollment_id
    JOIN class_sections cs ON e.section_id   = cs.section_id
    JOIN instructors i   ON cs.instructor_id = i.instructor_id
    WHERE g.status IN ('approved','submitted')
    GROUP BY i.instructor_id ORDER BY total_graded DESC
");
$years = $conn->query("SELECT DISTINCT school_year FROM class_sections ORDER BY school_year DESC");

function buildHref($extra = [])
{
    $p = array_merge([
        'tab' => $_GET['tab'] ?? 'records', 'status' => $_GET['status'] ?? 'all',
        'year' => $_GET['year'] ?? '', 'semester' => $_GET['semester'] ?? '', 'search' => $_GET['search'] ?? ''
    ], $extra);
    return 'grades.php?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}
?>

<!-- ══════════════════════════════════════════ PAGE-LEVEL STYLES -->
<style>
/* ── Navy Blue Theme Overrides for this page ───────────── */
/* Removed custom dark header and tabs - using standard Bootstrap nav-tabs on white */

/* ── Stat Cards ─────────────────────────────────────────── */
.stat-mini {
    background: #fff;
    border-radius: 1rem;
    padding: 1.1rem 1.4rem;
    border-left: 4px solid;
    box-shadow: 0 2px 12px rgba(0, 56, 168, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
}
.stat-mini .stat-icon {
    width: 46px; height: 46px;
    border-radius: 0.75rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
}
.stat-mini h4 { margin: 0; font-size: 1.6rem; font-weight: 800; line-height: 1; }
.stat-mini small { color: #64748b; font-size: 0.8rem; font-weight: 500; }

/* ── Table ──────────────────────────────────────────────── */
.grade-table thead th {
    background: #0038A8;
    color: #fff;
    font-weight: 600;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: none;
    padding: 0.85rem 0.75rem;
}
.grade-table tbody tr:hover { background: #f0f6ff; }
.grade-table tbody td { vertical-align: middle; font-size: 0.875rem; padding: 0.7rem 0.75rem; }
.grade-table tbody tr:nth-child(even) { background: #fafbff; }

/* ── Progress bar wrapper ───────────────────────────────── */
.pass-bar { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.pass-bar .progress { flex: 1; height: 7px; border-radius: 4px; }

/* ── Premium Action Buttons ───────────────────────── */
.btn-premium-edit, .btn-premium-print, .btn-premium-delete {
    width: 32px; height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    padding: 0;
    text-decoration: none !important;
}
.btn-premium-print { background-color: #f0f9ff; color: #0369a1 !important; }
.btn-premium-print:hover { background-color: #0369a1; color: #fff !important; box-shadow: 0 4px 6px rgba(3,105,161,0.2); }
.btn-premium-edit { background-color: #eff6ff; color: #2563eb !important; }
.btn-premium-edit:hover { background-color: #2563eb; color: #fff !important; box-shadow: 0 4px 6px rgba(37,99,235,0.2); }
.btn-premium-delete { background-color: #fef2f2; color: #ef4444 !important; }
.btn-premium-delete:hover { background-color: #ef4444; color: #fff !important; box-shadow: 0 4px 6px rgba(239,68,68,0.2); }

/* ══════════════ PRINT STYLES ══════════════════════════════ */
@media print {
    .sidebar, .top-navbar, .no-print, .alert,
    .grade-tabs, .filter-card, .btn, form, nav { display: none !important; }

    body { background: #fff !important; font-size: 11pt; font-family: Arial, sans-serif; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .content-area { padding: 0 !important; }

    .print-header {
        display: block !important;
        text-align: center;
        border-bottom: 2px solid #0038A8;
        padding-bottom: 12px;
        margin-bottom: 18px;
    }
    .print-header h2 { color: #0038A8; font-size: 16pt; margin: 4px 0; }
    .print-header p  { margin: 2px 0; font-size: 9pt; color: #555; }


    .card { box-shadow: none !important; border: none !important; border-radius: 0 !important; }
    .card-body { padding: 0 !important; }

    .stat-row { display: none !important; }

    .grade-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .grade-table thead th {
        background: #0038A8 !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        font-size: 8.5pt;
        padding: 8px 6px;
        text-align: left;
        border: 1px solid #0038A8;
    }
    .grade-table tbody td {
        font-size: 8.5pt;
        padding: 8px 6px;
        border: 1px solid #dee2e6;
        vertical-align: middle !important;
        line-height: 1.25;
        min-height: 32px;
    }
    .grade-table tbody tr:nth-child(even) td { background: #f9f9f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    /* Ensure numeric columns and badges stay on one line */
    .grade-table td.text-center { white-space: nowrap; }
    
    .badge { 
        display: inline-block !important;
        border: 1px solid #ccc !important; 
        background: transparent !important; 
        color: #000 !important; 
        padding: 2px 6px !important; 
        font-size: 8pt !important;
        font-weight: 600 !important;
        text-transform: uppercase;
        border-radius: 4px !important;
        white-space: nowrap;
    }
    
    /* Fix the Grade strong tag size in print to avoid row bloat */
    .grade-table td strong { font-size: 9.5pt !important; }
    
    .pass-bar .progress, .pass-bar small { display: none; }
    .pass-bar::after { content: attr(data-rate); font-size: 8pt; }

    .print-footer {
        display: block !important;
        margin-top: 24px;
        font-size: 8pt;
        color: #777;
        text-align: center;
        border-top: 1px solid #ccc;
        padding-top: 8px;
    }
}
/* Hidden by default, visible on print */
.print-header, .print-footer { display: none; }
</style>

<!-- ══════════════════════════════════════════ PRINT HEADER (hidden in browser) -->
<div class="print-header">
    <h2>TESDA-BCAT Grade Management System</h2>
    <h3 style="font-size:13pt;margin:4px 0;">Grade Records Report</h3>
    <p>Printed on: <?php echo date('F d, Y h:i A'); ?> &nbsp;|&nbsp; Prepared by: Registrar's Office</p>
</div>

<!-- ══════════════════════════════════════════ STAT CARDS -->
<div class="responsive-grid mb-4 stat-row">
    <?php
$stats = [
    ['label' => 'Total Records', 'value' => $totalGrades, 'color' => '#0038A8', 'bg' => '#e8f0fb', 'icon' => 'fa-list-alt'],
    ['label' => 'Graded', 'value' => $approvedCount, 'color' => '#198754', 'bg' => '#e6f4ed', 'icon' => 'fa-check-circle'],
    ['label' => 'Passed', 'value' => $passedCount, 'color' => '#0d6efd', 'bg' => '#e7f1ff', 'icon' => 'fa-graduation-cap'],
    ['label' => 'Failed', 'value' => $failedCount, 'color' => '#dc3545', 'bg' => '#fdecea', 'icon' => 'fa-times-circle'],
    ['label' => 'INC', 'value' => $incCount, 'color' => '#f39c12', 'bg' => '#fef5e7', 'icon' => 'fa-exclamation-triangle'],
    ['label' => 'Dropped', 'value' => $droppedCount, 'color' => '#6c757d', 'bg' => '#f8f9fa', 'icon' => 'fa-user-minus'],
    ['label' => 'Avg Grade (GWA)', 'value' => $avgGrade, 'color' => '#e67e22', 'bg' => '#fef5e7', 'icon' => 'fa-chart-bar'],
];
foreach ($stats as $s): ?>
    <div class="stat-mini" style="border-color:<?php echo $s['color']; ?>;">
        <div class="stat-icon" style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['color']; ?>;">
            <i class="fas <?php echo $s['icon']; ?>"></i>
        </div>
        <div>
            <h4 style="color:<?php echo $s['color']; ?>;"><?php echo $s['value']; ?></h4>
            <small><?php echo $s['label']; ?></small>
        </div>
    </div>
    <?php
endforeach; ?>
</div>


<!-- ══════════════════════════════════════════ ACADEMIC FREEDOM NOTICE -->
<div class="alert d-flex align-items-center mb-3 no-print"
     style="background:#e8f0fb;border:1px solid #b8d0ef;color:#0038A8;border-radius:.85rem;">
    <i class="fas fa-shield-alt me-2 fs-5"></i>
    <div>
        <strong>Academic Freedom Policy:</strong>
        Instructor grades are finalized upon submission — no registrar approval required.
    </div>
</div>

<!-- ══════════════════════════════════════════ MAIN CARD -->
<div class="card shadow-sm border-0">
    <!-- Card Header with title and buttons -->
    <div class="card-header gradient-navy p-4 d-flex justify-content-between align-items-center flex-wrap gap-3 rounded-top">
        <div>
            <h5 class="mb-0 text-white fw-bold"><i class="fas fa-folder-open me-2 text-warning"></i> Grade Records Management</h5>
            <p class="text-white opacity-75 mb-0 small mt-1">View, filter, and export student grades and course summaries.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo buildHref(['export' => 'csv']); ?>"
               class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold border-0">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary">
                <i class="fas fa-print me-1"></i> Print
            </button>
        </div>
    </div>

    <!-- Standard Bootstrap Tabs -->
    <ul class="nav nav-tabs px-3 border-bottom-0 no-print" role="tablist">
        <li class="nav-item" role="presentation">
            <a href="<?php echo buildHref(['tab' => 'records']); ?>"
               class="nav-link py-3 px-4 <?php echo $activeTab === 'records' ? 'active' : ''; ?> fw-semibold text-muted">
                <i class="fas fa-table me-2"></i> Grade Records
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="<?php echo buildHref(['tab' => 'summary']); ?>"
               class="nav-link py-3 px-4 <?php echo $activeTab === 'summary' ? 'active' : ''; ?> fw-semibold text-muted">
                <i class="fas fa-chart-pie me-2"></i> Course Summary
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="<?php echo buildHref(['tab' => 'instructor']); ?>"
               class="nav-link py-3 px-4 <?php echo $activeTab === 'instructor' ? 'active' : ''; ?> fw-semibold text-muted">
                <i class="fas fa-chalkboard-teacher me-2"></i> By Instructor
            </a>
        </li>
    </ul>

    <div class="card-body p-4 pt-3 border-top">

    <?php if ($activeTab === 'records'): ?>
    <!-- ══════════ TAB: GRADE RECORDS ════════════════════════════════════════ -->

        <!-- Filters -->
        <div class="filter-card no-print">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="records">
                <div class="col-md-3">
                    <label class="form-label form-label-sm fw-semibold text-muted mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Student name / No / course…"
                           value="<?php echo htmlspecialchars($filterSearch ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold text-muted mb-1">School Year</label>
                    <select name="year" class="form-select form-select-sm">
                        <option value="">All Years</option>
                        <?php $years->data_seek(0);
    while ($yr = $years->fetch_assoc()): ?>
                            <option value="<?php echo $yr['school_year']; ?>"
                                <?php echo $filterYear === $yr['school_year'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($yr['school_year'] ?? ''); ?>
                            </option>
                        <?php
    endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold text-muted mb-1">Semester</label>
                    <select name="semester" class="form-select form-select-sm">
                        <option value="">All Semesters</option>
                        <option value="1st"    <?php echo $filterSem === '1st' ? 'selected' : ''; ?>>1st Semester</option>
                        <option value="2nd"    <?php echo $filterSem === '2nd' ? 'selected' : ''; ?>>2nd Semester</option>
                        <option value="Summer" <?php echo $filterSem === 'Summer' ? 'selected' : ''; ?>>Summer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"      <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Graded</option>
                        <option value="submitted"<?php echo $filterStatus === 'submitted' ? 'selected' : ''; ?>>Submitted (legacy)</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100" style="background:#0038A8;border:none;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="grades.php?tab=records" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Print: active filters summary -->
        <?php if (!empty($filterSearch) || !empty($filterYear) || !empty($filterSem) || $filterStatus !== 'all'): ?>
        <p class="d-none d-print-block small text-muted mb-2">
            Filters:
            <?php if (!empty($filterSearch))
            echo "Search: \"$filterSearch\" | "; ?>
            <?php if (!empty($filterYear))
            echo "Year: $filterYear | "; ?>
            <?php if (!empty($filterSem))
            echo "Semester: $filterSem | "; ?>
            <?php if ($filterStatus !== 'all')
            echo "Status: " . ucfirst($filterStatus); ?>
        </p>
        <?php
    endif; ?>

        <p class="text-muted small mb-2 no-print">
            Showing <strong><?php echo $grades ? $grades->num_rows : 0; ?></strong> record(s)
        </p>

        <div class="table-responsive">
            <table class="table table-bordered table-mobile-card grade-table">
                <thead>
                    <tr>
                        <th class="ps-3 small">#</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Section</th>
                        <th style="width: 100px;">SY / Sem</th>
                        <th>Instructor</th>
                        <th class="text-center">Midterm</th>
                        <th class="text-center">Final</th>
                        <th class="text-center">Grade</th>
                        <th class="text-center">Remarks</th>
                        <th class="text-center">Status</th>
                        <th>Submitted</th>
                        <th class="text-center no-print">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($grades && $grades->num_rows > 0):
        $row = 0; ?>
                        <?php while ($g = $grades->fetch_assoc()):
            $row++; ?>
                        <tr>
                            <td class="text-muted ps-3" data-label="#" style="width:40px;"><?php echo $row; ?></td>
                            <td data-label="Student">
                                <strong><?php echo htmlspecialchars($g['student_name'] ?? ''); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($g['student_no'] ?? ''); ?></small>
                            </td>
                            <td data-label="Course">
                                <strong><?php echo htmlspecialchars($g['course_code'] ?? ''); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($g['course_name'] ?? ''); ?></small>
                            </td>
                            <td data-label="Section"><?php echo htmlspecialchars($g['section_name'] ?? ''); ?></td>
                            <td data-label="SY / Sem"><?php echo htmlspecialchars(($g['semester'] ?? '') . ' ' . ($g['school_year'] ?? '')); ?></td>
                            <td data-label="Instructor"><?php echo htmlspecialchars($g['instructor_name'] ?? ''); ?></td>
                            <td class="text-center fw-bold" data-label="Midterm"><?php echo number_format($g['midterm'] ?? 0, 2); ?></td>
                            <td class="text-center fw-bold" data-label="Final"><?php echo number_format($g['final'] ?? 0, 2); ?></td>
                            <td class="text-center" data-label="Grade">
                                <strong class="<?php
            if ($g['remarks'] === 'Passed')
                echo 'text-success';
            elseif ($g['remarks'] === 'Failed')
                echo 'text-danger';
            elseif ($g['remarks'] === 'INC')
                echo 'text-warning';
            elseif ($g['remarks'] === 'Dropped')
                echo 'text-secondary';
?>" style="font-size:1rem;">
                                    <?php echo $g['grade'] !== null ? number_format($g['grade'], 2) : '—'; ?>
                                </strong>
                            </td>
                            <td class="text-center" data-label="Remarks">
                                <?php
            $rc = 'secondary';
            if ($g['remarks'] === 'Passed')
                $rc = 'success';
            elseif ($g['remarks'] === 'Failed')
                $rc = 'danger';
            elseif ($g['remarks'] === 'INC')
                $rc = 'warning';
            elseif ($g['remarks'] === 'Dropped')
                $rc = 'secondary';
?>
                                <span class="badge bg-<?php echo $rc; ?>"><?php echo htmlspecialchars($g['remarks'] ?? ''); ?></span>
                            </td>
                            <td class="text-center" data-label="Status">
                                <?php
            $sc = ['approved' => 'success', 'submitted' => 'success', 'pending' => 'secondary', 'rejected' => 'danger'];
            $sl = ['approved' => 'Graded', 'submitted' => 'Graded', 'pending' => 'Pending', 'rejected' => 'Rejected'];
            $st = $g['status'];
            echo '<span class="badge bg-' . ($sc[$st] ?? 'secondary') . '">' . ($sl[$st] ?? ucfirst($st)) . '</span>';
?>
                            </td>
                            <td data-label="Submitted"><small><?php echo $g['submitted_at'] ? formatDateTime($g['submitted_at']) : '—'; ?></small></td>

                             <td class="no-print text-center pe-3">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn-premium-edit" 
                                            onclick='openEditGradeModal(<?php echo htmlspecialchars(json_encode($g), ENT_QUOTES, 'UTF-8'); ?>)' 
                                            title="Edit Grade Information">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="print_grade_slip.php?student_id=<?php echo urlencode($g['student_id'] ?? ''); ?>&semester=<?php echo urlencode($g['semester'] ?? ''); ?>&school_year=<?php echo urlencode($g['school_year'] ?? ''); ?>" 
                                       target="_blank" 
                                       class="btn-premium-print" 
                                       title="Print Grade Slip">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this grade record?')">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="delete_grade">
                                        <input type="hidden" name="grade_id" value="<?php echo $g['grade_id']; ?>">
                                        <button type="submit" class="btn-premium-delete" title="Delete Grade">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php
        endwhile; ?>
                    <?php
    else: ?>
                        <tr><td colspan="12" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>No grade records found.
                        </td></tr>
                    <?php
    endif; ?>
                </tbody>
            </table>
        </div>

    <?php
elseif ($activeTab === 'summary'): ?>
    <!-- ══════════ TAB: COURSE SUMMARY ═══════════════════════════════════════ -->

        <div class="table-responsive">
            <table class="table table-bordered table-mobile-card grade-table">
                <thead>
                    <tr>
                        <th class="ps-3">Course</th>
                        <th>Section / Semester</th>
                        <th>Instructor</th>
                        <th class="text-center">Students</th>
                        <th class="text-center">Passed</th>
                        <th class="text-center">Failed</th>
                        <th>Pass Rate</th>
                        <th class="text-center">Avg Grade</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($courseSummary && $courseSummary->num_rows > 0): ?>
                        <?php while ($cs = $courseSummary->fetch_assoc()):
            $pr = floatval($cs['pass_rate']); ?>
                        <tr>
                            <td class="ps-3" data-label="Course">
                                <strong><?php echo htmlspecialchars($cs['course_code'] ?? ''); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($cs['course_name'] ?? ''); ?></small>
                            </td>
                            <td data-label="Section / Semester"><?php echo htmlspecialchars(($cs['section_name'] ?? '') . ' | ' . ($cs['semester'] ?? '') . ' ' . ($cs['school_year'] ?? '')); ?></td>
                            <td data-label="Instructor"><?php echo htmlspecialchars($cs['instructor_name'] ?? ''); ?></td>
                            <td class="text-center fw-bold" data-label="Students"><?php echo $cs['total_students']; ?></td>
                            <td class="text-center text-success fw-bold" data-label="Passed"><?php echo $cs['passed']; ?></td>
                            <td class="text-center text-danger fw-bold" data-label="Failed"><?php echo $cs['failed']; ?></td>
                            <td data-label="Pass Rate">
                                <div class="pass-bar" data-rate="<?php echo $pr; ?>%">
                                    <div class="progress flex-grow-1">
                                        <div class="progress-bar bg-<?php echo $pr >= 75 ? 'success' : ($pr >= 50 ? 'warning' : 'danger'); ?>"
                                             style="width:<?php echo $pr; ?>%"></div>
                                    </div>
                                    <small class="fw-bold" style="min-width:36px;"><?php echo $pr; ?>%</small>
                                </div>
                            </td>
                            <td class="text-center" data-label="Avg Grade">
                                <span class="badge fs-6 bg-<?php echo $cs['avg_grade'] <= 3.00 ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($cs['avg_grade'], 2); ?>
                                </span>
                            </td>
                        </tr>

                        <?php
        endwhile; ?>
                    <?php
    else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>No data available.
                        </td></tr>
                    <?php
    endif; ?>
                </tbody>
            </table>
        </div>

    <?php
elseif ($activeTab === 'instructor'): ?>
    <!-- ══════════ TAB: BY INSTRUCTOR ═════════════════════════════════════════ -->

        <div class="table-responsive">
            <table class="table table-bordered table-mobile-card grade-table">
                <thead>
                    <tr>
                        <th class="ps-3">Instructor</th>
                        <th class="text-center">Total Graded</th>
                        <th class="text-center">Passed</th>
                        <th class="text-center">Failed</th>
                        <th>Pass Rate</th>
                        <th class="text-center">Avg Grade</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($instructorSummary && $instructorSummary->num_rows > 0): ?>
                        <?php while ($ins = $instructorSummary->fetch_assoc()):
            $pr = floatval($ins['pass_rate']); ?>
                        <tr>
                            <td class="ps-3" data-label="Instructor">
                                <i class="fas fa-chalkboard-teacher text-primary me-1"></i>
                                <strong><?php echo htmlspecialchars($ins['instructor_name'] ?? ''); ?></strong>
                            </td>
                            <td class="text-center fw-bold" data-label="Total Graded"><?php echo $ins['total_graded']; ?></td>
                            <td class="text-center text-success fw-bold" data-label="Passed"><?php echo $ins['passed']; ?></td>
                            <td class="text-center text-danger fw-bold" data-label="Failed"><?php echo $ins['failed']; ?></td>
                            <td data-label="Pass Rate">
                                <div class="pass-bar" data-rate="<?php echo $pr; ?>%">
                                    <div class="progress flex-grow-1">
                                        <div class="progress-bar bg-<?php echo $pr >= 75 ? 'success' : ($pr >= 50 ? 'warning' : 'danger'); ?>"
                                             style="width:<?php echo $pr; ?>%"></div>
                                    </div>
                                    <small class="fw-bold" style="min-width:36px;"><?php echo $pr; ?>%</small>
                                </div>
                            </td>
                            <td class="text-center" data-label="Avg Grade">
                                <span class="badge fs-6 bg-<?php echo $ins['avg_grade'] <= 3.00 ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($ins['avg_grade'], 2); ?>
                                </span>
                            </td>
                        </tr>

                        <?php
        endwhile; ?>
                    <?php
    else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>No data available.
                        </td></tr>
                    <?php
    endif; ?>
                </tbody>
            </table>
        </div>
    <?php
endif; ?>

    </div><!-- /card-body -->
</div><!-- /card -->

<!-- ══════════════════════════════════════════ PRINT FOOTER -->
<div class="print-footer">
    TESDA-BCAT Grade Management System &nbsp;|&nbsp; Grade Records Report &nbsp;|&nbsp;
    Generated: <?php echo date('F d, Y h:i A'); ?> &nbsp;|&nbsp; Registrar's Office
</div>

<!-- ══════════════════════════════════════════ COMPLIANCE MODAL -->
<div class="modal fade" id="complianceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="compliance">
            <input type="hidden" name="grade_id" id="comp_grade_id">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-file-signature me-2 text-warning"></i> Process Compliance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 mb-3">
                        <small><i class="fas fa-info-circle"></i> Updating record for: <strong id="comp_student_name"></strong></small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Midterm Grade</label>
                            <input type="number" name="midterm" id="comp_midterm" class="form-control" 
                                   step="0.01" min="1.00" max="5.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Final Grade</label>
                            <input type="number" name="final" id="comp_final" class="form-control" 
                                   step="0.01" min="1.00" max="5.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info rounded-pill px-4 fw-bold">
                        <i class="fas fa-check-circle me-1"></i> Save and Approve
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════ EDIT GRADE MODAL -->
<div class="modal fade" id="editGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="update_grade">
            <input type="hidden" name="grade_id" id="edit_grade_id">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2 text-warning"></i> Edit Grade Record</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3 small">
                        <i class="fas fa-info-circle"></i> Modifying record for: <strong id="edit_student_name"></strong>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Midterm</label>
                            <input type="number" name="midterm" id="edit_midterm" class="form-control" 
                                   step="0.01" min="1.00" max="5.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Final</label>
                            <input type="number" name="final" id="edit_final" class="form-control" 
                                   step="0.01" min="1.00" max="5.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="approved">Graded (Approved)</option>
                            <option value="submitted">Submitted</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openEditGradeModal(data) {
    document.getElementById('edit_grade_id').value = data.grade_id;
    document.getElementById('edit_student_name').innerText = data.student_name;
    document.getElementById('edit_midterm').value = data.midterm || '';
    document.getElementById('edit_final').value = data.final || '';
    document.getElementById('edit_status').value = data.status;
    
    new bootstrap.Modal(document.getElementById('editGradeModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
