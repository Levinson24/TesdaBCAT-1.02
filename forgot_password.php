<?php
/**
 * Forgot Password — User Identification
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
        $identifier = sanitizeInput($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            $message = 'Please enter your email or username.';
            $messageType = 'danger';
        } else {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE (email = ? OR username = ?) AND status = 'active'");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            // Notify Administration
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Set the reset_requested flag
                $updStmt = $conn->prepare("UPDATE users SET reset_requested = 1 WHERE user_id = ?");
                $updStmt->bind_param("i", $user['user_id']);
                $updStmt->execute();
                $updStmt->close();

                // Log the request
                logAudit($user['user_id'], 'PASSWORD_RESET_REQUESTED', 'users', $user['user_id'], null,
                    "User requested a password reset. (Account: " . ($user['email'] ? $user['email'] : $user['username']) . ")");

                $message = "Your password reset request has been logged with the <strong>Administrator</strong>. "
                         . "Please visit the Administration Office in person to have your password manually reset.";
                $messageType = 'success';
            } else {
                // Same message even if not found to prevent enumeration
                $message = "Your password reset request has been logged with the <strong>Administrator</strong>. "
                         . "Please visit the Administration Office in person to have your password manually reset.";
                $messageType = 'success';
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
        :root { --primary-indigo: #0038A8; --secondary-indigo: #002366; }
        body {
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            padding: 20px;
        }
        .fp-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 56, 168, 0.5);
            width: 100%;
            max-width: 450px;
            padding: 3rem 2.5rem;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        .fp-header {
            background: linear-gradient(135deg, #0038A8, #002366);
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
        .btn-reset:hover { box-shadow: 0 10px 25px rgba(0, 56, 168, 0.4); color: white; }
        .back-link { display: block; text-align: center; margin-top: 1.25rem; color: var(--primary-indigo);
                     text-decoration: none; font-weight: 500; font-size: 0.875rem; }
        .back-link:hover { text-decoration: underline; }

        /* ──── ELEGANT SCROLLBARS ──── */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 4px;
            transition: background 0.3s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.7);
        }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="fp-card mx-auto">
        <div class="fp-header">
            <div class="fp-icon"><i class="fas fa-key"></i></div>
            <h1>Forgot Password</h1>
            <p>Enter your email to request a reset code from the administration</p>
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
                    <label for="identifier" class="form-label fw-600 text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">
                        Email Address or Username
                    </label>
                    <input type="text" class="form-control" id="identifier" name="identifier"
                           placeholder="Enter your email or username" required autofocus>
                </div>
                <button type="submit" class="btn btn-reset">
                    <i class="fas fa-paper-plane me-2"></i>Get Reset Code
                </button>
            </form>
            <?php endif; ?>

            <div class="d-flex align-items-center justify-content-center gap-3">
                <a href="index.php" class="back-link mt-0">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
                <span class="text-muted opacity-25 mt-1">|</span>
                <a href="verify.php" class="back-link mt-0 fw-700">
                    <i class="fas fa-check-circle me-1"></i> Verify Document
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
