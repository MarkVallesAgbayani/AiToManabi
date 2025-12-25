<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once 'includes/teacher_profile_functions.php';
require_once '../includes/rbac_helper.php';


// Check if teacher has hybrid permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
$stmt->execute([$_SESSION['user_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
$is_hybrid = !empty($permissions);

// Store permissions in session if hybrid
if ($is_hybrid) {
    $_SESSION['is_hybrid'] = true;
    $_SESSION['permissions'] = $permissions;
}

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Debug: Log profile data
error_log("Settings page - Teacher profile data: " . print_r($teacher_profile, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
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
            outline: 2px solid #e11d48;
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
        
        /* Settings message styles */
        .settings-message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }
        
        .settings-message.success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .settings-message.error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .settings-message.info {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
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
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        My Drafts
                    </a>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['archived', 'delete_permanently', 'restore_to_drafts'])): ?>                    
                    <a href="teacher_archive.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
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
                       class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
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
        <div class="flex-1 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
                    <div class="text-sm text-gray-500">
                        <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                        <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                    </div>
                </div>
            </header>

            <!-- Content Areas -->
            <main class="main-content">
                <div class="settings-container">
                    <div class="max-w-5xl mx-auto p-6" x-data="{ 
                        openSections: {
                            profile: false,
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
                                        Teacher Settings
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
                                        <p class="text-sm text-gray-500">Update your profile and how you appear to students</p>
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
                                            $picture = getTeacherProfilePicture($teacher_profile);
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
                                                    <input type="text" id="display-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm" placeholder="How your name appears to students" value="<?php echo htmlspecialchars($teacher_profile['display_name'] ?? ''); ?>">
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-bold text-red-600 px-3 py-2 bg-red-50 rounded-lg border border-red-200">
                                                        <?php echo htmlspecialchars(getTeacherRoleDisplay($teacher_profile, $is_hybrid)); ?>
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
                            
                            <div x-show="openSections.security" x-collapse class="settings-content border-t border-gray-200">
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
                                        
                                        <form id="password-change-form" class="space-y-4">
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
                                                           id="confirm-new-password" 
                                                           name="confirm_new_password"
                                                           class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" 
                                                           placeholder="Confirm your new password"
                                                           required>
                                                    <button type="button" 
                                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                            data-input="confirm-new-password">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="confirm-new-password-icon">
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


                                    <!-- Privacy Settings -->
                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                                        <h4 class="font-semibold text-blue-900 mb-4 flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                            </svg>
                                            Privacy Settings
                                        </h4>
                                        <p class="text-sm text-blue-700 mb-6">Control how your profile information is displayed to students and other users.</p>
                                        
                                        <div class="space-y-4">
                                            <div class="bg-white rounded-lg p-4 border border-blue-200">
                                                <label class="flex items-center gap-3 cursor-pointer">
                                                    <input type="checkbox" id="profile-visible" class="w-5 h-5 text-blue-600 border-2 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                                                    <div class="flex-1">
                                                        <span class="text-sm font-medium text-gray-900">Make my profile visible to students</span>
                                                        <p class="text-xs text-gray-500 mt-1">Allow students to view your profile information and contact details</p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
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
    
    <script src="js/settings-teacher.js"></script>
    <script src="js/password-change.js"></script>
    <script>
        // Privacy Settings Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const profileVisibleCheckbox = document.getElementById('profile-visible');
            
            if (profileVisibleCheckbox) {
                // Load current privacy setting
                loadPrivacySetting();
                
                // Handle checkbox change
                profileVisibleCheckbox.addEventListener('change', function() {
                    savePrivacySetting(this.checked);
                });
            }
        });
        
        function loadPrivacySetting() {
            fetch('api/get_privacy_setting.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const checkbox = document.getElementById('profile-visible');
                    checkbox.checked = data.profile_visible;
                }
            })
            .catch(error => {
                console.error('Error loading privacy setting:', error);
            });
        }
        
        function savePrivacySetting(profileVisible) {
            const checkbox = document.getElementById('profile-visible');
            const originalState = checkbox.checked;
            
            // Show loading state
            checkbox.disabled = true;
            
            fetch('api/save_privacy_setting.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    profile_visible: profileVisible
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showPrivacyMessage('Privacy setting updated successfully!', 'success');
                } else {
                    // Revert checkbox state on error
                    checkbox.checked = originalState;
                    showPrivacyMessage('Failed to update privacy setting. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert checkbox state on error
                checkbox.checked = originalState;
                showPrivacyMessage('Network error. Please check your connection.', 'error');
            })
            .finally(() => {
                // Re-enable checkbox
                checkbox.disabled = false;
            });
        }
        
        function showPrivacyMessage(message, type) {
            // Remove existing messages
            const existingMessages = document.querySelectorAll('.privacy-message');
            existingMessages.forEach(msg => msg.remove());
            
            // Create new message
            const messageDiv = document.createElement('div');
            messageDiv.className = `privacy-message settings-message ${type}`;
            messageDiv.textContent = message;
            
            // Insert after privacy settings section
            const privacySection = document.querySelector('.bg-gradient-to-r.from-blue-50.to-indigo-50');
            if (privacySection) {
                privacySection.parentNode.insertBefore(messageDiv, privacySection.nextSibling);
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    messageDiv.remove();
                }, 3000);
            }
        }
    </script>
    <script src="js/session_timeout.js"></script>


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
