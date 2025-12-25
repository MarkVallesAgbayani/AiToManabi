<?php
require_once('../config/database.php');
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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

if (!$input || !isset($input['category_id']) || !isset($input['course_order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

$category_id = (int)$input['category_id'];
$course_order = $input['course_order'];

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Verify category exists and user has access
    $stmt = $pdo->prepare("SELECT id FROM course_category WHERE id = ?");
    $stmt->execute([$category_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Category not found');
    }
    
    // Update sort order for each course
    $updateStmt = $pdo->prepare("UPDATE courses SET sort_order = ? WHERE id = ? AND course_category_id = ?");
    
    foreach ($course_order as $order_item) {
        $course_id = (int)$order_item['course_id'];
        $sort_order = (int)$order_item['sort_order'];
        
        // Verify course exists in this category
        $verifyStmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND course_category_id = ?");
        $verifyStmt->execute([$course_id, $category_id]);
        
        if ($verifyStmt->fetch()) {
            $updateStmt->execute([$sort_order, $course_id, $category_id]);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log the activity
    error_log("Course order updated for category {$category_id} by user {$_SESSION['user_id']}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Course order updated successfully',
        'updated_count' => count($course_order)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Error updating course order: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update course order: ' . $e->getMessage()
    ]);
}
?>
