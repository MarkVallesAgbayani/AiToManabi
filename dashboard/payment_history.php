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
require_once 'includes/admin_profile_functions.php';

// Email masking function
function maskEmail($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    $parts = explode('@', $email);
    $username = $parts[0];
    $domain = $parts[1];
    
    // Mask username part
    $usernameLength = strlen($username);
    if ($usernameLength <= 2) {
        $maskedUsername = str_repeat('*', $usernameLength);
    } else {
        $visibleChars = min(2, floor($usernameLength / 3));
        $maskedUsername = substr($username, 0, $visibleChars) . str_repeat('*', $usernameLength - $visibleChars);
    }
    
    return $maskedUsername . '@' . $domain;
}


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if user has permission to view payment history or is admin
if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf'])) {
    header('Location: ../index.php');
    exit();
}

// Pagination for payment history
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total payment count
$stmt = $pdo->query("SELECT COUNT(*) FROM payments");
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get payment history with pagination
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.username as student_name,
        u.email as student_email,
        c.title as course_title
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN courses c ON p.course_id = c.id
    ORDER BY p.payment_date DESC
    LIMIT ? OFFSET ?
");

try {
    $stmt->execute([$limit, $offset]);
    $payment_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Payment query error: " . $e->getMessage());
    $payment_history = [];
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

// Get recent activities for notifications
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Get current user's permissions for navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);

// Get all permissions from database for dynamic navigation
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Log audit entry for accessing payment history
$auditLogger = new AuditLogger($pdo);
$auditLogger->logEntry([
    'action_type' => 'READ',
    'action_description' => 'Accessed payment history report',
    'resource_type' => 'Dashboard',
    'resource_id' => 'Payment History',
    'resource_name' => 'Payment History Report',
    'outcome' => 'Success',
    'context' => [
        'page' => $page,
        'limit' => $limit,
        'total_records' => $total_records,
        'total_revenue' => $total_revenue,
        'user_permissions' => ['payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf']
    ]
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Japanese Learning Platform</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            <?php
            // Use admin profile helpers to render display name and profile picture
            $admin_profile = getAdminProfile($pdo, $_SESSION['user_id']);
            $display_name = getAdminDisplayName($admin_profile);
            $picture = getAdminProfilePicture($admin_profile);
            ?>
            <div class="p-4 border-b flex items-center space-x-3">
                <?php if ($picture['has_image']): ?>
                    <img src="<?php echo '../' . htmlspecialchars($picture['image_path']); ?>" 
                         alt="Profile Picture" 
                         class="w-12 h-12 rounded-full object-cover shadow-md sidebar-profile-picture"
                         onerror="console.error('Failed to load image:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder" style="display: none;">
                        <?php echo htmlspecialchars($picture['initial']); ?>
                    </div>
                <?php else: ?>
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-lg shadow-md sidebar-profile-placeholder">
                        <?php echo htmlspecialchars($picture['initial']); ?>
                    </div>
                <?php endif; ?>
                <div class="flex-1">
                    <div class="font-medium sidebar-display-name truncate"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="text-sm text-gray-500 sidebar-role">Administrator</div>
                </div>
            </div>
            
            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <?php 
                    // Dashboard permissions check
                    $dashboard_permissions = ['dashboard_view_metrics', 'dashboard_view_course_completion', 'dashboard_view_sales_report', 'dashboard_view_user_retention'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $dashboard_permissions)): ?>
                    <a href="admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
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
                        'user_roles_view_details', 'login_activity_view_metrics', 'login_activity_view_report'
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
                            $security_permissions = ['nav_security_warnings', 'security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $security_permissions)): ?>
                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>     
                            Security Warnings
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Audit Trails permissions check
                            $audit_permissions = ['nav_audit_trails', 'audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $audit_permissions)): ?>
                            <a href="audit-trails.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
                            Audit Trails
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Performance Logs permissions check
                            $performance_permissions = ['nav_performance_logs', 'performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'];
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $performance_permissions)): ?>
                            <a href="performance-logs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                            System Performance Logs
                            </a>
                            <?php endif; ?>
                            <?php 
                            // Error Logs permissions check
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
                    <a href="payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins-icon lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>
                    <?php 
                    // Content Management permissions check
                    $content_permissions = ['content_manage_announcement', 'content_manage_terms', 'content_manage_privacy'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $content_permissions)): ?>
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
                        <h1 class="text-2xl font-semibold text-gray-900">Payment History</h1>
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

            <!-- Search, Filter, and Sort Controls -->
            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'payment_view_history')): ?>
<div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
    <div class="flex flex-wrap gap-4 items-center justify-between">
        <!-- Search Box -->
        <div class="flex-1 min-w-[250px]">
            <div class="relative">
                <input type="text" 
                       id="search-payment" 
                       placeholder="Search by student name, email, or course..." 
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm pl-10 pr-3 py-2"
                       onkeyup="filterPayments()">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="flex gap-3 items-center">
            <!-- Date Range Filter -->
            <select id="date-filter" 
                    onchange="filterPayments()" 
                    class="block rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                <option value="">All Dates</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last7days">Last 7 Days</option>
                <option value="last30days">Last 30 Days</option>
                <option value="thisweek">This Week</option>
                <option value="thismonth">This Month</option>
                <option value="lastmonth">Last Month</option>
                <option value="thisyear">This Year</option>
            </select>
            
            <!-- Status Filter -->
            <select id="status-filter" 
                    onchange="filterPayments()" 
                    class="block rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                <option value="">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
            </select>
            
            <!-- Sort Dropdown -->
            <select id="sort-by" 
                    onchange="sortPayments()" 
                    class="block rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                <option value="">Sort By</option>
                <option value="date-desc">Date (Newest First)</option>
                <option value="date-asc">Date (Oldest First)</option>
                <option value="amount-desc">Amount (Highest First)</option>
                <option value="amount-asc">Amount (Lowest First)</option>
                <option value="student-asc">Student Name (A-Z)</option>
                <option value="student-desc">Student Name (Z-A)</option>
                <option value="course-asc">Course (A-Z)</option>
                <option value="course-desc">Course (Z-A)</option>
            </select>
            
            <!-- Clear Filters Button -->
            <button onclick="clearAllFilters()" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Clear
            </button>
        </div>
    </div>
    
    <!-- Results Counter -->
    <div class="mt-3 text-sm text-gray-600">
        Showing <span id="visible-count" class="font-semibold"><?php echo count($payment_history); ?></span> 
        of <span id="total-count" class="font-semibold"><?php echo count($payment_history); ?></span> payments
    </div>
</div>
            <?php endif; ?>


            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Payment History View -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-semibold">Payment History</h2>
                        <div class="flex items-center gap-4">
                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'payment_view_metrics')): ?>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">Total Revenue</p>
                                <p class="text-2xl font-bold text-green-600">PHP <?php echo number_format($total_revenue, 2); ?></p>
                            </div>
                            <?php endif; ?>
                            <!-- Export Button -->
                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'payment_export_pdf')): ?>
                            <a href="export_payment_history.php" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Export PDF
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'payment_view_history')): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($payment_history as $payment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($payment['student_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars(maskEmail($payment['student_email'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['course_title']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        PHP <?php echo number_format($payment['amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            echo match($payment['payment_status']) {
                                                'completed' => 'bg-green-100 text-green-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'failed' => 'bg-red-100 text-red-800',
                                                'refunded' => 'bg-gray-100 text-gray-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                            ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'payment_export_invoice_pdf')): ?>
                                        <a href="generate_invoice.php?payment_id=<?php echo $payment['id']; ?>" 
                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200"
                                           title="Download Invoice for Payment #<?php echo $payment['id']; ?>">
                                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Invoice
                                        </a>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-gray-400 bg-gray-100">
                                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                            </svg>
                                            No Access
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-8 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Access Restricted</h3>
                        <p class="text-gray-600">You don't have permission to view payment history. Please contact your administrator for access.</p>
                    </div>
                    <?php endif; ?>

                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo ($page - 1); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo ($page + 1); ?>" 
                                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing
                                        <span class="font-medium"><?php echo $offset + 1; ?></span>
                                        to
                                        <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span>
                                        of
                                        <span class="font-medium"><?php echo $total_records; ?></span>
                                        results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?php echo ($page - 1); ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>

                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);

                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <a href="?page=<?php echo $i; ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-primary-600 bg-primary-50 border-primary-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?php echo ($page + 1); ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Next</span>
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        function toggleMobileSidebar() {
            // Mobile sidebar toggle functionality
            const sidebar = document.querySelector('.bg-white.w-64');
            sidebar.classList.toggle('hidden');
        }
        
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


        // Filter and Sort Functions for Payment History
function filterPayments() {
    const searchTerm = document.getElementById('search-payment').value.toLowerCase();
    const dateFilter = document.getElementById('date-filter').value;
    const statusFilter = document.getElementById('status-filter').value.toLowerCase();
    const tbody = document.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    let visibleCount = 0;
    const totalCount = rows.length;
    
    // Get date range based on filter
    let startDate = null;
    let endDate = null;
    
    if (dateFilter) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        switch(dateFilter) {
            case 'today':
                startDate = new Date(today);
                endDate = new Date(today);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'yesterday':
                startDate = new Date(today);
                startDate.setDate(today.getDate() - 1);
                endDate = new Date(startDate);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'last7days':
                startDate = new Date(today);
                startDate.setDate(today.getDate() - 6);
                endDate = new Date(today);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'last30days':
                startDate = new Date(today);
                startDate.setDate(today.getDate() - 29);
                endDate = new Date(today);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'thisweek':
                startDate = new Date(today);
                const dayOfWeek = today.getDay();
                const diff = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
                startDate.setDate(today.getDate() - diff);
                endDate = new Date(today);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'thismonth':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'lastmonth':
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                endDate.setHours(23, 59, 59, 999);
                break;
            case 'thisyear':
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today);
                endDate.setHours(23, 59, 59, 999);
                break;
        }
    }
    
    rows.forEach(row => {
        // Get cell data
        const dateCell = row.querySelectorAll('td')[0];
        const studentCell = row.querySelectorAll('td')[1];
        const courseCell = row.querySelectorAll('td')[2];
        const statusCell = row.querySelectorAll('td')[4];
        
        if (!dateCell || !studentCell || !courseCell || !statusCell) return;
        
        // Extract text content
        const dateText = dateCell.textContent.trim();
        const studentName = studentCell.querySelector('.text-gray-900') ? 
                           studentCell.querySelector('.text-gray-900').textContent.toLowerCase() : '';
        const studentEmail = studentCell.querySelector('.text-gray-500') ? 
                            studentCell.querySelector('.text-gray-500').textContent.toLowerCase() : '';
        const courseTitle = courseCell.textContent.toLowerCase();
        const statusText = statusCell.querySelector('span') ? 
                          statusCell.querySelector('span').textContent.toLowerCase() : '';
        
        // Parse payment date
        const paymentDate = new Date(dateText);
        
        // Apply filters
        const matchesSearch = !searchTerm || 
                             studentName.includes(searchTerm) || 
                             studentEmail.includes(searchTerm) || 
                             courseTitle.includes(searchTerm);
        
        const matchesDate = !dateFilter || 
                           (paymentDate >= startDate && paymentDate <= endDate);
        
        const matchesStatus = !statusFilter || statusText.includes(statusFilter);
        
        // Show/hide row
        if (matchesSearch && matchesDate && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update counter
    document.getElementById('visible-count').textContent = visibleCount;
    document.getElementById('total-count').textContent = totalCount;
}

function sortPayments() {
    const sortBy = document.getElementById('sort-by').value;
    if (!sortBy) return;
    
    const tbody = document.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let aValue, bValue;
        
        switch(sortBy) {
            case 'date-desc':
            case 'date-asc':
                const aDate = new Date(a.querySelectorAll('td')[0].textContent.trim());
                const bDate = new Date(b.querySelectorAll('td')[0].textContent.trim());
                aValue = aDate.getTime();
                bValue = bDate.getTime();
                break;
                
            case 'amount-desc':
            case 'amount-asc':
                const aAmount = parseFloat(a.querySelectorAll('td')[3].textContent.replace(/[^\d.]/g, ''));
                const bAmount = parseFloat(b.querySelectorAll('td')[3].textContent.replace(/[^\d.]/g, ''));
                aValue = aAmount;
                bValue = bAmount;
                break;
                
            case 'student-asc':
            case 'student-desc':
                const aStudent = a.querySelector('td:nth-child(2) .text-gray-900');
                const bStudent = b.querySelector('td:nth-child(2) .text-gray-900');
                aValue = aStudent ? aStudent.textContent.toLowerCase() : '';
                bValue = bStudent ? bStudent.textContent.toLowerCase() : '';
                break;
                
            case 'course-asc':
            case 'course-desc':
                aValue = a.querySelectorAll('td')[2].textContent.toLowerCase();
                bValue = b.querySelectorAll('td')[2].textContent.toLowerCase();
                break;
                
            default:
                return 0;
        }
        
        // Determine sort direction
        const isDescending = sortBy.includes('-desc');
        
        // Compare values
        if (typeof aValue === 'string') {
            return isDescending ? bValue.localeCompare(aValue) : aValue.localeCompare(bValue);
        } else {
            return isDescending ? bValue - aValue : aValue - bValue;
        }
    });
    
    // Clear and re-append sorted rows
    tbody.innerHTML = '';
    rows.forEach(row => tbody.appendChild(row));
    
    // Re-apply filters after sorting
    filterPayments();
}

function clearAllFilters() {
    document.getElementById('search-payment').value = '';
    document.getElementById('date-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('sort-by').value = '';
    
    // Show all rows
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => row.style.display = '');
    
    // Update counter
    document.getElementById('visible-count').textContent = rows.length;
    document.getElementById('total-count').textContent = rows.length;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set initial count
    const totalRows = document.querySelectorAll('tbody tr').length;
    document.getElementById('visible-count').textContent = totalRows;
    document.getElementById('total-count').textContent = totalRows;
});

    </script>
</body>
</html>
