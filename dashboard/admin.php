<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once '../includes/navigation_helper.php';
require_once '../includes/admin_notifications.php';
require_once 'audit_logger.php';
require_once 'performance_monitoring_functions.php';
require_once 'system_uptime_tracker.php';


// After loading database.php and before main logic
require_once '../includes/session_validator.php';
$sessionValidator = new SessionValidator($pdo);

if (!$sessionValidator->isSessionValid($_SESSION['user_id'])) {
    $sessionValidator->forceLogout('Your account access has been restricted.');
}


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if admin needs to change password (first time login)
if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->prepare("SELECT is_first_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user && $user['is_first_login']) {
        header('Location: change_password.php');
        exit();
    }
}

// Check admin access - either by permission or admin role
$has_admin_access = false;

// First check if user has nav_dashboard permission
if (function_exists('hasPermission')) {
    $has_admin_access = hasPermission($pdo, $_SESSION['user_id'], 'nav_dashboard');
}

// Fallback: Check if user has admin role
if (!$has_admin_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_admin_access = true;
}

// Final fallback: Check user role directly from database
if (!$has_admin_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_admin_access = true;
    }
}

if (!$has_admin_access) {
    header('Location: ../index.php');
    exit();
}

// Hybrid Admin Detection - Check if admin has teacher permissions
if ($_SESSION['role'] === 'admin') {
    // Get all user permissions
    $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // HYBRID DETECTION DISABLED - Always stay in regular admin dashboard
    // Admin users with teacher permissions will stay in admin.php
}

// Dashboard view only (payment history moved to separate file)
$view = 'dashboard';

// Manual performance logging for admin dashboard
if (defined('ENABLE_PERFORMANCE_MONITORING') && ENABLE_PERFORMANCE_MONITORING === true) {
    $start_time = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    
    // Register shutdown function to log performance
    register_shutdown_function(function() use ($start_time) {
        try {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            $status = $duration <= 3.0 ? 'fast' : ($duration <= 10.0 ? 'slow' : 'timeout');
            
            $sql = "
                INSERT INTO page_performance_log (
                    page_name, action_name, full_url, start_time, end_time, 
                    load_duration, status, user_id, session_id, ip_address, 
                    user_agent, device_type, browser, os
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $GLOBALS['pdo']->prepare($sql);
            $stmt->execute([
                'Admin Dashboard',
                'Page Load',
                $_SERVER['REQUEST_URI'] ?? '',
                date('Y-m-d H:i:s', (int)$start_time),
                date('Y-m-d H:i:s', (int)$end_time),
                round($duration, 3),
                $status,
                $_SESSION['user_id'] ?? null,
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                'Desktop',
                'Chrome', 
                'Windows'
            ]);
        } catch (Exception $e) {
            error_log("Admin performance logging failed: " . $e->getMessage());
        }
    });
}

// Calculate total revenue
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) as total_revenue 
    FROM payments 
    WHERE payment_status = 'completed'
");
$total_revenue = $stmt->fetchColumn();

// Fetch admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Get total counts for dashboard
$stmt = $pdo->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'teacher') as total_teachers,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM courses) as total_courses
");
$counts = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activities for notifications
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
$recent_activities = []; // Keep for backward compatibility

// Analytics Data for Dashboard Cards
// 1. Course Completion Rate
try {
    $started_stmt = $pdo->query("SELECT COUNT(*) FROM enrollments");
    $total_started = (int)$started_stmt->fetchColumn();
    
    // Check if course_progress table exists and has data
    $completed_stmt = $pdo->query("SELECT COUNT(*) FROM course_progress WHERE completion_status = 'completed'");
    $total_completed = (int)$completed_stmt->fetchColumn();
    
    $completion_rate = $total_started > 0 ? round(($total_completed / $total_started) * 100, 1) : 0;
} catch (PDOException $e) {
    // Fallback if course_progress table doesn't exist or has issues
    error_log("Course completion calculation error: " . $e->getMessage());
    $completion_rate = 0;
    $total_started = 0;
    $total_completed = 0;
}

// 2. User Engagement Metrics
$total_users_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$total_users = (int)$total_users_stmt->fetchColumn();
$active_users_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$active_users = (int)$active_users_stmt->fetchColumn();
$retention_rate = $total_users > 0 ? round(($active_users / $total_users) * 100, 1) : 0;

// 3. Sales Metrics
$sales_stmt = $pdo->query("SELECT COUNT(*) as modules_sold, COALESCE(SUM(amount),0) as total_revenue FROM payments WHERE payment_status = 'completed'");
$sales_row = $sales_stmt->fetch(PDO::FETCH_ASSOC);
$modules_sold = (int)$sales_row['modules_sold'];
$total_sales_revenue = (float)$sales_row['total_revenue'];
$avg_revenue_per_sale = $modules_sold > 0 ? round($total_sales_revenue / $modules_sold, 2) : 0;

// Course Completion Report Data
function getCourseCompletionData($pdo, $timeRange = 'all') {
    try {
        $whereClause = '';
        switch($timeRange) {
            case 'daily':
                $whereClause = "WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                break;
            case 'weekly':
                $whereClause = "WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'monthly':
                $whereClause = "WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            default:
                $whereClause = '';
        }
        
        $sql = "SELECT 
                    c.title as course_name,
                    c.id as course_id,
                    COUNT(e.id) as total_enrolled,
                    COUNT(CASE WHEN cp.completion_status = 'completed' THEN 1 END) as completed_count,
                    ROUND((COUNT(CASE WHEN cp.completion_status = 'completed' THEN 1 END) / COUNT(e.id)) * 100, 1) as completion_rate,
                    AVG(CASE WHEN cp.completion_status = 'completed' THEN cp.completion_percentage END) as avg_score
                FROM courses c
                LEFT JOIN enrollments e ON c.id = e.course_id
                LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
                $whereClause
                GROUP BY c.id, c.title
                HAVING total_enrolled > 0
                ORDER BY completion_rate DESC";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Course completion data error: " . $e->getMessage());
        return [];
    }
}

// User Retention Data
function getUserRetentionData($pdo, $timeRange = 'monthly') {
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Sales Analytics Data
function getSalesData($pdo, $timeRange = 'monthly') {
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get current user's permissions for navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);

// Get all permissions from database for dynamic navigation
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Get additional sales metrics
$new_customers_stmt = $pdo->query("SELECT COUNT(*) FROM (SELECT user_id, MIN(payment_date) as first_purchase FROM payments WHERE payment_status = 'completed' GROUP BY user_id HAVING first_purchase >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_cust");
$new_customers = (int)$new_customers_stmt->fetchColumn();

$repeat_customers_stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM payments WHERE payment_status = 'completed' GROUP BY user_id HAVING COUNT(*) > 1");
$repeat_customers = (int)$repeat_customers_stmt->fetchColumn();

$total_customers_stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM payments WHERE payment_status = 'completed'");
$total_customers = (int)$total_customers_stmt->fetchColumn();
$repeat_customer_rate = $total_customers > 0 ? round(($repeat_customers / $total_customers) * 100, 1) : 0;

// Growth Rate (compared to previous 30 days)
$prev_period_stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as prev_total FROM payments WHERE payment_status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND payment_date < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$prev_period_total = (float)$prev_period_stmt->fetchColumn();
$curr_period_stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) as curr_total FROM payments WHERE payment_status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$curr_period_total = (float)$curr_period_stmt->fetchColumn();
$sales_growth_rate = $prev_period_total > 0 ? round((($curr_period_total - $prev_period_total) / $prev_period_total) * 100, 1) : 0;

// Get data for charts
$course_completion_data = getCourseCompletionData($pdo);
$retention_data = getUserRetentionData($pdo);
$sales_data = getSalesData($pdo);

// Log dashboard access
$auditLogger = new AuditLogger($pdo);
$auditLogger->logEntry([
    'action_type' => 'READ',
    'action_description' => 'Accessed admin dashboard',
    'resource_type' => 'Dashboard',
    'resource_id' => 'Admin Dashboard',
    'resource_name' => 'Admin Dashboard',
    'outcome' => 'Success',
    'context' => [
        'view' => $view,
        'total_students' => $counts['total_students'],
        'total_teachers' => $counts['total_teachers'],
        'total_courses' => $counts['total_courses'],
        'total_revenue' => $total_revenue
    ]
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <script src="js/broken-link-monitor.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php echo $notificationSystem->renderNotificationAssets(); ?>
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Admin Portal</span>
            </div>
            <!-- Admin Profile -->
            <?php require_once __DIR__ . '/includes/sidebar_profile.php'; ?>
            
            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <?php 
                    // Dashboard permissions check
                    $dashboard_permissions = ['dashboard_view_metrics', 'dashboard_view_course_completion', 'dashboard_view_sales_report', 'dashboard_view_user_retention', 'dashboard_view_revenue_trends'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $dashboard_permissions)): ?>
                    <a href="?view=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php 
                    // Course Management permissions check
                    $course_permissions = ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $course_permissions)): ?>
                    <a href="course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php 
                    // User Management permissions check
                    $user_permissions = ['user_add_new', 'user_reset_password', 'user_change_password', 'user_ban_user', 'user_move_to_deleted', 'user_change_role'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_permissions)): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus-icon lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>
                    <!-- Reports Dropdown -->
                    <?php 
                    // Reports permissions check
                    $reports_permissions = [
                        'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 
                        'analytics_view_role_breakdown', 'analytics_view_activity_data', 
                        'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 
                        'user_roles_view_details', 'login_activity_view_metrics', 'login_activity_view_report',
                        'audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 
                        'audit_view_details', 'performance_logs_view', 'performance_logs_export', 
                        'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times',
                        'error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 
                        'error_logs_view_categories', 'error_logs_search_filter'
                    ];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $reports_permissions)): ?>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full text-gray-700 hover:bg-gray-100 focus:outline-none" :class="open ? 'bg-primary-50 text-primary-700 font-medium' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>
                            <span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php 
                            // Usage Analytics permissions check
                            $analytics_permissions = ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $analytics_permissions)): ?>
                            <a href="usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
     
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php 
                            // User Roles Report permissions check
                            $user_roles_permissions = ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $user_roles_permissions)): ?>
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
     
                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Login Activity permissions check
                            $login_activity_permissions = ['login_activity_view_metrics', 'login_activity_view_report', 'login_activity_view', 'login_activity_export_pdf'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $login_activity_permissions)): ?>
                            <a href="login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>      
     
                            Login Activity
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Security Warnings permissions check
                            $security_permissions = ['security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $security_permissions)): ?>
                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>     
      
                            Security Warnings
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Audit Trails permissions check
                            $audit_permissions = ['audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $audit_permissions)): ?>
                            <a href="audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
  
                            Audit Trails
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Performance Logs permissions check
                            $performance_permissions = ['performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
     
                            System Performance Logs
                            </a>
                            <?php endif; ?>

                            <?php 
                            $errorlogs_permissions = ['error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $errorlogs_permissions)): ?>
                            <a href="error-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            Error Logs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php
                    // Payment History permissions check
                    $payment_permissions = ['payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $payment_permissions)): ?>
                    <a href="payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins-icon lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>
                    <?php
                    $content_permission = ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $content_permission)): ?>
                    <a href="../dashboard/contentmanagement/content_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        Content Management
                    </a>
                    <?php endif; ?>
                    <!-- Settings Menu - Available to all admins -->
                    <a href="admin_settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                <!-- Push logout to bottom -->
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out-icon lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Logout
                    </a>
                </div>
            </nav>
        </div>
        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <!-- Mobile Menu Toggle -->
                        <button onclick="toggleMobileSidebar()" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <?php echo $notificationSystem->renderNotificationBell('System Notifications'); ?>
                        
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_metrics')): ?>
                        <!-- Total Students -->
                        <div class="dashboard-card">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1 w-0">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900"><?php echo $counts['total_students']; ?></div>
                                            <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                +12%
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_metrics')): ?>
                        <!-- Total Teachers -->
                        <div class="dashboard-card">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1 w-0">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Teachers</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900"><?php echo $counts['total_teachers']; ?></div>
                                            <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                +5%
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_metrics')): ?>
                        <!-- Total Modules -->
                        <div class="dashboard-card">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1 w-0">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Modules</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900"><?php echo $counts['total_courses']; ?></div>
                                            <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                +8%
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_metrics')): ?>
                        <!-- Total Revenue -->
                        <div class="dashboard-card">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1 w-0">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Revenue</dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900">₱<?php echo number_format($total_revenue, 0); ?></div>
                                            <div class="ml-2 flex items-baseline text-sm font-semibold <?php echo $sales_growth_rate >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo $sales_growth_rate >= 0 ? '+' : ''; ?><?php echo $sales_growth_rate; ?>%
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Analytics Sections -->
                    <div class="space-y-8">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_course_completion')): ?>
                        <!-- Course Completion Report -->
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-library-big-icon lucide-library-big"><rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/></svg>
                                    Course Completion Report
                                </h2>
                                <select id="completionTimeRange" class="dashboard-filter-select">
                                    <option value="all">All Time</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200" id="completionTable">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('completionTable', 0)">Course Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('completionTable', 1)">Enrolled</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('completionTable', 2)">Completed</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('completionTable', 3)">Completion Rate</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable('completionTable', 4)">Avg Score</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200" id="completionTableBody">
                                            <?php foreach ($course_completion_data as $course): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $course['total_enrolled']; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $course['completed_count']; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $course['completion_rate']; ?>%"></div>
                                                            </div>
                                                            <span class="text-sm text-gray-900"><?php echo $course['completion_rate']; ?>%</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $course['avg_score'] ? round($course['avg_score'], 1) . '%' : 'N/A'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_user_retention')): ?>
                        <!-- User Retention Report -->
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-column-increasing-icon lucide-chart-column-increasing"><path d="M13 17V9"/><path d="M18 17V5"/><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M8 17v-3"/></svg>                                    User Retention Report
                                </h2>
                                <select id="retentionTimeRange" class="dashboard-filter-select">
                                    <option value="monthly">Monthly</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="p-6">
                                <!-- Current Metrics Cards -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                    <div class="flex justify-between items-center p-3 bg-blue-200 rounded-lg">
                                        <span class="text-sm font-medium text-blue-900">Active Users</span>
                                        <span class="text-lg font-bold text-blue-600"><?php echo $active_users; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center p-3 bg-green-200 rounded-lg">
                                        <span class="text-sm font-medium text-green-900">Retention Rate</span>
                                        <span class="text-lg font-bold text-green-600"><?php echo $retention_rate; ?>%</span>
                                    </div>
                                    <div class="flex justify-between items-center p-3 bg-red-200 rounded-lg">
                                        <span class="text-sm font-medium text-red-900">Churn Rate</span>
                                        <span class="text-lg font-bold text-red-600"><?php echo 100 - $retention_rate; ?>%</span>
                                    </div>
                                </div>
                                <!-- Retention Chart -->
                                <div class="chart-container">
                                    <h3 class="flex items-center gap-2 text-lg font-medium text-gray-900 mb-4">
                                        <!-- Lucide Icon -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trending-up-down-icon lucide-trending-up-down"><path d="M14.828 14.828 21 21"/><path d="M21 16v5h-5"/><path d="m21 3-9 9-4-4-6 6"/><path d="M21 8V3h-5"/></svg>
                                        Active vs Churned Users Over Time
                                    </h3>
                                    <canvas id="retentionChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_sales_report')): ?>
                        <!-- Sales Reports -->
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-philippine-peso-icon lucide-philippine-peso"><path d="M20 11H4"/><path d="M20 7H4"/><path d="M7 21V4a1 1 0 0 1 1-1h4a1 1 0 0 1 0 12H7"/></svg>
                                    Sales Reports
                                </h2>
                                <select id="salesTimeRange" class="dashboard-filter-select">
                                    <option value="monthly">Monthly</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="p-6">
                                <!-- Sales Metrics Cards -->
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                                    <div class="sales-metric">
                                        <div class="text-2xl font-bold text-primary-600">₱<?php echo number_format($total_sales_revenue, 0); ?></div>
                                        <div class="text-sm text-gray-500">Total Sales Revenue</div>
                                    </div>
                                    <div class="sales-metric">
                                        <div class="text-2xl font-bold text-primary-600"><?php echo $modules_sold; ?></div>
                                        <div class="text-sm text-gray-500">Modules Sold</div>
                                    </div>
                                    <div class="sales-metric">
                                        <div class="text-2xl font-bold text-primary-600">₱<?php echo number_format($avg_revenue_per_sale, 2); ?></div>
                                        <div class="text-sm text-gray-500">Avg Revenue per Sale</div>
                                    </div>
                                    <div class="sales-metric">
                                        <div class="text-2xl font-bold text-primary-600"><?php echo $new_customers; ?></div>
                                        <div class="text-sm text-gray-500">New Customers</div>
                                    </div>
                                    <div class="sales-metric">
                                        <div class="text-2xl font-bold text-primary-600"><?php echo $repeat_customer_rate; ?>%</div>
                                        <div class="text-sm text-gray-500">Repeat Customer Rate</div>
                                    </div>
                                    <div class="sales-metric">
                                        <div class="text-2xl font-bold text-primary-600 <?php echo $sales_growth_rate >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $sales_growth_rate >= 0 ? '+' : ''; ?><?php echo $sales_growth_rate; ?>%
                                        </div>
                                        <div class="text-sm text-gray-500">Growth Rate</div>
                                    </div>
                                </div>

                                <!-- Sales Chart -->
                                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'dashboard_view_revenue_trends')): ?>
                                <div class="chart-container">
                                    <h3 class="flex items-center gap-2 text-lg font-medium text-gray-900 mb-4">
                                        <!-- Lucide Icon -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" 
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                            class="text-gray-700">
                                        <path d="m13.11 7.664 1.78 2.672"/>
                                        <path d="m14.162 12.788-3.324 1.424"/>
                                        <path d="m20 4-6.06 1.515"/>
                                        <path d="M3 3v16a2 2 0 0 0 2 2h16"/>
                                        <circle cx="12" cy="6" r="2"/>
                                        <circle cx="16" cy="12" r="2"/>
                                        <circle cx="9" cy="15" r="2"/>
                                        </svg>
                                        Revenue Trends
                                    </h3>

                                    <canvas id="salesChart" width="400" height="200"></canvas>
                                    </div>
                                <?php endif; ?>
                                                                </div>
                        </div>
                        <?php endif; ?>
                    </div>
            </main>
        </div>
    </div>
    
    <!-- Force Logout Modal HTML - Compact Rectangle Design -->
<div id="forceLogoutOverlay" class="force-logout-overlay">
    <div class="force-logout-modal">
        <div class="force-logout-header">
            <div class="force-logout-icon">⚠️</div>
            <h2 class="force-logout-title">Session Terminated</h2>
        </div>
        <div class="force-logout-content">
            <p class="force-logout-message" id="forceLogoutMessage">
                Your account has been banned. Please contact support for more information.
            </p>
            <div class="force-logout-footer">
                <div class="force-logout-countdown" id="forceLogoutCountdown">8</div>
                <button class="force-logout-button" onclick="window.location.href='/AIToManabi_Updated/dashboard/login.php'">
                    Go to Login Now
                </button>
            </div>
        </div>
    </div>
</div>

    <script>
        // Dashboard data for charts
        const courseCompletionData = <?php echo json_encode($course_completion_data); ?>;
        const retentionData = <?php echo json_encode($retention_data); ?>;
        const salesData = <?php echo json_encode($sales_data); ?>;
        
        // Real-time clock for Philippines timezone
        function updateRealTimeClock() {
            const now = new Date();
            const philippinesTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
            
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'
            };
            
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'Asia/Manila'
            };
            
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement) {
                timeElement.textContent = philippinesTime.toLocaleTimeString('en-US', timeOptions) + ' (PHT)';
            }
            
            if (dateElement) {
                dateElement.textContent = philippinesTime.toLocaleDateString('en-US', dateOptions);
            }
        }

        // Initialize real-time clock
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
        });
    </script>
    <script src="js/admin-dashboard.js"></script>
    <script src="js/password_reset_notification.js"></script>
    <script>
        // Initialize password reset notification for admin
        document.addEventListener('DOMContentLoaded', function() {
            const passwordResetNotification = new PasswordResetNotification('admin', 'admin_settings.php');
            passwordResetNotification.init();
        });
    </script>

    <!-- Auto Force Logout Check -->
<script>
(function() {
    // Configuration
    const CHECK_INTERVAL = 3000; // Check every 3 seconds
    const API_ENDPOINT = '../includes/check_session_status.php';
    const COUNTDOWN_SECONDS = 8; // Countdown before auto-redirect
    
    let isCheckingSession = false;
    let countdownInterval = null;
    
    // Function to show modern logout modal
    function showForceLogoutModal(reason) {
        const overlay = document.getElementById('forceLogoutOverlay');
        const messageEl = document.getElementById('forceLogoutMessage');
        const countdownEl = document.getElementById('forceLogoutCountdown');
        
        // Set the reason message
        messageEl.textContent = reason || 'Your session has been terminated.';
        
        // Show the modal
        overlay.style.display = 'block';
        
        // Start countdown
        let secondsLeft = COUNTDOWN_SECONDS;
        countdownEl.textContent = secondsLeft;
        
        countdownInterval = setInterval(() => {
            secondsLeft--;
            countdownEl.textContent = secondsLeft;
            
            if (secondsLeft <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '/AIToManabi_Updated/dashboard/login.php';
            }
        }, 1000);
    }
    
    // Function to check session status
    function checkSessionStatus() {
        if (isCheckingSession) return;
        isCheckingSession = true;
        
        fetch(API_ENDPOINT, {
            method: 'GET',
            cache: 'no-cache',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.valid) {
                console.log('Session invalidated:', data.reason);
                showForceLogoutModal(data.reason);
            }
        })
        .catch(error => {
            console.error('Session check error:', error);
        })
        .finally(() => {
            isCheckingSession = false;
        });
    }
    
    // Start checking when page loads
    console.log('🔒 Auto force-logout checker started (checking every ' + (CHECK_INTERVAL/1000) + ' seconds)');
    
    // Initial check after 2 seconds
    setTimeout(checkSessionStatus, 2000);
    
    // Regular interval checks
    setInterval(checkSessionStatus, CHECK_INTERVAL);
    
    // Also check on user activity
    let activityTimer;
    function onUserActivity() {
        clearTimeout(activityTimer);
        activityTimer = setTimeout(checkSessionStatus, 1000);
    }
    
    document.addEventListener('click', onUserActivity);
    document.addEventListener('mousemove', onUserActivity);
    document.addEventListener('keypress', onUserActivity);
})();
</script>

<!-- Session Timeout Manager -->
<script src="js/session_timeout.js"></script>

</body>
</html>
