<?php
require_once 'api_bootstrap.php';
api_session_start();

$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $data['action'] ?? '';
    // Login logic using direct PDO for speed
    if ($action === 'login') {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $conn = get_api_conn();
        $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                echo json_encode(["success" => false, "message" => "Account inactive"]);
                exit;
            }
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            $redirect = ($user['role'] === 'registrar_staff') ? 'registrar' : $user['role'];
            echo json_encode([
                "success" => true, 
                "user" => ["id" => $user['user_id'], "username" => $user['username'], "role" => $user['role'], "redirect" => $redirect . "/dashboard.php"]
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        }
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(["success" => true]);
    } elseif ($action === 'change_password') {
        if (!isset($_SESSION['user_id'])) exit;
        $conn = get_api_conn();
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $old = $stmt->fetchColumn();
        
        if (password_verify($data['current_password'], $old)) {
            $new = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$new, $_SESSION['user_id']]);
            echo json_encode(["success" => true, "message" => "Password Updated"]);
        } else {
            echo json_encode(["success" => false, "message" => "Incorrect Current Password"]);
        }
    } elseif ($action === 'admin_reset_password') {
        if ($_SESSION['role'] !== 'admin') exit;
        $conn = get_api_conn();
        $new = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$new, $data['target_user_id']]);
        echo json_encode(["success" => true, "message" => "Password Reset Success"]);
    }
}
?>
