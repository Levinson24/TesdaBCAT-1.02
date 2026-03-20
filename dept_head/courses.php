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

// === Premium Styles ===
?>
<style>
    .premium-card {
        border-radius: 1rem;
    }
    .bg-dark-navy {
        background-color: #0f172a !important;
    }
    .courses-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .courses-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        font-size: 0.85rem;
    }
    .subject-icon-box {
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
    .stat-pill {
        font-weight: 700;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .stat-blue { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
    .stat-green { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.25rem 0.75rem;
        border-radius: 2rem;
    }
    .status-pill-active { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .status-pill-inactive { background-color: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }
</style>
<?php

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

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-book me-2 text-info"></i> <?php echo htmlspecialchars($deptName); ?> - Course Catalog
        </h5>
        <div class="pe-2">
            <span class="badge bg-light text-dark fw-bold px-3 py-2 shadow-sm" style="font-size: 0.75rem;">
                <i class="fas fa-layer-group me-1 text-primary"></i> Academic Year 2025-2026
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 courses-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">SUBJECT IDENTITY</th>
                        <th>SUBJ DESCRIPTION</th>
                        <th class="text-center">UNITS</th>
                        <th class="text-center">TYPE</th>
                        <th class="text-center">ENROLLEES</th>
                        <th class="text-center">AVG GRADE</th>
                        <th class="text-end pe-4">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $courses->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="subject-icon-box">
                                    <?php echo substr($c['class_code'] ?? 'S', 0, 1); ?>
                                </div>
                                <div class="fw-bold text-primary"><?php echo htmlspecialchars($c['course_code']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark lh-sm"><?php echo htmlspecialchars($c['course_name']); ?></div>
                            <small class="text-muted">Class Code: <span class="fw-semibold"><?php echo htmlspecialchars($c['class_code'] ?? 'N/A'); ?></span></small>
                        </td>
                        <td class="text-center">
                            <span class="stat-pill stat-blue"><?php echo $c['units']; ?></span>
                        </td>
                        <td class="text-center">
                            <?php if (($c['course_type'] ?? 'Minor') === 'Major'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border-0 px-2 fw-bold" style="font-size: 0.7rem;">MAJOR</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border-0 px-2 fw-bold" style="font-size: 0.7rem;">MINOR</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="stat-pill stat-blue">
                                <i class="fas fa-users me-1"></i> <?php echo $c['active_enrollees']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="stat-pill <?php echo($c['avg_grade'] && $c['avg_grade'] > 3.0) ? 'stat-rose' : 'stat-green'; ?> fw-bold">
                                <?php echo $c['avg_grade'] ? number_format($c['avg_grade'], 2) : '—'; ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <?php if (($c['status'] ?? 'active') === 'active'): ?>
                                <div class="status-pill status-pill-active float-end">
                                    <div class="status-dot" style="background: #22c55e;"></div> Active
                                </div>
                            <?php else: ?>
                                <div class="status-pill status-pill-inactive float-end">
                                    <div class="status-dot" style="background: #94a3b8;"></div> Inactive
                                </div>
                            <?php endif; ?>
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
