<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        $studentId = intval($_POST['student_id']);
        $studentNo = sanitizeInput($_POST['student_no']);
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $dob = sanitizeInput($_POST['date_of_birth']);
        $gender = sanitizeInput($_POST['gender']);
        $address = sanitizeInput($_POST['address']);
        $municipality = sanitizeInput($_POST['municipality']);
        $religion = sanitizeInput($_POST['religion']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $email = sanitizeInput($_POST['email']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $deptId = intval($_POST['dept_id'] ?? 0);
        $yearLevel = intval($_POST['year_level']);
        $status = sanitizeInput($_POST['status']);

        $stmt = $conn->prepare("UPDATE students SET student_no = ?, first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, gender = ?, address = ?, municipality = ?, religion = ?, contact_number = ?, email = ?, program_id = ?, dept_id = ?, year_level = ?, status = ? WHERE student_id = ?");
        $stmt->bind_param("ssssssssssssiisi", $studentNo, $firstName, $lastName, $middleName, $dob, $gender, $address, $municipality, $religion, $contactNumber, $email, $programId, $deptId, $yearLevel, $status, $studentId);
        if ($stmt->execute()) {
            redirectWithMessage('students.php', 'Student profile updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $studentId = intval($_POST['student_id']);
        $stmt = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        if ($u = $stmt->get_result()->fetch_assoc()) {
            $userId = $u['user_id'];
            $conn->query("DELETE FROM users WHERE user_id = $userId");
            logAudit(getCurrentUserId(), 'DELETE', 'students', $studentId, null, "Deleted student profile (ID: $studentId) and associated user account.");
            redirectWithMessage('students.php', 'Student and account deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Students';
require_once '../includes/header.php';

// === STEP 3: Fetch data ===
$programs_list = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' ORDER BY p.program_name ASC");
$all_programs = [];
while ($p = $programs_list->fetch_assoc()) {
    $all_programs[] = $p;
}

$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments WHERE status = 'active' ORDER BY title_diploma_program ASC");
$departments_list = [];
while ($d = $dept_res->fetch_assoc()) {
    $departments_list[] = $d;
}

$students = $conn->query("
    SELECT s.*, u.username, u.status as user_status, d.title_diploma_program as dept_name, p.program_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    ORDER BY s.created_at DESC
");
?>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-primary">
            <i class="fas fa-user-graduate me-2 text-accent-indigo"></i> Student Academic Registry
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary rounded-pill px-3 shadow-sm btn-sm" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
            </button>
            <a href="generate_report.php?type=students" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-file-export me-2"></i> Export List
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table table-mobile-card">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Student ID / No</th>
                        <th>Student Name & Identity</th>
                        <th>Academic Placement</th>
                        <th>Year Level</th>
                        <th>Contact info</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Control Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" data-label="Student ID / No">
                            <span class="fw-bold text-primary"><?php echo htmlspecialchars($student['student_no']); ?></span>
                        </td>
                        <td data-label="Student Name & Identity">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3 bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px;">
                                    <i class="fas fa-user-graduate fa-lg"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-fingerprint me-1"></i> UID: <?php echo $student['student_id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Academic Placement">
                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($student['dept_name'] ?? 'Unassigned'); ?></div>
                            <div class="text-primary" style="font-size: 0.7rem;"><?php echo htmlspecialchars($student['program_name'] ?? 'General Program'); ?></div>
                        </td>
                        <td data-label="Year Level">
                            <span class="badge bg-light text-dark border px-3">Year <?php echo $student['year_level']; ?></span>
                        </td>
                        <td data-label="Contact info">
                            <div class="small"><i class="far fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                            <div class="small"><i class="fas fa-phone-alt me-1 text-muted"></i> <?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></div>
                        </td>
                        <td data-label="Status">
                            <?php
    $status = $student['status'] ?? 'inactive';
    $statusClasses = [
        'active' => 'bg-success bg-opacity-10 text-success',
        'graduated' => 'bg-primary bg-opacity-10 text-primary',
        'dropped' => 'bg-danger bg-opacity-10 text-danger',
        'inactive' => 'bg-secondary bg-opacity-10 text-secondary'
    ];
    $badgeClass = $statusClasses[$status] ?? 'bg-secondary bg-opacity-10 text-secondary';
?>
                            <span class="badge rounded-pill <?php echo $badgeClass; ?> px-3">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="d-flex justify-content-end gap-1">
                                <button class="btn btn-sm btn-light border text-primary rounded-pill" onclick='editStudent(<?php echo json_encode($student); ?>)' title="Edit Profile">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="view_student.php?id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-light border text-info rounded-pill" title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this student record? This cannot be undone.')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-light border text-danger rounded-pill">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="student_id" id="edit_student_id">
            <div class="modal-content">
                <div class="modal-header"><h5>Edit Student Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label>Student Number</label><input type="text" name="student_no" id="edit_student_no" class="form-control" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Birth Date</label>
                            <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Gender</label>
                            <select name="gender" id="edit_gender" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Religion</label>
                            <input type="text" name="religion" id="edit_religion" class="form-control" list="religionlist">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Home Address</label>
                            <input type="text" name="address" id="edit_address" class="form-control" list="addresslist">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Municipality/City</label>
                            <input type="text" name="municipality" id="edit_municipality" class="form-control" list="municipalitylist">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Program (Course)</label>
                            <select name="program_id" id="edit_program_id" class="form-select" required>
                                <option value="">-- Select Program --</option>
                                <?php foreach ($all_programs as $p): ?>
                                    <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                        <label class="form-label">Diploma Program</label>
                        <select name="dept_id" id="edit_dept_id" class="form-select" required>
                            <option value="">-- Select Diploma Program --</option>
                            <?php foreach ($departments_list as $d): ?>
                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Year Level</label>
                            <select name="year_level" id="edit_year_level" class="form-select" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Status</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Active</option><option value="inactive">Inactive</option><option value="graduated">Graduated</option><option value="dropped">Dropped</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Program Datalist Removed -->

<!-- Dept Datalist Removed -->

<!-- Religion Datalist -->
<datalist id="religionlist">
    <option value="Roman Catholic">
    <option value="Islam">
    <option value="Iglesia ni Cristo">
    <option value="Seventh-day Adventist">
    <option value="Bible Baptist Church">
    <option value="Jehovah’s Witnesses">
    <option value="United Church of Christ in the Philippines">
    <option value="Assembly of God">
    <option value="The Church of Jesus Christ of Latter-day Saints">
    <option value="Born Again Christian">
    <option value="Atheist / None">
</datalist>

<!-- Address Datalist (Local Barangays) -->
<datalist id="addresslist">
    <option value="Brgy. Poblacion">
    <option value="Brgy. San Jose">
    <option value="Brgy. Santa Maria">
    <option value="Brgy. Santo Niño">
    <option value="Brgy. San Juan">
    <option value="Brgy. San Pedro">
    <option value="Brgy. Santa Cruz">
    <option value="Brgy. Santa Lucia">
    <option value="Brgy. Fatima">
    <option value="Brgy. Maligaya">
</datalist>

<!-- Municipality Datalist -->
<datalist id="municipalitylist">
    <option value="Allen">
    <option value="Victoria">
    <option value="San Isidro">
    <option value="Lavezares">
    <option value="Rosario">
    <option value="San Jose">
    <option value="Catarman">
    <option value="Mondragon">
    <option value="San Roque">
    <option value="Pambujan">
</datalist>

<!-- Religion Datalist -->
<datalist id="religionlist">
    <option value="Roman Catholic">
    <option value="Islam">
    <option value="Iglesia ni Cristo">
    <option value="Seventh-day Adventist">
    <option value="Bible Baptist Church">
    <option value="Jehovah’s Witnesses">
    <option value="United Church of Christ in the Philippines">
    <option value="Assembly of God">
    <option value="The Church of Jesus Christ of Latter-day Saints">
    <option value="Born Again Christian">
    <option value="Atheist / None">
</datalist>

<!-- Address Datalist (Local Barangays) -->
<datalist id="addresslist">
    <option value="Brgy. Poblacion">
    <option value="Brgy. San Jose">
    <option value="Brgy. Santa Maria">
    <option value="Brgy. Santo Niño">
    <option value="Brgy. San Juan">
    <option value="Brgy. San Pedro">
    <option value="Brgy. Santa Cruz">
    <option value="Brgy. Santa Lucia">
    <option value="Brgy. Fatima">
    <option value="Brgy. Maligaya">
</datalist>

<script>
let studentModal;
document.addEventListener('DOMContentLoaded', function() {
    studentModal = new bootstrap.Modal(document.getElementById('editModal'));
});

function editStudent(data) {
    document.getElementById('edit_student_id').value = data.student_id;
    document.getElementById('edit_student_no').value = data.student_no;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_date_of_birth').value = data.date_of_birth;
    document.getElementById('edit_gender').value = data.gender || 'Male';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('edit_municipality').value = data.municipality || '';
    document.getElementById('edit_religion').value = data.religion || '';
    document.getElementById('edit_contact_number').value = data.contact_number || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_program_id').value = data.program_id || '';
    document.getElementById('edit_dept_id').value = data.dept_id || '';
    document.getElementById('edit_year_level').value = data.year_level;
    document.getElementById('edit_status').value = data.status;
    studentModal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
