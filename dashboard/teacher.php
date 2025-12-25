<?php
session_start();

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}

require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'performance_monitoring_functions.php';
require_once 'system_uptime_tracker.php';

// After loading database.php and before main logic
require_once '../includes/session_validator.php';
$sessionValidator = new SessionValidator($pdo);

if (!$sessionValidator->isSessionValid($_SESSION['user_id'])) {
    $sessionValidator->forceLogout('Your account access has been restricted.');
}


// Manual performance logging for teacher dashboard
if (defined('ENABLE_PERFORMANCE_MONITORING') && ENABLE_PERFORMANCE_MONITORING === true) {
    $start_time = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    
    // Register shutdown function to log performance
    register_shutdown_function(function() use ($start_time) {
        try {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            $status = $duration <= 3.0 ? 'fast' : ($duration <= 10.0 ? 'slow' : 'timeout');
            
            $sql = "
                INSERT INTO page_performance_log (
                    page_name, action_name, full_url, start_time, end_time, 
                    load_duration, status, user_id, session_id, ip_address, 
                    user_agent, device_type, browser, os
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $GLOBALS['pdo']->prepare($sql);
            $stmt->execute([
                'Teacher Dashboard',
                'Page Load',
                $_SERVER['REQUEST_URI'] ?? '',
                date('Y-m-d H:i:s', (int) $start_time),
                date('Y-m-d H:i:s', (int) $end_time),
                round($duration, 3),
                $status,
                $_SESSION['user_id'] ?? null,
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                'Desktop',
                'Chrome',
                'Windows'
            ]);
        } catch (Exception $e) {
            error_log("Teacher performance logging failed: " . $e->getMessage());
        }
    });
}

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Check if teacher needs to change password (first time login)
$stmt = $pdo->prepare("SELECT is_first_login FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user && $user['is_first_login']) {
    header('Location: change_password.php');
    exit();
}
require_once 'Teacher Notification/teacher_notifications.php';
require_once 'includes/teacher_profile_functions.php';

// Get all user permissions to check for hybrid status
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$all_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if teacher has any admin permissions (making them hybrid)
$admin_permissions = ['nav_user_management', 'nav_reports', 'nav_payments', 'nav_course_management', 'nav_content_management', 'nav_users'];
$user_admin_permissions = array_intersect($all_permissions, $admin_permissions);
$is_hybrid = !empty($user_admin_permissions);

// Debug logging
error_log("Teacher ID: " . $_SESSION['user_id']);
error_log("All Permissions: " . print_r($all_permissions, true));
error_log("Admin Permissions Found: " . print_r($user_admin_permissions, true));
error_log("Is Hybrid: " . ($is_hybrid ? 'true' : 'false'));

// If user is hybrid, redirect to hybrid dashboard
if ($is_hybrid) {
    header("Location: hybrid_teacher.php");
    exit();
}

// Store permissions in session for regular teachers
$_SESSION['is_hybrid'] = false;
$_SESSION['permissions'] = $all_permissions;

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Initialize teacher notification system
$teacherNotificationSystem = null;
try {
    $teacherNotificationSystem = initializeTeacherNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
} catch (Exception $e) {
    error_log("Teacher notification system initialization error: " . $e->getMessage());
}

// Handle AJAX requests for teacher notifications
if (isset($_GET['ajax'])) {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['ajax']) {
            case 'teacher_notification_count':
                $count = $teacherNotificationSystem ? $teacherNotificationSystem->getNotificationCount() : 0;
                echo json_encode(['count' => $count]);
                exit();
                
            case 'teacher_notifications':
                if ($teacherNotificationSystem) {
                    $notifications = $teacherNotificationSystem->getNotifications();
                    echo json_encode([
                        'notifications' => $notifications,
                        'count' => count($notifications),
                        'last_updated' => date('g:i A')
                    ]);
                } else {
                    echo json_encode([
                        'notifications' => [],
                        'count' => 0,
                        'last_updated' => date('g:i A'),
                        'error' => 'Notification system not initialized'
                    ]);
                }
                exit();
                
            case 'teacher_notification_stats':
                if ($teacherNotificationSystem) {
                    $stats = [
                        'total' => $teacherNotificationSystem->getNotificationCount(),
                        'categories' => []
                    ];
                    $notifications = $teacherNotificationSystem->getNotifications();
                    foreach ($notifications as $notification) {
                        $category = $notification['category'];
                        if (!isset($stats['categories'][$category])) {
                            $stats['categories'][$category] = 0;
                        }
                        $stats['categories'][$category]++;
                    }
                    echo json_encode($stats);
                } else {
                    echo json_encode(['total' => 0, 'categories' => []]);
                }
                exit();
                
            case 'mark_notification_read':
                $notification_id = $_GET['id'] ?? '';
                if ($notification_id && $teacherNotificationSystem) {
                    $success = $teacherNotificationSystem->markNotificationAsRead($notification_id);
                    echo json_encode(['success' => $success]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Notification ID required or system not initialized']);
                }
                exit();
                
            case 'mark_all_notifications_read':
                if ($teacherNotificationSystem) {
                    $count = $teacherNotificationSystem->markAllNotificationsAsRead();
                    echo json_encode(['success' => true, 'count' => $count]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Notification system not initialized']);
                }
                exit();
                
            case 'create_sample_notifications':
                if ($teacherNotificationSystem) {
                    $count = $teacherNotificationSystem->createSampleNotifications();
                    echo json_encode(['success' => true, 'created' => $count, 'message' => "Created {$count} sample notifications"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Notification system not initialized']);
                }
                exit();
                
            default:
                echo json_encode(['error' => 'Invalid AJAX request']);
                exit();
        }
    } catch (Exception $e) {
        error_log("AJAX request error: " . $e->getMessage());
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
        exit();
    }
}

// Fetch active courses for this teacher
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? AND is_archived = 0 ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$active_courses = $stmt->fetchAll();

// Note: Archived courses section has been replaced with student progress visualization

// Fetch recent audit trail
$stmt = $pdo->prepare("
    SELECT at.*, c.title as course_title, u.username 
    FROM audit_trail at 
    JOIN courses c ON at.course_id = c.id 
    JOIN users u ON at.user_id = u.id 
    WHERE c.teacher_id = ? 
    ORDER BY at.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_activities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Japanese Learning Platform</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    <link href="css/settings-teacher.css" rel="stylesheet">
    <?php echo $teacherNotificationSystem->renderNotificationAssets(); ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            overflow: hidden; /* prevent double page scroll; inner main handles scrolling */
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
        /* Teacher notification bell animation */
        @keyframes teacherNotificationPulse {
            0%, 100% { 
                transform: scale(1); 
                opacity: 1; 
            }
            50% { 
                transform: scale(1.15); 
                opacity: 0.8; 
            }
        }
        .teacher-notification-bell.animate {
            animation: teacherNotificationPulse 0.6s ease-in-out;
        }
        header {
            overflow: visible !important;
        }
        
        /* Student Names Tooltip Styles */
        .student-names-tooltip {
            z-index: 9999 !important;
            transform: translateY(5px);
        }
        
        .group:hover .student-names-tooltip {
            transform: translateY(0);
        }
        
        /* Prevent table overflow but allow tooltips */
        .analytics-table-container {
            overflow: visible;
            position: relative;
        }
        
        .analytics-table {
            position: relative;
        }
        
        /* Table column widths */
        .analytics-table {
            width: 100%;
            table-layout: fixed;
        }
        
        .analytics-table th:nth-child(1), 
        .analytics-table td:nth-child(1) { width: 25%; }
        
        .analytics-table th:nth-child(2), 
        .analytics-table td:nth-child(2) { 
            width: 20%; 
            min-width: 180px;
            overflow: visible;
            white-space: normal;
            word-wrap: break-word;
        }
        
        .analytics-table th:nth-child(3), 
        .analytics-table td:nth-child(3) { width: 12%; }
        
        .analytics-table th:nth-child(4), 
        .analytics-table td:nth-child(4) { width: 18%; }
        
        .analytics-table th:nth-child(5), 
        .analytics-table td:nth-child(5) { width: 12%; }
        
        .analytics-table th:nth-child(6), 
        .analytics-table td:nth-child(6) { width: 13%; }
        
        /* Ensure table body allows overflow for tooltips */
        .analytics-table tbody tr td {
            overflow: visible !important;
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .analytics-table th:nth-child(2), 
            .analytics-table td:nth-child(2) {
                width: 25%;
                min-width: 150px;
            }
            
            .analytics-table button {
                font-size: 10px;
                padding: 1px 6px;
            }
        }
        
        @media (max-width: 640px) {
            .analytics-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        /* Smooth hover transitions */
        .group:hover .absolute {
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        }
        
        /* Student list button styling */
        .analytics-table button {
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%);
            border: 1px solid #0288d1;
            padding: 2px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .analytics-table button:hover {
            background: linear-gradient(135deg, #b3e5fc 0%, #81d4fa 100%);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(2, 136, 209, 0.2);
        }
        
        /* Ensure tooltips appear above everything */
        .student-names-tooltip {
            position: fixed !important;
            z-index: 99999 !important;
        }
        /* Force Logout Modal Styles - Compact Rectangle Design */
.force-logout-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(5px);
    z-index: 999999;
    animation: fadeIn 0.3s ease-in-out;
}

.force-logout-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.9);
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 550px;
    width: 90%;
    z-index: 1000000;
    animation: modalSlideIn 0.4s ease-out forwards;
    overflow: hidden;
    display: flex;
    flex-direction: row;
}

.force-logout-header {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 30px 25px;
    text-align: center;
    flex-shrink: 0;
    width: 140px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.force-logout-icon {
    font-size: 48px;
    margin-bottom: 10px;
    animation: shake 0.5s ease-in-out;
}

.force-logout-title {
    font-size: 16px;
    font-weight: bold;
    margin: 0;
    line-height: 1.3;
}

.force-logout-content {
    flex: 1;
    padding: 25px 30px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.force-logout-message {
    font-size: 15px;
    color: #374151;
    line-height: 1.6;
    margin: 0 0 20px 0;
}

.force-logout-footer {
    display: flex;
    align-items: center;
    gap: 15px;
}

.force-logout-countdown {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fef2f2;
    border: 2px solid #dc2626;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    font-size: 20px;
    font-weight: bold;
    color: #dc2626;
    flex-shrink: 0;
    animation: pulse 1s ease-in-out infinite;
}

.force-logout-button {
    flex: 1;
    padding: 10px 20px;
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.force-logout-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 12px rgba(220, 38, 38, 0.3);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalSlideIn {
    from {
        transform: translate(-50%, -50%) scale(0.9);
        opacity: 0;
    }
    to {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
    }
}

@keyframes shake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-10deg); }
    75% { transform: rotate(10deg); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Mobile Responsive */
@media (max-width: 600px) {
    .force-logout-modal {
        flex-direction: column;
        max-width: 90%;
    }
    
    .force-logout-header {
        width: 100%;
        padding: 20px;
        flex-direction: row;
        gap: 15px;
    }
    
    .force-logout-icon {
        font-size: 36px;
        margin-bottom: 0;
    }
    
    .force-logout-title {
        text-align: left;
    }
    
    .force-logout-content {
        padding: 20px;
    }
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
                       class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
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
        <div class="flex-1 ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
                    <div class="flex items-center gap-4">
                        <!-- Teacher Notification Bell -->
                        <?php echo $teacherNotificationSystem->renderNotificationBell('Teacher Notifications'); ?>
                        <div class="text-sm text-gray-500">
                            <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                            <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Areas -->
            <main class="p-6 overflow-y-auto" style="height: calc(100vh - 80px);">
                <!-- Dashboard Content -->
                <div id="dashboard-content" class="content-area active">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_dashboard_view_active_modules')): ?>
                        <div class="bg-gradient-to-br from-red-50 to-pink-100 overflow-hidden shadow-lg rounded-xl border border-red-100 hover:shadow-xl transition-all duration-300 hover:scale-105">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-semibold text-red-700 truncate">Active Modules</dt>
                                            <dd class="flex items-baseline mt-1">
                                                <div class="text-3xl font-bold text-red-800"><?php echo count($active_courses); ?></div>
                                                <span class="ml-2 text-sm font-medium text-red-600">modules</span>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm text-red-600">
                                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                        <span class="font-medium">Currently active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_dashboard_view_active_students')): ?>
                        <div class="bg-gradient-to-br from-purple-50 to-indigo-100 overflow-hidden shadow-lg rounded-xl border border-purple-100 hover:shadow-xl transition-all duration-300 hover:scale-105">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-semibold text-purple-700 truncate">Active Students</dt>
                                            <dd class="flex items-baseline mt-1">
                                                <div class="text-3xl font-bold text-purple-800">
                                                    <?php
                                                    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = ?");
                                                    $stmt->execute([$_SESSION['user_id']]);
                                                    echo $stmt->fetchColumn();
                                                    ?>
                                                </div>
                                                <span class="ml-2 text-sm font-medium text-purple-600">enrolled</span>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm text-purple-600">
                                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <span class="font-medium">Currently learning</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_dashboard_view_completion_rate')): ?>
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
                                            <dt class="text-sm font-semibold text-blue-700 truncate">Module Completion Rate</dt>
                                            <dd class="flex items-baseline mt-1">
                                                <div class="text-3xl font-bold text-blue-800">
                                                    <?php
                                                    // Professional Module Completion Rate Calculation
                                                    // Option A: Overall completion rate (system efficiency)
                                                    // Formula: (Total completed lessons / Total assigned lessons) × 100
                                                    // Capped at 100% maximum, rounded to 1 decimal place
                                                    
                                                    $stmt = $pdo->prepare("
                                                        SELECT 
                                                            LEAST(100.0, ROUND(
                                                                (COUNT(
                                                                    CASE 
                                                                        WHEN cp.completion_percentage = 100 
                                                                        OR cp.completion_status = 'completed'
                                                                        THEN 1 
                                                                    END
                                                                ) / 
                                                                NULLIF(
                                                                    COUNT(DISTINCT e.student_id) * (
                                                                        SELECT COUNT(ch.id) 
                                                                        FROM chapters ch 
                                                                        JOIN sections s ON ch.section_id = s.id 
                                                                        WHERE s.course_id = c.id
                                                                    ), 0
                                                                )) * 100, 1
                                                            )) as overall_completion_rate
                                                        FROM enrollments e
                                                        JOIN courses c ON e.course_id = c.id
                                                        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
                                                        WHERE c.teacher_id = ?
                                                    ");
                                                    $stmt->execute([$_SESSION['user_id']]);
                                                    $overall_completion_rate = $stmt->fetchColumn();
                                                    
                                                    // Option B: Average student completion rate (fairness metric)
                                                    $stmt = $pdo->prepare("
                                                        SELECT 
                                                            LEAST(100.0, ROUND(
                                                                AVG(
                                                                    CASE 
                                                                        WHEN cp.completion_percentage IS NOT NULL 
                                                                        THEN cp.completion_percentage
                                                                        ELSE 0 
                                                                    END
                                                                ), 1
                                                            )) as avg_student_completion_rate
                                                        FROM enrollments e
                                                        JOIN courses c ON e.course_id = c.id
                                                        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
                                                        WHERE c.teacher_id = ?
                                                    ");
                                                    $stmt->execute([$_SESSION['user_id']]);
                                                    $avg_student_completion_rate = $stmt->fetchColumn();
                                                    
                                                    // Display overall completion rate (Option A - system efficiency)
                                                    echo number_format((float)($overall_completion_rate ?: 0), 1);
                                                    ?>%
                                                </div>
                                                <span class="ml-2 text-sm font-medium text-blue-600">completion</span>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm text-blue-600">
                                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="font-medium">Students finishing courses</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_dashboard_view_published_modules')): ?>
                        <div class="bg-gradient-to-br from-green-50 to-emerald-100 overflow-hidden shadow-lg rounded-xl border border-green-100 hover:shadow-xl transition-all duration-300 hover:scale-105">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-semibold text-green-700 truncate">Published Modules</dt>
                                            <dd class="flex items-baseline mt-1">
                                                <div class="text-3xl font-bold text-green-800">
                                                    <?php
                                                    $stmt = $pdo->prepare("
                                                        SELECT COUNT(*) 
                                                        FROM courses 
                                                        WHERE teacher_id = ? 
                                                        AND is_published = 1
                                                    ");
                                                    $stmt->execute([$_SESSION['user_id']]);
                                                    echo $stmt->fetchColumn();
                                                    ?>
                                                </div>
                                                <span class="ml-2 text-sm font-medium text-green-600">live</span>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center text-sm text-green-600">
                                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="font-medium">Available to students</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions and Archived Courses -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_dashboard_view_quick_actions')): ?>
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                            <div class="p-6 bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-gray-100">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-white rounded-lg shadow-sm">
                                        <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <a href="teacher_create_module.php" class="group flex items-center p-4 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl border border-purple-100 hover:border-purple-200 hover:shadow-md transition-all duration-200">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center shadow-md group-hover:scale-110 transition-transform duration-200">
                                                <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-purple-700 group-hover:text-purple-800">Create New Module</div>
                                            <div class="text-xs text-purple-600">Build engaging content for students</div>
                                        </div>
                                        <div class="ml-auto">
                                            <svg class="w-4 h-4 text-purple-400 group-hover:text-purple-600 group-hover:translate-x-1 transition-all duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                    <a href="Student Management/completion_reports.php" class="group flex items-center p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl border border-blue-100 hover:border-blue-200 hover:shadow-md transition-all duration-200">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg flex items-center justify-center shadow-md group-hover:scale-110 transition-transform duration-200">
                                                <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-blue-700 group-hover:text-blue-800">View Reports</div>
                                            <div class="text-xs text-blue-600">Analyze student performance data</div>
                                        </div>
                                        <div class="ml-auto">
                                            <svg class="w-4 h-4 text-blue-400 group-hover:text-blue-600 group-hover:translate-x-1 transition-all duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                    <a href="Student Management/student_profiles.php" class="group flex items-center p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-100 hover:border-green-200 hover:shadow-md transition-all duration-200">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center shadow-md group-hover:scale-110 transition-transform duration-200">
                                                <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-green-700 group-hover:text-green-800">Manage Students</div>
                                            <div class="text-xs text-green-600">Track progress and engagement</div>
                                        </div>
                                        <div class="ml-auto">
                                            <svg class="w-4 h-4 text-green-400 group-hover:text-green-600 group-hover:translate-x-1 transition-all duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_dashboard_view_learning_analytics')): ?>
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                            <div class="p-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="p-2 bg-white rounded-lg shadow-sm">
                                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-900">Learning Progress Analytics</h3>
                                    </div>
                                    <a href="Student Management/progress_tracking.php" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-600 bg-white rounded-lg hover:bg-indigo-50 hover:text-indigo-700 transition-colors duration-200 shadow-sm border border-indigo-200">
                                        View Details
                                        <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div class="p-6">
                                <!-- Progress Chart Container -->
                                <div class="h-80">
                                    <canvas id="studentProgressChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Student Analytics Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Student Progress Overview -->
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'student_progress_overview')): ?>
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                            <div class="p-6 bg-gradient-to-r from-primary-50 to-pink-50 border-b border-gray-100">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-3">
                                        <div class="p-2 bg-white rounded-lg shadow-sm">
                                            <svg class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-900">Student Progress Overview</h3>
                                    </div>
                                    <a href="Student Management/progress_tracking.php" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-primary-600 bg-white rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors duration-200 shadow-sm border border-primary-200">
                                        View All
                                        <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <?php
                                    // Get top 5 students with weighted overall progress calculation
                                    $stmt = $pdo->prepare("
                                        SELECT 
                                            u.username, 
                                            u.email,
                                            COUNT(DISTINCT e.course_id) as enrolled_courses,
                                            -- Professional student progress calculation (Option B: Average per student)
                                            -- Formula: Average completion percentage across all courses for each student
                                            -- Capped at 100% maximum, rounded to 1 decimal place
                                            LEAST(100.0, ROUND(
                                                AVG(
                                                    CASE 
                                                        WHEN cp.completion_percentage IS NOT NULL 
                                                        THEN cp.completion_percentage
                                                        ELSE 0 
                                                    END
                                                ), 1
                                            )) as overall_progress_pct,
                                            COALESCE(sp.profile_picture, '') as profile_picture
                                        FROM users u
                                        JOIN enrollments e ON u.id = e.student_id
                                        JOIN courses c ON e.course_id = c.id
                                        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
                                        LEFT JOIN student_preferences sp ON u.id = sp.student_id
                                        WHERE c.teacher_id = ? AND u.role = 'student'
                                        GROUP BY u.id, u.username, u.email, sp.profile_picture
                                        ORDER BY overall_progress_pct DESC, enrolled_courses DESC
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $top_students = $stmt->fetchAll();
                                    ?>
                                    <?php foreach ($top_students as $student): ?>
                                    <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-blue-50 rounded-xl border border-gray-100 hover:border-primary-200 hover:shadow-md transition-all duration-200">
                                        <div class="flex items-center space-x-3">
                                            <?php if (!empty($student['profile_picture'])): ?>
                                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                                     class="w-10 h-10 rounded-full object-cover shadow-md" 
                                                     alt="Profile Picture"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="w-10 h-10 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md" style="display: none;">
                                                    <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-10 h-10 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                                    <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($student['username']); ?></div>
                                                <div class="text-xs text-gray-600 flex items-center space-x-1">
                                                    <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                    </svg>
                                                    <span><?php echo $student['enrolled_courses']; ?> courses enrolled</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-semibold text-green-600 mb-1">Active</div>
                                            <div class="w-24 bg-gray-200 rounded-full h-2.5 shadow-inner">
                                                <div class="bg-gradient-to-r from-primary-500 to-primary-600 h-2.5 rounded-full shadow-sm transition-all duration-500 ease-out" style="width: <?php echo number_format((float)($student['overall_progress_pct'] ?: 0), 1); ?>%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1"><?php echo number_format((float)($student['overall_progress_pct'] ?: 0), 1); ?>%</div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($top_students)): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <div class="bg-gradient-to-br from-gray-100 to-gray-200 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                                            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-600">No students enrolled yet</p>
                                        <p class="text-xs text-gray-400 mt-1">Students will appear here once they enroll in your courses</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Student Activity -->
                        <?php if (hasPermission($pdo, $_SESSION['user_id'], 'teacher_dashboard_view_recent_activities')): ?>
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                            <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <div class="p-1.5 bg-white rounded-lg shadow-sm">
                                            <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base font-semibold text-gray-900">Recent Student Activity</h3>
                                    </div>
                                    <a href="Student Management/engagement_monitoring.php" class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-600 bg-white rounded-md hover:bg-green-50 hover:text-green-700 transition-colors duration-200 shadow-sm border border-green-200">
                                        View All
                                        <svg class="ml-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="space-y-3">
                                    <?php
                                    // Get recent enrollments
                                    $stmt = $pdo->prepare("
                                        SELECT u.username, c.title as course_title, e.enrolled_at, COALESCE(sp.profile_picture, '') as profile_picture
                                        FROM enrollments e
                                        JOIN users u ON e.student_id = u.id
                                        JOIN courses c ON e.course_id = c.id
                                        LEFT JOIN student_preferences sp ON u.id = sp.student_id
                                        WHERE c.teacher_id = ?
                                        ORDER BY e.enrolled_at DESC
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $recent_enrollments = $stmt->fetchAll();
                                    ?>
                                    <?php foreach ($recent_enrollments as $enrollment): ?>
                                    <div class="flex items-start space-x-3 p-3 bg-gradient-to-r from-white to-green-50 rounded-lg border border-gray-100 hover:border-green-200 hover:shadow-sm transition-all duration-200">
                                        <div class="flex-shrink-0">
                                            <?php if (!empty($enrollment['profile_picture'])): ?>
                                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($enrollment['profile_picture']); ?>" 
                                                     class="w-8 h-8 rounded-full object-cover shadow-md" 
                                                     alt="Profile Picture"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-md" style="display: none;">
                                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                                    </svg>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-md">
                                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($enrollment['username']); ?></p>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    New
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-600 mt-0.5">
                                                <span class="text-gray-500">Enrolled in</span>
                                                <span class="font-medium text-gray-800">"<?php echo htmlspecialchars($enrollment['course_title']); ?>"</span>
                                            </p>
                                            <div class="flex items-center mt-1 text-xs text-gray-400">
                                                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <?php echo date('M j, Y • g:i A', strtotime($enrollment['enrolled_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_enrollments)): ?>
                                    <div class="text-center py-6 text-gray-500">
                                        <div class="bg-gradient-to-br from-gray-100 to-gray-200 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                                            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-600">No recent activity</p>
                                        <p class="text-xs text-gray-400 mt-1">Student enrollments and activities will appear here</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Course Performance Analytics -->
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'module_performance_analytics')): ?>
                    <div class="bg-white shadow-lg rounded-xl overflow-hidden mb-8 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-white rounded-lg shadow-sm">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Module Performance Analytics</h3>
                                </div>
                                <a href="Student Management/completion_reports.php" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-white rounded-lg hover:bg-blue-50 hover:text-blue-700 transition-colors duration-200 shadow-sm border border-blue-200">
                                    View Detailed Reports
                                    <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="analytics-table-container overflow-x-auto">
                                <table class="w-full analytics-table" style="table-layout: fixed;">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 rounded-tl-lg">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                    </svg>
                                                    <span>Module</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                    </svg>
                                                    <span>Students</span>
                                                    <span class="text-xs text-gray-400 normal-case">(hover for all)</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                                    </svg>
                                                    <span>Enrollment</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Module Performance</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Status</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 rounded-tr-lg">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Module Status</span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        <?php
                                        // Get course analytics with separate Activity and Module Performance calculations
                                        $stmt = $pdo->prepare("
                                            SELECT c.id, c.title, c.is_published,
                                                   COUNT(DISTINCT e.student_id) as student_count,
                                                   GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') as student_names,
                                                   -- Activity calculation: percentage of students with activity in last 30 days
                                                   LEAST(100.0, ROUND(
                                                       (COUNT(DISTINCT CASE 
                                                           WHEN cat.user_id IS NOT NULL 
                                                           THEN e.student_id 
                                                           END) * 100.0) / 
                                                       NULLIF(COUNT(DISTINCT e.student_id), 0), 1
                                                   )) as activity_pct,
                                                   -- Professional Module Performance calculation
                                                   -- Formula: (Completed lessons / Total assigned lessons) × 100
                                                   -- Uses binary completion flag: completion_percentage = 100 OR completion_status = 'completed'
                                                   -- Capped at 100% maximum, rounded to 1 decimal place
                                                   LEAST(100.0, ROUND(
                                                       (COUNT(
                                                           CASE 
                                                               WHEN cp.completion_percentage = 100 
                                                               OR cp.completion_status = 'completed'
                                                               THEN 1 
                                                           END
                                                       ) / 
                                                       NULLIF(
                                                           COUNT(DISTINCT e.student_id) * (
                                                               SELECT COUNT(ch.id) 
                                                               FROM chapters ch 
                                                               JOIN sections s ON ch.section_id = s.id 
                                                               WHERE s.course_id = c.id
                                                           ), 0
                                                       )) * 100, 1
                                                   )) as module_performance_pct
                                            FROM courses c
                                            LEFT JOIN enrollments e ON c.id = e.course_id
                                            LEFT JOIN users u ON e.student_id = u.id
                                            LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
                                            LEFT JOIN comprehensive_audit_trail cat ON e.student_id = cat.user_id 
                                                AND cat.resource_type = 'Course' 
                                                AND cat.resource_id LIKE CONCAT('%', c.id, '%')
                                                AND cat.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                            WHERE c.teacher_id = ? AND c.is_archived = 0
                                            GROUP BY c.id, c.title, c.is_published
                                            ORDER BY student_count DESC
                                            LIMIT 5
                                        ");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $course_analytics = $stmt->fetchAll();
                                        ?>
                                        <?php foreach ($course_analytics as $index => $course): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50 transition-all duration-200 <?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                                        <?php echo strtoupper(substr($course['title'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($course['title']); ?></div>
                                                        <div class="text-xs text-gray-500">Course ID: <?php echo $course['id']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4" style="min-width: 250px;">
                                                <div class="text-sm text-gray-900">
                                                    <?php 
                                                    if ($course['student_names']) {
                                                        $all_names = explode(', ', $course['student_names']);
                                                        $total_count = count($all_names);
                                                        
                                                        if ($total_count <= 2) {
                                                            // Show all names if 2 or fewer
                                                            echo '<div class="w-full">';
                                                            echo htmlspecialchars($course['student_names']);
                                                            echo '</div>';
                                                        } else {
                                                            // Show first name + "and X more" on new line
                                                            $first_name = $all_names[0];
                                                            $remaining_count = $total_count - 1;
                                                            
                                                            echo '<div class="w-full">';
                                                            echo '<div>' . htmlspecialchars($first_name) . '</div>';
                                                            echo '<button class="mt-1" onclick="showAllStudents(\'' . htmlspecialchars(json_encode($all_names), ENT_QUOTES) . '\', ' . count($all_names) . ')" title="Click to see all ' . $total_count . ' students">';
                                                            echo '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">';
                                                            echo '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>';
                                                            echo '</svg>';
                                                            echo '+' . $remaining_count . ' more';
                                                            echo '</button>';
                                                            echo '</div>';
                                                        }
                                                    } else {
                                                        echo '<div class="text-gray-500 italic">No students</div>';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center space-x-2">
                                                    <div class="text-lg font-bold text-gray-900"><?php echo $course['student_count']; ?></div>
                                                    <div class="text-xs text-gray-500">enrolled</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-20 bg-gray-200 rounded-full h-2.5 shadow-inner">
                                                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2.5 rounded-full transition-all duration-500 ease-out" style="width: <?php echo number_format((float)($course['module_performance_pct'] ?: 0), 1); ?>%"></div>
                                                    </div>
                                                    <span class="text-xs text-gray-500"><?php echo number_format((float)($course['module_performance_pct'] ?: 0), 1); ?>%</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center space-x-2">
                                                    <?php if ($course['student_count'] > 0): ?>
                                                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                                        <span class="text-sm font-medium text-green-700">Active</span>
                                                    <?php else: ?>
                                                        <div class="w-2 h-2 bg-gray-300 rounded-full"></div>
                                                        <span class="text-sm text-gray-500">No Students</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($course['is_published']): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200">
                                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Published
                                                </span>
                                                <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-yellow-100 to-orange-100 text-yellow-800 border border-yellow-200">
                                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Draft
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($course_analytics)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
                                                <div class="bg-gradient-to-br from-gray-100 to-gray-200 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                                                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                    </svg>
                                                </div>
                                                <p class="text-sm font-medium text-gray-600">No modules available</p>
                                                <p class="text-xs text-gray-400 mt-1">Create your first module to see analytics here</p>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>


            </main>
        </div>
    </div>
    <!-- Force Logout Modal HTML - Compact Rectangle Design -->
<div id="forceLogoutOverlay" class="force-logout-overlay">
    <div class="force-logout-modal">
        <div class="force-logout-header">
            <div class="force-logout-icon">⚠️</div>
            <h2 class="force-logout-title">Session Terminated</h2>
        </div>
        <div class="force-logout-content">
            <p class="force-logout-message" id="forceLogoutMessage">
                Your account has been banned. Please contact support for more information.
            </p>
            <div class="force-logout-footer">
                <div class="force-logout-countdown" id="forceLogoutCountdown">8</div>
                <button class="force-logout-button" onclick="window.location.href='/dashboard/login.php'">
                    Go to Login Now
                </button>
            </div>
        </div>
    </div>
</div>


    <script src="js/settings-teacher.js"></script>
    <script src="Teacher Notification/teacher_notifications.js"></script>
    <script src="js/password_reset_notification.js"></script>
    <script>
        // Initialize password reset notification for teacher
        document.addEventListener('DOMContentLoaded', function() {
            const passwordResetNotification = new PasswordResetNotification('teacher', 'settings-teacher.php');
            passwordResetNotification.init();
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

        // Debug function to test AJAX endpoints
        window.testAjaxEndpoint = function(endpoint) {
            console.log(`Testing endpoint: ${endpoint}`);
            fetch(`teacher.php?ajax=${endpoint}`)
                .then(response => {
                    console.log(`Response status: ${response.status}`);
                    console.log(`Response headers:`, response.headers);
                    return response.text();
                })
                .then(text => {
                    console.log(`Raw response:`, text);
                    try {
                        const json = JSON.parse(text);
                        console.log(`Parsed JSON:`, json);
                    } catch (e) {
                        console.log(`JSON parse error:`, e);
                        console.log(`First 200 chars:`, text.substring(0, 200));
                    }
                })
                .catch(error => console.log(`Fetch error:`, error));
        };

        // Initialize the page with dashboard content
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize real-time clock
            updateRealTimeClock();
            setInterval(updateRealTimeClock, 1000); // Update every second
            
            // Initialize Student Progress Chart
            initializeProgressChart();
            
            // Initialize student tooltips for analytics table
            initializeStudentTooltips();
            
            // Initialize teacher notification system with auto-refresh
            if (typeof TeacherNotificationManager !== 'undefined') {
                const notificationManager = new TeacherNotificationManager();
                notificationManager.init();
                
                // Start auto-refresh every 2 minutes
                setInterval(() => {
                    notificationManager.refreshNotifications().catch(error => {
                        console.log("Auto-refresh error:", error);
                    });
                }, 120000); // 2 minutes
                
                // Immediate refresh on page load
                setTimeout(() => {
                    notificationManager.refreshNotifications().catch(error => {
                        console.log("Initial refresh error:", error);
                    });
                }, 1000);
            } else {
                console.log("TeacherNotificationManager not available, using basic notification system");
            }
            
            // Enhanced notification bell click handler
            window.toggleTeacherNotifications = function() {
                console.log("Enhanced toggleTeacherNotifications called");
                const dropdown = document.getElementById("teacherNotificationDropdown");
                if (dropdown) {
                    const isVisible = dropdown.classList.contains("show");
                    if (isVisible) {
                        dropdown.classList.remove("show");
                    } else {
                        // Refresh notifications when opening dropdown
                        if (typeof TeacherNotificationManager !== 'undefined') {
                            const manager = new TeacherNotificationManager();
                            manager.refreshNotifications().then(() => {
                                dropdown.classList.add("show");
                            }).catch(error => {
                                console.log("Error refreshing notifications:", error);
                                dropdown.classList.add("show");
                            });
                        } else {
                            dropdown.classList.add("show");
                        }
                    }
                }
            };
            
            // Auto-update notification count in real-time
            function updateNotificationCount() {
                fetch('teacher.php?ajax=teacher_notification_count', {
                    method: 'GET',
                    cache: 'no-cache',
                    credentials: 'same-origin'
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            throw new Error('Response is not JSON');
                        }
                        return response.json();
                    })
                    .then(data => {
                        const countElement = document.getElementById("teacherNotificationCount");
                        if (countElement && data.count !== undefined) {
                            const currentCount = parseInt(countElement.textContent) || 0;
                            const newCount = data.count;
                            
                            countElement.textContent = newCount;
                            
                            // Add pulse animation for new notifications
                            if (newCount > currentCount && newCount > 0) {
                                countElement.classList.add("animate-pulse");
                                // Show notification bell animation
                                const bell = document.querySelector(".teacher-notification-bell");
                                if (bell) {
                                    bell.style.animation = "teacherNotificationPulse 0.5s ease-in-out 3";
                                }
                            } else if (newCount === 0) {
                                countElement.classList.remove("animate-pulse");
                            }
                            
                            // Update visibility
                            countElement.style.display = newCount > 0 ? 'flex' : 'none';
                        }
                    })
                    .catch(error => {
                        console.log("Notification count update error:", error);
                        // Fallback: hide count element on error
                        const countElement = document.getElementById("teacherNotificationCount");
                        if (countElement) {
                            countElement.style.display = 'none';
                        }
                    });
            }
            
            // Update count every 30 seconds
            setInterval(updateNotificationCount, 30000);
            
            // Initial count update
            setTimeout(updateNotificationCount, 500);
        });
        
        // Global function for refreshing notifications
        window.refreshTeacherNotifications = function() {
            console.log("Manual refresh triggered");
            if (typeof TeacherNotificationManager !== 'undefined') {
                const manager = new TeacherNotificationManager();
                manager.refreshNotifications().catch(error => {
                    console.log("Error in manual refresh:", error);
                    // Try updating just the count instead of full refresh
                    updateNotificationCount();
                });
            } else {
                console.log("TeacherNotificationManager not found, trying basic count update");
                updateNotificationCount();
            }
        };
        
        // Enhanced notification click handler
        window.handleTeacherNotificationClick = function(url) {
            if (url && url !== "#") {
                window.location.href = url;
            }
            // Close dropdown after click
            const dropdown = document.getElementById("teacherNotificationDropdown");
            if (dropdown) {
                dropdown.classList.remove("show");
            }
        };
        
        // Initialize Student Progress Chart
        function initializeProgressChart() {
            const ctx = document.getElementById('studentProgressChart');
            if (!ctx) return;
            
            // Get progress data from PHP variables using professional completion calculations
            const progressData = {
                completed: <?php 
                    // Count students who have completed ALL their assigned courses (100% completion)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT e.student_id) as completed_students
                        FROM enrollments e
                        JOIN courses c ON e.course_id = c.id
                        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
                        WHERE c.teacher_id = ?
                        AND e.student_id NOT IN (
                            SELECT DISTINCT e2.student_id
                            FROM enrollments e2
                            JOIN courses c2 ON e2.course_id = c2.id
                            LEFT JOIN course_progress cp2 ON e2.course_id = cp2.course_id AND e2.student_id = cp2.student_id
                            WHERE c2.teacher_id = ? 
                            AND (cp2.completion_percentage IS NULL OR cp2.completion_percentage < 100)
                        )
                    ");
                    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                    echo $stmt->fetchColumn() ?: 0;
                ?>,
                active: <?php 
                    // Count students who have made progress (completion_percentage > 0) but not finished all courses
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT e.student_id) as active_students
                        FROM enrollments e
                        JOIN courses c ON e.course_id = c.id
                        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
                        WHERE c.teacher_id = ?
                        AND cp.completion_percentage > 0 
                        AND cp.completion_percentage < 100
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    echo $stmt->fetchColumn() ?: 0;
                ?>,
                total: <?php 
                    // Total enrolled students
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT e.student_id) as total_students
                        FROM enrollments e
                        JOIN courses c ON e.course_id = c.id
                        WHERE c.teacher_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    echo $stmt->fetchColumn() ?: 0;
                ?>
            };
            
            // Create the chart
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'Not Started'],
                    datasets: [{
                        data: [
                            progressData.completed,
                            progressData.active - progressData.completed,
                            Math.max(0, progressData.total - progressData.active)
                        ],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',   // Green for completed
                            'rgba(59, 130, 246, 0.8)',  // Blue for in progress
                            'rgba(156, 163, 175, 0.8)'  // Gray for not started
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(156, 163, 175, 1)'
                        ],
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12,
                                    family: 'Inter, sans-serif'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} students (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%',
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }
        
        // Show all students function
        function showAllStudents(studentsJson, count) {
            try {
                const students = JSON.parse(studentsJson);
                
                let studentList = '';
                students.forEach((name, index) => {
                    studentList += `
                        <div class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-lg transition-colors">
                            <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                ${name.charAt(0).toUpperCase()}
                            </div>
                            <span class="text-gray-700 font-medium">${name}</span>
                        </div>
                    `;
                });
                
                Swal.fire({
                    title: `<div class="flex items-center justify-center space-x-2">
                                <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>All Students (${count})</span>
                            </div>`,
                    html: `
                        <div class="text-left max-h-96 overflow-y-auto">
                            <div class="mb-4 text-sm text-gray-600 text-center">
                                Complete list of enrolled students
                            </div>
                            ${studentList}
                        </div>
                    `,
                    width: '500px',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'rounded-xl shadow-2xl',
                        title: 'text-lg font-semibold text-gray-800',
                        closeButton: 'text-gray-400 hover:text-gray-600'
                    },
                    background: '#ffffff',
                    backdrop: 'rgba(0,0,0,0.4)'
                });
            } catch (error) {
                console.error('Error parsing student data:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Unable to display student list'
                });
            }
        }
        
        // Enhanced tooltip positioning for student names
        function initializeStudentTooltips() {
            const tooltipElements = document.querySelectorAll('.group');
            
            tooltipElements.forEach(element => {
                const tooltip = element.querySelector('.absolute');
                if (!tooltip) return;
                
                element.addEventListener('mouseenter', () => {
                    const rect = element.getBoundingClientRect();
                    const tooltipRect = tooltip.getBoundingClientRect();
                    const viewport = {
                        width: window.innerWidth,
                        height: window.innerHeight
                    };
                    
                    // Adjust position if tooltip goes off-screen
                    if (rect.left + tooltipRect.width > viewport.width - 20) {
                        tooltip.style.left = 'auto';
                        tooltip.style.right = '0';
                        tooltip.style.transform = 'none';
                    }
                    
                    // Adjust vertical position if needed
                    if (rect.bottom + tooltipRect.height > viewport.height - 20) {
                        tooltip.style.top = 'auto';
                        tooltip.style.bottom = '100%';
                        tooltip.style.marginBottom = '8px';
                        tooltip.style.marginTop = '0';
                    }
                });
                
                element.addEventListener('mouseleave', () => {
                    // Reset positioning
                    tooltip.style.left = '0';
                    tooltip.style.right = 'auto';
                    tooltip.style.top = 'full';
                    tooltip.style.bottom = 'auto';
                    tooltip.style.transform = '';
                    tooltip.style.marginTop = '8px';
                    tooltip.style.marginBottom = '0';
                });
            });
        }
    </script>

<script>
(function() {
    // Configuration
    const CHECK_INTERVAL = 3000; // Check every 3 seconds
    const API_ENDPOINT = '../includes/check_session_status.php';
    const COUNTDOWN_SECONDS = 8; // Countdown before auto-redirect
    
    let isCheckingSession = false;
    let countdownInterval = null;
    
    // Function to show modern logout modal
    function showForceLogoutModal(reason) {
        const overlay = document.getElementById('forceLogoutOverlay');
        const messageEl = document.getElementById('forceLogoutMessage');
        const countdownEl = document.getElementById('forceLogoutCountdown');
        
        // Set the reason message
        messageEl.textContent = reason || 'Your session has been terminated.';
        
        // Show the modal
        overlay.style.display = 'block';
        
        // Start countdown
        let secondsLeft = COUNTDOWN_SECONDS;
        countdownEl.textContent = secondsLeft;
        
        countdownInterval = setInterval(() => {
            secondsLeft--;
            countdownEl.textContent = secondsLeft;
            
            if (secondsLeft <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '/AIToManabi_Updated/dashboard/login.php';
            }
        }, 1000);
    }
    
    // Function to check session status
    function checkSessionStatus() {
        if (isCheckingSession) return;
        isCheckingSession = true;
        
        fetch(API_ENDPOINT, {
            method: 'GET',
            cache: 'no-cache',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.valid) {
                console.log('Session invalidated:', data.reason);
                showForceLogoutModal(data.reason);
            }
        })
        .catch(error => {
            console.error('Session check error:', error);
        })
        .finally(() => {
            isCheckingSession = false;
        });
    }
    
    // Start checking when page loads
    console.log('🔒 Auto force-logout checker started (checking every ' + (CHECK_INTERVAL/1000) + ' seconds)');
    
    // Initial check after 2 seconds
    setTimeout(checkSessionStatus, 2000);
    
    // Regular interval checks
    setInterval(checkSessionStatus, CHECK_INTERVAL);
    
    // Also check on user activity
    let activityTimer;
    function onUserActivity() {
        clearTimeout(activityTimer);
        activityTimer = setTimeout(checkSessionStatus, 1000);
    }
    
    document.addEventListener('click', onUserActivity);
    document.addEventListener('mousemove', onUserActivity);
    document.addEventListener('keypress', onUserActivity);
})();
</script>

<!-- Session Timeout Manager -->
<script src="js/session_timeout.js"></script>

</body>
</html>
