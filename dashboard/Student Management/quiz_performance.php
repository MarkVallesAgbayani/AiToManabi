<?php
session_start();

// Check if user is logged in and is a teacher or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login.php');
    exit();
}

// Include required files
require_once '../../config/database.php';
require_once '../includes/teacher_profile_functions.php';
require_once 'includes/quiz_performance_functions.php';
require_once '../reports.php';
require_once '../../includes/rbac_helper.php';


// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $filters = [
        'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
        'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
        'student_id' => $_GET['student_id'] ?? '',
        'quiz_id' => $_GET['quiz_id'] ?? '',
        'export_columns' => ['student', 'module', 'quiz', 'score', 'percentage', 'date'],
        'data_source' => 'auto',
        'export_detail' => 'summary',
        'report_purpose' => 'quiz_performance',
        'confidentiality' => 'internal',
        'summary_metrics' => ['totals', 'averages'],
        'data_grouping' => 'chronological',
        'admin_info_level' => 'name_only'
    ];
    
    // Use centralized report generator
    $reportGenerator = new ReportGenerator($pdo);
    $reportGenerator->generateQuizPerformanceReport($filters, $_GET['export']);
    exit;
}

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Check if teacher has hybrid permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
$stmt->execute([$_SESSION['user_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
$is_hybrid = !empty($permissions);

// Custom profile rendering function for subdirectory files
function renderTeacherSidebarProfileSubdir($profile, $is_hybrid = false) {
    $display_name = getTeacherDisplayName($profile);
    $picture = getTeacherProfilePicture($profile);
    $role_display = getTeacherRoleDisplay($profile, $is_hybrid);
    
    // Fix the image path for subdirectory files
    if ($picture['has_image']) {
        $picture['image_path'] = '../' . $picture['image_path'];
    }
    
    $html = '<div class="p-3 border-b flex items-center space-x-3">';
    
    if ($picture['has_image']) {
        $html .= '<img src="' . htmlspecialchars($picture['image_path']) . '" 
                      alt="Profile Picture" 
                      class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture">';
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder" style="display: none;">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
    } else {
        $html .= '<div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm sidebar-profile-placeholder">';
        $html .= htmlspecialchars($picture['initial']);
        $html .= '</div>';
        $html .= '<img src="" alt="Profile Picture" class="w-10 h-10 rounded-full object-cover shadow-sm sidebar-profile-picture" style="display: none;">';
    }
    
    $html .= '<div class="flex-1 min-w-0">';
    $html .= '<div class="font-medium text-sm sidebar-display-name truncate">' . htmlspecialchars($display_name) . '</div>';
    $html .= '<div class="text-xs font-bold text-red-600 sidebar-role">' . htmlspecialchars($role_display) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Initialize quiz performance analyzer
$quizAnalyzer = new QuizPerformanceAnalyzer($pdo);

// Get date range filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$student_id = $_GET['student_id'] ?? '';
$quiz_id = $_GET['quiz_id'] ?? '';

// Get overall statistics
$overallStats = $quizAnalyzer->getOverallStatistics($date_from, $date_to);
$topPerformers = $quizAnalyzer->getTopPerformers(10, $date_from, $date_to);
$quizStats = $quizAnalyzer->getQuizStatistics($date_from, $date_to);

// Get pagination parameters
$page = $_GET['page'] ?? 1;
$limit = 5; // Set limit to 5 rows as requested
$recentAttemptsResult = $quizAnalyzer->getRecentAttempts($limit, $page, $date_from, $date_to);
$recentAttempts = $recentAttemptsResult['data'];
$pagination = $recentAttemptsResult['pagination'];

// Get students and quizzes for filters
$students = $quizAnalyzer->getAllStudents();
$quizzes = $quizAnalyzer->getAllQuizzes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Performance - Student Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/quiz_performance.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
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
            background-color: #fff1f2 !important;
            color: #be123c !important;
        }
        .dropdown-enter {
            transition: all 0.2s ease-out;
        }
        .dropdown-enter-start {
            opacity: 0;
            transform: translateY(-10px);
        }
        .dropdown-enter-end {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-primary-600 to-primary-800 text-white">
                <span class="text-2xl font-bold">Teacher Portal</span>
            </div>
            
            <!-- Teacher Profile -->
            <?php echo renderTeacherSidebarProfileSubdir($teacher_profile, $is_hybrid); ?>

            <!-- Sidebar Navigation -->
            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['module_performance_analytics', 'student_progress_overview', 'teacher_dashboard_view_active_modules', 'teacher_dashboard_view_active_students', 'teacher_dashboard_view_completion_rate', 'teacher_dashboard_view_published_modules', 'teacher_dashboard_view_learning_analytics', 'teacher_dashboard_view_quick_actions', 'teacher_dashboard_view_recent_activities'])): ?>
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: false, currentPage: 'quiz-performance' }">
                <div class="space-y-1">
                    <a href="../teacher.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
            <?php endif; ?>

                <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['unpublished_modules', 'edit_course_module', 'archived_course_module', 'courses'])): ?>
                    <a href="../courses_available.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                       <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        Courses
                    </a>
                <?php endif; ?>


            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['create_new_module', 'delete_level', 'edit_level', 'add_level', 'add_quiz'])): ?>
                    <a href="../teacher_create_module.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Module
                    </a>
            <?php endif; ?>

            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['my_drafts', 'create_new_draft', 'archived_modules', 'published_modules', 'edit_modules'])): ?>
                    <a href="../teacher_drafts.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                       :class="{ 'bg-primary-50 text-primary-700': currentPage === 'my-drafts' }">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        My Drafts
                    </a>
            <?php endif; ?>

                <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['archived', 'delete_permanently', 'restore_to_drafts'])): ?>
                    <a href="../teacher_archive.php" 
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
                            <a href="student_profiles.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Student Profiles
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['progress_tracking', 'export_progress', 'complete_modules', 'in_progress', 'average_progress', 'active_students', 'progress_distribution', 'module_completion', 'detailed_progress_tracking'])): ?>
                            <a href="progress_tracking.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Progress Tracking
                            </a>
                            <?php endif; ?>

                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['quiz_performance', 'filter_search', 'export_pdf_quiz', 'average_score', 'total_attempts', 'active_students_quiz', 'total_quiz_students', 'performance_trend', 'quiz_difficulty_analysis', 'top_performer', 'recent_quiz_attempt'])): ?>
                            <a href="quiz_performance.php" 
                               class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Quiz Performance
                            </a>
                            <?php endif; ?>

                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['engagement_monitoring', 'filter_engagement_monitoring', 'export_pdf_engagement', 'login_frequency', 'drop_off_rate', 'average_enrollment_days', 'recent_enrollments', 'time_spent_learning', 'module_engagement', 'most_engaged_students', 'recent_enrollments_card'])): ?>
                            <a href="engagement_monitoring.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Engagement Monitoring
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['completion_reports', 'filter_completion_reports', 'export_completion_reports', 'overall_completion_rate', 'average_progress_completion_reports', 'on_time_completions', 'delayed_completions', 'module_completion_breakdown', 'completion_timeline', 'completion_breakdown'])): ?>   
                            <a href="completion_reports.php" 
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
                    <a href="../Placement Test/placement_test.php" 
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

                    <a href="../settings.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>
                </div>
                
                <div class="mt-auto pt-4">
                    <a href="../../auth/logout.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
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
                    <h1 class="text-2xl font-semibold text-gray-900">Quiz Performance Analytics</h1>
                    <div class="flex items-center gap-4">
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Filters Section -->
            <div class="p-6 bg-gradient-to-r from-gray-50 to-blue-50 border-b border-gray-200">
                <div class="mb-4 flex justify-between items-start">
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'filter_search')): ?>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-2">Filter Options</h2>
                        <p class="text-sm text-gray-600">Customize your quiz performance analysis with advanced filtering options</p>
                    </div>
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'export_pdf_quiz')): ?>
                    <button onclick="exportData()" class="bg-gradient-to-r from-primary-500 to-primary-600 text-white px-4 py-2 rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all duration-200 shadow-md hover:shadow-lg flex items-center">
                        <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                        Export PDF
                    </button>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" id="date_from" value="<?php echo $date_from; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" id="date_to" value="<?php echo $date_to; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Student</label>
                        <select id="student_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                            <option value="">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quiz</label>
                        <select id="quiz_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200">
                            <option value="">All Quizzes</option>
                            <?php foreach ($quizzes as $quiz): ?>
                                <option value="<?php echo $quiz['id']; ?>" <?php echo $quiz_id == $quiz['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($quiz['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button onclick="applyFilters()" class="bg-gradient-to-r from-primary-500 to-primary-600 text-white px-6 py-2 rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all duration-200 shadow-md hover:shadow-lg flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z" />
                        </svg>
                        Apply Filters
                    </button>
                    <button onclick="resetFilters()" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-2 rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 shadow-md hover:shadow-lg flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset
                    </button>
                </div>
            </div>
            <?php endif; ?>


            <!-- Statistics Cards -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'average_score')): ?>
                    <div class="bg-gradient-to-br from-blue-50 to-cyan-100 overflow-hidden shadow-lg rounded-xl border border-blue-100 hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-semibold text-blue-700 truncate">Average Score</dt>
                                        <dd class="flex items-baseline mt-1">
                                            <div class="text-3xl font-bold text-blue-800"><?php echo number_format($overallStats['average_score'], 1); ?>%</div>
                                            <span class="ml-2 text-sm font-medium text-blue-600">overall</span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm text-blue-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                    <span class="font-medium">Performance metric</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'total_attempts')): ?>
                    <div class="bg-gradient-to-br from-green-50 to-emerald-100 overflow-hidden shadow-lg rounded-xl border border-green-100 hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-semibold text-green-700 truncate">Total Attempts</dt>
                                        <dd class="flex items-baseline mt-1">
                                            <div class="text-3xl font-bold text-green-800"><?php echo number_format($overallStats['total_attempts']); ?></div>
                                            <span class="ml-2 text-sm font-medium text-green-600">attempts</span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm text-green-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="font-medium">Quiz completions</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'active_students_quiz')): ?>
                    <div class="bg-gradient-to-br from-amber-50 to-orange-100 overflow-hidden shadow-lg rounded-xl border border-amber-100 hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-semibold text-amber-700 truncate">Active Students</dt>
                                        <dd class="flex items-baseline mt-1">
                                            <div class="text-3xl font-bold text-amber-800"><?php echo number_format($overallStats['active_students']); ?></div>
                                            <span class="ml-2 text-sm font-medium text-amber-600">learners</span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm text-amber-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span class="font-medium">Currently active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'total_quiz_students')): ?>
                    <div class="bg-gradient-to-br from-purple-50 to-indigo-100 overflow-hidden shadow-lg rounded-xl border border-purple-100 hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-semibold text-purple-700 truncate">Total Quizzes</dt>
                                        <dd class="flex items-baseline mt-1">
                                            <div class="text-3xl font-bold text-purple-800"><?php echo number_format($overallStats['total_quizzes']); ?></div>
                                            <span class="ml-2 text-sm font-medium text-purple-600">available</span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center text-sm text-purple-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="font-medium">Assessment tools</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Performance Trend Chart -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'performance_trend')): ?>
                    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Performance Trend</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <canvas id="performanceTrendChart" height="300"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quiz Difficulty Chart -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'quiz_difficulty_analysis')): ?>
                    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Quiz Difficulty Analysis</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <canvas id="quizDifficultyChart" height="300"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Data Tables -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Performers -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'top_performer')): ?>
                    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Top Performers</h3>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 rounded-tl-lg">
                                            <div class="flex items-center space-x-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <span>Student</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50">
                                            <div class="flex items-center space-x-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                </svg>
                                                <span>Avg Score</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 rounded-tr-lg">
                                            <div class="flex items-center space-x-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                <span>Attempts</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <?php foreach ($topPerformers as $index => $performer): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gradient-to-r hover:from-gray-50 hover:to-green-50 transition-all duration-200 <?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-3">
                                                <?php if (!empty($performer['profile_picture'])): ?>
                                                    <img src="../../uploads/profile_pictures/<?php echo htmlspecialchars($performer['profile_picture']); ?>" 
                                                         class="w-10 h-10 rounded-full object-cover shadow-md" 
                                                         alt="Profile Picture"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md" style="display: none;">
                                                        <?php echo strtoupper(substr($performer['username'], 0, 1)); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                                        <?php echo strtoupper(substr($performer['username'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($performer['username']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $avg_score = $performer['average_score'];
                                            $avg_total_points = $performer['average_total_points'] ?? 0;
                                            $percentage = $avg_total_points > 0 ? ($avg_score / $avg_total_points) * 100 : $avg_score;
                                            $score_display = $avg_total_points > 0 ? round($avg_score) . '/' . round($avg_total_points) : round($avg_score) . '%';
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $percentage >= 80 ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : ($percentage >= 60 ? 'bg-gradient-to-r from-yellow-100 to-orange-100 text-yellow-800 border border-yellow-200' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200'); ?>">
                                                <?php echo $score_display; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-gray-900"><?php echo $performer['total_attempts']; ?></div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Attempts -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'recent_quiz_attempt')): ?>
                    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Recent Quiz Attempts</h3>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 rounded-tl-lg">
                                            <div class="flex items-center space-x-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <span>Student</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50">
                                            <div class="flex items-center space-x-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Quiz</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50">
                                            <div class="flex items-center space-x-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                </svg>
                                                <span>Score</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 rounded-tr-lg">
                                            <div class="flex items-center space-x-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Date</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <?php foreach ($recentAttempts as $index => $attempt): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50 transition-all duration-200 <?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-3">
                                                <?php if (!empty($attempt['profile_picture'])): ?>
                                                    <img src="../../uploads/profile_pictures/<?php echo htmlspecialchars($attempt['profile_picture']); ?>" 
                                                         class="w-10 h-10 rounded-full object-cover shadow-md" 
                                                         alt="Profile Picture"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md" style="display: none;">
                                                        <?php echo strtoupper(substr($attempt['username'], 0, 1)); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                                        <?php echo strtoupper(substr($attempt['username'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($attempt['username']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($attempt['quiz_title']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $score = $attempt['score'];
                                            $total_points = $attempt['total_points'] ?? 0;
                                            $percentage = $total_points > 0 ? ($score / $total_points) * 100 : 0;
                                            $score_display = $total_points > 0 ? $score . '/' . $total_points : number_format($score, 1);
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo $percentage >= 80 ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : ($percentage >= 60 ? 'bg-gradient-to-r from-yellow-100 to-orange-100 text-yellow-800 border border-yellow-200' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200'); ?>">
                                                <?php echo $score_display; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($attempt['completed_at'])); ?></div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Controls -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing 
                                    <span class="font-medium"><?php echo (($pagination['current_page'] - 1) * $pagination['limit']) + 1; ?></span>
                                    to 
                                    <span class="font-medium"><?php echo min($pagination['current_page'] * $pagination['limit'], $pagination['total_records']); ?></span>
                                    of 
                                    <span class="font-medium"><?php echo $pagination['total_records']; ?></span>
                                    results
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <!-- Previous Button -->
                                    <?php if ($pagination['has_prev']): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])); ?>" 
                                           class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Page Numbers -->
                                    <div class="flex space-x-1">
                                        <?php
                                        $start = max(1, $pagination['current_page'] - 2);
                                        $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <?php if ($i == $pagination['current_page']): ?>
                                                <span class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-primary-600 rounded-md">
                                                    <?php echo $i; ?>
                                                </span>
                                            <?php else: ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <!-- Next Button -->
                                    <?php if ($pagination['has_next']): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])); ?>" 
                                           class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-xl shadow-2xl border border-gray-100">
            <div class="flex items-center space-x-4">
                <div class="animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-primary-600"></div>
                <div>
                    <div class="text-lg font-semibold text-gray-900">Loading...</div>
                    <div class="text-sm text-gray-500">Processing your request</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <script src="js/quiz_performance.js"></script>
    
    <script>
        // Initialize Lucide Icons after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
        
        // Re-initialize icons when content changes (for dynamic content)
        function initializeLucideIcons() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
        
        // Make it globally available
        window.initializeLucideIcons = initializeLucideIcons;
        
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
    <script src="../js/session_timeout.js"></script>
</body>
</html>
