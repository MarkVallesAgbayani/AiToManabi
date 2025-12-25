<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Include required files
require_once '../config/database.php';
require_once 'error_logs_functions.php';

// Get error ID from request
$errorId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($errorId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid error ID']);
    exit();
}

try {
    $errorLogsMonitor = new ErrorLogsMonitor($pdo);
    $errorDetails = $errorLogsMonitor->getErrorDetails($errorId);
    
    if ($errorDetails) {
        echo json_encode([
            'success' => true,
            'error' => $errorDetails
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error details not found'
        ]);
    }
} catch (Exception $e) {
    error_log("Error fetching error details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch error details'
    ]);
}
?>
