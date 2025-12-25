<?php

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include required files
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once '../includes/admin_notifications.php';
require_once 'audit_logger.php';
require_once 'reports.php';
require_once '../components/collapsible_section.php';

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



// Check user roles report access - either by permission or admin role
$has_user_roles_access = false;

// First check if user has user roles report permissions
if (function_exists('hasAnyPermission')) {
    $user_roles_permissions = ['user_roles_view_metrics', 'user_roles_search_filter', 'user_roles_export_pdf', 'user_roles_view_details'];
    $has_user_roles_access = hasAnyPermission($pdo, $_SESSION['user_id'], $user_roles_permissions);
}

// Fallback: Check if user has admin role
if (!$has_user_roles_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_user_roles_access = true;
}

// Final fallback: Check user role directly from database
if (!$has_user_roles_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_user_roles_access = true;
    }
}

if (!$has_user_roles_access) {
    header('Location: ../index.php');
    exit();
}

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Check export permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'user_roles_export_pdf')) {
        header('Location: ../index.php');
        exit();
    }
    
    // Validate that at least one export column is selected
    $export_columns = $_GET['export_columns'] ?? [];
    if (empty($export_columns)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Please select at least one export column to generate the PDF report.'
        ]);
        exit;
    }
    
    $filters = [
        'search' => $_GET['search'] ?? '',
        'role_filter' => $_GET['role_filter'] ?? '',
        'status_filter' => $_GET['status_filter'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'export_columns' => $_GET['export_columns'] ?? ['user_id', 'username', 'full_name', 'email', 'role', 'status', 'created_at', 'enrolled_courses'],
        'export_detail' => $_GET['export_detail'] ?? 'summary',
        'report_purpose' => $_GET['report_purpose'] ?? 'general',
        'confidentiality' => $_GET['confidentiality'] ?? 'internal',
        'summary_metrics' => $_GET['summary_metrics'] ?? ['totals', 'averages'],
        'admin_info_level' => $_GET['admin_info_level'] ?? 'name_only'
    ];
    
    // Use centralized report generator
    $reportGenerator = new ReportGenerator($pdo);
    $reportGenerator->generateUserRoleReport($filters, $_GET['export']);
    exit;
}

// Handle AJAX requests for user details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'user_details' && isset($_GET['user_id'])) {
    // Check view details permission
    if (!hasPermission($pdo, $_SESSION['user_id'], 'user_roles_view_details')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    header('Content-Type: application/json');
    $userDetails = getUserDetails($pdo, $_GET['user_id']);
    echo json_encode($userDetails);
    exit;
}

// Get filters from URL parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'role_filter' => $_GET['role_filter'] ?? '',
    'status_filter' => $_GET['status_filter'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_order' => $_GET['sort_order'] ?? 'DESC',
    'export_columns' => $_GET['export_columns'] ?? ['user_id', 'username', 'full_name', 'email', 'role', 'status', 'created_at', 'enrolled_courses'],
    'export_detail' => $_GET['export_detail'] ?? 'summary',
    'report_purpose' => $_GET['report_purpose'] ?? 'general',
    'confidentiality' => $_GET['confidentiality'] ?? 'internal',
    'summary_metrics' => $_GET['summary_metrics'] ?? ['totals', 'averages'],
    'admin_info_level' => $_GET['admin_info_level'] ?? 'name_only'
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// User Report Functions
function getUsersReport($pdo, $filters, $offset, $limit) {
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause .= " AND (u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Role filter
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND u.role = ?";
            $params[] = $filters['role_filter'];
        }
        
        // Status filter - Make this more flexible
        if (!empty($filters['status_filter'])) {
            if ($filters['status_filter'] === 'active') {
                $whereClause .= " AND (u.status = 'active' OR u.status IS NULL)";
            } elseif ($filters['status_filter'] === 'inactive') {
                $whereClause .= " AND u.status IN ('inactive', 'suspended', 'banned', 'deleted')";
            }
        }
        
        // Date range filter - Only apply if both dates are provided and valid
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $whereClause .= " AND DATE(u.created_at) >= ? AND DATE(u.created_at) <= ?";
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
        } elseif (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(u.created_at) >= ?";
            $params[] = $filters['date_from'];
        } elseif (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(u.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // SQL with profile picture data and enrolled courses count
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.role,
                    COALESCE(u.status, 'active') as status,
                    u.created_at,
                    u.updated_at,
                    CASE 
                        WHEN u.role = 'student' THEN COALESCE(enrolled_count.count, 0)
                        ELSE 0 
                    END as enrolled_courses,
                    COALESCE(tp.profile_picture, sp.profile_picture, '') as profile_picture
                FROM users u
                LEFT JOIN teacher_preferences tp ON u.id = tp.teacher_id AND u.role = 'teacher'
                LEFT JOIN student_preferences sp ON u.id = sp.student_id AND u.role = 'student'
                LEFT JOIN (
                    SELECT student_id, COUNT(*) as count 
                    FROM enrollments 
                    GROUP BY student_id
                ) enrolled_count ON u.id = enrolled_count.student_id AND u.role = 'student'
                $whereClause
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If we still get no results, try the most basic query
        if (empty($result) && count($params) > 2) {
            $sql = "SELECT 
                        u.id,
                        u.username,
                        u.email,
                        u.first_name,
                        u.last_name,
                        u.role,
                        COALESCE(u.status, 'active') as status,
                        u.created_at,
                        u.updated_at,
                        0 as enrolled_courses,
                        COALESCE(tp.profile_picture, sp.profile_picture, '') as profile_picture
                    FROM users u
                    LEFT JOIN teacher_preferences tp ON u.id = tp.teacher_id AND u.role = 'teacher'
                    LEFT JOIN student_preferences sp ON u.id = sp.student_id AND u.role = 'student'
                    ORDER BY u.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Users report error: " . $e->getMessage());
        // Fallback query - guaranteed to work if users table exists
        try {
            $sql = "SELECT id, username, email, first_name, last_name, role, 'active' as status, created_at, created_at as updated_at, 0 as enrolled_courses FROM users LIMIT ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            error_log("Fallback query also failed: " . $e2->getMessage());
            return [];
        }
    }
}

function getTotalUsers($pdo, $filters) {
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereClause .= " AND (username LIKE ? OR email LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['role_filter'])) {
            $whereClause .= " AND role = ?";
            $params[] = $filters['role_filter'];
        }
        
        if (!empty($filters['status_filter'])) {
            if ($filters['status_filter'] === 'active') {
                $whereClause .= " AND (status = 'active' OR status IS NULL)";
            } elseif ($filters['status_filter'] === 'inactive') {
                $whereClause .= " AND status IN ('inactive', 'suspended', 'banned', 'deleted')";
            }
        }
        
        // Date range filter - Only apply if valid dates
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $whereClause .= " AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
        } elseif (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        } elseif (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql = "SELECT COUNT(*) FROM users $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        // If no results with filters, try basic count
        if ($count == 0 && !empty($params)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $count = $stmt->fetchColumn();
        }
        
        return $count;
    } catch (PDOException $e) {
        error_log("Total users count error: " . $e->getMessage());
        // Fallback
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (PDOException $e2) {
            return 0;
        }
    }
}

function getUserDetails($pdo, $userId) {
    try {
        // Get basic user info
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.role,
                    COALESCE(u.status, 'active') as status,
                    u.created_at,
                    u.updated_at
                FROM users u
                WHERE u.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['error' => 'User not found'];
        }
        
        // Get role-specific data
        $roleData = [];
        
        switch ($user['role']) {
            case 'student':
                // Get enrolled courses and progress
                $sql = "SELECT 
                            c.id,
                            c.title,
                            c.description,
                            e.enrolled_at,
                            e.completed_at,
                            CASE 
                                WHEN e.completed_at IS NOT NULL THEN 100 
                                ELSE 0 
                            END as completion_percentage,
                            CASE 
                                WHEN e.completed_at IS NOT NULL THEN 'completed'
                                ELSE 'in_progress'
                            END as completion_status
                        FROM enrollments e
                        JOIN courses c ON e.course_id = c.id
                        WHERE e.student_id = ?
                        ORDER BY e.enrolled_at DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId]);
                $roleData['enrolled_courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'teacher':
                // Teachers don't show course information in this report
                break;
                
            case 'admin':
                // Admin basic information only - no activity statistics
                break;
        }
        
        return [
            'user' => $user,
            'role_data' => $roleData
        ];
        
    } catch (PDOException $e) {
        error_log("User details error: " . $e->getMessage());
        return ['error' => 'Failed to fetch user details'];
    }
}

function getRoleSummary($pdo, $filters = []) {
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Apply same filters as main query for consistency
        if (!empty($filters['search'])) {
            $whereClause .= " AND (username LIKE ? OR email LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status_filter'])) {
            if ($filters['status_filter'] === 'active') {
                $whereClause .= " AND (status = 'active' OR status IS NULL)";
            } elseif ($filters['status_filter'] === 'inactive') {
                $whereClause .= " AND status IN ('inactive', 'suspended', 'banned', 'deleted')";
            }
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql = "SELECT 
                    role,
                    COUNT(*) as total,
                    COUNT(CASE WHEN (status = 'active' OR status IS NULL) THEN 1 END) as active,
                    COUNT(CASE WHEN status IN ('inactive', 'suspended', 'banned', 'deleted') THEN 1 END) as inactive
                FROM users
                $whereClause
                GROUP BY role
                ORDER BY total DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Role summary error: " . $e->getMessage());
        return [];
    }
}

// Function to get user profile picture or generate initial
function getUserProfilePicture($user) {
    $picture = [
        'has_image' => false,
        'image_path' => '',
        'initial' => strtoupper(substr($user['first_name'] ?? $user['username'], 0, 1))
    ];
    
    if (!empty($user['profile_picture'])) {
        // Try multiple possible paths - uploads folder is inside dashboard directory
        $possiblePaths = [
            // From dashboard directory (uploads is inside dashboard)
            'uploads/profile_pictures/' . $user['profile_picture'],
            // Relative path from dashboard
            '../uploads/profile_pictures/' . $user['profile_picture'],
            // Absolute path
            $_SERVER['DOCUMENT_ROOT'] . '/AIToManabi_Updated/dashboard/uploads/profile_pictures/' . $user['profile_picture']
        ];
        
        foreach ($possiblePaths as $webPath) {
            // Convert to file system path for checking
            if (strpos($webPath, 'uploads/') === 0) {
                // uploads/ is inside dashboard directory
                $fileSystemPath = __DIR__ . '/uploads/profile_pictures/' . $user['profile_picture'];
            } elseif (strpos($webPath, '../') === 0) {
                // Relative path from dashboard
                $fileSystemPath = __DIR__ . '/../uploads/profile_pictures/' . $user['profile_picture'];
            } else {
                // Absolute path
                $fileSystemPath = $webPath;
            }
            
            if (file_exists($fileSystemPath)) {
                $picture['has_image'] = true;
                $picture['image_path'] = $webPath;
                break;
            }
        }
    }
    
    return $picture;
}

// Get data for the page
$usersData = getUsersReport($pdo, $filters, $offset, $limit);
$totalUsers = getTotalUsers($pdo, $filters);
$totalPages = ceil($totalUsers / $limit);
$roleSummary = getRoleSummary($pdo, $filters);

// Log user role report access
$auditLogger = new AuditLogger($pdo);
$auditLogger->logEntry([
    'action_type' => 'READ',
    'action_description' => 'Accessed user role breakdown report',
    'resource_type' => 'Dashboard',
    'resource_id' => 'User Role Report',
    'resource_name' => 'User Role Breakdown Report',
    'outcome' => 'Success',
    'context' => [
        'role_filter' => $filters['role_filter'] ?? 'all',
        'status_filter' => $filters['status_filter'] ?? 'all',
        'search' => $filters['search'] ?? '',
        'total_users' => $totalUsers,
        'page' => $page,
        'limit' => $limit
    ]
]);

// Get admin info for header
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Initialize notification system
$notificationSystem = initializeAdminNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Get current user's permissions for navigation
$current_user_permissions = getUserEffectivePermissions($pdo, $_SESSION['user_id']);

// Get all permissions from database for dynamic navigation
$all_permissions_by_category = getAllPermissionsByCategory($pdo);
$all_permissions_flat = getAllPermissionsFlat($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Roles Report - Japanese Learning Platform</title>
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
    <link href="css/user-role.css" rel="stylesheet">
    
    <!-- Collapsible Section Styles and Scripts -->
    <style>
        <?php echo getCollapsibleCSS(); ?>
        .restricted-user-notice {
    padding: 20px;
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.restricted-user-notice h3 {
    color: #1f2937;
    font-weight: 700;
}

.alert-error {
    background-color: #fee2e2;
    border: 1px solid #ef4444;
    color: #991b1b;
    padding: 16px;
    border-radius: 8px;
    margin: 20px 0;
}

.alert-error strong {
    font-weight: 600;
}

/* Cross-browser fix for card white backgrounds */
.bg-white, 
main .bg-white,
div.bg-white,
[class*="bg-white"] {
    background: #ffffff !important;
    background-color: #ffffff !important;
    -webkit-background-color: #ffffff !important;
    -moz-background-color: #ffffff !important;
    -ms-background-color: #ffffff !important;
    -o-background-color: #ffffff !important;
}

/* Specific fix for role summary cards */
.flex.justify-center.gap-6 > div[class*="bg-white"],
main div[class*="rounded-xl"][class*="shadow"] {
    background: #ffffff !important;
    background-color: #ffffff !important;
    color: #1f2937 !important;
}

/* Override any potential browser defaults or theme conflicts */
body div[class*="bg-white"]:not([style*="background: rgb"]):not([style*="background-color: rgb"]) {
    background: #ffffff !important;
    background-color: #ffffff !important;
}

    </style>
    <script src="js/collapsible_section.js"></script>
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
                    $dashboard_permissions = ['dashboard_view_metrics', 'dashboard_view_course_completion', 'dashboard_view_sales_report', 'dashboard_view_user_retention'];
                    if (hasAnyPermission($pdo, $_SESSION['user_id'], $dashboard_permissions)): ?>
                    <a href="admin.php?view=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
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
                    <div x-data="{ open: true }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors w-full bg-primary-50 text-primary-700 font-medium focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-combined-icon lucide-chart-no-axes-combined"><path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/></svg>                            <span class="flex-1 text-left">Reports</span>
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
                            <a href="user-role-report.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors bg-primary-50 text-primary-700 font-medium">
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
                            if (hasAnyPermission($pdo, $_SESSION['user_id'], $security_permissions)): ?>                            <a href="security-warnings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
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
                        <h1 class="text-2xl font-semibold text-gray-900">User Roles Report</h1>
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
                <!-- Role Summary Cards -->
                <div class="flex justify-center gap-6 mb-8 flex-wrap">
                    <?php foreach ($roleSummary as $role): ?>
                        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-shadow duration-300 w-72" style="background: #ffffff !important; background-color: #ffffff !important;">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center">
                                    <span class="text-primary-600">
    <?php 
    echo match($role['role']) {
        'student' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" 
                          viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                          class="lucide lucide-graduation-cap">
                          <path d="M22 10V6L12 3 2 6v4"/>
                          <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                          <path d="M6 12c3 2 9 2 12 0"/>
                        </svg>',
        'teacher' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" 
                          viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                          class="lucide lucide-book-open-text">
                          <path d="M12 12h6"/>
                          <path d="M12 18h6"/>
                          <path d="M2 4h6v16H2z"/>
                          <path d="M22 4h-6v16h6z"/>
                        </svg>',
        'admin' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" 
                          viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                          class="lucide lucide-shield-check">
                          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                          <path d="m9 12 2 2 4-4"/>
                        </svg>',
        default => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" 
                          viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                          class="lucide lucide-user">
                          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                          <circle cx="12" cy="7" r="4"/>
                        </svg>'
    };
    ?>
</span>

                                    </div>
                                </div>
                                <div class="ml-6 flex-1">
                                    <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">
                                        <?php echo ucfirst($role['role']); ?>s
                                    </div>
                                    <div class="mt-1 flex items-baseline">
                                        <div class="text-3xl font-bold text-gray-900">
                                            <?php echo number_format($role['total']); ?>
                                        </div>
                                        <div class="ml-3 text-sm font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">
                                            <?php echo $role['active']; ?> active
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_roles_search_filter')): ?>
                <!-- Search and Filter Controls -->
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6" style="background: #ffffff !important; background-color: #ffffff !important;">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-medium text-gray-900"> Search & Filter Users</h3>
                            <!-- Filter Action Buttons - Near the title -->
                            <div class="flex space-x-2">
                                <button type="submit" form="filterForm" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    Search Users
                                </button>
                                <a href="user-role-report.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Clear Filters
                                </a>
                            </div>
                        </div>
                        <!-- Export Buttons - Right corner -->
                        <div class="export-buttons">
                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_roles_export_pdf')): ?>
                            <button type="button" onclick="exportData('pdf')" class="inline-flex items-center px-3 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text-icon lucide-file-text"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                            Export PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <form method="GET" id="filterForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- Search -->
                                <div class="lg:col-span-2">
                                    <label for="search" class="block text-sm font-medium text-gray-700">Search Users</label>
                                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                           placeholder="Search by name, email, or username..." 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                
                                <!-- Role Filter -->
                                <div>
                                    <label for="role_filter" class="block text-sm font-medium text-gray-700">Role</label>
                                    <select name="role_filter" id="role_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">All Roles</option>
                                        <option value="student" <?php echo $filters['role_filter'] === 'student' ? 'selected' : ''; ?>>Students</option>
                                        <option value="teacher" <?php echo $filters['role_filter'] === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                                        <option value="admin" <?php echo $filters['role_filter'] === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                    </select>
                                </div>
                                
                                <!-- Status Filter -->
                                <div>
                                    <label for="status_filter" class="block text-sm font-medium text-gray-700">Status</label>
                                    <select name="status_filter" id="status_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $filters['status_filter'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $filters['status_filter'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Additional Date Filters -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-gray-200">
                                <!-- From Date -->
                                <div>
                                    <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                                
                                <!-- To Date -->
                                <div>
                                    <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>
                            </div>

                            <!-- Professional Export Configuration - Collapsible -->
                            <div class="border-t border-gray-200 pt-4">
                                <?php
                                // Prepare the Export Configuration content
                                $exportConfigContent = '
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Report Purpose -->
                                    <div>
                                        <label for="report_purpose" class="block text-sm font-medium text-gray-700">Report Purpose</label>
                                        <select name="report_purpose" id="report_purpose" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <option value="general" ' . (($filters['report_purpose'] ?? 'general') === 'general' ? 'selected' : '') . '>General Report</option>
                                            <option value="user_audit" ' . (($filters['report_purpose'] ?? '') === 'user_audit' ? 'selected' : '') . '>User Audit</option>
                                            <option value="role_analysis" ' . (($filters['report_purpose'] ?? '') === 'role_analysis' ? 'selected' : '') . '>Role Analysis</option>
                                            <option value="compliance_check" ' . (($filters['report_purpose'] ?? '') === 'compliance_check' ? 'selected' : '') . '>Compliance Check</option>
                                            <option value="stakeholder_report" ' . (($filters['report_purpose'] ?? '') === 'stakeholder_report' ? 'selected' : '') . '>Stakeholder Report</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Confidentiality Level -->
                                    <div>
                                        <label for="confidentiality" class="block text-sm font-medium text-gray-700">Confidentiality Level</label>
                                        <select name="confidentiality" id="confidentiality" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <option value="internal" ' . (($filters['confidentiality'] ?? 'internal') === 'internal' ? 'selected' : '') . '>Internal Use Only</option>
                                            <option value="confidential" ' . (($filters['confidentiality'] ?? '') === 'confidential' ? 'selected' : '') . '>Confidential</option>
                                            <option value="restricted" ' . (($filters['confidentiality'] ?? '') === 'restricted' ? 'selected' : '') . '>Restricted Access</option>
                                            <option value="public" ' . (($filters['confidentiality'] ?? '') === 'public' ? 'selected' : '') . '>Public (Anonymized)</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Admin Information Display -->
                                    <div>
                                        <label for="admin_info_level" class="block text-sm font-medium text-gray-700">Admin Information</label>
                                        <select name="admin_info_level" id="admin_info_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <option value="name_only" ' . (($filters['admin_info_level'] ?? 'name_only') === 'name_only' ? 'selected' : '') . '>Name Only</option>
                                            <option value="name_role" ' . (($filters['admin_info_level'] ?? '') === 'name_role' ? 'selected' : '') . '>Name + Role</option>
                                            <option value="full_details" ' . (($filters['admin_info_level'] ?? '') === 'full_details' ? 'selected' : '') . '>Full Details + Contact</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Export Column Selection -->
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Export Columns</label>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="user_id" ' . (in_array('user_id', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">User ID</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="username" ' . (in_array('username', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Username</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="full_name" ' . (in_array('full_name', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Full Name</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="email" ' . (in_array('email', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Email</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="role" ' . (in_array('role', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Role</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="status" ' . (in_array('status', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Status</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="created_at" ' . (in_array('created_at', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Registration Date</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="export_columns[]" value="enrolled_courses" ' . (in_array('enrolled_courses', $filters['export_columns']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Enrolled Modules</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Summary Metrics Selection -->
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Include Summary Metrics</label>
                                    <div class="grid grid-cols-2 md:grid-cols-2 gap-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="summary_metrics[]" value="totals" ' . (in_array('totals', $filters['summary_metrics']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">User Totals</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="summary_metrics[]" value="averages" ' . (in_array('averages', $filters['summary_metrics']) ? 'checked' : '') . ' class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="ml-2 text-sm text-gray-700">Role Distribution</span>
                                        </label>
                                    </div>
                                </div>';

                                // Generate the collapsible Export Configuration section
                                echo generateCollapsibleSection(
                                    'export-configuration',        // Unique ID
                                    'Export Configuration',        // Title
                                    $exportConfigContent,         // Content
                                    true,                         // Default COLLAPSED (starts closed)
                                    '',                          // Header additional classes
                                    'bg-white p-4 border border-gray-200 rounded-lg mt-2', // Content styling
                                    'chevron'                    // Chevron icon
                                );
                                ?>
                            </div>

                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Users Table -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_roles_view_metrics')): ?>
                <div class="bg-white shadow-sm rounded-lg border border-gray-200" style="background: #ffffff !important; background-color: #ffffff !important;">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
    <!-- Left: title with icon -->
    <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" 
             viewBox="0 0 24 24" fill="none" stroke="currentColor" 
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
             class="lucide lucide-users-round text-indigo-600 mr-2">
            <path d="M18 21a8 8 0 0 0-16 0"/>
            <circle cx="10" cy="8" r="5"/>
            <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-900">User Roles Report</h3>
    </div>
</div>

                        <!-- Pagination Controls - Top of Table -->
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-b border-gray-200 sm:px-6" style="background: #ffffff !important; background-color: #ffffff !important;">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" style="background: #ffffff !important; background-color: #ffffff !important;">Previous</a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50" style="background: #ffffff !important; background-color: #ffffff !important;">Next</a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $totalUsers); ?></span> of <span class="font-medium"><?php echo $totalUsers; ?></span> users
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($totalPages, $page + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Next</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="sortable px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                        data-sort="id">
                                        User ID
                                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </th>
                                    <th class="sortable px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                        data-sort="username">
                                        Full Name
                                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </th>
                                    <th class="sortable px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                        data-sort="email">
                                        Email
                                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </th>
                                    <th class="sortable px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                        data-sort="role">
                                        Role
                                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Enrolled Courses
                                    </th>
                                    <th class="sortable px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" 
                                        data-sort="created_at">
                                        Date Registered
                                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" style="background: #ffffff !important; background-color: #ffffff !important;">
                                <?php if (empty($usersData)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                                </svg>
                                                <p class="text-lg">No users found</p>
                                                <p class="text-sm">Try adjusting your search filters</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usersData as $index => $user): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-blue-50 transition-colors" <?php echo $index % 2 === 0 ? 'style="background: #ffffff !important; background-color: #ffffff !important;"' : ''; ?>>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #<?php echo $user['id']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-8 w-8">
                                                        <?php 
                                                        $profilePicture = getUserProfilePicture($user);
                                                        if ($profilePicture['has_image']): 
                                                        ?>
                                                            <img class="h-8 w-8 rounded-full object-cover" src="<?php echo htmlspecialchars($profilePicture['image_path']); ?>" alt="">
                                                        <?php else: ?>
                                                            <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                                                <span class="text-sm font-medium text-gray-700">
                                                                    <?php echo $profilePicture['initial']; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            @<?php echo htmlspecialchars($user['username']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars(maskEmail($user['email'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
    <?php 
    echo match($user['role']) {
        'student' => 'bg-blue-100 text-blue-800',
        'teacher' => 'bg-green-100 text-green-800',
        'admin'   => 'bg-purple-100 text-purple-800',
        default   => 'bg-gray-100 text-gray-800'
    };
    ?>">
    <?php 
    echo match($user['role']) {
        'student' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M22 10V6L12 3 2 6v4"/>
                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                <path d="M6 12c3 2 9 2 12 0"/>
            </svg> Student',
        'teacher' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 12h6"/>
                <path d="M12 18h6"/>
                <path d="M2 4h6v16H2z"/>
                <path d="M22 4h-6v16h6z"/>
            </svg> Teacher',
        'admin' => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg> Admin',
        default => ucfirst($user['role'])
    };
    ?>
</span>

                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    <?php echo number_format($user['enrolled_courses']); ?> modules
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo in_array($user['status'], ['active', null]) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo in_array($user['status'], ['active', null]) ? '✅ Active' : '❌ ' . ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'user_roles_view_details')): ?>
                                                <button onclick="viewUserDetails(<?php echo $user['id']; ?>)" 
                                                        class="view-btn inline-flex items-center px-3 py-1.5 border border-primary-300 text-sm font-medium rounded-md text-primary-700 bg-primary-50 hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                    View
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $limit, $totalUsers); ?></span> of <span class="font-medium"><?php echo $totalUsers; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($totalPages, $page + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Next</span>
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-gray-900">User Details</h3>
                        <p class="text-sm text-gray-500">Complete user information and statistics</p>
                    </div>
                </div>
                <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-lg p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-scroll p-6 overflow-y-auto max-h-[calc(90vh-120px)]" id="modalContent">
                <!-- Content will be loaded here via JavaScript -->
                <div class="flex items-center justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="ml-2 text-gray-600">Loading user details...</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const currentFilters = <?php echo json_encode($filters); ?>;
        
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
    <script src="js/user-role.js"></script>
</body>
</html>
