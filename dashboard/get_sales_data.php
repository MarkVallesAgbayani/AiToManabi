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
$allowedRanges = ['daily', 'weekly', 'monthly', 'yearly'];
if (!in_array($timeRange, $allowedRanges)) {
    $timeRange = 'monthly';
}

try {
    // Build SQL query based on time range
    switch($timeRange) {
        case 'daily':
            $sql = "SELECT 
                        DATE(payment_date) as period,
                        COUNT(*) as total_sales,
                        SUM(amount) as total_revenue,
                        COUNT(DISTINCT user_id) as unique_customers,
                        AVG(amount) as avg_revenue_per_sale
                    FROM payments 
                    WHERE payment_status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(payment_date)
                    ORDER BY period DESC";
            break;
        case 'weekly':
            $sql = "SELECT 
                        YEARWEEK(payment_date) as period,
                        COUNT(*) as total_sales,
                        SUM(amount) as total_revenue,
                        COUNT(DISTINCT user_id) as unique_customers,
                        AVG(amount) as avg_revenue_per_sale
                    FROM payments 
                    WHERE payment_status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                    GROUP BY YEARWEEK(payment_date)
                    ORDER BY period DESC";
            break;
        case 'yearly':
            $sql = "SELECT 
                        YEAR(payment_date) as period,
                        COUNT(*) as total_sales,
                        SUM(amount) as total_revenue,
                        COUNT(DISTINCT user_id) as unique_customers,
                        AVG(amount) as avg_revenue_per_sale
                    FROM payments 
                    WHERE payment_status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
                    GROUP BY YEAR(payment_date)
                    ORDER BY period DESC";
            break;
        default: // monthly
            $sql = "SELECT 
                        DATE_FORMAT(payment_date, '%Y-%m') as period,
                        COUNT(*) as total_sales,
                        SUM(amount) as total_revenue,
                        COUNT(DISTINCT user_id) as unique_customers,
                        AVG(amount) as avg_revenue_per_sale
                    FROM payments 
                    WHERE payment_status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
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
    error_log("Sales data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
