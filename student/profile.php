<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('student');

$conn = getDBConnection();
$userId = getCurrentUserId();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('profile.php', 'Invalid security token. Please try again.', 'danger');
    }
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($password)) {
        $hashedPassword = hashPassword($password);
        $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $email, $hashedPassword, $userId);
    }
    else {
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $stmt->bind_param("si", $email, $userId);
    }

    if ($stmt->execute()) {
        // Also update the email in the students table
        $stmt2 = $conn->prepare("UPDATE students SET email = ? WHERE user_id = ?");
        $stmt2->bind_param("si", $email, $userId);
        $stmt2->execute();

        logAudit($userId, 'UPDATE', 'users', $userId, null, 'Updated student profile');
        redirectWithMessage('profile.php', 'Profile updated successfully', 'success');
    }
    else {
        $error = "Error updating profile: " . $conn->error;
    }
}

$stmt = $conn->prepare("
    SELECT s.*, u.profile_image, p.program_name, d.title_diploma_program as dept_name, col.college_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN colleges col ON d.college_id = col.college_id
    WHERE s.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$pageTitle = 'My Profile';
require_once '../includes/header.php';
?>

<style>
    .profile-card {
        border: none;
        border-radius: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05), 0 1px 8px rgba(0,0,0,0.02);
        background: #fff;
        overflow: hidden;
    }
    .profile-info-label {
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.25rem;
    }
    .profile-info-value {
        font-size: 1.1rem;
        font-weight: 500;
        color: var(--text-main);
        margin-bottom: 2rem;
    }
    .profile-section-title {
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 1rem;
        margin-bottom: 2rem;
        color: var(--primary-indigo);
        font-size: 1.2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
    }
    .profile-section-title i {
        width: 32px;
        height: 32px;
        background: rgba(79, 70, 229, 0.1);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        margin-right: 12px;
    }
    .profile-card-header { background: #002366; color: white; }
    .text-indigo { color: #0038A8; }
    .profile-item {
        background: #f8fafc;
        padding: 1.25rem;
        border-radius: 1rem;
        border: 1px solid #f1f5f9;
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
    }
    .profile-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }
    .profile-pic-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 2rem;
    }
    .profile-pic {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 1.5rem;
        border: 4px solid #fff;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .profile-pic img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-pic-overlay {
        position: absolute;
        bottom: -10px;
        right: -10px;
        background: var(--primary-indigo);
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0, 56, 168, 0.3);
        border: 3px solid #fff;
        z-index: 10;
    }
    .profile-pic-overlay:hover {
        transform: scale(1.1) rotate(5deg);
        background: var(--secondary-indigo);
    }
    .profile-pic-overlay i {
        font-size: 1.1rem;
    }
    #profileImageInput {
        display: none;
    }
    .upload-progress {
        position: absolute;
        top: 0; left: 0; bottom: 0; right: 0;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(4px);
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        border-radius: 1.25rem;
        color: white;
    }
    .loader-circle {
        width: 38px;
        height: 38px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-top: 3px solid #fff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 8px;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .loader-text {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        opacity: 0.9;
    }
</style>

<div class="row justify-content-center mt-4">
    <div class="col-xl-11">
        <div class="profile-card">
            <div class="card-header profile-card-header py-3 px-4 border-0">
                <div class="d-flex align-items-center">
                    <div class="bg-white rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user-graduate text-primary"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">My Student Profile</h5>
                </div>
            </div>
            <div class="card-body p-4 p-md-5">
                <!-- Profile Image Section -->
                <div class="profile-pic-container">
                    <div class="profile-pic" id="profilePicPreview">
                        <?php if (!empty($student['profile_image'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($student['profile_image']); ?>?v=<?php echo time(); ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user-graduate fa-4x text-light"></i>
                        <?php endif; ?>
                        <div class="upload-progress" id="uploadProgress">
                            <div class="loader-circle"></div>
                            <div class="loader-text">Processing...</div>
                        </div>
                    </div>
                    <label for="profileImageInput" class="profile-pic-overlay" title="Update Profile Picture">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profileImageInput" accept="image/jpeg,image/png">
                </div>

                <div class="row g-5">
                    <!-- Personal Information -->
                    <div class="col-lg-6">
                        <h5 class="profile-section-title">
                            <i class="fas fa-user"></i> Personal Details
                        </h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Full Name</div>
                                    <div class="profile-info-value mb-0"><?php echo htmlspecialchars(($student['first_name'] ?? '') . (($student['middle_name'] ?? '') ? ' ' . $student['middle_name'] : '') . ' ' . ($student['last_name'] ?? '')); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Student ID</div>
                                    <div class="profile-info-value text-primary fw-bold mb-0"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Date of Birth</div>
                                    <div class="profile-info-value mb-0"><?php echo formatDate($student['date_of_birth']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Gender</div>
                                    <div class="profile-info-value mb-0"><?php echo htmlspecialchars($student['gender'] ?? 'Not Specified'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Religion</div>
                                    <div class="profile-info-value mb-0"><?php echo htmlspecialchars($student['religion'] ?? 'Not Specified'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Home Address</div>
                                    <div class="profile-info-value mb-0"><?php echo nl2br(htmlspecialchars($student['address'] ?? 'No address provided')); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Municipality/City</div>
                                    <div class="profile-info-value mb-0"><?php echo htmlspecialchars($student['municipality'] ?? 'Not Specified'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic & Contact Information -->
                    <div class="col-lg-6">
                        <h5 class="profile-section-title">
                            <i class="fas fa-graduation-cap"></i> Academic & Contact
                        </h5>
                        <div class="row g-3">
                            <?php if (!empty($student['academic_honor'])): ?>
                            <div class="col-12">
                                <div class="profile-item" style="background-color: #f0fdf4; border-color: #bbf7d0;">
                                    <div class="profile-info-label text-success"><i class="fas fa-medal me-1"></i> Academic Honor</div>
                                    <div class="profile-info-value fw-bold text-success fs-5 mb-0"><?php echo htmlspecialchars($student['academic_honor'] ?? ''); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Current Program (Course)</div>
                                    <div class="profile-info-value fw-bold text-primary mb-0"><?php echo htmlspecialchars($student['program_name'] ?? 'No program assigned'); ?></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">College / Diploma Program</div>
                                    <div class="profile-info-value mb-0 small text-muted">
                                        <?php echo htmlspecialchars(($student['college_name'] ? $student['college_name'] . ' - ' : '') . ($student['dept_name'] ?? 'N/A')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Year Level</div>
                                    <div class="profile-info-value mb-0"><?php echo htmlspecialchars($student['year_level']); ?><?php
$suffix = ['th', 'st', 'nd', 'rd'];
echo($student['year_level'] < 4) ? $suffix[$student['year_level']] : 'th';
?> Year</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-item">
                                    <div class="profile-info-label">Account Status</div>
                                    <div class="profile-info-value mb-0">
                                        <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?> px-4 py-2 rounded-pill shadow-sm">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Email Address</div>
                                    <div class="profile-info-value mb-0"><?php echo htmlspecialchars($student['email'] ?? 'No email provided'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="profile-item">
                                    <div class="profile-info-label">Contact Number</div>
                                    <div class="profile-info-value mb-0"><?php echo htmlspecialchars($student['contact_number'] ?? 'No contact number'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Security Section -->
                <div class="row mt-5">
                    <div class="col-12 px-5">
                        <div class="p-4 bg-light rounded-4 border">
                            <h5 class="fw-bold mb-4 text-primary d-flex align-items-center">
                                <i class="fas fa-shield-alt me-2"></i> Account Security Settings
                            </h5>
                            <form method="POST">
                                <?php csrfField(); ?>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold small text-muted text-uppercase">Portal Email Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white border-end-0 text-primary">
                                                    <i class="fas fa-envelope"></i>
                                                </span>
                                                <input type="email" name="email" class="form-control border-start-0 ps-0" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold small text-muted text-uppercase">New Security Key (Optional)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white border-end-0 text-primary">
                                                    <i class="fas fa-key"></i>
                                                </span>
                                                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••••••••">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="update_profile" class="btn btn-primary px-5 py-2 fw-bold rounded-pill">
                                            <i class="fas fa-save me-2"></i> Update Security Credentials
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$additionalJS = '
<script>
$(document).ready(function() {
    $("#profileImageInput").on("change", function() {
        const file = this.files[0];
        if (!file) return;

        // Basic validation
        const allowedTypes = ["image/jpeg", "image/png"];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: "error",
                title: "Invalid File Type",
                text: "Please select a JPG or PNG image."
            });
            return;
        }

        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            Swal.fire({
                icon: "error",
                title: "File Too Large",
                text: "Image size must be less than 5MB."
            });
            return;
        }

        const formData = new FormData();
        formData.append("profile_image", file);
        formData.append("csrf_token", "' . getCSRFToken() . '");
        
        const startTime = Date.now();
        // Show progress
        $("#uploadProgress").css("display", "flex");

        $.ajax({
            url: "' . BASE_URL . 'includes/ajax/update_profile_image.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const elapsed = Date.now() - startTime;
                const minDelay = 5000; // 5 seconds
                const remaining = Math.max(0, minDelay - elapsed);

                setTimeout(function() {
                    $("#uploadProgress").hide();
                    if (response.success) {
                        window.location.reload();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Upload Failed",
                            text: response.message
                        });
                    }
                }, remaining);
            },
            error: function(xhr, status, error) {
                const elapsed = Date.now() - startTime;
                const minDelay = 2000; // 2 seconds for errors
                const remaining = Math.max(0, minDelay - elapsed);

                setTimeout(function() {
                    $("#uploadProgress").hide();
                    console.error("Upload Error:", status, error, xhr.responseText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "An unexpected error occurred. Status: " + status + ", Error: " + error
                    });
                }, remaining);
            }
        });
    });
});
</script>
';
require_once '../includes/footer.php'; 
?>
