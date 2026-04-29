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
        $formatted_dob = date('Ymd', strtotime($dob));
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
            $delUser = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delUser->bind_param("i", $userId);
            $delUser->execute();
            $delUser->close();
            
            logAudit(getCurrentUserId(), 'DELETE', 'instructors', $instructorId, null, "Deleted instructor profile (ID: $instructorId) and associated user account.");
            redirectWithMessage('instructors.php', 'Instructor and account deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Instructors';
require_once '../includes/header.php';

$instructors = $conn->query("
    SELECT i.*, u.username, u.profile_image, d.title_diploma_program as dept_name,
           (SELECT COUNT(*) FROM class_sections cs WHERE cs.instructor_id = i.instructor_id AND cs.status = 'active') as total_classes
    FROM instructors i
    JOIN users u ON i.user_id = u.user_id
    LEFT JOIN departments d ON i.dept_id = d.dept_id
    ORDER BY i.last_name, i.first_name
");

?>
<style>
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
            <i class="fas fa-chalkboard-teacher me-2 text-info"></i> Faculty & Instructor Registry
        </h5>
        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-1"></i> Add Instructor
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 instructors-table premium-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">INSTRUCTOR ID</th>
                        <th>FACULTY IDENTITY</th>
                        <th>DEPARTMENT</th>
                        <th>SPECIALIZATION</th>
                        <th class="text-center">LOAD</th>
                        <th class="text-end pe-4">STATUS</th>
                        <th class="text-end pe-4" style="min-width: 220px !important;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($i = $instructors->fetch_assoc()): ?>
                    <tr class="table-row-premium align-middle">
                        <td class="ps-4" data-label="Instructor ID">
                            <span class="fw-bold text-primary"><?php echo htmlspecialchars($i['instructor_id_no'] ?? 'N/A'); ?></span>
                        </td>
                        <td class="py-3" data-label="Faculty Identity">
                            <div class="d-flex align-items-center">
                                <div class="avatar-premium me-3 overflow-hidden d-flex align-items-center justify-content-center">
                                    <?php if (!empty($i['profile_image'])): ?>
                                        <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($i['profile_image']); ?>?v=<?php echo time(); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-chalkboard-teacher"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="identity-name"><?php echo htmlspecialchars($i['first_name'] . ' ' . $i['last_name']); ?></span>
                                    <span class="identity-meta"><?php echo htmlspecialchars($i['email'] ?? 'N/A'); ?></span>
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
                            <span class="status-pill <?php echo ($i['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <div class="status-dot" style="background: <?php echo ($i['status'] ?? 'active') === 'active' ? '#22c55e' : '#94a3b8'; ?>;"></div> <?php echo ucfirst($i['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4 py-3" data-label="Control Actions" style="min-width: 220px !important; white-space: nowrap !important; width: 220px !important;">
                            <div class="table-actions-v2" style="display: flex !important; justify-content: flex-end !important; align-items: center !important; gap: 1.5rem !important; flex-wrap: nowrap !important;">
                                <button class="btn-premium-view" style="flex-shrink: 0 !important;" onclick='viewInstructor(<?php echo json_encode($i); ?>)'>
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn-premium-edit" style="flex-shrink: 0 !important;" onclick='editInstructor(<?php echo json_encode($i); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: contents !important;" onsubmit="return confirm('Are you sure you want to delete this instructor?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="instructor_id" value="<?php echo $i['instructor_id']; ?>">
                                    <button type="submit" class="btn-premium-delete" style="flex-shrink: 0 !important; width: 38px !important; height: 38px !important;">
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

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off" class="w-100">
                <input type="hidden" name="action" value="create">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i>
                        <span>Register Instructor</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 mb-4 d-flex align-items-center gap-3 shadow-sm border-0" style="border-radius: 1rem; background: rgba(37, 99, 235, 0.05);">
                        <i class="fas fa-info-circle fa-2x text-primary"></i>
                        <small class="text-dark">Login Credentials will be generated automatically: <b>Username = ID</b> and <b>Password = Birthday (YYYYMMDD)</b>.</small>
                    </div>

                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-address-card me-2"></i>Personal Details</span>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>First Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="first_name" class="form-control" placeholder="Given Name" required>
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Middle Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="middle_name" class="form-control" placeholder="Optional">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Last Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="last_name" class="form-control" placeholder="Surname" required>
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Date of Birth</label>
                                <div class="input-wrapper">
                                    <input type="date" name="date_of_birth" class="form-control" required>
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Contact Number</label>
                                <div class="input-wrapper">
                                    <input type="text" name="contact_number" class="form-control" placeholder="09XX-XXX-XXXX">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Email Address</label>
                                <div class="input-wrapper">
                                    <input type="email" name="email" class="form-control" placeholder="instructor@example.com">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-chalkboard-teacher me-2"></i>Academic Assignment</span>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Diploma Program</label>
                                <div class="input-wrapper">
                                    <select name="dept_id" class="form-select" required>
                                        <option value="">-- Select Diploma Program --</option>
                                        <?php foreach ($departments_list as $d): ?>
                                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Specialization</label>
                                <div class="input-wrapper">
                                    <input type="text" name="specialization" class="form-control" placeholder="e.g. Database Systems">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-check-circle me-2"></i>Register Instructor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form method="POST" autocomplete="off" class="w-100">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="instructor_id" id="edit_instructor_id">
            <div class="modal-content border-0">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Instructor Profile</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-address-card me-2"></i>Personal Details</span>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>First Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Middle Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Last Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Date of Birth</label>
                                <div class="input-wrapper">
                                    <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Contact Number</label>
                                <div class="input-wrapper">
                                    <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Email Address</label>
                                <div class="input-wrapper">
                                    <input type="email" name="email" id="edit_email" class="form-control">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-chalkboard-teacher me-2"></i>Professional Info</span>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Instructor ID</label>
                                <div class="input-wrapper">
                                    <input type="text" name="instructor_id_no" id="edit_instructor_id_no" class="form-control" required>
                                    <i class="fas fa-id-card"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Diploma Program</label>
                                <div class="input-wrapper">
                                    <select name="dept_id" id="edit_dept_id" class="form-select" required>
                                        <option value="">-- Select Diploma Program --</option>
                                        <?php foreach ($departments_list as $d): ?>
                                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Specialization</label>
                                <div class="input-wrapper">
                                    <input type="text" name="specialization" id="edit_specialization" class="form-control">
                                    <i class="fas fa-cogs"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="premium-input-group">
                                <label>Status</label>
                                <div class="input-wrapper">
                                    <select name="status" id="edit_status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <i class="fas fa-toggle-on"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-sync me-2"></i>Update Profile</button>
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
