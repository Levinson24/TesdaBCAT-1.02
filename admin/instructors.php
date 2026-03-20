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

// === Premium Styles ===
?>
<style>
    .premium-card {
        border-radius: 1rem;
    }
    .bg-dark-navy {
        background-color: #002366 !important;
    }
    .instructors-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
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
    /* Premium Action Buttons */
    .btn-premium-edit {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 1.2rem;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50px;
        color: #334155 !important;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        text-decoration: none !important;
        cursor: pointer;
    }
    .btn-premium-edit:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e0;
        color: #1e293b !important;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }
    .btn-premium-edit i { color: #2563eb; margin-right: 0.5rem; }

    .btn-premium-delete {
        width: 36px; height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50%;
        color: #ef4444 !important;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border: none;
        cursor: pointer;
    }
    .btn-premium-delete:hover {
        background-color: #fef2f2;
        border-color: #fecaca;
        color: #dc2626 !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
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
    .status-pill-active { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .status-pill-inactive { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; }

    .btn-premium-view {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 1.2rem;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50px;
        color: #334155 !important;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        text-decoration: none !important;
        cursor: pointer;
    }
    .btn-premium-view:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e0;
        color: #1e293b !important;
        transform: translateY(-1px);
    }
    .btn-premium-view i { color: #2563eb; margin-right: 0.5rem; }

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
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-chalkboard-teacher me-2 text-info"></i> Faculty & Instructor Registry
        </h5>
        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-1"></i> Add Instructor
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 instructors-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">INSTRUCTOR ID</th>
                        <th>FACULTY IDENTITY</th>
                        <th>DEPARTMENT</th>
                        <th>SPECIALIZATION</th>
                        <th class="text-center">LOAD</th>
                        <th class="text-end pe-4">STATUS</th>
                        <th class="text-end pe-4">ACTIONS</th>
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
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="action-container">
                                <button class="btn-premium-view" onclick='viewInstructor(<?php echo json_encode($i); ?>)'>
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn-premium-edit" onclick='editInstructor(<?php echo json_encode($i); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this instructor?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="instructor_id" value="<?php echo $i['instructor_id']; ?>">
                                    <button type="submit" class="btn-premium-delete">
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

<!-- View Profile Modal -->
<div class="modal fade" id="viewModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark-navy text-white py-3 px-4 border-0 rounded-top-4">
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
                            <div class="col-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Account Status</div>
                                    <div class="profile-info-value" id="disp_status"></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Current Load</div>
                                    <div class="profile-info-value fw-bold text-info" id="disp_load"></div>
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
let instructorModal;
document.addEventListener('DOMContentLoaded', function() {
    instructorModal = new bootstrap.Modal(document.getElementById('editModal'));
});

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
    document.getElementById('disp_load').innerText = (data.total_classes || '0') + ' Sections';
    
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
