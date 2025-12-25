<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['profile_visible'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing profile_visible parameter']);
    exit();
}

$profile_visible = (bool)$input['profile_visible'];
$teacher_id = $_SESSION['user_id'];

try {
    // Check if teacher preferences exist
    $stmt = $pdo->prepare("SELECT id FROM teacher_preferences WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing preferences
        $stmt = $pdo->prepare("UPDATE teacher_preferences SET profile_visible = ?, updated_at = CURRENT_TIMESTAMP WHERE teacher_id = ?");
        $stmt->execute([$profile_visible, $teacher_id]);
    } else {
        // Insert new preferences
        $stmt = $pdo->prepare("INSERT INTO teacher_preferences (teacher_id, profile_visible) VALUES (?, ?)");
        $stmt->execute([$teacher_id, $profile_visible]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Privacy setting updated successfully',
        'profile_visible' => $profile_visible
    ]);
    
} catch (PDOException $e) {
    error_log("Error updating privacy setting: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
