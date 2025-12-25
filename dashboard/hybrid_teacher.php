<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';

// Get all user permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if teacher has any admin permissions (making them hybrid)
$admin_permissions = ['nav_user_management', 'nav_reports', 'nav_payments', 'nav_course_management', 'nav_content_management', 'nav_users'];
$user_admin_permissions = array_intersect($user_permissions, $admin_permissions);
$is_hybrid = !empty($user_admin_permissions);

// If user is not hybrid, redirect to regular teacher dashboard
if (!$is_hybrid) {
    header("Location: teacher.php");
    exit();
}

// Helper function to check if user has specific permission
function hasUserPermission($permission, $user_permissions) {
    return in_array($permission, $user_permissions);
}

// Debug logging
error_log("Hybrid Teacher ID: " . $_SESSION['user_id']);
error_log("All Permissions: " . print_r($user_permissions, true));
error_log("Admin Permissions Found: " . print_r($user_admin_permissions, true));
error_log("Is Hybrid: " . ($is_hybrid ? 'true' : 'false'));

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch active courses for this teacher
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? AND is_archived = 0 ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$active_courses = $stmt->fetchAll();

// Fetch archived courses for this teacher
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? AND is_archived = 1 ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$archived_courses = $stmt->fetchAll();

// Get dashboard statistics
try {
    $stats = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM courses WHERE teacher_id = {$_SESSION['user_id']} AND is_archived = 0) as active_modules,
        (SELECT COUNT(*) FROM courses WHERE teacher_id = {$_SESSION['user_id']} AND is_archived = 1) as archived_modules,
        (SELECT COUNT(*) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = {$_SESSION['user_id']}) as total_students,
        (SELECT COUNT(*) FROM courses WHERE teacher_id = {$_SESSION['user_id']}) as published_modules
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'active_modules' => 0,
        'archived_modules' => 0,
        'total_students' => 0,
        'published_modules' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hybrid Teacher Portal - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
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
    <link href="css/hybrid_teacher.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        .main-content {
            height: calc(100vh - 64px);
            overflow-y: auto;
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
            background-color: #f0fdf4 !important;
            color: #15803d !important;
        }
    </style>
    <script>
        function loadAdminDashboard() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'admin.php';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 Admin Dashboard</h2></div>';
                mainContent.appendChild(iframe);
            }
        }

        function loadPaymentHistory() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'admin.php?view=payments';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 Payment History</h2></div>';
                mainContent.appendChild(iframe);
            }
        }

        function loadUserManagement() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'users.php';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 User Management</h2></div>';
                mainContent.appendChild(iframe);
            }
        }

        function loadCourseManagement() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'course_management_admin.php';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 Course Management</h2></div>';
                mainContent.appendChild(iframe);
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-green-600 to-green-800 text-white">
                <span class="text-2xl font-bold">🟢 Hybrid Teacher</span>
            </div>
            
            <!-- Teacher Profile -->
            <div class="p-4 border-b flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-green-400 to-green-600 flex items-center justify-center text-white font-semibold text-lg shadow-md">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div>
                    <div class="font-medium"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="text-sm text-gray-500">Hybrid Teacher</div>
                </div>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <!-- Core Teacher Navigation -->
                    <!-- Dashboard - Always available for hybrid teachers -->
                    <a href="hybrid_teacher.php" class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>

                    <?php if (hasUserPermission('nav_teacher_courses', $user_permissions)): ?>
                        <a href="courses_available.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                           <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Courses
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_teacher_create_module', $user_permissions)): ?>
                        <a href="teacher_create_module.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Create New Module
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_teacher_placement_test', $user_permissions)): ?>
                        <a href="Placement Test/placement_test.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-list"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>
                            Placement Test
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_teacher_settings', $user_permissions)): ?>
                        <a href="settings.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </a>
                    <?php endif; ?>

                    <!-- Divider -->
                    <hr class="my-3 border-gray-200">
                    <div class="text-xs font-semibold text-gray-500 px-4 mb-2">ADMIN FEATURES</div>

                    <!-- Admin Navigation -->
                    <?php if (hasUserPermission('nav_user_management', $user_permissions) || hasUserPermission('nav_users', $user_permissions)): ?>
                        <a href="#" onclick="loadUserManagement()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            User Management
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_reports', $user_permissions)): ?>
                        <a href="#" onclick="loadAdminDashboard()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-3"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                            Admin Reports
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_payments', $user_permissions)): ?>
                        <a href="#" onclick="loadPaymentHistory()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-credit-card"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                            Payment History
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_course_management', $user_permissions)): ?>
                        <a href="#" onclick="loadCourseManagement()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                            Course Management
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_content_management', $user_permissions)): ?>
                        <a href="../dashboard/contentmanagement/content_management.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                            Content Management
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Push logout to bottom -->
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="flex items-center justify-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
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
                    <h1 class="text-2xl font-semibold text-gray-900">🟢 Hybrid Teacher Dashboard</h1>
                    <div class="text-sm text-gray-500">
                        <?php echo date('l, F j, Y'); ?>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="main-content p-6">
                <div class="content-area active">
                    <!-- Welcome Section -->
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h2>
                        <p class="text-gray-600">You have hybrid teacher privileges with admin access to certain features.</p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['active_modules']; ?></div>
                                    <div class="text-sm text-gray-600">Active Modules</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['archived_modules']; ?></div>
                                    <div class="text-sm text-gray-600">Archived Modules</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_students']; ?></div>
                                    <div class="text-sm text-gray-600">Total Students</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['published_modules']; ?></div>
                                    <div class="text-sm text-gray-600">Published Modules</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Teacher Quick Actions -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">🟢 Teacher Quick Actions</h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <a href="teacher_create_module.php" class="flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors">
                                    <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create New Module
                                </a>
                                <a href="courses_available.php" class="flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors">
                                    <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    View My Courses
                                </a>
                            </div>
                        </div>

                        <!-- Admin Quick Actions -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">🔵 Admin Quick Actions</h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <?php if (hasUserPermission('nav_user_management', $user_permissions) || hasUserPermission('nav_users', $user_permissions)): ?>
                                    <button onclick="loadUserManagement()" class="w-full flex items-center p-3 rounded-lg border border-blue-200 hover:bg-blue-50 transition-colors">
                                        <svg class="h-5 w-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                                        </svg>
                                        Manage Users
                                    </button>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_reports', $user_permissions)): ?>
                                    <button onclick="loadAdminDashboard()" class="w-full flex items-center p-3 rounded-lg border border-blue-200 hover:bg-blue-50 transition-colors">
                                        <svg class="h-5 w-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                        View Reports
                                    </button>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_payments', $user_permissions)): ?>
                                    <button onclick="loadPaymentHistory()" class="w-full flex items-center p-3 rounded-lg border border-blue-200 hover:bg-blue-50 transition-colors">
                                        <svg class="h-5 w-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                        Payment History
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/hybrid_teacher.js"></script>
</body>
</html>
