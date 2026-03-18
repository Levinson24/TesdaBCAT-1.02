<?php
/**
 * Admin - User Management  
 * TESDA-BCAT Grade Management System
 * FIXED VERSION - No warnings
 */

// === STEP 1: Load dependencies (no output) ===
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');
$conn = getDBConnection();
$error = '';

$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments ORDER BY title_diploma_program");
$departments_list = [];
while ($d = $dept_res->fetch_assoc())
    $departments_list[] = $d;

$prog_res = $conn->query("SELECT p.program_id, p.program_name, p.dept_id FROM programs p ORDER BY p.program_name");
$programs_list = [];
while ($p = $prog_res->fetch_assoc())
    $programs_list[] = $p;

// === STEP 2: Process forms BEFORE any HTML output ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('users.php', 'Invalid security token. Please try again.', 'danger');
    }
    switch ($_POST['action']) {
        case 'create':
            $role = sanitizeInput($_POST['role'] ?? 'student');
            $status = sanitizeInput($_POST['status'] ?? 'active');
            $firstName = sanitizeInput($_POST['first_name'] ?? 'New');
            $lastName = sanitizeInput($_POST['last_name'] ?? 'User');
            $middleName = sanitizeInput($_POST['middle_name'] ?? '');
            $dob = sanitizeInput($_POST['date_of_birth'] ?? '');
            $department = sanitizeInput($_POST['department'] ?? ''); // Standardized to dept_id below
            $specialization = sanitizeInput($_POST['specialization'] ?? '');
            $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $deptId = !empty($_POST['dept_id']) ? intval($_POST['dept_id']) : null;
            $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;

            if ($role === 'student' || $role === 'instructor') {
                $username = ($role === 'student') ? generateNextID('student') : generateNextID('instructor');
                $formatted_dob = date('m/d/Y', strtotime($dob));
                $password = hashPassword($formatted_dob);
            }
            else {
                $username = sanitizeInput($_POST['username'] ?? '');
                $password = hashPassword($_POST['password'] ?? '');
            }

            // Check for duplicate username
            $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'Username/ID already exists. Please choose another or check if profile exists.';
                $check->close();
                break;
            }
            $check->close();

            $stmt = $conn->prepare("INSERT INTO users (username, password, role, status, dept_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $username, $password, $role, $status, $deptId);

            if ($stmt->execute()) {
                $userId = $stmt->insert_id;

                // Create role-specific profile if needed
                if ($role === 'student' && !empty($firstName) && !empty($lastName)) {
                    $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_no, first_name, last_name, middle_name, date_of_birth, dept_id, program_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    $stmt2->bind_param("isssssii", $userId, $username, $firstName, $lastName, $middleName, $dob, $deptId, $programId);
                    $stmt2->execute();
                    $stmt2->close();
                }
                elseif ($role === 'instructor' && !empty($firstName) && !empty($lastName)) {
                    $stmt2 = $conn->prepare("INSERT INTO instructors (user_id, instructor_id_no, first_name, last_name, middle_name, date_of_birth, dept_id, specialization, contact_number, email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    $stmt2->bind_param("isssssisss", $userId, $username, $firstName, $lastName, $middleName, $dob, $deptId, $specialization, $contactNumber, $email);
                    $stmt2->execute();
                    $stmt2->close();
                }

                $stmt->close();
                logAudit(getCurrentUserId(), 'CREATE', 'users', $userId, null, "Created new user: $username ($role)");
                redirectWithMessage('users.php', 'User and profile created successfully', 'success');
            }
            else {
                $error = 'Failed to create user: ' . $conn->error;
            }
            break;

        case 'update':
            $userId = intval($_POST['user_id'] ?? 0);
            $username = sanitizeInput($_POST['username'] ?? '');
            $role = sanitizeInput($_POST['role'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? '');
            $deptId = !empty($_POST['dept_id']) ? intval($_POST['dept_id']) : null;
            $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;

            if (!empty($_POST['password'])) {
                $password = hashPassword($_POST['password']);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ?, status = ?, dept_id = ? WHERE user_id = ?");
                $stmt->bind_param("ssssii", $username, $password, $role, $status, $deptId, $userId);
            }
            else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, status = ?, dept_id = ? WHERE user_id = ?");
                $stmt->bind_param("sssii", $username, $role, $status, $deptId, $userId);
            }

            if ($stmt->execute()) {
                $stmt->close();

                // Keep role-specific profiles in sync if dept_id or program_id changes
                if ($role === 'student') {
                    $sync = $conn->prepare("UPDATE students SET dept_id = ?, program_id = ? WHERE user_id = ?");
                    $sync->bind_param("iii", $deptId, $programId, $userId);
                    $sync->execute();
                    $sync->close();
                }
                elseif ($role === 'instructor' || $role === 'dept_head') {
                    $sync = $conn->prepare("UPDATE instructors SET dept_id = ? WHERE user_id = ?");
                    $sync->bind_param("ii", $deptId, $userId);
                    $sync->execute();
                    $sync->close();
                }

                logAudit(getCurrentUserId(), 'UPDATE', 'users', $userId, null, "Updated user: $username ($role)");
                redirectWithMessage('users.php', 'User updated successfully', 'success');
            }
            else {
                $error = 'Failed to update user: ' . $conn->error;
            }
            break;

        case 'delete':
            $userId = intval($_POST['user_id'] ?? 0);

            if ($userId !== getCurrentUserId()) {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);

                if ($stmt->execute()) {
                    $stmt->close();
                    logAudit(getCurrentUserId(), 'DELETE', 'users', $userId, null, "Deleted user ID: $userId");
                    redirectWithMessage('users.php', 'User deleted successfully', 'success');
                }
                else {
                    $error = 'Failed to delete user: ' . $conn->error;
                }
            }
            else {
                $error = 'Cannot delete your own account';
            }
            break;
    }
}

// === STEP 3: Fetch data ===
$users = $conn->query("
    SELECT u.*, 
           CASE 
               WHEN u.role = 'student' THEN COALESCE(CONCAT(s.first_name, ' ', s.last_name), u.username)
               WHEN u.role = 'instructor' THEN COALESCE(CONCAT(i.first_name, ' ', i.last_name), u.username)
               ELSE u.username
           END as display_name,
           d.title_diploma_program as dept_name,
           s.program_id
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN instructors i ON u.user_id = i.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.created_at DESC
    LIMIT 500
");

// === STEP 4: NOW output HTML ===
$pageTitle = 'Manage Users';
require_once '../includes/header.php';
?>

<?php if (!empty($error)): ?>
    <?php echo showError($error); ?>
<?php
endif; ?>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-primary">
            <i class="fas fa-user-shield me-2 text-accent-indigo"></i> System User Registry
        </h5>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus-circle me-2"></i> Add New User
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-mobile-card align-middle overflow-hidden mb-0" id="usersTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Account Identity</th>
                        <th>Role & Level</th>
                        <th>Status & Activity</th>
                        <th class="text-end pe-4">Control Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4" data-label="ID">
                            <span class="text-muted small">#<?php echo htmlspecialchars($user['user_id'] ?? ''); ?></span>
                        </td>
                        <td data-label="Account Identity">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px;">
                                    <i class="fas fa-user-circle fa-lg"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['display_name'] ?? 'N/A'); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Role & Level">
                            <?php
    $roleColors = [
        'admin' => 'bg-danger bg-opacity-10 text-danger',
        'registrar' => 'bg-primary bg-opacity-10 text-primary',
        'instructor' => 'bg-info bg-opacity-10 text-info',
        'student' => 'bg-success bg-opacity-10 text-success',
        'dept_head' => 'bg-warning bg-opacity-10 text-warning',
        'registrar_staff' => 'bg-indigo bg-opacity-10 text-indigo'
    ];
    $role = $user['role'] ?? 'student';
    $badgeStyle = $roleColors[$role] ?? 'bg-secondary bg-opacity-10 text-secondary';
?>
                            <span class="badge rounded-pill <?php echo $badgeStyle; ?> px-3">
                                <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                            </span>
                            <?php if (!empty($user['dept_name'])): ?>
                                <div class="mt-1 small text-muted"><i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($user['dept_name']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Current Status/Activity">
                            <div id="user-status-<?php echo $user['user_id']; ?>">
                                <?php 
                                    $status = $user['status'] ?? 'inactive'; 
                                    
                                    // Determine Online/Offline status
                                    $isOnline = false;
                                    $sessionDuration = '';
                                    
                                    if (!empty($user['last_activity'])) {
                                        $lastActivityTime = strtotime($user['last_activity']);
                                        
                                        // Consider online if active in the last 5 minutes (300 seconds)
                                        if ((time() - $lastActivityTime) <= 300) {
                                            $isOnline = true;
                                            
                                            if (!empty($user['session_start'])) {
                                                $sessionStartTime = strtotime($user['session_start']);
                                                // Calculate H:M:S duration
                                                $durationSecs = time() - $sessionStartTime;
                                                $hours = floor($durationSecs / 3600);
                                                $minutes = floor(($durationSecs % 3600) / 60);
                                                $seconds = $durationSecs % 60;
                                                $sessionDuration = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
                                            }
                                        }
                                    }
                                ?>
                                <div class="mb-1">
                                    <span class="badge rounded-pill <?php echo $status === 'active' ? 'bg-success' : 'bg-secondary'; ?> bg-opacity-10 text-<?php echo $status === 'active' ? 'success' : 'secondary'; ?> px-2 py-1">
                                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> Acc: <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                                
                                <?php if ($isOnline): ?>
                                    <span class="badge bg-success rounded-pill px-2 py-1 shadow-sm">
                                        <i class="fas fa-signal me-1"></i> Online (<?php echo $sessionDuration; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border border-secondary border-opacity-25 rounded-pill px-2 py-1">
                                        <i class="fas fa-bed me-1"></i> Offline
                                    </span>
                                    <div class="mt-1 small text-muted" style="font-size: 0.70rem;">
                                        <?php echo !empty($user['last_login']) ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never logged in'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <button class="btn btn-sm btn-light border text-primary rounded-pill me-1" onclick='editUser(<?php echo json_encode([
        "user_id" => $user["user_id"],
        "username" => $user["username"],
        "role" => $user["role"],
        "status" => $user["status"],
        "dept_id" => $user["dept_id"],
        "program_id" => $user["program_id"] ?? ""
    ]); ?>)'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if ($user['user_id'] !== getCurrentUserId()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirmDelete('Are you sure you want to remove this user?');">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id'] ?? ''); ?>">
                                <button type="submit" class="btn btn-sm btn-light border text-danger rounded-pill">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                            <?php
    endif; ?>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" autocomplete="off">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="add_role" class="form-select" required onchange="toggleAddFields()">
                            <option value="student">Student</option>
                            <option value="instructor">Instructor</option>
                            <option value="registrar">Head Registrar</option>
                            <option value="registrar_staff">Registrar Staff</option>
                            <option value="admin">Admin</option>
                            <option value="dept_head">Diploma Program Head</option>
                        </select>
                    </div>
                    <div id="dept_selector" class="mb-3" style="display:none;">
                        <label class="form-label" id="dept_label">Diploma Program</label>
                        <select name="dept_id" id="add_dept_id" class="form-select" onchange="filterAddPrograms()">
                            <option value="">-- Select Diploma Program --</option>
                            <?php foreach ($departments_list as $d): ?>
                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div id="program_selector" class="mb-3" style="display:none;">
                        <label class="form-label">Specific Program (Course)</label>
                        <select name="program_id" id="add_program_id" class="form-select">
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs_list as $p): ?>
                                <option value="<?php echo $p['program_id']; ?>" data-dept="<?php echo $p['dept_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div id="standard_creds">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="add_username" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="add_password" class="form-control">
                        </div>
                    </div>
                    <div id="auto_creds" class="alert alert-info py-2">
                        <small><i class="fas fa-info-circle"></i> Login: <b>Username = ID</b> and <b>Password = Birthday (MM/DD/YYYY)</b>.</small>
                    </div>
                    <div id="profile_name_fields" style="display:none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" id="add_first_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="add_last_name" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3" id="dob_field">
                                <label class="form-label">Birth Date</label>
                                <input type="date" name="date_of_birth" id="add_dob" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div id="instructor_only_fields" style="display:none;">
                        <div class="row">
                            <!-- Removing legacy department text field, using dept_id instead -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specialization</label>
                                <input type="text" name="specialization" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" autocomplete="off">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="instructor">Instructor</option>
                            <option value="registrar">Head Registrar</option>
                            <option value="registrar_staff">Registrar Staff</option>
                            <option value="admin">Admin</option>
                            <option value="dept_head">Diploma Program Head</option>
                        </select>
                    </div>
                    <div id="edit_jurisdiction" class="mb-3" style="display:none;">
                        <label class="form-label">Diploma Program</label>
                        <select name="dept_id" id="edit_dept_id" class="form-select" onchange="filterEditPrograms()">
                            <option value="">-- Select Diploma Program --</option>
                            <?php foreach ($departments_list as $d): ?>
                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div id="edit_program_selector" class="mb-3" style="display:none;">
                        <label class="form-label">Specific Program (Course)</label>
                        <select name="program_id" id="edit_program_id" class="form-select">
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs_list as $p): ?>
                                <option value="<?php echo $p['program_id']; ?>" data-dept="<?php echo $p['dept_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAddFields() {
    const role = document.getElementById('add_role').value;
    const standard = document.getElementById('standard_creds');
    const auto = document.getElementById('auto_creds');
    const nameFields = document.getElementById('profile_name_fields');
    const instructorFields = document.getElementById('instructor_only_fields');
    const deptSelector = document.getElementById('dept_selector');
    const deptLabel = document.getElementById('dept_label');
    
    // Reset
    deptSelector.style.display = 'none';

    if (role === 'student' || role === 'instructor') {
        standard.style.display = 'none';
        auto.style.display = 'block';
        nameFields.style.display = 'block';
        deptSelector.style.display = 'block';
        document.getElementById('program_selector').style.display = (role === 'student' ? 'block' : 'none');
        deptLabel.innerText = 'Diploma Program';
        document.getElementById('add_username').required = false;
        document.getElementById('add_password').required = false;
        document.getElementById('add_first_name').required = true;
        document.getElementById('add_last_name').required = true;
        document.getElementById('add_dob').required = true;
        document.getElementById('add_dept_id').required = true;
        
        if (role === 'instructor') {
            instructorFields.style.display = 'block';
        } else {
            instructorFields.style.display = 'none';
        }
    } else {
        standard.style.display = 'block';
        auto.style.display = 'none';
        nameFields.style.display = 'none';
        instructorFields.style.display = 'none';
        document.getElementById('add_username').required = true;
        document.getElementById('add_password').required = true;
        document.getElementById('add_first_name').required = false;
        document.getElementById('add_last_name').required = false;
        document.getElementById('add_dob').required = false;
        document.getElementById('add_dept_id').required = false;

        if (role === 'dept_head' || role === 'registrar_staff') {
            deptSelector.style.display = 'block';
            deptLabel.innerText = role === 'dept_head' ? 'Jurisdiction (Diploma Program)' : 'Designated Diploma Program';
            document.getElementById('add_dept_id').required = true;
        }
    }
}

function toggleEditFields() {
    const role = document.getElementById('edit_role').value;
    const editJurisdiction = document.getElementById('edit_jurisdiction');
    const editProgram = document.getElementById('edit_program_selector');

    if (role === 'dept_head' || role === 'instructor' || role === 'student' || role === 'registrar_staff') {
        editJurisdiction.style.display = 'block';
        if (role === 'student') {
            editProgram.style.display = 'block';
            filterEditPrograms();
        } else {
            editProgram.style.display = 'none';
        }
    } else {
        editJurisdiction.style.display = 'none';
        editProgram.style.display = 'none';
    }
}

function filterAddPrograms() {
    const deptId = document.getElementById('add_dept_id').value;
    const progSelect = document.getElementById('add_program_id');
    const options = progSelect.options;
    
    let firstMatch = "";
    for (let i = 1; i < options.length; i++) {
        if (!deptId || options[i].getAttribute('data-dept') == deptId) {
            options[i].style.display = 'block';
            if (!firstMatch) firstMatch = options[i].value;
        } else {
            options[i].style.display = 'none';
        }
    }
}

function filterEditPrograms() {
    const deptId = document.getElementById('edit_dept_id').value;
    const progSelect = document.getElementById('edit_program_id');
    const options = progSelect.options;
    
    for (let i = 1; i < options.length; i++) {
        if (!deptId || options[i].getAttribute('data-dept') == deptId) {
            options[i].style.display = 'block';
        } else {
            options[i].style.display = 'none';
        }
    }
}

// Run on load
let userModal;
document.addEventListener('DOMContentLoaded', () => {
    toggleAddFields();
    document.getElementById('edit_role').addEventListener('change', toggleEditFields);
    userModal = new bootstrap.Modal(document.getElementById('editUserModal'));
});

function editUser(user) {
    document.getElementById('edit_user_id').value = user.user_id || '';
    document.getElementById('edit_username').value = user.username || '';
    document.getElementById('edit_role').value = user.role || 'student';
    document.getElementById('edit_status').value = user.status || 'active';
    document.getElementById('edit_dept_id').value = user.dept_id || '';
    document.getElementById('edit_program_id').value = user.program_id || '';
    
    toggleEditFields();
    
    userModal.show();
}

// Real-time status update polling
let isUpdatingStatus = false;
function updateUserStatuses() {
    if (isUpdatingStatus) return;
    isUpdatingStatus = true;

    $.ajax({
        url: 'ajax/get_user_status.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            for (const userId in data) {
                const user = data[userId];
                const container = document.getElementById('user-status-' + userId);
                if (!container) continue;

                let html = '';
                // Account Status Badge
                html += `<div class="mb-1">
                    <span class="badge rounded-pill ${user.status === 'active' ? 'bg-success' : 'bg-secondary'} bg-opacity-10 text-${user.status === 'active' ? 'success' : 'secondary'} px-2 py-1">
                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> Acc: ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                    </span>
                </div>`;

                // Online/Offline Status
                if (user.isOnline) {
                    html += `<span class="badge bg-success rounded-pill px-2 py-1 shadow-sm">
                        <i class="fas fa-signal me-1"></i> Online (${user.sessionDuration})
                    </span>`;
                } else {
                    html += `<span class="badge bg-light text-muted border border-secondary border-opacity-25 rounded-pill px-2 py-1">
                        <i class="fas fa-bed me-1"></i> Offline
                    </span>
                    <div class="mt-1 small text-muted" style="font-size: 0.70rem;">
                        ${user.lastLogin}
                    </div>`;
                }

                container.innerHTML = html;
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to update user statuses:', error);
        },
        complete: function() {
            isUpdatingStatus = false;
        }
    });
}


// Start polling every 10 seconds
setInterval(updateUserStatuses, 10000);
</script>

<?php require_once '../includes/footer.php'; ?>
