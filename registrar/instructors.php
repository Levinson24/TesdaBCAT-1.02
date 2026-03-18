<?php
/**
 * Instructor Management
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff']);
$conn = getDBConnection();

$userRole = getCurrentUserRole();
$userProfile = getUserProfile(getCurrentUserId(), $userRole);
$deptId = $userProfile['dept_id'] ?? 0;
$isStaff = ($userRole === 'registrar_staff');

// Handle form submissions BEFORE header output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('instructors.php', 'Invalid security token. Please try again.', 'danger');
    }

    // Enforce department restriction for Registrar Staff
    if ($isStaff) {
        $_POST['dept_id'] = $deptId;
        
        // If updating or deleting, verify the instructor belongs to the staff's department
        if (isset($_POST['instructor_id'])) {
            $checkStmt = $conn->prepare("SELECT dept_id FROM instructors WHERE instructor_id = ?");
            $checkStmt->bind_param("i", $_POST['instructor_id']);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result()->fetch_assoc();
            if ($checkRes && $checkRes['dept_id'] != $deptId) {
                redirectWithMessage('instructors.php', 'Unauthorized: Instructor belongs to another department.', 'danger');
            }
        }
    }
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
            logAudit(getCurrentUserId(), 'CREATE', 'users', $userId, null, "Created instructor profile: $instructorIdNo ($firstName $lastName)");
            redirectWithMessage('instructors.php', 'Instructor created successfully. Login: ID=' . $instructorIdNo . ', Pwd=' . $formatted_dob, 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $instructorId = intval($_POST['instructor_id']);
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $dob = sanitizeInput($_POST['date_of_birth']);
        $deptId = intval($_POST['dept_id'] ?? 0);
        $specialization = sanitizeInput($_POST['specialization']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $email = sanitizeInput($_POST['email']);
        $status = sanitizeInput($_POST['status']);

        $stmt = $conn->prepare("UPDATE instructors SET first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, dept_id = ?, specialization = ?, contact_number = ?, email = ?, status = ? WHERE instructor_id = ?");
        $stmt->bind_param("ssssissssi", $firstName, $lastName, $middleName, $dob, $deptId, $specialization, $contactNumber, $email, $status, $instructorId);
        if ($stmt->execute()) {
            logAudit(getCurrentUserId(), 'UPDATE', 'instructors', $instructorId, null, "Updated instructor profile: $firstName $lastName");
            redirectWithMessage('instructors.php', 'Instructor updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        if (getCurrentUserRole() !== 'registrar') {
            redirectWithMessage('instructors.php', 'Unauthorized: Only the Head Registrar can delete instructor profiles.', 'danger');
        }
        $instructorId = intval($_POST['instructor_id']);
        $stmt = $conn->prepare("SELECT user_id FROM instructors WHERE instructor_id = ?");
        $stmt->bind_param("i", $instructorId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            $userId = $u['user_id'];
            $conn->query("DELETE FROM users WHERE user_id = $userId");
            logAudit(getCurrentUserId(), 'DELETE', 'users', $userId, null, "Deleted instructor profile ID: $instructorId (User ID: $userId)");
            redirectWithMessage('instructors.php', 'Instructor deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Instructors';
require_once '../includes/header.php';

$deptWhere = $isStaff ? " WHERE dept_id = $deptId" : " WHERE status = 'active'";
$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments $deptWhere ORDER BY title_diploma_program ASC");
$departments_list = [];
while ($d = $dept_res->fetch_assoc())
    $departments_list[] = $d;

$instructorWhere = $isStaff ? " WHERE i.dept_id = $deptId" : "";
$instructors = $conn->query("
    SELECT i.*, u.username, d.title_diploma_program as dept_name 
    FROM instructors i 
    JOIN users u ON i.user_id = u.user_id 
    LEFT JOIN departments d ON i.dept_id = d.dept_id
    $instructorWhere
    ORDER BY i.created_at DESC
");
?>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Instructor Management</h5>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Add Instructor
        </button>
    </div>
    <div class="card-body">
        <table class="table table-hover data-table">
            <thead>
                <tr><th>Name</th><th>Program/Specialization</th><th>Contact</th><th>Email</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($i = $instructors->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars(($i['first_name'] ?? '') . (($i['middle_name'] ?? '') ? ' ' . $i['middle_name'] : '') . ' ' . ($i['last_name'] ?? '')); ?></td>
                    <td>
                        <div><b>Program:</b> <?php echo htmlspecialchars($i['dept_name'] ?? 'Unassigned'); ?></div>
                        <div class="small text-muted"><b>Spec:</b> <?php echo htmlspecialchars($i['specialization'] ?? 'N/A'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($i['contact_number'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($i['email'] ?? 'N/A'); ?></td>
                    <td><span class="badge bg-<?php echo $i['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($i['status']); ?></span></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-info" onclick='editInstructor(<?php echo json_encode($i); ?>)' title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (getCurrentUserRole() === 'registrar'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this instructor? This will also delete their user account.');">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="instructor_id" value="<?php echo $i['instructor_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Delete">
                                <i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i>
                            </button>
                        </form>
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

<!-- Add Modal -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrfField(); ?>
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
                        <div class="col-md-6 mb-3">
                            <label>Diploma Program</label>
                            <select name="dept_id" class="form-select" required>
                                <option value="">-- Select Diploma Program --</option>
                                <?php foreach ($departments_list as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
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
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="instructor_id" id="edit_instructor_id">
                <div class="modal-header">
                    <h5>Edit Instructor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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
                            <label>Diploma Program</label>
                            <select name="dept_id" id="edit_dept_id" class="form-select" required>
                                <option value="">-- Select Diploma Program --</option>
                                <?php foreach ($departments_list as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
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
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Removing legacy datalist -->

<script>
function editInstructor(data) {
    document.getElementById('edit_instructor_id').value = data.instructor_id;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_date_of_birth').value = data.date_of_birth;
    document.getElementById('edit_dept_id').value = data.dept_id || '';
    document.getElementById('edit_specialization').value = data.specialization || '';
    document.getElementById('edit_contact_number').value = data.contact_number || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_status').value = data.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
