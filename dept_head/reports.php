<?php
/**
 * Diploma Program Head - Reports & Analytics
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userProfile = getUserProfile($_SESSION['user_id'], 'dept_head');
$deptId = $userProfile['dept_id'] ?? 0;
$deptName = $userProfile['dept_name'] ?? 'Diploma Program';

$pageTitle = 'Diploma Program Reports';
require_once '../includes/header.php';

// 1. Grade Distribution for the Diploma Program
$distQuery = $conn->prepare("
    SELECT 
        remarks, 
        COUNT(*) as count
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE c.dept_id = ? AND g.status = 'approved'
    GROUP BY remarks
");
$distQuery->bind_param("i", $deptId);
$distQuery->execute();
$distribution = $distQuery->get_result();

$distData = [];
while ($row = $distribution->fetch_assoc()) {
    $distData[$row['remarks']] = $row['count'];
}

// 2. Faculty Workload Consolidated
$currentSem = getSetting('current_semester', '1st');
$currentSY = getSetting('academic_year', '2024-2025');

$workloadQuery = $conn->prepare("
    SELECT 
        i.first_name, i.last_name, i.instructor_id_no,
        COUNT(cs.section_id) as total_sections,
        COALESCE(SUM(c.units), 0) as total_units,
        (SELECT COUNT(*) FROM enrollments e
         JOIN class_sections s ON e.section_id = s.section_id
         WHERE s.instructor_id = i.instructor_id 
         AND s.status = 'active'
         AND s.semester = ?
         AND s.school_year = ?) as total_students
    FROM instructors i
    LEFT JOIN class_sections cs ON i.instructor_id = cs.instructor_id 
        AND cs.status = 'active'
        AND cs.semester = ?
        AND cs.school_year = ?
    LEFT JOIN courses c ON cs.course_id = c.course_id
    WHERE i.dept_id = ?
    GROUP BY i.instructor_id
    ORDER BY total_units DESC
");
$workloadQuery->bind_param("ssssi", $currentSem, $currentSY, $currentSem, $currentSY, $deptId);
$workloadQuery->execute();
$workload = $workloadQuery->get_result();

// 3. Subject Performance Analytics
$perfQuery = $conn->prepare("
    SELECT 
        c.course_code, c.course_name,
        COUNT(*) as total_grades,
        SUM(CASE WHEN g.remarks = 'Passed' THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN g.remarks = 'Failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN g.remarks = 'INC' THEN 1 ELSE 0 END) as inc_count,
        SUM(CASE WHEN g.remarks = 'Dropped' THEN 1 ELSE 0 END) as dropped_count,
        ROUND(AVG(g.grade), 2) as avg_grade
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE c.dept_id = ? AND g.status = 'approved'
    GROUP BY c.course_id
    ORDER BY avg_grade ASC
");
$perfQuery->bind_param("i", $deptId);
$perfQuery->execute();
$performance = $perfQuery->get_result();
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Reports & Analytics</li>
        </ol>
    </nav>
</div>

<div class="row g-4 mb-4">
    <!-- Grade Distribution Card -->
    <div class="col-lg-4">
        <div class="card h-100 shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie text-primary me-2"></i> Grade Remarks Distribution</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php
$remarks_list = ['Passed' => 'success', 'Failed' => 'danger', 'INC' => 'warning', 'Dropped' => 'secondary'];
foreach ($remarks_list as $rem => $color):
    $count = $distData[$rem] ?? 0;
?>
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                            <span><i class="fas fa-circle text-<?php echo $color; ?> me-2 small"></i> <?php echo $rem; ?></span>
                            <span class="badge bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> rounded-pill px-3"><?php echo $count; ?></span>
                        </div>
                    <?php
endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Diploma Program Performance Summary -->
    <div class="col-lg-8">
        <div class="card h-100 shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-graduation-cap text-primary me-2"></i> Subject Performance Overview</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Subject</th>
                                <th class="text-center">Avg Grade</th>
                                <th class="text-center">Pass Rate</th>
                                <th class="text-center">INC/Drop</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $performance->fetch_assoc()):
    $passRate = $p['total_grades'] > 0 ? round(($p['passed_count'] / $p['total_grades']) * 100, 1) : 0;
?>
                                <tr>
                                    <td>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($p['course_code']); ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($p['course_name']); ?></div>
                                    </td>
                                    <td class="text-center fw-bold text-primary"><?php echo $p['avg_grade'] ?: '—'; ?></td>
                                    <td class="text-center">
                                        <div class="progress" style="height: 6px; width: 80px; margin: 0 auto;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $passRate; ?>%"></div>
                                        </div>
                                        <small class="fw-bold text-success" style="font-size: 0.7rem;"><?php echo $passRate; ?>%</small>
                                    </td>
                                    <td class="text-center small">
                                        <span class="text-warning"><?php echo $p['inc_count']; ?></span> / 
                                        <span class="text-secondary"><?php echo $p['dropped_count']; ?></span>
                                    </td>
                                </tr>
                            <?php
endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-white border-0 py-3">
        <h5 class="mb-0 fw-bold"><i class="fas fa-users-cog text-primary me-2"></i> Faculty Workload Analysis</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Instructor</th>
                        <th class="text-center">ID Number</th>
                        <th class="text-center">Total Sections</th>
                        <th class="text-center">Total Units</th>
                        <th class="text-center">Total Students</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($w = $workload->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($w['first_name'] . ' ' . $w['last_name']); ?></td>
                            <td class="text-center"><small class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($w['instructor_id_no']); ?></small></td>
                            <td class="text-center"><?php echo $w['total_sections']; ?></td>
                            <td class="text-center fw-bold text-primary"><?php echo $w['total_units'] ?: 0; ?></td>
                            <td class="text-center">
                                <span class="badge bg-info bg-opacity-10 text-info px-3"><?php echo $w['total_students']; ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($w['total_units'] >= 24): ?>
                                    <span class="badge bg-danger rounded-pill">Overload</span>
                                <?php
    elseif ($w['total_units'] >= 18): ?>
                                    <span class="badge bg-success rounded-pill">Full Load</span>
                                <?php
    else: ?>
                                    <span class="badge bg-warning text-dark rounded-pill">Partial</span>
                                <?php
    endif; ?>
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
