<?php
/**
 * Instructor - Class Roster
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('instructor');
$conn = getDBConnection();
$userId = getCurrentUserId();

// Get instructor profile
$stmt = $conn->prepare("SELECT * FROM instructors WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$instructor) {
    redirectWithMessage('dashboard.php', 'Instructor profile not found.', 'danger');
}

$instructorId = $instructor['instructor_id'];
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

if ($sectionId <= 0) {
    redirectWithMessage('my_classes.php', 'Please select a class section first.', 'warning');
}

// Verify permission and get section info
$sectionStmt = $conn->prepare("
    SELECT cs.*, cur.class_code, s.subject_id as course_code, s.subject_name as course_name
    FROM class_sections cs
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects s ON cur.subject_id = s.subject_id
    WHERE cs.section_id = ? AND cs.instructor_id = ?
");
$sectionStmt->bind_param("ii", $sectionId, $instructorId);
$sectionStmt->execute();
$section = $sectionStmt->get_result()->fetch_assoc();
$sectionStmt->close();

if (!$section) {
    redirectWithMessage('my_classes.php', 'Section not found or access denied.', 'danger');
}

// Get enrolled students
$studentsStmt = $conn->prepare("
    SELECT 
        s.student_id,
        s.student_no,
        u.profile_image,
        s.first_name,
        s.last_name,
        s.middle_name,
        s.gender,
        s.email,
        s.contact_number,
        e.enrollment_date,
        e.status as enrollment_status
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE e.section_id = ? AND e.status = 'enrolled'
    ORDER BY s.last_name, s.first_name
");
$studentsStmt->bind_param("i", $sectionId);
$studentsStmt->execute();
$students = $studentsStmt->get_result();

$pageTitle = 'Class Roster - ' . ($section['course_code'] ?? 'N/A');
require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="my_classes.php">My Classes</a></li>
                <li class="breadcrumb-item active">Class Roster</li>
            </ol>
        </nav>

        <div class="card premium-card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center p-3 p-md-4">
                <h5 class="mb-0 fw-bold"><i class="fas fa-users me-2"></i> Class Roster</h5>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-sm btn-light rounded-pill px-3 d-none d-sm-inline-block">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <a href="submit_grades.php?section_id=<?php echo $sectionId; ?>" class="btn btn-sm btn-light rounded-pill px-3 text-primary fw-bold">
                        <i class="fas fa-edit me-1"></i> Grades
                    </a>
                </div>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row mb-4 g-3">
                    <div class="col-md-6 border-md-end">
                        <h6 class="text-muted text-uppercase x-small fw-bold mb-3 ls-1">Course Intelligence</h6>
                        <div class="d-flex mb-2 align-items-center">
                            <span class="text-muted x-small text-uppercase fw-bold" style="width: 80px;">Status:</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill x-small px-3"><?php echo htmlspecialchars($section['course_code']); ?></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted x-small text-uppercase fw-bold" style="width: 80px;">Title:</span>
                            <span class="fw-bold text-dark small"><?php echo htmlspecialchars($section['course_name']); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <h6 class="text-muted text-uppercase x-small fw-bold mb-3 ls-1">Schedule & Context</h6>
                        <div class="d-flex mb-2">
                            <span class="text-muted x-small text-uppercase fw-bold" style="width: 80px;">Section:</span>
                            <span class="fw-bold text-accent-indigo small"><?php echo htmlspecialchars($section['section_name']); ?></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted x-small text-uppercase fw-bold" style="width: 80px;">Period:</span>
                            <span class="text-dark small"><?php echo htmlspecialchars($section['semester'] . ' ' . $section['school_year']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Student ID</th>
                                <th>Full Identity</th>
                                <th>Attributes</th>
                                <th>Communication</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $count = 1; if ($students->num_rows > 0): while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td class="fw-semibold text-primary"><?php echo htmlspecialchars($student['student_no']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle overflow-hidden" style="width: 35px; height: 35px;">
                                            <?php if (!empty($student['profile_image'])): ?>
                                                <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($student['profile_image']); ?>?v=<?php echo time(); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fas fa-user-graduate"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-muted border rounded-pill px-2 x-small"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></span></td>
                                <td class="small">
                                    <div class="text-dark"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></div>
                                    <div class="text-muted x-small"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 rounded-pill px-2 x-small">ENROLLED</span>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center py-5 italic text-muted">No students enrolled.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Student Cards (Prototype Style) -->
                <div class="d-block d-md-none">
                    <?php 
                    $students->data_seek(0);
                    if ($students->num_rows > 0): 
                        while ($student = $students->fetch_assoc()): ?>
                        <div class="card p-3 mb-3 border shadow-none rounded-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="fw-bold text-dark fs-15"><?php echo strtoupper($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill x-small">Active</span>
                            </div>
                            <div class="x-small text-muted mb-2">
                                ID: <span class="fw-bold text-primary"><?php echo htmlspecialchars($student['student_no']); ?></span> | <?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?>
                            </div>
                            <div class="bg-light p-2 rounded-3 mb-2">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="x-small text-muted opacity-75"><i class="fas fa-envelope me-1"></i> Email</div>
                                        <div class="small fw-semibold text-truncate"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="col-12 mt-1">
                                        <div class="x-small text-muted opacity-75"><i class="fas fa-phone me-1"></i> Contact</div>
                                        <div class="small fw-semibold"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="mailto:<?php echo $student['email']; ?>" class="btn btn-outline-primary btn-sm flex-grow-1 py-2 rounded-pill"><i class="fas fa-envelope me-1"></i> Message</a>
                                <a href="print_grade_slip.php?student_id=<?php echo $student['student_id']; ?>&section_id=<?php echo $sectionId; ?>" class="btn btn-light btn-sm flex-grow-1 py-2 rounded-pill"><i class="fas fa-file-alt me-1"></i> Slip</a>
                            </div>
                        </div>
                        <?php endwhile; 
                    else: ?>
                        <div class="text-center py-5 text-muted small italic">No students enrolled in this section yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-light p-3 border-0 rounded-bottom-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Aggregate: <strong><?php echo $students->num_rows; ?></strong> Students</span>
                    <a href="my_classes.php" class="text-primary fw-bold text-decoration-none small">
                        <i class="fas fa-arrow-left me-1"></i> Back to Load Registry
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .navbar, .breadcrumb, .btn, .card-footer, .card-header .btn { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { background: #fff !important; color: #000 !important; border-bottom: 2px solid #000 !important; }
    .card-body { padding: 0 !important; }
    .table thead th { background-color: #f8f9fa !important; border-bottom: 2px solid #dee2e6 !important; -webkit-print-color-adjust: exact; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
