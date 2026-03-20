<?php
/**
 * Department Head - Profile
 * Similar to instructor/profile.php but for dept_head
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('dept_head');

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
        logAudit($userId, 'UPDATE', 'users', $userId, null, 'Updated dept head profile');
        redirectWithMessage('profile.php', 'Profile updated successfully', 'success');
    }
    else {
        $error = "Error updating profile: " . $conn->error;
    }
}

$user = getUserProfile($userId, 'dept_head');

$pageTitle = 'My Profile';
require_once '../includes/header.php';
?>

<style>
    .profile-card-header { background: #002366; color: white; }
    .text-indigo { color: #0038A8; }
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
        background: rgba(0, 56, 168, 0.1);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        margin-right: 12px;
    }
    .profile-pic-preview-container {
        position: relative;
        width: 140px;
        height: 140px;
        margin: 0 auto;
    }
    .profile-pic-preview {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: rgba(0, 56, 168, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 4px solid #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    .profile-pic-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .upload-btn-overlay {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: #0038A8;
        color: white;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 3px solid #fff;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transition: all 0.2s ease;
    }
    .upload-btn-overlay:hover {
        transform: scale(1.1);
        background: #002366;
    }
    .upload-progress-overlay {
        position: absolute;
        top: 0; left: 0; bottom: 0; right: 0;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(4px);
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        border-radius: 50%;
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

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="profile-card p-4 text-center h-100">
                <div class="profile-pic-preview-container mb-4">
                    <div class="profile-pic-preview" id="profilePicPreview">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user-tie fa-4x text-primary"></i>
                        <?php endif; ?>
                        <div class="upload-progress-overlay" id="uploadProgress">
                            <div class="loader-circle"></div>
                            <div class="loader-text" style="font-size:0.6rem;">Processing...</div>
                        </div>
                    </div>
                    <label for="profileImageInput" class="upload-btn-overlay" title="Update Profile Picture">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profileImageInput" accept="image/jpeg,image/png" style="display: none;">
                </div>
                <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($user['username']); ?></h3>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 mb-4">Department Head</span>
                
                <div class="alert alert-info border-0 shadow-sm rounded-4 text-start">
                    <i class="fas fa-info-circle me-2"></i>
                    <small>You are the head of the <b><?php echo htmlspecialchars($user['dept_name'] ?? 'Assigned'); ?></b> Diploma Program.</small>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="profile-card p-4">
                <div class="profile-section-title">
                    <i class="fas fa-id-card"></i> Account Information
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="profile-info-label">Full Name</div>
                        <div class="profile-info-value"><?php echo htmlspecialchars($user['username']); ?> (Dept Head)</div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-info-label">Diploma Program Jurisdiction</div>
                        <div class="profile-info-value fw-bold text-primary"><?php echo htmlspecialchars($user['dept_name'] ?? 'Unassigned'); ?></div>
                    </div>
                </div>

                <form method="POST" class="mt-4">
                    <?php csrfField(); ?>
                    <div class="profile-section-title">
                        <i class="fas fa-user-edit"></i> Edit Credentials
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-lg rounded-4 border-light-subtle" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control form-control-lg rounded-4 border-light-subtle" placeholder="••••••••">
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary btn-lg rounded-4 px-5">
                        Save Changes
                    </button>
                </form>
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

        const allowedTypes = ["image/jpeg", "image/png"];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({ icon: "error", title: "Invalid File Type", text: "Please select a JPG or PNG image." });
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: "error", title: "File Too Large", text: "Image size must be less than 5MB." });
            return;
        }

        const formData = new FormData();
        formData.append("profile_image", file);
        formData.append("csrf_token", "' . getCSRFToken() . '");
        
        const startTime = Date.now();
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
                        Swal.fire({ icon: "error", title: "Upload Failed", text: response.message });
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
