<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';

// Get all user permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if admin has any teacher permissions (making them hybrid)
$teacher_permissions = ['nav_teacher_courses', 'nav_teacher_create_module', 'nav_teacher_placement_test', 'nav_teacher_settings'];
$user_teacher_permissions = array_intersect($user_permissions, $teacher_permissions);
$is_hybrid = !empty($user_teacher_permissions);

// If user is not hybrid, redirect to regular admin dashboard
if (!$is_hybrid) {
    header("Location: admin.php");
    exit();
}

// Helper function to check if user has specific permission
function hasUserPermission($permission, $user_permissions) {
    return in_array($permission, $user_permissions);
}

// Debug logging
error_log("Hybrid Admin ID: " . $_SESSION['user_id']);
error_log("All Permissions: " . print_r($user_permissions, true));
error_log("Teacher Permissions Found: " . print_r($user_teacher_permissions, true));
error_log("Is Hybrid: " . ($is_hybrid ? 'true' : 'false'));

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get dashboard statistics
try {
    $stats = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'teacher') as total_teachers,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM courses WHERE is_archived = 0) as active_courses,
        (SELECT COUNT(*) FROM enrollments) as total_enrollments
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_teachers' => 0,
        'total_students' => 0,
        'active_courses' => 0,
        'total_enrollments' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hybrid Admin Portal - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#faf5ff',
                            100: '#f3e8ff',
                            200: '#e9d5ff',
                            300: '#d8b4fe',
                            400: '#c084fc',
                            500: '#a855f7',
                            600: '#9333ea',
                            700: '#7c3aed',
                            800: '#6b21a8',
                            900: '#581c87',
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
    <link href="css/hybrid_admin.css" rel="stylesheet">
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
            background-color: #faf5ff !important;
            color: #7c3aed !important;
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
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-purple-700">🟣 Admin Dashboard</h2></div>';
                mainContent.appendChild(iframe);
            }
        }

        function loadUserManagement() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'admin.php?view=users';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-purple-700">🟣 User Management</h2></div>';
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
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-purple-700">🟣 Payment History</h2></div>';
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
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-purple-700">🟣 Course Management</h2></div>';
                mainContent.appendChild(iframe);
            }
        }

        function loadAdminReports() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'admin.php?view=reports';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-purple-700">🟣 Admin Reports</h2></div>';
                mainContent.appendChild(iframe);
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-purple-600 to-purple-800 text-white">
                <span class="text-2xl font-bold">🟣 Hybrid Admin</span>
            </div>
            
            <!-- Admin Profile -->
            <div class="p-4 border-b flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-400 to-purple-600 flex items-center justify-center text-white font-semibold text-lg shadow-md">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div>
                    <div class="font-medium"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="text-sm text-gray-500">Hybrid Admin</div>
                </div>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]">
                <div class="space-y-1">
                    <!-- Core Admin Navigation -->
                    <!-- Dashboard - Always available for hybrid admins -->
                    <a href="hybrid_admin.php" class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>

                    <?php if (hasUserPermission('nav_user_management', $user_permissions) || hasUserPermission('nav_users', $user_permissions)): ?>
                    <a href="#" onclick="loadUserManagement()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        User Management
                    </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_reports', $user_permissions)): ?>
                    <a href="#" onclick="loadAdminReports()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-3"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                        Admin Reports
                    </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_payments', $user_permissions)): ?>
                    <a href="#" onclick="loadPaymentHistory()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Payment History
                    </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_course_management', $user_permissions)): ?>
                    <a href="#" onclick="loadCourseManagement()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Course Management
                    </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_content_management', $user_permissions)): ?>
                    <a href="../dashboard/contentmanagement/content_management.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        Content Management
                    </a>
                    <?php endif; ?>

                    <!-- Divider -->
                    <hr class="my-3 border-gray-200">
                    <div class="text-xs font-semibold text-gray-500 px-4 mb-2">TEACHER FEATURES</div>

                    <!-- Teacher Navigation -->
                    <?php if (hasUserPermission('nav_teacher_courses', $user_permissions)): ?>
                    <a href="courses_available.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        My Courses
                    </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_teacher_create_module', $user_permissions)): ?>
                    <a href="teacher_create_module.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create Module
                    </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_teacher_placement_test', $user_permissions)): ?>
                    <a href="Placement Test/placement_test.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-list"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>
                        Placement Test
                    </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_teacher_settings', $user_permissions)): ?>
                    <a href="settings.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Teacher Settings
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
                    <h1 class="text-2xl font-semibold text-gray-900">🟣 Hybrid Admin Dashboard</h1>
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
                        <p class="text-gray-600">You have hybrid admin privileges with teacher access to certain features.</p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_teachers']; ?></div>
                                    <div class="text-sm text-gray-600">Total Teachers</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_students']; ?></div>
                                    <div class="text-sm text-gray-600">Total Students</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['active_courses']; ?></div>
                                    <div class="text-sm text-gray-600">Active Courses</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_enrollments']; ?></div>
                                    <div class="text-sm text-gray-600">Total Enrollments</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Admin Quick Actions -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">🟣 Admin Quick Actions</h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <?php if (hasUserPermission('nav_user_management', $user_permissions) || hasUserPermission('nav_users', $user_permissions)): ?>
                                <button onclick="loadUserManagement()" class="w-full flex items-center p-3 rounded-lg border border-purple-200 hover:bg-purple-50 transition-colors admin-action">
                                    <svg class="h-5 w-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                                    </svg>
                                    <span class="text-gray-700">Manage Users</span>
                                </button>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_reports', $user_permissions)): ?>
                                <button onclick="loadAdminReports()" class="w-full flex items-center p-3 rounded-lg border border-purple-200 hover:bg-purple-50 transition-colors admin-action">
                                    <svg class="h-5 w-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    <span class="text-gray-700">View Reports</span>
                                </button>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_payments', $user_permissions)): ?>
                                <button onclick="loadPaymentHistory()" class="w-full flex items-center p-3 rounded-lg border border-purple-200 hover:bg-purple-50 transition-colors admin-action">
                                    <svg class="h-5 w-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                    <span class="text-gray-700">Payment History</span>
                                </button>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_course_management', $user_permissions)): ?>
                                <button onclick="loadCourseManagement()" class="w-full flex items-center p-3 rounded-lg border border-purple-200 hover:bg-purple-50 transition-colors admin-action">
                                    <svg class="h-5 w-5 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <span class="text-gray-700">Course Management</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Teacher Quick Actions -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">🟢 Teacher Quick Actions</h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <?php if (hasUserPermission('nav_teacher_create_module', $user_permissions)): ?>
                                <a href="teacher_create_module.php" class="w-full flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors teacher-action quick-action-card">
                                    <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <span class="text-gray-700">Create New Module</span>
                                </a>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_teacher_courses', $user_permissions)): ?>
                                <a href="courses_available.php" class="w-full flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors teacher-action quick-action-card">
                                    <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <span class="text-gray-700">View My Courses</span>
                                </a>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_teacher_placement_test', $user_permissions)): ?>
                                <a href="Placement Test/placement_test.php" class="w-full flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors teacher-action quick-action-card">
                                    <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 7l2 2 4-4"/>
                                    </svg>
                                    <span class="text-gray-700">Placement Test</span>
                                </a>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_teacher_settings', $user_permissions)): ?>
                                <a href="settings.php" class="w-full flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors teacher-action quick-action-card">
                                    <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span class="text-gray-700">Teacher Settings</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/hybrid_admin.js"></script>
</body>
</html>
                <!-- Header -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">🟣 Hybrid Admin</h2>
                    <p class="text-sm text-gray-600">Admin + Teacher Access</p>
                </div>

                <div class="space-y-1">
                    <!-- Core Admin Navigation -->
                    <!-- Dashboard - Always available for hybrid admins -->
                    <a href="hybrid_admin.php" class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>

                    <?php if (hasUserPermission('nav_user_management', $user_permissions) || hasUserPermission('nav_users', $user_permissions)): ?>
                        <a href="#" onclick="loadUserManagement()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            User Management
                        </a>
                    <?php endif; ?>

                    <?php if (hasUserPermission('nav_reports', $user_permissions)): ?>
                        <a href="#" onclick="loadAdminReports()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
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

                    <!-- Teacher Navigation -->
                    <div class="border-t border-gray-200 my-4 pt-4">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Teacher Features</div>
                        
                        <?php if (hasUserPermission('nav_teacher_courses', $user_permissions)): ?>
                            <a href="courses_available.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                               <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                                My Courses
                            </a>
                        <?php endif; ?>

                        <?php if (hasUserPermission('nav_teacher_create_module', $user_permissions)): ?>
                            <a href="teacher_create_module.php" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Create Module
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
                                Teacher Settings
                            </a>
                        <?php endif; ?>
                    </div>
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
                    <h1 class="text-2xl font-semibold text-gray-900">🔵 Hybrid Admin Dashboard</h1>
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
                        <p class="text-gray-600">You have hybrid admin privileges with teacher access to certain features.</p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_teachers']; ?></div>
                                    <div class="text-sm text-gray-600">Total Teachers</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['active_courses']; ?></div>
                                    <div class="text-sm text-gray-600">Active Courses</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_enrollments']; ?></div>
                                    <div class="text-sm text-gray-600">Total Enrollments</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                                    <button onclick="loadAdminReports()" class="w-full flex items-center p-3 rounded-lg border border-blue-200 hover:bg-blue-50 transition-colors">
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

                        <!-- Teacher Quick Actions -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">🟢 Teacher Quick Actions</h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <?php if (hasUserPermission('nav_teacher_create_module', $user_permissions)): ?>
                                    <a href="teacher_create_module.php" class="w-full flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors">
                                        <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Create New Module
                                    </a>
                                <?php endif; ?>

                                <?php if (hasUserPermission('nav_teacher_courses', $user_permissions)): ?>
                                    <a href="courses_available.php" class="w-full flex items-center p-3 rounded-lg border border-green-200 hover:bg-green-50 transition-colors">
                                        <svg class="h-5 w-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                        View My Courses
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function loadUserManagement() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'admin.php?view=users';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-blue-700">🔵 User Management</h2></div>';
                mainContent.appendChild(iframe);
            }
        }

        function loadAdminReports() {
            const mainContent = document.querySelector('.main-content .content-area');
            if (mainContent) {
                const iframe = document.createElement('iframe');
                iframe.src = 'admin.php?view=reports';
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-blue-700">🔵 Admin Reports</h2></div>';
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
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-blue-700">🔵 Payment History</h2></div>';
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
                
                mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-blue-700">🔵 Course Management</h2></div>';
                mainContent.appendChild(iframe);
            }
        }
    </script>
    <script src="js/hybrid_admin.js"></script>
</body>
</html>
