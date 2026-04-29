<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        $studentId = intval($_POST['student_id']);
        $studentNo = sanitizeInput($_POST['student_no']);
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $middleName = sanitizeInput($_POST['middle_name']);
        $dob = sanitizeInput($_POST['date_of_birth']);
        $gender = sanitizeInput($_POST['gender']);
        $address = sanitizeInput($_POST['address']);
        $municipality = sanitizeInput($_POST['municipality']);
        $religion = sanitizeInput($_POST['religion']);
        $contactNumber = sanitizeInput($_POST['contact_number']);
        $email = sanitizeInput($_POST['email']);
        $programId = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $deptId = intval($_POST['dept_id'] ?? 0);
        $yearLevel = intval($_POST['year_level']);
        $status = sanitizeInput($_POST['status']);
        
        // Fetch current status to detect change to 'dropped'
        $oldStatusStmt = $conn->prepare("SELECT status FROM students WHERE student_id = ?");
        $oldStatusStmt->bind_param("i", $studentId);
        $oldStatusStmt->execute();
        $oldStatusRes = $oldStatusStmt->get_result()->fetch_assoc();
        $oldStatus = $oldStatusRes['status'] ?? '';
        $oldStatusStmt->close();

        $academicHonor = sanitizeInput($_POST['academic_honor']);
        
        $stmt = $conn->prepare("UPDATE students SET student_no = ?, first_name = ?, last_name = ?, middle_name = ?, date_of_birth = ?, gender = ?, address = ?, municipality = ?, religion = ?, contact_number = ?, email = ?, program_id = ?, dept_id = ?, year_level = ?, status = ?, academic_honor = ? WHERE student_id = ?");
        $stmt->bind_param("ssssssssssssiissi", $studentNo, $firstName, $lastName, $middleName, $dob, $gender, $address, $municipality, $religion, $contactNumber, $email, $programId, $deptId, $yearLevel, $status, $academicHonor, $studentId);
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
            redirectWithMessage('students.php', 'Student profile updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $studentId = intval($_POST['student_id']);
        $stmt = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        if ($u = $stmt->get_result()->fetch_assoc()) {
            $userId = $u['user_id'];
            $delUser = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delUser->bind_param("i", $userId);
            $delUser->execute();
            $delUser->close();
            
            logAudit(getCurrentUserId(), 'DELETE', 'students', $studentId, null, "Deleted student profile (ID: $studentId) and associated user account.");
            redirectWithMessage('students.php', 'Student and account deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Students';
require_once '../includes/header.php';
?>

<?php

// === STEP 3: Fetch data ===
$programs_list = $conn->query("SELECT p.*, d.title_diploma_program FROM programs p JOIN departments d ON p.dept_id = d.dept_id WHERE p.status = 'active' ORDER BY p.program_name ASC");
$all_programs = [];
while ($p = $programs_list->fetch_assoc()) {
    $all_programs[] = $p;
}

$dept_res = $conn->query("SELECT dept_id, title_diploma_program FROM departments WHERE status = 'active' ORDER BY title_diploma_program ASC");
$departments_list = [];
while ($d = $dept_res->fetch_assoc()) {
    $departments_list[] = $d;
}

$students = $conn->query("
    SELECT s.*, u.username, u.profile_image, u.status as user_status, d.title_diploma_program as dept_name, p.program_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    ORDER BY s.created_at DESC
");
// === Premium Styles ===
?>


<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top gap-2">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-user-graduate me-2 text-info"></i> Student Academic Registry
        </h5>
        <div class="d-flex gap-2 pe-2 flex-wrap">
            <button class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold border-0" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
            </button>
            <a href="generate_report.php?type=students" class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary">
                <i class="fas fa-file-export me-2"></i><span class="d-none d-sm-inline">Export List</span><span class="d-sm-none">Export</span>
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 students-table premium-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">Student ID / No</th>
                        <th>Student Name & Identity</th>
                        <th>Academic Placement</th>
                        <th>Year Level</th>
                        <th>Contact info</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Control Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                    <tr class="table-row-premium align-middle">
                        <td class="ps-4" data-label="Student ID / No">
                            <span class="fw-bold text-primary">#<?php echo htmlspecialchars($student['student_no']); ?></span>
                        </td>
                        <td class="py-3" data-label="Student Name & Identity">
                            <div class="d-flex align-items-center">
                                <div class="avatar-premium me-3 overflow-hidden">
                                    <?php if (!empty($student['profile_image'])): ?>
                                        <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($student['profile_image']); ?>?v=<?php echo time(); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user-graduate"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="identity-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                    <span class="identity-meta">UID: <?php echo $student['user_id']; ?> • #<?php echo $student['student_no']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Academic Placement">
                            <div class="fw-bold text-dark" style="font-size: 0.8rem;"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></div>
                            <div class="text-muted small" style="font-size: 0.7rem; text-transform: uppercase;"><?php echo htmlspecialchars($student['dept_name'] ?? 'Unassigned'); ?></div>
                        </td>
                        <td data-label="Year Level">
                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.7rem;">Year <?php echo $student['year_level']; ?></span>
                        </td>
                        <td data-label="Contact info">
                            <div class="small"><i class="far fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                            <div class="small"><i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></div>
                        </td>
                        <td data-label="Status">
                            <span class="status-pill status-<?php echo ($student['status'] === 'active') ? 'active' : 'inactive'; ?>">
                                <div class="status-dot" style="background: <?php echo ($student['status'] === 'active') ? '#22c55e' : '#94a3b8'; ?>;"></div> <?php echo ucfirst($student['status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4 py-3" data-label="Control Actions">
                            <div class="table-actions-v2">
                                <a href="view_student.php?id=<?php echo $student['student_id']; ?>" class="btn-premium-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn-premium-edit" onclick='editStudent(<?php echo json_encode($student); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this student record? This cannot be undone.')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                    <button type="submit" class="btn-premium-delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form method="POST" autocomplete="off" class="w-100">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="student_id" id="edit_student_id">
            <div class="modal-content border-0">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Student Profile</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-id-card me-2"></i>Personal Details</span>
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
                                <label>Gender</label>
                                <div class="input-wrapper">
                                    <select name="gender" id="edit_gender" class="form-select">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                    <i class="fas fa-venus-mars"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Religion</label>
                                <div class="input-wrapper">
                                    <input type="text" name="religion" id="edit_religion" class="form-control" list="religionlist">
                                    <i class="fas fa-praying-hands"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-map-marker-alt me-2"></i>Contact & Location</span>
                    </div>
                    <div class="row">
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
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Municipality/City</label>
                                <div class="input-wrapper">
                                    <input type="text" name="municipality" id="edit_municipality" class="form-control" list="municipalitylist">
                                    <i class="fas fa-city"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="premium-input-group">
                                <label>Home Address</label>
                                <div class="input-wrapper">
                                    <input type="text" name="address" id="edit_address" class="form-control" list="addresslist">
                                    <i class="fas fa-home"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-divider">
                        <span><i class="fas fa-graduation-cap me-2"></i>Academic Profile</span>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Student Number</label>
                                <div class="input-wrapper">
                                    <input type="text" name="student_no" id="edit_student_no" class="form-control" required>
                                    <i class="fas fa-hashtag"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
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
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Program (Course)</label>
                                <div class="input-wrapper">
                                    <select name="program_id" id="edit_program_id" class="form-select" required>
                                        <option value="">-- Select Program --</option>
                                        <?php foreach ($all_programs as $p): ?>
                                            <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-certificate"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="premium-input-group">
                                <label>Year Level</label>
                                <div class="input-wrapper">
                                    <select name="year_level" id="edit_year_level" class="form-select" required>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                    <i class="fas fa-level-up-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Academic Honor</label>
                                <div class="input-wrapper">
                                    <input type="text" name="academic_honor" id="edit_academic_honor" class="form-control" placeholder="e.g. Cum Laude" list="honorlist">
                                    <i class="fas fa-medal"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="premium-input-group">
                                <label>Status</label>
                                <div class="input-wrapper">
                                    <select name="status" id="edit_status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="graduated">Graduated</option>
                                        <option value="dropped">Dropped (Clears Current Load)</option>
                                    </select>
                                    <i class="fas fa-toggle-on"></i>
                                </div>
                                <div class="mt-1 small text-danger"><i class="fas fa-exclamation-triangle ms-1 me-1"></i> 'Dropped' clears current enrollments</div>
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

<!-- Program Datalist Removed -->

<!-- Dept Datalist Removed -->

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
 
 <!-- Honor Datalist -->
 <datalist id="honorlist">
    <option value="Summa Cum Laude">
    <option value="Magna Cum Laude">
    <option value="Cum Laude">
    <option value="With High Honor">
    <option value="With Honor">
    <option value="With Distinction">
 </datalist>

<script>
let studentModal;
document.addEventListener('DOMContentLoaded', function() {
    studentModal = new bootstrap.Modal(document.getElementById('editModal'));
});

function editStudent(data) {
    document.getElementById('edit_student_id').value = data.student_id;
    document.getElementById('edit_student_no').value = data.student_no;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_date_of_birth').value = data.date_of_birth;
    document.getElementById('edit_gender').value = data.gender || 'Male';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('edit_municipality').value = data.municipality || '';
    document.getElementById('edit_religion').value = data.religion || '';
    document.getElementById('edit_contact_number').value = data.contact_number || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_program_id').value = data.program_id || '';
    document.getElementById('edit_dept_id').value = data.dept_id || '';
    document.getElementById('edit_year_level').value = data.year_level;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_academic_honor').value = data.academic_honor || '';
    studentModal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
