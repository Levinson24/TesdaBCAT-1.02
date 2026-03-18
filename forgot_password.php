<?php
/**
 * Forgot Password — Send Reset Link
 * TESDA-BCAT Grade Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . getCurrentUserRole() . "/dashboard.php");
    exit();
}

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please refresh and try again.';
        $messageType = 'danger';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');

        if (!validateEmail($email)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } else {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            // Always show success message (prevent email enumeration)
            $message = "If that email address is registered, you will receive a password reset link shortly. "
                     . "Please check your inbox (and spam folder).";
            $messageType = 'success';

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                // Delete any old tokens for this user
                $delStmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                $delStmt->bind_param("i", $user['user_id']);
                $delStmt->execute();
                $delStmt->close();

                // Insert new token
                $insStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $insStmt->bind_param("iss", $user['user_id'], $token, $expires);
                $insStmt->execute();
                $insStmt->close();

                // Build reset URL
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $resetUrl = $protocol . "://" . $host . "/TesdaBCAT-1.02/reset_password.php?token=" . urlencode($token);

                // Try to send email (if mailer is available)
                if (file_exists(__DIR__ . '/includes/mailer.php')) {
                    require_once __DIR__ . '/includes/mailer.php';
                    sendPasswordResetEmail($user['username'], $email, $resetUrl);
                }

                logAudit($user['user_id'], 'PASSWORD_RESET_REQUESTED', 'users', $user['user_id'], null,
                    "Reset token generated for {$email}");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
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
        .fp-card {
            background: rgba(255,255,255,0.92); backdrop-filter: blur(20px);
            border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
            overflow: hidden; max-width: 460px; width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        .fp-header {
            background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo));
            color: white; padding: 2.5rem 2rem; text-align: center;
        }
        .fp-header h1 { font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; margin: 0.5rem 0 0.25rem; }
        .fp-header p  { font-size: 0.825rem; opacity: 0.8; margin: 0; }
        .fp-icon { width: 64px; height: 64px; background: rgba(255,255,255,0.15); border-radius: 1rem;
                   display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 1rem; }
        .fp-body { padding: 2.5rem 2rem; }
        .form-control { border-radius: 0.75rem; border: 2px solid #e2e8f0; padding: 0.75rem 1rem; font-size: 0.95rem; }
        .form-control:focus { border-color: var(--primary-indigo); box-shadow: 0 0 0 3px rgba(26,58,92,0.12); }
        .btn-reset {
            background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo));
            color: white; border: none; border-radius: 0.875rem; padding: 0.875rem;
            font-weight: 700; width: 100%; transition: all 0.3s; margin-top: 0.5rem;
        }
        .btn-reset:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(26,58,92,0.4); color: white; }
        .back-link { display: block; text-align: center; margin-top: 1.25rem; color: var(--primary-indigo);
                     text-decoration: none; font-weight: 500; font-size: 0.875rem; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="fp-card mx-auto">
        <div class="fp-header">
            <div class="fp-icon"><i class="fas fa-key"></i></div>
            <h1>Forgot Password</h1>
            <p>Enter your registered email address to receive a reset link</p>
        </div>
        <div class="fp-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> mb-4" style="border-radius:0.75rem;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($messageType !== 'success'): ?>
            <form method="POST" action="">
                <?php csrfField(); ?>
                <div class="mb-3">
                    <label for="email" class="form-label fw-600 text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">
                        Email Address
                    </label>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="your@email.com" required autofocus>
                </div>
                <button type="submit" class="btn btn-reset">
                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
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
</body>
</html>
