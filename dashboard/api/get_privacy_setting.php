<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['user_id'];

try {
    // Get teacher preferences
    $stmt = $pdo->prepare("SELECT profile_visible FROM teacher_preferences WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Default to true if no preferences found
    $profile_visible = $preferences ? (bool)$preferences['profile_visible'] : true;
    
    echo json_encode([
        'success' => true,
        'profile_visible' => $profile_visible
    ]);
    
} catch (PDOException $e) {
    error_log("Error getting privacy setting: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
