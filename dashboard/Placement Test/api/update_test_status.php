<?php
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['test_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$test_id = (int)$input['test_id'];
$status = $input['status'];

// Validate status
$allowed_statuses = ['draft', 'published', 'archived'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Check if test exists and belongs to the teacher
    $stmt = $pdo->prepare("SELECT id, is_published FROM placement_test WHERE id = ? AND created_by = ?");
    $stmt->execute([$test_id, $_SESSION['user_id']]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Test not found']);
        exit();
    }
    
    // Map status to database values
    $is_published = ($status === 'published') ? 1 : 0;
    $archived = ($status === 'archived') ? 1 : 0;
    
    // Debug logging
    error_log("Updating test status - Test ID: $test_id, Status: $status, is_published: $is_published, archived: $archived, User ID: " . $_SESSION['user_id']);
    
    // Update test status
    $stmt = $pdo->prepare("UPDATE placement_test SET is_published = ?, archived = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND created_by = ?");
    $result = $stmt->execute([$is_published, $archived, $test_id, $_SESSION['user_id']]);
    
    // Debug logging for result
    error_log("Update result: " . ($result ? 'SUCCESS' : 'FAILED') . ", Rows affected: " . $stmt->rowCount());
    
    if ($result) {
        $status_messages = [
            'draft' => 'Test unpublished successfully',
            'published' => 'Test published successfully',
            'archived' => 'Test archived successfully'
        ];
        
        echo json_encode([
            'success' => true, 
            'message' => $status_messages[$status],
            'data' => [
                'test_id' => $test_id,
                'new_status' => $status
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update test status']);
    }
    
} catch (Exception $e) {
    error_log("Error updating test status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
