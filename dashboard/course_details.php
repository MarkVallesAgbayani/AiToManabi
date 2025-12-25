<?php
session_start();
require_once('../config/database.php');
require_once('includes/teacher_profile_functions.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header('Location: courses_available.php');
    exit();
}

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Check if teacher has hybrid permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
$stmt->execute([$_SESSION['user_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
$is_hybrid = !empty($permissions);

// Fetch course details with teacher info
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            u.first_name,
            u.last_name,
            u.email as teacher_email,
            cc.name as category_name,
            cc.id as category_id,
             (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
             (SELECT COUNT(*) FROM sections WHERE course_id = c.id) as section_count,
             (SELECT COUNT(*) FROM chapters ch JOIN sections s ON ch.section_id = s.id WHERE s.course_id = c.id) as chapter_count,
             (SELECT COUNT(*) FROM quizzes q JOIN sections s ON q.section_id = s.id WHERE s.course_id = c.id) as quiz_count
        FROM courses c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN course_category cc ON c.course_category_id = cc.id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        header('Location: courses_available.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching course: " . $e->getMessage());
    header('Location: courses_available.php');
    exit();
}

// Check if current user is enrolled (for students)
$is_enrolled = false;
if ($_SESSION['role'] === 'student') {
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $is_enrolled = (bool)$stmt->fetch();
}

// Get course progress for current user (if student)
$user_progress = null;
if ($_SESSION['role'] === 'student' && $is_enrolled) {
    $stmt = $pdo->prepare("SELECT * FROM course_progress WHERE course_id = ? AND student_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $user_progress = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get course sections and chapters
$stmt = $pdo->prepare("
    SELECT 
        s.id as section_id,
        s.title as section_title,
        s.description as section_description,
        s.order_index as section_order,
        c.id as chapter_id,
        c.title as chapter_title,
        c.content as chapter_content,
        c.order_index as chapter_order
    FROM sections s
    LEFT JOIN chapters c ON s.id = c.section_id
    WHERE s.course_id = ?
    ORDER BY s.order_index ASC, c.order_index ASC
");
$stmt->execute([$course_id]);
$course_content = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize content by sections
$sections = [];
foreach ($course_content as $row) {
    $section_id = $row['section_id'];
    if (!isset($sections[$section_id])) {
        $sections[$section_id] = [
            'id' => $section_id,
            'title' => $row['section_title'],
            'description' => $row['section_description'],
            'order' => $row['section_order'],
            'chapters' => []
        ];
    }
    
    if ($row['chapter_id']) {
        $sections[$section_id]['chapters'][] = [
            'id' => $row['chapter_id'],
            'title' => $row['chapter_title'],
            'content' => $row['chapter_content'],
            'order' => $row['chapter_order']
        ];
    }
}

// Get enrolled students (for teachers viewing their own courses)
$enrolled_students = [];
if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] == $_SESSION['user_id']) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.email,
            e.enrolled_at,
            cp.completion_percentage,
            cp.last_accessed,
            sp.profile_picture
        FROM enrollments e
        JOIN users u ON e.student_id = u.id
        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
        LEFT JOIN student_preferences sp ON u.id = sp.student_id
        WHERE e.course_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$course_id]);
    $enrolled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Course Details</title>
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
        
        /* Mobile responsiveness */
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
        
        /* Custom scrollbar for course content */
        .course-content-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .course-content-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .course-content-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .course-content-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
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
                <span class="text-2xl font-bold">
                    <?php echo $_SESSION['role'] === 'teacher' ? 'Teacher Portal' : 'Student Portal'; ?>
                </span>
            </div>
            
            <!-- Profile -->
            <?php echo renderTeacherSidebarProfile($teacher_profile, $is_hybrid); ?>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: false }">
                <div class="space-y-1">
                    <?php if ($_SESSION['role'] === 'teacher'): ?>
                        <a href="teacher.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>

                        <a href="courses_available.php" 
                           class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Courses
                        </a>

                        <a href="teacher_create_module.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Create New Module
                        </a>

                        <a href="teacher_drafts.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            My Drafts
                        </a>

                        <a href="teacher_archive.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-archive-restore-icon lucide-archive-restore"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg>
                            Archived
                        </a>

                        <!-- Student Management Dropdown -->
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
                                
                                <a href="Student Management/student_profiles.php" 
                                   class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Student Profiles
                                </a>
                                
                                <a href="Student Management/progress_tracking.php" 
                                   class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    Progress Tracking
                                </a>
                                
                                <a href="Student Management/quiz_performance.php" 
                                   class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Quiz Performance
                                </a>
                                
                                <a href="Student Management/engagement_monitoring.php" 
                                   class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Engagement Monitoring
                                </a>
                                
                                <a href="Student Management/completion_reports.php" 
                                   class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Completion Reports
                                </a>
                            </div>
                        </div>

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

                        <a href="settings.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </a>
                    <?php else: ?>
                        <!-- Student navigation -->
                        <a href="student.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                        
                        <a href="courses_available.php" 
                           class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Browse Courses
                        </a>
                        
                        <a href="my_courses.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            My Courses
                        </a>
                    <?php endif; ?>
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
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Course Details</h1>
                    <div class="text-sm text-gray-500">
                        <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                        <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="p-6">
                <!-- Back button -->
                <div class="mb-6">
                    <a href="<?php echo $course['category_id'] ? 'courses_by_category.php?category_id=' . $course['category_id'] : 'courses_available.php'; ?>" 
                       class="inline-flex items-center text-gray-600 hover:text-primary-600 group transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to <?php echo $course['category_name'] ? htmlspecialchars($course['category_name']) . ' Courses' : 'Courses'; ?>
                    </a>
                </div>

                <!-- Course Header -->
                <div class="bg-gradient-to-r from-primary-50 via-pink-50 to-purple-50 rounded-2xl p-8 border border-primary-100 mb-8">
                    <div class="flex flex-col lg:flex-row gap-8">
                        <!-- Course Image -->
                        <div class="flex-shrink-0">
                            <img src="<?php echo !empty($course['image_path']) ? '../uploads/course_images/' . htmlspecialchars($course['image_path']) : '../assets/images/default-course.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>"
                                 class="w-full lg:w-64 h-48 object-cover rounded-xl shadow-lg">
                        </div>
                        
                        <!-- Course Info -->
                        <div class="flex-1">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($course['title']); ?></h1>
                                    <div class="flex items-center gap-4 text-sm text-gray-600 mb-4">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                        </span>
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <?php echo $course['student_count']; ?> students enrolled
                                        </span>
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <?php echo $course['chapter_count']; ?> lessons
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Status and Price -->
                                <div class="text-right">
                                    <?php if ($course['is_published']): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 mb-2">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Published
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 mb-2">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Draft
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="text-2xl font-bold text-primary-600">
                                        <?php if ($course['price'] > 0): ?>
                                            ₱<?php echo number_format($course['price'], 2); ?>
                                        <?php else: ?>
                                            FREE
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Course Description -->
                            <div class="prose max-w-none">
                                <p class="text-gray-700 leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($course['description'] ?? 'No description available.')); ?>
                                </p>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="mt-6 flex gap-4">
                                <?php if ($_SESSION['role'] === 'student'): ?>
                                    <?php if ($is_enrolled): ?>
                                        <a href="continue_learning.php?course_id=<?php echo $course_id; ?>" 
                                           class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all duration-300 shadow-md hover:shadow-lg">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293H15M9 10v4a1 1 0 01-1 1H6a1 1 0 01-1-1V9a1 1 0 011-1h2a1 1 0 011 1z"/>
                                            </svg>
                                            Continue Learning
                                        </a>
                                    <?php else: ?>
                                        <a href="enroll_course.php?id=<?php echo $course_id; ?>" 
                                           class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 text-white font-semibold rounded-xl hover:from-green-600 hover:to-emerald-600 transition-all duration-300 shadow-md hover:shadow-lg">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                            Enroll Now
                                        </a>
                                    <?php endif; ?>
                                <?php elseif ($_SESSION['role'] === 'teacher' && $course['teacher_id'] == $_SESSION['user_id']): ?>
                                    <a href="teacher_course_editor.php?id=<?php echo $course_id; ?>" 
                                       class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-md hover:shadow-lg">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit Course
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracking for Students -->
                <?php if ($_SESSION['role'] === 'student' && $is_enrolled && $user_progress): ?>
                <div class="bg-white rounded-2xl p-6 shadow-lg mb-8 border border-gray-100">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Your Progress</h3>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-gray-600">Course Completion</span>
                        <span class="text-sm font-semibold text-primary-600"><?php echo number_format($user_progress['completion_percentage'] ?? 0, 1); ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-4">
                        <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-3 rounded-full transition-all duration-500" 
                             style="width: <?php echo $user_progress['completion_percentage'] ?? 0; ?>%"></div>
                    </div>
                    <?php if ($user_progress['last_accessed']): ?>
                        <p class="text-sm text-gray-500">
                            Last accessed: <?php echo date('F j, Y \a\t g:i A', strtotime($user_progress['last_accessed'])); ?> (PHT)
                        </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Course Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100">
                            <h3 class="text-xl font-semibold text-gray-900 mb-6">Course Content</h3>
                            
                            <?php if (!empty($sections)): ?>
                                <div class="space-y-4 max-h-96 overflow-y-auto pr-2 course-content-scroll">
                                    <?php foreach ($sections as $section): ?>
                                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                                <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($section['title']); ?></h4>
                                                <?php if ($section['description']): ?>
                                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($section['description']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($section['chapters'])): ?>
                                                <div class="p-6">
                                                    <div class="space-y-3">
                                                        <?php foreach ($section['chapters'] as $chapter): ?>
                                                            <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center mr-3">
                                                                    <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                                                    </svg>
                                                                </div>
                                                                <div class="flex-1">
                                                                    <h5 class="font-medium text-gray-900"><?php echo htmlspecialchars($chapter['title']); ?></h5>
                                                                </div>
                                                                 <div class="text-sm text-gray-500">
                                                                     <?php if ($chapter['order'] == 0): ?>
                                                                         Chapter
                                                                     <?php else: ?>
                                                                         Lesson <?php echo $chapter['order']; ?>
                                                                     <?php endif; ?>
                                                                 </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="p-6 text-center text-gray-500">
                                                    <p>No lessons available in this section yet.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2">No content available</h4>
                                    <p class="text-gray-600">This course doesn't have any content yet. Check back later!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Course Stats -->
                        <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Course Statistics</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Sections:</span>
                                    <span class="font-semibold"><?php echo $course['section_count']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Modules:</span>
                                    <span class="font-semibold"><?php echo $course['chapter_count']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Quizzes:</span>
                                    <span class="font-semibold"><?php echo $course['quiz_count']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Students:</span>
                                    <span class="font-semibold"><?php echo $course['student_count']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Created:</span>
                                    <span class="font-semibold"><?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Instructor Info -->
                        <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Teacher</h3>
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-r from-primary-400 to-primary-600 rounded-full flex items-center justify-center text-white font-semibold">
                                    <?php echo strtoupper(substr($course['first_name'], 0, 1) . substr($course['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <?php 
                                        $email = $course['teacher_email'];
                                        $email_parts = explode('@', $email);
                                        $username = $email_parts[0];
                                        $domain = $email_parts[1];
                                        $masked_username = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 2));
                                        echo $masked_username . '@' . $domain;
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enrolled Students (for teachers) -->
                        <?php if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] == $_SESSION['user_id'] && !empty($enrolled_students)): ?>
                        <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Enrolled Students</h3>
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                <?php foreach ($enrolled_students as $student): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <?php if (!empty($student['profile_picture'])): ?>
                                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                                     class="w-8 h-8 rounded-full object-cover" 
                                                     alt="Profile">
                                            <?php else: ?>
                                                <div class="w-8 h-8 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                                    <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['username']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo number_format($student['completion_percentage'] ?? 0, 1); ?>% complete</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
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
</body>
</html>
