<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'includes/teacher_profile_functions.php';

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Check if teacher has hybrid permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
$stmt->execute([$_SESSION['user_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
$is_hybrid = !empty($permissions);

// Fetch all draft courses for the current teacher
$draft_sql = "SELECT 
    c.*, 
    cc.name as course_category_name,
    cat.name as category_name,
    (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as section_count,
    (SELECT COUNT(*) FROM chapters ch JOIN sections s ON ch.section_id = s.id WHERE s.course_id = c.id) as chapter_count
FROM courses c
LEFT JOIN course_category cc ON c.course_category_id = cc.id
LEFT JOIN categories cat ON c.category_id = cat.id
WHERE c.teacher_id = ? AND c.is_published = 0 AND c.is_archived = 0
ORDER BY c.created_at DESC";

try {
    $stmt = $pdo->prepare($draft_sql);
    $stmt->execute([$_SESSION['user_id']]);
    $draft_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching draft courses: " . $e->getMessage());
    $draft_courses = [];
}

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Handle AJAX requests for draft operations
if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendJsonResponse(false, 'Invalid JSON input', null, 400);
        }
        
        $action = $input['action'] ?? '';
        $course_id = isset($input['course_id']) ? (int)$input['course_id'] : 0;
        
        if (!$course_id) {
            sendJsonResponse(false, 'Course ID is required', null, 400);
        }
        
        // Verify the course belongs to the current teacher
        $stmt = $pdo->prepare("SELECT id, title, status FROM courses WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$course_id, $_SESSION['user_id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            sendJsonResponse(false, 'Course not found or you do not have permission to modify it', null, 404);
        }
        
        switch ($action) {
            case 'publish':
                // Publish the draft course
                $stmt = $pdo->prepare("
                    UPDATE courses 
                    SET status = 'published', 
                        is_published = 1, 
                        published_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    sendJsonResponse(true, 'Course published successfully', [
                        'course_id' => $course_id,
                        'title' => $course['title']
                    ]);
                } else {
                    sendJsonResponse(false, 'Failed to publish course', null, 500);
                }
                break;
                
            case 'unpublish':
                // Unpublish the course
                $stmt = $pdo->prepare("
                    UPDATE courses 
                    SET status = 'draft', 
                        is_published = 0, 
                        updated_at = NOW()
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    sendJsonResponse(true, 'Course unpublished successfully', [
                        'course_id' => $course_id,
                        'title' => $course['title']
                    ]);
                } else {
                    sendJsonResponse(false, 'Failed to unpublish course', null, 500);
                }
                break;
                
            case 'archive':
                // Archive the draft course
                $stmt = $pdo->prepare("UPDATE courses SET is_archived = 1, archived_at = NOW(), status = 'archived' WHERE id = ? AND teacher_id = ? AND is_published = 0");
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    sendJsonResponse(true, 'Draft course archived successfully', [
                        'course_id' => $course_id,
                        'title' => $course['title']
                    ]);
                } else {
                    sendJsonResponse(false, 'Failed to archive draft course', null, 500);
                }
                break;
                
            case 'delete':
                // Delete the draft course (permanent deletion)
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ? AND teacher_id = ? AND is_published = 0");
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    sendJsonResponse(true, 'Draft course deleted successfully', [
                        'course_id' => $course_id,
                        'title' => $course['title']
                    ]);
                } else {
                    sendJsonResponse(false, 'Failed to delete draft course', null, 500);
                }
                break;
                
            case 'duplicate':
                // Duplicate the draft course
                $pdo->beginTransaction();
                
                try {
                    // Get the original course data
                    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
                    $stmt->execute([$course_id, $_SESSION['user_id']]);
                    $original_course = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$original_course) {
                        throw new Exception('Original course not found');
                    }
                    
                    // Create duplicate course
                    $stmt = $pdo->prepare("
                        INSERT INTO courses (
                            title, description, category_id, course_category_id, price, 
                            status, teacher_id, is_published, image_path, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, 'draft', ?, 0, ?, NOW(), NOW()
                        )
                    ");
                    $stmt->execute([
                        $original_course['title'] . ' (Copy)',
                        $original_course['description'],
                        $original_course['category_id'],
                        $original_course['course_category_id'],
                        $original_course['price'],
                        $_SESSION['user_id'],
                        $original_course['image_path']
                    ]);
                    
                    $new_course_id = $pdo->lastInsertId();
                    
                    // Duplicate sections
                    $stmt = $pdo->prepare("
                        INSERT INTO sections (course_id, title, description, order_index)
                        SELECT ?, title, description, order_index
                        FROM sections 
                        WHERE course_id = ?
                    ");
                    $stmt->execute([$new_course_id, $course_id]);
                    
                    // Duplicate chapters
                    $stmt = $pdo->prepare("
                        INSERT INTO chapters (section_id, title, content_type, content, video_url, video_type, video_file_path, order_index, created_at, updated_at)
                        SELECT 
                            (SELECT s2.id FROM sections s2 WHERE s2.course_id = ? AND s2.order_index = s1.order_index),
                            c1.title, c1.content_type, c1.content, c1.video_url, c1.video_type, c1.video_file_path, c1.order_index, NOW(), NOW()
                        FROM chapters c1
                        JOIN sections s1 ON c1.section_id = s1.id
                        WHERE s1.course_id = ?
                    ");
                    $stmt->execute([$new_course_id, $course_id]);
                    
                    // Duplicate quizzes
                    $stmt = $pdo->prepare("
                        INSERT INTO quizzes (section_id, title, description, passing_score, total_points, order_index, created_at, updated_at)
                        SELECT 
                            (SELECT s2.id FROM sections s2 WHERE s2.course_id = ? AND s2.order_index = s1.order_index),
                            q1.title, q1.description, q1.passing_score, q1.total_points, q1.order_index, NOW(), NOW()
                        FROM quizzes q1
                        JOIN sections s1 ON q1.section_id = s1.id
                        WHERE s1.course_id = ?
                    ");
                    $stmt->execute([$new_course_id, $course_id]);
                    
                    $pdo->commit();
                    
                    sendJsonResponse(true, 'Course duplicated successfully', [
                        'new_course_id' => $new_course_id,
                        'title' => $original_course['title'] . ' (Copy)'
                    ]);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            default:
                sendJsonResponse(false, 'Invalid action', null, 400);
                break;
        }
        
    } catch (Exception $e) {
        error_log("Draft management error: " . $e->getMessage());
        sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), null, 500);
    }
} else {
    // Display the HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Drafts - Teacher Dashboard</title>
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
                        sans: ['Inter', 'Noto Sans JP', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/teacher_drafts.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Noto Sans JP', sans-serif; background-color: #f3f4f6; min-height: 100vh; }
        .main-content { margin-left: 16rem; min-height: calc(100vh - 4rem); padding: 1.5rem; }
        [x-cloak] { display: none !important; }
        .nav-link { transition: all 0.2s ease-in-out; }
        .nav-link:hover { background-color: #f3f4f6; }
        .nav-link.active { background-color: #fff1f2; color: #be123c; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-red-600 to-red-800 text-white">
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
                       class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
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
        <div class="flex-1 ml-4">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-0">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900 ml-64">My Draft Modules</h1>
                    <div class="text-sm text-gray-500">
                        <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                        <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Header Section -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 mb-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-yellow-500 to-orange-500 p-3 rounded-xl shadow-lg">
                                <i class="fas fa-file-alt text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-3xl font-bold bg-gradient-to-r from-yellow-600 to-orange-600 bg-clip-text text-transparent">
                                    My Draft Modules
                                </h2>
                                <p class="text-gray-600 mt-1">Manage your unpublished module drafts</p>
                            </div>
                        </div>

                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['create_new_draft'])): ?>
                        <a href="teacher_create_module.php" 
                           class="bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                            <i class="fas fa-plus mr-2"></i> Create New Draft
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Drafts Grid -->
                <?php if (!empty($draft_courses)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php foreach ($draft_courses as $draft): ?>
                            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl hover:scale-105 transition-all duration-300 border border-gray-100 relative">
                                <!-- Status Indicator - Circular Dot -->
                                <div class="absolute top-3 left-3 w-3 h-3 rounded-full bg-orange-500 z-10 shadow-lg border-2 border-white"></div>
                                
                                <div class="relative h-48 overflow-hidden">
                                    <img 
                                        src="<?php echo !empty($draft['image_path']) ? '../uploads/course_images/' . htmlspecialchars($draft['image_path']) : '../assets/images/default-course.jpg'; ?>" 
                                        alt="<?php echo htmlspecialchars($draft['title']); ?>"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                    >
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                                    <?php if ($draft['price'] > 0): ?>
                                        <div class="absolute top-4 right-4">
                                            <span class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 py-1 rounded-full text-sm font-bold shadow-lg">
                                                ₱<?php echo number_format($draft['price'], 2); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="absolute top-4 right-4">
                                            <span class="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-3 py-1 rounded-full text-sm font-bold shadow-lg">
                                                FREE
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-6">
                                    <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-primary-600 transition-colors duration-300">
                                        <?php echo htmlspecialchars($draft['title']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-3 leading-relaxed">
                                        <?php echo strip_tags($draft['description'] ?? 'Course description not available.'); ?>
                                    </p>
                                    
                                    <div class="flex items-center justify-between mb-6 p-3 bg-gray-50 rounded-xl">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-layer-group text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <span class="text-gray-700 text-sm font-medium block">
                                                    <?php echo htmlspecialchars($draft['course_category_name'] ?? 'Uncategorized'); ?>
                                                </span>
                                                <div class="text-gray-500 text-xs mt-1">
                                                    <?php echo $draft['section_count']; ?> sections • <?php echo $draft['chapter_count']; ?> chapters
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['edit_modules'])): ?>
                                        <a href="teacher_course_editor.php?id=<?php echo $draft['id']; ?>" 
                                           class="flex-1 text-center bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-300 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </a>
                                        <?php endif; ?>
                                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['published_modules'])): ?>
                                        <button onclick="publishDraft(<?php echo $draft['id']; ?>, '<?php echo htmlspecialchars($draft['title']); ?>')" 
                                                class="flex-1 text-center bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-xl hover:from-green-600 hover:to-emerald-600 transition-all duration-300 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                            <i class="fas fa-check mr-1"></i> Publish
                                        </button>
                                        <?php endif; ?>
                                        <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['archived_modules'])): ?>
                                        <button onclick="archiveDraft(<?php echo $draft['id']; ?>, '<?php echo htmlspecialchars($draft['title']); ?>')" 
                                                class="flex-1 text-center bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-2 rounded-xl hover:from-gray-600 hover:to-gray-700 transition-all duration-300 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                            <i class="fas fa-archive mr-1"></i> Archive
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Bottom accent -->
                                <div class="h-1 bg-gradient-to-r from-yellow-400 via-orange-400 to-red-400"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16 bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl shadow-inner">
                        <div class="max-w-md mx-auto">
                            <div class="w-24 h-24 bg-gradient-to-r from-yellow-100 to-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-file-alt text-4xl text-yellow-500"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No draft modules found</h3>
                            <p class="text-gray-600 mb-6">You haven't created any draft modules yet. Start creating your first course!</p>
                            <a href="teacher_create_module.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Create Your First Module
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for Draft Management -->
    <script>
        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });
        
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
    </script>
    <script>
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
    <script src="js/teacher_draft.js"></script>
    <!-- Session Timeout Manager -->
<script src="js/session_timeout.js"></script>
</body>
</html>
<?php
}
?>
