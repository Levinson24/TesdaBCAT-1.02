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
        c.class_code,
        c.course_code,
        c.course_name,
        c.units,
        g.midterm,
        g.final,
        g.grade,
        g.remarks,
        g.status,
        CONCAT(i.first_name, ' ', i.last_name) as instructor_name
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE g.student_id = ?
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

$query .= " ORDER BY cs.school_year DESC, cs.semester DESC, c.course_code";

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
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Academic Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="p-3">
                            <h2 class="text-primary mb-0"><?php echo $gpa > 0 ? number_format($gpa, 2) : '0.00'; ?></h2>
                            <small class="text-muted">General Weighted Average (GWA)</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center border-start">
                        <div class="p-3">
                            <?php
$passedCount = $conn->query("
                                SELECT COUNT(*) as total FROM grades 
                                WHERE student_id = $studentId AND remarks = 'Passed' AND status = 'approved'
                            ")->fetch_assoc()['total'];
?>
                            <h2 class="text-success mb-0"><?php echo $passedCount; ?></h2>
                            <small class="text-muted">Courses Passed</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center border-start">
                        <div class="p-3">
                            <?php
$failedCount = $conn->query("
                                SELECT COUNT(*) as total FROM grades 
                                WHERE student_id = $studentId AND remarks = 'Failed' AND status = 'approved'
                            ")->fetch_assoc()['total'];
?>
                            <h2 class="text-danger mb-0"><?php echo $failedCount; ?></h2>
                            <small class="text-muted">Courses Failed</small>
                        </div>
                    </div>
                    <div class="col-md-3 text-center border-start">
                        <div class="p-3">
                            <?php
$totalUnitsResult = $conn->query("
                                SELECT SUM(c.units) as total
                                FROM grades g
                                JOIN enrollments e ON g.enrollment_id = e.enrollment_id
                                JOIN class_sections cs ON e.section_id = cs.section_id
                                JOIN courses c ON cs.course_id = c.course_id
                                WHERE g.student_id = $studentId AND g.remarks = 'Passed' AND g.status = 'approved'
                            ");
$totalUnits = $totalUnitsResult->fetch_assoc()['total'] ?? 0;
?>
                            <h2 class="text-info mb-0"><?php echo $totalUnits; ?></h2>
                            <small class="text-muted">Units Earned</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card custom-table">
    <div class="card-header bg-primary text-white">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="fas fa-list"></i> My Grades</h5>
            </div>
            <div class="col-md-6">
                <form method="GET" class="row g-2">
                    <div class="col-md-5">
                        <select name="year" class="form-select form-select-sm">
                            <option value="">All Years</option>
                            <?php while ($year = $years->fetch_assoc()): ?>
                                <option value="<?php echo $year['school_year']; ?>" 
                                    <?php echo $filterYear === $year['school_year'] ? 'selected' : ''; ?>>
                                    <?php echo $year['school_year']; ?>
                                </option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <select name="semester" class="form-select form-select-sm">
                            <option value="">All Semesters</option>
                            <option value="1st" <?php echo $filterSemester === '1st' ? 'selected' : ''; ?>>1st Semester</option>
                            <option value="2nd" <?php echo $filterSemester === '2nd' ? 'selected' : ''; ?>>2nd Semester</option>
                            <option value="Summer" <?php echo $filterSemester === 'Summer' ? 'selected' : ''; ?>>Summer</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-light btn-sm w-100">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>School Year</th>
                        <th>Semester</th>
                        <th>Class Code</th>
                        <th>Subject Code</th>
                        <th>Subject Description</th>
                        <th>Units</th>
                        <th>Instructor</th>
                        <th>Midterm</th>
                        <th>Final</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($grade = $grades->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($grade['school_year'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($grade['semester'] ?? ''); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($grade['class_code'] ?? 'N/A'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($grade['course_code'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($grade['course_name'] ?? ''); ?></td>
                        <td class="text-center"><?php echo $grade['units']; ?></td>
                        <td><?php echo htmlspecialchars($grade['instructor_name'] ?? ''); ?></td>
                        <td class="text-center">
                            <?php echo $grade['midterm'] !== null ? number_format($grade['midterm'], 2) : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $grade['final'] !== null ? number_format($grade['final'], 2) : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($grade['grade'] !== null): ?>
                                <strong class="<?php echo $grade['remarks'] === 'Passed' ? 'text-success' : ($grade['remarks'] === 'Failed' ? 'text-danger' : ''); ?>">
                                    <?php echo $grade['grade'] !== null ? number_format($grade['grade'], 2) : '—'; ?>
                                </strong>
                            <?php
    else: ?>
                                -
                            <?php
    endif; ?>
                        </td>
                        <td class="text-center">
                            <?php
    $remarkClass = '';
    switch ($grade['remarks']) {
        case 'Passed':
            $remarkClass = 'success';
            break;
        case 'Failed':
            $remarkClass = 'danger';
            break;
        case 'INC':
            $remarkClass = 'warning';
            break;
        default:
            $remarkClass = 'secondary';
    }
?>
                            <span class="badge bg-<?php echo $remarkClass; ?>">
                                <?php echo htmlspecialchars($grade['remarks'] ?? 'Pending'); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php
    $statusClass = '';
    $statusText = '';
    switch ($grade['status']) {
        case 'approved':
            $statusClass = 'success';
            $statusText = 'Approved';
            break;
        case 'submitted':
            $statusClass = 'info';
            $statusText = 'Submitted';
            break;
        case 'pending':
            $statusClass = 'warning';
            $statusText = 'Pending';
            break;
        case 'rejected':
            $statusClass = 'danger';
            $statusText = 'Rejected';
            break;
        default:
            $statusClass = 'secondary';
            $statusText = 'Unknown';
    }
?>
                            <span class="badge bg-<?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
