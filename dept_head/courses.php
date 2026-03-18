<?php
/**
 * Diploma Program Head - Course Catalog
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userProfile = getUserProfile($_SESSION['user_id'], 'dept_head');
$deptId = $userProfile['dept_id'] ?? 0;
$deptName = $userProfile['dept_name'] ?? 'Diploma Program';

$pageTitle = 'Diploma Program Course Catalog';
require_once '../includes/header.php';

// Fetch courses for this department with enrollment stats and passing rates
$query = "
    SELECT 
        c.*,
        (SELECT COUNT(DISTINCT e.student_id) 
         FROM enrollments e 
         JOIN class_sections cs ON e.section_id = cs.section_id 
         WHERE cs.course_id = c.course_id AND cs.status = 'active') as active_enrollees,
        (SELECT ROUND(AVG(g.grade), 2) 
         FROM grades g 
         JOIN enrollments e ON g.enrollment_id = e.enrollment_id
         JOIN class_sections cs ON e.section_id = cs.section_id
         WHERE cs.course_id = c.course_id AND g.status = 'approved' AND g.grade > 0) as avg_grade
    FROM courses c
    JOIN programs p ON c.program_id = p.program_id
    WHERE p.dept_id = ?
    ORDER BY p.program_name, c.course_code ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$courses = $stmt->get_result();
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Course Catalog</li>
        </ol>
    </nav>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-primary text-white p-3">
        <h5 class="mb-0"><i class="fas fa-book me-2"></i> <?php echo htmlspecialchars($deptName); ?> - Course Catalog</h5>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle data-table">
                <thead class="bg-light">
                    <tr>
                        <th>Subject Code</th>
                        <th>Description</th>
                        <th class="text-center">Units</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Enrollees</th>
                        <th class="text-center">Avg Grade</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $courses->fetch_assoc()): ?>
                    <tr>
                        <td><strong class="text-primary"><?php echo htmlspecialchars($c['course_code']); ?></strong></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($c['course_name']); ?></div>
                            <small class="text-muted">Class Code: <?php echo htmlspecialchars($c['class_code'] ?? 'N/A'); ?></small>
                        </td>
                        <td class="text-center"><?php echo $c['units']; ?></td>
                        <td class="text-center">
                            <?php if (($c['course_type'] ?? 'Minor') === 'Major'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Major</span>
                            <?php
    else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Minor</span>
                            <?php
    endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-info bg-opacity-10 text-info px-3">
                                <?php echo $c['active_enrollees']; ?> Students
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold <?php echo($c['avg_grade'] && $c['avg_grade'] > 3.0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $c['avg_grade'] ? number_format($c['avg_grade'], 2) : '—'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $c['status'] === 'active' ? 'success' : 'secondary'; ?> rounded-pill">
                                <?php echo ucfirst($c['status']); ?>
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
