<?php
session_start();
require_once '../config/database.php';
require_once 'audit_database_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

try {
    // Get fresh statistics
    $statistics = getRealAuditStatistics($pdo);
    
    // Add additional real-time metrics
    $statistics['last_updated'] = date('Y-m-d H:i:s');
    $statistics['server_time'] = time();
    
    // Get activity trend (last 24 hours)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                HOUR(timestamp) as hour,
                COUNT(*) as count
            FROM comprehensive_audit_trail 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY HOUR(timestamp)
            ORDER BY hour
        ");
        $stmt->execute();
        $hourly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $statistics['hourly_activity'] = $hourly_activity;
    } catch (Exception $e) {
        $statistics['hourly_activity'] = [];
    }
    
    // Get recent failed actions count
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM comprehensive_audit_trail 
            WHERE outcome = 'Failed' 
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $statistics['recent_failures'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        $statistics['recent_failures'] = 0;
    }
    
    // Get top active users today
    try {
        $stmt = $pdo->prepare("
            SELECT 
                username,
                COUNT(*) as action_count
            FROM comprehensive_audit_trail 
            WHERE DATE(timestamp) = CURDATE()
            GROUP BY user_id, username
            ORDER BY action_count DESC
            LIMIT 5
        ");
        $stmt->execute();
        $statistics['top_users_today'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $statistics['top_users_today'] = [];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $statistics
    ]);

} catch (Exception $e) {
    error_log("Audit stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch audit statistics'
    ]);
}
?>
