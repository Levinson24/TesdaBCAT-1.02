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
<style>
    .bg-dark-navy {
        background-color: #0f172a !important;
    }
    .premium-card {
        border-radius: 1rem;
    }
</style>
<?php

?>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-user-graduate me-2 text-info"></i> Students in <?php echo htmlspecialchars($userProfile['dept_name'] ?? 'Your Diploma Program'); ?>
        </h5>
        <a href="?export=csv" class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary pe-3 me-2">
            <i class="fas fa-download me-2"></i> Export CSV
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Student No</th>
                        <th>Student Name</th>
                        <th>Program (Course)</th>
                        <th>Year Level</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="fw-bold text-primary">#<?php echo htmlspecialchars($s['student_no']); ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 35px; height: 35px;">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div>
                                    <a href="view_student.php?student_id=<?php echo $s['student_id']; ?>" class="text-decoration-none fw-bold text-dark">
                                        <?php echo htmlspecialchars($s['last_name'] . ', ' . $s['first_name']); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-muted small"><?php echo htmlspecialchars($s['program_name'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.7rem;">Year <?php echo $s['year_level']; ?></span>
                        </td>
                        <td>
                            <?php 
                                $statusColor = ($s['status'] === 'active' ? 'success' : ($s['status'] === 'graduated' ? 'primary' : 'secondary'));
                            ?>
                            <span class="badge rounded-pill bg-<?php echo $statusColor; ?> bg-opacity-10 text-<?php echo $statusColor; ?> px-3">
                                <i class="fas fa-circle me-1" style="font-size: 0.4rem;"></i> <?php echo ucfirst($s['status']); ?>
                            </span>
                        </td>
                         <td class="text-end pe-4">
                            <div class="btn-group">
                                <a href="view_student.php?student_id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-light border text-primary rounded-pill me-1" title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="manage_student_schedule.php?student_id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-light border text-info rounded-pill" title="Enrollment">
                                    <i class="fas fa-calendar-check"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
