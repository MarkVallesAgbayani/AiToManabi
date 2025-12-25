<?php
session_start();
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';

// Check if user is logged in and has admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check admin access
$has_admin_access = false;
if (function_exists('hasPermission')) {
    $has_admin_access = hasPermission($pdo, $_SESSION['user_id'], 'nav_dashboard');
}
if (!$has_admin_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_admin_access = true;
}
if (!$has_admin_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_admin_access = true;
    }
}

if (!$has_admin_access) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// Get time range from request
$timeRange = isset($_GET['range']) ? $_GET['range'] : 'monthly';

// Validate time range
$allowedRanges = ['weekly', 'monthly', 'quarterly', 'yearly'];
if (!in_array($timeRange, $allowedRanges)) {
    $timeRange = 'monthly';
}

try {
    // Build SQL query based on time range
    switch($timeRange) {
        case 'weekly':
            $sql = "SELECT 
                        YEARWEEK(created_at) as period,
                        COUNT(*) as new_users,
                        COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users,
                        COUNT(CASE WHEN last_login_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as churned_users
                    FROM users 
                    WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                    GROUP BY YEARWEEK(created_at)
                    ORDER BY period DESC";
            break;
        case 'quarterly':
            $sql = "SELECT 
                        CONCAT(YEAR(created_at), '-Q', QUARTER(created_at)) as period,
                        COUNT(*) as new_users,
                        COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users,
                        COUNT(CASE WHEN last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) as churned_users
                    FROM users 
                    WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
                    GROUP BY YEAR(created_at), QUARTER(created_at)
                    ORDER BY period DESC";
            break;
        case 'yearly':
            $sql = "SELECT 
                        YEAR(created_at) as period,
                        COUNT(*) as new_users,
                        COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users,
                        COUNT(CASE WHEN last_login_at < DATE_SUB(NOW(), INTERVAL 365 DAY) THEN 1 END) as churned_users
                    FROM users 
                    WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
                    GROUP BY YEAR(created_at)
                    ORDER BY period DESC";
            break;
        default: // monthly
            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as period,
                        COUNT(*) as new_users,
                        COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users,
                        COUNT(CASE WHEN last_login_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as churned_users
                    FROM users 
                    WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY period DESC";
    }
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timeRange' => $timeRange
    ]);
    
} catch (PDOException $e) {
    error_log("User retention data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
