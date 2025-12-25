<?php
/**
 * Navigation Helper Functions
 * Determines which navigation modules to show based on user permissions
 */

require_once 'rbac_helper.php';

/**
 * Get available navigation modules for a user
 */
function getAvailableNavigationModules($pdo, $user_id) {
    $modules = [];
    
    // Check if user can access admin modules
    if (canAccessAdminModules($pdo, $user_id)) {
        $modules['admin'] = [
            'dashboard' => hasPermission($pdo, $user_id, 'nav_dashboard'),
            'course_management' => hasPermission($pdo, $user_id, 'nav_course_management'),
            'user_management' => hasPermission($pdo, $user_id, 'nav_user_management'),
            'reports' => hasPermission($pdo, $user_id, 'nav_reports'),
            'usage_analytics' => hasPermission($pdo, $user_id, 'nav_usage_analytics'),
            'performance_logs' => hasPermission($pdo, $user_id, 'nav_performance_logs'),
            'login_activity' => hasPermission($pdo, $user_id, 'nav_login_activity'),
            'security_warnings' => hasPermission($pdo, $user_id, 'nav_security_warnings'),
            'audit_trails' => hasPermission($pdo, $user_id, 'nav_audit_trails'),
            'user_roles_report' => hasPermission($pdo, $user_id, 'nav_user_roles_report'),
            'error_logs' => hasPermission($pdo, $user_id, 'nav_error_logs'),
            'payments' => hasPermission($pdo, $user_id, 'nav_payments'),
            'content_management' => hasPermission($pdo, $user_id, 'nav_content_management'),
            'system_logs' => hasPermission($pdo, $user_id, 'nav_system_logs'),
            'teacher_courses_by_category' => hasPermission($pdo, $user_id, 'nav_teacher_courses_by_category')
        ];
    }
    
    // Check if user can access teacher modules
    if (canAccessTeacherModules($pdo, $user_id)) {
        $modules['teacher'] = [
            'dashboard' => hasPermission($pdo, $user_id, 'nav_teacher_dashboard'),
            'courses' => hasPermission($pdo, $user_id, 'nav_teacher_courses'),
            'create_module' => hasPermission($pdo, $user_id, 'nav_teacher_create_module'),
            'placement_test' => hasPermission($pdo, $user_id, 'nav_teacher_placement_test'),
            'settings' => hasPermission($pdo, $user_id, 'nav_teacher_settings'),
            'content' => hasPermission($pdo, $user_id, 'nav_teacher_content'),
            'students' => hasPermission($pdo, $user_id, 'nav_teacher_students'),
            'reports' => hasPermission($pdo, $user_id, 'nav_teacher_reports'),
            'audit' => hasPermission($pdo, $user_id, 'nav_teacher_audit'),
            'courses_by_category' => hasPermission($pdo, $user_id, 'nav_teacher_courses_by_category')
        ];
    }
    
    // Check if user can access student modules
    if (hasPermission($pdo, $user_id, 'nav_student_dashboard')) {
        $modules['student'] = [
            'dashboard' => hasPermission($pdo, $user_id, 'nav_student_dashboard'),
            'courses' => hasPermission($pdo, $user_id, 'nav_student_courses'),
            'learning' => hasPermission($pdo, $user_id, 'nav_student_learning')
        ];
    }
    
    return $modules;
}

/**
 * Get user's primary role type for navigation
 */
function getUserPrimaryRoleType($pdo, $user_id) {
    if (isHybridAdmin($pdo, $user_id)) {
        return 'hybrid_admin';
    } elseif (isHybridTeacher($pdo, $user_id)) {
        return 'hybrid_teacher';
    } elseif (hasPermission($pdo, $user_id, 'nav_dashboard')) {
        return 'admin';
    } elseif (hasPermission($pdo, $user_id, 'nav_teacher_dashboard')) {
        return 'teacher';
    } elseif (hasPermission($pdo, $user_id, 'nav_student_dashboard')) {
        return 'student';
    } else {
        return 'unknown';
    }
}

/**
 * Get navigation menu items for a user
 */
function getNavigationMenuItems($pdo, $user_id) {
    $modules = getAvailableNavigationModules($pdo, $user_id);
    $primary_role = getUserPrimaryRoleType($pdo, $user_id);
    
    $menu_items = [];
    
    // Add admin modules if available
    if (isset($modules['admin'])) {
        $menu_items['admin'] = [
            'label' => 'Admin Panel',
            'icon' => 'fas fa-cog',
            'modules' => array_filter($modules['admin']) // Only show modules user has access to
        ];
    }
    
    // Add teacher modules if available
    if (isset($modules['teacher'])) {
        $menu_items['teacher'] = [
            'label' => 'Teacher Panel',
            'icon' => 'fas fa-chalkboard-teacher',
            'modules' => array_filter($modules['teacher']) // Only show modules user has access to
        ];
    }
    
    // Add student modules if available
    if (isset($modules['student'])) {
        $menu_items['student'] = [
            'label' => 'Student Panel',
            'icon' => 'fas fa-graduation-cap',
            'modules' => array_filter($modules['student']) // Only show modules user has access to
        ];
    }
    
    return [
        'menu_items' => $menu_items,
        'primary_role' => $primary_role,
        'is_hybrid_admin' => isHybridAdmin($pdo, $user_id),
        'is_hybrid_teacher' => isHybridTeacher($pdo, $user_id)
    ];
}

/**
 * Check if user should see combined navigation (for Hybrid Admin)
 */
function shouldShowCombinedNavigation($pdo, $user_id) {
    return isHybridAdmin($pdo, $user_id) || isHybridTeacher($pdo, $user_id);
}

/**
 * Get module labels for display
 */
function getModuleLabels() {
    return [
        'admin' => [
            'dashboard' => 'Dashboard',
            'course_management' => 'Course Management',
            'user_management' => 'User Management',
            'reports' => 'Reports',
            'usage_analytics' => 'Usage Analytics',
            'performance_logs' => 'Performance Logs',
            'login_activity' => 'Login Activity',
            'security_warnings' => 'Security Warnings',
            'audit_trails' => 'Audit Trails',
            'user_roles_report' => 'User Roles Report',
            'error_logs' => 'Error Logs',
            'payments' => 'Payments',
            'content_management' => 'Content Management',
            'system_logs' => 'System Logs',
            'teacher_courses_by_category' => 'Teacher Courses by Category'
        ],
        'teacher' => [
            'dashboard' => 'Dashboard',
            'courses' => 'My Courses',
            'create_module' => 'Create Module',
            'placement_test' => 'Placement Test',
            'settings' => 'Settings',
            'content' => 'Content Management',
            'students' => 'My Students',
            'reports' => 'Reports',
            'audit' => 'Audit',
            'courses_by_category' => 'Courses by Category'
        ],
        'student' => [
            'dashboard' => 'Dashboard',
            'courses' => 'My Courses',
            'learning' => 'Learning Materials'
        ]
    ];
}

/**
 * Get module icon for display
 */
function getModuleIcon($module, $panel = 'admin') {
    $icons = [
        'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
        'course_management' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>',
        'user_management' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>',
        'reports' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>',
        'usage_analytics' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v6l1.56.78"/><path d="M13.227 21.925a10 10 0 1 1 8.767-9.588"/><path d="m14 18 4-4 4 4"/><path d="M18 22v-8"/></svg>',
        'performance_logs' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>',
        'login_activity' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>',
        'security_warnings' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>',
        'audit_trails' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>',
        'user_roles_report' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>',
        'error_logs' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        'payments' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'content_management' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14,2 14,8 20,8"/></svg>',
        'system_logs' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14,2 14,8 20,8"/></svg>',
        'teacher_courses_by_category' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>',
        
        // Teacher icons
        'teacher_dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
        'teacher_courses' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>',
        'teacher_create_module' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>',
        'teacher_placement_test' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/><path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/></svg>',
        'teacher_settings' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
        'teacher_content' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14,2 14,8 20,8"/></svg>',
        'teacher_students' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
        'teacher_reports' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>',
        'teacher_audit' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/><circle cx="14" cy="15" r="1"/></svg>',
        'teacher_courses_by_category' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>',
        
        // Student icons
        'student_dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
        'student_courses' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>',
        'student_learning' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
    ];
    
    return $icons[$module] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
}
?>
