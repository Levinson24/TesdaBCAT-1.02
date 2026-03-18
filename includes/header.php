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
}
else {
    $userName = $currentUser['username'] ?? '';
}

$roleDisplay = ucfirst($userRole);
if ($userRole === 'registrar') $roleDisplay = 'Head Registrar';
if ($userRole === 'registrar_staff') $roleDisplay = 'Registrar Staff';
if ($userRole === 'dept_head') $roleDisplay = 'Department Head';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?> - <?php echo htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'GMS'); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-indigo: #1a3a5c;
            --secondary-indigo: #0f2a47;
            --accent-indigo: #5b8db8;
            --background-soft: #f0f4f8;
            --card-glass: rgba(255, 255, 255, 0.97);
            --sidebar-gradient: linear-gradient(160deg, #1a3a5c 0%, #0c1f33 100%);
            --sidebar-width: 260px;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }
        
        /* ──── SHARED LAYOUT ──── */
        html, body {
            width: 100%;
            position: relative;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-soft);
            color: var(--text-main);
            letter-spacing: -0.01em;
            -webkit-text-size-adjust: 100%;
        }

        /* ──── MOBILE-ONLY ISOLATION ──── */
        @media (max-width: 1024px) {
            html, body {
                overflow-x: hidden;
            }
            .sidebar {
                transform: translateX(-280px); /* Fully off-canvas */
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
        }

        /* ──── PRINT PROTECTION (COR/TOR INTEGRITY) ──── */
        @media print {
            .sidebar, .top-navbar, .sidebar-toggle, .sidebar-overlay, .no-print {
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
            .card, .premium-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
        }
        
        h1, h2, h3, h4, h5, h6, .page-title {
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
            font-size: 0.7rem;
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            border: 2px solid rgba(255,255,255,0.1);
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
            padding: 0.875rem 1.25rem;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            margin-bottom: 0.375rem;
            font-weight: 500;
            font-size: 0.9375rem;
            position: relative;
            overflow: hidden;
        }

        @media (max-width: 992px) {
            .sidebar-menu-item {
                padding: 1.1rem 1.5rem; /* Larger touch target for mobile */
                font-size: 1rem;
            }
        }

        
        .sidebar-menu-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
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
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
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
            .card, .premium-card, .glass-effect, .top-navbar, .sidebar-overlay {
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }
            .sidebar, .main-content, .sidebar-menu-item {
                transition-duration: 0.2s !important; /* Faster transitions for better response */
            }
        }

        .content-area {
            flex: 1;
            padding: 1.5rem 1.75rem;
            position: relative;
        }

        .content-area::before {
            content: '';
            position: absolute; /* Changed from fixed to absolute to reduce paint overhead on scroll */
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            height: 60%;
            background-image: url('../TesdaOfficialLogo.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.12; /* Reduced slightly for better performance/readability */
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

        @media (min-width: 993px) {
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
            background: rgba(26, 58, 92, 0.06);
        }
        
        .user-details {
            text-align: right;
            line-height: 1.25;
        }
        
        .user-name {
            display: block;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-main);
        }
        
        .user-role {
            display: block;
            font-size: 0.75rem;
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
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--card-glass);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04);
        }

        .premium-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px -15px rgba(26, 58, 92, 0.12);
            border-color: rgba(26, 58, 92, 0.1);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }


        .gradient-navy {
            background: linear-gradient(135deg, #1a3a5c 0%, #0c1f33 100%) !important;
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
                border: 1px solid rgba(0,0,0,0.08);
                border-radius: 1.25rem;
                padding: 0.75rem;
                background: white;
                box-shadow: 0 8px 16px rgba(0,0,0,0.04);
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
        @media (min-width: 576px) { .responsive-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1200px) { .responsive-grid { grid-template-columns: repeat(4, 1fr); } }

        /* Form Controls for Mobile */
        @media (max-width: 480px) {
            .btn { width: 100%; margin-bottom: 0.5rem; }
            .btn-group { display: flex; flex-direction: column; width: 100%; gap: 0.5rem; }
            .btn-group .btn { border-radius: 0.75rem !important; }
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
            transform: translateY(-2px);
            box-shadow: 0 44px 12px rgba(26, 58, 92, 0.35);
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
            --bs-primary:     #1a3a5c;
            --bs-primary-rgb: 26, 58, 92;
            --bs-info:        #2980b9;
            --bs-info-rgb:    41, 128, 185;
            --bs-secondary:   #2c4a6e;
            --bs-secondary-rgb: 44, 74, 110;
            --bs-warning:     #f39c12;
            --bs-warning-rgb: 243, 156, 18;
        }

        /* Text colour overrides (Bootstrap text-* still resolves via --bs-primary) */
        .text-primary { color: #1a3a5c !important; }
        .text-info    { color: #2980b9 !important; }

        /* Borders */
        .border-primary { border-color: #1a3a5c !important; }

        /* Buttons */
        .btn-primary {
            background-color: #1a3a5c !important;
            border-color: #1a3a5c !important;
            color: #fff !important;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: #0f2a47 !important;
            border-color: #0f2a47 !important;
            box-shadow: 0 4px 12px rgba(26, 58, 92, 0.35) !important;
        }
        .btn-outline-primary {
            color: #1a3a5c !important;
            border-color: #1a3a5c !important;
        }
        .btn-outline-primary:hover {
            background-color: #1a3a5c !important;
            color: #fff !important;
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
        .table-dark, .table-dark th, .table-dark td {
            background-color: #1a3a5c !important;
            border-color: #254f7a !important;
        }
        thead.table-dark th { background-color: #1a3a5c !important; }

        /* Alerts */
        .alert-primary {
            background-color: #e8f0fb;
            border-color: #b8d0ef;
            color: #1a3a5c;
        }
        .alert-info {
            background-color: #e3f2fd;
            border-color: #90caf9;
            color: #0d47a1;
        }

        /* Form focus ring */
        .form-control:focus, .form-select:focus {
            border-color: #5b8db8;
            box-shadow: 0 0 0 0.2rem rgba(26, 58, 92, 0.2);
        }

        /* Nav tabs & pills */
        .nav-tabs .nav-link.active,
        .nav-pills .nav-link.active {
            background-color: #1a3a5c !important;
            border-color: #1a3a5c !important;
            color: #fff !important;
        }
        .nav-tabs .nav-link:hover,
        .nav-pills .nav-link:hover {
            color: #1a3a5c !important;
        }

        /* Pagination */
        .page-link { color: #1a3a5c; }
        .page-link:hover { color: #0f2a47; }
        .page-item.active .page-link {
            background-color: #1a3a5c;
            border-color: #1a3a5c;
        }

        /* Progress bars */
        .progress-bar.bg-primary { background-color: #1a3a5c !important; }
        .progress-bar.bg-info    { background-color: #2980b9 !important; }

        /* DataTables */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #1a3a5c !important;
            border-color: #1a3a5c !important;
            color: #fff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e8f0fb !important;
            border-color: #b8d0ef !important;
            color: #1a3a5c !important;
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
        .dataTables_filter input {
            width: 250px !important;
            border-radius: 0.75rem !important;
            padding: 0.4rem 1rem !important;
            border: 1px solid rgba(26,58,92,0.15) !important;
            background-color: #f8fafc !important;
        }
        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: #5b8db8 !important;
            box-shadow: 0 0 0 0.2rem rgba(26,58,92,0.2) !important;
            outline: none;
        }
        .dataTables_length select {
            border-radius: 0.5rem !important;
            padding: 0.25rem 0.5rem !important;
        }

        /* Links */
        a:not(.btn):not(.sidebar-menu-item):not(.nav-link):not(.dropdown-item) {
            color: #1a3a5c;
        }
        a:not(.btn):not(.sidebar-menu-item):not(.nav-link):not(.dropdown-item):hover {
            color: #0f2a47;
        }

        /* Login page (index.php) */
        .login-card .btn-primary {
            background: linear-gradient(135deg, #1a3a5c, #0f2a47) !important;
        }

        /* Modal Refinements */
        .modal-content {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 15px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            background: #f8fafc;
            padding: 1.25rem 1.75rem;
        }
        .modal-header h5 {
            font-weight: 700;
            color: var(--primary-indigo);
            margin: 0;
        }
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.75rem;
        }
        .modal.fade .modal-dialog {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
    </style>

    
    <?php echo $additionalCSS ?? ''; ?>
</head>
<body>
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
            ['url' => 'departments.php', 'icon' => 'fa-id-badge', 'label' => 'Manage Diploma Programs'],
            ['url' => 'colleges.php', 'icon' => 'fa-university', 'label' => 'Colleges'],
            ['url' => 'grades.php', 'icon' => 'fa-chart-line', 'label' => 'View Grades'],
            ['url' => 'settings.php', 'icon' => 'fa-cog', 'label' => 'Settings'],
            ['url' => 'reports.php', 'icon' => 'fa-file-alt', 'label' => 'Reports'],
            ['url' => 'audit_logs.php', 'icon' => 'fa-clipboard-list', 'label' => 'Audit Logs'],
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
    echo "<a href='{$item['url']}' class='sidebar-menu-item {$active}'>
                        <i class='fas {$item['icon']}'></i>
                        <span>{$item['label']}</span>
                      </a>";
}
?>
            
            <div style="border-top: 1px solid rgba(255,255,255,0.1); margin: 20px 0;"></div>
            
            <a href="../logout.php" class="sidebar-menu-item">
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
            </div>
            
            <div class="user-info" onclick="location.href='profile.php'">
                <div class="user-details d-none d-sm-block">
                    <span class="user-name"><?php echo htmlspecialchars($userName ?? ''); ?></span>
                    <span class="user-role"><?php echo $roleDisplay; ?></span>
                </div>
                <div class="user-avatar">
                     <img src="../BCAT logo 2024.png" alt="User Avatar">
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content-area">
            <?php echo getFlashMessage(); ?>
