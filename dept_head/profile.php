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
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="profile-card p-4 text-center h-100">
                <div class="position-relative mb-4 d-inline-block">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mx-auto" style="width: 140px; height: 140px;">
                        <i class="fas fa-user-tie fa-4x text-primary"></i>
                    </div>
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

<?php require_once '../includes/footer.php'; ?>
