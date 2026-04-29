<?php
/**
 * Login Page
 * TESDA-BCAT Grade Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSession();

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    $targetDir = ($role === 'registrar_staff') ? 'registrar' : $role;
    header("Location: $targetDir/dashboard.php");
    exit();
}

// Handle login form submission
$error = '';
$errorType = 'danger'; // Bootstrap alert class
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $result = authenticateUser($username, $password);

            if (is_array($result)) {
                // Successful login
                createUserSession($result);
                $targetDir = ($result['role'] === 'registrar_staff') ? 'registrar' : $result['role'];
                header("Location: {$targetDir}/dashboard.php");
                exit();
            } elseif (is_string($result)) {
                if (str_starts_with($result, 'locked:')) {
                    $mins = (int) explode(':', $result)[1];
                    $errorType = 'warning';
                    $error = "<i class='fas fa-lock me-2'></i>Account temporarily locked due to too many failed attempts. "
                        . "Please try again in <strong>{$mins} minute(s)</strong>. "
                        . "Contact your administrator if this was a mistake.";
                } elseif ($result === 'account_inactive') {
                    $errorType = 'warning';
                    $error = "<i class='fas fa-user-slash me-2'></i>Your account is inactive. Please contact the system administrator.";
                } elseif (str_starts_with($result, 'invalid_credentials:')) {
                    $attLeft = (int) explode(':', $result)[1];
                    $error = "<i class='fas fa-exclamation-circle me-2'></i>Invalid username or password. "
                        . "<strong>{$attLeft} attempt(s)</strong> remaining before lockout.";
                } else {
                    $error = "<i class='fas fa-exclamation-circle me-2'></i>Invalid username or password.";
                }
            }
        }
    }
}

// Check for URL errors (like timeout)
$loginErrorOccurred = false;
if (isset($_GET['error']) && $_GET['error'] === 'timeout') {
    $error = "<i class='fas fa-history me-2'></i>Your session has expired due to 10 minutes of inactivity. Please log in again to continue.";
    $errorType = 'warning';
} elseif (isset($error) && !empty($error)) {
    $loginErrorOccurred = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, shrink-to-fit=no">
    <title>Login - TESDA-BCAT GMS</title>
    <link rel="icon" href="BCAT logo 2024.png" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-indigo: #0038A8;
            --secondary-indigo: #002366;
            --accent-indigo: #5b8db8;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

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
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* ──── SHARED LAYOUT ──── */
        html,
        body {
            width: 100%;
            position: relative;
        }

        body {
            background: linear-gradient(135deg, rgba(0, 35, 102, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%), url('bcat updated.png');
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
            padding: 0;
            overflow-x: hidden;
            -webkit-text-size-adjust: 100%;
        }

        /* ──── MOBILE ISOLATION ──── */
        @media (max-width: 576px) {
            .login-container {
                padding: 10px;
            }

            .login-card {
                border-radius: 2rem;
            }

            .login-header {
                padding: 35px 20px 30px;
                text-align: center !important;
                background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo)) !important;
                color: white !important;
                border-radius: 2rem 2rem 0 0;
            }

            .login-header h1 {
                font-size: 1.85rem;
                margin-bottom: 0.15rem;
                text-align: center;
                color: white !important;
            }

            .login-header p {
                font-size: 0.85rem;
                text-align: center;
                color: white !important;
                opacity: 0.8 !important;
            }

            .login-body {
                padding: 20px 25px 30px;
            }

            .tesda-logo-wrap {
                width: 85px;
                height: 85px;
                margin: 0 auto 0.75rem !important;
                padding: 10px;
                border-radius: 1.5rem;
                box-shadow: 0 10px 20px rgba(0, 56, 168, 0.15);
            }

            .mb-4 {
                margin-bottom: 1rem !important;
            }

            .form-label {
                margin-bottom: 0.4rem;
                font-size: 0.7rem;
                opacity: 0.8;
            }

            .form-control {
                padding: 0.75rem 0.85rem;
                font-size: 0.9rem;
            }

            .btn-login {
                padding: 0.85rem;
                font-size: 0.9rem;
                border-radius: 1.25rem;
            }

            .footer-text {
                margin-top: 20px !important;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {

            html,
            body {
                overflow-x: hidden;
            }

            .login-container {
                perspective: none;
                /* Avoid mobile scaling issues */
            }
        }

        .login-container {
            max-width: 1000px;
            /* Expansive for Desktop */
            width: 95%;
            padding: 20px;
            perspective: 1000px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-card {
            background: rgba(220, 243, 242, 0.97);
            /* Refined Off-White (Ghost White) */
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 2.5rem;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.4);
            animation: cardFloat 1.2s cubic-bezier(0.22, 1, 0.36, 1);
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 992px) {
            .login-container {
                max-width: 1050px;
            }

            .login-card {
                flex-direction: row;
                min-height: 600px;
            }

            .login-header {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 60px !important;
                background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo)) !important;
                color: white !important;
                position: relative;
                overflow: hidden;
            }

            .login-header h1 {
                color: white !important;
            }

            .login-header p {
                color: white !important;
                opacity: 0.9 !important;
            }

            .tesda-logo-wrap {
                background: white !important;
            }

            /* Keep logo on white for contrast */
            .login-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 60px !important;
            }

            .tesda-logo-wrap {
                width: 180px;
                height: 180px;
                margin-bottom: 2rem !important;
            }

            .login-header h1 {
                font-size: 6.75rem;
            }

            .login-header p {
                font-size: 3.9rem;
                letter-spacing: 0.25em;
            }
        }

        @keyframes cardFloat {
            0% {
                opacity: 0;
                transform: translateY(40px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            padding: 40px 32px 10px;
            text-align: center;
            position: relative;
            background: transparent;
            transition: all 0.4s ease;
        }

        .login-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.45rem;
            margin-bottom: 0.25rem;
            font-weight: 850;
            letter-spacing: -0.04em;
            color: var(--primary-indigo);
            line-height: 1.1;
            transition: font-size 0.4s ease;
        }

        .login-header p {
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.6;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--primary-indigo);
            font-family: 'Outfit', sans-serif;
        }

        .login-body {
            padding: 10px 40px 35px;
            /* Minimalist tighter spacing */
            position: relative;
            z-index: 1;
        }

        .login-body::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 80%;
            background-image: url('TesdaOfficialLogo.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.2;
            z-index: -1;
            filter: invert(14%) sepia(35%) saturate(1637%) hue-rotate(174deg) brightness(90%) contrast(94%);
        }

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
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .input-group:focus-within {
            border-color: var(--primary-indigo);
            background: white;
            box-shadow: 0 12px 30px -10px rgba(0, 56, 168, 0.2);
            transform: translateY(-2px);
        }

        .input-group-text {
            background-color: transparent;
            border: none;
            padding-left: 1.25rem;
            color: var(--primary-indigo);
            opacity: 0.8;
            font-size: 1.1rem;
        }

        .form-control {
            border: none;
            background: transparent;
            padding: 0.875rem 1rem;
            font-weight: 500;
            color: var(--text-main);
            font-size: 1rem;
        }

        .form-control:focus {
            box-shadow: none;
            background: transparent;
        }

        .toggle-password {
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--primary-indigo);
            opacity: 0.7;
            padding-right: 1.25rem;
            transition: opacity 0.3s;
        }

        .toggle-password:hover {
            opacity: 1;
        }

        .btn-login {
            background: linear-gradient(135deg, #0038A8, #0066FF);
            border: none;
            border-radius: 1.25rem;
            padding: 1rem;
            font-weight: 700;
            font-size: 1rem;
            font-family: 'Outfit', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 20px -5px rgba(0, 56, 168, 0.3);
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 40px -10px rgba(0, 56, 168, 0.5);
            background: linear-gradient(135deg, #002e8a, #0052cc);
            color: white;
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .tesda-logo-wrap {
            width: 140px;
            height: 140px;
            margin: 0 auto 1.5rem;
            background: white;
            border-radius: 2.5rem;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2), 0 0 0 rgba(255, 255, 255, 0);
            animation: logoPulse 3s ease-in-out infinite;
            position: relative;
            z-index: 2;
        }

        @keyframes logoPulse {
            0% {
                transform: translateY(0);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2), 0 0 0 rgba(255, 255, 255, 0);
            }

            50% {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 0 30px rgba(255, 255, 255, 0.8);
            }

            100% {
                transform: translateY(0);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2), 0 0 0 rgba(255, 255, 255, 0);
            }
        }

        .tesda-logo-wrap img {
            max-width: 100%;
            height: auto;
            border-radius: 0.8rem;
            transition: transform 0.3s ease;
        }

        .tesda-logo-wrap:hover img {
            transform: scale(1.1);
        }

        .footer-text {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="tesda-logo-wrap">
                    <img src="BCAT logo 2024.png" alt="TESDA Logo">
                </div>
                <h1><?php echo APP_NAME; ?></h1>
                <p>Grade Management System</p>
            </div>

            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-<?php echo $errorType; ?> alert-dismissible fade show border-0 shadow-sm mb-4"
                        role="alert" style="border-radius: 1rem;">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php
                endif; ?>

                <?php echo getFlashMessage(); ?>

                <form id="loginForm" method="POST" action="">
                    <?php csrfField(); ?>
                    <div class="mb-4">
                        <label for="username" class="form-label">Account Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user-circle"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username"
                                placeholder="Enter your ID or username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Secure Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-shield-alt"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Enter your password" required>
                            <button class="toggle-password" type="button" id="togglePassword">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                        Login to Portal <i class="fas fa-arrow-right ms-2"></i>
                    </button>

                    <div class="text-center mb-3 d-flex align-items-center justify-content-center gap-2">
                        <a href="forgot_password.php" class="text-muted"
                            style="font-size:0.85rem; text-decoration:none;">
                            <i class="fas fa-key me-1"></i> Forgot Password?
                        </a>
                        <span class="text-muted opacity-25">|</span>
                        <a href="verify.php" class="text-primary fw-600"
                            style="font-size:0.85rem; text-decoration:none;">
                            <i class="fas fa-check-circle me-1"></i> Verify Document
                        </a>
                    </div>

                    <div class="d-flex justify-content-center mb-4">
                        <div class="contact-support-pill d-inline-flex align-items-center flex-wrap justify-content-center gap-2 px-3 py-2 shadow-sm"
                            style="background: rgba(0, 56, 168, 0.04); border-radius: 50px; border: 1px solid rgba(0, 56, 168, 0.08);">
                            <span
                                style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fas fa-headset me-1 text-primary" style="font-size: 1rem;"></i> Need help?
                            </span>
                            <button type="button"
                                class="btn btn-link text-primary text-decoration-none fw-bold p-0 m-0 align-baseline"
                                style="font-size: 0.9rem; transition: transform 0.2s ease;" data-bs-toggle="modal"
                                data-bs-target="#contactAdminModal">
                                <i class="fas fa-envelope me-1"></i> Contact Admin
                            </button>
                        </div>
                    </div>

                    <div class="text-center mt-3 pb-2"
                        style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; opacity: 0.85; letter-spacing: 0.5px; line-height: 1.6;">
                        &copy; <?php echo date('Y'); ?> TESDA-BCAT GRADE MANAGEMENT SYSTEM<br>
                        <span style="font-size: 0.7rem; font-weight: 600; opacity: 0.6; letter-spacing: 1.5px;">ADVANCED
                            PORTAL VERSION <?php echo APP_VERSION; ?></span>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.querySelector('#togglePassword');
            const password = document.querySelector('#password');
            const toggleIcon = document.querySelector('#toggleIcon');

            togglePassword.addEventListener('click', function () {
                // Toggle the type attribute
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);

                // Toggle the eye / eye-slash icon
                toggleIcon.classList.toggle('fa-eye');
                toggleIcon.classList.toggle('fa-eye-slash');
            });

            const loginForm = document.getElementById('loginForm');
            const loginLoader = document.getElementById('loginLoader');
            const loaderTitle = document.getElementById('loaderTitle');
            const loaderSubtitle = document.getElementById('loaderSubtitle');
            const loaderSpinner = document.querySelector('.spinner-border');

            // Handle post-reload error animation
            <?php if ($loginErrorOccurred): ?>
                if (loginLoader) {
                    loaderTitle.textContent = "Credentials do not match";
                    loaderSubtitle.textContent = "Please check your username and password";
                    loaderTitle.style.color = "#ff4d4d"; // Red alert color
                    loaderSpinner.classList.replace('text-light', 'text-danger');
                    loaderSpinner.style.borderLeftColor = "#ff4d4d";

                    loginLoader.style.display = 'flex';
                    loginLoader.style.opacity = '1';

                    setTimeout(() => {
                        loginLoader.style.opacity = '0';
                        setTimeout(() => {
                            loginLoader.style.display = 'none';
                            // Reset for next legitimate attempt
                            loaderTitle.textContent = "Checking credentials...";
                            loaderSubtitle.textContent = "Verifying your account details";
                            loaderTitle.style.color = "white";
                            loaderSpinner.classList.replace('text-danger', 'text-light');
                            loaderSpinner.style.borderLeftColor = "#f39c12";
                        }, 400);
                    }, 2000);
                }
                <?php
            endif; ?>

            if (loginForm) {
                loginForm.addEventListener('submit', function (e) {
                    // Check standard HTML5 validation first
                    if (!this.checkValidity()) {
                        return;
                    }

                    e.preventDefault();

                    // Set loading state text
                    loaderTitle.textContent = "Checking credentials...";
                    loaderSubtitle.textContent = "Verifying your account details";

                    // Show loader overlay
                    loginLoader.style.display = 'flex';
                    // Brief delay to allow browser to render display:flex before animating opacity
                    setTimeout(() => {
                        loginLoader.style.opacity = '1';
                    }, 20);

                    // Delay actual form submission to display the animation (secure handoff effect)
                    setTimeout(() => {
                        loginForm.submit();
                    }, 3500);
                });
            }
        });
    </script>

    <!-- Modern Full-Screen Loading Overlay -->
    <div id="loginLoader"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 35, 102, 0.95); z-index: 9999; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); align-items: center; justify-content: center; flex-direction: column; color: white; opacity: 0; transition: opacity 0.4s ease;">
        <div class="spinner-border text-light shadow-sm" role="status"
            style="width: 4.5rem; height: 4.5rem; border-width: 0.35rem; margin-bottom: 1.75rem; border-left-color: #f39c12;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h2 id="loaderTitle"
            style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2rem; letter-spacing: -0.02em; margin-bottom: 0.5rem;">
            Checking credentials...</h2>
        <p id="loaderSubtitle" style="color: rgba(255, 255, 255, 0.7); font-weight: 500; font-size: 0.95rem;">Verifying
            your account details</p>
    </div>

    <!-- Contact Admin Modal -->
    <div class="modal fade" id="contactAdminModal" tabindex="-1" aria-labelledby="contactAdminModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0"
                style="border-radius: 1.5rem; overflow: hidden; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(25px);">
                <div class="modal-header border-0 gradient-navy text-white px-4 py-3"
                    style="background: linear-gradient(135deg, var(--primary-indigo), var(--secondary-indigo));">
                    <h5 class="modal-title fw-bold" id="contactAdminModalLabel">
                        <i class="fas fa-paper-plane me-2 text-info"></i> Send Message to Administrator
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="contactFormAlert"></div>
                    <form id="adminContactForm">
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.8rem; letter-spacing: 0.5px;">YOUR
                                NAME</label>
                            <input type="text" class="form-control" id="contactName" required
                                style="border-radius: 1rem; padding: 0.8rem 1.25rem; font-size: 0.95rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.8rem; letter-spacing: 0.5px;">EMAIL OR CONTACT
                                NO.</label>
                            <input type="text" class="form-control" id="contactEmail" required
                                style="border-radius: 1rem; padding: 0.8rem 1.25rem; font-size: 0.95rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.8rem; letter-spacing: 0.5px;">SYSTEM ROLE
                                (OPTIONAL)</label>
                            <select class="form-select" id="contactRole"
                                style="border-radius: 1rem; padding: 0.8rem 1.25rem; font-size: 0.95rem; border: none; background: rgba(0,0,0,0.03);">
                                <option value="Guest">Guest / Not Logged In</option>
                                <option value="Student">Student</option>
                                <option value="Instructor">Instructor</option>
                                <option value="Registrar">Registrar Staff</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" style="font-size: 0.8rem; letter-spacing: 0.5px;">YOUR
                                MESSAGE</label>
                            <textarea class="form-control" id="contactMessage" rows="4" required
                                style="border-radius: 1rem; padding: 0.8rem 1.25rem; font-size: 0.95rem;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold" id="btnSubmitContact"
                            style="border-radius: 1rem; padding: 0.85rem; background: linear-gradient(135deg, #0038A8, #0066FF); border: none;">
                            <i class="fas fa-paper-plane me-2"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- AJAX Script for Contact Form -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const contactForm = document.getElementById('adminContactForm');
            if (contactForm) {
                contactForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const btn = document.getElementById('btnSubmitContact');
                    const alertBox = document.getElementById('contactFormAlert');

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
                    alertBox.innerHTML = '';

                    const formData = new FormData();
                    formData.append('name', document.getElementById('contactName').value);
                    formData.append('email', document.getElementById('contactEmail').value);
                    formData.append('role', document.getElementById('contactRole').value);
                    formData.append('message', document.getElementById('contactMessage').value);
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');

                    fetch('ajax/submit_contact_message.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alertBox.innerHTML = `<div class="alert alert-success" style="border-radius: 1rem;"><i class="fas fa-check-circle me-2"></i> ${data.message}</div>`;
                                contactForm.reset();
                                setTimeout(() => {
                                    const modal = bootstrap.Modal.getInstance(document.getElementById('contactAdminModal'));
                                    modal.hide();
                                    alertBox.innerHTML = '';
                                }, 3000);
                            } else {
                                alertBox.innerHTML = `<div class="alert alert-danger" style="border-radius: 1rem;"><i class="fas fa-exclamation-circle me-2"></i> ${data.message}</div>`;
                            }
                        })
                        .catch(error => {
                            alertBox.innerHTML = `<div class="alert alert-danger" style="border-radius: 1rem;"><i class="fas fa-exclamation-triangle me-2"></i> Server communication error.</div>`;
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Message';
                        });
                });
            }
        });
    </script>
</body>

</html>
