<?php
/**
 * AJAX Handler for Updating Profile Image
 * TESDA-BCAT Grade Management System
 */

// Start output buffering as early as possible to catch any stray output (BOMs, whitespace, etc.)
ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Prepare JSON response
header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit();
}

requireLogin();

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $response['message'] = 'Invalid security token';
    echo json_encode($response);
    exit();
}

$userId = getCurrentUserId();

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
    $response['message'] = 'No file uploaded';
    echo json_encode($response);
    exit();
}

// Target directory
$targetDir = __DIR__ . '/../../uploads/profile_pics/';
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Upload file using existing helper
$uploadResult = uploadFile($_FILES['profile_image'], $targetDir, ['jpg', 'jpeg', 'png']);

if ($uploadResult[0]) {
    $filename = $uploadResult[2];
    $conn = getDBConnection();
    
    // Get old image to delete if necessary
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldRow = $result->fetch_assoc();
    $oldImage = $oldRow['profile_image'] ?? null;
    $stmt->close();
    
    // Update DB
    $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
    $stmt->bind_param("si", $filename, $userId);
    
    if ($stmt->execute()) {
        // Delete old image if it exists and is not the same
        if ($oldImage && $oldImage !== $filename && file_exists($targetDir . $oldImage)) {
            @unlink($targetDir . $oldImage);
        }
        
        $response['success'] = true;
        $response['message'] = 'Profile picture updated successfully';
        $response['image_path'] = 'uploads/profile_pics/' . $filename;
    } else {
        $response['message'] = 'Database update failed';
    }
    $stmt->close();
} else {
    $response['message'] = $uploadResult[1];
}

// Clear any stray output before sending JSON
ob_clean();
echo json_encode($response);
exit();
