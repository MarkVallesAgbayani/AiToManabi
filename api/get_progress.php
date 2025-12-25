<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

try {
    $chapter_id = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : 0;
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $student_id = $_SESSION['user_id'];
    
    if (!$chapter_id || !$type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing chapter_id or type']);
        exit();
    }
    
    $completed = false;
    $completion_percentage = 0;
    $watch_time = 0;
    
    if ($type === 'video') {
        $stmt = $pdo->prepare("
            SELECT completed, completion_percentage, watch_time_seconds 
            FROM video_progress 
            WHERE student_id = ? AND chapter_id = ?
        ");
        $stmt->execute([$student_id, $chapter_id]);
        $progress = $stmt->fetch();
        
        if ($progress) {
            $completed = (bool)$progress['completed'];
            $completion_percentage = (float)$progress['completion_percentage'];
            $watch_time = (int)$progress['watch_time_seconds'];
        }
        
    } else if ($type === 'text') {
        $stmt = $pdo->prepare("
            SELECT completed 
            FROM text_progress 
            WHERE student_id = ? AND chapter_id = ?
        ");
        $stmt->execute([$student_id, $chapter_id]);
        $progress = $stmt->fetch();
        
        if ($progress) {
            $completed = (bool)$progress['completed'];
            $completion_percentage = $completed ? 100 : 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'completed' => $completed,
        'completion_percentage' => $completion_percentage,
        'watch_time' => $watch_time
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
