<?php
/**
 * Logout Page
 * TESDA-BCAT Grade Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

// Securely terminate session without immediate redirect
logout(false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - TESDA-BCAT GMS</title>
    <link rel="icon" href="BCAT logo 2024.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-image: linear-gradient(rgba(0, 35, 102, 0.8), rgba(0, 35, 102, 0.95)), url('bcat updated.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: white;
            overflow: hidden;
        }

        .logout-container {
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem 4rem;
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeInOut 2s ease-in-out forwards;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: scale(0.95) translateY(20px); }
            20% { opacity: 1; transform: scale(1) translateY(0); }
            80% { opacity: 1; transform: scale(1) translateY(0); }
            100% { opacity: 0; transform: scale(1.05) translateY(-20px); }
        }

        .spinner-ring {
            width: 80px;
            height: 80px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left-color: #f39c12; /* Warning Yellow Accent */
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logout-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .logout-subtitle {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .shield-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            color: white;
            opacity: 0.8;
        }

        .spinner-wrapper {
            position: relative;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="logout-container">
        <div class="spinner-wrapper">
            <div class="spinner-ring"></div>
            <i class="fas fa-shield-alt shield-icon"></i>
        </div>
        <h1 class="logout-title">Logout successfully</h1>
        <p class="logout-subtitle">Your session has been securely closed</p>

    </div>

    <script>
        // Redirect back to login page after 3.5 seconds to allow the fade Out animation to play
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 3500);
    </script>
</body>
</html>
