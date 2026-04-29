<?php
/**
 * Common Header Template
 * TESDA-BCAT Grade Management System
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
}

requireLogin();

$currentUser = getUserProfile(getCurrentUserId(), getCurrentUserRole());
$userName = '';
$userRole = getCurrentUserRole();

if ($userRole === 'student' || $userRole === 'instructor') {
    $userName = ($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '');
} else {
    $userName = $currentUser['username'] ?? '';
}

$roleDisplay = ucfirst($userRole);
if ($userRole === 'registrar')
    $roleDisplay = 'Head Registrar';
if ($userRole === 'registrar_staff')
    $roleDisplay = 'Clerk';
if ($userRole === 'dept_head')
    $roleDisplay = 'Department Head';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?> -
        <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'TESDA-BCAT GMS'); ?></title>
    <link rel="icon" href="<?php echo BASE_URL; ?>BCAT logo 2024.png" type="image/png">

    <!-- PWA Manifest & Meta -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <meta name="theme-color" content="#0038A8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BCAT GMS">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="BCAT GMS">

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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Responsive Extension CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary-indigo: #0038A8;
            --secondary-indigo: #002366;
            --accent-indigo: #0056b3;
            --background-soft: #f8fafc;

            /* ──── GLASSMORPHISM TOKENS ──── */
            --glass-surface: rgba(255, 255, 255, 0.72);
            --glass-border: rgba(255, 255, 255, 0.45);
            --glass-blur: blur(12px);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
            --glass-edge: rgba(255, 255, 255, 0.4);

            --sidebar-gradient: linear-gradient(180deg, #0038A8 0%, #002366 100%);
            --sidebar-width: 280px;
            --text-main: #0f172a;
            --text-muted: #475569;
            --accent-glow: rgba(0, 56, 168, 0.05);
        }

        /* Override Bootstrap Primary */
        .btn-primary {
            background-color: var(--primary-indigo);
            border-color: var(--primary-indigo);
        }

        .btn-primary:hover {
            background-color: #002e8a;
            border-color: #002e8a;
        }

        /* ──── SHARED LAYOUT ──── */
        html,
        body {
            height: 100vh;
            width: 100vw;
            position: relative;
            margin: 0;
            padding: 0;
            overflow: hidden;
            /* Lock the viewport */
            font-family: 'Inter', sans-serif;
            background-color: var(--background-soft);
            color: var(--text-main);
            letter-spacing: -0.01em;
            -webkit-text-size-adjust: 100%;
        }

        html {
            font-size: 108%;
        }

        /* ──── APP-HUB ARCHITECTURE (FIXED) ──── */
        .main-content {
            margin-left: var(--sidebar-width);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* Only children scroll */
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            /* Central scrolling hub */
            padding: 1.5rem 1.75rem;
            position: relative;
            scrollbar-gutter: stable;
            /* Prevent layout shift */
        }

        /* ──── MESH GRADIENT ENVIRONMENTAL BACKGROUND ──── */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(at 0% 0%, rgba(0, 56, 168, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(37, 99, 235, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(0, 35, 102, 0.08) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
            z-index: -1;
            pointer-events: none;
        }

        /* ──── ELEGANT GLASS SCROLLBARS ──── */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(0, 56, 168, 0.15);
            border: 2px solid transparent;
            background-clip: content-box;
            border-radius: 10px;
            transition: all 0.3s;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 56, 168, 0.25);
            border: 2px solid transparent;
            background-clip: content-box;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.4);
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 116, 139, 0.7);
        }

        /* ──── MOBILE-ONLY ISOLATION ──── */

        @media (max-width: 1200px) {
            :root {
                --sidebar-width: 260px;
            }
        }

        @media (max-width: 1024px) {

            html,
            body {
                overflow-x: hidden;
            }

            .sidebar {
                transform: translateX(-280px);
                /* Fully off-canvas */
                box-shadow: none;
                width: 280px;
                z-index: 2000;
            }

            .sidebar.active {
                transform: translateX(0);
                box-shadow: 20px 0 50px rgba(0, 0, 0, 0.2);
            }

            .main-content {
                margin-left: 0 !important;
            }

            .content-area {
                padding: 1.25rem 1rem;
            }
        }

        @media (max-width: 768px) {
            .stat-value {
                font-size: 1.5rem;
            }

            .stat-card {
                padding: 1rem;
                gap: 1rem;
            }

            .stat-icon-wrapper {
                width: 44px;
                height: 44px;
                font-size: 1.1rem;
            }
        }

        /* ──── PRINT PROTECTION (COR/TOR INTEGRITY) ──── */
        @media print {

            .sidebar,
            .top-navbar,
            .sidebar-toggle,
            .sidebar-overlay,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .content-area {
                padding: 0 !important;
            }

            body {
                background: white !important;
                font-size: 10pt !important;
            }

            .card,
            .premium-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            @page {
                size: A4;
                margin: 10mm;
            }
        }

        /* --- Premium Stat Cards (Global) --- */
        .stat-card {
            background: #fff;
            border-radius: 1.25rem;
            padding: 1.25rem;
            border: 1px solid rgba(0, 56, 168, 0.05);
            box-shadow: 0 4px 15px rgba(0, 56, 168, 0.04);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0, 56, 168, 0.08);
        }

        .stat-icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.35rem;
            display: block;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        /* --- Premium Action Buttons --- */
        .btn-premium-view,
        .btn-premium-print {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            padding: 0;
            text-decoration: none !important;
        }

        .btn-premium-view {
            background-color: #f0f9ff;
            color: #0369a1 !important;
        }

        .btn-premium-view:hover {
            background-color: #0369a1;
            color: #fff !important;
            box-shadow: 0 4px 6px rgba(3, 105, 161, 0.2);
        }

        .btn-premium-print {
            background-color: #f8fafc;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
        }

        .btn-premium-print:hover {
            background-color: #1e293b;
            color: #fff !important;
            border-color: #1e293b !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .page-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--sidebar-gradient);
            color: white;
            overflow-y: auto;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.05);
        }

        .sidebar-brand {
            padding: 1.25rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .sidebar-brand p {
            margin: 0.5rem 0 0 0;
            font-size: 0.85rem;
            opacity: 0.8;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.1em;
        }

        .brand-logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.25rem;
        }

        .brand-logo-circle {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .brand-logo-circle img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .sidebar-menu {
            padding: 1.5rem 0.75rem;
        }

        .sidebar-menu-item {
            padding: 0.8rem 1.25rem;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 0.75rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 1rem;
            /* Slightly smaller for better fit */
            line-height: 1.2;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            /* Prevent wrapping */
        }

        @media (max-width: 992px) {
            .sidebar-menu-item {
                padding: 1.1rem 1.5rem;
                /* Larger touch target for mobile */
                font-size: 1.15rem;
            }
        }


        .sidebar-menu-item:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.15) !important;
            transform: translateX(6px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            height: 60%;
            width: 4px;
            background: #fff;
            border-radius: 0 4px 4px 0;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .sidebar-menu-item:hover::before {
            opacity: 1;
            top: 15%;
            height: 70%;
        }

        .sidebar-menu-item.active {
            background: white;
            color: var(--primary-indigo);
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sidebar-menu-item i {
            font-size: 1.125rem;
            width: 1.5rem;
            margin-right: 1rem;
            transition: transform 0.3s ease;
        }

        .sidebar-menu-item:hover i {
            transform: scale(1.1);
        }


        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* ──── PERFORMANCE OPTIMIZATION ──── */
        @media (max-width: 992px) {

            .card,
            .premium-card,
            .glass-effect,
            .top-navbar,
            .sidebar-overlay {
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }

            .sidebar,
            .main-content,
            .sidebar-menu-item {
                transition-duration: 0.2s !important;
                /* Faster transitions for better response */
            }
        }


        .content-area::before {
            content: '';
            position: absolute;
            /* Changed from fixed to absolute to reduce paint overhead on scroll */
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            height: 60%;
            background-image: url('../TesdaOfficialLogo.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.12;
            /* Reduced slightly for better performance/readability */
            z-index: -1;
            pointer-events: none;
            /* Using simpler opacity instead of heavy SVG filters for mobile performance */
        }

        /* Top Navbar */
        .top-navbar {
            background: var(--card-glass);
            backdrop-filter: blur(12px);
            padding: 0.75rem 1.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .top-navbar {
                padding: 0.6rem 1rem;
            }
        }

        .sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: white;
            border: 1px solid rgba(26, 58, 92, 0.1);
            color: var(--primary-indigo);
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }

        @media (min-width: 1025px) {
            .sidebar-toggle {
                display: none !important;
            }
        }

        .sidebar-toggle:hover {
            background: var(--background-soft);
            color: var(--secondary-indigo);
        }

        .page-title {
            margin: 0;
            font-size: 1.4rem;
            color: var(--text-main);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 0.75rem;
            border-radius: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .user-info:hover {
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 10px 25px -5px rgba(0, 56, 168, 0.12);
            transform: translateY(-2px);
            border-color: rgba(0, 56, 168, 0.2);
        }

        .user-details {
            text-align: right;
            line-height: 1.25;
        }

        .user-name {
            display: block;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-main);
        }

        @media (max-width: 576px) {
            .user-name {
                font-size: 1rem;
            }

            .user-role {
                font-size: 0.8rem;
            }

            .user-details {
                display: none;
                /* Hide name on very small screens to save space */
            }

            .page-title {
                font-size: 1.1rem;
            }
        }

        .user-role {
            display: block;
            font-size: 0.95rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            padding: 4px;
            border: 2px solid rgba(26, 58, 92, 0.15);
            overflow: hidden;
        }

        .user-avatar img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        /* ─────── PREMIUM UI EXTENSIONS ─────── */
        .premium-card {
            border: none;
            border-radius: 1.5rem;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--glass-surface);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border) !important;
            box-shadow: var(--glass-shadow);
        }

        .premium-card:hover {
            box-shadow: 0 20px 40px -15px rgba(0, 56, 168, 0.12);
            border-color: rgba(255, 255, 255, 0.8) !important;
            transform: translateY(-4px) scale(1.01);
        }

        .glass-effect {
            background: var(--glass-surface) !important;
            backdrop-filter: var(--glass-blur) !important;
            -webkit-backdrop-filter: var(--glass-blur) !important;
            border: 1px solid var(--glass-border) !important;
        }

        /* Session Timer Tray */
        .session-timer-tray {
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            background: rgba(0, 56, 168, 0.05);
            border: 1px solid rgba(0, 56, 168, 0.1);
            transition: all 0.3s ease;
        }

        .session-timer-tray:hover {
            background: rgba(0, 56, 168, 0.08);
            transform: translateY(-1px);
        }

        .timer-warning {
            background: rgba(239, 68, 68, 0.1) !important;
            border-color: rgba(239, 68, 68, 0.2) !important;
            animation: timerPulse 2s infinite;
        }

        .timer-warning span {
            color: #b91c1c !important;
        }

        .timer-warning i {
            color: #b91c1c !important;
        }

        @keyframes timerPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }


        .gradient-navy {
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%) !important;
        }

        .gradient-light {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .stat-card-icon-v2 {
            width: 56px;
            height: 56px;
            border-radius: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: all 0.3s ease;
        }

        .premium-card:hover .stat-card-icon-v2 {
            transform: scale(1.1) rotate(5deg);
        }

        /* Mobile Table Stack Improved */
        @media (max-width: 768px) {
            .table-mobile-card thead {
                display: none;
            }

            .table-mobile-card tr {
                display: block;
                margin-bottom: 1.5rem;
                border: 1px solid rgba(0, 0, 0, 0.08);
                border-radius: 1.25rem;
                padding: 0.75rem;
                background: white;
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.04);
            }

            .table-mobile-card td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 0.8rem 1rem !important;
                border: none !important;
                text-align: right;
                border-bottom: 1px solid #f1f5f9 !important;
            }

            .table-mobile-card td:last-child {
                border-bottom: none !important;
            }

            .table-mobile-card td::before {
                content: attr(data-label);
                font-weight: 800;
                text-transform: uppercase;
                font-size: 0.65rem;
                color: var(--text-muted);
                text-align: left;
                margin-right: 1.5rem;
                min-width: 100px;
                letter-spacing: 0.05em;
            }

            /* Action alignment in cards */
            .table-mobile-card .btn-group,
            .table-mobile-card .d-flex {
                justify-content: flex-end;
                width: 100%;
            }
        }

        /* Fluid Spacing & Grid Utility */
        .responsive-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(1, 1fr);
        }

        @media (min-width: 576px) {
            .responsive-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1200px) {
            .responsive-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Form Controls for Mobile */
        @media (max-width: 480px) {
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .btn-group {
                display: flex;
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            .btn-group .btn {
                border-radius: 0.75rem !important;
            }

            /* Responsive Padding Utilities */
            .p-mobile-2 {
                padding: 0.5rem !important;
            }

            .p-mobile-3 {
                padding: 1rem !important;
            }

            .p-mobile-4 {
                padding: 1.5rem !important;
            }

            .m-mobile-0 {
                margin: 0 !important;
            }

            /* Specific fix for large padding in dashboard cards */
            .col-lg-3.p-5,
            .col-lg-9.p-5 {
                padding: 1.5rem !important;
            }
        }

        /* ──── DASHBOARD SPECIFIC MOBILE POLISH ──── */
        @media (max-width: 992px) {
            .display-5 {
                font-size: 2.25rem !important;
            }

            .lead {
                font-size: 1rem !important;
            }
        }


        .user-info:hover .user-avatar {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 10px 20px rgba(26, 58, 92, 0.3);
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            background: #fff;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        /* ──── PREMIUM LAYOUT STANDARDS ──── */
        .premium-card {
            border-radius: 1rem !important;
            border: 0 !important;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
        }

        .premium-table thead th {
            background-color: #f8fafc !important;
            color: #64748b !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 0.85rem !important;
            letter-spacing: 0.05em !important;
            padding: 1rem !important;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05) !important;
        }

        .premium-table tbody td {
            vertical-align: middle !important;
            color: #334155 !important;
            font-size: 0.95rem !important;
            padding: 1rem !important;
        }

        /* ──── SHARED UI UTILITIES ──── */
        .gradient-navy {
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%) !important;
        }

        .avatar-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 2px solid #fff;
        }

        /* ──── PRECISE ACTION BUTTONS V2 (GLOBAL HARMONIZATION) ──── */
        .btn-premium-view,
        .btn-premium-edit,
        .btn-premium-delete {
            height: 38px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            background-color: #ffffff;
            font-weight: 800;
            font-size: 0.85rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
            text-decoration: none !important;
            cursor: pointer;
            gap: 0.5rem !important;
            flex-shrink: 0 !important;
            padding: 0 1.25rem !important;
            min-width: max-content;
            line-height: 1 !important;
            /* Unified center alignment */
        }

        .btn-premium-view,
        .btn-premium-edit {
            padding: 0 1.25rem;
            border-radius: 50px;
        }

        .btn-premium-delete {
            width: 38px;
            padding: 0;
            border-radius: 50%;
        }

        /* Color Variants & Hover elevation */
        .btn-premium-view {
            color: #475569 !important;
            border-color: rgba(71, 85, 105, 0.3);
        }

        .btn-premium-view:hover {
            background: #f8fafc;
            color: #0f172a !important;
            border-color: #475569;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-premium-view i {
            color: #3b82f6;
            position: relative;
            top: -1px;
        }

        .btn-premium-edit {
            color: #0038A8 !important;
            border-color: rgba(0, 56, 168, 0.4);
        }

        .btn-premium-edit:hover {
            background: #f0f7ff;
            color: #002366 !important;
            border-color: #0038A8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 56, 168, 0.15);
        }

        .btn-premium-edit i {
            color: #0038A8;
        }

        .btn-premium-delete {
            color: #ef4444 !important;
            border-color: rgba(239, 68, 68, 0.4);
        }

        .btn-premium-delete:hover {
            background: #fff1f2;
            color: #dc2626 !important;
            border-color: #ef4444;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.18);
        }

        /* Master Container to prevent collisions */
        .table-actions-v2 {
            display: flex !important;
            justify-content: flex-end;
            align-items: center;
            gap: 0.75rem !important;
            /* Harmonized spacing for action groups */
            min-height: 40px;
            flex-wrap: nowrap !important;
            /* Absolutely prevents wrapping or overlap */
            min-width: max-content;
        }

        .table-actions-v2 form {
            display: contents !important;
        }

        /* ──── PREMIUM LAYOUT V2 ELEVATION ──── */
        .table-row-premium {
            transition: all 0.2s ease;
            position: relative;
        }

        .table-row-premium:hover {
            background-color: rgba(0, 56, 168, 0.02) !important;
            box-shadow: inset 4px 0 0 var(--bs-primary), 0 12px 30px -10px rgba(0, 56, 168, 0.1);
            transform: translateY(-2px) scale(1.005);
            z-index: 10;
        }

        .avatar-premium {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            border: 2px solid #fff;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0038A8;
            font-size: 1.2rem;
        }

        .identity-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
            line-height: 1.2;
        }

        .identity-meta {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-premium {
            padding: 0.5rem 1rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            font-size: 0.7rem !important;
            border-radius: 8px !important;
        }

        /* ──── ALL-NEW PREMIUM CTA TOKENS ──── */
        .btn-premium-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.75rem;
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
            color: #fff !important;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 56, 168, 0.2);
            text-decoration: none !important;
            cursor: pointer;
            gap: 0.75rem;
        }

        .btn-premium-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 56, 168, 0.3);
            filter: brightness(1.1);
        }

        .btn-premium-action:active {
            transform: translateY(-1px);
        }

        .btn-premium-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.5rem;
            background: #f8fafc;
            color: #475569 !important;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            text-decoration: none !important;
            cursor: pointer;
            gap: 0.5rem;
        }

        .btn-premium-secondary:hover {
            background: #f1f5f9;
            color: #1e293b !important;
            border-color: #cbd5e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .search-box-premium {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 0.15rem 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            min-width: 280px;
        }

        .search-box-premium:focus-within {
            background: rgba(255, 255, 255, 0.22);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .search-box-premium .form-control {
            background: transparent !important;
            border: none !important;
            color: white !important;
            box-shadow: none !important;
            font-weight: 500;
        }

        .search-box-premium .form-control::placeholder {
            color: rgba(255, 255, 255, 0.55);
        }

        .search-box-premium i {
            color: rgba(255, 255, 255, 0.6);
            margin-left: 0.75rem;
        }

        .icon-box-premium {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: rgba(0, 56, 168, 0.08);
            color: #0038A8;
            font-size: 1rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .btn-premium-action:hover .icon-box-premium {
            transform: scale(1.1) rotate(5deg);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .status-active {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status-inactive {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        /* ──── PREMIUM MODALS & INPUTS ──── */
        .modal-content {
            background: #ffffff !important;
            /* Forces solid white background */
            border: 1px solid var(--glass-border) !important;
            border-radius: 1.5rem !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
            overflow: hidden;
        }

        .modal-premium-header {
            background: var(--sidebar-gradient) !important;
            padding: 1.5rem 2rem !important;
            border: none !important;
            position: relative;
        }

        .modal-premium-header .modal-title {
            color: #ffffff !important;
            font-weight: 800 !important;
            letter-spacing: -0.02em !important;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.4rem !important;
        }

        .modal-premium-header i {
            font-size: 1.6rem;
            color: #ffffff !important;
            opacity: 0.9;
        }

        .form-section-divider {
            display: flex;
            align-items: center;
            margin: 2.5rem 0 1.5rem;
            gap: 1.25rem;
        }

        .form-section-divider span {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #0038A8;
            background: rgba(0, 56, 168, 0.05);
            padding: 0.45rem 1rem;
            border-radius: 50px;
            border: 1px solid rgba(0, 56, 168, 0.1);
        }

        .premium-input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .premium-input-group label {
            font-weight: 800;
            color: #0038A8 !important;
            /* Vibrant Institution Blue */
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            display: block;
        }

        .premium-input-group .input-wrapper {
            position: relative;
        }

        .premium-input-group .input-wrapper i {
            position: absolute;
            left: 1.15rem;
            top: 50%;
            transform: translateY(-50%);
            color: #0038A8;
            /* High contrast Icon */
            font-size: 1.1rem;
            z-index: 10;
        }

        .premium-input-group .form-control,
        .premium-input-group .form-select {
            padding-left: 3rem !important;
            border-radius: 0.75rem !important;
            border: 1.5px solid rgba(0, 56, 168, 0.2) !important;
            font-size: 1rem !important;
            height: 3.25rem !important;
            background: #fff !important;
            /* Solid background */
            color: #1e293b !important;
        }

        .premium-input-group .form-control:focus,
        .premium-input-group .form-select:focus {
            background: #fff !important;
            border-color: #0038A8 !important;
            box-shadow: 0 0 0 4px rgba(0, 56, 168, 0.1) !important;
        }

        .btn-create-profile {
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%) !important;
            color: white !important;
            border: none;
            border-radius: 50px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 700 !important;
            box-shadow: 0 10px 15px -3px rgba(0, 56, 168, 0.3) !important;
        }



        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0.875rem 1.25rem;
            font-weight: 700;
        }

        .stat-card {
            border-radius: 1rem;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-indigo);
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary-indigo);
            box-shadow: 0 4px 12px rgba(26, 58, 92, 0.35);
        }

        .tesda-logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tesda-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Responsive Sidebar */
        @media (max-width: 1024px) {

            /* Mobile translation handled in the isolated block above */
            .main-content {
                margin-left: 0 !important;
            }

            .sidebar-toggle {
                display: flex;
            }

            .page-title {
                font-size: 1.15rem;
            }

            /* Adjust content area for mobile */
            .content-area {
                padding: 1rem;
            }
        }


        @media (max-width: 768px) {
            .content-area {
                padding: 1.25rem 1rem;
            }

            .stat-card {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .top-navbar {
                padding: 0.75rem 1rem;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
            }

            .page-title {
                font-size: 1.1rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 150px;
            }
        }

        /* ─────── SHARED PREMIUM FORM & MODAL UI ─────── */
        /* True Glassmorphic Modal */
        .modal-content.border-0 {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            border-radius: 2rem;
            box-shadow:
                0 20px 50px -12px rgba(0, 0, 0, 0.12),
                inset 0 0 0 1px rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }

        .modal-header.modal-premium-header {
            background: linear-gradient(135deg, var(--bs-secondary, #002366) 0%, var(--bs-primary, #0038A8) 100%) !important;
            padding: 1.5rem 2rem 1.5rem;
            border: none;
            position: relative;
        }

        .modal-header.modal-premium-header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        }

        .modal-header.modal-premium-header .modal-title {
            color: #fff;
            font-weight: 800;
            letter-spacing: -0.04em;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.25rem;
            font-size: 1.4rem;
        }

        .modal-header.modal-premium-header .modal-title i {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, #e2e8f0, #ffffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 12px rgba(255, 255, 255, 0.2));
        }

        .form-section-divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0 1rem;
            gap: 1.5rem;
        }

        .form-section-divider span {
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--bs-secondary, #002366);
            white-space: nowrap;
            background: rgba(var(--bs-primary-rgb, 0, 56, 168), 0.08);
            padding: 0.35rem 1rem;
            border-radius: 50px;
        }

        .form-section-divider::after,
        .form-section-divider::before {
            content: "";
            height: 1px;
            flex-grow: 1;
            background: linear-gradient(90deg, transparent, rgba(var(--bs-primary-rgb, 0, 56, 168), 0.15), transparent);
        }

        .premium-input-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .premium-input-group label {
            font-weight: 700;
            color: var(--bs-secondary, #002366);
            font-size: 1rem;
            margin-bottom: 0.4rem;
            display: block;
            margin-left: 0.5rem;
        }

        .premium-input-group .input-wrapper {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .premium-input-group .input-wrapper i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(var(--bs-primary-rgb, 0, 56, 168), 0.45);
            font-size: 1.1rem;
            transition: all 0.3s;
            z-index: 10;
        }

        .premium-input-group .form-control,
        .premium-input-group .form-select {
            padding-left: 3.25rem;
            border-radius: 0.75rem;
            border: 1.5px solid rgba(var(--bs-primary-rgb, 0, 56, 168), 0.15);
            font-size: 1.1rem;
            height: 2.75rem;
            background: rgba(var(--bs-primary-rgb, 0, 56, 168), 0.02);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.01);
            transition: all 0.3s;
        }

        .premium-input-group .form-control:focus,
        .premium-input-group .form-select:focus {
            background: #fff;
            border-color: var(--bs-primary);
            box-shadow:
                0 10px 20px -5px rgba(var(--bs-primary-rgb, 0, 56, 168), 0.15),
                0 0 0 4px rgba(var(--bs-primary-rgb, 0, 56, 168), 0.1);
            transform: translateY(-2px);
        }

        .premium-input-group .form-control:focus+i,
        .premium-input-group .form-select:focus+i {
            color: var(--bs-primary);
            transform: translateY(-50%) scale(1.2);
        }

        .modal-footer {
            padding: 1.25rem 2rem;
            background: rgba(0, 0, 0, 0.02);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn-create-profile {
            background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-secondary) 100%);
            color: white !important;
            border: none;
            border-radius: 50px;
            padding: 0.6rem 2rem;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: -0.02em;
            box-shadow: 0 10px 25px -5px rgba(var(--bs-primary-rgb, 0, 56, 168), 0.4);
            transition: all 0.3s;
        }

        .btn-create-profile:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 30px -5px rgba(var(--bs-primary-rgb, 0, 56, 168), 0.5);
        }

        .btn-discard {
            color: #64748b !important;
            font-weight: 600;
            text-decoration: none;
            background: transparent;
            border: none;
            padding: 0.6rem 1.5rem;
            transition: all 0.2s;
        }

        .btn-discard:hover {
            color: #1e293b !important;
            transform: translateX(-5px);
        }

        /* ═══════════════════════════════════════════════════
           NAVY BLUE BOOTSTRAP THEME OVERRIDES
           Applies to all 28 pages system-wide
        ═══════════════════════════════════════════════════ */

        /* ── Navy blue theme: override Bootstrap colour tokens at the root.
           Bootstrap 5 builds ALL bg-*, text-*, border-* utilities from these
           CSS variables, so both solid (.bg-primary) and tinted
           (.bg-primary.bg-opacity-10) will automatically use navy.
           We no longer need blanket !important overrides on individual
           utility classes. ── */
        :root {
            --bs-primary: #0038A8;
            --bs-primary-rgb: 0, 56, 168;
            --bs-info: #2980b9;
            --bs-info-rgb: 41, 128, 185;
            --bs-secondary: #002366;
            --bs-secondary-rgb: 0, 35, 102;
            --bs-warning: #f39c12;
            --bs-warning-rgb: 243, 156, 18;
        }

        /* Text colour overrides */
        .text-primary {
            color: #0038A8;
        }

        .text-info {
            color: #2980b9;
        }

        /* Borders */
        .border-primary {
            border-color: #0038A8 !important;
        }

        /* Buttons */
        .btn-primary {
            background-color: #0038A8 !important;
            border-color: #0038A8 !important;
            color: #fff !important;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: #002366 !important;
            border-color: #002366 !important;
            box-shadow: 0 4px 12px rgba(26, 58, 92, 0.35) !important;
        }

        .btn-outline-primary {
            color: #0038A8 !important;
            border-color: #0038A8 !important;
        }

        .btn-outline-primary:hover {
            background-color: #0038A8 !important;
            color: #fff !important;
        }

        /* Pulse Indicators (Online/Offline) */
        .pulse-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            position: relative;
        }

        .pulse-dot.online {
            background-color: #22c55e;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            animation: pulse-green 2s infinite;
        }

        .pulse-dot.offline {
            background-color: #ef4444;
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }

        @keyframes pulse-green {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }

        .btn-info {
            background-color: #2980b9 !important;
            border-color: #2980b9 !important;
            color: #fff !important;
        }

        .btn-info:hover {
            background-color: #1f6591 !important;
            border-color: #1f6591 !important;
        }

        .btn-outline-info {
            color: #2980b9 !important;
            border-color: #2980b9 !important;
        }

        .btn-outline-info:hover {
            background-color: #2980b9 !important;
            color: #fff !important;
        }

        /* Table dark header */
        .table-dark,
        .table-dark th,
        .table-dark td {
            background-color: #0038A8 !important;
            border-color: #254f7a !important;
        }

        thead.table-dark th {
            background-color: #0038A8 !important;
        }

        /* Alerts */
        .alert-primary {
            background-color: #e8f0fb;
            border-color: #b8d0ef;
            color: #0038A8;
        }

        .alert-info {
            background-color: #e3f2fd;
            border-color: #90caf9;
            color: #0d47a1;
        }

        /* Form focus ring */
        .form-control:focus,
        .form-select:focus {
            border-color: #5b8db8;
            box-shadow: 0 0 0 0.2rem rgba(26, 58, 92, 0.2);
        }

        /* Nav tabs & pills */
        .nav-tabs .nav-link.active,
        .nav-pills .nav-link.active {
            background-color: #0038A8 !important;
            border-color: #0038A8 !important;
            color: #fff !important;
        }

        .nav-tabs .nav-link:hover,
        .nav-pills .nav-link:hover {
            color: #0038A8 !important;
        }

        /* Pagination */
        .page-link {
            color: #0038A8;
        }

        .page-link:hover {
            color: #002366;
        }

        .page-item.active .page-link {
            background-color: #0038A8;
            border-color: #0038A8;
        }

        /* Progress bars */
        .progress-bar.bg-primary {
            background-color: #0038A8 !important;
        }

        .progress-bar.bg-info {
            background-color: #2980b9 !important;
        }

        /* DataTables */
        /* DataTables Premium Pagination Enforcer */
        .dataTables_wrapper .dataTables_paginate .paginate_button,
        .page-item .page-link {
            border-radius: 12px !important;
            margin: 0 3px !important;
            border: 1px solid rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s ease !important;
            padding: 0.5rem 1.1rem !important;
            font-weight: 600 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover,
        .page-item.active .page-link {
            background: #0038A8 !important;
            background-color: #0038A8 !important;
            border-color: #0038A8 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(0, 56, 168, 0.25) !important;
            font-weight: 800 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current),
        .page-link:hover {
            background: #f1f5f9 !important;
            color: #0038A8 !important;
            border-color: #0038A8 !important;
        }

        /* DataTables search box & length */
        .dataTables_wrapper .row:first-child {
            padding: 1.25rem 1.25rem 0.5rem !important;
            align-items: center;
        }

        .dataTables_wrapper .row:last-child {
            padding: 0.5rem 1.25rem 1.25rem !important;
            align-items: center;
        }

        .dataTables_filter {
            margin-bottom: 0.5rem;
            text-align: right;
        }

        .dataTables_filter label {
            font-weight: 700;
            color: #0038A8 !important;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dataTables_filter input {
            width: 250px !important;
            border-radius: 50px !important;
            padding: 0.5rem 1.25rem !important;
            border: 1.5px solid var(--glass-border) !important;
            background-color: rgba(255, 255, 255, 0.6) !important;
            backdrop-filter: blur(8px);
            color: #1e293b !important;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02) !important;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .dataTables_filter input {
                width: 100% !important;
            }

            .dataTables_filter {
                text-align: left;
            }
        }

        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--primary-indigo) !important;
            background-color: rgba(255, 255, 255, 0.9) !important;
            box-shadow: 0 0 0 4px rgba(0, 56, 168, 0.1) !important;
        }

        /* Links */
        a:not(.btn):not(.sidebar-menu-item):not(.nav-link):not(.dropdown-item) {
            color: #0038A8;
        }

        a:not(.btn):not(.sidebar-menu-item):not(.nav-link):not(.dropdown-item):hover {
            color: #002366;
        }

        /* ──── RESPONSIVE SEARCH BOX CONTAINERS ──── */
        .search-box-container {
            flex: 1 1 auto;
            max-width: 400px;
            min-width: 220px !important;
            margin-bottom: 0;
            width: 100%;
        }

        @media (max-width: 768px) {
            .search-box-container {
                max-width: 100%;
                width: 100% !important;
                min-width: 0 !important;
                margin-top: 0.75rem;
                flex-basis: 100%;
            }
        }

        .search-box-container .input-group {
            border: 1.5px solid rgba(255, 255, 255, 0.2) !important;
            transition: all 0.2s;
        }

        .search-box-container .input-group:focus-within {
            border-color: rgba(255, 255, 255, 0.6) !important;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.15) !important;
        }


        /* Login page (index.php) */
        .login-card .btn-primary {
            background: linear-gradient(135deg, #0038A8, #002366) !important;
        }

        /* Modal Refinements */
        .modal-content {
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            background: var(--glass-surface);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.4);
            padding: 1.25rem 1.75rem;
        }

        .modal-header h5 {
            font-weight: 700;
            color: var(--primary-indigo);
            margin: 0;
            font-family: 'Outfit', sans-serif;
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.2);
            padding: 1.25rem 1.75rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.5s ease 0.3s;
        }

        .modal.show .modal-header,
        .modal.show .modal-body,
        .modal.show .modal-footer {
            opacity: 1;
            transform: translateY(0);
        }

        .modal-header {
            transition: all 0.5s ease 0.1s;
        }

        .modal-body {
            opacity: 0;
            transform: translateY(15px);
            transition: all 0.5s ease 0.2s;
        }

        .modal.fade .modal-dialog {
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
            transform: scale(0.9) translateY(35px) !important;
            opacity: 0;
            filter: blur(8px);
        }

        .modal.show .modal-dialog {
            transform: scale(1) translateY(0) !important;
            opacity: 1;
            filter: blur(0);
        }

        /* ──── DYNAMIC BACKDROP BLUR (FOCUS MODE) ──── */
        .modal-backdrop.show {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background-color: rgba(0, 35, 102, 0.18);
            opacity: 1 !important;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-backdrop {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ──── MOBILE-FIRST PROTOTYPE INTEGRATION (320px - 576px) ──── */
        @media (max-width: 576px) {
            :root {
                --nav-height: 56px;
                --border-radius-mobile: 12px;
            }

            /* Global Mobile Overrides */
            body {
                font-size: 14px !important;
            }

            .content-area {
                padding: 10px 15px !important;
            }

            /* Prototype Card Style */
            .card,
            .premium-card {
                border-radius: var(--border-radius-mobile) !important;
                padding: 15px !important;
                margin-bottom: 15px !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
                border: 1px solid rgba(0, 0, 0, 0.02) !important;
            }

            /* Subject Card Component */
            .subject-card-mobile {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .subject-header-mobile {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 5px;
            }

            .subject-name-mobile {
                font-size: 15px;
                font-weight: 700;
                color: var(--text-main);
                flex: 1;
                line-height: 1.3;
            }

            .subject-grade-mobile {
                font-size: 1.1rem;
                font-weight: 800;
                color: var(--primary-indigo);
                background: rgba(0, 56, 168, 0.05);
                padding: 4px 10px;
                border-radius: 8px;
                margin-left: 10px;
            }

            .subject-info-mobile {
                color: var(--text-muted);
                font-size: 13px;
                margin-bottom: 8px;
            }

            .subject-footer-mobile {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 5px;
                padding-top: 10px;
                border-top: 1px solid #f1f5f9;
            }

            /* Stat Grid Prototype */
            .stat-grid-mobile {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                margin-bottom: 15px;
            }

            .stat-card-mobile {
                text-align: center;
                padding: 15px !important;
            }

            .stat-value-mobile {
                font-size: 1.5rem;
                font-weight: 800;
                color: var(--primary-indigo);
            }

            .stat-label-mobile {
                font-size: 11px;
                color: var(--text-muted);
                text-transform: uppercase;
                font-weight: 600;
            }

            /* Thumb-Friendly Buttons */
            .btn-mobile-full {
                width: 100% !important;
                height: 48px !important;
                display: flex !important;
                align-items: center;
                justify-content: center;
                margin-bottom: 10px !important;
                font-weight: 700 !important;
                border-radius: var(--border-radius-mobile) !important;
                font-size: 14px !important;
            }

            /* Student Profile Mobile */
            .profile-card-mobile {
                text-align: center;
            }

            .profile-img-mobile {
                width: 90px;
                height: 90px;
                margin: 0 auto 10px;
                border-radius: 50%;
                border: 3px solid #fff;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            }
        }

        /* ══════════════════════════════════════════════════
           COMPREHENSIVE RESPONSIVE SYSTEM UPGRADE
           Mobile-first breakpoints — covers all 28 pages
        ══════════════════════════════════════════════════ */

        /* ── Fluid responsive-grid (stat cards) ── */
        .responsive-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
        }

        /* ── Make sure ALL tables never break the viewport ── */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* ── Ensure modal doesn't overflow on small screens ── */
        @media (max-width: 576px) {
            .modal-dialog {
                margin: 0.5rem !important;
                max-width: calc(100vw - 1rem) !important;
            }

            .modal-content {
                border-radius: 1rem !important;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1rem !important;
            }

            /* Cards stack tightly */
            .card,
            .premium-card {
                border-radius: 0.85rem !important;
                margin-bottom: 1rem !important;
            }

            /* Page header wraps */
            .top-navbar {
                flex-wrap: wrap;
                gap: 0.5rem;
                padding: 0.6rem 0.75rem !important;
            }

            .page-title {
                font-size: 1rem !important;
                max-width: calc(100vw - 120px);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* Larger tap targets for action buttons in tables */
            .btn-sm {
                padding: 0.4rem 0.65rem !important;
                font-size: 0.8rem !important;
            }

            /* DataTables controls stack vertically */
            .dataTables_wrapper .row:first-child {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.75rem !important;
            }

            .dataTables_wrapper .row:last-child {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.75rem !important;
                text-align: center;
            }

            .dataTables_length,
            .dataTables_filter {
                width: 100% !important;
                text-align: left !important;
            }

            .dataTables_filter input {
                width: 100% !important;
            }

            /* DataTables info & paginate center-aligned */
            .dataTables_info,
            .dataTables_paginate {
                width: 100%;
                text-align: center;
            }

            /* Responsive grid squishes to 2-cols on phones */
            .responsive-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            /* Hide non-critical sidebar branding text on very small */
            .sidebar-brand p {
                display: none;
            }

            /* Premium input groups — prevent icon overflow */
            .premium-input-group .form-control,
            .premium-input-group .form-select {
                font-size: 0.95rem !important;
                height: auto !important;
                padding-left: 2.75rem !important;
            }

            /* Force all flex rows to wrap cleanly */
            .d-flex.gap-3,
            .d-flex.gap-4 {
                flex-wrap: wrap;
            }

            /* Session timer — hide on tiny screens */
            .session-timer-tray {
                display: none !important;
            }
        }

        /* ── Tablet: 577px → 768px ── */
        @media (min-width: 577px) and (max-width: 768px) {
            .modal-dialog.modal-xl,
            .modal-dialog.modal-lg {
                max-width: calc(100vw - 2rem) !important;
                margin: 1rem auto !important;
            }

            .responsive-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .top-navbar {
                padding: 0.65rem 1.25rem !important;
            }

            .page-title {
                font-size: 1.1rem !important;
            }
        }

        /* ── Mid-tablet: 769px → 1024px ── */
        @media (min-width: 769px) and (max-width: 1024px) {
            .modal-dialog.modal-xl {
                max-width: 92vw !important;
            }

            .responsive-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* ── Dashboard hero card: sidebar panel hides below lg ── */
        @media (max-width: 991px) {
            /* Hero welcome card: stack vertically */
            .col-lg-3.gradient-navy {
                border-radius: 1rem 1rem 0 0 !important;
                padding: 2rem 1.5rem !important;
        }

            /* Ensure table avatar cells don't stretch */
            td .d-flex.align-items-center {
                flex-wrap: nowrap;
            }

            /* Prevent modal sidebars from overflowing */
            .profile-sidebar {
                padding: 2rem 1.5rem 1.5rem !important;
            }
        }

        /* ── DataTables child-row (responsive detail) styling ── */
        table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
        table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
            background-color: var(--primary-indigo) !important;
            border: 2px solid #fff !important;
            box-shadow: 0 2px 5px rgba(0,56,168,0.3);
        }

        table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control:before {
            background-color: var(--secondary-indigo) !important;
        }

        /* Child row detail view */
        table.dataTable > tbody > tr.child {
            background: rgba(0,56,168,0.02);
        }

        table.dataTable > tbody > tr.child ul.dtr-details {
            width: 100%;
        }

        table.dataTable > tbody > tr.child ul.dtr-details > li {
            border-bottom: 1px solid rgba(0,0,0,0.04);
            padding: 0.5rem 0;
        }

        table.dataTable > tbody > tr.child ul.dtr-details > li b {
            color: var(--primary-indigo);
            font-weight: 700;
            min-width: 120px;
            display: inline-block;
        }

        /* ── Mobile table card alternative (for non-DataTable tables) ── */
        @media (max-width: 576px) {
            .table-mobile-card thead {
                display: none;
            }

            .table-mobile-card tbody tr {
                display: block;
                margin-bottom: 0.75rem;
                border: 1px solid rgba(0,56,168,0.08);
                border-radius: 0.75rem;
                padding: 0.75rem;
                background: #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            }

            .table-mobile-card tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.35rem 0.25rem;
                border: none;
                font-size: 0.875rem;
            }

            .table-mobile-card tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--text-muted);
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin-right: 0.5rem;
                flex-shrink: 0;
            }
        }

        /* ── PWA Install Button (Premium Capsule) ── */
        #pwa-install-btn {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
            color: #fff !important;
            border: none;
            border-radius: 50px;
            padding: 0.6rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 56, 168, 0.3);
            white-space: nowrap;
            text-transform: none;
            margin-left: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            letter-spacing: 0.01em;
            z-index: 1001;
        }

        #pwa-install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 56, 168, 0.4);
            filter: brightness(1.1);
        }

        #pwa-install-btn.visible {
            display: inline-flex !important;
        }

        #pwa-install-btn i {
            font-size: 0.95rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        @media (max-width: 768px) {
            #pwa-install-btn {
                padding: 0.5rem 0.9rem;
                font-size: 0.8rem;
                margin-left: 0.65rem;
                gap: 0.4rem;
            }
        }

        @media (max-width: 576px) {
            #pwa-install-btn {
                padding: 0.5rem;
                width: 38px;
                height: 38px;
                border-radius: 50%;
                margin-left: 0.5rem;
                box-shadow: 0 4px 12px rgba(0, 56, 168, 0.2);
            }
            #pwa-install-btn span {
                display: none !important;
            }
            #pwa-install-btn i {
                margin: 0;
                font-size: 1rem;
            }
        }

        /* ── PWA Sync Status Pill ── */
        #pwa-sync-pill {
            position: fixed;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: rgba(15,23,42,0.92);
            color: #fff;
            padding: 0.55rem 1.25rem;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            z-index: 9999;
            transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            pointer-events: none;
            backdrop-filter: blur(8px);
        }
        #pwa-sync-pill.show {
            transform: translateX(-50%) translateY(0);
        }
        #pwa-sync-pill.online { background: rgba(21,128,61,0.92); }
        #pwa-sync-pill.offline { background: rgba(185,28,28,0.92); }
        #pwa-sync-pill.syncing { background: rgba(0,56,168,0.95); }
        .pwa-sync-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #fff;
            flex-shrink: 0;
        }
        .pwa-sync-dot.pulse {
            animation: pwa-pulse 1.2s infinite;
        }
        @keyframes pwa-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
    </style>


    <?php echo $additionalCSS ?? ''; ?>
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logos">
                <div class="brand-logo-circle">
                    <img src="../tesda_logo.png" alt="TESDA">
                </div>
                <div class="brand-logo-circle">
                    <img src="../BCAT logo 2024.png" alt="BCAT">
                </div>
            </div>
            <h4>TESDA-BCAT</h4>
            <p>Grade Management</p>
        </div>

        <div class="sidebar-menu">
            <?php
            $unreadMsgCount = 0;
            if ($userRole === 'admin') {
                try {
                    $conn = getDBConnection();
                    $unreadRes = $conn->query("SELECT COUNT(*) as unread FROM admin_messages WHERE is_read = 0");
                    if ($unreadRes) {
                        $unreadMsgCount = $unreadRes->fetch_assoc()['unread'];
                    }
                } catch (Exception $e) {
                    error_log("Dashboard message count error: " . $e->getMessage());
                    $unreadMsgCount = 0;
                }
            }

            $menuItems = [];

            switch ($userRole) {
                case 'admin':
                    $menuItems = [
                        ['url' => 'dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                        ['url' => 'users.php', 'icon' => 'fa-users', 'label' => 'Manage Users'],
                        ['url' => 'students.php', 'icon' => 'fa-user-graduate', 'label' => 'Students'],
                        ['url' => 'instructors.php', 'icon' => 'fa-chalkboard-teacher', 'label' => 'Instructors'],
                        ['url' => 'courses.php', 'icon' => 'fa-book', 'label' => 'Subjects'],
                        ['url' => 'programs.php', 'icon' => 'fa-graduation-cap', 'label' => 'Programs'],
                        ['url' => 'departments.php', 'icon' => 'fa-id-badge', 'label' => 'Diploma Programs'],
                        ['url' => 'colleges.php', 'icon' => 'fa-university', 'label' => 'Colleges'],
                        ['url' => 'grades.php', 'icon' => 'fa-chart-line', 'label' => 'View Grades'],
                        ['url' => 'settings.php', 'icon' => 'fa-cog', 'label' => 'Settings'],
                        ['url' => 'reports.php', 'icon' => 'fa-file-alt', 'label' => 'Reports'],
                        ['url' => 'audit_logs.php', 'icon' => 'fa-clipboard-list', 'label' => 'Audit Logs'],
                        ['url' => 'messages.php', 'icon' => 'fa-envelope', 'label' => 'Support Messages'],
                        ['url' => 'profile.php', 'icon' => 'fa-user', 'label' => 'My Profile'],
                    ];
                    break;

                case 'registrar':
                case 'registrar_staff':
                    $menuItems = [
                        ['url' => 'dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                        ['url' => 'students.php', 'icon' => 'fa-user-graduate', 'label' => 'Students'],
                        ['url' => 'instructors.php', 'icon' => 'fa-chalkboard-teacher', 'label' => 'Instructors'],
                        ['url' => 'courses.php', 'icon' => 'fa-book', 'label' => 'Courses'],
                        ['url' => 'sections.php', 'icon' => 'fa-layer-group', 'label' => 'Class Sections'],
                        ['url' => 'enrollments.php', 'icon' => 'fa-user-plus', 'label' => 'Enrollments'],
                        ['url' => 'grades.php', 'icon' => 'fa-chart-line', 'label' => 'Grade Records'],
                        ['url' => 'transcripts.php', 'icon' => 'fa-file-alt', 'label' => 'Transcripts'],
                        ['url' => 'profile.php', 'icon' => 'fa-user', 'label' => 'My Profile'],
                    ];
                    break;

                case 'instructor':
                    $menuItems = [
                        ['url' => 'dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                        ['url' => 'my_classes.php', 'icon' => 'fa-chalkboard', 'label' => 'My Classes'],
                        ['url' => 'submit_grades.php', 'icon' => 'fa-edit', 'label' => 'Submit Grades'],
                        ['url' => 'grade_history.php', 'icon' => 'fa-history', 'label' => 'Grade History'],
                        ['url' => 'profile.php', 'icon' => 'fa-user', 'label' => 'My Profile'],
                    ];
                    break;

                case 'student':
                    $menuItems = [
                        ['url' => 'dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                        ['url' => 'my_grades.php', 'icon' => 'fa-chart-line', 'label' => 'My Grades'],
                        ['url' => 'cor.php', 'icon' => 'fa-file-alt', 'label' => 'COR'],
                        ['url' => 'profile.php', 'icon' => 'fa-user', 'label' => 'My Profile'],
                    ];
                    break;

                case 'dept_head':
                    $menuItems = [
                        ['url' => 'dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
                        ['url' => 'instructors.php', 'icon' => 'fa-chalkboard-teacher', 'label' => 'Diploma Program Faculty'],
                        ['url' => 'students.php', 'icon' => 'fa-user-graduate', 'label' => 'Diploma Program Students'],
                        ['url' => 'courses.php', 'icon' => 'fa-book', 'label' => 'Course Catalog'],
                        ['url' => 'grades.php', 'icon' => 'fa-check-circle', 'label' => 'Grade Oversight'],
                        ['url' => 'reports.php', 'icon' => 'fa-chart-bar', 'label' => 'Reports & Analytics'],
                        ['url' => 'profile.php', 'icon' => 'fa-user', 'label' => 'My Profile'],
                    ];
                    break;
            }

            $currentPage = basename($_SERVER['PHP_SELF']);

            foreach ($menuItems as $item) {
                $active = (basename($item['url']) === $currentPage) ? 'active' : '';
                $badge = '';
                if (basename($item['url']) === 'messages.php' && $unreadMsgCount > 0) {
                    $badge = "<span class='badge bg-danger rounded-pill ms-auto' style='font-size: 0.7rem; padding: 0.35em 0.65em;'>{$unreadMsgCount}</span>";
                }

                echo "<a href='{$item['url']}' class='sidebar-menu-item {$active} d-flex align-items-center justify-content-between'>
                        <div class='d-flex align-items-center'>
                            <i class='fas {$item['icon']} me-2' style='width: 20px; text-align: center;'></i>
                            <span>{$item['label']}</span>
                        </div>
                        {$badge}
                      </a>";
            }
            ?>

            <div style="border-top: 1px solid rgba(255,255,255,0.1); margin: 20px 0;"></div>

            <a href="../verify.php" target="_blank" class="sidebar-menu-item"
                style="color: #fff !important; opacity: 0.8;">
                <i class="fas fa-shield-halved"></i>
                <span>Verify Document</span>
            </a>

            <a href="../logout.php" class="sidebar-menu-item mt-2">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center gap-2">
                <button class="sidebar-toggle" id="sidebarCollapse">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?php echo $pageTitle ?? 'Dashboard'; ?></h1>

                <!-- Persistent Session Timer -->
                <div class="session-timer-tray d-none d-md-flex align-items-center ms-3" id="persistentTimer"
                    onclick="keepAlive()" title="Click to refresh session">
                    <i class="fas fa-clock-rotate-left me-2 text-primary opacity-75"></i>
                    <span id="navCountdown" class="fw-bold text-dark font-monospace"
                        style="font-size: 0.85rem;">--:--</span>
                </div>
            </div> <!-- CLOSE LEFT ITEMS -->

            <div class="d-flex align-items-center"> <!-- START RIGHT ITEMS -->
                <div class="user-info" onclick="location.href='profile.php'">
                    <div class="user-details d-none d-sm-block">
                        <span class="user-name"><?php echo htmlspecialchars($userName ?? ''); ?></span>
                        <span class="user-role"><?php echo $roleDisplay; ?></span>
                    </div>
                    <div class="user-avatar text-center">
                        <?php if (!empty($currentUser['profile_image'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($currentUser['profile_image']); ?>?v=<?php echo time(); ?>"
                                alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <img src="<?php echo BASE_URL; ?>BCAT logo 2024.png" alt="User Avatar">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PWA Install App Button (Positioned to the right of profile) -->
                <button id="pwa-install-btn" title="Install this app on your device">
                    <i class="fas fa-download"></i>
                    <span class="d-none d-sm-inline">Install App</span>
                </button>
            </div>
        </div>

        <!-- ─── SESSION TIMEOUT WARNING MODAL ─── -->
        <div class="modal fade" id="sessionTimeoutModal" tabindex="-1" data-bs-backdrop="static"
            data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius:1.5rem;overflow:hidden;">
                    <div class="modal-header border-0 text-white"
                        style="background:linear-gradient(135deg,#0038A8,#002366);padding:1.5rem 1.75rem;">
                        <h5 class="modal-title fw-700">
                            <i class="fas fa-clock me-2"></i>Session Expiring Soon
                        </h5>
                    </div>
                    <div class="modal-body text-center py-4 px-4">
                        <div
                            style="width:80px;height:80px;border-radius:50%;background:rgba(26,58,92,0.08);
                                    display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;">
                            ⏱️
                        </div>
                        <p class="text-muted mb-1">Your session will expire in:</p>
                        <p class="fw-800 mb-3" style="font-size:2.5rem;color:#0038A8;letter-spacing:-0.02em;"
                            id="sessionCountdown">5:00</p>
                        <p class="text-muted small mb-0">Click "Stay Logged In" to continue your session, or you will be
                            automatically logged out.</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-3 pb-4">
                        <button type="button" class="btn btn-primary px-4" id="keepAliveBtn"
                            style="border-radius:0.875rem;font-weight:700;">
                            <i class="fas fa-refresh me-2"></i>Stay Logged In
                        </button>
                        <a href="<?php echo str_repeat('../', substr_count($_SERVER['SCRIPT_NAME'], '/') - 2); ?>logout.php"
                            class="btn btn-outline-secondary px-4" style="border-radius:0.875rem;">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout Now
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const SESSION_LIFETIME = <?php echo defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600; ?>;
                const WARN_BEFORE_SEC = 300; // Show warning 5 min before timeout
                let countdownInterval = null;
                let warningShown = false;
                let sessionModal = null;

                // Determine the correct path to keep_alive.php based on current depth
                const depth = (window.location.pathname.match(/\//g) || []).length - 2;
                let keepAliveUrl = '';
                for (let i = 0; i < depth; i++) keepAliveUrl += '../';
                keepAliveUrl += 'includes/ajax/keep_alive.php';

                function formatTime(sec) {
                    const m = Math.floor(sec / 60);
                    const s = sec % 60;
                    return m + ':' + String(s).padStart(2, '0');
                }

                function startSessionWatcher() {
                    const loginTime = <?php echo time(); ?>;
                    const expiresAt = loginTime + SESSION_LIFETIME;
                    const warnAt = expiresAt - WARN_BEFORE_SEC;

                    const navCountdown = document.getElementById('navCountdown');
                    const navTray = document.getElementById('persistentTimer');

                    setInterval(function () {
                        const now = Math.floor(Date.now() / 1000);
                        const remaining = expiresAt - now;

                        // Update Top Navbar Timer (Persistent)
                        if (navCountdown) {
                            navCountdown.textContent = formatTime(remaining > 0 ? remaining : 0);
                            // Add warning color if < 5 mins
                            if (navTray) {
                                if (remaining < 300) { // 5 mins
                                    navTray.classList.add('timer-warning');
                                    const icon = navTray.querySelector('i');
                                    if (icon) icon.className = 'fas fa-hourglass-half me-2';
                                } else {
                                    navTray.classList.remove('timer-warning');
                                    const icon = navTray.querySelector('i');
                                    if (icon) icon.className = 'fas fa-clock-rotate-left me-2 text-primary opacity-75';
                                }
                            }
                        }

                        if (remaining <= 0) {
                            window.location.href = keepAliveUrl.replace('includes/ajax/keep_alive.php', 'logout.php');
                            return;
                        }

                        if (now >= warnAt && !warningShown) {
                            warningShown = true;
                            sessionModal = new bootstrap.Modal(document.getElementById('sessionTimeoutModal'));
                            sessionModal.show();
                            startCountdown(remaining);
                        }
                    }, 1000); // Check every 1 second for the navbar timer
                }

                function startCountdown(seconds) {
                    let remaining = seconds;
                    const countdownEl = document.getElementById('sessionCountdown');

                    countdownInterval = setInterval(function () {
                        remaining--;
                        if (countdownEl) countdownEl.textContent = formatTime(remaining);

                        if (remaining <= 0) {
                            clearInterval(countdownInterval);
                            window.location.href = keepAliveUrl.replace('includes/ajax/keep_alive.php', 'logout.php');
                        }
                    }, 1000);
                }

                function keepAlive() {
                    fetch(keepAliveUrl, { method: 'GET', credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'ok') {
                                clearInterval(countdownInterval);
                                warningShown = false;
                                if (sessionModal) sessionModal.hide();
                                startSessionWatcher();
                            } else {
                                window.location.href = keepAliveUrl.replace('includes/ajax/keep_alive.php', 'index.php');
                            }
                        })
                        .catch(() => { });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const keepBtn = document.getElementById('keepAliveBtn');
                    if (keepBtn) keepBtn.addEventListener('click', keepAlive);
                    startSessionWatcher();

                    // ──── SILENT HEARTBEAT (Every 4 mins) ────
                    // Keeps the user marked as "Online" in the database while the tab is open.
                    setInterval(function () {
                        fetch(keepAliveUrl, { method: 'GET', credentials: 'same-origin' }).catch(() => { });
                    }, 240000);
                });
            })();
        </script>

        <!-- Content Area -->
        <div class="content-area">
            <?php echo getFlashMessage(); ?>