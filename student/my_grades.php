<?php
/**
 * Student - My Grades
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'My Grades';
require_once '../includes/header.php';

requireRole('student');

$conn = getDBConnection();
$userId = getCurrentUserId();

// Get student profile
$stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo showError('Student profile not found.');
    require_once '../includes/footer.php';
    exit();
}

$studentId = $student['student_id'];

// Get filter parameters
$filterYear = $_GET['year'] ?? '';
$filterSemester = $_GET['semester'] ?? '';

// Build query with filters
$query = "
    SELECT 
        cs.school_year,
        cs.semester,
        cur.class_code,
        s.subject_id,
        s.subject_name,
        s.units,
        g.grade,
        g.remarks,
        g.status,
        CONCAT(i.first_name, ' ', i.last_name) as instructor_name
    FROM enrollments e
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects s ON cur.subject_id = s.subject_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE e.student_id = ?
";

$params = [$studentId];
$types = "i";

if (!empty($filterYear)) {
    $query .= " AND cs.school_year = ?";
    $params[] = $filterYear;
    $types .= "s";
}

if (!empty($filterSemester)) {
    $query .= " AND cs.semester = ?";
    $params[] = $filterSemester;
    $types .= "s";
}

$query .= " ORDER BY cs.school_year DESC, cs.semester DESC, s.subject_id";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$grades = $stmt->get_result();

// Get unique school years for filter
$years = $conn->query("
    SELECT DISTINCT school_year 
    FROM class_sections cs
    JOIN enrollments e ON cs.section_id = e.section_id
    WHERE e.student_id = $studentId
    ORDER BY school_year DESC
");

// Calculate statistics
$gpa = calculateGPA($studentId);

$totalUnitsQuery = $conn->query("
    SELECT SUM(s.units) as total 
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects s ON cur.subject_id = s.subject_id
    WHERE e.student_id = $studentId AND g.remarks = 'Passed' AND g.status = 'approved'
");
$totalUnits = $totalUnitsQuery ? (float)($totalUnitsQuery->fetch_assoc()['total'] ?? 0) : 0;
?>

<style>
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card-v2 {
        background: #fff;
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        transition: transform 0.2s;
    }
    .stat-card-v2:hover {
        transform: translateY(-5px);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }
    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        margin-bottom: 0.25rem;
    }
    .stat-label {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        color: #64748b;
    }
    .premium-table-card {
        border: none;
        border-radius: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        overflow: hidden;
        background: #fff;
    }
    .premium-header {
        background: #002366;
        color: white;
        padding: 1.5rem 2rem;
        border: none;
    }
    .filter-wrapper {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 1rem;
        margin-bottom: 1rem;
    }
    @media (max-width: 768px) {
        .premium-header { padding: 1.25rem 1.5rem; }
        .premium-header .row { gap: 1rem; }
        .stat-value { font-size: 1.5rem; }
    }
</style>

<div class="stats-container d-none d-sm-grid">
    <div class="stat-card-v2">
        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-value text-primary"><?php echo $gpa > 0 ? number_format($gpa, 2) : '0.00'; ?></div>
        <div class="stat-label">Current GWA</div>
    </div>
    <div class="stat-card-v2">
        <div class="stat-icon bg-success bg-opacity-10 text-success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value text-success">
            <?php
            echo $conn->query("SELECT COUNT(*) as total FROM grades WHERE student_id = $studentId AND remarks = 'Passed' AND status = 'approved'")->fetch_assoc()['total'];
            ?>
        </div>
        <div class="stat-label">Courses Passed</div>
    </div>
    <div class="stat-card-v2">
        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-value text-danger">
            <?php
            echo $conn->query("SELECT COUNT(*) as total FROM grades WHERE student_id = $studentId AND remarks = 'Failed' AND status = 'approved'")->fetch_assoc()['total'];
            ?>
        </div>
        <div class="stat-label">Courses Failed</div>
    </div>
    <div class="stat-card-v2">
        <div class="stat-icon bg-info bg-opacity-10 text-info">
            <i class="fas fa-book-open"></i>
        </div>
        <div class="stat-value text-info"><?php echo $totalUnits; ?></div>
        <div class="stat-label">Units Earned</div>
    </div>
</div>

<!-- Mobile Stats (Prototype Style) -->
<div class="stat-grid-mobile d-grid d-sm-none">
    <div class="card stat-card-mobile p-3">
        <div class="stat-value-mobile"><?php echo $gpa > 0 ? number_format($gpa, 2) : '0.00'; ?></div>
        <div class="stat-label-mobile">GWA</div>
    </div>
    <div class="card stat-card-mobile p-3">
        <div class="stat-value-mobile">
            <?php echo $conn->query("SELECT COUNT(*) as total FROM grades WHERE student_id = $studentId AND remarks = 'Passed' AND status = 'approved'")->fetch_assoc()['total']; ?>
        </div>
        <div class="stat-label-mobile">Passed</div>
    </div>
    <div class="card stat-card-mobile p-3">
        <div class="stat-value-mobile">
            <?php echo $conn->query("SELECT COUNT(*) as total FROM grades WHERE student_id = $studentId AND remarks = 'Failed' AND status = 'approved'")->fetch_assoc()['total']; ?>
        </div>
        <div class="stat-label-mobile">Failed</div>
    </div>
    <div class="card stat-card-mobile p-3">
        <div class="stat-value-mobile"><?php echo $totalUnits; ?></div>
        <div class="stat-label-mobile">Units</div>
    </div>
</div>

<div class="card premium-table-card">
    <div class="card-header premium-header">
        <div class="row align-items-center">
            <div class="col-lg-4 mb-3 mb-lg-0">
                <h5 class="mb-0 fw-bold"><i class="fas fa-list-alt me-2"></i>Official Academic Transcript</h5>
            </div>
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                    <form method="GET" class="d-flex gap-2 w-100 w-sm-auto mb-2 mb-sm-0">
                        <select name="year" class="form-select form-select-sm rounded-pill border-0 shadow-sm px-3 flex-grow-1" style="min-width: 100px;">
                            <option value="">Year</option>
                            <?php 
                            $years->data_seek(0);
                            while ($year = $years->fetch_assoc()): ?>
                                <option value="<?php echo $year['school_year']; ?>" <?php echo $filterYear === $year['school_year'] ? 'selected' : ''; ?>>
                                    <?php echo $year['school_year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <select name="semester" class="form-select form-select-sm rounded-pill border-0 shadow-sm px-3 flex-grow-1" style="min-width: 100px;">
                            <option value="">Sem</option>
                            <option value="1st" <?php echo $filterSemester === '1st' ? 'selected' : ''; ?>>1st</option>
                            <option value="2nd" <?php echo $filterSemester === '2nd' ? 'selected' : ''; ?>>2nd</option>
                            <option value="Summer" <?php echo $filterSemester === 'Summer' ? 'selected' : ''; ?>>Sum</option>
                        </select>
                        <button type="submit" class="btn btn-light btn-sm rounded-circle" style="width: 32px; height: 32px; flex-shrink: 0;">
                            <i class="fas fa-search text-primary"></i>
                        </button>
                    </form>
                    <a href="grade_report_pdf.php" target="_blank" class="btn btn-success btn-sm btn-mobile-full rounded-pill px-3 shadow-sm fw-bold">
                        <i class="fas fa-file-pdf me-1"></i> REPORT PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <!-- Desktop Table (Hidden on Mobile) -->
        <div class="table-responsive d-none d-sm-block">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Cycle / Class</th>
                        <th>Subject Details</th>
                        <th class="text-center">Units</th>
                        <!-- Removed Midterm/Final Assessment -->
                        <th class="text-center">Final Grade</th>
                        <th>Status</th>
                        <th class="pe-4">Registry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($grades->num_rows > 0): ?>
                        <?php 
                        $grades->data_seek(0);
                        while ($grade = $grades->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($grade['school_year']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($grade['semester']); ?></div>
                                <div class="badge bg-secondary-subtle text-secondary x-small mt-1"><?php echo htmlspecialchars($grade['class_code'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-primary"><?php echo htmlspecialchars($grade['subject_id']); ?></div>
                                <div class="text-muted small text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($grade['subject_name']); ?></div>
                                <div class="text-muted x-small italic mt-1">Instr: <?php echo htmlspecialchars($grade['instructor_name']); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold"><?php echo $grade['units']; ?></span>
                            </td>
                            <!-- Removed Midterm/Final Assessment -->
                            <td class="text-center">
                                <?php if ($grade['grade'] !== null): ?>
                                    <div class="badge bg-primary fs-6 px-3 rounded-pill"><?php echo number_format($grade['grade'], 2); ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $remarkClass = 'secondary';
                                switch ($grade['remarks']) {
                                    case 'Passed': $remarkClass = 'success'; break;
                                    case 'Failed': $remarkClass = 'danger'; break;
                                    case 'INC': $remarkClass = 'warning'; break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $remarkClass; ?> bg-opacity-10 text-<?php echo $remarkClass; ?> px-3 border border-<?php echo $remarkClass; ?> border-opacity-25 rounded-pill">
                                    <?php echo htmlspecialchars($grade['remarks'] ?? 'Enrolled'); ?>
                                </span>
                            </td>
                            <td class="pe-4">
                                <?php
                                $statusClass = 'secondary'; $statusIcon = 'fa-clock';
                                switch ($grade['status']) {
                                    case 'approved': $statusClass = 'success'; $statusIcon = 'fa-check-circle'; break;
                                    case 'submitted': $statusClass = 'info'; $statusIcon = 'fa-spinner'; break;
                                    case 'pending': $statusClass = 'warning'; $statusIcon = 'fa-user-clock'; break;
                                    case 'rejected': $statusClass = 'danger'; $statusIcon = 'fa-times-circle'; break;
                                }
                                ?>
                                <span class="text-<?php echo $statusClass; ?> small fw-bold">
                                    <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                    <?php echo ucfirst($grade['status'] ?? 'enrolled'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-folder-open fa-3x text-light mb-3"></i>
                                <p class="text-muted mb-0">No academic records found matching your selection.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile-First Card View (Hidden on Desktop) -->
        <div class="d-block d-sm-none p-3">
            <?php if ($grades->num_rows > 0): ?>
                <?php 
                $grades->data_seek(0);
                while ($grade = $grades->fetch_assoc()): 
                    $remarkClass = 'secondary';
                    switch ($grade['remarks']) {
                        case 'Passed': $remarkClass = 'success'; break;
                        case 'Failed': $remarkClass = 'danger'; break;
                        case 'INC': $remarkClass = 'warning'; break;
                    }
                ?>
                    <div class="card subject-card-mobile p-3 mb-3 shadow-none border">
                        <div class="subject-header-mobile">
                            <div class="subject-name-mobile"><?php echo htmlspecialchars($grade['subject_name']); ?></div>
                            <div class="subject-grade-mobile"><?php echo $grade['grade'] !== null ? number_format($grade['grade'], 2) : '--'; ?></div>
                        </div>
                        <div class="subject-info-mobile">
                            Code: <?php echo htmlspecialchars($grade['subject_id']); ?> | Units: <?php echo $grade['units']; ?>
                        </div>
                        <div class="subject-footer-mobile">
                             <span class="remark <?php echo strtolower($grade['remarks'] ?? 'enrolled'); ?>" 
                                   style="color: var(--bs-<?php echo $remarkClass; ?>); font-weight: 800; font-size: 12px; text-transform: uppercase;">
                                 <?php echo htmlspecialchars($grade['remarks'] ?? 'Enrolled'); ?>
                             </span>
                            <div class="small text-muted">
                                <i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($grade['school_year']); ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-2x text-light mb-2"></i>
                    <p class="text-muted mb-0">No academic records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
