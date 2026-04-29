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

            // Handle profile image upload
            $profileImage = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['profile_image'], '../uploads/profile_pics/', ['jpg', 'jpeg', 'png']);
                if ($uploadResult[0]) {
                    $profileImage = $uploadResult[2];
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, status, dept_id, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssis", $username, $password, $role, $status, $deptId, $profileImage);

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

            // Handle profile image upload
            $profileImage = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['profile_image'], '../uploads/profile_pics/', ['jpg', 'jpeg', 'png']);
                if ($uploadResult[0]) {
                    $profileImage = $uploadResult[2];
                    
                    // Get old image to delete
                    $oldStmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
                    $oldStmt->bind_param("i", $userId);
                    $oldStmt->execute();
                    $oldRes = $oldStmt->get_result()->fetch_assoc();
                    $oldImage = $oldRes['profile_image'] ?? null;
                    $oldStmt->close();
                }
            }

            if (!empty($_POST['password'])) {
                $password = hashPassword($_POST['password']);
                if ($profileImage) {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ?, status = ?, dept_id = ?, profile_image = ?, reset_requested = 0 WHERE user_id = ?");
                    $stmt->bind_param("ssssisi", $username, $password, $role, $status, $deptId, $profileImage, $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ?, status = ?, dept_id = ?, reset_requested = 0 WHERE user_id = ?");
                    $stmt->bind_param("ssssii", $username, $password, $role, $status, $deptId, $userId);
                }
            }
            else {
                if ($profileImage) {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, status = ?, dept_id = ?, profile_image = ?, reset_requested = 0 WHERE user_id = ?");
                    $stmt->bind_param("sssisi", $username, $role, $status, $deptId, $profileImage, $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, status = ?, dept_id = ?, reset_requested = 0 WHERE user_id = ?");
                    $stmt->bind_param("sssii", $username, $role, $status, $deptId, $userId);
                }
            }

            if ($stmt->execute()) {
                if (isset($oldImage) && $oldImage && file_exists('../uploads/profile_pics/' . $oldImage)) {
                    @unlink('../uploads/profile_pics/' . $oldImage);
                }
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
           s.program_id,
           (SELECT al.action FROM audit_logs al 
            WHERE al.user_id = u.user_id 
            AND (al.action LIKE 'PRINT_%' OR al.action LIKE 'DOWNLOAD_%' OR al.action = 'VIEW_COR')
            ORDER BY al.log_id DESC LIMIT 1) as last_doc_action,
           (SELECT al.created_at FROM audit_logs al 
            WHERE al.user_id = u.user_id 
            AND (al.action LIKE 'PRINT_%' OR al.action LIKE 'DOWNLOAD_%' OR al.action = 'VIEW_COR')
            ORDER BY al.log_id DESC LIMIT 1) as last_doc_time
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN instructors i ON u.user_id = i.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.created_at DESC
");

// === STEP 4: NOW output HTML ===
$pageTitle = 'Manage Users';
require_once '../includes/header.php';
?>
<style>
    .premium-card {
        border-radius: 1rem;
    }
    .bg-dark-navy {
        background-color: #0f172a !important;
    }
    @keyframes pulse-red {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 53, 64, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .pulse-badge {
        animation: pulse-red 2s infinite;
    }
    .users-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
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
</style>

<?php if (!empty($error)): ?>
    <?php echo showError($error); ?>
<?php
endif; ?>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-user-shield me-2 text-info"></i> System User Registry
        </h5>
        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus-circle me-1"></i> Add User
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table" id="usersTable">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>ACCOUNT IDENTITY</th>
                        <th>ROLE & DESIGNATION</th>
                        <th>STATUS & ACTIVITY</th>
                        <th class="text-end pe-4">ACTIONS</th>
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
                                <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; overflow: hidden;">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="../uploads/profile_pics/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-lg"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="identity-name"><?php echo htmlspecialchars($user['display_name'] ?? 'N/A'); ?></span>
                                    <span class="identity-meta">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></span>
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
                                <div class="mt-1 text-muted" style="font-size: 0.65rem;">
                                    <i class="fas fa-desktop me-1"></i> IP: <?php echo htmlspecialchars($user['last_ip'] ?? 'N/A'); ?>
                                </div>
                                <?php if (!empty($user['reset_requested'])): ?>
                                    <div class="mb-2 text-start">
                                        <span class="badge bg-danger pulse-badge rounded-pill px-2 py-1 shadow-sm" style="font-size: 0.65rem;">
                                            <i class="fas fa-exclamation-triangle me-1"></i> RESET REQUESTED
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-1 text-primary" style="font-size: 0.65rem; font-weight: 500;">
                                    <i class="fas fa-file-alt me-1"></i> Doc: 
                                    <span class="doc-activity">
                                        <?php 
                                            if ($user['last_doc_action']) {
                                                $action = str_replace('_', ' ', $user['last_doc_action']);
                                                $time = strtotime($user['last_doc_time']);
                                                $diff = time() - $time;
                                                if ($diff < 60) echo $action . ' just now';
                                                elseif ($diff < 3600) echo $action . ' ' . floor($diff/60) . 'm ago';
                                                elseif ($diff < 86400) echo $action . ' ' . floor($diff/3600) . 'h ago';
                                                else echo $action . ' on ' . date('M d', $time);
                                            } else {
                                                echo 'No activity';
                                            }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn-premium-edit" onclick='editUser(<?php echo json_encode([
                                    "user_id" => $user["user_id"],
                                    "username" => $user["username"],
                                    "role" => $user["role"],
                                    "status" => $user["status"],
                                    "dept_id" => $user["dept_id"],
                                    "program_id" => $user["program_id"] ?? "",
                                    "profile_image" => $user["profile_image"] ?? ""
                                ]); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if ($user['user_id'] !== getCurrentUserId()): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirmDelete('Are you sure you want to remove this user?');">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id'] ?? ''); ?>">
                                    <button type="submit" class="btn-premium-delete">
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" autocomplete="off" enctype="multipart/form-data">
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
                    <div class="mb-3">
                        <label class="form-label">Profile Picture (Optional)</label>
                        <input type="file" name="profile_image" class="form-control" accept="image/jpeg,image/png">
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
            <form method="POST" autocomplete="off" enctype="multipart/form-data">
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
                    <div class="mb-3">
                        <label class="form-label d-block">Current Profile Picture</label>
                        <div id="edit_image_preview" class="mb-2" style="width: 80px; height: 80px; border-radius: 12px; overflow: hidden; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user fa-2x text-light"></i>
                        </div>
                        <label class="form-label">Update Picture (Optional)</label>
                        <input type="file" name="profile_image" class="form-control" accept="image/jpeg,image/png">
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
    
    // Update image preview
    const preview = document.getElementById('edit_image_preview');
    if (user.profile_image) {
        preview.innerHTML = `<img src="../uploads/profile_pics/${user.profile_image}" style="width: 100%; height: 100%; object-fit: cover;">`;
    } else {
        preview.innerHTML = `<i class="fas fa-user fa-2x text-light"></i>`;
    }
    
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

                html += `<div class="mt-1 text-muted" style="font-size: 0.65rem;">
                    <i class="fas fa-desktop me-1"></i> IP: ${user.lastIP}
                </div>
                <div class="mt-1 text-primary" style="font-size: 0.65rem; font-weight: 500;">
                    <i class="fas fa-file-alt me-1"></i> Doc: ${user.lastDoc}
                </div>`;

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
