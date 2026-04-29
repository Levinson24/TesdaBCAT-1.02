<?php
$pageTitle = 'Reports';
require_once '../includes/header.php';
requireRole('admin');
$conn = getDBConnection();

// Get statistics
$stats = [];
$stats['total_students'] = $conn->query("SELECT COUNT(*) as c FROM students WHERE status = 'active'")->fetch_assoc()['c'];
$stats['total_instructors'] = $conn->query("SELECT COUNT(*) as c FROM instructors WHERE status = 'active'")->fetch_assoc()['c'];
$stats['total_courses'] = $conn->query("SELECT COUNT(*) as c FROM curriculum WHERE status = 'active'")->fetch_assoc()['c'];
$stats['total_enrollments'] = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE status = 'enrolled'")->fetch_assoc()['c'];
$stats['approved_grades'] = $conn->query("SELECT COUNT(*) as c FROM grades WHERE status = 'approved'")->fetch_assoc()['c'];

// Top performing students (GWA)
$topStudents = $conn->query("
    SELECT s.student_no, CONCAT(s.first_name, ' ', s.last_name) as name,
           SUM(g.grade * subj.units) / NULLIF(SUM(subj.units), 0) as gwa
    FROM students s
    JOIN grades g ON s.student_id = g.student_id
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects subj ON cur.subject_id = subj.subject_id
    WHERE g.status = 'approved'
    GROUP BY s.student_id
    ORDER BY gwa ASC
    LIMIT 10
");
?>

<div class="responsive-grid mb-4">
    <div class="card premium-card bg-primary text-white border-0 shadow-sm">
        <div class="card-body p-4 d-flex align-items-center gap-3">
            <div class="stat-icon bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                <i class="fas fa-user-graduate fa-lg"></i>
            </div>
            <div>
                <h3 class="mb-0 fw-bold"><?php echo $stats['total_students']; ?></h3>
                <p class="mb-0 small opacity-75">Active Students</p>
            </div>
        </div>
    </div>
    <div class="card premium-card bg-info text-white border-0 shadow-sm">
        <div class="card-body p-4 d-flex align-items-center gap-3">
            <div class="stat-icon bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                <i class="fas fa-chalkboard-teacher fa-lg"></i>
            </div>
            <div>
                <h3 class="mb-0 fw-bold"><?php echo $stats['total_instructors']; ?></h3>
                <p class="mb-0 small opacity-75">Active Instructors</p>
            </div>
        </div>
    </div>
    <div class="card premium-card bg-success text-white border-0 shadow-sm">
        <div class="card-body p-4 d-flex align-items-center gap-3">
            <div class="stat-icon bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                <i class="fas fa-book fa-lg"></i>
            </div>
            <div>
                <h3 class="mb-0 fw-bold"><?php echo $stats['total_courses']; ?></h3>
                <p class="mb-0 small opacity-75">Active Courses</p>
            </div>
        </div>
    </div>
    <div class="card premium-card bg-warning text-white border-0 shadow-sm">
        <div class="card-body p-4 d-flex align-items-center gap-3">
            <div class="stat-icon bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                <i class="fas fa-user-plus fa-lg"></i>
            </div>
            <div>
                <h3 class="mb-0 fw-bold"><?php echo $stats['total_enrollments']; ?></h3>
                <p class="mb-0 small opacity-75">Current Enrollments</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-trophy"></i> Top Performing Students</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-mobile-card align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Rank</th>
                        <th>Student No</th>
                        <th>Name</th>
                        <th class="text-end pe-4">GWA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1;
                    while ($s = $topStudents->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" data-label="Rank">
                            <span class="badge rounded-circle bg-<?php echo $rank <= 3 ? 'warning' : 'light text-dark'; ?> p-2" style="width: 30px; height: 30px;">
                                <?php echo $rank++; ?>
                            </span>
                        </td>
                        <td data-label="Student No"><strong><?php echo htmlspecialchars($s['student_no']); ?></strong></td>
                        <td data-label="Name"><?php echo htmlspecialchars($s['name']); ?></td>
                        <td class="text-end pe-4" data-label="GWA">
                            <span class="fw-bold text-primary fs-5"><?php echo $s['gwa'] !== null ? number_format($s['gwa'], 2) : '0.00'; ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
