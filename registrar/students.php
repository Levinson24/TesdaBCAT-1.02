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
        $studentNo = sanitizeInput($_POST['student_no']);
        if (empty($studentNo)) {
            $studentNo = generateNextID('student');
        }
        $dob = sanitizeInput($_POST['date_of_birth']); // Expected YYYY-MM-DD
        $formatted_dob = date('m/d/Y', strtotime($dob));
        $username = $studentNo;
        $password = hashPassword($formatted_dob);

        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $gender = sanitizeInput($_POST['gender']);
        $address = sanitizeInput($_POST['address']);
        $municipality = sanitizeInput($_POST['municipality']);
        $religion = sanitizeInput($_POST['religion']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $email = sanitizeInput($_POST['email']);
        $elemSchool = sanitizeInput($_POST['elem_school']);
        $elemYear = sanitizeInput($_POST['elem_year']);
        $secSchool = sanitizeInput($_POST['secondary_school']);
        $secYear = sanitizeInput($_POST['secondary_year']);
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

        $stmt = $conn->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'student', 'active')");
        $stmt->bind_param("ss", $username, $password);
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_no, first_name, last_name, middle_name, date_of_birth, gender, elem_school, elem_year, secondary_school, secondary_year, address, municipality, religion, contact_number, email, program_id, dept_id, year_level, enrollment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE())");
            $stmt2->bind_param("isssssssssssssssii", $userId, $studentNo, $firstName, $lastName, $middleName, $dob, $gender, $elemSchool, $elemYear, $secSchool, $secYear, $address, $municipality, $religion, $contactNumber, $email, $programId, $deptId);
            $stmt2->execute();
            logAudit(getCurrentUserId(), 'CREATE', 'users', $userId, null, "Created student profile: $studentNo ($firstName $lastName)");
            redirectWithMessage('students.php', 'Student created successfully. Login: ID=' . $studentNo . ', Pwd=' . $formatted_dob, 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $studentId = intval($_POST['student_id']);
        $studentNo = sanitizeInput($_POST['student_no']);
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $gender = sanitizeInput($_POST['gender']);
        $address = sanitizeInput($_POST['address']);
        $municipality = sanitizeInput($_POST['municipality']);
        $religion = sanitizeInput($_POST['religion']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $email = sanitizeInput($_POST['email']);
        $elemSchool = sanitizeInput($_POST['elem_school']);
        $elemYear = sanitizeInput($_POST['elem_year']);
        $secSchool = sanitizeInput($_POST['secondary_school']);
        $secYear = sanitizeInput($_POST['secondary_year']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $deptId = intval($_POST['dept_id'] ?? 0);
        $yearLevel = intval($_POST['year_level']);
        $status = sanitizeInput($_POST['status']);
        $dob = sanitizeInput($_POST['date_of_birth']);

        $academicHonor = !empty($_POST['academic_honor']) ? sanitizeInput($_POST['academic_honor']) : null;
        $evaluatorId = !empty($academicHonor) ? getCurrentUserId() : null;

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

        $stmt = $conn->prepare("UPDATE students SET student_no = ?, first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, gender = ?, elem_school = ?, elem_year = ?, secondary_school = ?, secondary_year = ?, address = ?, municipality = ?, religion = ?, contact_number = ?, email = ?, program_id = ?, dept_id = ?, year_level = ?, status = ?, academic_honor = ?, honor_evaluated_by = ? WHERE student_id = ?");
        $stmt->bind_param("sssssssssssssssiiissii", $studentNo, $firstName, $lastName, $middleName, $dob, $gender, $elemSchool, $elemYear, $secSchool, $secYear, $address, $municipality, $religion, $contactNumber, $email, $programId, $deptId, $yearLevel, $status, $academicHonor, $evaluatorId, $studentId);
        if ($stmt->execute()) {
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

$pageTitle = 'Manage Students';
require_once '../includes/header.php';

// === STEP 3: Fetch data ===
$programWhere = $isStaff ? " AND p.dept_id = $deptId" : "";
$programs_res = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' $programWhere ORDER BY p.program_name ASC");
$programs_list = [];
while ($p = $programs_res->fetch_assoc()) {
    $programs_list[] = $p;
}

$deptWhere = $isStaff ? " WHERE dept_id = $deptId" : " WHERE status = 'active'";
$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments $deptWhere ORDER BY title_diploma_program ASC");
$departments_list = [];
while ($d = $dept_res->fetch_assoc()) {
    $departments_list[] = $d;
}

$studentWhere = $isStaff ? " WHERE s.dept_id = $deptId" : "";
$students = $conn->query("
    SELECT s.*, u.username, d.title_diploma_program as dept_name, p.program_name,
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

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between">
        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Student Management</h5>
        <div>
            <a href="student_import.php" class="btn btn-light btn-sm me-2">
                <i class="fas fa-file-import"></i> Import Students
            </a>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus"></i> Add Student
            </button>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-hover data-table">
            <thead>
                <tr>
                    <th>Student No</th>
                    <th>Name</th>
                    <th>Diploma Program</th>
                    <th>Program (Course)</th>
                    <th>Year</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = $students->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['student_no'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($s['first_name'] ?? '') . (($s['middle_name'] ?? '') ? ' ' . $s['middle_name'] : '') . ' ' . ($s['last_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($s['dept_name'] ?? 'Unassigned'); ?></td>
                    <td><?php echo htmlspecialchars($s['program_name'] ?? 'N/A'); ?></td>
                    <td><?php echo $s['year_level'] ?? ''; ?></td>
                    <td><?php echo htmlspecialchars($s['email'] ?? 'N/A'); ?></td>
                    <td><span class="badge bg-<?php echo $s['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-info" onclick='editStudent(<?php echo json_encode($s); ?>)' title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="curriculum_evaluation.php?id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-primary" target="_blank" title="Print Evaluation">
                            <i class="fas fa-file-invoice"></i>
                        </a>
                        <?php if (getCurrentUserRole() === 'registrar'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this student? Account will also be removed.')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                <i class="fas fa-trash"></i>
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

<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        <small><i class="fas fa-info-circle"></i> Login Credentials will be generated automatically: <b>Username = ID</b> and <b>Password = Birthday (MM/DD/YYYY)</b>.</small>
                    </div>
                    <div class="mb-3">
                        <label>Student Number (Leave blank for auto-generation)</label>
                        <input type="text" name="student_no" class="form-control" placeholder="Optional - Auto-generated if blank">
                    </div>
                    <div class="mb-3">
                        <label>Birth Date</label>
                        <input type="date" name="date_of_birth" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold text-primary border-bottom pb-1 d-block mb-3">Educational Background</label>
                        <div class="row">
                            <div class="col-md-9 mb-2">
                                <label class="small text-muted">Elementary School</label>
                                <input type="text" name="elem_school" class="form-control" placeholder="Complete School Name">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="small text-muted">Graduation Year</label>
                                <input type="text" name="elem_year" class="form-control" placeholder="YYYY">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-9 mb-2">
                                <label class="small text-muted">Secondary School</label>
                                <input type="text" name="secondary_school" class="form-control" placeholder="Complete School Name">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="small text-muted">Graduation Year</label>
                                <input type="text" name="secondary_year" class="form-control" placeholder="YYYY">
                            </div>
                        </div>
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
                            <label>Gender</label>
                            <select name="gender" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Home Address</label>
                            <input type="text" name="address" class="form-control" list="addresslist" placeholder="House No., Street, Brgy.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Municipality/City</label>
                            <input type="text" name="municipality" class="form-control" list="municipalitylist" placeholder="Municipality/City">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Religion</label>
                            <input type="text" name="religion" class="form-control" list="religionlist">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Program (Course)</label>
                            <select name="program_id" class="form-select" required>
                                <option value="">-- Select Program --</option>
                                <?php foreach ($programs_list as $prog): ?>
                                    <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['program_name'] ?? ''); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label>Diploma Program</label>
                            <select name="dept_id" class="form-select" required>
                                <option value="">-- Select Diploma Program --</option>
                                <?php foreach ($departments_list as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program'] ?? ''); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
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
                    <button type="submit" class="btn btn-primary">Create Student</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="student_id" id="edit_student_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="disqualification_alert" class="alert alert-danger py-2 d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>Disqualified:</strong> This student has backlogs (INC, Dropped, or 5.00). Honors and Graduation status are restricted.
                    </div>
                    <div class="mb-3">
                        <label>Student Number</label>
                        <input type="text" name="student_no" id="edit_student_no" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Birth Date</label>
                        <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold text-primary border-bottom pb-1 d-block mb-3">Educational Background</label>
                        <div class="row">
                            <div class="col-md-9 mb-2">
                                <label class="small text-muted">Elementary School</label>
                                <input type="text" name="elem_school" id="edit_elem_school" class="form-control">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="small text-muted">Graduation Year</label>
                                <input type="text" name="elem_year" id="edit_elem_year" class="form-control" placeholder="YYYY">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-9 mb-2">
                                <label class="small text-muted">Secondary School</label>
                                <input type="text" name="secondary_school" id="edit_secondary_school" class="form-control">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="small text-muted">Graduation Year</label>
                                <input type="text" name="secondary_year" id="edit_secondary_year" class="form-control" placeholder="YYYY">
                            </div>
                        </div>
                    </div>
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
                            <label>Gender</label>
                            <select name="gender" id="edit_gender" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Home Address</label>
                            <input type="text" name="address" id="edit_address" class="form-control" list="addresslist">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Municipality/City</label>
                            <input type="text" name="municipality" id="edit_municipality" class="form-control" list="municipalitylist">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Religion</label>
                            <input type="text" name="religion" id="edit_religion" class="form-control" list="religionlist">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Program (Course)</label>
                            <select name="program_id" id="edit_program_id" class="form-select" required>
                                <option value="">-- Select Program --</option>
                                <?php foreach ($programs_list as $prog): ?>
                                    <option value="<?php echo $prog['program_id']; ?>"><?php echo htmlspecialchars($prog['program_name']); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label>Diploma Program</label>
                            <select name="dept_id" id="edit_dept_id" class="form-select" required>
                                <option value="">-- Select Diploma Program --</option>
                                <?php foreach ($departments_list as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['title_diploma_program']); ?></option>
                                <?php
endforeach; ?>
                            </select>
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Year Level</label>
                            <select name="year_level" id="edit_year_level" class="form-select" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Status</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="graduated">Graduated</option>
                                <option value="dropped">Dropped</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="fw-bold text-primary"><i class="fas fa-medal me-1"></i> Academic Honor (Manual Evaluation)</label>
                            <select name="academic_honor" id="edit_academic_honor" class="form-select border-primary">
                                <option value="">-- No Honor Assigned --</option>
                                <option value="With Honor">With Honor</option>
                                <option value="With High Honor">With High Honor</option>
                                <option value="With Highest Honor">With Highest Honor</option>
                                <option value="Cum Laude">Cum Laude</option>
                                <option value="Magna Cum Laude">Magna Cum Laude</option>
                                <option value="Summa Cum Laude">Summa Cum Laude</option>
                            </select>
                            <small class="text-muted">Only assign this after careful evaluation of grades and residency.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Student</button>
                </div>
            </div>
        </form>
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
function editStudent(data) {
    document.getElementById('edit_student_id').value = data.student_id;
    document.getElementById('edit_student_no').value = data.student_no;
    document.getElementById('edit_date_of_birth').value = data.date_of_birth;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_gender').value = data.gender || 'Male';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('edit_municipality').value = data.municipality || '';
    document.getElementById('edit_religion').value = data.religion || '';
    document.getElementById('edit_contact_number').value = data.contact_number || '';
    document.getElementById('edit_email').value = data.email || '';
    
    document.getElementById('edit_elem_school').value = data.elem_school || '';
    document.getElementById('edit_elem_year').value = data.elem_year || '';
    document.getElementById('edit_secondary_school').value = data.secondary_school || '';
    document.getElementById('edit_secondary_year').value = data.secondary_year || '';
    
    document.getElementById('edit_program_id').value = data.program_id || '';
    document.getElementById('edit_dept_id').value = data.dept_id || '';
    document.getElementById('edit_year_level').value = data.year_level;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_academic_honor').value = data.academic_honor || '';

    // Handle Disqualification UI
    const isDisqualified = (parseInt(data.grade_backlog) > 0 || parseInt(data.enrollment_backlog) > 0);
    const alert = document.getElementById('disqualification_alert');
    const honorSelect = document.getElementById('edit_academic_honor');
    const statusSelect = document.getElementById('edit_status');

    if (isDisqualified) {
        alert.classList.remove('d-none');
        honorSelect.disabled = true;
        
        statusSelect.addEventListener('change', function() {
            if (this.value === 'graduated') {
                alert('Warning: This student has backlogs and is technically unqualified for graduation.');
                if (data.status !== 'graduated') {
                    this.value = data.status; // Revert
                }
            }
        });
    } else {
        alert.classList.add('d-none');
        honorSelect.disabled = false;
    }

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
