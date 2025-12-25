<?php
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

try {
    // Include required files
    require_once '../config/database.php';
    require_once 'performance_monitoring_functions.php';
    
    // Initialize performance monitor
    $performanceMonitor = new PerformanceMonitor($pdo);
    
    // Get statistics
    $stats = $performanceMonitor->getPerformanceStatistics();
    
    // Get recent activities
    $recentActivities = $performanceMonitor->getRecentActivities(10);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_activities' => $recentActivities,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
