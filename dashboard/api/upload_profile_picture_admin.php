<?php
/**
 * Profile Picture Upload API - Admin Version
 * Handles uploading and processing profile pictures for admins
 */

session_start();
require_once __DIR__ . '/../../config/database.php';

// Set JSON header immediately
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['profile_picture'];
    $admin_id = $_SESSION['user_id'];

    // Validate file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF files are allowed.');
    }

    if ($file['size'] > $max_size) {
        throw new Exception('File size too large. Maximum size is 2MB.');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../uploads/profile_pictures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Get old profile picture before updating
    $stmt = $pdo->prepare("SELECT profile_picture FROM admin_preferences WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $old_picture = $stmt->fetchColumn();

    // Check if preferences exist
    $stmt = $pdo->prepare("SELECT id FROM admin_preferences WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update existing preferences
        $stmt = $pdo->prepare("UPDATE admin_preferences SET profile_picture = ?, updated_at = CURRENT_TIMESTAMP WHERE admin_id = ?");
        $stmt->execute([$filename, $admin_id]);
    } else {
        // Insert new preferences with profile picture
        $stmt = $pdo->prepare("INSERT INTO admin_preferences (admin_id, profile_picture) VALUES (?, ?)");
        $stmt->execute([$admin_id, $filename]);
    }

    // Delete old profile picture if it exists
    if ($old_picture && $old_picture !== $filename) {
        $old_file_path = $upload_dir . $old_picture;
        if (file_exists($old_file_path)) {
            unlink($old_file_path);
        }
    }

    // Log to audit trail
    try {
        $auditSql = "INSERT INTO audit_trail (user_id, action, details, created_at) 
                     VALUES (?, 'profile_picture_upload', 'Admin uploaded profile picture', NOW())";
        $auditStmt = $pdo->prepare($auditSql);
        $auditStmt->execute([$admin_id]);
    } catch (PDOException $auditError) {
        error_log("Audit trail logging failed: " . $auditError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'file_path' => $filename
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
