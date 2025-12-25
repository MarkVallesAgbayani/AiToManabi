<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../index.php");
    exit();
}

require_once '../../config/database.php';
require_once '../includes/teacher_profile_functions.php';
require_once '../../includes/rbac_helper.php';
require_once '../reports.php'; // Include ReportGenerator class

// Handle PDF export request
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    handleProgressPDFExport($pdo);
    exit();
}

/**
 * Handle PDF export for progress tracking data
 */
function handleProgressPDFExport($pdo) {
    // Check permissions
    if (!isset($_SESSION['user_id']) || !hasPermission($pdo, $_SESSION['user_id'], 'export_progress')) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit();
    }

    try {
        // Get filters from request
        $courseFilter = $_GET['course_filter'] ?? 'all';
        $progressFilter = $_GET['progress_filter'] ?? 'all';
        $sortFilter = $_GET['sort_filter'] ?? 'progress_desc';
        $searchQuery = $_GET['search'] ?? '';

        // Get progress data with filters
        $data = getFilteredProgressData($pdo, $courseFilter, $progressFilter, $sortFilter, $searchQuery);

        // Get summary statistics
        $summaryStats = getProgressSummaryStats($pdo, $courseFilter, $progressFilter);

        // Prepare filters for report
        $filters = [
            'course_filter' => $courseFilter,
            'progress_filter' => $progressFilter,
            'sort_filter' => $sortFilter,
            'search' => $searchQuery,
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d')
        ];

        // Initialize ReportGenerator
        $reportGenerator = new ReportGenerator($pdo);

        // Generate PDF report
        $reportGenerator->generateProgressTrackingReport($data, $summaryStats, $filters);

    } catch (Exception $e) {
        error_log("Progress PDF Export Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
        exit();
    }
}

/**
 * Get filtered progress data for export
 */
function getFilteredProgressData($pdo, $courseFilter, $progressFilter, $sortFilter, $searchQuery) {
    $whereConditions = [];
    $params = [];

    // Course filter
    if ($courseFilter !== 'all') {
        $whereConditions[] = "c.id = ?";
        $params[] = $courseFilter;
    }

    // Progress filter
    switch ($progressFilter) {
        case 'completed':
            $whereConditions[] = "(SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) = (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id)";
            break;
        case 'in_progress':
            $whereConditions[] = "(SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) > 0 AND (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) < (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id)";
            break;
        case 'not_started':
            $whereConditions[] = "(SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) = 0";
            break;
    }

    // Search filter
    if (!empty($searchQuery)) {
        $whereConditions[] = "(u.username LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = !empty($whereConditions) ? "AND " . implode(" AND ", $whereConditions) : "";

    // Sorting
    $orderBy = "ORDER BY ";
    switch ($sortFilter) {
        case 'progress_desc':
            $orderBy .= "progress_percentage DESC";
            break;
        case 'progress_asc':
            $orderBy .= "progress_percentage ASC";
            break;
        case 'name_asc':
            $orderBy .= "u.username ASC";
            break;
        case 'activity_desc':
            $orderBy .= "COALESCE(cp.last_accessed_at, e.enrolled_at) DESC";
            break;
        default:
            $orderBy .= "progress_percentage DESC";
    }

    $sql = "SELECT
                u.id as user_id,
                u.username as student_name,
                u.email as student_email,
                c.title as course_title,
                ROUND((SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) / NULLIF((SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id), 0) * 100) as progress_percentage,
                COALESCE(cp.completed_sections, 0) as completed_modules,
                (SELECT COUNT(*) FROM chapters ch JOIN sections s ON ch.section_id = s.id WHERE s.course_id = c.id) as total_modules,
                CASE
                    WHEN (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) >= (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id)
                    THEN CONCAT('All Sections Completed (', (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1), '/', (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id), ')')
                    ELSE COALESCE((SELECT s.title FROM sections s LEFT JOIN text_progress tp ON s.id = tp.section_id AND tp.student_id = u.id AND tp.completed = 1 WHERE s.course_id = c.id AND tp.section_id IS NULL ORDER BY s.order_index ASC LIMIT 1), (SELECT s.title FROM sections s WHERE s.course_id = c.id ORDER BY s.order_index ASC LIMIT 1))
                END as current_section,
                (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) as chapters_completed,
                COALESCE(cp.last_accessed_at, e.enrolled_at) as last_activity,
                e.enrolled_at as enrollment_date
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN course_progress cp ON u.id = cp.student_id AND c.id = cp.course_id
            WHERE u.role = 'student'
            AND u.status = 'active'
            AND c.teacher_id = ?
            $whereClause
            $orderBy";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$_SESSION['user_id']], $params));

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get summary statistics for progress report
 */
function getProgressSummaryStats($pdo, $courseFilter, $progressFilter) {
    $whereConditions = [];
    $params = [];

    // Course filter
    if ($courseFilter !== 'all') {
        $whereConditions[] = "c.id = ?";
        $params[] = $courseFilter;
    }

    // Progress filter
    switch ($progressFilter) {
        case 'completed':
            $whereConditions[] = "(SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) = (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id)";
            break;
        case 'in_progress':
            $whereConditions[] = "(SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) > 0 AND (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) < (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id)";
            break;
        case 'not_started':
            $whereConditions[] = "(SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) = 0";
            break;
    }

    $whereClause = !empty($whereConditions) ? "AND " . implode(" AND ", $whereConditions) : "";

    $sql = "SELECT
                COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) = (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id) THEN u.id END) as completed_students,
                COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) > 0 AND (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) < (SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id) THEN u.id END) as in_progress_students,
                COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) = 0 THEN u.id END) as not_started_students,
                COUNT(DISTINCT u.id) as total_students,
                AVG(ROUND((SELECT COUNT(*) FROM text_progress tp JOIN sections s ON tp.section_id = s.id WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1) / NULLIF((SELECT COUNT(*) FROM chapters ch JOIN sections s2 ON ch.section_id = s2.id WHERE s2.course_id = c.id), 0) * 100)) as average_progress,
                COUNT(DISTINCT c.id) as total_courses
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN course_progress cp ON u.id = cp.student_id AND c.id = cp.course_id
            WHERE u.role = 'student'
            AND u.status = 'active'
            AND c.teacher_id = ?
            $whereClause";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$_SESSION['user_id']], $params));
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'completed_students' => (int)($stats['completed_students'] ?? 0),
        'in_progress_students' => (int)($stats['in_progress_students'] ?? 0),
        'not_started_students' => (int)($stats['not_started_students'] ?? 0),
        'total_students' => (int)($stats['total_students'] ?? 0),
        'average_progress' => round((float)($stats['average_progress'] ?? 0), 1),
        'total_courses' => (int)($stats['total_courses'] ?? 0)
    ];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracking - Japanese Learning Platform</title>
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
    <link href="../css/settings-teacher.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/student_management.css">
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
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: true }">
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
                                class="nav-link w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-700 hover:bg-gray-100 transition-colors">
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
                               class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Progress Tracking
                            </a>
                        <?php endif; ?>
                            
                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['quiz_performance', 'filter_search', 'export_pdf_quiz', 'average_score', 'total_attempts', 'active_students_quiz', 'total_quiz_students', 'performance_trend', 'quiz_difficulty_analysis', 'top_performer', 'recent_quiz_attempt'])): ?>
                            <a href="quiz_performance.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
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
                    <h1 class="text-2xl font-semibold text-gray-900">Progress Tracking</h1>
                    <div class="text-sm text-gray-500">
                        <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                        <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <div class="main-content p-6">

            <!-- Filter Controls -->
            <div class="bg-white rounded-xl shadow-lg mb-8 border border-gray-100">
                <div class="p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <select id="progress-filter" class="filter-select bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-green-200">
                                <option value="all">All Progress</option>
                                <option value="completed">Completed (100%)</option>
                                <option value="in_progress">In Progress (1-99%)</option>
                                <option value="not_started">Not Started (0%)</option>
                            </select>
                            <select id="sort-filter" class="filter-select bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-lg px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-purple-200">
                                <option value="progress_desc">Progress (High to Low)</option>
                                <option value="progress_asc">Progress (Low to High)</option>
                                <option value="name_asc">Name (A-Z)</option>
                                <option value="activity_desc">Last Activity</option>
                            </select>
                        </div>
                        <div class="flex gap-3">
                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'export_progress')): ?>
                            <button id="export-progress-btn" class="export-btn bg-gradient-to-r from-primary-500 to-primary-600 text-white px-4 py-2 rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all duration-200 shadow-md hover:shadow-lg flex items-center">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Export to PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'complete_modules')): ?>
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl shadow-lg p-6 border border-green-100 hover:shadow-xl transition-all duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-500 text-white mr-4 shadow">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-green-700" id="completed-modules">-</h3>
                            <p class="text-sm text-gray-600">Completed Modules</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'in_progress')): ?>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl shadow-lg p-6 border border-yellow-100 hover:shadow-xl transition-all duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-400 text-white mr-4 shadow">
                            <i data-lucide="clock" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-yellow-700" id="in-progress-modules">-</h3>
                            <p class="text-sm text-gray-600">In Progress</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'average_progress')): ?>
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg p-6 border border-blue-100 hover:shadow-xl transition-all duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-500 text-white mr-4 shadow">
                            <i data-lucide="trending-up" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-blue-700" id="avg-progress">-</h3>
                            <p class="text-sm text-gray-600">Average Progress</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'active_students')): ?>
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl shadow-lg p-6 border border-purple-100 hover:shadow-xl transition-all duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-500 text-white mr-4 shadow">
                            <i data-lucide="users" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-purple-700" id="active-students">-</h3>
                            <p class="text-sm text-gray-600">Active Students</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Progress Chart and Details -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Progress Chart -->
                 <?php if (hasPermission($pdo, $_SESSION['user_id'], 'progress_distribution')): ?>
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg border border-blue-100">
                    <div class="p-6 border-b border-blue-100">
                        <h2 class="text-xl font-bold text-blue-900 flex items-center">
                            <i data-lucide="bar-chart-3" class="w-5 h-5 mr-2 text-emerald-600"></i>
                            Progress Distribution
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="chart-container">
                            <canvas id="progressChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Module Completion Chart -->
                <?php if (hasPermission($pdo, $_SESSION['user_id'], 'module_completion')): ?>
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl shadow-lg border border-purple-100">
                    <div class="p-6 border-b border-purple-100">
                        <h2 class="text-xl font-bold text-purple-900 flex items-center">
                            <i data-lucide="pie-chart" class="w-5 h-5 mr-2 text-emerald-600"></i>
                            Module Completion
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="chart-container">
                            <canvas id="moduleChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

<!-- Detailed Progress Table -->
<?php if (hasPermission($pdo, $_SESSION['user_id'], 'detailed_progress_tracking')): ?>
<div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 mb-8">
    <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-blue-50">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                <i data-lucide="list" class="w-5 h-5 mr-2 text-emerald-600"></i>
                Detailed Progress Tracking
            </h2>
            <!-- Search Filter -->
            <div class="relative max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                </div>
                <input 
                    type="text" 
                    id="progress-search" 
                    placeholder="Search by student name or module..." 
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white shadow-sm"
                />
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="student-table w-full">
            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                <tr>
                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">Student</th>
                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">Module</th>
                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">Current Section</th>
                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">Overall Progress</th>
                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">Chapters Completed</th>
                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Activity</th>
                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="progress-table-body" class="bg-white divide-y divide-gray-100">
                <!-- Progress data will be loaded via JavaScript -->
                <tr>
                    <td colspan="7" class="text-center py-12">
                        <div class="loading-spinner mx-auto"></div>
                        <p class="text-gray-500 mt-2">Loading progress data...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-100 bg-gradient-to-r from-gray-50 to-blue-50">
                    <div id="progress-pagination-container"></div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- Progress Detail Modal -->
    <div id="progress-detail-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
        <div class="modal-overlay absolute inset-0"></div>
        <div class="relative z-10 w-full max-w-5xl max-h-[90vh] p-4">
            <div class="modal-content w-full h-full overflow-y-auto">
                <div id="progress-detail-content">
                    <!-- Modal content will be loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <script src="js/student_management.js"></script>
    <script src="js/progress_tracking.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
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
        
        // Load data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadProgressData();
            loadCourseFilter();
            initializeProgressCharts();
            initializeProgressEventListeners();
            
            // Initialize real-time clock
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
            
            // Initialize real-time section updates
            setInterval(function() {
                if (typeof updateCurrentSections === 'function') {
                    updateCurrentSections();
                }
            }, 30000); // Update every 30 seconds
        });
    </script>
    <script src="../js/session_timeout.js"></script>
</body>
</html>
