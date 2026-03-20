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
            $conn->query("DELETE FROM users WHERE user_id = $userId");
            logAudit(getCurrentUserId(), 'DELETE', 'students', $studentId, null, "Deleted student profile (ID: $studentId) and associated user account.");
            redirectWithMessage('students.php', 'Student and account deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Students';
require_once '../includes/header.php';
?>
<style>
    .bg-dark-navy {
        background-color: #0f172a !important;
    }
    .premium-card {
        border-radius: 1rem;
    }
</style>
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
    SELECT s.*, u.username, u.status as user_status, d.title_diploma_program as dept_name, p.program_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    ORDER BY s.created_at DESC
");
// === Premium Styles ===
?>
<style>
    .bg-dark-navy { background-color: #0f172a !important; }
    .premium-card { border-radius: 1rem; }
    .students-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.70rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .students-table tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        color: #334155;
    }
    /* Premium Action Buttons */
    .btn-premium-edit {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 1.2rem;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50px;
        color: #334155;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        text-decoration: none;
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

<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-user-graduate me-2 text-info"></i> Student Academic Registry
        </h5>
        <div class="d-flex gap-2 pe-2">
            <button class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold border-0" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
            </button>
            <a href="generate_report.php?type=students" class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary">
                <i class="fas fa-file-export me-2"></i> Export List
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 students-table data-table">
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
                    <tr>
                        <td class="ps-4" data-label="Student ID / No">
                            <span class="fw-bold text-primary">#<?php echo htmlspecialchars($student['student_no']); ?></span>
                        </td>
                        <td data-label="Student Name & Identity">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px;">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                    <div class="text-muted" style="font-size: 0.70rem;"><i class="fas fa-fingerprint me-1"></i> UID: <?php echo $student['user_id']; ?></div>
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
                            <?php
                                $statusColors = [
                                    'active' => 'success',
                                    'inactive' => 'secondary',
                                    'graduated' => 'primary',
                                    'dropped' => 'danger'
                                ];
                                $color = $statusColors[$student['status']] ?? 'secondary';
                            ?>
                            <span class="badge rounded-pill bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> px-3">
                                <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> <?php echo ucfirst($student['status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4" data-label="Control Actions">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="view_student.php?id=<?php echo $student['student_id']; ?>" class="btn-premium-edit">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn-premium-edit" onclick='editStudent(<?php echo json_encode($student); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this student record? This cannot be undone.')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                    <button type="submit" class="btn-premium-delete">
                                        <i class="fas fa-trash"></i>
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

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="student_id" id="edit_student_id">
            <div class="modal-content">
                <div class="modal-header"><h5>Edit Student Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label>Student Number</label><input type="text" name="student_no" id="edit_student_no" class="form-control" required></div>
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
                            <label>Gender</label>
                            <select name="gender" id="edit_gender" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Religion</label>
                            <input type="text" name="religion" id="edit_religion" class="form-control" list="religionlist">
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
                            <label>Program (Course)</label>
                            <select name="program_id" id="edit_program_id" class="form-select" required>
                                <option value="">-- Select Program --</option>
                                <?php foreach ($all_programs as $p): ?>
                                    <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
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
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Status</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="graduated">Graduated</option>
                                <option value="dropped">Dropped (Clears Current Load)</option>
                            </select>
                            <div class="form-text small text-danger">Selecting 'Dropped' will automatically remove this student's current semester enrollments.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Academic Honor / Title</label>
                            <input type="text" name="academic_honor" id="edit_academic_honor" class="form-control" placeholder="e.g. Cum Laude, With Distinction" list="honorlist">
                        </div>
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
