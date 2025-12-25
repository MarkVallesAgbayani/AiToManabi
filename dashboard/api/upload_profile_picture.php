<?php
/**
 * Profile Picture Upload API
 * Handles uploading and processing profile pictures for teachers
 */

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['profile_picture'];
    $teacher_id = $_SESSION['user_id'];

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
    $upload_dir = __DIR__ . '/../uploads/profile_pictures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'teacher_' . $teacher_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Update database with just the filename (like course images)
    $relative_path = $filename;
    
    // Check if teacher_preferences table exists, create if not
    createTeacherPreferencesTable($pdo);
    
    // Check if preferences exist
    $stmt = $pdo->prepare("SELECT id FROM teacher_preferences WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update existing preferences
        $stmt = $pdo->prepare("UPDATE teacher_preferences SET profile_picture = ?, updated_at = CURRENT_TIMESTAMP WHERE teacher_id = ?");
        $stmt->execute([$relative_path, $teacher_id]);
    } else {
        // Insert new preferences with profile picture
        $stmt = $pdo->prepare("INSERT INTO teacher_preferences (teacher_id, profile_picture) VALUES (?, ?)");
        $stmt->execute([$teacher_id, $relative_path]);
    }

    // Delete old profile picture if it exists
    $stmt = $pdo->prepare("SELECT profile_picture FROM teacher_preferences WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $old_picture = $stmt->fetchColumn();
    
    if ($old_picture && $old_picture !== $relative_path) {
        // Construct file system path for deletion
        $old_file_system_path = __DIR__ . '/../uploads/profile_pictures/' . $old_picture;
        if (file_exists($old_file_system_path)) {
            unlink($old_file_system_path);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'file_path' => $relative_path
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function createTeacherPreferencesTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS teacher_preferences (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            display_name VARCHAR(100),
            profile_picture VARCHAR(255),
            bio TEXT,
            phone VARCHAR(20),
            languages VARCHAR(255),
            profile_visible BOOLEAN DEFAULT TRUE,
            contact_visible BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_teacher (teacher_id),
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
}
?>
