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
<style>
    .premium-card { border-radius: 1rem; }
    .bg-dark-navy { background-color: #002366 !important; }
    .instructors-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.70rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .instructors-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
        font-size: 0.85rem;
    }
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-pill-active { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
    .status-pill-inactive { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }

    /* Premium Action Buttons */
    .btn-premium-view, .btn-premium-edit, .btn-premium-delete {
        width: 32px; height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    .btn-premium-view {
        background-color: #f0f9ff;
        color: #0369a1 !important;
    }
    .btn-premium-view:hover {
        background-color: #0369a1;
        color: #fff !important;
        box-shadow: 0 4px 6px rgba(3, 105, 161, 0.2);
    }
    .btn-premium-edit {
        background-color: #eff6ff;
        color: #2563eb !important;
    }
    .btn-premium-edit:hover {
        background-color: #2563eb;
        color: #fff !important;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }
    .btn-premium-delete {
        background-color: #fef2f2;
        color: #ef4444 !important;
    }
    .btn-premium-delete:hover {
        background-color: #ef4444;
        color: #fff !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }

    /* Modal Profile Styles */
    .profile-info-label {
        font-weight: 600;
        color: #64748b;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.25rem;
    }
    .profile-info-value {
        font-size: 0.95rem;
        font-weight: 500;
        color: #1e293b;
        margin-bottom: 0;
    }
    .profile-item {
        background: #f8fafc;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        border: 1px solid #f1f5f9;
        height: 100%;
    }
    .profile-section-title {
        color: #0038A8;
        font-size: 0.9rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        margin-top: 1rem;
    }
    .profile-section-title i {
        width: 28px;
        height: 28px;
        background: rgba(79, 70, 229, 0.1);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        margin-right: 10px;
    }
</style>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-chalkboard-teacher me-2 text-warning"></i> Instructor Management
        </h5>
        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-1"></i> Add Instructor
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 instructors-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">NAME</th>
                        <th>PROGRAM / SPECIALIZATION</th>
                        <th>CONTACT</th>
                        <th>EMAIL</th>
                        <th>STATUS</th>
                        <th class="text-end pe-4">ACTIONS</th>
                    </tr>
                </thead>
            <tbody>
                <?php while ($i = $instructors->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4"><?php echo htmlspecialchars(($i['first_name'] ?? '') . (($i['middle_name'] ?? '') ? ' ' . $i['middle_name'] : '') . ' ' . ($i['last_name'] ?? '')); ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($i['dept_name'] ?? 'Unassigned'); ?></div>
                        <div class="small text-muted">Spec: <?php echo htmlspecialchars($i['specialization'] ?? 'N/A'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($i['contact_number'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($i['email'] ?? 'N/A'); ?></td>
                    <td>
                        <?php if (($i['status'] ?? 'active') === 'active'): ?>
                            <div class="status-pill status-pill-active">
                                <div class="status-dot" style="background: #22c55e;"></div> Active
                            </div>
                        <?php else: ?>
                            <div class="status-pill status-pill-inactive">
                                <div class="status-dot" style="background: #94a3b8;"></div> Inactive
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn-premium-view" onclick='viewInstructor(<?php echo json_encode($i); ?>)' title="View Full Profile">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-premium-edit" onclick='editInstructor(<?php echo json_encode($i); ?>)' title="Edit Profile">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if (getCurrentUserRole() === 'registrar'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this instructor? This will also delete their user account.');">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="instructor_id" value="<?php echo $i['instructor_id']; ?>">
                                <button type="submit" class="btn-premium-delete" title="Delete Profile">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                            <?php endif; ?>
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

<!-- Add Modal -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-chalkboard-teacher me-2 text-warning"></i>Add Instructor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                                <?php foreach ($departments_list as $dept): ?>                                    <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
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
                <div class="modal-footer bg-light border-0 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-plus-circle me-1"></i> Create Instructor
                    </button>
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
                <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2 text-warning"></i>Edit Instructor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                <div class="modal-footer bg-light border-0 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> Update Instructor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Profile Modal -->
<div class="modal fade" id="viewModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header gradient-navy text-white py-3 px-4 border-0 rounded-top-4">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-id-card text-info"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold" id="view_full_name">Instructor Profile</h5>
                        <small class="text-info opacity-75" id="view_id_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Personal Info -->
                    <div class="col-lg-6">
                        <h6 class="profile-section-title">
                            <i class="fas fa-user"></i> PERSONAL INFORMATION
                        </h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Full Name</div>
                                    <div class="profile-info-value" id="disp_full_name"></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Date of Birth</div>
                                    <div class="profile-info-value" id="disp_dob"></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Contact No.</div>
                                    <div class="profile-info-value" id="disp_contact"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Email Address</div>
                                    <div class="profile-info-value" id="disp_email"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Professional Info -->
                    <div class="col-lg-6">
                        <h6 class="profile-section-title">
                            <i class="fas fa-briefcase"></i> ACADEMIC ASSIGNMENT
                        </h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Diploma Program / Dept</div>
                                    <div class="profile-info-value fw-bold text-primary" id="disp_dept"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Specialization</div>
                                    <div class="profile-info-value" id="disp_specialization"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Account Status</div>
                                    <div class="profile-info-value" id="disp_status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3">
                <button class="btn btn-primary rounded-pill px-4" onclick="initiateEditFromView()">
                    <i class="fas fa-edit me-1"></i> Edit Profile
                </button>
                <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<script>
let currentInstructorData = null;

function viewInstructor(data) {
    currentInstructorData = data;
    document.getElementById('view_full_name').innerText = (data.first_name || '') + ' ' + (data.last_name || '');
    document.getElementById('view_id_no').innerText = 'ID: ' + (data.instructor_id_no || 'N/A');
    
    document.getElementById('disp_full_name').innerText = (data.first_name || '') + (data.middle_name ? ' ' + data.middle_name : '') + ' ' + (data.last_name || '');
    document.getElementById('disp_dob').innerText = data.date_of_birth ? new Date(data.date_of_birth).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    document.getElementById('disp_contact').innerText = data.contact_number || 'N/A';
    document.getElementById('disp_email').innerText = data.email || 'N/A';
    document.getElementById('disp_dept').innerText = data.dept_name || 'Unassigned';
    document.getElementById('disp_specialization').innerText = data.specialization || 'N/A';
    
    const status = data.status || 'active';
    const statusHTML = status === 'active' 
        ? '<span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><i class="fas fa-check-circle me-1"></i> Active</span>'
        : '<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3"><i class="fas fa-times-circle me-1"></i> Inactive</span>';
    document.getElementById('disp_status').innerHTML = statusHTML;
    
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

function initiateEditFromView() {
    if (currentInstructorData) {
        bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();
        editInstructor(currentInstructorData);
    }
}

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
