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
    }
    else {
        $identifier = sanitizeInput($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            $message = 'Please enter your email or username.';
            $messageType = 'danger';
        }
        else {
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
            }
            else {
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
    <title>Forgot Password - TESDA-BCAT GMS</title>
    <link rel="icon" href="BCAT logo 2024.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-indigo: #0038A8;
            --secondary-indigo: #002366;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }
        
        body {
            background: linear-gradient(135deg, rgba(0, 35, 102, 0.8) 0%, rgba(0, 86, 179, 0.7) 100%), url('bcat updated.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 24px;
        }

        .fp-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 2.5rem;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.5);
            width: 100%;
            max-width: 400px;
            animation: cardFloat 1.2s cubic-bezier(0.22, 1, 0.36, 1);
            display: flex;
            flex-direction: column;
        }

        @keyframes cardFloat {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .fp-header {
            background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo));
            color: white;
            padding: 40px 32px 30px;
            text-align: center;
            position: relative;
        }
        
        @media (min-width: 992px) {
            .container { max-width: 1000px; }
            .fp-card { max-width: 940px; flex-direction: row; min-height: 520px; }
            .fp-header { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 60px !important; border-right: 1px solid rgba(0, 56, 168, 0.1); }
            .fp-body { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 60px !important; }
            .tesda-logo-wrap { width: 120px; height: 120px; margin-bottom: 2rem !important; }
            .fp-header h1 { font-size: 2.2rem !important; color: white !important; }
            .fp-header p { font-size: 0.85rem !important; color: white !important; opacity: 0.9 !important; }
        }

        .tesda-logo-wrap {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.25rem;
            background: white;
            border-radius: 2.25rem;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2), 0 0 0 rgba(255, 255, 255, 0);
            animation: logoPulse 3s ease-in-out infinite;
        }

        @keyframes logoPulse {
            0% { transform: translateY(0); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2), 0 0 0 rgba(255, 255, 255, 0); }
            50% { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 0 30px rgba(255, 255, 255, 0.8); }
            100% { transform: translateY(0); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2), 0 0 0 rgba(255, 255, 255, 0); }
        }

        .tesda-logo-wrap img {
            max-width: 100%;
            height: auto;
            border-radius: 0.75rem;
        }

        .fp-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
            font-weight: 850;
            letter-spacing: -0.03em;
            color: var(--primary-indigo);
        }

        .fp-header p {
            font-size: 0.7rem;
            opacity: 0.6;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-family: 'Outfit', sans-serif;
            color: var(--primary-indigo);
        }

        .fp-body { padding: 10px 40px 40px; }
        
        .tesda-logo-wrap { background: white; border-radius: 2rem; padding: 10px; margin: 0 auto 1.5rem; }

        .form-label {
            font-weight: 700;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }

        .input-group {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(0, 56, 168, 0.15);
            border-radius: 1.25rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        .input-group:focus-within {
            border-color: var(--primary-indigo);
            background: white;
            box-shadow: 0 12px 30px -10px rgba(0, 56, 168, 0.2);
            transform: translateY(-2px);
        }

        .input-group-text {
            background: transparent;
            border: none;
            padding-left: 1.25rem;
            color: var(--primary-indigo);
            font-size: 1.1rem;
        }

        .form-control {
            border: none;
            background: transparent;
            padding: 0.875rem 1rem;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control:focus { box-shadow: none; background: transparent; }

        .btn-reset {
            background: linear-gradient(135deg, #0038A8, #0066FF);
            color: white; border: none; border-radius: 1.25rem; padding: 1rem;
            font-weight: 700; width: 100%; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-family: 'Outfit', sans-serif; text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 10px 20px -5px rgba(0, 56, 168, 0.3);
        }

        .btn-reset:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 40px -10px rgba(0, 56, 168, 0.5);
            background: linear-gradient(135deg, #002e8a, #0052cc);
            color: white;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary-indigo);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .back-link:hover { transform: translateX(-3px); color: var(--secondary-indigo); }

        @media (max-width: 576px) {
            body { padding: 12px; }
            .fp-card { border-radius: 2rem; }
            .fp-header { 
                padding: 35px 20px 30px; 
                background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo)) !important;
                color: white !important;
                border-radius: 2rem 2rem 0 0;
            }
            .fp-header h1 { font-size: 1.5rem; color: white !important; }
            .fp-header p { font-size: 0.65rem; color: white !important; opacity: 0.8 !important; }
            .tesda-logo-wrap { width: 65px; height: 65px; border-radius: 1.5rem; margin-bottom: 0.75rem; background: white; padding: 8px; }
            .fp-body { padding: 25px 20px 30px; }
            .input-group { border-radius: 1rem; }
            .btn-reset { padding: 0.85rem; font-size: 0.85rem; border-radius: 1rem; }
        }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="fp-card mx-auto">
        <div class="fp-header">
            <div class="tesda-logo-wrap">
                <img src="BCAT logo 2024.png" alt="TESDA Logo">
            </div>
            <h1>Forgot Password</h1>
            <p>Enter your ID or Email below</p>
        </div>
        <div class="fp-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> mb-4" style="border-radius:0.75rem;">
                    <?php echo $message; ?>
                </div>
            <?php
endif; ?>

            <?php if ($messageType !== 'success'): ?>
            <form method="POST" action="">
                <?php csrfField(); ?>
                <div class="mb-4">
                    <label for="identifier" class="form-label">Email or Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-id-card"></i>
                        </span>
                        <input type="text" class="form-control" id="identifier" name="identifier"
                               placeholder="Enter your email or username" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-reset">
                    Request Reset Code <i class="fas fa-paper-plane ms-2"></i>
                </button>
            </form>
            <?php
endif; ?>

            <div class="text-center mt-3 d-flex align-items-center justify-content-center gap-3">
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
                <span class="text-muted opacity-25">|</span>
                <a href="verify.php" class="back-link">
                    <i class="fas fa-check-circle me-1"></i> Verify Document
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
