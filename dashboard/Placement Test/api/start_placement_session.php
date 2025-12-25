<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');

require_once '../../../config/database.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        $test_id = (int)($input['test_id'] ?? 0);
        $session_token = trim($input['session_token'] ?? '');
        $session_type = trim($input['session_type'] ?? 'test_attempt');
        
        if (!$test_id || !$session_token) {
            throw new Exception('Missing required parameters');
        }

        // Check if user is logged in (optional for placement tests)
        $student_id = null;
        if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
            $student_id = (int)$_SESSION['user_id'];
        }

        // Verify test exists and is published
        $stmt = $pdo->prepare("SELECT id FROM placement_test WHERE id = ? AND is_published = 1");
        $stmt->execute([$test_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Test not found or not published');
        }

        // Check if session already exists
        $stmt = $pdo->prepare("SELECT id FROM placement_session WHERE session_token = ?");
        $stmt->execute([$session_token]);
        if ($stmt->fetch()) {
            sendJsonResponse(true, 'Session already exists', ['session_token' => $session_token]);
        }

        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO placement_session (
                student_id, session_token, session_type, session_data, 
                status, ip_address, user_agent, expires_at
            ) VALUES (?, ?, ?, ?, 'active', ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))
        ");
        
        $session_data = json_encode([
            'test_id' => $test_id,
            'started_at' => date('Y-m-d H:i:s')
        ]);
        
        $stmt->execute([
            $student_id,
            $session_token,
            $session_type,
            $session_data,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        sendJsonResponse(true, 'Session started successfully', [
            'session_token' => $session_token,
            'student_id' => $student_id
        ]);

    } catch (Exception $e) {
        error_log("Error starting placement session: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage(), null, 400);
    }
} else {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}
?>
