<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole(['registrar', 'registrar_staff']);

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
        logAudit($userId, 'UPDATE', 'users', $userId, null, 'Updated profile');
        redirectWithMessage('profile.php', 'Profile updated successfully', 'success');
    }
    else {
        $error = "Error updating profile: " . $conn->error;
    }
    if (isset($stmt))
        $stmt->close();
}

$user = getUserProfile($userId, getCurrentUserRole());
$pageTitle = 'My Profile';
require_once '../includes/header.php';
?>

<style>
    .profile-card {
        border: none;
        border-radius: 1.5rem;
        box-shadow: 0 20px 50px rgba(0,0,0,0.08), 0 5px 15px rgba(0,0,0,0.03);
        background: #fff;
        overflow: hidden;
    }
    .profile-sidebar {
        background: linear-gradient(160deg, #0038A8 0%, #001a4d 100%);
        color: #fff;
        padding: 4rem 2rem;
        text-align: center;
        position: relative;
    }
    .profile-sidebar::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 86c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zm66-3c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zm-46-45c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm26 18c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm16 18c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        pointer-events: none;
    }
    .profile-avatar {
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border: 4px solid rgba(255,255,255,0.3);
        border-radius: 2.5rem;
        margin: 0 auto 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        font-weight: 800;
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
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
        border-radius: 2.5rem;
        color: white;
    }
    .loader-circle {
        width: 48px;
        height: 48px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top: 4px solid #fff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 12px;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .loader-text {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        opacity: 0.9;
    }
    .profile-form-container {
        padding: 4rem;
        background: #fff;
    }
    .badge-status {
        font-size: 0.85rem;
        font-weight: 700;
        padding: 0.6rem 1.5rem;
        border-radius: 2rem;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.2);
        backdrop-filter: blur(5px);
    }
</style>

<div class="row justify-content-center mt-4">
    <div class="col-xl-11">
        <div class="profile-card">
            <div class="row g-0">
                <!-- Sidebar Info -->
                <div class="col-lg-4 profile-sidebar">
                    <div class="profile-avatar" id="profilePicPreview" style="position: relative; overflow: visible;">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 2.5rem;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['username'] ?? 'R', 0, 1)); ?>
                        <?php endif; ?>
                        
                        <label for="profileImageInput" style="
                            position: absolute;
                            bottom: -5px;
                            right: -5px;
                            background: #fff;
                            width: 38px;
                            height: 38px;
                            border-radius: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: #0038A8;
                            cursor: pointer;
                            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                            z-index: 10;
                            font-size: 1.1rem;
                        " title="Update Profile Picture">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" id="profileImageInput" accept="image/jpeg,image/png" style="display: none;">
                        
                        <div class="upload-progress" id="uploadProgress">
                            <div class="loader-circle"></div>
                            <div class="loader-text">Processing...</div>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($user['username'] ?? 'Registrar'); ?></h2>
                    <p class="opacity-75 mb-4 text-uppercase tracking-widest small fw-bold"><?php echo $user['role'] ?? 'Registrar'; ?></p>
                    <div class="d-inline-block badge-status mb-5">
                        <i class="fas fa-check-circle me-2"></i> Verified Official
                    </div>
                    
                    <div class="mt-5 text-start border-top border-white border-opacity-10 pt-4 px-3">
                        <div class="mb-3 d-flex align-items-center">
                            <i class="fas fa-clock me-3 opacity-50"></i>
                            <div>
                                <div class="small opacity-50 text-uppercase fw-bold" style="font-size: 0.65rem;">Last Activity</div>
                                <div class="small fw-medium"><?php echo isset($user['last_login']) ? formatDateTime($user['last_login']) : 'N/A'; ?></div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-calendar-check me-3 opacity-50"></i>
                            <div>
                                <div class="small opacity-50 text-uppercase fw-bold" style="font-size: 0.65rem;">Joined On</div>
                                <div class="small fw-medium"><?php echo isset($user['created_at']) ? formatDateTime($user['created_at']) : 'N/A'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Form -->
                <div class="col-lg-8">
                    <div class="profile-form-container">
                        <div class="d-flex align-items-center mb-5">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3 text-primary">
                                <i class="fas fa-user-shield fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-0">Registrar Settings</h3>
                                <p class="text-muted mb-0">Secure your portal credentials and administrative email.</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <?php csrfField(); ?>
                            <div class="premium-input-group mb-4">
                                <label class="form-label mb-2">Official Username</label>
                                <div class="input-wrapper">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled style="cursor: not-allowed; opacity: 0.7;">
                                    <i class="fas fa-id-badge"></i>
                                </div>
                                <div class="form-text mt-2 ps-1 small"><i class="fas fa-lock me-1"></i> System-assigned names are fixed for security auditing.</div>
                            </div>
                            
                            <div class="premium-input-group mb-4">
                                <label class="form-label mb-2">Registrar Email</label>
                                <div class="input-wrapper">
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required placeholder="registrar@tesda.gov.ph">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>

                            <div class="premium-input-group mb-4">
                                <label class="form-label mb-2">Update Security Key</label>
                                <div class="input-wrapper">
                                    <input type="password" name="password" class="form-control" placeholder="••••••••••••••">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="form-text mt-2 ps-1 text-primary fw-medium small">
                                    <i class="fas fa-info-circle me-1"></i> Leave empty to maintain your current security standards.
                                </div>
                            </div>

                            <div class="mt-5 pt-2">
                                <button type="submit" name="update_profile" class="btn btn-create-profile btn-lg px-5">
                                    <i class="fas fa-save me-2"></i> Update Official Profile
                                </button>
                            </div>
                        </form>
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
