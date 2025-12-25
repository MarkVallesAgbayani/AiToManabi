<?php
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get the test ID from the request
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Debug logging
    error_log("Delete test request received: " . json_encode($input));
    error_log("User ID: " . $_SESSION['user_id']);
    
    if (!isset($input['test_id']) || empty($input['test_id'])) {
        throw new Exception('Test ID is required');
    }
    
    $test_id = (int)$input['test_id'];
    
    if ($test_id <= 0) {
        throw new Exception('Invalid test ID');
    }
    
    error_log("Attempting to delete test ID: $test_id");
    
    // Start transaction
    $pdo->beginTransaction();
    
    // First, check if the test exists and belongs to the current teacher
    $stmt = $pdo->prepare("SELECT id, title FROM placement_test WHERE id = ? AND created_by = ?");
    $stmt->execute([$test_id, $_SESSION['user_id']]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        throw new Exception('Test not found or you do not have permission to delete it');
    }
    
    // Delete related placement results first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM placement_result WHERE test_id = ?");
    $stmt->execute([$test_id]);
    $deletedResults = $stmt->rowCount();
    error_log("Deleted $deletedResults placement results");
    
    // Note: placement_session table doesn't have test_id column, so we don't delete from it
    // The placement_session table is for general student sessions, not test-specific
    
    // Delete the test itself
    $stmt = $pdo->prepare("DELETE FROM placement_test WHERE id = ? AND created_by = ?");
    $stmt->execute([$test_id, $_SESSION['user_id']]);
    $deletedTests = $stmt->rowCount();
    error_log("Deleted $deletedTests placement tests");
    
    if ($deletedTests === 0) {
        throw new Exception('Failed to delete test - no rows affected');
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Test '{$test['title']}' has been permanently deleted"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log("Delete test error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
