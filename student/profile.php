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
    :root {
        --profile-gradient: linear-gradient(135deg, #002366 0%, #0038A8 100%);
        --card-shadow-premium: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .profile-card {
        border: none;
        border-radius: 2rem;
        box-shadow: var(--card-shadow-premium);
        background: #fff;
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .cinema-header {
        height: 180px;
        background: var(--profile-gradient);
        position: relative;
        overflow: hidden;
    }

    .cinema-header::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('data:image/svg+xml,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><g fill="%23ffffff" fill-opacity="0.05" fill-rule="evenodd"><circle cx="3" cy="3" r="3"/><circle cx="13" cy="13" r="3"/></g></svg>');
    }

    .profile-main-content {
        position: relative;
        padding: 0 1.5rem 2rem;
        margin-top: -60px;
        text-align: center;
    }

    .profile-pic-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 1.5rem;
        z-index: 10;
    }

    .profile-pic {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 2.5rem;
        border: 6px solid #fff;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        background-color: #f1f5f9;
        background-image: url('<?php echo BASE_URL; ?>BCAT logo 2024.png');
        background-size: 100% auto;
        background-position: center;
        background-repeat: no-repeat;
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
        bottom: 0;
        right: 0;
        background: #fff;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-indigo);
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 11;
    }

    .profile-pic-overlay:hover { transform: scale(1.1); color: var(--secondary-indigo); }

    .student-name { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.5rem; color: #1e293b; margin-bottom: 0.25rem; }
    .student-id-pill { background: rgba(0, 56, 168, 0.08); color: #0038A8; font-weight: 700; font-size: 0.85rem; padding: 0.4rem 1.2rem; border-radius: 2rem; display: inline-block; margin-bottom: 2rem; }

    .stat-highlight-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-bottom: 2.5rem;
    }

    .stat-highlight-item {
        background: #f8fafc;
        padding: 1rem 0.5rem;
        border-radius: 1.25rem;
        border: 1px solid #f1f5f9;
    }

    .stat-highlight-label { font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
    .stat-highlight-value { font-size: 0.9rem; font-weight: 800; color: #0f172a; }

    .profile-section-card {
        background: #fff;
        border-radius: 1.5rem;
        padding: 1.5rem;
        border: 1px solid #f1f5f9;
        margin-bottom: 1.5rem;
        text-align: left;
    }

    .section-title-premium {
        font-size: 1rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
    }

    .section-title-premium i {
        width: 32px;
        height: 32px;
        background: rgba(0, 56, 168, 0.1);
        color: #0038A8;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        font-size: 0.9rem;
    }

    .info-row {
        display: flex;
        flex-direction: column;
        margin-bottom: 1.25rem;
    }

    .info-row:last-child { margin-bottom: 0; }
    .info-label { font-size: 0.7rem; font-weight: 800; color: #0038A8; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.3rem; opacity: 0.8; }
    .info-value { font-size: 0.95rem; font-weight: 600; color: #334155; }

    .security-section {
        background: #f8fafc;
        border-radius: 2rem;
        padding: 2rem 1.5rem;
        margin-top: 1rem;
    }

    .premium-input-group {
        background: #fff;
        border-radius: 1rem;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        transition: all 0.3s;
        margin-bottom: 1rem;
    }

    .premium-input-group:focus-within {
        border-color: #0038A8;
        box-shadow: 0 0 0 4px rgba(0, 56, 168, 0.1);
    }

    .premium-input-group i { color: #94a3b8; margin-right: 1rem; }
    .premium-input-group input { border: none; outline: none; width: 100%; font-weight: 600; color: #1e293b; background: transparent; }

    #profileImageInput { display: none; }
    
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
        border-radius: 2.5rem;
        color: white;
    }

    @media (max-width: 576px) {
        .stat-highlight-grid { gap: 0.5rem; }
        .stat-highlight-value { font-size: 0.8rem; }
        .profile-card { border-radius: 1.5rem; }
        .cinema-header { height: 140px; }
    }
</style>

<div class="row justify-content-center mt-3">
    <div class="col-xl-10">
        <div class="profile-card">
            <!-- Cinema Header -->
            <div class="cinema-header"></div>

            <div class="profile-main-content">
                <!-- Overlapping Profile Pic -->
                <div class="profile-pic-container">
                    <div class="profile-pic" id="profilePicPreview">
                        <?php if (!empty($student['profile_image'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($student['profile_image']); ?>?v=<?php echo time(); ?>" alt="Profile">
                        <?php
else: ?>
                            <img src="<?php echo BASE_URL; ?>BCAT logo 2024.png" alt="Default Branding" style="width: 100%; height: 100%; object-fit: cover; opacity: 1;">
                        <?php
endif; ?>
                        <div class="upload-progress" id="uploadProgress">
                            <div class="spinner-border text-white spinner-border-sm mb-2" role="status"></div>
                            <div class="small fw-bold">Syncing...</div>
                        </div>
                    </div>
                    <label for="profileImageInput" class="profile-pic-overlay shadow-sm" title="Change Photo">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profileImageInput" accept="image/jpeg,image/png,image/webp">
                </div>

                <h2 class="student-name"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h2>
                <div class="student-id-pill"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></div>
                
                <div class="mb-4">
                    <div style="color: #0038A8; font-weight: 600; font-size: 1rem; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($student['program_name'] ?? 'No Program Assigned'); ?>
                    </div>
                    <span class="badge rounded-pill px-4 py-2" style="background: #10b981; color: white; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">
                        Enrolled
                    </span>
                </div>

                <!-- Stat Grid -->
                <div class="stat-highlight-grid">
                    <div class="stat-highlight-item">
                        <div class="stat-highlight-label">Year</div>
                        <div class="stat-highlight-value"><?php
$yl = $student['year_level'] ?? 1;
$suffix = ['th', 'st', 'nd', 'rd'];
echo $yl . ($yl <= 3 ? $suffix[$yl] : 'th');
?></div>
                    </div>
                    <div class="stat-highlight-item">
                        <div class="stat-highlight-label">Status</div>
                        <div class="stat-highlight-value text-success"><?php echo ucfirst($student['status'] ?? 'Active'); ?></div>
                    </div>
                    <div class="stat-highlight-item">
                        <div class="stat-highlight-label">Honor</div>
                        <div class="stat-highlight-value"><?php echo !empty($student['academic_honor']) ? 'Yes' : 'None'; ?></div>
                    </div>
                </div>

                <!-- Information Sections -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="profile-section-card h-100">
                            <h6 class="section-title-premium"><i class="fas fa-id-card"></i> Identity</h6>
                            <div class="info-row">
                                <div class="info-label">Current Program</div>
                                <div class="info-value text-primary"><?php echo htmlspecialchars($student['program_name'] ?? 'Not Set'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Birth Date</div>
                                <div class="info-value"><?php echo !empty($student['date_of_birth']) ? formatDate($student['date_of_birth']) : 'Not Set'; ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Gender</div>
                                <div class="info-value text-capitalize"><?php echo htmlspecialchars($student['gender'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="profile-section-card h-100">
                            <h6 class="section-title-premium"><i class="fas fa-envelope"></i> Contact</h6>
                            <div class="info-row">
                                <div class="info-label">Personal Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['email'] ?? 'Not Set'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Mobile Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['contact_number'] ?? 'Not Set'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Residential Address</div>
                                <div class="info-value x-small"><?php echo htmlspecialchars($student['address'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="security-section text-start">
                    <h6 class="section-title-premium"><i class="fas fa-lock text-danger"></i> Vault Settings</h6>
                    <form method="POST">
                        <?php csrfField(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" placeholder="Login Email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="premium-input-group">
                                    <i class="fas fa-key"></i>
                                    <input type="password" name="password" placeholder="Key Phrase (New)">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                        <div class="mt-4 text-center">
                            <button type="submit" name="update_profile" class="btn-premium-action w-100 py-3">
                                <i class="fas fa-shield-check"></i> Sync Vault Credentials
                            </button>
                        </div>
                        </div>
                    </form>
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
        const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: "warning",
                title: "Format Not Supported",
                text: "Please select a JPG, PNG, or WebP image. (Tip: If you\'re on iPhone, avoid HEIC files and use JPG)."
            });
            return;
        }

        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
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
                const minDelay = 2000; // 2 seconds
                const remaining = Math.max(0, minDelay - elapsed);

                setTimeout(function() {
                    $("#uploadProgress").hide();
                    if (response.success) {
                        window.location.reload();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Upload Failed",
                            text: response.message || "An unknown error occurred during sync."
                        });
                    }
                }, remaining);
            },
            error: function(xhr, status, error) {
                $("#uploadProgress").hide();
                console.error("Upload Error:", status, error, xhr.responseText);
                
                let errorMsg = "An unexpected error occurred.";
                if (xhr.status === 413) errorMsg = "The image file is too large for the server to process.";
                else if (xhr.status === 403) {
                    try {
                        const err = JSON.parse(xhr.responseText);
                        errorMsg = err.message || "Session expired or security mismatch. Please refresh.";
                    } catch(e) {
                         errorMsg = "Session expired or security mismatch. Please refresh the page.";
                    }
                }
                else if (xhr.status === 500) errorMsg = "Server encountered an error while processing the image.";

                Swal.fire({
                    icon: "error",
                    title: "Sync Error",
                    text: errorMsg + " (Status: " + xhr.status + ", Error: " + error + ")"
                });
            }
        });
    });
});
</script>
';
require_once '../includes/footer.php';

?>
