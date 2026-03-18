<?php
/**
 * Admin - Instructor Management
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments ORDER BY title_diploma_program");
$departments_list = [];
while ($d = $dept_res->fetch_assoc())
    $departments_list[] = $d;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $dob = sanitizeInput($_POST['date_of_birth']); // Expected YYYY-MM-DD
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $deptId = intval($_POST['dept_id'] ?? 0);
        $specialization = sanitizeInput($_POST['specialization']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $email = sanitizeInput($_POST['email']);

        $instructorIdNo = generateNextID('instructor');
        $formatted_dob = date('m/d/Y', strtotime($dob));
        $username = $instructorIdNo;
        $password = hashPassword($formatted_dob);

        // Remove manual username/password from POST as we auto-generate
        unset($_POST['username'], $_POST['password']);

        // Check for duplicate username
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            redirectWithMessage('instructors.php', 'Instructor ID already exists as a username.', 'danger');
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'instructor', 'active')");
        $stmt->bind_param("ss", $username, $password);
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO instructors (user_id, instructor_id_no, first_name, last_name, middle_name, date_of_birth, dept_id, specialization, contact_number, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isssssisss", $userId, $instructorIdNo, $firstName, $lastName, $middleName, $dob, $deptId, $specialization, $contactNumber, $email);
            $stmt2->execute();
            redirectWithMessage('instructors.php', 'Instructor created successfully. Login: ID=' . $instructorIdNo . ', Pwd=' . $formatted_dob, 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $instructorId = intval($_POST['instructor_id']);
        $instructorIdNo = sanitizeInput($_POST['instructor_id_no']);
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $dob = sanitizeInput($_POST['date_of_birth']);
        $deptId = intval($_POST['dept_id'] ?? 0);
        $specialization = sanitizeInput($_POST['specialization']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $email = sanitizeInput($_POST['email']);
        $status = sanitizeInput($_POST['status']);

        $stmt = $conn->prepare("UPDATE instructors SET instructor_id_no = ?, first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, dept_id = ?, specialization = ?, contact_number = ?, email = ?, status = ? WHERE instructor_id = ?");
        $stmt->bind_param("sssssissssi", $instructorIdNo, $firstName, $lastName, $middleName, $dob, $deptId, $specialization, $contactNumber, $email, $status, $instructorId);
        if ($stmt->execute()) {
            redirectWithMessage('instructors.php', 'Instructor updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $instructorId = intval($_POST['instructor_id']);
        $stmt = $conn->prepare("SELECT user_id FROM instructors WHERE instructor_id = ?");
        $stmt->bind_param("i", $instructorId);
        $stmt->execute();
        if ($u = $stmt->get_result()->fetch_assoc()) {
            $userId = $u['user_id'];
            $conn->query("DELETE FROM users WHERE user_id = $userId");
            logAudit(getCurrentUserId(), 'DELETE', 'instructors', $instructorId, null, "Deleted instructor profile (ID: $instructorId) and associated user account.");
            redirectWithMessage('instructors.php', 'Instructor and account deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Instructors';
require_once '../includes/header.php';

$instructors = $conn->query("
    SELECT i.*, u.username, d.title_diploma_program as dept_name,
           (SELECT COUNT(*) FROM class_sections cs WHERE cs.instructor_id = i.instructor_id AND cs.status = 'active') as total_classes
    FROM instructors i
    JOIN users u ON i.user_id = u.user_id
    LEFT JOIN departments d ON i.dept_id = d.dept_id
    ORDER BY i.last_name, i.first_name
");

// Departments are now managed via the departments table
?>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-primary">
            <i class="fas fa-chalkboard-teacher me-2 text-accent-indigo"></i> Faculty & Instructor Registry
        </h5>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-2"></i> Add Instructor
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table table-mobile-card">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Instructor ID</th>
                        <th>Faculty Identity</th>
                        <th>Assignment</th>
                        <th>Specialization</th>
                        <th>Class Load</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Control Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($i = $instructors->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" data-label="Instructor ID">
                            <span class="fw-bold text-primary"><?php echo htmlspecialchars($i['instructor_id_no'] ?? 'N/A'); ?></span>
                        </td>
                        <td data-label="Faculty Identity">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px;">
                                    <i class="fas fa-chalkboard-teacher fa-lg"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($i['first_name'] . ' ' . $i['last_name']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><i class="far fa-envelope me-1"></i> <?php echo htmlspecialchars($i['email'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Assignment">
                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($i['dept_name'] ?? 'Unassigned'); ?></div>
                            <div class="text-muted small" style="font-size: 0.7rem;"><i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($i['contact_number'] ?? 'N/A'); ?></div>
                        </td>
                        <td data-label="Specialization">
                            <span class="badge bg-light text-dark border px-2 font-monospace" style="font-size: 0.7rem;">
                                <?php echo htmlspecialchars($i['specialization'] ?? 'Generalist'); ?>
                            </span>
                        </td>
                        <td data-label="Class Load">
                            <div class="d-flex align-items-center">
                                <span class="badge rounded-pill bg-info bg-opacity-10 text-info px-3 me-2">
                                    <?php echo $i['total_classes']; ?> Sections
                                </span>
                            </div>
                        </td>
                        <td data-label="Status">
                            <?php $status = $i['status'] ?? 'inactive'; ?>
                            <span class="badge rounded-pill <?php echo $status === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary'; ?> px-3">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <button class="btn btn-sm btn-light border text-primary rounded-pill me-1" onclick='editInstructor(<?php echo json_encode($i); ?>)' title="Edit Profile">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this instructor and their user account?')">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="instructor_id" value="<?php echo $i['instructor_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-light border text-danger rounded-pill">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5>Add Instructor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        <small><i class="fas fa-info-circle"></i> Login Credentials will be generated automatically: <b>Username = ID</b> and <b>Password = Birthday (MM/DD/YYYY)</b>.</small>
                    </div>
                    <div class="mb-3">
                        <label>Birth Date</label>
                        <input type="date" name="date_of_birth" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" class="form-control">
                        </div>
                        <div class="mb-3">
                        <label class="form-label">Diploma Program</label>
                        <select name="dept_id" class="form-select" required>
                            <option value="">-- Select Diploma Program --</option>
                            <?php foreach ($departments_list as $d): ?>
                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    </div>
                    <div class="mb-3">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="instructor_id" id="edit_instructor_id">
            <div class="modal-content">
                <div class="modal-header"><h5>Edit Instructor Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label>Instructor ID</label><input type="text" name="instructor_id_no" id="edit_instructor_id_no" class="form-control" required></div>
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
                        <div class="col-md-6 mb-3">
                            <label>Specialization</label>
                            <input type="text" name="specialization" id="edit_specialization" class="form-control">
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
                    <div class="mb-3"><label>Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option><option value="inactive">Inactive</option>
                        </select>
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

<!-- Removing legacy datalist -->

<script>
let instructorModal;
document.addEventListener('DOMContentLoaded', function() {
    instructorModal = new bootstrap.Modal(document.getElementById('editModal'));
});

function editInstructor(data) {
    document.getElementById('edit_instructor_id').value = data.instructor_id;
    document.getElementById('edit_instructor_id_no').value = data.instructor_id_no;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_date_of_birth').value = data.date_of_birth;
    document.getElementById('edit_dept_id').value = data.dept_id || '';
    document.getElementById('edit_specialization').value = data.specialization || '';
    document.getElementById('edit_contact_number').value = data.contact_number || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_status').value = data.status;
    instructorModal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
