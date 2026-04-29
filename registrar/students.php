<?php
/**
 * Student Management
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
        redirectWithMessage('students.php', 'Invalid security token. Please try again.', 'danger');
    }

    // Enforce department restriction for Registrar Staff
    if ($isStaff) {
        $_POST['dept_id'] = $deptId;
        
        // If updating or deleting, verify the student belongs to the staff's department
        if (isset($_POST['student_id'])) {
            $checkStmt = $conn->prepare("SELECT dept_id FROM students WHERE student_id = ?");
            $checkStmt->bind_param("i", $_POST['student_id']);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result()->fetch_assoc();
            if ($checkRes && $checkRes['dept_id'] != $deptId) {
                redirectWithMessage('students.php', 'Unauthorized: Student belongs to another department.', 'danger');
            }
        }
    }
    if ($_POST['action'] === 'create') {
        $studentNo = sanitizeInput($_POST['student_no'] ?? '');
        if (empty($studentNo)) {
            $studentNo = generateNextID('student');
        }
        $dob = sanitizeInput($_POST['date_of_birth'] ?? ''); // Expected YYYY-MM-DD
        $formatted_dob = !empty($dob) ? date('m/d/Y', strtotime($dob)) : '';
        $username = $studentNo;
        
        // Use custom password if provided, otherwise default to birthdate
        $rawPassword = !empty($_POST['password']) ? $_POST['password'] : $formatted_dob;
        $password = hashPassword($rawPassword);

        // Handle Profile Image Upload
        $profileImage = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadRes = uploadFile($_FILES['profile_image'], '../uploads/profiles/');
            if ($uploadRes[0]) {
                $profileImage = 'uploads/profiles/' . $uploadRes[2];
            }
        }

        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $middleName = sanitizeInput($_POST['middle_name'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $municipality = sanitizeInput($_POST['municipality'] ?? '');
        $province = sanitizeInput($_POST['province'] ?? '');
        $religion = sanitizeInput($_POST['religion'] ?? '');
        $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $elemSchool = sanitizeInput($_POST['elem_school'] ?? '');
        $elemYear = sanitizeInput($_POST['elem_year'] ?? '');
        $secSchool = sanitizeInput($_POST['secondary_school'] ?? '');
        $secYear = sanitizeInput($_POST['secondary_year'] ?? '');
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $deptId = intval($_POST['dept_id'] ?? 0);

        // Check for duplicate username
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            redirectWithMessage('students.php', 'Username/ID already exists. Profile might already have an account.', 'danger');
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO users (username, password, role, profile_image, status) VALUES (?, ?, 'student', ?, 'active')");
        $stmt->bind_param("sss", $username, $password, $profileImage);
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_no, first_name, last_name, middle_name, date_of_birth, gender, elem_school, elem_year, secondary_school, secondary_year, address, municipality, province, religion, contact_number, email, program_id, dept_id, year_level, enrollment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
            $enrollmentDate = !empty($_POST['enrollment_date']) ? sanitizeInput($_POST['enrollment_date']) : date('Y-m-d');
            $stmt2->bind_param("issssssssssssssssiis", $userId, $studentNo, $firstName, $lastName, $middleName, $dob, $gender, $elemSchool, $elemYear, $secSchool, $secYear, $address, $municipality, $province, $religion, $contactNumber, $email, $programId, $deptId, $enrollmentDate);
            $stmt2->execute();
            logAudit(getCurrentUserId(), 'CREATE', 'users', $userId, null, "Created student profile: $studentNo ($firstName $lastName)");
            redirectWithMessage('students.php', 'Student created successfully. Login: ID=' . $studentNo . ', Pwd=' . $formatted_dob, 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $studentId = intval($_POST['student_id'] ?? 0);
        $studentNo = sanitizeInput($_POST['student_no'] ?? '');
        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $middleName = sanitizeInput($_POST['middle_name'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $municipality = sanitizeInput($_POST['municipality'] ?? '');
        $province = sanitizeInput($_POST['province'] ?? '');
        $religion = sanitizeInput($_POST['religion'] ?? '');
        $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $elemSchool = sanitizeInput($_POST['elem_school'] ?? '');
        $elemYear = sanitizeInput($_POST['elem_year'] ?? '');
        $secSchool = sanitizeInput($_POST['secondary_school'] ?? '');
        $secYear = sanitizeInput($_POST['secondary_year'] ?? '');
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $deptId = intval($_POST['dept_id'] ?? 0);
        $yearLevel = intval($_POST['year_level'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? '');
        $dob = sanitizeInput($_POST['date_of_birth'] ?? '');
        $enrollmentDate = !empty($_POST['enrollment_date']) ? sanitizeInput($_POST['enrollment_date']) : null;

        // Fetch current status to detect change to 'dropped'
        $oldStatusStmt = $conn->prepare("SELECT status FROM students WHERE student_id = ?");
        $oldStatusStmt->bind_param("i", $studentId);
        $oldStatusStmt->execute();
        $oldStatusRes = $oldStatusStmt->get_result()->fetch_assoc();
        $oldStatus = $oldStatusRes['status'] ?? '';
        $oldStatusStmt->close();

        $academicHonor = !empty($_POST['academic_honor']) ? sanitizeInput($_POST['academic_honor']) : null;
        $evaluatorId = !empty($academicHonor) ? getCurrentUserId() : null;

        // Handle Profile Image Update
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadRes = uploadFile($_FILES['profile_image'], '../uploads/profiles/');
            if ($uploadRes[0]) {
                $newProfileImage = 'uploads/profiles/' . $uploadRes[2];
                $imgStmt = $conn->prepare("UPDATE users u JOIN students s ON u.user_id = s.user_id SET u.profile_image = ? WHERE s.student_id = ?");
                $imgStmt->bind_param("si", $newProfileImage, $studentId);
                $imgStmt->execute();
            }
        }

        // Handle Password Update if provided
        if (!empty($_POST['password'])) {
            $newHashedPassword = hashPassword($_POST['password']);
            $pwdStmt = $conn->prepare("UPDATE users u JOIN students s ON u.user_id = s.user_id SET u.password = ? WHERE s.student_id = ?");
            $pwdStmt->bind_param("si", $newHashedPassword, $studentId);
            $pwdStmt->execute();
        }

        // Handle Username Sync (student_no)
        $userSync = $conn->prepare("UPDATE users u JOIN students s ON u.user_id = s.user_id SET u.username = ? WHERE s.student_id = ?");
        $userSync->bind_param("si", $studentNo, $studentId);
        $userSync->execute();

        // Backend Disqualification Validation
        $hasBacklog = hasAcademicBacklog($studentId);
        if ($hasBacklog) {
            if ($academicHonor !== null) {
                redirectWithMessage('students.php', 'Cannot assign academic honors to a student with backlogs (INC/Dropped/5.00).', 'danger');
            }
            if ($status === 'graduated') {
                redirectWithMessage('students.php', 'Cannot set status to "Graduated" for a student with backlogs.', 'danger');
            }
        }

        $stmt = $conn->prepare("UPDATE students SET student_no = ?, first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, gender = ?, elem_school = ?, elem_year = ?, secondary_school = ?, secondary_year = ?, address = ?, municipality = ?, province = ?, religion = ?, contact_number = ?, email = ?, program_id = ?, dept_id = ?, year_level = ?, status = ?, academic_honor = ?, honor_evaluated_by = ?, enrollment_date = COALESCE(?, enrollment_date) WHERE student_id = ?");
        $stmt->bind_param("ssssssssssssssssiiissisi", $studentNo, $firstName, $lastName, $middleName, $dob, $gender, $elemSchool, $elemYear, $secSchool, $secYear, $address, $municipality, $province, $religion, $contactNumber, $email, $programId, $deptId, $yearLevel, $status, $academicHonor, $evaluatorId, $enrollmentDate, $studentId);
        if ($stmt->execute()) {
            // Lifecycle Cleanup: If status changed to 'dropped', clear current semester enrollments
            if ($status === 'dropped' && $oldStatus !== 'dropped') {
                $curSem = getSetting('current_semester', '1st');
                $curAY = getSetting('academic_year', '2024-2025');
                
                $cleanupStmt = $conn->prepare("
                    DELETE e FROM enrollments e
                    JOIN class_sections cs ON e.section_id = cs.section_id
                    WHERE e.student_id = ? 
                    AND cs.semester = ? 
                    AND cs.school_year = ?
                ");
                $cleanupStmt->bind_param("iss", $studentId, $curSem, $curAY);
                $cleanupStmt->execute();
                $cleanupStmt->close();
                
                logAudit(getCurrentUserId(), 'UPDATE', 'students', $studentId, null, "Status changed to 'Dropped'. Automatically cleared current semester ($curSem $curAY) enrollments.");
            }
            logAudit(getCurrentUserId(), 'UPDATE', 'students', $studentId, null, "Updated student profile: $studentNo");
            redirectWithMessage('students.php', 'Student updated successfully' . ($academicHonor ? ' with ' . $academicHonor : ''), 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        if (getCurrentUserRole() !== 'registrar') {
            redirectWithMessage('students.php', 'Unauthorized: Only the Head Registrar can delete student profiles.', 'danger');
        }
        $studentId = intval($_POST['student_id']);
        $stmt = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            $userId = $u['user_id'];
            $conn->query("DELETE FROM users WHERE user_id = $userId");
            logAudit(getCurrentUserId(), 'DELETE', 'users', $userId, null, "Deleted student profile ID: $studentId (User ID: $userId)");
            redirectWithMessage('students.php', 'Student deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Student Records Registry';
require_once '../includes/header.php';
?>
<!-- Import Premium Typography -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    /* Premium Modal Styles */
    :root {
        --primary-blue: #0038A8;
        --dark-blue: #002366;
        --premium-gold: #FFD700;
        --premium-shadow: 0 10px 30px rgba(0, 56, 168, 0.15);
    }

    .modal-xl {
        max-width: 1100px;
    }

    .modal-dialog-scrollable .modal-content {
        max-height: 95vh;
        overflow: hidden;
    }
    
    .modal-dialog-scrollable form {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .modal-dialog-scrollable .modal-body {
        overflow-y: auto;
        flex: 1;
    }

    .modal-content {
        font-family: 'Outfit', sans-serif;
    }

    .modal-premium-header {
        background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
        position: relative;
        overflow: visible !important;
        min-height: 100px;
    }

    .title-icon-wrapper {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: inset 0 0 10px rgba(255, 255, 255, 0.1);
    }

    .form-section-divider {
        display: flex;
        align-items: center;
        margin: 1.25rem 0 1rem;
        color: var(--primary-blue);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
    }

    .form-section-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, rgba(0,56,168,0.2), transparent);
        margin-left: 1rem;
    }

    .form-section-divider span {
        background: rgba(0,56,168,0.05);
        padding: 0.3rem 0.75rem;
        border-radius: 50px;
        font-size: 0.7rem;
    }

    /* Premium Input Groups */
    .premium-input-group {
        background: linear-gradient(145deg, #ffffff, #fcfdff);
        padding: 0.5rem 0.85rem;
        border-radius: 10px;
        border: 1px solid rgba(0, 56, 168, 0.08);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        position: relative;
    }

    .premium-input-group:hover {
        border-color: rgba(0, 56, 168, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 56, 168, 0.05);
    }

    .premium-input-group:focus-within {
        border-color: var(--primary-blue);
        box-shadow: 0 8px 20px rgba(0, 56, 168, 0.1);
        background: #fff;
    }

    .premium-input-group label {
        display: block;
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--primary-blue) !important; /* Enforced Institution Blue */
        text-transform: uppercase;
        margin-bottom: 0.4rem;
        letter-spacing: 0.05em;
        position: relative;
        z-index: 2;
    }

    .premium-input-group .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }

    .premium-input-group .input-wrapper i:not(.password-toggle) {
        position: absolute;
        left: 0;
        color: var(--primary-blue);
        opacity: 0.4;
        font-size: 0.9rem;
        pointer-events: none;
        transition: all 0.3s ease;
    }

    .premium-input-group:focus-within i:not(.password-toggle) {
        opacity: 1;
        transform: scale(1.1);
    }

    .premium-input-group .form-control,
    .premium-input-group .form-select {
        border: none !important;
        padding: 0 0 0 28px !important;
        font-weight: 600 !important;
        color: #1a1c23 !important;
        background: transparent !important;
        box-shadow: none !important;
        font-size: 0.95rem !important;
        width: 100% !important;
    }

    .premium-input-group .form-control::placeholder {
        color: #adb5bd;
        font-weight: 400;
        font-size: 0.85rem;
    }

    .premium-input-group i {
        color: var(--primary-blue);
        opacity: 0.3;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }

    .premium-input-group:focus-within i {
        opacity: 1;
        transform: scale(1.1);
    }

    /* Password Toggle */
    .password-toggle {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .password-toggle:hover {
        color: var(--primary-blue) !important;
        opacity: 1 !important;
    }

    /* Premium Avatar Upload Area */
    .avatar-upload-wrapper {
        position: absolute;
        width: 90px;
        height: 90px;
        bottom: -45px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 100;
    }

    .avatar-preview-container {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: #f8faff;
        border: 4px solid #ffffff;
        box-shadow: 0 5px 15px rgba(0, 56, 168, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .avatar-preview-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-edit-btn {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--primary-blue);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        transition: all 0.2s ease;
        border: 2px solid white;
    }

    .avatar-edit-btn:hover {
        transform: scale(1.1);
        background: var(--dark-blue);
    }
</style>

<?php

// Fetch all departments and programs for the modals
$departments_list = [];
$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments WHERE status = 'active' ORDER BY title_diploma_program ASC");
if ($dept_res) {
    while ($d = $dept_res->fetch_assoc()) $departments_list[] = $d;
}

$programs_all_list = [];
$prog_res = $conn->query("SELECT program_id, dept_id, program_name as title_specific_program FROM programs ORDER BY program_name ASC");
if ($prog_res) {
    while ($p = $prog_res->fetch_assoc()) $programs_all_list[] = $p;
}
?>

<?php

// === STEP 3: Fetch data ===
$programWhere = $isStaff ? " AND p.dept_id = $deptId" : "";
$programs_res = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' $programWhere ORDER BY p.program_name ASC");
$programs_list = [];
if ($programs_res) {
    while ($p = $programs_res->fetch_assoc()) {
        $programs_list[] = $p;
    }
}

$deptWhere = $isStaff ? " WHERE dept_id = $deptId" : " WHERE status = 'active'";
$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments $deptWhere ORDER BY title_diploma_program ASC");
if ($dept_res) {
    while ($d = $dept_res->fetch_assoc()) {
        $departments_list[] = $d;
    }
}

$studentWhere = $isStaff ? " WHERE s.dept_id = $deptId" : "";
$students = $conn->query("
    SELECT s.*, u.username, u.profile_image, d.title_diploma_program as dept_name, p.program_name,
    (SELECT COUNT(*) FROM grades g WHERE g.student_id = s.student_id AND (g.remarks IN ('Failed', 'INC', 'Conditional', 'Dropped') OR g.grade >= 5.00)) as grade_backlog,
    (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.student_id AND e.status = 'dropped') as enrollment_backlog
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    $studentWhere
    ORDER BY s.created_at DESC
");
?>

<div class="card premium-card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top-4 gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-user-graduate me-2 text-warning"></i> Student Registry
        </h5>
        
        <div class="search-box-container">
            <div class="search-box-premium">
                <i class="fas fa-search"></i>
                <input type="text" id="studentSearchInput" class="form-control" placeholder="Search Identity..." onkeyup="filterStudents()">
                <span class="ps-2 pe-3 text-white-50" id="searchCounter" style="font-size: 0.75rem; font-weight: 600; white-space: nowrap;"></span>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="student_import.php" class="btn-premium-secondary py-2" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: white !important;">
                <i class="fas fa-file-import"></i> Import
            </a>
            <button class="btn-premium-action px-4 py-2" style="background: white; color: var(--primary-indigo) !important; box-shadow: 0 4px 15px rgba(0,0,0,0.1);" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus-circle"></i> Add Student
            </button>
        </div>
    </div>
    <div class="card-body p-4 bg-light bg-opacity-50">
        <div class="table-responsive">
            <table class="table align-middle mb-0 students-table premium-table data-table" id="studentTable">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 120px;">Student No.</th>
                        <th style="min-width: 200px;">Student Name</th>
                        <th style="min-width: 180px;">Academic Placement</th>
                        <th style="width: 90px;" class="text-center">Year</th>
                        <th style="min-width: 160px;">Contact Info</th>
                        <th style="width: 130px;">Admission Date</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end pe-4" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students): ?>
                    <?php while ($s = $students->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="fw-bold text-primary" style="font-size: 0.85rem;">#<?php echo htmlspecialchars($s['student_no'] ?? ''); ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0 overflow-hidden">
                                    <?php if (!empty($s['profile_image'])): ?>
                                        <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($s['profile_image']); ?>?v=<?php echo time(); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user-graduate"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem; line-height: 1.2;"><?php echo htmlspecialchars(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')); ?></div>
                                    <div class="text-muted" style="font-size: 0.70rem;"><i class="fas fa-fingerprint me-1"></i>UID: <?php echo $s['user_id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark" style="font-size: 0.82rem; line-height: 1.3;"><?php echo htmlspecialchars($s['program_name'] ?? 'N/A'); ?></div>
                            <div class="text-muted" style="font-size: 0.70rem; text-transform: uppercase; letter-spacing: 0.3px;"><?php echo htmlspecialchars($s['dept_name'] ?? 'Unassigned'); ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.75rem;">Yr. <?php echo $s['year_level'] ?? '—'; ?></span>
                        </td>
                        <td>
                            <div class="small text-truncate" style="max-width: 160px;"><i class="far fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($s['email'] ?? 'N/A'); ?></div>
                            <div class="small"><i class="fas fa-phone me-1 text-muted"></i><?php echo htmlspecialchars($s['contact_number'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($s['enrollment_date']) && $s['enrollment_date'] !== '0000-00-00'): ?>
                                <span class="small fw-semibold text-dark"><i class="fas fa-calendar-check me-1 text-success" style="font-size: 0.7rem;"></i><?php echo date('M d, Y', strtotime($s['enrollment_date'])); ?></span>
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-calendar-times me-1"></i>Not Set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $statusMap = [
                                    'active'    => ['label' => 'Active',    'dot' => '#22c55e', 'cls' => 'active'],
                                    'inactive'  => ['label' => 'Inactive',  'dot' => '#94a3b8', 'cls' => 'inactive'],
                                    'graduated' => ['label' => 'Graduated', 'dot' => '#3b82f6', 'cls' => 'inactive'],
                                    'dropped'   => ['label' => 'Dropped',   'dot' => '#ef4444', 'cls' => 'inactive'],
                                ];
                                $sm = $statusMap[$s['status']] ?? ['label' => ucfirst($s['status']), 'dot' => '#94a3b8', 'cls' => 'inactive'];
                            ?>
                            <span class="status-pill status-<?php echo $sm['cls']; ?>">
                                <div class="status-dot" style="background: <?php echo $sm['dot']; ?>;"></div> <?php echo $sm['label']; ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="curriculum_evaluation.php?id=<?php echo $s['student_id']; ?>" class="btn-premium-eval" title="Curriculum Evaluation" target="_blank">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                                <button class="btn-premium-edit" onclick="editStudent(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit Profile">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (getCurrentUserRole() === 'registrar'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student record permanently?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                    <button type="submit" class="btn-premium-delete" title="Delete Profile">
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
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-user-slash fs-1 mb-3 opacity-25"></i>
                                <p class="mb-0">No student records found matching your criteria.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header modal-premium-header py-4 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                        <div class="title-icon-wrapper me-3">
                            <i class="fas fa-user-plus text-warning"></i>
                        </div>
                        New Student Registration
                    </h5>
                    <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal" aria-label="Close"></button>

                    <!-- Avatar overlap -->
                    <div class="avatar-upload-wrapper">
                        <div class="avatar-preview-container" id="addAvatarPreview">
                            <i class="fas fa-user-graduate fa-3x text-muted opacity-25"></i>
                        </div>
                        <label for="add_profile_image" class="avatar-edit-btn">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" name="profile_image" id="add_profile_image" class="d-none" accept="image/*" onchange="previewImage(this, 'addAvatarPreview')">
                    </div>
                </div>

                <div class="modal-body p-4 p-md-5 bg-light" style="padding-top: 60px !important;">

                    <div class="form-section-divider mb-4" style="margin-top: 0 !important;">
                        <span><i class="fas fa-university me-2"></i>Academic Placement</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Department / Sector</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-building"></i>
                                    <select name="dept_id" id="add_dept_id" class="form-select" onchange="updatePrograms(this.value, 'add_program_id')" required>
                                        <option value="">-- Select Department --</option>
                                        <?php if(isset($departments_list)): foreach ($departments_list as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
                                        <?php endforeach; endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Specific Course / Program</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-award"></i>
                                    <select name="program_id" id="add_program_id" class="form-select" required>
                                        <option value="">-- Select Department First --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Identity -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-id-card me-2"></i>Account & Identity</span>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>Admission Date</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-plus"></i>
                                    <input type="date" name="enrollment_date" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="premium-input-group shadow-sm border-primary border-opacity-10" style="background: rgba(0, 56, 168, 0.01);">
                                <label class="text-primary">Account Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-shield-alt"></i>
                                    <input type="password" name="password" id="add_password" class="form-control" placeholder="Default: Birthdate">
                                    <i class="fas fa-eye-slash password-toggle" style="position: absolute; right: 0;" onclick="togglePasswordVisibility('add_password', this)"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Student Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-hashtag"></i>
                                    <input type="text" name="student_no" class="form-control" placeholder="Leave blank for Auto-ID">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Year Level</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-layer-group"></i>
                                    <select name="year_level" class="form-select">
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Identity -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-user-tag me-2"></i>Personal Identity</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>First Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Last Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Middle Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="middle_name" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Demographics -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-address-book me-2"></i>Contact & Background</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Gender</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-venus-mars"></i>
                                    <select name="gender" class="form-select">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Birth Date</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" name="date_of_birth" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Religion</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-hands-praying"></i>
                                    <input type="text" name="religion" class="form-control" list="religionlist">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Contact Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" name="contact_number" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Home Address</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <input type="text" name="address" class="form-control" list="addresslist" placeholder="Brgy / Street">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Municipality / City</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-city"></i>
                                    <input type="text" name="municipality" class="form-control" list="municipalitylist" placeholder="Municipality">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Province</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-map"></i>
                                    <input type="text" name="province" class="form-control" list="provincelist" placeholder="Province">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Educational History -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-history me-2"></i>Educational History</span>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-8">
                            <div class="premium-input-group">
                                <label>Elementary School</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-school"></i>
                                    <input type="text" name="elem_school" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Graduation Year</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar"></i>
                                    <input type="text" name="elem_year" class="form-control" placeholder="YYYY">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="premium-input-group">
                                <label>Secondary School</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-school-flag"></i>
                                    <input type="text" name="secondary_school" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Graduation Year</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-check"></i>
                                    <input type="text" name="secondary_year" class="form-control" placeholder="YYYY">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white border-top-0 py-4 px-4 px-md-5 rounded-bottom-4 d-flex justify-content-between">
                    <button type="button" class="btn-premium-secondary" data-bs-dismiss="modal">Discard</button>
                    <button type="submit" class="btn-premium-action px-5">
                        <i class="fas fa-plus-circle"></i> Register Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" id="edit_student_id">
                
                <div class="modal-header modal-premium-header py-4 px-4 border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center">
                        <div class="title-icon-wrapper me-3">
                            <i class="fas fa-user-edit text-warning"></i>
                        </div>
                        Modify Student Portfolio
                    </h5>
                    <button type="button" class="btn-close btn-close-white opacity-75" data-bs-dismiss="modal" aria-label="Close"></button>

                    <!-- Avatar overlap -->
                    <div class="avatar-upload-wrapper">
                        <div class="avatar-preview-container" id="editAvatarPreview">
                            <i class="fas fa-user-graduate fa-3x text-muted opacity-25"></i>
                        </div>
                        <label for="edit_profile_image" class="avatar-edit-btn">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" name="profile_image" id="edit_profile_image" class="d-none" accept="image/*" onchange="previewImage(this, 'editAvatarPreview')">
                    </div>
                </div>

                <div class="modal-body p-4 p-md-5 bg-light" style="padding-top: 60px !important;">
                    <div id="disqualification_alert"></div>

                    <!-- Academic Placement -->
                    <div class="form-section-divider mb-4" style="margin-top: 0 !important;">
                        <span><i class="fas fa-university me-2"></i>Academic Placement</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="premium-input-group shadow-sm border-primary border-opacity-10" style="background: rgba(0, 56, 168, 0.01);">
                                <label>Update Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-shield-alt"></i>
                                    <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep">
                                    <i class="fas fa-eye-slash password-toggle" style="position: absolute; right: 0;" onclick="togglePasswordVisibility('edit_password', this)"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Department / Sector</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-building"></i>
                                    <select name="dept_id" id="edit_dept_id" class="form-select" onchange="updatePrograms(this.value, 'edit_program_id')" required>
                                        <option value="">-- Select Department --</option>
                                        <?php if(isset($departments_list)): foreach ($departments_list as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
                                        <?php endforeach; endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Specific Course / Program</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-award"></i>
                                    <select name="program_id" id="edit_program_id" class="form-select" required>
                                        <?php if(isset($programs_all_list)): foreach ($programs_all_list as $prog): ?>
                                            <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['title_specific_program'] ?? $prog['program_name']); ?></option>
                                        <?php endforeach; endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Record Status</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-toggle-on"></i>
                                    <select name="status" id="edit_status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="graduated">Graduated</option>
                                        <option value="dropped">Dropped</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="premium-input-group border-info shadow-sm bg-light-subtle mb-4">
                        <label>Academic Honor Rank</label>
                        <div class="input-wrapper">
                            <i class="fas fa-star text-warning"></i>
                            <select name="academic_honor" id="edit_academic_honor" class="form-select">
                                <option value="">-- No Honor Assigned --</option>
                                <option value="With Honor">With Honor</option>
                                <option value="With High Honor">With High Honor</option>
                                <option value="With Highest Honor">With Highest Honor</option>
                                <option value="Cum Laude">Cum Laude</option>
                                <option value="Magna Cum Laude">Magna Cum Laude</option>
                                <option value="Summa Cum Laude">Summa Cum Laude</option>
                            </select>
                        </div>
                    </div>

                    <!-- Identity -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-id-card me-2"></i>Account & Identity</span>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Student Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-hashtag"></i>
                                    <input type="text" name="student_no" id="edit_student_no" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Birth Date</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Year Level</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-layer-group"></i>
                                    <select name="year_level" id="edit_year_level" class="form-select">
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Admission Date</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-plus"></i>
                                    <input type="date" name="enrollment_date" id="edit_enrollment_date" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Identity -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-user-tag me-2"></i>Personal Identity</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>First Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Last Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Middle Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Demographics -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-address-book me-2"></i>Contact & Background</span>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Gender</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-venus-mars"></i>
                                    <select name="gender" id="edit_gender" class="form-select">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Birth Date</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Religion</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-hands-praying"></i>
                                    <input type="text" name="religion" id="edit_religion" class="form-control" list="religionlist">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Contact Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="premium-input-group mb-4">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Home Address</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <input type="text" name="address" id="edit_address" class="form-control" list="addresslist" placeholder="Brgy / Street">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Municipality / City</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-city"></i>
                                    <input type="text" name="municipality" id="edit_municipality" class="form-control" list="municipalitylist" placeholder="Municipality">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Province</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-map"></i>
                                    <input type="text" name="province" id="edit_province" class="form-control" list="provincelist" placeholder="Province">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Educational History -->
                    <div class="form-section-divider mb-4">
                        <span><i class="fas fa-history me-2"></i>Educational History</span>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="premium-input-group">
                                <label>Elementary School</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-school"></i>
                                    <input type="text" name="elem_school" id="edit_elem_school" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Graduation Year</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar"></i>
                                    <input type="text" name="elem_year" id="edit_elem_year" class="form-control" placeholder="YYYY">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="premium-input-group">
                                <label>Secondary School</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-school-flag"></i>
                                    <input type="text" name="secondary_school" id="edit_secondary_school" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Graduation Year</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-check"></i>
                                    <input type="text" name="secondary_year" id="edit_secondary_year" class="form-control" placeholder="YYYY">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white border-top-0 py-4 px-4 px-md-5 rounded-bottom-4 d-flex justify-content-between">
                    <button type="button" class="btn-premium-secondary" data-bs-dismiss="modal">Discard changes</button>
                    <button type="submit" class="btn-premium-action px-5">
                        <i class="fas fa-save"></i> Update Portfolio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

<script>
    // Dynamic Program Filtering
    function updatePrograms(deptId, targetSelectId) {
        const targetSelect = document.getElementById(targetSelectId);
        if (!targetSelect) return;
        
        targetSelect.innerHTML = '<option value="">-- Select Program --</option>';
        if (!deptId) return;

        const allPrograms = <?php echo json_encode($programs_all_list); ?>;
        const filtered = allPrograms.filter(p => p.dept_id == deptId);
        
        filtered.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.program_id;
            opt.textContent = p.title_specific_program || p.program_name;
            targetSelect.appendChild(opt);
        });
    }

    // Modal data mapping
    function editStudent(data) {
        // Reset and populate fields
        document.getElementById('edit_student_id').value = data.student_id;
        document.getElementById('edit_student_no').value = data.student_no;
        document.getElementById('edit_first_name').value = data.first_name;
        document.getElementById('edit_last_name').value = data.last_name;
        document.getElementById('edit_middle_name').value = data.middle_name || '';
        document.getElementById('edit_date_of_birth').value = data.date_of_birth;
        document.getElementById('edit_gender').value = data.gender || 'Male';
        document.getElementById('edit_year_level').value = data.year_level;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('edit_academic_honor').value = data.academic_honor || '';
        document.getElementById('edit_enrollment_date').value = data.enrollment_date || '';
        document.getElementById('edit_province').value = data.province || '';
        
        // Handle Program and Dept mapping correctly
        const deptSelect = document.getElementById('edit_dept_id');
        deptSelect.value = data.dept_id || '';
        updatePrograms(data.dept_id, 'edit_program_id');
        document.getElementById('edit_program_id').value = data.program_id || '';

        // Reset password field
        document.getElementById('edit_password').value = '';

        // Handle Avatar Preview
        const preview = document.getElementById('editAvatarPreview');
        if (data.profile_image) {
            preview.innerHTML = `<img src="../${data.profile_image}" alt="Profile">`;
        } else {
            preview.innerHTML = `<i class="fas fa-user-graduate fa-3x text-muted opacity-25"></i>`;
        }

        // Handle Disqualification UI
        const isDisqualified = (parseInt(data.grade_backlog) > 0 || parseInt(data.enrollment_backlog) > 0);
        const alert = document.getElementById('disqualification_alert');
        const honorSelect = document.getElementById('edit_academic_honor');
        const statusSelect = document.getElementById('edit_status');

        if (isDisqualified) {
            alert.classList.remove('d-none');
            alert.innerHTML = `
                <div class="alert alert-danger py-4 rounded-4 mb-4 border-0 shadow-sm" style="background: rgba(220, 53, 69, 0.1); border-left: 5px solid #dc3545 !important;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3 text-danger"></i>
                        <div>
                            <h6 class="fw-bold mb-1 text-danger">Academic Restriction Alert</h6>
                            <p class="small mb-0 text-dark opacity-75">This student has active grade backlogs. Graduation and honor ranking are currently restricted.</p>
                        </div>
                    </div>
                </div>`;
            honorSelect.disabled = true;
            
            statusSelect.addEventListener('change', function() {
                if (this.value === 'graduated') {
                    alert('Warning: This student has backlogs and is technically unqualified for graduation.');
                    if (data.status !== 'graduated') {
                        this.value = data.status;
                    }
                }
            });
        } else {
            alert.classList.add('d-none');
            alert.innerHTML = '';
            honorSelect.disabled = false;
        }

        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    }

    // Image Preview Helper
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Password Toggle Helper
    function togglePasswordVisibility(fieldId, icon) {
        const field = document.getElementById(fieldId);
        if (field.type === "password") {
            field.type = "text";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            field.type = "password";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }

    // Table Filter
    function filterStudents() {
        const input = document.getElementById('studentSearchInput');
        const filter = input.value.toLowerCase().trim();
        const tr = document.querySelectorAll('#studentTable tbody tr');
        const counter = document.getElementById('searchCounter');
        let visibleCount = 0;

        tr.forEach(row => {
            if (row.textContent.toLowerCase().includes(filter)) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        counter.textContent = filter === "" ? "" : visibleCount + " found";
    }
</script>

<?php require_once '../includes/footer.php'; ?>
