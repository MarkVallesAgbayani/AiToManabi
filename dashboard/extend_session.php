<?php
session_start();
require_once '../config/database.php';

// Set content type for AJAX requests
header('Content-Type: application/json');

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit();
    }
    
    // Update session with new timestamp
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Log the session extension activity
    try {
        require_once 'real_time_activity_logger.php';
        $logger = new RealTimeActivityLogger($pdo);
        $logger->logPageView('session_extend', [
            'page_title' => 'Session Extended',
            'access_time' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'],
            'extend_reason' => 'user_request'
        ]);
    } catch (Exception $e) {
        error_log("Activity logging error during session extension: " . $e->getMessage());
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Session extended successfully',
        'timestamp' => time(),
        'expires_at' => time() + (15 * 60) // 15 minutes from now
    ]);
    
} catch (Exception $e) {
    error_log("Session extension error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error extending session'
    ]);
}
?>
