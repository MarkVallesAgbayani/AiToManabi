<?php
session_start();

require_once('../config/database.php');
require_once('../includes/rbac_helper.php');
require_once('includes/teacher_profile_functions.php');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Validate that the current user has teacher role and exists in database
$teacher_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    // User doesn't exist, isn't a teacher, or isn't active - clear session and redirect
    session_destroy();
    header("Location: ../login.php?error=invalid_session");
    exit();
}

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Get all user permissions to check for hybrid status
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$all_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if teacher has any admin permissions (making them hybrid)
$admin_permissions = ['nav_user_management', 'nav_reports', 'nav_payments', 'nav_course_management', 'nav_content_management', 'nav_users'];
$user_admin_permissions = array_intersect($all_permissions, $admin_permissions);
$is_hybrid = !empty($user_admin_permissions);

// Get category ID from URL
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Fetch category details
try {
    $stmt = $pdo->prepare("SELECT * FROM course_category WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        header('Location: courses_available.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching category: " . $e->getMessage());
    header('Location: courses_available.php');
    exit();
}

// Fetch published courses for this category
$sql = "SELECT 
    c.*, 
    u.first_name,
    u.last_name,
    (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
FROM courses c
LEFT JOIN users u ON c.teacher_id = u.id
WHERE c.course_category_id = ? AND c.is_published = 1
ORDER BY COALESCE(c.sort_order, 999999), c.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $courses = [];
}


// Get category styles
function getCategoryBadgeStyles($categoryName) {
    $styles = [
        'Japanese' => [
            'bg' => 'bg-red-100',
            'text' => 'text-red-800',
            'border' => 'border-red-200',
            'icon' => '🇯🇵'
        ],
        'English' => [
            'bg' => 'bg-blue-100',
            'text' => 'text-blue-800',
            'border' => 'border-blue-200',
            'icon' => '🇬🇧'
        ],
        'Korean' => [
            'bg' => 'bg-purple-100',
            'text' => 'text-purple-800',
            'border' => 'border-purple-200',
            'icon' => '🇰🇷'
        ],
        'Chinese' => [
            'bg' => 'bg-yellow-100',
            'text' => 'text-yellow-800',
            'border' => 'border-yellow-200',
            'icon' => '🇨🇳'
        ]
    ];
    return $styles[$categoryName] ?? [
        'bg' => 'bg-gray-100',
        'text' => 'text-gray-800',
        'border' => 'border-gray-200',
        'icon' => '📚'
    ];
}

$styles = getCategoryBadgeStyles($category['name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> Courses - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            200: '#fecdd3',
                            300: '#fda4af',
                            400: '#fb7185',
                            500: '#f43f5e',
                            600: '#e11d48',
                            700: '#be123c',
                            800: '#9f1239',
                            900: '#881337',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .main-content {
            margin-left: 16rem;
            min-height: calc(100vh - 64px);
            padding: 1.5rem;
        }
        [x-cloak] { 
            display: none !important; 
        }
        .nav-link {
            transition: all 0.2s ease-in-out;
        }
        .nav-link:hover {
            background-color: #f3f4f6;
        }
        .nav-link.active {
            background-color: #fff1f2;
            color: #be123c;
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
        
        /* Drag and Drop Styles */
        .course-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .course-card.dragging {
            opacity: 0.7;
            transform: rotate(2deg) scale(1.02);
            z-index: 1000;
        }
        
        .course-card.drag-over {
            transform: scale(1.05);
            border: 3px solid #f43f5e;
            background-color: #fef2f2;
            box-shadow: 0 0 20px rgba(244, 63, 94, 0.3);
        }
        
        .course-card.drag-over-left {
            transform: scale(1.05);
            border-left: 6px solid #f43f5e;
            background-color: #fef2f2;
            box-shadow: 0 0 20px rgba(244, 63, 94, 0.3);
            position: relative;
        }
        
        .course-card.drag-over-left::before {
            content: '';
            position: absolute;
            left: -3px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-left: 8px solid #f43f5e;
            z-index: 10;
        }
        
        .course-card.drag-over-right {
            transform: scale(1.05);
            border-right: 6px solid #f43f5e;
            background-color: #fef2f2;
            box-shadow: 0 0 20px rgba(244, 63, 94, 0.3);
            position: relative;
        }
        
        .course-card.drag-over-right::before {
            content: '';
            position: absolute;
            right: -3px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-right: 8px solid #f43f5e;
            z-index: 10;
        }
        
        .drop-zone {
            min-height: 200px;
            border: 2px dashed #e5e7eb;
            border-radius: 1rem;
            background-color: #f9fafb;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-weight: 500;
        }
        
        .drop-zone.drag-over {
            border-color: #f43f5e;
            background-color: #fef2f2;
            color: #f43f5e;
        }
        
        
        .placeholder {
            background: linear-gradient(45deg, #f3f4f6, #e5e7eb);
            border: 2px dashed #d1d5db;
            border-radius: 1rem;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-weight: 500;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .mobile-menu-btn {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .course-card {
                touch-action: none;
            }
            
            .course-card.dragging {
                transform: scale(1.05);
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .category-info {
                width: 100%;
            }
            
            .save-order-btn {
                width: 100%;
                justify-content: center;
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
            
            .course-actions {
                flex-direction: column;
                gap: 0.375rem;
            }
            
            .course-actions button,
            .course-actions a {
                width: 100%;
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
            
            .course-image {
                height: 160px;
            }
            
            .course-content {
                padding: 1rem;
            }
            
            .course-title {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .course-description {
                font-size: 0.8rem;
                margin-bottom: 0.75rem;
                line-height: 1.4;
            }
        }
        
        @media (max-width: 640px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .header-title {
                font-size: 1.25rem;
            }
            
            .header-time {
                font-size: 0.8rem;
            }
            
            .course-card {
                margin-bottom: 0.75rem;
            }
            
            .course-image {
                height: 140px;
            }
            
            .course-content {
                padding: 0.75rem;
            }
            
            .course-title {
                font-size: 0.95rem;
                margin-bottom: 0.375rem;
            }
            
            .course-description {
                font-size: 0.75rem;
                margin-bottom: 0.5rem;
                line-height: 1.3;
            }
            
            .course-actions button,
            .course-actions a {
                padding: 0.375rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .save-order-btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 0.375rem;
            }
            
            .category-card {
                padding: 0.75rem;
            }
            
            .category-icon {
                width: 2.5rem;
                height: 2.5rem;
                font-size: 1.25rem;
            }
            
            .category-title {
                font-size: 1.125rem;
            }
            
            .course-image {
                height: 120px;
            }
            
            .course-content {
                padding: 0.5rem;
            }
            
            .course-title {
                font-size: 0.875rem;
                margin-bottom: 0.25rem;
            }
            
            .course-description {
                font-size: 0.7rem;
                margin-bottom: 0.375rem;
                line-height: 1.2;
            }
            
            .course-actions {
                gap: 0.25rem;
            }
            
            .course-actions button,
            .course-actions a {
                padding: 0.25rem 0.375rem;
                font-size: 0.75rem;
            }
            
            .save-order-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .courses-grid {
                gap: 0.5rem;
            }
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1000;
            background: #f43f5e;
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            cursor: pointer;
        }
        
        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.open {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50" x-data="{ currentPage: 'courses', sidebarOpen: false }">
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" @click="sidebarOpen = !sidebarOpen">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" :class="{ 'open': sidebarOpen }" @click="sidebarOpen = false"></div>
    
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10 sidebar" :class="{ 'open': sidebarOpen }">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Teacher Portal</span>
            </div>
            
            <!-- Teacher Profile -->
            <?php echo renderTeacherSidebarProfile($teacher_profile, $is_hybrid); ?>

            <!-- Sidebar Navigation -->
            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['module_performance_analytics', 'student_progress_overview', 'teacher_dashboard_view_active_modules', 'teacher_dashboard_view_active_students', 'teacher_dashboard_view_completion_rate', 'teacher_dashboard_view_published_modules', 'teacher_dashboard_view_learning_analytics', 'teacher_dashboard_view_quick_actions', 'teacher_dashboard_view_recent_activities'])): ?>
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: false }">
                <div class="space-y-1">
                    <a href="teacher.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
            <?php endif; ?>


                     <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['unpublished_modules', 'edit_course_module', 'archived_course_module', 'courses'])): ?>
                    <a href="courses_available.php" 
                       class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        Courses
                    </a>
                    <?php endif; ?>

                     <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['create_new_module', 'delete_level', 'edit_level', 'add_level', 'add_quiz'])): ?>
                    <a href="teacher_create_module.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Module
                    </a>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['my_drafts', 'create_new_draft', 'archived_modules', 'published_modules', 'edit_modules'])): ?>
                    <a href="teacher_drafts.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                       :class="{ 'bg-primary-50 text-primary-700': currentPage === 'my-drafts' }">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        My Drafts
                    </a>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['archived', 'delete_permanently', 'restore_to_drafts'])): ?>
                    <a href="teacher_archive.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                       :class="{ 'bg-primary-50 text-primary-700': currentPage === 'archived' }">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-archive-restore-icon lucide-archive-restore"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg>
                        Archived
                    </a>
                    <?php endif; ?>

                    <!-- Student Management Dropdown -->
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['student_profiles', 'progress_tracking', 'quiz_performance', 'engagement_monitoring', 'completion_reports'])): ?>
                    <div class="relative">
                        <button @click="studentDropdownOpen = !studentDropdownOpen" 
                                class="nav-link w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                                :class="{ 'bg-primary-50 text-primary-700': studentDropdownOpen }">
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Student Management
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" 
                                 :class="{ 'rotate-180': studentDropdownOpen }" 
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="studentDropdownOpen" 
                             x-transition:enter="dropdown-enter"
                             x-transition:enter-start="dropdown-enter-start"
                             x-transition:enter-end="dropdown-enter-end"
                             x-cloak
                             class="mt-1 ml-4 space-y-1">
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['student_profiles', 'view_progress_button', 'view_profile_button', 'search_and_filter'])): ?>
                            <a href="Student Management/student_profiles.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Student Profiles
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['progress_tracking', 'export_progress', 'complete_modules', 'in_progress', 'average_progress', 'active_students', 'progress_distribution', 'module_completion', 'detailed_progress_tracking'])): ?>
                            <a href="Student Management/progress_tracking.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Progress Tracking
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['quiz_performance', 'filter_search', 'export_pdf_quiz', 'average_score', 'total_attempts', 'active_students_quiz', 'total_quiz_students', 'performance_trend', 'quiz_difficulty_analysis', 'top_performer', 'recent_quiz_attempt'])): ?>
                            <a href="Student Management/quiz_performance.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Quiz Performance
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['engagement_monitoring', 'filter_engagement_monitoring', 'export_pdf_engagement', 'login_frequency', 'drop_off_rate', 'average_enrollment_days', 'recent_enrollments', 'time_spent_learning', 'module_engagement', 'most_engaged_students', 'recent_enrollments_card'])): ?>
                            <a href="Student Management/engagement_monitoring.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Engagement Monitoring
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['completion_reports', 'filter_completion_reports', 'export_completion_reports', 'overall_completion_rate', 'average_progress_completion_reports', 'on_time_completions', 'delayed_completions', 'module_completion_breakdown', 'completion_timeline', 'completion_breakdown'])): ?>
                            <a href="Student Management/completion_reports.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Completion Reports
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['preview_placement', 'placement_test', 'teacher_placement_test_create', 'teacher_placement_test_edit', 'teacher_placement_test_delete', 'teacher_placement_test_publish'])): ?>
                    <a href="Placement Test/placement_test.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
            <svg fill="#000000" viewBox="0 0 64 64" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg">
                <g id="SVGRepo_iconCarrier">
                                <g data-name="Layer 2" id="Layer_2">
                    <path d="M51,5H41.21A2.93,2.93,0,0,0,39,4H34.21a2.94,2.94,0,0,0-4.42,0H25a2.93,2.93,0,0,0-2.21,1H13a2,2,0,0,0-2,2V59a2,2,0,0,0,2,2H51a2,2,0,0,0,2-2V7A2,2,0,0,0,51,5Zm-19.87.5A1,1,0,0,1,32,5a1,1,0,0,1,.87.51A1,1,0,0,1,33,6a1,1,0,0,1-2,0A1.09,1.09,0,0,1,31.13,5.5ZM32,9a3,3,0,0,0,3-3h4a1,1,0,0,1,.87.51A1,1,0,0,1,40,7V9a1,1,0,0,1-1,1H25a1,1,0,0,1-1-1V7a1.09,1.09,0,0,1,.13-.5A1,1,0,0,1,25,6h4A3,3,0,0,0,32,9ZM51,59H13V7h9V9a3,3,0,0,0,3,3H39a3,3,0,0,0,3-3V7h9Z"></path>
                    <path d="M16,56H48V15H16Zm2-39H46V54H18Z"></path>
                    <rect height="2" width="18" x="26" y="22"></rect>
                    <rect height="2" width="4" x="20" y="22"></rect>
                    <rect height="2" width="18" x="26" y="27"></rect>
                    <rect height="2" width="4" x="20" y="27"></rect>
                    <rect height="2" width="18" x="26" y="32"></rect>
                    <rect height="2" width="4" x="20" y="32"></rect>
                    <rect height="2" width="18" x="26" y="37"></rect>
                    <rect height="2" width="4" x="20" y="37"></rect>
                    <rect height="2" width="18" x="26" y="42"></rect>
                    <rect height="2" width="4" x="20" y="42"></rect>
                    <rect height="2" width="18" x="26" y="47"></rect>
                    <rect height="2" width="4" x="20" y="47"></rect>
                                </g>
                </g>
            </svg>
            Placement Test
                    </a>
                    <?php endif; ?>

                    <a href="settings.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
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
        <div class="flex-1 ml-64 main-content">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 header-content flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900 header-title">
                        <?php echo htmlspecialchars($category['name']); ?> Courses
                    </h1>
                    <div class="text-sm text-gray-500 header-time">
                        <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                        <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="p-6">
                <!-- Back button and Category header -->
                <div class="mb-8">
                    <a href="courses_available.php" class="inline-flex items-center text-gray-600 hover:text-primary-600 mb-6 group transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Categories
                    </a>
                    
                    <div class="bg-gradient-to-r from-primary-50 via-pink-50 to-purple-50 rounded-2xl p-6 border border-primary-100 relative category-card">
                        <div class="flex items-center justify-between category-header">
                            <div class="flex items-center gap-4 category-info">
                                <div class="w-16 h-16 bg-white/80 backdrop-blur-sm rounded-xl flex items-center justify-center shadow-md category-icon">
                                    <span class="text-4xl"><?php echo $styles['icon']; ?></span>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900 mb-2 category-title"><?php echo htmlspecialchars($category['name']); ?> Learning</h2>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($category['description']); ?></p>
                                </div>
                            </div>
                            
                            <!-- Save Order Button - positioned in top right of category card -->
                            <button id="saveOrderBtn" 
                                    class="hidden bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white px-4 py-2 rounded-lg font-semibold transition-all duration-300 flex items-center gap-2 save-order-btn"
                                    onclick="saveCourseOrder()">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Save Module Order
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Courses Grid -->
                <?php if (!empty($courses)): ?>
                    <div id="coursesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 courses-grid">
                        <?php foreach ($courses as $index => $course): ?>
                            <div class="course-card group bg-white rounded-2xl overflow-hidden hover:scale-105 transition-all duration-300 border border-gray-100 relative cursor-move" 
                                 data-course-id="<?php echo $course['id']; ?>"
                                 data-sort-order="<?php echo $course['sort_order'] ?? $index; ?>"
                                 draggable="true">
                                <!-- Status Indicator - Circular Dot -->
                                <div class="absolute top-3 left-3 w-3 h-3 rounded-full <?php echo $course['is_published'] ? 'bg-green-500' : 'bg-orange-500'; ?> z-10 border-2 border-white"></div>
                                
                                <div class="relative h-48 overflow-hidden course-image">
                                    <img 
                                        src="<?php echo !empty($course['image_path']) ? '../uploads/course_images/' . htmlspecialchars($course['image_path']) : '../assets/images/default-course.jpg'; ?>" 
                                        alt="<?php echo htmlspecialchars($course['title']); ?>"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                    >
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                                    <?php if ($course['price'] > 0): ?>
                                        <div class="absolute top-4 right-4">
                                            <span class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-full text-sm font-bold">
                                                ₱<?php echo number_format($course['price'], 2); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="absolute top-4 right-4">
                                            <span class="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-full text-sm font-bold">
                                                FREE
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-6 course-content">
                                    <h2 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-primary-600 transition-colors duration-300 course-title">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </h2>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-3 leading-relaxed course-description">
                                        <?php echo strip_tags($course['description'] ?? 'Comprehensive course content designed to enhance your learning experience.'); ?>
                                    </p>
                                    
                                    <div class="flex items-center justify-between mb-6 p-3 bg-gray-50 rounded-xl">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-r from-red-400 to-red-500 rounded-full flex items-center justify-center mr-3">
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <span class="text-gray-700 text-sm font-medium block">
                                                    <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                </span>
                                                <div class="text-gray-500 text-xs mt-1">
                                                    <?php echo $course['student_count']; ?> students
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-3 course-actions">
                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'teacher' && $course['teacher_id'] == $_SESSION['user_id']): ?>
                                            <!-- Teacher's own course - show publish/unpublish and edit buttons based on permissions -->
                                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'courses')): ?>
                                            <button onclick="togglePublishStatus(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>', <?php echo $course['is_published'] ? 'true' : 'false'; ?>)" 
                                                    class="flex-1 text-center <?php echo $course['is_published'] ? 'bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700' : 'bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600'; ?> text-white px-4 py-3 rounded-xl transition-all duration-300 font-semibold transform hover:-translate-y-0.5 flex items-center justify-center">
                                                <?php if ($course['is_published']): ?>
                                                    <i data-lucide="eye-off" class="w-4 h-4 mr-2"></i>
                                                <?php else: ?>
                                                    <i data-lucide="eye" class="w-4 h-4 mr-2"></i>
                                                <?php endif; ?>
                                                <?php echo $course['is_published'] ? 'Unpublish' : 'Published'; ?>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'edit_course_module')): ?>
                                            <a href="teacher_course_editor.php?id=<?php echo $course['id']; ?>" 
                                               class="flex-1 text-center bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-3 rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-300 font-semibold transform hover:-translate-y-0.5 flex items-center justify-center">
                                                <i data-lucide="edit" class="w-4 h-4 mr-2"></i> Edit Course
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'archived_course_module')): ?>
                                            <button onclick="archiveCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['title']); ?>', <?php echo $course['is_published'] ? 'true' : 'false'; ?>)" 
                                                    class="flex-1 text-center bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-3 rounded-xl hover:from-gray-600 hover:to-gray-700 transition-all duration-300 font-semibold transform hover:-translate-y-0.5 flex items-center justify-center">
                                                <i data-lucide="archive" class="w-4 h-4 mr-2"></i> Archive
                                            </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Other users - show view details or enroll -->
                                            <a href="course_details.php?id=<?php echo $course['id']; ?>" 
                                               class="flex-1 text-center bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-3 rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300 font-semibold transform hover:-translate-y-0.5 flex items-center justify-center">
                                                <i data-lucide="info" class="w-4 h-4 mr-2"></i> View Details
                                            </a>
                                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'teacher'): ?>
                                                <a href="enroll_course.php?id=<?php echo $course['id']; ?>" 
                                                   class="flex-1 text-center bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-3 rounded-xl hover:from-green-600 hover:to-emerald-600 transition-all duration-300 font-semibold transform hover:-translate-y-0.5 flex items-center justify-center">
                                                    <i data-lucide="graduation-cap" class="w-4 h-4 mr-2"></i> Enroll Now
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Bottom accent -->
                                <div class="h-1 bg-gradient-to-r from-primary-400 via-red-400 to-pink-400"></div>
                                
                                <!-- Drag Handle -->
                                <div class="absolute top-3 right-3 w-8 h-8 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-grab active:cursor-grabbing">
                                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h8M8 10h8M8 14h8M8 18h8"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h2M4 10h2M4 14h2M4 18h2"></path>
                                    </svg>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16 bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl">
                        <div class="max-w-md mx-auto">
                            <div class="w-24 h-24 bg-gradient-to-r from-primary-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-12 h-12 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No modules available</h3>
                            <p class="text-gray-600 mb-6">Check back later for new modules in this category, or explore other categories.</p>
                            <a href="courses_available.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                Browse All Categories
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- JavaScript for Publish/Unpublish functionality -->
    <script>
        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            initializeDragAndDrop();
        });
        
        // Drag and Drop Variables
        let draggedElement = null;
        let draggedIndex = null;
        let hasChanges = false;
        let autoScrollInterval = null;
        
        function initializeDragAndDrop() {
            const courseCards = document.querySelectorAll('.course-card');
            const coursesGrid = document.getElementById('coursesGrid');
            
            // Add drag event listeners to each course card
            courseCards.forEach((card, index) => {
                card.addEventListener('dragstart', handleDragStart);
                card.addEventListener('dragend', handleDragEnd);
                card.addEventListener('dragover', handleDragOver);
                card.addEventListener('drop', handleDrop);
                card.addEventListener('dragenter', handleDragEnter);
                card.addEventListener('dragleave', handleDragLeave);
            });
            
            // Remove grid-level event listeners - only allow card-to-card swapping
        }
        
        function handleDragStart(e) {
            draggedElement = this;
            draggedIndex = Array.from(this.parentNode.children).indexOf(this);
            
            // Add dragging class for visual feedback
            this.classList.add('dragging');
            
            // Set drag data
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.outerHTML);
            
            // Create a custom drag image
            const dragImage = this.cloneNode(true);
            dragImage.style.transform = 'rotate(2deg)';
            dragImage.style.opacity = '0.8';
            dragImage.style.boxShadow = 'none';
            document.body.appendChild(dragImage);
            e.dataTransfer.setDragImage(dragImage, 0, 0);
            
            // Remove the temporary drag image after a short delay
            setTimeout(() => {
                document.body.removeChild(dragImage);
            }, 0);
            
            // Start auto-scroll monitoring
            startAutoScroll();
        }
        
        function handleDragEnd(e) {
            // Remove dragging class
            this.classList.remove('dragging');
            
            // Remove drag-over classes from all elements
            document.querySelectorAll('.course-card').forEach(card => {
                card.classList.remove('drag-over');
            });
            
            // Stop auto-scroll
            stopAutoScroll();
            
            draggedElement = null;
            draggedIndex = null;
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }
        
        function handleDragEnter(e) {
            e.preventDefault();
            if (this !== draggedElement) {
                // Determine which side of the card the mouse is over
                const rect = this.getBoundingClientRect();
                const mouseX = e.clientX;
                const cardCenterX = rect.left + (rect.width / 2);
                
                // Remove any existing indicators
                this.classList.remove('drag-over', 'drag-over-left', 'drag-over-right');
                
                // Add appropriate indicator
                if (mouseX < cardCenterX) {
                    this.classList.add('drag-over-left');
                } else {
                    this.classList.add('drag-over-right');
                }
            }
        }
        
        function handleDragLeave(e) {
            if (!this.contains(e.relatedTarget)) {
                this.classList.remove('drag-over', 'drag-over-left', 'drag-over-right');
            }
        }
        
        function handleDrop(e) {
            e.preventDefault();
            this.classList.remove('drag-over', 'drag-over-left', 'drag-over-right');
            
            if (this === draggedElement) return;
            
            // Get mouse position to determine left/right
            const rect = this.getBoundingClientRect();
            const mouseX = e.clientX;
            const cardCenterX = rect.left + (rect.width / 2);
            const insertLeft = mouseX < cardCenterX;
            
            // Get all cards
            const allCards = Array.from(document.querySelectorAll('.course-card'));
            const draggedIndex = allCards.indexOf(draggedElement);
            const targetIndex = allCards.indexOf(this);
            
            // Simple logic: insert left or right of target
            let newIndex = targetIndex;
            if (insertLeft) {
                // Insert before target
                if (draggedIndex > targetIndex) {
                    newIndex = targetIndex;
                } else {
                    newIndex = targetIndex - 1;
                }
            } else {
                // Insert after target
                if (draggedIndex < targetIndex) {
                    newIndex = targetIndex;
                } else {
                    newIndex = targetIndex + 1;
                }
            }
            
            // Move the element
            const grid = document.getElementById('coursesGrid');
            const draggedNode = draggedElement;
            
            draggedNode.remove();
            
            if (newIndex >= grid.children.length) {
                grid.appendChild(draggedNode);
            } else {
                grid.insertBefore(draggedNode, grid.children[newIndex]);
            }
            
            // Mark changes and animate
            hasChanges = true;
            showSaveButton();
            
            draggedElement.style.transform = 'scale(1.02)';
            setTimeout(() => {
                draggedElement.style.transform = '';
            }, 200);
        }
        
        
        function showSaveButton() {
            const saveBtn = document.getElementById('saveOrderBtn');
            saveBtn.classList.remove('hidden');
            saveBtn.style.animation = 'pulse 1s infinite';
        }
        
        function hideSaveButton() {
            const saveBtn = document.getElementById('saveOrderBtn');
            saveBtn.classList.add('hidden');
            saveBtn.style.animation = '';
            hasChanges = false;
        }
        
        function saveCourseOrder() {
            if (!hasChanges) return;
            
            const confirmMessage = 'Are you sure you want to save the new module order? This will update the order for all users viewing this category.';
            
            showCustomConfirm(confirmMessage, () => {
                proceedWithSaveOrder();
            });
        }
        
        function proceedWithSaveOrder() {
            const saveBtn = document.getElementById('saveOrderBtn');
            const originalText = saveBtn.innerHTML;
            
            // Show loading state
            saveBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Saving Order...';
            saveBtn.disabled = true;
            lucide.createIcons();
            
            // Get the new order
            const courseCards = document.querySelectorAll('.course-card');
            const newOrder = Array.from(courseCards).map((card, index) => ({
                course_id: card.dataset.courseId,
                sort_order: index + 1
            }));
            
            // Make AJAX request to save the order
            fetch('save_course_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    category_id: <?php echo $category_id; ?>,
                    course_order: newOrder
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Module order saved successfully!', 'success');
                    hideSaveButton();
                    
                    // Update the data-sort-order attributes
                    courseCards.forEach((card, index) => {
                        card.dataset.sortOrder = index + 1;
                    });
                } else {
                    showNotification(data.message || 'Failed to save module order', 'error');
                    // Restore button state
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while saving the order', 'error');
                // Restore button state
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                lucide.createIcons();
            });
        }
        
        function togglePublishStatus(courseId, courseTitle, isPublished) {
            const action = isPublished ? 'unpublish' : 'publish';
            const actionText = isPublished ? 'unpublish' : 'publish';
            const confirmMessage = isPublished ? 
                `Are you sure you want to unpublish "${courseTitle}"? This will make it unavailable to students.` :
                `Are you sure you want to publish "${courseTitle}"? This will make it available to students.`;
            
            // Store the button reference
            const button = event.target.closest('button');
            
            showCustomConfirm(confirmMessage, () => {
                proceedWithToggle(courseId, courseTitle, isPublished, action, button);
            });
        }
        
        function archiveCourse(courseId, courseTitle, isPublished) {
            if (isPublished) {
                // If course is published, show warning that it needs to be unpublished first
                const confirmMessage = `"${courseTitle}" is currently published and visible to students. To archive this course, you must first unpublish it. Would you like to unpublish and archive it now?`;
                
                // Store the button reference
                const button = event.target.closest('button');
                
                showCustomConfirm(confirmMessage, () => {
                    proceedWithUnpublishAndArchive(courseId, courseTitle, button);
                });
            } else {
                // If course is already unpublished, proceed with normal archive
                const confirmMessage = `Are you sure you want to archive "${courseTitle}"? This will hide it from your main course list but you can restore it anytime from the Archived page.`;
                
                // Store the button reference
                const button = event.target.closest('button');
                
                showCustomConfirm(confirmMessage, () => {
                    proceedWithArchive(courseId, courseTitle, button);
                });
            }
        }
        
        function proceedWithToggle(courseId, courseTitle, isPublished, action, button) {
            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Processing...';
            button.disabled = true;
            lucide.createIcons(); // Re-initialize icons for the spinner
            
            // Make AJAX request
            fetch('teacher_drafts.php?ajax=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    course_id: courseId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification(data.message || 'Operation failed', 'error');
                    // Restore button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                    lucide.createIcons(); // Re-initialize icons
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
                lucide.createIcons(); // Re-initialize icons
            });
        }
        
        function proceedWithUnpublishAndArchive(courseId, courseTitle, button) {
            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Unpublishing & Archiving...';
            button.disabled = true;
            lucide.createIcons(); // Re-initialize icons for the spinner
            
            // First unpublish the course
            fetch('teacher_drafts.php?ajax=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'unpublish',
                    course_id: courseId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Now archive the course
                    return fetch('teacher_archive.php?ajax=1', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=archive&course_id=${courseId}`
                    });
                } else {
                    throw new Error(data.message || 'Failed to unpublish course');
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Course unpublished and archived successfully', 'success');
                    // Remove the course card from the page
                    const courseCard = button.closest('.group');
                    courseCard.style.transition = 'all 0.3s ease';
                    courseCard.style.transform = 'scale(0.8)';
                    courseCard.style.opacity = '0';
                    
                    setTimeout(() => {
                        courseCard.remove();
                    }, 300);
                } else {
                    throw new Error(data.message || 'Failed to archive course');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification(error.message || 'An error occurred while processing the course', 'error');
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
                lucide.createIcons(); // Re-initialize icons
            });
        }
        
        function proceedWithArchive(courseId, courseTitle, button) {
            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Archiving...';
            button.disabled = true;
            lucide.createIcons(); // Re-initialize icons for the spinner
            
            // Make AJAX request
            fetch('teacher_archive.php?ajax=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=archive&course_id=${courseId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Remove the course card from the page
                    const courseCard = button.closest('.group');
                    courseCard.style.transition = 'all 0.3s ease';
                    courseCard.style.transform = 'scale(0.8)';
                    courseCard.style.opacity = '0';
                    
                    setTimeout(() => {
                        courseCard.remove();
                    }, 300);
                } else {
                    showNotification(data.message || 'Failed to archive course', 'error');
                    // Restore button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                    lucide.createIcons(); // Re-initialize icons
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while archiving the course', 'error');
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
                lucide.createIcons(); // Re-initialize icons
            });
        }
        
        function showCustomConfirm(message, onConfirm) {
            // Prevent multiple modals
            if (document.getElementById('confirmModal')) {
                return;
            }
            
            // Create modal backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            backdrop.id = 'confirmModal';
            
            // Create modal content
            backdrop.innerHTML = `
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="confirmModalContent">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mr-4">
                                <i data-lucide="alert-triangle" class="w-6 h-6 text-orange-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Confirm Action</h3>
                        </div>
                        <p class="text-gray-600 mb-6 leading-relaxed">${message}</p>
                        <div class="flex gap-3 justify-end">
                            <button id="confirmCancel" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium transition-colors duration-200">
                                Cancel
                            </button>
                            <button id="confirmOk" class="px-6 py-2 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-medium rounded-lg transition-all duration-200 shadow-md hover:shadow-lg">
                                Yes, Continue
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(backdrop);
            
            // Initialize icons
            lucide.createIcons();
            
            // Show modal with animation
            setTimeout(() => {
                const modalContent = document.getElementById('confirmModalContent');
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
            
            // Handle button clicks
            document.getElementById('confirmOk').addEventListener('click', () => {
                closeModal();
                onConfirm();
            });
            
            document.getElementById('confirmCancel').addEventListener('click', closeModal);
            
            // Close on backdrop click
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    closeModal();
                }
            });
            
            // Close on Escape key
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
            
            function closeModal() {
                const modalContent = document.getElementById('confirmModalContent');
                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');
                
                setTimeout(() => {
                    backdrop.remove();
                }, 300);
            }
        }
        
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}" class="w-5 h-5 mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Initialize icons for the notification
            lucide.createIcons();
            
            // Show notification
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
                notification.style.opacity = '1';
            }, 100);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
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

        // Auto-scroll functions
        function startAutoScroll() {
            autoScrollInterval = setInterval(() => {
                if (!draggedElement) return;
                
                const mouseY = window.mouseY || 0;
                const windowHeight = window.innerHeight;
                const scrollThreshold = 100; // Distance from edge to trigger scroll
                const scrollSpeed = 5; // Pixels to scroll per interval
                
                // Scroll down if mouse is near bottom edge
                if (mouseY > windowHeight - scrollThreshold) {
                    window.scrollBy(0, scrollSpeed);
                }
                // Scroll up if mouse is near top edge
                else if (mouseY < scrollThreshold) {
                    window.scrollBy(0, -scrollSpeed);
                }
            }, 16); // ~60fps
        }
        
        function stopAutoScroll() {
            if (autoScrollInterval) {
                clearInterval(autoScrollInterval);
                autoScrollInterval = null;
            }
        }
        
        // Track mouse position during drag
        document.addEventListener('dragover', function(e) {
            window.mouseY = e.clientY;
        });

        // Initialize real-time clock
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
        });
    </script>
    
</body>
</html> 