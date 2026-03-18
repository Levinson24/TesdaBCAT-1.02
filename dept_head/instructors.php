<?php
/**
 * Diploma Program Head - Instructors List
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userId = getCurrentUserId();
$profile = getUserProfile($userId, 'dept_head');
$deptId = $profile['dept_id'];
$deptName = $profile['dept_name'] ?? 'Your Diploma Program';

// Fetch instructors for THIS department only
$stmt = $conn->prepare("
    SELECT i.*, u.username, u.last_login
    FROM instructors i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.dept_id = ?
    ORDER BY i.last_name ASC
");
$stmt->bind_param("i", $deptId);
$stmt->execute();
$instructors = $stmt->get_result();

// CSV Export logic
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $instructors->data_seek(0);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="faculty_' . strtolower(str_replace(' ', '_', $deptName)) . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID Number', 'First Name', 'Last Name', 'Specialization', 'Email', 'Contact', 'Status']);
    while ($row = $instructors->fetch_assoc()) {
        fputcsv($out, [
            $row['instructor_id_no'],
            $row['first_name'],
            $row['last_name'],
            $row['specialization'],
            $row['email'],
            $row['contact_number'],
            ucfirst($row['status'])
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Diploma Program Faculty';
require_once '../includes/header.php';

?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Faculty Members - <?php echo htmlspecialchars($deptName); ?></h5>
        <a href="?export=csv" class="btn btn-light btn-sm fw-bold">
            <i class="fas fa-download me-1"></i> Export CSV
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($i = $instructors->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($i['instructor_id_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($i['first_name'] . ' ' . $i['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($i['specialization'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $i['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($i['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewInstructor(<?php echo $i['instructor_id']; ?>)">
                                <i class="fas fa-eye"></i> View Load
                            </button>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewInstructor(id) {
    window.location.href = "instructor_load.php?id=" + id;
}
</script>

<?php require_once '../includes/footer.php'; ?>
