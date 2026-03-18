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
    SELECT cs.*, c.class_code, c.course_code, c.course_name
    FROM class_sections cs
    JOIN courses c ON cs.course_id = c.course_id
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

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center p-3">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i> Class Roster</h5>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-sm btn-light rounded-pill px-3">
                        <i class="fas fa-print me-1"></i> Print Roster
                    </button>
                    <a href="submit_grades.php?section_id=<?php echo $sectionId; ?>" class="btn btn-sm btn-light rounded-pill px-3 text-primary fw-bold">
                        <i class="fas fa-edit me-1"></i> Enter Grades
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6 border-end">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Course Details</h6>
                        <div class="d-flex mb-2">
                            <span class="text-muted w-25">Code:</span>
                            <span class="fw-bold text-primary"><?php echo htmlspecialchars($section['course_code']); ?></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted w-25">Course:</span>
                            <span class="fw-bold"><?php echo htmlspecialchars($section['course_name']); ?></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted w-25">Class Code:</span>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($section['class_code'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Schedule & Section</h6>
                        <div class="d-flex mb-2">
                            <span class="text-muted w-25">Section:</span>
                            <span class="fw-bold"><?php echo htmlspecialchars($section['section_name']); ?></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted w-25">Schedule:</span>
                            <span><?php echo htmlspecialchars($section['schedule'] ?? 'TBA'); ?></span>
                        </div>
                        <div class="d-flex mb-2">
                            <span class="text-muted w-25">SY/Sem:</span>
                            <span><?php echo htmlspecialchars($section['semester'] . ' ' . $section['school_year']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 border-0" style="width: 50px;">#</th>
                                <th class="py-3 border-0">Student ID</th>
                                <th class="py-3 border-0">Full Name</th>
                                <th class="py-3 border-0">Gender</th>
                                <th class="py-3 border-0">Contact</th>
                                <th class="py-3 border-0">Email</th>
                                <th class="py-3 border-0">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
$count = 1;
if ($students->num_rows > 0):
    while ($student = $students->fetch_assoc()):
?>
                            <tr>
                                <td class="py-3"><?php echo $count++; ?></td>
                                <td class="py-3 fw-semibold text-primary"><?php echo htmlspecialchars($student['student_no']); ?></td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ? substr($student['middle_name'], 0, 1) . '.' : '')); ?></div>
                                </td>
                                <td class="py-3"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></td>
                                <td class="py-3"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></td>
                                <td class="py-3 text-muted small"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                <td class="py-3">
                                    <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill border border-success border-opacity-10">Enrolled</span>
                                </td>
                            </tr>
                            <?php
    endwhile;
else:
?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted italic">
                                    <i class="fas fa-user-slash d-block mb-3 display-6 opacity-25"></i>
                                    No students enrolled in this section yet.
                                </td>
                            </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Total Students: <strong><?php echo $students->num_rows; ?></strong></span>
                    <a href="my_classes.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                        <i class="fas fa-arrow-left me-1"></i> Back to My Classes
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
