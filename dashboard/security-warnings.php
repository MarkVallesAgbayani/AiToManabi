<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once '../includes/admin_notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has permission to view security warnings
if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'])) {
    header('Location: ../index.php');
    exit();
}

// Security Analysis Functions
function getFailedLoginPatterns($pdo) {
    try {
        // Get failed login attempts today grouped by IP (using same logic as login-activity.php)
        $stmt = $pdo->prepare("
            SELECT 
                ip_address,
                COUNT(*) as attempt_count,
                MAX(login_time) as last_attempt,
                'Failed Login' as reason
            FROM login_logs 
            WHERE DATE(login_time) = CURDATE()
            AND status = 'failed'
            GROUP BY ip_address
            HAVING attempt_count >= 3
            ORDER BY attempt_count DESC, last_attempt DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getSuspiciousAdminActivity($pdo) {
    try {
        // Get admin activities outside business hours or unusual patterns (using admin_action_logs table)
        $stmt = $pdo->prepare("
            SELECT 
                aal.admin_id,
                u.username,
                aal.action,
                'N/A' as ip_address,
                aal.created_at,
                COUNT(*) as frequency
            FROM admin_action_logs aal
            JOIN users u ON aal.admin_id = u.id
            WHERE aal.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND (
                HOUR(aal.created_at) NOT BETWEEN 8 AND 18  -- Outside business hours
                OR aal.action IN ('user_delete', 'mass_export', 'system_config_change')
            )
            GROUP BY aal.admin_id, aal.action, DATE(aal.created_at)
            HAVING frequency >= 2
            ORDER BY aal.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getSystemSecurityMetrics($pdo) {
    try {
        // Get various security metrics
        $metrics = [];
        
        // Failed logins today (using same logic as login-activity.php)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE DATE(login_time) = CURDATE() AND status = 'failed'");
        $stmt->execute();
        $metrics['failed_logins_hour'] = $stmt->fetchColumn();
        
        // Unique IPs with failed attempts today
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM login_logs WHERE login_time >= CURDATE() AND status = 'failed'");
        $stmt->execute();
        $metrics['suspicious_ips_today'] = $stmt->fetchColumn();
        
        // Admin actions today (using admin_action_logs table)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_action_logs WHERE created_at >= CURDATE()");
        $stmt->execute();
        $metrics['admin_actions_today'] = $stmt->fetchColumn();
        
        // New user registrations today (potential spam)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()");
        $stmt->execute();
        $metrics['new_users_today'] = $stmt->fetchColumn();
        
        return $metrics;
    } catch (PDOException $e) {
        return [
            'failed_logins_hour' => 0,
            'suspicious_ips_today' => 0,
            'admin_actions_today' => 0,
            'new_users_today' => 0
        ];
    }
}

function getSecurityThreatLevel($metrics, $patterns, $adminActivity) {
    $score = 0;
    
    // Calculate threat score based on various factors
    $score += min($metrics['failed_logins_hour'] * 2, 20); // Max 20 points
    $score += min($metrics['suspicious_ips_today'] * 3, 30); // Max 30 points
    $score += count($patterns) * 5; // 5 points per suspicious pattern
    $score += count($adminActivity) * 3; // 3 points per suspicious admin activity
    
    // Determine threat level
    if ($score >= 50) return ['level' => 'HIGH', 'color' => 'red', 'score' => $score];
    if ($score >= 25) return ['level' => 'MEDIUM', 'color' => 'orange', 'score' => $score];
    if ($score >= 10) return ['level' => 'LOW', 'color' => 'yellow', 'score' => $score];
    return ['level' => 'NORMAL', 'color' => 'green', 'score' => $score];
}

// Ensure login_logs table has required columns
function ensureLoginLogsTableStructure($pdo) {
    try {
        // Check if status column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN status ENUM('success', 'failed') NOT NULL DEFAULT 'success'");
        }
        
        // Check if location column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'location'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN location VARCHAR(255) NULL");
        }
        
        // Check if device_type column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'device_type'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN device_type VARCHAR(50) NULL");
        }
        
        // Check if browser_name column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'browser_name'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN browser_name VARCHAR(100) NULL");
        }
        
        // Check if operating_system column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'operating_system'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN operating_system VARCHAR(100) NULL");
        }
        
        // Check if session_id column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'session_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE login_logs ADD COLUMN session_id VARCHAR(255) NULL");
        }
        
    } catch (PDOException $e) {
        error_log("Login logs table structure error: " . $e->getMessage());
    }
}

// Ensure table structure is correct
ensureLoginLogsTableStructure($pdo);

// Get security data
$failedLoginPatterns = getFailedLoginPatterns($pdo);
$suspiciousAdminActivity = getSuspiciousAdminActivity($pdo);
$securityMetrics = getSystemSecurityMetrics($pdo);
$threatLevel = getSecurityThreatLevel($securityMetrics, $failedLoginPatterns, $suspiciousAdminActivity);

// Get admin info for header
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Get user permissions for dynamic navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Warnings - Japanese Learning Platform</title>
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
    <link href="css/security-warnings.css" rel="stylesheet">
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
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['dashboard_view_course_completion', 'dashboard_view_metrics', 'dashboard_view_sales_report', 'dashboard_view_user_retention'])): ?>
                    <a href="admin.php?view=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'])): ?>
                    <a href="course_management_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_add_new', 'user_reset_password', 'user_change_password', 'user_ban_user', 'user_move_to_deleted', 'user_change_role'])): ?>
                    <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus-icon lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>
                    <!-- Reports Dropdown -->
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data', 'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details', 'login_activity_view', 'audit_trails_view', 'nav_security_warnings', 'security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations', 'performance_logs_view', 'error_logs_view'])): ?>
                    <div x-data="{ open: true }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full bg-primary-50 text-primary-700 font-medium focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>                            <span class="flex-1 text-left">Reports</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 ml-auto transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="mt-1 ml-4 space-y-1" x-cloak>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data'])): ?>
                            <a href="usage-analytics.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
    
                            Usage Analytics
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'])): ?>
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
   
                            User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_view_metrics', 'login_activity_view_report', 'login_activity_view', 'login_activity_export_pdf'])): ?>
                            <a href="login-activity.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>      
    
                            Login Activity
                            </a>
                            <?php endif; ?>
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], [ 'security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'])): ?>
                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
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
                        <h1 class="text-2xl font-semibold text-gray-900">Security Warnings</h1>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <?php echo $notificationSystem->renderNotificationBell('System Notifications'); ?>
                        
                        <!-- Threat Level Indicator -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-600">Threat Level:</span>
                            <span class="px-3 py-1 rounded-full text-sm font-bold text-<?php echo $threatLevel['color']; ?>-800 bg-<?php echo $threatLevel['color']; ?>-100 border border-<?php echo $threatLevel['color']; ?>-200">
                                <?php echo $threatLevel['level']; ?> (<?php echo $threatLevel['score']; ?>)
                            </span>
                        </div>
                        
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Check if user has any security permissions -->
                <?php if (!hasAnyPermission($pdo, $_SESSION['user_id'], ['security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'])): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                    <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-yellow-600">
                            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
                            <path d="M12 8v4"/>
                            <path d="M12 16h.01"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Access Restricted</h2>
                    <p class="text-gray-600 max-w-md mx-auto">
                        You don't have permission to view security warnings. Please contact your administrator if you need access to security monitoring features.
                    </p>
                </div>
                <?php else: ?>
                
                <!-- Security Overview Cards -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'security_view_metrics')): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Failed Logins -->
                    <div class="security-card bg-white rounded-lg shadow p-6 <?php echo $securityMetrics['failed_logins_hour'] > 5 ? 'pulse-red' : ''; ?>">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Failed Logins</h3>
                                <p class="text-3xl font-bold text-red-600"><?php echo $securityMetrics['failed_logins_hour']; ?></p>
                                <p class="text-sm text-gray-500">Today</p>
                            </div>
                        </div>
                    </div>

                    <!-- Suspicious IPs -->
                    <div class="security-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-globe-lock-icon lucide-globe-lock"><path d="M15.686 15A14.5 14.5 0 0 1 12 22a14.5 14.5 0 0 1 0-20 10 10 0 1 0 9.542 13"/><path d="M2 12h8.5"/><path d="M20 6V4a2 2 0 1 0-4 0v2"/><rect width="8" height="5" x="14" y="6" rx="1"/></svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Suspicious IPs</h3>
                                <p class="text-3xl font-bold text-orange-600"><?php echo $securityMetrics['suspicious_ips_today']; ?></p>
                                <p class="text-sm text-gray-500">Today</p>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Actions -->
                    <div class="security-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-check-icon lucide-shield-check"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Admin Actions</h3>
                                <p class="text-3xl font-bold text-blue-600"><?php echo $securityMetrics['admin_actions_today']; ?></p>
                                <p class="text-sm text-gray-500">Today</p>
                            </div>
                        </div>
                    </div>

                    <!-- New Users -->
                    <div class="security-card bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus-icon lucide-user-plus"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">New Users</h3>
                                <p class="text-3xl font-bold text-green-600"><?php echo $securityMetrics['new_users_today']; ?></p>
                                <p class="text-sm text-gray-500">Today</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Security Alerts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Failed Login Patterns -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'security_view_suspicious_patterns')): ?>
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell-ring-icon lucide-bell-ring"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M22 8c0-2.3-.8-4.3-2-6"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/><path d="M4 2C2.8 3.7 2 5.7 2 8"/></svg>
                                Suspicious Login Patterns
                            </h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($failedLoginPatterns)): ?>
                                <div class="text-center py-8 text-gray-500">
  <!-- Lucide Shield-Check Icon -->
  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
       class="lucide lucide-shield-check mx-auto text-green-500">
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    <path d="M9 12l2 2 4-4"/>
  </svg>
  <p class="mt-2">No suspicious login patterns detected</p>
</div>

                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($failedLoginPatterns as $pattern): ?>
                                        <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-semibold text-red-800">IP: <?php echo htmlspecialchars($pattern['ip_address']); ?></p>
                                                    <p class="text-sm text-red-600"><?php echo $pattern['attempt_count']; ?> failed attempts</p>
                                                    <p class="text-xs text-red-500">Last: <?php echo date('M j, H:i', strtotime($pattern['last_attempt'])); ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="px-2 py-1 bg-red-200 text-red-800 rounded text-xs font-medium">
                                                        <?php echo ucfirst(str_replace('_', ' ', $pattern['reason'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Suspicious Admin Activity -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'security_view_admin_activity')): ?>
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                Unusual Admin Activity
                            </h2>
                            <?php if (!empty($suspiciousAdminActivity)): ?>
                            <button onclick="openAdminActivityModal()" class="inline-flex items-center px-3 py-2 border border-orange-300 text-sm font-medium rounded-md text-orange-700 bg-orange-50 hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                </svg>
                                View All (<?php echo count($suspiciousAdminActivity); ?>)
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="p-6">
                            <?php if (empty($suspiciousAdminActivity)): ?>
                                <div class="text-center py-8 text-gray-500">
  <!-- Lucide User-Check Icon -->
  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
       class="lucide lucide-user-check mx-auto text-green-500">
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
    <circle cx="9" cy="7" r="4"/>
    <path d="m16 11 2 2 4-4"/>
  </svg>
  <p class="mt-2">No unusual admin activity detected</p>
</div>

                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($suspiciousAdminActivity as $activity): ?>
                                        <div class="border border-orange-200 rounded-lg p-4 bg-orange-50">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-semibold text-orange-800"><?php echo htmlspecialchars($activity['username']); ?></p>
                                                    <p class="text-sm text-orange-600"><?php echo htmlspecialchars($activity['action']); ?></p>
                                                    <p class="text-xs text-orange-500"><?php echo date('M j, H:i', strtotime($activity['created_at'])); ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="px-2 py-1 bg-orange-200 text-orange-800 rounded text-xs font-medium">
                                                        <?php echo $activity['frequency']; ?>x
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Security Recommendations -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'security_view_recommendations')): ?>
                <div class="mt-8 bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-globe-lock-icon lucide-globe-lock"><path d="M15.686 15A14.5 14.5 0 0 1 12 22a14.5 14.5 0 0 1 0-20 10 10 0 1 0 9.542 13"/><path d="M2 12h8.5"/><path d="M20 6V4a2 2 0 1 0-4 0v2"/><rect width="8" height="5" x="14" y="6" rx="1"/></svg>
                            Security Recommendations
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h3 class="font-semibold text-gray-900">Immediate Actions</h3>
                                <?php if ($threatLevel['level'] === 'HIGH' || $threatLevel['level'] === 'MEDIUM'): ?>
                                    <div class="flex items-start gap-3 p-3 bg-red-50 rounded-lg border border-red-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-banknote-x-icon lucide-banknote-x"><path d="M13 18H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5"/><path d="m17 17 5 5"/><path d="M18 12h.01"/><path d="m22 17-5 5"/><path d="M6 12h.01"/><circle cx="12" cy="12" r="2"/></svg>
                                        <div>
                                            <p class="font-medium text-red-800">Review Failed Login Attempts</p>
                                            <p class="text-sm text-red-600">Consider implementing IP blocking for repeated offenders</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (count($suspiciousAdminActivity) > 0): ?>
                                    <div class="flex items-start gap-3 p-3 bg-orange-50 rounded-lg border border-orange-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-ellipsis-icon lucide-shield-ellipsis"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                                        <div>
                                            <p class="font-medium text-orange-800">Verify Admin Activity</p>
                                            <p class="text-sm text-orange-600">Contact admins to confirm unusual activities</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-refresh-ccw-dot-icon lucide-refresh-ccw-dot"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/><circle cx="12" cy="12" r="1"/></svg>
                                    <div>
                                        <p class="font-medium text-blue-800">Regular Security Review</p>
                                        <p class="text-sm text-blue-600">Monitor this dashboard daily for security threats</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <h3 class="font-semibold text-gray-900">Long-term Security</h3>
                                <div class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-check-icon lucide-shield-check"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                                    <div>
                                        <p class="font-medium text-green-800">Enable Two-Factor Authentication</p>
                                        <p class="text-sm text-green-600">Require 2FA for all admin accounts</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start gap-3 p-3 bg-purple-50 rounded-lg border border-purple-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-bar-big-icon lucide-chart-bar-big"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><rect x="7" y="13" width="9" height="4" rx="1"/><rect x="7" y="5" width="12" height="4" rx="1"/></svg>
                                    <div>
                                        <p class="font-medium text-purple-800">Implement Rate Limiting</p>
                                        <p class="text-sm text-purple-600">Limit login attempts per IP address</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-list-icon lucide-clipboard-list"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                                    <div>
                                        <p class="font-medium text-gray-800">Regular Audit Reviews</p>
                                        <p class="text-sm text-gray-600">Weekly review of audit logs and user activities</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; // End of main permission check ?>
            </main>
        </div>
    </div>

    <script>
        // Mobile Sidebar Toggle
        function toggleMobileSidebar() {
            const sidebar = document.querySelector('.bg-white.w-64');
            sidebar.classList.toggle('mobile-open');
        }
        
        // Notification System
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        // Show notification for high threat levels
        <?php if ($threatLevel['level'] === 'HIGH'): ?>
            setTimeout(() => {
                alert('🚨 HIGH SECURITY THREAT DETECTED! Please review the security warnings immediately.');
            }, 1000);
        <?php endif; ?>
        
        // Add fade-in animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.security-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('fade-in');
                }, index * 100);
            });
        });
        
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
        
        // Admin Activity Modal Functions
        function openAdminActivityModal() {
            document.getElementById('adminActivityModal').classList.remove('hidden');
        }
        
        function closeAdminActivityModal() {
            document.getElementById('adminActivityModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('adminActivityModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdminActivityModal();
            }
        });
    </script>
    
    <!-- Admin Activity Modal -->
    <div id="adminActivityModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
        <div class="p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-600">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>
                            <path d="M12 9v4"/>
                            <path d="M12 17h.01"/>
                        </svg>
                        All Unusual Admin Activity
                    </h3>
                    <button onclick="closeAdminActivityModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Modal Content -->
                <div class="mt-4 max-h-96 overflow-y-auto">                    <?php if (empty($suspiciousAdminActivity)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-green-500">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="m16 11 2 2 4-4"/>
                            </svg>
                            <p class="mt-2">No unusual admin activity detected</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($suspiciousAdminActivity as $activity): ?>
                                <div class="border border-orange-200 rounded-lg p-4 bg-orange-50 hover:bg-orange-100 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="font-semibold text-orange-800"><?php echo htmlspecialchars($activity['username']); ?></span>
                                                <span class="px-2 py-1 bg-orange-200 text-orange-800 rounded text-xs font-medium">
                                                    <?php echo $activity['frequency']; ?>x
                                                </span>
                                            </div>
                                            <p class="text-sm text-orange-600 mb-1"><?php echo htmlspecialchars($activity['action']); ?></p>
                                            <p class="text-xs text-orange-500"><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Modal Footer -->
                <div class="flex justify-end pt-4 border-t mt-4">
                    <button onclick="closeAdminActivityModal()" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
