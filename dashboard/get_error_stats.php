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

try {
    $errorLogsMonitor = new ErrorLogsMonitor($pdo);
    
    // Get statistics
    $stats = $errorLogsMonitor->getErrorStatistics();
    
    // Get recent activities
    $recentActivities = $errorLogsMonitor->getRecentActivities(10);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_activities' => $recentActivities
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching error statistics: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch statistics'
    ]);
}
?>
