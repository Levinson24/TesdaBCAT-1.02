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
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, status, dept_id, email, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisss", $username, $password, $role, $status, $deptId, $email, $profileImage);

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

// === THE PERMANENT FIX FOR LINE 203 ===
$users = $conn->query("
    SELECT 
        u.*, 
        CASE 
            WHEN u.role = 'student' THEN CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))
            WHEN u.role = 'instructor' THEN CONCAT(COALESCE(i.first_name, ''), ' ', COALESCE(i.last_name, ''))
            ELSE u.username
        END as display_name,
        d.title_diploma_program as dept_name
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN instructors i ON u.user_id = i.user_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.created_at DESC
");
if (!$users) {
    $error = 'Database query error: ' . $conn->error;
}



// === STEP 4: NOW output HTML ===
$pageTitle = 'Manage Users';
require_once '../includes/header.php';
?>
<!-- Import Premium Typography -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Add User Modal (Moved to top for reliability) -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off" enctype="multipart/form-data">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title" id="addModalTitle">
                        <i class="fas fa-user-plus"></i>
                        <span>Register Student</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- SECTION: ROLE SELECTION (ALWAYS VISIBLE) -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="premium-input-group mb-0">
                                <label for="add_role">System Role / Designation</label>
                                <div class="input-wrapper">
                                    <select name="role" id="add_role" class="form-select" required onchange="toggleAddFields()">
                                        <option value="student">Student</option>
                                        <option value="instructor">Instructor</option>
                                        <option value="registrar">Head Registrar</option>
                                        <option value="registrar_staff">Clerk</option>
                                        <option value="admin">Admin</option>
                                        <option value="dept_head">Diploma Program Head</option>
                                    </select>
                                    <i class="fas fa-user-tag"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group mb-0">
                                <label for="add_status">Account Status</label>
                                <div class="input-wrapper">
                                    <select name="status" id="add_status" class="form-select" required>
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DYNAMIC ALERT BOX -->
                    <div id="add_global_access_alert" class="login-details-alert bg-primary bg-opacity-10 border-primary border-opacity-25 text-primary mb-3" style="display:none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <div id="alert_text">
                            Login Credentials will be generated automatically: <strong>Username = ID</strong> and <strong>Password = Birthday (MM/DD/YYYY)</strong>.
                        </div>
                    </div>

                    <!-- SECTION: MANUAL CREDENTIALS (FOR ADMIN/REGISTRAR) -->
                    <div id="standard_creds" style="display:none;">
                        <div class="form-section-divider">
                            <span><i class="fas fa-key me-2"></i>Access Credentials</span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <label for="add_username">Custom Username</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="username" id="add_username" class="form-control" placeholder="Enter username">
                                        <i class="fas fa-at"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <label for="add_password">Initial Password</label>
                                    <div class="input-wrapper">
                                        <input type="password" name="password" id="add_password" class="form-control" placeholder="••••••••">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION: PERSONAL DETAILS -->
                    <div id="profile_name_fields">
                        <div class="form-section-divider">
                            <span><i class="fas fa-user me-2"></i>PERSONAL DETAILS</span>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="premium-input-group">
                                    <label for="add_first_name">FIRST NAME</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="first_name" id="add_first_name" class="form-control" placeholder="Given Name">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="premium-input-group">
                                    <label for="add_middle_name">MIDDLE NAME</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="middle_name" id="add_middle_name" class="form-control" placeholder="Optional">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="premium-input-group">
                                    <label for="add_last_name">LAST NAME</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="last_name" id="add_last_name" class="form-control" placeholder="Surname">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="premium-input-group">
                                    <label for="add_dob">DATE OF BIRTH</label>
                                    <div class="input-wrapper">
                                        <input type="date" name="date_of_birth" id="add_dob" class="form-control" required>
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="premium-input-group">
                                    <label for="add_contact">CONTACT NUMBER</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="contact_number" id="add_contact" class="form-control" placeholder="09XX-XXX-XXXX">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="premium-input-group">
                                    <label for="add_email">EMAIL ADDRESS</label>
                                    <div class="input-wrapper">
                                        <input type="email" name="email" id="add_email" class="form-control" placeholder="instructor@example.com">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION: ACADEMIC ASSIGNMENT -->
                    <div id="academic_section">
                        <div class="form-section-divider">
                            <span><i class="fas fa-graduation-cap me-2"></i>ACADEMIC ASSIGNMENT</span>
                        </div>
                        <div class="row">
                            <div class="col-md-6" id="dept_selector">
                                <div class="premium-input-group">
                                    <label for="add_dept_id" id="dept_label">DIPLOMA PROGRAM</label>
                                    <div class="input-wrapper">
                                        <select name="dept_id" id="add_dept_id" class="form-select" onchange="filterAddPrograms()">
                                            <option value="">-- Select Diploma Program --</option>
                                            <?php foreach ($departments_list as $d): ?>
                                                <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fas fa-university"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" id="program_selector" style="display:none;">
                                <div class="premium-input-group">
                                    <label for="add_program_id">SPECIFIC PROGRAM (COURSE)</label>
                                    <div class="input-wrapper">
                                        <select name="program_id" id="add_program_id" class="form-select">
                                            <option value="">-- Select Program --</option>
                                            <?php foreach ($programs_list as $p): ?>
                                                <option value="<?php echo $p['program_id']; ?>" data-dept="<?php echo $p['dept_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" id="instructor_only_fields" style="display:none;">
                                <div class="premium-input-group">
                                    <label for="add_specialization">SPECIALIZATION</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="specialization" id="add_specialization" class="form-control" placeholder="e.g. Database Systems">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION: PROFILE ASSETS -->
                    <div class="form-section-divider">
                        <span><i class="fas fa-camera me-2"></i>PROFILE REPRESENTATION</span>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div id="add_image_preview_container" style="width: 70px; height: 70px; border-radius: 50%; overflow: hidden; background: #f1f5f9; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user fa-2x text-light"></i>
                            </div>
                        </div>
                        <div class="col">
                            <div class="premium-input-group mb-0">
                                <label for="add_profile_image">UPLOAD PROFILE PICTURE</label>
                                <div class="input-wrapper">
                                    <input type="file" name="profile_image" id="add_profile_image" class="form-control" accept="image/jpeg,image/png" onchange="previewImage(this, 'add_image_preview_container')">
                                    <i class="fas fa-upload"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-end gap-3">
                    <button type="button" class="btn btn-discard px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile px-5" id="addSubmitBtn">
                        <i class="fas fa-user-plus me-2"></i> <span>Register Student</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php if (!empty($error)): ?>
    <?php echo showError($error); ?>
<?php
endif; ?>

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-user-shield me-2 text-info"></i> System User Registry
        </h5>
        
        <div class="search-box-container">
            <div class="input-group input-group-sm rounded-pill overflow-hidden border-0 shadow-sm" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                <span class="input-group-text bg-transparent border-0 text-white-50 ps-3">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="userSearchInput" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="ID No Search or Profile Name..." onkeyup="filterUsers()" style="box-shadow: none;">
                <span class="input-group-text bg-transparent border-0 text-white-50 pe-3" id="searchCounter" style="font-size: 0.75rem; font-weight: 600;"></span>
            </div>
        </div>

        <button type="button" id="btnAddUser" class="btn btn-warning btn-sm rounded-pill px-4 shadow fw-bold border-0" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus-circle me-2"></i> Add User
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table premium-table data-table" id="usersTable">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>ACCOUNT IDENTITY</th>
                        <th>ROLE & DESIGNATION</th>
                        <th>STATUS & ACTIVITY</th>
                        <th class="text-end pe-4" style="min-width: 160px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0): ?>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <tr class="table-row-premium align-middle">
                        <td class="ps-4 py-3" data-label="ID">
                            <span class="text-muted fw-500 small">#<?php echo htmlspecialchars($user['user_id'] ?? ''); ?></span>
                        </td>
                        <td class="py-3" data-label="Account Identity">
                            <div class="d-flex align-items-center">
                                <div class="avatar-premium me-3 overflow-hidden">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="../uploads/profile_pics/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="identity-name"><?php echo htmlspecialchars($user['display_name'] ?? 'N/A'); ?></span>
                                    <span class="identity-meta">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3" data-label="Role & Level">
                            <?php
                                $roleColors = [
                                    'admin' => 'bg-danger text-danger',
                                    'registrar' => 'bg-primary text-primary',
                                    'instructor' => 'bg-info text-info',
                                    'student' => 'bg-success text-success',
                                    'dept_head' => 'bg-warning text-warning',
                                    'registrar_staff' => 'bg-indigo text-indigo'
                                ];
                                $role = $user['role'] ?? 'student';
                                $badgeStyle = $roleColors[$role] ?? 'bg-secondary text-secondary';
                            ?>
                            <span class="badge badge-premium <?php echo $badgeStyle; ?> bg-opacity-10">
                                <i class="fas fa-circle-nodes me-2 opacity-50"></i>
                                <?php 
                                    if ($role === 'registrar_staff') echo 'CLERK';
                                    else echo strtoupper(str_replace('_', ' ', $role)); 
                                ?>
                            </span>
                        </td>
                        <td class="py-3" data-label="Status & Activity">
                            <div id="user-status-<?php echo $user['user_id']; ?>" class="d-flex flex-column gap-2">
                                <?php 
                                    $status = $user['status'] ?? 'inactive'; 
                                    $lastActivity = $user['last_activity'] ?? null;
                                    $isOnline = false;
                                    if (!empty($lastActivity)) {
                                        $isOnline = (time() - strtotime($lastActivity)) <= 300;
                                    }
                                    $pulseColor = $isOnline ? '#22c55e' : '#ef4444';
                                    $statusLabel = $isOnline ? 'Active Online' : 'User Offline';
                                    $labelColor = $isOnline ? '#166534' : '#b91c1c';
                                ?>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="position-relative d-flex" style="width: 10px; height: 10px;">
                                        <?php if ($isOnline): ?>
                                            <span class="animate-ping position-absolute h-100 w-100 rounded-circle opacity-75" style="background: <?php echo $pulseColor; ?>;"></span>
                                        <?php endif; ?>
                                        <span class="position-relative rounded-circle h-100 w-100" style="background: <?php echo $pulseColor; ?>; border: 1.5px solid #fff; box-shadow: 0 0 5px <?php echo $pulseColor; ?>44;"></span>
                                    </span>
                                    <span class="fw-800 text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em; color: <?php echo $labelColor; ?>; font-family: 'Outfit', sans-serif;">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="badge border border-<?php echo $status === 'active' ? 'success' : 'secondary'; ?> border-opacity-25 bg-<?php echo $status === 'active' ? 'success' : 'secondary'; ?> bg-opacity-10 text-<?php echo $status === 'active' ? 'success' : 'secondary'; ?> rounded-1 py-1 px-2" style="font-size: 0.6rem; font-weight: 700;">
                                        <i class="fas fa-shield-halved me-1 opacity-75"></i> <?php echo strtoupper($status); ?>
                                    </span>
                                    <?php if (!empty($user['reset_requested'])): ?>
                                        <span class="badge border border-danger border-opacity-25 bg-danger bg-opacity-10 text-danger rounded-1 py-1 px-2" style="font-size: 0.6rem; font-weight: 700;">
                                            <i class="fas fa-key me-1"></i> RESET REQ.
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted d-flex flex-column gap-1" style="font-size: 0.65rem;">
                                    <div class="d-flex align-items-center"><i class="far fa-clock me-2 opacity-50"></i><?php echo !empty($lastActivity) ? timeAgo($lastActivity) : 'Long time'; ?></div>
                                    <div class="d-flex align-items-center"><i class="fas fa-network-wired me-2 opacity-50"></i>IP: <span class="ms-1 font-monospace"><?php echo htmlspecialchars($user['last_ip'] ?? '---'); ?></span></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-end pe-4 py-3" data-label="Control Actions" style="min-width: 160px;">
                            <div class="table-actions-v2">
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
                                <form method="POST" onsubmit="return confirmDelete('Are you sure you want to remove this user?');">
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
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="d-flex flex-column align-items-center gap-3 text-muted">
                                <i class="fas fa-users fa-3x opacity-25"></i>
                                <div>
                                    <p class="fw-bold mb-1">No users found</p>
                                    <p class="small mb-0">Click "Add User" to register the first user in the system.</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off" enctype="multipart/form-data">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit"></i>
                        <span>Modify User Profile</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-key me-2"></i>Access Credentials</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label for="edit_username">Username / System ID</label>
                                <div class="input-wrapper">
                                    <input type="text" name="username" id="edit_username" class="form-control" required>
                                    <i class="fas fa-hashtag"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label for="edit_password">Change Password (Optional)</label>
                                <div class="input-wrapper">
                                    <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep current">
                                    <i class="fas fa-lock"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label for="edit_role">Role Assignment</label>
                                <div class="input-wrapper">
                                    <select name="role" id="edit_role" class="form-select" required onchange="toggleEditFields()">
                                        <option value="student">Student</option>
                                        <option value="instructor">Instructor</option>
                                        <option value="registrar">Head Registrar</option>
                                        <option value="registrar_staff">Clerk</option>
                                        <option value="admin">Admin</option>
                                        <option value="dept_head">Diploma Program Head</option>
                                    </select>
                                    <i class="fas fa-user-tag"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label for="edit_status">Current Status</label>
                                <div class="input-wrapper">
                                    <select name="status" id="edit_status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="edit_global_access_alert" class="login-details-alert bg-primary bg-opacity-10 border-primary border-opacity-25 text-primary mb-3" style="display:none;">
                        <i class="fas fa-globe-asia me-2"></i>
                        <div>
                            <strong>Global Institutional Access Active</strong><br>
                            This account maintains oversight of all programs. Selection of specific departments is disabled for this role.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6" id="edit_jurisdiction" style="display:none;">
                            <div class="premium-input-group">
                                <label for="edit_dept_id">Designated Diploma Program</label>
                                <div class="input-wrapper">
                                    <select name="dept_id" id="edit_dept_id" class="form-select" onchange="filterEditPrograms()">
                                        <option value="">-- Select Diploma Program --</option>
                                        <?php foreach ($departments_list as $d): ?>
                                            <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6" id="edit_program_selector" style="display:none;">
                            <div class="premium-input-group">
                                <label for="edit_program_id">Specific Academic Program</label>
                                <div class="input-wrapper">
                                    <select name="program_id" id="edit_program_id" class="form-select">
                                        <option value="">-- Select Program --</option>
                                        <?php foreach ($programs_list as $p): ?>
                                            <option value="<?php echo $p['program_id']; ?>" data-dept="<?php echo $p['dept_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-image me-2"></i>Profile Representation</span>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div id="edit_image_preview" style="width: 80px; height: 80px; border-radius: 1rem; overflow: hidden; background: #f8fafc; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                                <i class="fas fa-user fa-2x text-light"></i>
                            </div>
                        </div>
                        <div class="col">
                            <div class="premium-input-group mb-0">
                                <label for="edit_profile_image">Modify Profile Picture</label>
                                <div class="input-wrapper">
                                    <input type="file" name="profile_image" id="edit_profile_image" class="form-control" accept="image/jpeg,image/png" onchange="previewImage(this, 'edit_image_preview')">
                                    <i class="fas fa-upload"></i>
                                </div>
                                <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">Supported: JPG, PNG. Max size 2MB.</small>
                            </div>
                        </div>
                    </div>
                       <div class="modal-footer d-flex justify-content-end gap-3">
                    <button type="button" class="btn btn-discard px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile px-5">
                        <i class="fas fa-save me-2"></i> Update Profile
                    </button>
                </div>
         </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Role display name mapping
const ROLE_NAMES = {
    'student': 'Student',
    'instructor': 'Instructor',
    'registrar': 'Head Registrar',
    'registrar_staff': 'Clerk',
    'admin': 'Administrator',
    'dept_head': 'Program Head'
};

function toggleAddFields() {
    const role = document.getElementById('add_role').value;
    const standard = document.getElementById('standard_creds');
    const nameFields = document.getElementById('profile_name_fields');
    const instructorFields = document.getElementById('instructor_only_fields');
    const deptSelector = document.getElementById('dept_selector');
    const deptLabel = document.getElementById('dept_label');
    const globalAlert = document.getElementById('add_global_access_alert');
    const alertText = document.getElementById('alert_text');
    const progSelector = document.getElementById('program_selector');
    const academicSection = document.getElementById('academic_section');
    const modalTitle = document.getElementById('addModalTitle');
    const submitBtn = document.getElementById('addSubmitBtn');
    const dobField = document.getElementById('add_dob');
    const contactField = document.getElementById('add_contact').closest('.col-md-4');
    const emailField = document.getElementById('add_email').closest('.col-md-4');
    
    // --- RESET EVERYTHING ---
    progSelector.style.display = 'none';
    globalAlert.style.display = 'none';
    instructorFields.style.display = 'none';
    standard.style.display = 'none';
    
    // Name fields are ALWAYS visible for every role
    nameFields.style.display = 'block';

    // Update dynamic title & button
    const roleDisplay = ROLE_NAMES[role] || role;
    modalTitle.querySelector('span').innerText = 'Register ' + roleDisplay;
    submitBtn.querySelector('span').innerText = 'Register ' + roleDisplay;

    // --- STUDENT / INSTRUCTOR ---
    if (role === 'student' || role === 'instructor') {
        // Auto credentials alert
        globalAlert.style.display = 'flex';
        alertText.innerHTML = 'Login Credentials will be generated automatically: <strong>Username = ID</strong> and <strong>Password = Birthday (MM/DD/YYYY)</strong>.';
        
        // Show academic section with dept
        academicSection.style.display = 'block';
        deptSelector.style.display = 'block';
        deptLabel.innerText = 'DIPLOMA PROGRAM';
        progSelector.style.display = (role === 'student' ? 'block' : 'none');
        
        // Show DOB, contact, email
        dobField.closest('.col-md-4').style.display = '';
        contactField.style.display = '';
        emailField.style.display = '';
        
        // Instructor specialization
        instructorFields.style.display = (role === 'instructor' ? 'block' : 'none');

        // Requirements
        document.getElementById('add_username').required = false;
        document.getElementById('add_password').required = false;
        document.getElementById('add_first_name').required = true;
        document.getElementById('add_last_name').required = true;
        dobField.required = true;
        document.getElementById('add_dept_id').required = true;

    // --- ADMIN / HEAD REGISTRAR ---
    } else if (role === 'admin' || role === 'registrar') {
        // Manual credentials
        standard.style.display = 'block';
        
        // Global access alert
        globalAlert.style.display = 'flex';
        alertText.innerHTML = '<strong>Global Institutional Access Enabled</strong><br>This administrative role has oversight across all Diploma Programs. No specific department assignment is required.';
        
        // Hide academic section entirely
        academicSection.style.display = 'none';
        
        // Hide DOB, contact, email for admins
        dobField.closest('.col-md-4').style.display = 'none';
        contactField.style.display = 'none';
        emailField.style.display = 'none';

        // Requirements
        document.getElementById('add_username').required = true;
        document.getElementById('add_password').required = true;
        document.getElementById('add_first_name').required = true;
        document.getElementById('add_last_name').required = false;
        dobField.required = false;
        document.getElementById('add_dept_id').required = false;

    // --- DEPT HEAD / CLERK ---
    } else {
        // Manual credentials
        standard.style.display = 'block';
        
        // Show academic assignment with dept
        academicSection.style.display = 'block';
        deptSelector.style.display = 'block';
        deptLabel.innerText = (role === 'dept_head') ? 'JURISDICTION (DIPLOMA PROGRAM)' : 'DESIGNATED DIPLOMA PROGRAM';

        // Hide DOB, contact, email
        dobField.closest('.col-md-4').style.display = 'none';
        contactField.style.display = 'none';
        emailField.style.display = 'none';

        // Requirements
        document.getElementById('add_username').required = true;
        document.getElementById('add_password').required = true;
        document.getElementById('add_first_name').required = true;
        document.getElementById('add_last_name').required = false;
        dobField.required = false;
        document.getElementById('add_dept_id').required = true;
    }
}

document.addEventListener('DOMContentLoaded', toggleAddFields);

function toggleEditFields() {
    const role = document.getElementById('edit_role').value;
    const editJurisdiction = document.getElementById('edit_jurisdiction');
    const editProgram = document.getElementById('edit_program_selector');
    const editGlobalAlert = document.getElementById('edit_global_access_alert');

    // Reset
    editJurisdiction.style.display = 'none';
    editProgram.style.display = 'none';
    editGlobalAlert.style.display = 'none';

    if (role === 'dept_head' || role === 'instructor' || role === 'student' || role === 'registrar_staff') {
        editJurisdiction.style.display = 'block';
        if (role === 'student') {
            editProgram.style.display = 'block';
            filterEditPrograms();
        }
    } else if (role === 'registrar' || role === 'admin') {
        editGlobalAlert.style.display = 'flex';
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

// Modal instances
let addUserModalObj;
let editUserModalObj;

document.addEventListener('DOMContentLoaded', () => {
    // Initialize modals
    const addEl = document.getElementById('addUserModal');
    const editEl = document.getElementById('editUserModal');
    
    if (addEl) addUserModalObj = new bootstrap.Modal(addEl);
    if (editEl) editUserModalObj = new bootstrap.Modal(editEl);

    toggleAddFields();
    
    const editRoleEl = document.getElementById('edit_role');
    if (editRoleEl) {
        editRoleEl.addEventListener('change', toggleEditFields);
    }
});

function showAddUserModal() {
    console.log("Opening Add User Modal...");
    if (addUserModalObj) {
        addUserModalObj.show();
    } else {
        const modalEl = document.getElementById('addUserModal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            console.error("Modal element #addUserModal not found!");
        }
    }
}

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
    
    if (editUserModalObj) {
        editUserModalObj.show();
    }
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
                const pulseColor = user.isOnline ? '#22c55e' : '#ef4444';
                const statusLabel = user.isOnline ? 'Active Online' : 'User Offline';
                const labelColor = user.isOnline ? '#166534' : '#b91c1c';
                
                // Header (Pulse & Status Text)
                html += `
                    <div class="d-flex align-items-center gap-2">
                        <span class="position-relative d-flex" style="width: 10px; height: 10px;">
                            ${user.isOnline ? `<span class="animate-ping position-absolute h-100 w-100 rounded-circle opacity-75" style="background: ${pulseColor};"></span>` : ''}
                            <span class="position-relative rounded-circle h-100 w-100" style="background: ${pulseColor}; border: 1.5px solid #fff; box-shadow: 0 0 5px ${pulseColor}44;"></span>
                        </span>
                        <span class="fw-800 text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em; color: ${labelColor}; font-family: 'Outfit', sans-serif;">
                            ${statusLabel}
                        </span>
                    </div>`;

                // Badges (Account Status)
                html += `
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge border border-${user.status === 'active' ? 'success' : 'secondary'} border-opacity-25 bg-${user.status === 'active' ? 'success' : 'secondary'} bg-opacity-10 text-${user.status === 'active' ? 'success' : 'secondary'} rounded-1 py-1 px-2" style="font-size: 0.6rem; font-weight: 700;">
                            <i class="fas fa-shield-halved me-1 opacity-75"></i> ${user.status.toUpperCase()}
                        </span>
                    </div>`;

                // Meta Info (Time, IP, Activity)
                html += `
                    <div class="text-muted d-flex flex-column gap-1" style="font-size: 0.65rem;">
                        <div class="d-flex align-items-center"><i class="far fa-clock me-2 opacity-50"></i>${user.isOnline ? `Online (${user.sessionDuration})` : user.lastLogin}</div>
                        <div class="d-flex align-items-center"><i class="fas fa-network-wired me-2 opacity-50"></i>IP: <span class="ms-1 font-monospace">${user.lastIP}</span></div>
                        <div class="d-flex align-items-center text-primary" style="font-weight: 500;"><i class="fas fa-fingerprint me-2 opacity-50"></i>Act: ${user.lastDoc}</div>
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


// Filter users locally
function filterUsers() {
    const input = document.getElementById('userSearchInput');
    const filter = input.value.toLowerCase().trim();
    const table = document.getElementById('usersTable');
    const tr = table.getElementsByTagName('tr');
    const counter = document.getElementById('searchCounter');
    let visibleCount = 0;

    for (let i = 1; i < tr.length; i++) {
        // Find the "Account Identity" column (contains Display Name and @username)
        const nameCol = tr[i].getElementsByTagName('td')[1];
        if (nameCol) {
            const textContent = nameCol.textContent || nameCol.innerText;
            if (textContent.toLowerCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
                visibleCount++;
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    // Update counter if search is active
    if (filter === "") {
        counter.textContent = "";
    } else {
        counter.textContent = visibleCount + " found";
    }
}

// Real-time image preview
function previewImage(input, containerId) {
    const container = document.getElementById(containerId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            container.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Start polling every 10 seconds
setInterval(updateUserStatuses, 10000);
</script>
