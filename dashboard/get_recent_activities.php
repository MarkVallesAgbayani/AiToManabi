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
    // Get recent activities for today
    $recentFilters = [
        'date_from' => date('Y-m-d'),
        'date_to' => date('Y-m-d')
    ];
    
    $recentActivities = getRealAuditData($pdo, 0, 10, $recentFilters);
    
    // Format activities for notifications
    $formattedActivities = [];
    foreach ($recentActivities as $activity) {
        $timeAgo = time() - strtotime($activity['timestamp']);
        $timeString = '';
        
        if ($timeAgo < 60) {
            $timeString = 'Just now';
        } elseif ($timeAgo < 3600) {
            $timeString = floor($timeAgo/60) . 'm ago';
        } elseif ($timeAgo < 86400) {
            $timeString = floor($timeAgo/3600) . 'h ago';
        } else {
            $timeString = floor($timeAgo/86400) . 'd ago';
        }
        
        $formattedActivities[] = [
            'id' => $activity['id'],
            'action_type' => $activity['action_type'],
            'action_description' => substr($activity['action_description'], 0, 50) . (strlen($activity['action_description']) > 50 ? '...' : ''),
            'username' => $activity['username'],
            'outcome' => $activity['outcome'],
            'time_ago' => $timeString,
            'timestamp' => $activity['timestamp']
        ];
    }
    
    // Get count of activities in last hour for notification badge
    $hourlyFilters = [
        'date_from' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'date_to' => date('Y-m-d H:i:s')
    ];
    
    $hourlyActivities = getRealAuditData($pdo, 0, 100, $hourlyFilters);
    $notificationCount = count($hourlyActivities);
    
    echo json_encode([
        'success' => true,
        'activities' => $formattedActivities,
        'count' => $notificationCount,
        'total_today' => count($recentActivities)
    ]);

} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch recent activities'
    ]);
}
?>
