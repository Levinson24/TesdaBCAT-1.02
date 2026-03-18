<?php
/**
 * Diploma Program Head - Students List
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userProfile = getUserProfile($_SESSION['user_id'], 'dept_head');
$deptId = $userProfile['dept_id'] ?? 0;

$studentsQuery = $conn->prepare("
    SELECT s.*, u.username, d.title_diploma_program as dept_name, p.program_name as program_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE s.dept_id = ?
    ORDER BY s.last_name ASC
");
$studentsQuery->bind_param("i", $deptId);
$studentsQuery->execute();
$result = $studentsQuery->get_result();

// CSV Export logic
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $result->data_seek(0);
    $deptName = $userProfile['dept_name'] ?? 'diploma program';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="students_' . strtolower(str_replace(' ', '_', $deptName)) . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student No', 'First Name', 'Last Name', 'Program (Course)', 'Year Level', 'Status', 'Enrollment Date']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['student_no'],
            $row['first_name'],
            $row['last_name'],
            $row['program_name'] ?? 'N/A',
            $row['year_level'],
            ucfirst($row['status']),
            $row['enrollment_date']
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Diploma Program Students';
require_once '../includes/header.php';

?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Students in <?php echo htmlspecialchars($userProfile['dept_name'] ?? 'Your Diploma Program'); ?></h5>
        <a href="?export=csv" class="btn btn-light btn-sm fw-bold">
            <i class="fas fa-download me-1"></i> Export CSV
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>Student No</th>
                        <th>Name</th>
                        <th>Program (Course)</th>
                        <th>Year Level</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['student_no']); ?></td>
                        <td>
                            <a href="view_student.php?student_id=<?php echo $s['student_id']; ?>" class="text-decoration-none fw-bold">
                                <?php echo htmlspecialchars($s['last_name'] . ', ' . $s['first_name']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($s['program_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $s['year_level']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $s['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($s['status']); ?>
                            </span>
                        </td>
                         <td class="text-end text-nowrap">
                            <a href="view_student.php?student_id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Profile">
                                <i class="fas fa-eye me-1"></i> View
                            </a>
                            <a href="manage_student_schedule.php?student_id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-info" title="Manage Enrollment">
                                <i class="fas fa-calendar-check mr-1"></i> Enrollment
                            </a>
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
