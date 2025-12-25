<?php
/**
 * Student Profile Picture Upload API
 * Handles uploading and processing student profile pictures
 */

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (!isset($_FILES['profile_picture'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit();
}

$file = $_FILES['profile_picture'];
$student_id = $_SESSION['user_id'];

// Validate file
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$maxSize = 2 * 1024 * 1024; // 2MB

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, and GIF files are allowed.']);
    exit();
}

if ($file['size'] > $maxSize) {
    echo json_encode(['error' => 'File size too large. Maximum size is 2MB.']);
    exit();
}

try {
    // Create uploads directory if it doesn't exist
    $uploadDir = '../../uploads/profile_pictures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'student_' . $student_id . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database with new profile picture path
        $stmt = $pdo->prepare("
            INSERT INTO student_preferences (student_id, profile_picture) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE profile_picture = ?
        ");
        $stmt->execute([$student_id, $filename, $filename]);
        
        echo json_encode([
            'success' => true,
            'file_path' => $filename,
            'web_path' => '../uploads/profile_pictures/' . $filename,
            'message' => 'Profile picture uploaded successfully'
        ]);
    } else {
        echo json_encode(['error' => 'Failed to upload file']);
    }
} catch (Exception $e) {
    error_log("Error uploading student profile picture: " . $e->getMessage());
    echo json_encode(['error' => 'Upload failed']);
}
?>
