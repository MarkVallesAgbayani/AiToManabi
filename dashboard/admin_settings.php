<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'includes/admin_profile_functions.php';
require_once '../includes/admin_notifications.php';

// All admins have access to settings - no permission checks needed

// Get admin profile data
$admin_profile = getAdminProfile($pdo, $_SESSION['user_id']);

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Debug: Log profile data
error_log("Admin settings page - Admin profile data: " . print_r($admin_profile, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Japanese Learning Platform</title>
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
    <link href="css/settings.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        .main-content {
            height: calc(100vh - 64px);
            overflow-y: auto;
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: #e5e7eb #f9fafb;
        }
        
        /* Custom Scrollbar for Webkit browsers */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .main-content::-webkit-scrollbar-track {
            background: #f9fafb;
            border-radius: 4px;
        }
        
        .main-content::-webkit-scrollbar-thumb {
            background: #e5e7eb;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        
        .main-content::-webkit-scrollbar-thumb:hover {
            background: #d1d5db;
        }
        
        /* Ensure proper scrolling container */
        .settings-container {
            min-height: 100%;
            padding-bottom: 2rem;
        }
        
        /* Improve section spacing for better scroll experience */
        .settings-section-wrapper {
            margin-bottom: 1.5rem;
        }
        
        /* Ensure consistent spacing between sections */
        .settings-section-wrapper:last-child {
            margin-bottom: 0;
        }
        
        /* Better alignment for form fields */
        .settings-field {
            margin-bottom: 1rem;
        }
        
        .settings-field:last-child {
            margin-bottom: 0;
        }
        
        /* Enhanced card animations for better UX */
        .settings-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .settings-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        
        /* Smooth content expansion */
        .settings-content {
            transition: all 0.3s ease-in-out;
        }
        
        /* Focus and accessibility improvements */
        .settings-card button:focus {
            outline: 2px solid #0284c7;
            outline-offset: 2px;
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .main-content {
                height: calc(100vh - 56px);
            }
            
            .settings-container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        
        [x-cloak] { 
            display: none !important; 
        }
        
        
        .content-area {
            display: none;
        }
        .content-area.active {
            display: block;
        }
        .nav-link.active,
        .nav-link.bg-primary-50 {
            background-color: #f0f9ff !important;
            color: #0284c7 !important;
        }
        
        /* Dropdown transition styles */
        .dropdown-enter {
            transition: all 0.2s ease-in-out;
        }
        .dropdown-enter-start {
            opacity: 0;
            transform: translateY(-10px);
        }
        .dropdown-enter-end {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* OTP Modal positioning fixes */
        #otp-modal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            z-index: 9999 !important;
            width: 100vw !important;
            height: 100vh !important;
        }
        
        #otp-modal .fixed.inset-0 {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
        }
        
        #otp-modal .flex.min-h-screen {
            position: relative !important;
            z-index: 10000 !important;
        }
        
        /* Notification Bell Styles */
        .notification-bell {
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .notification-bell:hover {
            transform: scale(1.1);
        }
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .notification-dropdown.show {
            display: block;
        }
    </style>
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
            <?php echo renderAdminSidebarProfile($admin_profile); ?>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ reportsDropdownOpen: false }">
                <div class="space-y-1">
                    <?php 
                    // Dashboard permissions check
                    $dashboard_permissions = ['dashboard_view_metrics', 'dashboard_view_course_completion', 'dashboard_view_sales_report', 'dashboard_view_user_retention'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $dashboard_permissions)): ?>
                    <a href="admin.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                    <?php endif; ?>

                    <?php                     
                    // Course Management permissions check
                    $course_permissions = ['course_add_category', 'course_view_categories', 'course_edit_category', 'course_delete_category'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $course_permissions)): ?>
                    <a href="course_management_admin.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Categories
                    </a>
                    <?php endif; ?>

                    <?php 
                    // User Management permissions check - require core user management permissions (not just delete/restore)
                    $core_user_permissions = ['user_add_new', 'user_reset_password', 'user_ban_user', 'user_move_to_deleted'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $core_user_permissions)): ?>
                    <a href="users.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-plus-icon lucide-user-round-plus"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>

                    <?php 
                    // Reports permissions check
                    $reports_permissions = ['nav_reports', 'nav_usage_analytics', 'nav_user_roles_report', 'nav_login_activity', 'nav_security_warnings', 'nav_audit_trails', 'nav_performance_logs', 'nav_error_logs', 'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data', 'view_filter_analytics', 'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details', 'login_activity_view', 'login_activity_view_metrics', 'login_activity_view_report', 'login_activity_export_pdf', 'broken_links_view_report', 'broken_links_export_pdf', 'audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details', 'security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations', 'performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times', 'error_logs_view',];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $reports_permissions)): ?>
                    <!-- Reports Dropdown -->
                    <div class="relative">
                        <button @click="reportsDropdownOpen = !reportsDropdownOpen" 
                                class="nav-link w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                                :class="{ 'bg-primary-50 text-primary-700': reportsDropdownOpen }">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>
                                Reports
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" 
                                 :class="{ 'rotate-180': reportsDropdownOpen }" 
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="reportsDropdownOpen" 
                             x-transition:enter="dropdown-enter"
                             x-transition:enter-start="dropdown-enter-start"
                             x-transition:enter-end="dropdown-enter-end"
                             x-cloak
                             class="mt-1 ml-4 space-y-1">
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['nav_usage_analytics', 'analytics_export_pdf', 'analytics_view_metrics', 'analytics_view_active_trends', 'analytics_view_role_breakdown', 'analytics_view_activity_data', 'view_filter_analytics'])): ?>
                            <a href="usage-analytics.php" 
                               class="nav-link w-full flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-arrow-up-icon lucide-clock-arrow-up"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>
                                Usage Analytics
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['nav_user_roles_report', 'user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'])): ?>
                            <a href="user-role-report.php" 
                               class="nav-link w-full flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                                User Roles Breakdown
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['login_activity_view_metrics', 'login_activity_view_report', 'login_activity_export_pdf', 'broken_links_view_report', 'broken_links_export_pdf', 'login_activity_view'])): ?>
                            <a href="login-activity.php" 
                               class="nav-link w-full flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                                Login Activity
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['nav_security_warnings', 'security_warnings_view', 'security_view_metrics', 'security_view_suspicious_patterns', 'security_view_admin_activity', 'security_view_recommendations'])): ?>
                            <a href="security-warnings.php" 
                               class="nav-link w-full flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-alert-icon lucide-shield-alert"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                                Security Warnings
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['audit_trails_view', 'audit_view_metrics', 'audit_search_filter', 'audit_export_pdf', 'audit_view_details'])): ?>
                            <a href="audit-trails.php" 
                               class="nav-link w-full flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open-dot-icon lucide-folder-open-dot"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>
                                Audit Trails
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['nav_performance_logs', 'performance_logs_view', 'performance_logs_export', 'performance_view_metrics', 'performance_view_uptime_chart', 'performance_view_load_times'])): ?>
                            <a href="performance-logs.php" 
                               class="nav-link w-full flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity-icon lucide-activity"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                                System Performance Logs
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['nav_error_logs', 'error_logs_view', 'error_logs_export_pdf', 'error_logs_view_metrics', 'error_logs_view_trends', 'error_logs_view_categories', 'error_logs_search_filter'])): ?>
                            <a href="error-logs.php" 
                               class="nav-link w-full flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                Error Logs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'payment_view_history', 'payment_export_invoice_pdf', 'payment_export_pdf')): ?>
                    <a href="payment_history.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins-icon lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>

                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'content_manage_announcement', 'content_manage_terms', 'content_manage_privacy')): ?>
                    <a href="../dashboard/contentmanagement/content_management.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        Content Management
                    </a>
                    <?php endif; ?>

                    <a href="admin_settings.php" 
                       class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Admin Settings</h1>
                    
                    <div class="flex items-center gap-4">
                        <?php echo $notificationSystem->renderNotificationBell('System Notifications'); ?>
                        
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Areas -->
            <main class="main-content">
                <div class="settings-container">
                    <div class="max-w-5xl mx-auto p-6" x-data="{ 
                        openSections: {
                            profile: true,
                            security: false
                        },
                        toggleSection(section) {
                            this.openSections[section] = !this.openSections[section];
                            // Smooth scroll to section after opening
                            if (this.openSections[section]) {
                                setTimeout(() => {
                                    const element = document.getElementById(section + '-section');
                                    if (element) {
                                        element.scrollIntoView({ 
                                            behavior: 'smooth', 
                                            block: 'nearest',
                                            inline: 'nearest'
                                        });
                                    }
                                }, 300);
                            }
                        }
                    }">
                    <!-- Modern Header with Status -->
                    <div class="bg-gradient-to-r from-primary-50 to-blue-50 rounded-2xl border border-primary-100 mb-8">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </div>
                                        Admin Settings
                                    </h2>
                                    <p class="text-gray-600 mt-1">Manage your account preferences and security settings</p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="flex items-center gap-2 text-green-600">
                                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                            <span class="text-sm font-medium">All changes saved</span>
                                        </div>
                                        <p class="text-xs text-gray-500" id="last-saved">Last updated: 2 minutes ago</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Sections -->
                    <div class="space-y-6">
                        <!-- Profile Section -->
                        <div id="profile-section" class="settings-section-wrapper">
                            <div class="settings-card bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <button @click="toggleSection('profile')" 
                                    class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Profile Information</h3>
                                        <p class="text-sm text-gray-500">Update your profile and how you appear to others</p>
                                    </div>
                                </div>
                                <svg :class="{ 'rotate-180': openSections.profile }" class="w-5 h-5 text-gray-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <div x-show="openSections.profile" x-collapse class="settings-content border-t border-gray-200">
                                <div class="p-6 space-y-4">
                                    <!-- Profile Picture Section -->
                                    <div class="flex items-center space-x-4">
                                        <div class="relative">
                                            <?php 
                                            $picture = getAdminProfilePicture($admin_profile);
                                            if ($picture['has_image']): ?>
                                                <img src="<?php echo htmlspecialchars($picture['image_path']); ?>" 
                                                     class="w-16 h-16 rounded-full object-cover shadow-md profile-picture" 
                                                     alt="Profile Picture">
                                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-lg shadow-md profile-picture-placeholder" style="display: none;">
                                                    <?php echo htmlspecialchars($picture['initial']); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-lg shadow-md profile-picture-placeholder">
                                                    <?php echo htmlspecialchars($picture['initial']); ?>
                                                </div>
                                                <img class="w-16 h-16 rounded-full object-cover shadow-md profile-picture" style="display: none;" alt="Profile Picture">
                                            <?php endif; ?>
                                            <button type="button" class="absolute -bottom-1 -right-1 w-6 h-6 bg-white rounded-full border-2 border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-50 transition-colors shadow-sm" id="camera-icon-btn">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-end space-x-3">
                                                <div class="flex-1">
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                                                    <input type="text" id="display-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm" placeholder="How your name appears to others" value="<?php echo htmlspecialchars($admin_profile['display_name'] ?? ''); ?>">
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-bold text-primary-600 px-3 py-2 bg-primary-50 rounded-lg border border-primary-200">
                                                        <?php echo htmlspecialchars(getAdminRoleDisplay($admin_profile)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <button type="button" id="change-photo-btn" class="bg-primary-600 text-white px-3 py-1.5 rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm">
                                                    Change Photo
                                                </button>
                                                <p class="text-xs text-gray-500 mt-1">JPG, PNG or GIF. Max size 2MB.</p>
                                            </div>
                                        </div>
                                        <input type="file" id="photo-input" accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Section -->
                        <div id="security-section" class="settings-section-wrapper">
                            <div class="settings-card bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <button @click="toggleSection('security')" 
                                    class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Security & Privacy</h3>
                                        <p class="text-sm text-gray-500">Manage your account security and privacy settings</p>
                                    </div>
                                </div>
                                <svg :class="{ 'rotate-180': openSections.security }" class="w-5 h-5 text-gray-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <div x-show="openSections.security">
                                <div class="p-6 space-y-6">
                                    <!-- Password Change Form -->
                                    <div class="bg-gradient-to-r from-red-50 to-pink-50 rounded-xl p-6 border border-red-100">
                                        <h4 class="font-semibold text-red-900 mb-4 flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                            </svg>
                                            Change Password
                                        </h4>
                                        <p class="text-sm text-red-700 mb-6">Update your password to keep your account secure. You'll receive an OTP verification after changing your password.</p>
                                        
                                            <form id="change-password-form" class="space-y-4" onsubmit="return false;">
                                            <!-- Current Password -->
                                            <div class="settings-field">
                                                <label class="block text-sm font-medium text-red-900 mb-2">Current Password <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <input type="password" 
                                                           id="current-password" 
                                                           name="current_password"
                                                           class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" 
                                                           placeholder="Enter your current password"
                                                           required>
                                                    <button type="button" 
                                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                            data-input="current-password">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="current-password-icon">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="text-xs text-red-600 mt-1" id="current-password-error"></div>
                                            </div>

                                            <!-- New Password -->
                                            <div class="settings-field relative">
                                                <label class="block text-sm font-medium text-red-900 mb-2">New Password <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <input type="password" 
                                                           id="new-password" 
                                                           name="new_password"
                                                           class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" 
                                                           placeholder="Create a strong password"
                                                           minlength="12" 
                                                           maxlength="64"
                                                           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{12,64}"
                                                           required>
                                                    <button type="button" 
                                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                            data-input="new-password">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="new-password-icon">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                
                                                <!-- Password Tooltip -->
                                                <div id="password-tooltip" class="hidden absolute z-10 bg-white border border-gray-300 rounded-lg shadow-lg p-4 mt-2 w-80">
                                                    <p class="font-medium mb-2 text-sm text-gray-900">Password Requirements:</p>
                                                    <ul class="list-disc pl-4 space-y-1">
                                                        <li id="length-check-tooltip" class="requirement unmet text-xs">Minimum 12 characters (14+ recommended)</li>
                                                        <li id="uppercase-check-tooltip" class="requirement unmet text-xs">Include uppercase letters</li>
                                                        <li id="lowercase-check-tooltip" class="requirement unmet text-xs">Include lowercase letters</li>
                                                        <li id="number-check-tooltip" class="requirement unmet text-xs">Include numbers</li>
                                                        <li id="special-check-tooltip" class="requirement unmet text-xs">Include special characters (e.g., ! @ # ?)</li>
                                                    </ul>
                                                </div>
                                                
                                                <!-- Password Strength Meter -->
                                                <div class="password-strength-meter mt-2">
                                                    <div id="strength-bar" class="strength-weak h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                                </div>
                                                <span id="strength-text" class="text-xs text-gray-500 block mt-1"></span>
                                                <div class="text-xs text-red-600 mt-1" id="new-password-error"></div>
                                            </div>

                                            <!-- Confirm New Password -->
                                            <div class="settings-field">
                                                <label class="block text-sm font-medium text-red-900 mb-2">Confirm New Password <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <input type="password" 
                                                           id="confirm-password" 
                                                           name="confirm_new_password"
                                                           class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" 
                                                           placeholder="Confirm your new password"
                                                           required>
                                                    <button type="button" 
                                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                            data-input="confirm-password">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="confirm-password-icon">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <span id="password-match" class="text-xs block mt-1"></span>
                                                <div class="text-xs text-red-600 mt-1" id="confirm-password-error"></div>
                                            </div>

                                            <!-- Submit Button -->
                                            <div class="flex justify-end pt-4">
                                                <button type="submit" 
                                                        id="change-password-btn"
                                                        class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                                    </svg>
                                                    <span id="change-password-text">Change Password</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mt-8">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-sm text-gray-500">
                                <span id="last-saved-bottom">All changes are automatically saved</span>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" id="reset-settings" class="px-6 py-3 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors font-medium flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Reset to Defaults
                                </button>
                                <button type="button" id="save-settings" class="px-6 py-3 text-white bg-primary-600 hover:bg-primary-700 rounded-xl transition-colors font-medium flex items-center gap-2 shadow-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Save All Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="js/admin-settings.js"></script>
    <script src="js/password-change-admin.js"></script>

    <script>
        // Admin Settings functionality will be handled by admin-settings.js
        
        // Notification Bell Functions
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
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
    </script>

    <!-- OTP Verification Modal -->
    <div id="otp-modal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="otp-modal-title" role="dialog" aria-modal="true" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <!-- Modal container -->
        <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md bg-white">
                <!-- Modal header -->
                <div class="bg-gradient-to-r from-red-50 to-pink-50 px-6 py-4 border-b border-red-200">
                    <div class="flex items-center justify-between">
                        <h2 id="otp-modal-title" class="text-lg font-semibold text-red-900 flex items-center gap-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            Verify Password Change
                        </h2>
                        <button type="button" 
                                onclick="hideOTPModal()" 
                                class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal content -->
                <div class="bg-white px-6 py-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Check Your Email</h3>
                        <p class="text-sm text-gray-600">
                            We've sent a verification code to your email address. Please enter the code below to complete your password change.
                        </p>
                    </div>

                    <form id="otp-verification-form" class="space-y-4">
                        <div>
                            <label for="otp-code" class="block text-sm font-medium text-gray-700 mb-2">
                                Verification Code
                            </label>
                            <input type="text" 
                                   id="otp-code" 
                                   name="otp_code"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-center text-lg tracking-widest" 
                                   placeholder="Enter 6-digit code"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   required>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <button type="button" 
                                    id="resend-otp-btn"
                                    class="text-red-600 hover:text-red-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                Resend Code
                            </button>
                            <span id="otp-timer" class="text-gray-500"></span>
                        </div>

                        <div id="otp-error" class="text-sm text-red-600 text-center"></div>

                        <div class="flex space-x-3">
                            <button type="button" 
                                    onclick="hideOTPModal()"
                                    class="flex-1 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    id="verify-otp-btn"
                                    class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                Verify & Complete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
