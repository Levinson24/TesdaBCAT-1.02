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
        $dob = sanitizeInput($_POST['date_of_birth']);
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
            redirectWithMessage('instructors.php', 'Instructor ID already exists.', 'danger');
        }
        $check->close();

        // Handle File Upload
        $profileImage = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $targetDir = '../uploads/profile_pics/';
            if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
            $uploadResult = uploadFile($_FILES['photo'], $targetDir, ['jpg', 'jpeg', 'png']);
            if ($uploadResult[0]) {
                $profileImage = $uploadResult[2];
            }
        }

        $stmt = $conn->prepare("INSERT INTO users (username, password, role, status, profile_image) VALUES (?, ?, 'instructor', 'active', ?)");
        $stmt->bind_param("sss", $username, $password, $profileImage);
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO instructors (user_id, instructor_id_no, first_name, last_name, middle_name, date_of_birth, dept_id, specialization, contact_number, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isssssisss", $userId, $instructorIdNo, $firstName, $lastName, $middleName, $dob, $deptId, $specialization, $contactNumber, $email);
            $stmt2->execute();
            logAudit(getCurrentUserId(), 'CREATE', 'users', $userId, null, "Created instructor: $instructorIdNo");
            redirectWithMessage('instructors.php', 'Instructor created. ID=' . $instructorIdNo . ', Password=' . $formatted_dob, 'success');
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

        // Handle File Upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $targetDir = '../uploads/profile_pics/';
            if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
            $uploadResult = uploadFile($_FILES['photo'], $targetDir, ['jpg', 'jpeg', 'png']);
            if ($uploadResult[0]) {
                $newImage = $uploadResult[2];
                
                // Get old image
                $uStmt = $conn->prepare("SELECT u.user_id, u.profile_image FROM users u JOIN instructors i ON u.user_id = i.user_id WHERE i.instructor_id = ?");
                $uStmt->bind_param("i", $instructorId);
                $uStmt->execute();
                $uRes = $uStmt->get_result()->fetch_assoc();
                
                if ($uRes) {
                    $userId = $uRes['user_id'];
                    $oldImage = $uRes['profile_image'];
                    
                    $upStmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                    $upStmt->bind_param("si", $newImage, $userId);
                    $upStmt->execute();
                    
                    if ($oldImage && file_exists($targetDir . $oldImage)) {
                        @unlink($targetDir . $oldImage);
                    }
                }
            }
        }
        
        // Handle Password Update if provided
        if (!empty($_POST['password'])) {
            $newHashedPassword = hashPassword($_POST['password']);
            $pwdStmt = $conn->prepare("UPDATE users u JOIN instructors i ON u.user_id = i.user_id SET u.password = ? WHERE i.instructor_id = ?");
            $pwdStmt->bind_param("si", $newHashedPassword, $instructorId);
            $pwdStmt->execute();
        }

        $instructorIdNo = sanitizeInput($_POST['instructor_id_no']);
        
        // Handle Username Sync
        $userSync = $conn->prepare("UPDATE users u JOIN instructors i ON u.user_id = i.user_id SET u.username = ? WHERE i.instructor_id = ?");
        $userSync->bind_param("si", $instructorIdNo, $instructorId);
        $userSync->execute();

        $stmt = $conn->prepare("UPDATE instructors SET instructor_id_no = ?, first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, dept_id = ?, specialization = ?, contact_number = ?, email = ?, status = ? WHERE instructor_id = ?");
        $stmt->bind_param("sssssissssi", $instructorIdNo, $firstName, $lastName, $middleName, $dob, $deptId, $specialization, $contactNumber, $email, $status, $instructorId);
        if ($stmt->execute()) {
            logAudit(getCurrentUserId(), 'UPDATE', 'instructors', $instructorId, null, "Updated instructor: $firstName $lastName");
            redirectWithMessage('instructors.php', 'Profile updated successfully', 'success');
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

$pageTitle = 'Instructor Faculty Registry';
require_once '../includes/header.php';
?>
<!-- Import Premium Typography -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    .profile-sidebar {
        background: linear-gradient(135deg, #0038A8 0%, #001f5c 100%);
        color: white;
        padding: 3rem 1.5rem;
        border-radius: 1rem 0 0 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .profile-sidebar .profile-preview-container {
        width: 140px;
        height: 140px;
        border: 4px solid rgba(255,255,255,0.2);
        margin-bottom: 1.5rem;
    }
    .profile-sidebar h4 {
        font-weight: 700;
        margin-bottom: 0.25rem;
        letter-spacing: -0.5px;
    }
    .profile-sidebar .status-badge {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(5px);
        padding: 0.4rem 1rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-top: 1rem;
        border: 1px solid rgba(255,255,255,0.1);
    }
    .view-info-section {
        padding: 0.5rem 0;
    }
    .view-info-group {
        margin-bottom: 1.5rem;
    }
    .view-info-label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 1px;
        margin-bottom: 0.25rem;
    }
    .view-info-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
    }
    .section-header-premium {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px dashed #e2e8f0;
    }
    .section-header-premium i {
        width: 32px;
        height: 32px;
        background: #eff6ff;
        color: #0038A8;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
    }
    .section-header-premium h6 {
        margin: 0;
        font-weight: 700;
        color: #0038A8;
    }
    .profile-preview-container {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        overflow: hidden;
        border: 4px solid #fff;
        box-shadow: 0 10px 25px -5px rgba(0, 56, 168, 0.2);
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    .profile-preview-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .sect-icon-box {
        width: 32px;
        height: 32px;
        background: #f1f5f9;
        color: #6366f1;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        margin-right: 12px;
        flex-shrink: 0;
        border: 1px solid #e2e8f0;
        font-size: 0.8rem;
    }

    /* Premium Table Styling */
    .premium-card {
        background: #fff;
        border-radius: 20px !important;
        overflow: hidden;
    }
    .premium-table {
        border-collapse: separate;
        border-spacing: 0 8px;
        background: #f8fafc;
    }
    .premium-table thead th {
        background: transparent;
        border: none;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        padding: 1.5rem 1rem;
    }
    .premium-table tbody tr {
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        transition: all 0.2s ease;
        border-radius: 12px;
    }
    .premium-table tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        background: #fff !important;
    }
    .premium-table td {
        border: none;
        padding: 1.25rem 1rem;
        vertical-align: middle;
    }
    .premium-table td:first-child { border-radius: 12px 0 0 12px; }
    .premium-table td:last-child { border-radius: 0 12px 12px 0; }

    .status-pill {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .status-active { background: #ecfdf5; color: #059669; }
    .status-inactive { background: #f1f5f9; color: #64748b; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; }

    .btn-premium-view, .btn-premium-edit, .btn-premium-delete {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border: none;
        background: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .btn-premium-view { color: #6366f1; }
    .btn-premium-view:hover { background: #6366f1; color: #fff; transform: scale(1.1); }
    .btn-premium-edit { color: #0ea5e9; }
    .btn-premium-edit:hover { background: #0ea5e9; color: #fff; transform: scale(1.1); }
    .btn-premium-delete { color: #ef4444; }
    .btn-premium-delete:hover { background: #ef4444; color: #fff; transform: scale(1.1); }
</style>


<?php

$deptWhere = $isStaff ? " WHERE dept_id = $deptId" : " WHERE status = 'active'";
$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments $deptWhere ORDER BY title_diploma_program ASC");
$departments_list = [];
while ($d = $dept_res->fetch_assoc())
    $departments_list[] = $d;

$instructorWhere = $isStaff ? " WHERE i.dept_id = $deptId" : "";
$instructors = $conn->query("
    SELECT i.*, u.username, u.profile_image as photo, d.title_diploma_program as dept_name 
    FROM instructors i 
    JOIN users u ON i.user_id = u.user_id 
    LEFT JOIN departments d ON i.dept_id = d.dept_id
    $instructorWhere
    ORDER BY i.created_at DESC
");
?>


<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-chalkboard-teacher me-2 text-warning"></i> Instructor Management
        </h5>
        
        <div class="search-box-container">
            <div class="input-group input-group-sm rounded-pill overflow-hidden border-0 shadow-sm" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                <span class="input-group-text bg-transparent border-0 text-white-50 ps-3">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="instructorSearchInput" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="Search Instructor or Dept..." onkeyup="filterInstructors()" style="box-shadow: none;">
                <span class="input-group-text bg-transparent border-0 text-white-50 pe-3" id="searchCounter" style="font-size: 0.75rem; font-weight: 600;"></span>
            </div>
        </div>

        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus-circle me-1"></i> Add Instructor
        </button>
    </div>
    <div class="card-body p-4 bg-light bg-opacity-50">
        <div class="table-responsive">
            <table class="table align-middle mb-0 instructors-table premium-table data-table" id="instructorTable">
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
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle overflow-hidden" style="width: 35px; height: 35px;">
                                <?php if (!empty($i['photo'])): ?>
                                    <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($i['photo']); ?>?v=<?php echo time(); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-chalkboard-teacher"></i>
                                <?php endif; ?>
                            </div>
                            <div class="fw-bold text-dark">
                                <?php echo htmlspecialchars(($i['first_name'] ?? '') . (($i['middle_name'] ?? '') ? ' ' . $i['middle_name'] : '') . ' ' . ($i['last_name'] ?? '')); ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($i['dept_name'] ?? 'Unassigned'); ?></div>
                        <div class="small text-muted">Spec: <?php echo htmlspecialchars($i['specialization'] ?? 'N/A'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($i['contact_number'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($i['email'] ?? 'N/A'); ?></td>
                    <td>
                            <span class="status-pill status-<?php echo ($i['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                                <div class="status-dot" style="background: <?php echo ($i['status'] ?? 'active') === 'active' ? '#22c55e' : '#94a3b8'; ?>;"></div> <?php echo ucfirst($i['status'] ?? 'active'); ?>
                            </span>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn-premium-view" onclick="viewInstructor(<?php echo htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8'); ?>)" title="View Full Profile">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-premium-edit" onclick="editInstructor(<?php echo htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit Profile">
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

<!-- Add Instructor Modal -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Sidebar -->
                        <div class="col-12 col-lg-4 profile-sidebar">
                            <button type="button" class="btn-close btn-close-white position-absolute end-0 top-0 m-3" data-bs-dismiss="modal"></button>
                            
                            <div class="profile-preview-container mx-auto bg-white">
                                <img id="addPhotoPreview" src="../assets/img/default-avatar.png" alt="Profile Preview">
                            </div>
                            
                            <h4 class="mt-4 mb-2 text-white">New Faculty</h4>
                            <p class="opacity-75 small mb-4 px-4 text-white">Registering a new account</p>
                            
                            <div class="px-4 w-100">
                                <label class="btn btn-outline-light btn-sm rounded-pill px-4 w-100 mb-4 border-opacity-25 shadow-sm">
                                    <i class="fas fa-camera me-2"></i> Upload Photo
                                    <input type="file" name="photo" class="d-none" onchange="previewImage(this, 'addPhotoPreview')">
                                </label>
                            </div>

                            <div class="mt-auto pt-5 opacity-50 small text-white">
                                <i class="fas fa-university me-1"></i> Faculty Management System
                            </div>
                        </div>
                        
                        <!-- Right Content -->
                        <div class="col-12 col-lg-8 p-4 p-lg-5 bg-white">
                            <div class="row g-4">
                                <!-- Personal Info -->
                                <div class="col-12">
                                    <div class="section-header-premium">
                                        <i class="fas fa-id-card"></i>
                                        <h6>PERSONAL INFORMATION</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="premium-input-group mb-0">
                                                <label>First Name</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="first_name" class="form-control" required>
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="premium-input-group mb-0">
                                                <label>Last Name</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="last_name" class="form-control" required>
                                                    <i class="fas fa-user-tag"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="premium-input-group mb-0">
                                                <label>Middle Name</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="middle_name" class="form-control">
                                                    <i class="fas fa-user-edit"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="premium-input-group mb-0">
                                                <label>Birth Date</label>
                                                <div class="input-wrapper">
                                                    <input type="date" name="date_of_birth" class="form-control" required>
                                                    <i class="fas fa-calendar-alt"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Professional Assignment -->
                                <div class="col-12">
                                    <div class="section-header-premium mt-3">
                                        <i class="fas fa-briefcase"></i>
                                        <h6>PROFESSIONAL ASSIGNMENT</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Diploma Program</label>
                                                <div class="input-wrapper">
                                                    <select name="dept_id" class="form-select border-0 ps-0" required>
                                                        <option value="">-- Select Diploma --</option>
                                                        <?php foreach ($departments_list as $dept): ?>
                                                            <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <i class="fas fa-building"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Specialization</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="specialization" class="form-control" placeholder="e.g. Computer Engineering">
                                                    <i class="fas fa-medal"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Details -->
                                <div class="col-12">
                                    <div class="section-header-premium mt-3">
                                        <i class="fas fa-address-book"></i>
                                        <h6>CONTACT DETAILS</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Contact Number</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="contact_number" class="form-control">
                                                    <i class="fas fa-phone"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Email Address</label>
                                                <div class="input-wrapper">
                                                    <input type="email" name="email" class="form-control">
                                                    <i class="fas fa-envelope"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-5 pt-4 border-top">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Discard</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="fas fa-user-plus me-2"></i> Create account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Faculty Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="instructor_id" id="edit_instructor_id">
                
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Sidebar -->
                        <div class="col-12 col-lg-4 profile-sidebar">
                            <button type="button" class="btn-close btn-close-white position-absolute end-0 top-0 m-3" data-bs-dismiss="modal"></button>
                            
                            <div class="profile-preview-container mx-auto shadow position-relative">
                                <img id="editPhotoPreview" src="../assets/img/default-avatar.png" alt="Profile Preview">
                                <label class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center cursor-pointer" style="width: 40px; height: 40px; border: 3px solid #fff; transform: translate(10px, 10px);" title="Change Photo">
                                    <i class="fas fa-camera"></i>
                                    <input type="file" name="photo" class="d-none" onchange="previewImage(this, 'editPhotoPreview')">
                                </label>
                            </div>
                            
                            <h4 class="mt-4 mb-1">Edit Profile</h4>
                            <p class="opacity-75 small mb-4">Update faculty member information</p>
                            
                            <div class="w-100 px-4 mt-2">
                                <div class="premium-input-group text-start">
                                    <label class="text-white opacity-75 small">Account Status</label>
                                    <div class="input-wrapper" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                                        <select name="status" id="edit_status" class="form-select bg-transparent text-white border-0" style="box-shadow: none;">
                                            <option value="active" style="color: #333;">Active</option>
                                            <option value="inactive" style="color: #333;">Inactive</option>
                                        </select>
                                        <i class="fas fa-toggle-on text-white-50"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-auto pt-5 opacity-50 small">
                                <i class="fas fa-user-lock me-1"></i> Secure Data Environment
                            </div>
                        </div>
                        
                        <!-- Right Content -->
                        <div class="col-12 col-lg-8 p-4 p-lg-5 bg-white">
                            <div class="row g-4">
                                <!-- Personal Info -->
                                <div class="col-12">
                                    <div class="section-header-premium">
                                        <i class="fas fa-user-edit"></i>
                                        <h6>PERSONAL INFORMATION</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>First Name</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Last Name</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                                                    <i class="fas fa-user-tag"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Instructor ID No.</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="instructor_id_no" id="edit_instructor_id_no" class="form-control" required>
                                                    <i class="fas fa-barcode"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Middle Name</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                                                    <i class="fas fa-user-edit"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Birth Date</label>
                                                <div class="input-wrapper">
                                                    <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
                                                    <i class="fas fa-calendar-alt"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Academic Assignment -->
                                <div class="col-12">
                                    <div class="section-header-premium mt-3">
                                        <i class="fas fa-briefcase"></i>
                                        <h6>ACADEMIC ASSIGNMENT</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-7">
                                            <div class="premium-input-group mb-0">
                                                <label>Diploma Program / Dept</label>
                                                <div class="input-wrapper">
                                                    <select name="dept_id" id="edit_dept_id" class="form-select border-0 ps-0" required>
                                                        <option value="">-- Select Diploma --</option>
                                                        <?php foreach ($departments_list as $dept): ?>
                                                            <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <i class="fas fa-building"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="premium-input-group mb-0">
                                                <label>Specialization</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="specialization" id="edit_specialization" class="form-control">
                                                    <i class="fas fa-medal"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="premium-input-group mb-0 mt-3">
                                                <label>New Password (Leave blank to keep current)</label>
                                                <div class="input-wrapper">
                                                    <input type="password" name="password" class="form-control" autocomplete="new-password">
                                                    <i class="fas fa-key"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Details -->
                                <div class="col-12">
                                    <div class="section-header-premium mt-3">
                                        <i class="fas fa-phone-alt"></i>
                                        <h6>CONTACT DETAILS</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Contact Number</label>
                                                <div class="input-wrapper">
                                                    <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                                                    <i class="fas fa-phone"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="premium-input-group mb-0">
                                                <label>Email Address</label>
                                                <div class="input-wrapper">
                                                    <input type="email" name="email" id="edit_email" class="form-control">
                                                    <i class="fas fa-envelope"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-5 pt-4 border-top">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Discard</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="fas fa-save me-2"></i> Update Portfolio
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Profile Modal -->
<div class="modal fade" id="viewModal">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Left Sidebar -->
                    <div class="col-12 col-lg-4 profile-sidebar">
                        <button type="button" class="btn-close btn-close-white position-absolute end-0 top-0 m-3" data-bs-dismiss="modal"></button>
                        
                        <div class="profile-preview-container mx-auto shadow">
                            <img id="viewPhotoPreview" src="../assets/img/default-avatar.png" alt="Profile Photo">
                        </div>
                        
                        <h4 id="disp_header_name">Instructor Name</h4>
                        <div class="opacity-75 small" id="disp_header_id">Employee ID</div>
                        
                        <div id="disp_status_container">
                            <!-- Status will be injected here -->
                        </div>

                        <div class="mt-auto pt-5 opacity-50 small">
                            <i class="fas fa-shield-alt me-1"></i> Verified Faculty Profile
                        </div>
                    </div>
                    
                    <!-- Right Content -->
                    <div class="col-12 col-lg-8 p-4 p-lg-5 bg-white">
                        <div class="row g-4">
                            <!-- Personal Information -->
                            <div class="col-12">
                                <div class="section-header-premium">
                                    <i class="fas fa-user"></i>
                                    <h6>PERSONAL INFORMATION</h6>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 view-info-group">
                                        <div class="view-info-label">Complete Name</div>
                                        <div class="view-info-value" id="disp_full_name"></div>
                                    </div>
                                    <div class="col-md-6 view-info-group">
                                        <div class="view-info-label">Date of Birth</div>
                                        <div class="view-info-value" id="disp_dob"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Professional Assignment -->
                            <div class="col-12">
                                <div class="section-header-premium mt-2">
                                    <i class="fas fa-graduation-cap"></i>
                                    <h6>ACADEMIC ASSIGNMENT</h6>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 view-info-group">
                                        <div class="view-info-label">Diploma Program / Department</div>
                                        <div class="view-info-value text-primary" id="disp_dept"></div>
                                    </div>
                                    <div class="col-md-6 view-info-group">
                                        <div class="view-info-label">Specialization</div>
                                        <div class="view-info-value" id="disp_specialization"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Details -->
                            <div class="col-12">
                                <div class="section-header-premium mt-2">
                                    <i class="fas fa-address-book"></i>
                                    <h6>CONTACT DETAILS</h6>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 view-info-group">
                                        <div class="view-info-label">Mobile Number</div>
                                        <div class="view-info-value" id="disp_contact"></div>
                                    </div>
                                    <div class="col-md-6 view-info-group">
                                        <div class="view-info-label">Email Address</div>
                                        <div class="view-info-value" id="disp_email"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-4 border-top">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Close</button>
                            <button class="btn btn-primary rounded-pill px-5 fw-bold" onclick="initiateEditFromView()">
                                <i class="fas fa-edit me-2"></i> Update Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
let currentInstructorData = null;

function viewInstructor(data) {
    currentInstructorData = data;
    const fullName = (data.first_name || '') + (data.middle_name ? ' ' + data.middle_name : '') + ' ' + (data.last_name || '');
    
    // Sidebar Info
    document.getElementById('disp_header_name').innerText = fullName;
    document.getElementById('disp_header_id').innerText = 'ID: ' + (data.instructor_id_no || 'N/A');
    
    // Photo
    const photoPreview = document.getElementById('viewPhotoPreview');
    if (data.photo) {
        photoPreview.setAttribute('src', '../uploads/profile_pics/' + data.photo + '?v=' + new Date().getTime());
    } else {
        photoPreview.setAttribute('src', '../assets/img/default-avatar.png');
    }

    // Status Badge
    const status = data.status || 'active';
    const statusClass = status === 'active' ? 'bg-success' : 'bg-secondary';
    document.getElementById('disp_status_container').innerHTML = `
        <div class="status-badge">
            <i class="fas fa-circle me-1" style="font-size: 0.5rem; color: ${status === 'active' ? '#22c55e' : '#94a3b8'}"></i>
            ${status.toUpperCase()}
        </div>
    `;

    // Content Details
    document.getElementById('disp_full_name').innerText = fullName;
    document.getElementById('disp_dob').innerText = data.date_of_birth ? new Date(data.date_of_birth).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    document.getElementById('disp_contact').innerText = data.contact_number || 'N/A';
    document.getElementById('disp_email').innerText = data.email || 'N/A';
    document.getElementById('disp_dept').innerText = data.dept_name || 'Unassigned';
    document.getElementById('disp_specialization').innerText = data.specialization || 'N/A';
    
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

function initiateEditFromView() {
    if (currentInstructorData) {
        bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();
        editInstructor(currentInstructorData);
    }
}

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).setAttribute('src', e.target.result);
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function editInstructor(data) {
    document.getElementById('edit_instructor_id').value = data.instructor_id;
    document.getElementById('edit_instructor_id_no').value = data.instructor_id_no;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_date_of_birth').value = data.date_of_birth;
    document.getElementById('edit_dept_id').value = data.dept_id;
    document.getElementById('edit_specialization').value = data.specialization || '';
    document.getElementById('edit_contact_number').value = data.contact_number || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_status').value = data.status;
    
    // Set Profile Preview
    const preview = document.getElementById('editPhotoPreview');
    if (data.photo) {
        preview.setAttribute('src', '../uploads/profile_pics/' + data.photo + '?v=' + new Date().getTime());
    } else {
        preview.setAttribute('src', '../assets/img/default-avatar.png');
    }

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
// Filter instructors locally
function filterInstructors() {
    const input = document.getElementById('instructorSearchInput');
    const filter = input.value.toLowerCase().trim();
    const table = document.querySelector('.instructors-table');
    if (!table) return;
    const tr = table.getElementsByTagName('tr');
    const counter = document.getElementById('searchCounter');
    let visibleCount = 0;

    for (let i = 1; i < tr.length; i++) {
        let rowMatch = false;
        const tds = tr[i].getElementsByTagName('td');
        for (let j = 0; j < tds.length; j++) {
            if (tds[j].textContent.toLowerCase().indexOf(filter) > -1) {
                rowMatch = true;
                break;
            }
        }
        
        if (rowMatch) {
            tr[i].style.display = "";
            visibleCount++;
        } else {
            tr[i].style.display = "none";
        }
    }

    if (filter === "") {
        counter.textContent = "";
    } else {
        counter.textContent = visibleCount + " found";
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
