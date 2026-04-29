<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$conn = getDBConnection();

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$role = isset($_POST['role']) ? trim($_POST['role']) : 'Guest';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO admin_messages (sender_name, sender_email, system_role, message_body) VALUES (?, ?, ?, ?)");
if ($stmt) {
    // Sanitize input to prevent XSS during display later
    $name = htmlspecialchars($name);
    $email = htmlspecialchars($email);
    $role = htmlspecialchars($role);
    $message = htmlspecialchars($message);

    $stmt->bind_param("ssss", $name, $email, $role, $message);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully. Admin will review it shortly.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving message. Please try again.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

$conn->close();
?>
