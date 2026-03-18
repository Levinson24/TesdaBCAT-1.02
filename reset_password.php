<?php
/**
 * Reset Password — Token Validation & New Password Form
 * TESDA-BCAT Grade Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSession();

if (isLoggedIn()) {
    header("Location: " . getCurrentUserRole() . "/dashboard.php");
    exit();
}

$token   = sanitizeInput($_GET['token'] ?? '');
$message = '';
$messageType = 'danger';
$validToken  = false;
$tokenUserId = null;

if (empty($token)) {
    $message = 'Invalid or missing reset token. Please request a new password reset.';
} else {
    $conn   = getDBConnection();
    $stmt   = $conn->prepare("
        SELECT prt.token_id, prt.user_id, u.username
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.user_id
        WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 1) {
        $tokenData  = $result->fetch_assoc();
        $validToken = true;
        $tokenUserId = $tokenData['user_id'];
    } else {
        $message = 'This reset link is invalid or has expired. Please <a href="forgot_password.php">request a new one</a>.';
    }
}

// Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please refresh and try again.';
    } else {
        $newPassword     = $_POST['password']         ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8) {
            $message = 'Password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
        } else {
            $conn = getDBConnection();
            $hashed = hashPassword($newPassword);

            // Update password and reset lockout
            $upd = $conn->prepare("
                UPDATE users SET password = ?, failed_attempts = 0, lockout_until = NULL
                WHERE user_id = ?
            ");
            $upd->bind_param("si", $hashed, $tokenUserId);
            $upd->execute();
            $upd->close();

            // Mark token as used
            $markUsed = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $markUsed->bind_param("s", $token);
            $markUsed->execute();
            $markUsed->close();

            logAudit($tokenUserId, 'PASSWORD_RESET', 'users', $tokenUserId, null, 'Password reset via email link');

            redirectWithMessage('index.php', 'Password reset successfully. You may now log in with your new password.', 'success');
        }
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="BCAT logo 2024.png" type="image/png">
    <style>
        :root { --primary-indigo: #1a3a5c; --secondary-indigo: #0f2a47; }
        body {
            font-family: 'Inter', sans-serif;
            background-image: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)), url('bcat updated.png');
            background-size: cover; background-position: center; background-attachment: fixed;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .rp-card {
            background: rgba(255,255,255,0.92); backdrop-filter: blur(20px);
            border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
            overflow: hidden; max-width: 460px; width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        .rp-header {
            background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo));
            color: white; padding: 2.5rem 2rem; text-align: center;
        }
        .rp-header h1 { font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; margin: 0.5rem 0 0.25rem; }
        .rp-header p  { font-size: 0.825rem; opacity: 0.8; margin: 0; }
        .rp-icon { width: 64px; height: 64px; background: rgba(255,255,255,0.15); border-radius: 1rem;
                   display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 1rem; }
        .rp-body { padding: 2.5rem 2rem; }
        .form-control { border-radius: 0.75rem; border: 2px solid #e2e8f0; padding: 0.75rem 1rem; }
        .form-control:focus { border-color: var(--primary-indigo); box-shadow: 0 0 0 3px rgba(26,58,92,0.12); }
        .btn-save {
            background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo));
            color: white; border: none; border-radius: 0.875rem; padding: 0.875rem;
            font-weight: 700; width: 100%; transition: all 0.3s; margin-top: 0.5rem;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(26,58,92,0.4); color: white; }
        .back-link { display: block; text-align: center; margin-top: 1.25rem; color: var(--primary-indigo);
                     text-decoration: none; font-weight: 500; font-size: 0.875rem; }
        .strength-bar { height: 4px; border-radius: 4px; transition: all 0.3s; background: #e2e8f0; margin-top: 8px; }
        .strength-fill { height: 100%; border-radius: 4px; transition: all 0.4s; width: 0; }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="rp-card mx-auto">
        <div class="rp-header">
            <div class="rp-icon"><i class="fas fa-lock-open"></i></div>
            <h1>Reset Password</h1>
            <p>Create a strong new password for your account</p>
        </div>
        <div class="rp-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> mb-4" style="border-radius:0.75rem;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
            <form method="POST" action="" id="resetForm">
                <?php csrfField(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="mb-3">
                    <label for="password" class="form-label text-muted fw-600" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">
                        New Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="At least 8 characters" required minlength="8" autofocus>
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <small class="text-muted" id="strengthText"></small>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label text-muted fw-600" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">
                        Confirm New Password
                    </label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                           placeholder="Repeat your new password" required>
                    <small class="text-danger d-none" id="matchError">Passwords do not match</small>
                </div>

                <button type="submit" class="btn btn-save">
                    <i class="fas fa-check me-2"></i>Set New Password
                </button>
            </form>
            <?php endif; ?>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left me-1"></i> Back to Login
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Password strength indicator
    const pwInput   = document.getElementById('password');
    const confInput = document.getElementById('confirm_password');
    const fill      = document.getElementById('strengthFill');
    const txt       = document.getElementById('strengthText');
    const matchErr  = document.getElementById('matchError');

    if (pwInput) {
        pwInput.addEventListener('input', function () {
            const val = this.value;
            let score = 0;
            if (val.length >= 8)  score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['', '#e74c3c', '#f39c12', '#2980b9', '#27ae60'];
            fill.style.width = (score * 25) + '%';
            fill.style.background = colors[score] || '#e2e8f0';
            txt.textContent = labels[score] || '';
            txt.style.color = colors[score] || '';
        });

        confInput.addEventListener('input', function () {
            if (this.value && this.value !== pwInput.value) {
                matchErr.classList.remove('d-none');
            } else {
                matchErr.classList.add('d-none');
            }
        });

        document.getElementById('resetForm').addEventListener('submit', function (e) {
            if (confInput.value !== pwInput.value) {
                e.preventDefault();
                matchErr.classList.remove('d-none');
            }
        });
    }
</script>
</body>
</html>
