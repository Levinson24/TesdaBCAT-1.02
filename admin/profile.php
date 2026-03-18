<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('admin');

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

$user = getUserProfile($userId, 'admin');
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
        background: linear-gradient(160deg, #1a3a5c 0%, #0c1f33 100%);
        color: #fff;
        padding: 4rem 2rem;
        text-align: center;
        position: relative;
    }
    .profile-sidebar::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 86c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zm66-3c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zm-46-45c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm26 18c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1zm16 18c.552 0 1-.448 1-1s-.448-1-1-1-1 .448-1 1 .448 1 1 1z' fill='%23ffffff' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
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
    .profile-form-container {
        padding: 4rem;
        background: #fff;
    }
    .form-group-custom {
        margin-bottom: 2.5rem;
    }
    .form-label-custom {
        font-weight: 700;
        color: var(--text-muted);
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 0.75rem;
        display: block;
    }
    .input-group-custom {
        position: relative;
    }
    .input-group-custom .fas {
        position: absolute;
        left: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary-indigo);
        z-index: 10;
        opacity: 0.7;
        font-size: 1.1rem;
    }
    .input-group-custom .form-control {
        padding-left: 4rem;
        height: 4rem;
        border-radius: 1.25rem;
        border: 2px solid #f1f5f9;
        background-color: #f8fafc;
        font-size: 1.05rem;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .input-group-custom .form-control:focus {
        background-color: #fff;
        border-color: var(--primary-indigo);
        box-shadow: 0 10px 25px rgba(26, 58, 92, 0.12);
        transform: translateY(-2px);
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
    .btn-save {
        border-radius: 1.25rem;
        padding: 1rem 3rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        font-size: 0.9rem;
        box-shadow: 0 10px 25px rgba(26, 58, 92, 0.35);
        transition: all 0.3s;
    }
    .btn-save:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 35px rgba(26, 58, 92, 0.45);
    }
</style>

<div class="row justify-content-center mt-4">
    <div class="col-xl-11">
        <div class="profile-card">
            <div class="row g-0">
                <!-- Sidebar Info -->
                <div class="col-lg-4 profile-sidebar">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['username'] ?? 'A', 0, 1)); ?>
                    </div>
                    <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($user['username'] ?? 'Admin'); ?></h2>
                    <p class="opacity-75 mb-4 text-uppercase tracking-widest small fw-bold"><?php echo $user['role'] ?? 'Administrator'; ?></p>
                    <div class="d-inline-block badge-status mb-5">
                        <i class="fas fa-shield-alt me-2"></i> Authorized Access
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
                                <i class="fas fa-user-cog fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-0">Profile Management</h3>
                                <p class="text-muted mb-0">Keep your account credentials secure and up-to-date.</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <?php csrfField(); ?>
                            <div class="form-group-custom">
                                <label class="form-label-custom">Account Username</label>
                                <div class="input-group-custom">
                                    <i class="fas fa-user-shield"></i>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled style="cursor: not-allowed; opacity: 0.7;">
                                </div>
                                <div class="form-text mt-2 ps-2"><i class="fas fa-lock me-1"></i> System-level username cannot be altered.</div>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom">Primary Email</label>
                                <div class="input-group-custom">
                                    <i class="fas fa-envelope-open-text"></i>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required placeholder="your.name@example.com">
                                </div>
                            </div>

                            <div class="form-group-custom">
                                <label class="form-label-custom">Security (New Password)</label>
                                <div class="input-group-custom">
                                    <i class="fas fa-key"></i>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••••••••">
                                </div>
                                <div class="form-text mt-2 ps-2 text-primary fw-medium">
                                    <i class="fas fa-info-circle me-1"></i> Only enter a value if you wish to reset your current password.
                                </div>
                            </div>

                            <div class="mt-5 pt-2">
                                <button type="submit" name="update_profile" class="btn btn-primary btn-lg btn-save">
                                    <i class="fas fa-check-double me-2"></i> Synchronize Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
