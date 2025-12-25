<?php
session_start();

// Set proper headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/database.php';

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    if (!isset($data['test_id']) || !isset($data['assignments'])) {
        throw new Exception('Missing required fields');
    }
    
    $test_id = (int)$data['test_id'];
    $assignments = $data['assignments'];
    
    // Simple validation - check if test exists (teachers can edit any test)
    $stmt = $pdo->prepare("SELECT id FROM placement_test WHERE id = ?");
    $stmt->execute([$test_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Test not found');
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update the placement test with module assignments
    $assignmentsJson = json_encode($assignments);
    
    $stmt = $pdo->prepare("
        UPDATE placement_test 
        SET module_assignments = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $stmt->execute([$assignmentsJson, $test_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Module assignments saved successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if it was started
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error saving module assignments: " . $e->getMessage());
    error_log("Assignments data: " . json_encode($assignments ?? null));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'test_id' => $test_id ?? null,
            'user_id' => $_SESSION['user_id'],
            'has_assignments' => isset($assignments)
        ]
    ]);
}
?>