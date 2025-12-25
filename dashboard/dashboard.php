<?php
session_start();
require_once '../config/database.php';
require_once 'real_time_activity_logger.php';
require_once 'includes/placement_test_functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

// After loading database.php and before main logic
require_once '../includes/session_validator.php';
$sessionValidator = new SessionValidator($pdo);

if (!$sessionValidator->isSessionValid($_SESSION['user_id'])) {
    $sessionValidator->forceLogout('Your account access has been restricted.');
}


// Check if student needs to take placement test
if (needsPlacementTest($pdo, $_SESSION['user_id'])) {
    $redirectUrl = getPlacementTestRedirectUrl($pdo, '');
    if ($redirectUrl) {
        header('Location: ' . $redirectUrl);
        exit();
    }
}

// Log student dashboard access
try {
    $logger = new RealTimeActivityLogger($pdo);
    $logger->logPageView('student_dashboard', [
        'page_title' => 'Student Dashboard',
        'access_time' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log("Activity logging error: " . $e->getMessage());
}

try {
    // Get student's name and email (same pattern as other pages)
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();

    // Get student preferences for display name and profile picture
    $stmt = $pdo->prepare("SELECT display_name, profile_picture FROM student_preferences WHERE student_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);

    // Merge preferences with student data
    if ($preferences) {
        $student['display_name'] = $preferences['display_name'] ?? '';
        $student['profile_picture'] = $preferences['profile_picture'] ?? '';
    } else {
        $student['display_name'] = '';
        $student['profile_picture'] = '';
    }

    // Get student statistics
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(DISTINCT e.id) FROM enrollments e WHERE e.student_id = ?) as enrolled_courses,
            (SELECT COUNT(DISTINCT e.course_id) 
             FROM enrollments e 
             JOIN courses c ON e.course_id = c.id
             LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
             WHERE e.student_id = ? 
             AND cp.completion_status = 'completed') as completed_courses
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Merge stats with student data
    $student['enrolled_courses'] = $stats['enrolled_courses'] ?? 0;
    $student['completed_courses'] = $stats['completed_courses'] ?? 0;

    if (!$student) {
        throw new Exception("Student not found");
    }

    // Get recent courses (excluding archived courses)
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            cat.name as category_name,
            u.username as teacher_name,
            COALESCE(tp.display_name, u.username) as teacher_display_name,
            COALESCE(tp.profile_visible, 1) as teacher_profile_visible,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_count,
            (SELECT COUNT(*) FROM sections WHERE course_id = c.id) as total_sections,
            CASE WHEN e.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
            COALESCE(cp.completion_percentage, 0) as completion_percentage,
            COALESCE(cp.completion_status, 'not_started') as progress_status
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN teacher_preferences tp ON u.id = tp.teacher_id
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE c.status = 'published' AND c.is_archived = 0
        ORDER BY COALESCE(c.sort_order, 999999), c.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get enrolled courses with progress (excluding archived courses)
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            cat.name as category_name,
            u.username as teacher_name,
            COALESCE(tp.display_name, u.username) as teacher_display_name,
            COALESCE(tp.profile_visible, 1) as teacher_profile_visible,
            e.enrolled_at,
            (SELECT COUNT(*) FROM sections WHERE course_id = c.id) as total_sections,
            COALESCE(cp.completed_sections, 0) as completed_sections,
            COALESCE(cp.completion_percentage, 0) as completion_percentage,
            COALESCE(cp.completion_status, 'not_started') as progress_status
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN teacher_preferences tp ON u.id = tp.teacher_id
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE e.student_id = ? AND c.is_archived = 0
        ORDER BY 
            CASE WHEN e.enrolled_at > DATE_SUB(NOW(), INTERVAL 7 DAY) 
                THEN 0 ELSE 1 END,
            e.enrolled_at DESC
        LIMIT 6
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add realtime chapter-based progress calculation for recent courses
    foreach ($recent_courses as &$course) {
        if ($course['is_enrolled']) {
            // Get total chapters count for this course
            $stmt_total = $pdo->prepare("
                SELECT COUNT(c.id) as total_chapters
                FROM chapters c
                INNER JOIN sections s ON c.section_id = s.id
                WHERE s.course_id = ?
            ");
            $stmt_total->execute([$course['id']]);
            $total_chapters = $stmt_total->fetch(PDO::FETCH_ASSOC)['total_chapters'] ?? 0;
            
            if ($total_chapters > 0) {
                // Get completed chapters count (both video and text)
                $stmt_completed = $pdo->prepare("
                    SELECT COUNT(DISTINCT c.id) as completed_chapters
                    FROM chapters c
                    INNER JOIN sections s ON c.section_id = s.id
                    LEFT JOIN video_progress vp ON c.id = vp.chapter_id AND vp.student_id = ? AND vp.completed = 1
                    LEFT JOIN text_progress tp ON c.id = tp.chapter_id AND tp.student_id = ? AND tp.completed = 1
                    WHERE s.course_id = ? 
                    AND (
                        (c.content_type = 'video' AND vp.completed = 1) OR 
                        (c.content_type = 'text' AND tp.completed = 1)
                    )
                ");
                $stmt_completed->execute([$_SESSION['user_id'], $_SESSION['user_id'], $course['id']]);
                $completed_chapters = $stmt_completed->fetch(PDO::FETCH_ASSOC)['completed_chapters'] ?? 0;
                
                // Calculate chapter-based progress
                $course['completed_chapters'] = (int)$completed_chapters;
                $course['total_chapters'] = (int)$total_chapters;
                $course['completion_percentage'] = round(($completed_chapters / $total_chapters) * 100, 2);
                
                // Update status based on percentage
                if ($course['completion_percentage'] >= 100) {
                    $course['progress_status'] = 'completed';
                } elseif ($course['completion_percentage'] > 0) {
                    $course['progress_status'] = 'in_progress';
                } else {
                    $course['progress_status'] = 'not_started';
                }
                
            // Update course_progress table with chapter-based values
            $stmt_update = $pdo->prepare("
                INSERT INTO course_progress (course_id, student_id, completed_sections, completion_percentage, completion_status)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    completed_sections = VALUES(completed_sections),
                    completion_percentage = VALUES(completion_percentage),
                    completion_status = VALUES(completion_status)
            ");
                $stmt_update->execute([
                    $course['id'], 
                    $_SESSION['user_id'], 
                    $completed_chapters, // Store completed chapters count for compatibility
                    $course['completion_percentage'], 
                    $course['progress_status']
                ]);
            } else {
                // No chapters found, set default values
                $course['completed_chapters'] = 0;
                $course['total_chapters'] = 0;
                $course['completion_percentage'] = 0;
                $course['progress_status'] = 'not_started';
            }
        } else {
            // Not enrolled, set default values
            $course['completed_chapters'] = 0;
            $course['total_chapters'] = 0;
            $course['completion_percentage'] = 0;
            $course['progress_status'] = 'not_started';
        }
    }
    unset($course); // Break the reference

    // Add realtime chapter-based progress calculation for enrolled courses
    foreach ($enrolled_courses as &$course) {
        // Get total chapters count for this course
        $stmt_total = $pdo->prepare("
            SELECT COUNT(c.id) as total_chapters
            FROM chapters c
            INNER JOIN sections s ON c.section_id = s.id
            WHERE s.course_id = ?
        ");
        $stmt_total->execute([$course['id']]);
        $total_chapters = $stmt_total->fetch(PDO::FETCH_ASSOC)['total_chapters'] ?? 0;
        
        if ($total_chapters > 0) {
            // Get completed chapters count (both video and text)
            $stmt_completed = $pdo->prepare("
                SELECT COUNT(DISTINCT c.id) as completed_chapters
                FROM chapters c
                INNER JOIN sections s ON c.section_id = s.id
                LEFT JOIN video_progress vp ON c.id = vp.chapter_id AND vp.student_id = ? AND vp.completed = 1
                LEFT JOIN text_progress tp ON c.id = tp.chapter_id AND tp.student_id = ? AND tp.completed = 1
                WHERE s.course_id = ? 
                AND (
                    (c.content_type = 'video' AND vp.completed = 1) OR 
                    (c.content_type = 'text' AND tp.completed = 1)
                )
            ");
            $stmt_completed->execute([$_SESSION['user_id'], $_SESSION['user_id'], $course['id']]);
            $completed_chapters = $stmt_completed->fetch(PDO::FETCH_ASSOC)['completed_chapters'] ?? 0;
            
            // Calculate chapter-based progress
            $course['completed_chapters'] = (int)$completed_chapters;
            $course['total_chapters'] = (int)$total_chapters;
            $course['completion_percentage'] = round(($completed_chapters / $total_chapters) * 100, 2);
            
            // Update status based on percentage
            if ($course['completion_percentage'] >= 100) {
                $course['progress_status'] = 'completed';
            } elseif ($course['completion_percentage'] > 0) {
                $course['progress_status'] = 'in_progress';
            } else {
                $course['progress_status'] = 'not_started';
            }
            
            // Update course_progress table with chapter-based values
            $stmt_update = $pdo->prepare("
                INSERT INTO course_progress (course_id, student_id, completed_sections, completion_percentage, completion_status)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    completed_sections = VALUES(completed_sections),
                    completion_percentage = VALUES(completion_percentage),
                    completion_status = VALUES(completion_status)
            ");
            $stmt_update->execute([
                $course['id'], 
                $_SESSION['user_id'], 
                $completed_chapters, // Store completed chapters count for compatibility
                $course['completion_percentage'], 
                $course['progress_status']
            ]);
        } else {
            // No chapters found, set default values
            $course['completed_chapters'] = 0;
            $course['total_chapters'] = 0;
            $course['completion_percentage'] = 0;
            $course['progress_status'] = 'not_started';
        }
    }
    unset($course); // Break the reference

    // Add debug logging
    error_log("Student ID: " . $_SESSION['user_id']);
    error_log("Enrolled courses count: " . $student['enrolled_courses']);
    error_log("Enrolled courses data: " . print_r($enrolled_courses, true));
    
    // Debug progress calculations
    foreach ($enrolled_courses as $course) {
        error_log("Course ID: " . $course['id'] . " - Progress: " . $course['completion_percentage'] . "% - Status: " . $course['progress_status'] . " - Completed: " . $course['completed_chapters'] . "/" . $course['total_chapters']);
    }
    
    // Debug image paths
    foreach ($enrolled_courses as $course) {
        error_log("Course ID: " . $course['id'] . " - Image path: " . ($course['image_path'] ?? 'NULL'));
    }
    foreach ($recent_courses as $course) {
        error_log("Recent Course ID: " . $course['id'] . " - Image path: " . ($course['image_path'] ?? 'NULL'));
    }

    // Add error logging
    if (empty($recent_courses)) {
        error_log("No recent courses found for user " . $_SESSION['user_id']);
    }
    if (empty($enrolled_courses)) {
        error_log("No enrolled courses found for user " . $_SESSION['user_id']);
    }

} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    // Set default values
    $student = [
        'username' => $_SESSION['username'] ?? 'Student',
        'enrolled_courses' => 0,
        'completed_courses' => 0
    ];
    $recent_courses = [];
    $enrolled_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard_modern.css">
    <link rel="stylesheet" href="css/calendar.css">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="js/dashboard_modern.js"></script>
    <script src="js/session_timeout.js"></script>
    <script src="js/calendar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#ef4444',
                        secondary: '#ef4444',
                        accent: '#ef4444',
                        dark: {
                            bg: '#18181b',
                            surface: '#27272a',
                            border: '#3f3f46'
                        }
                    },
                    fontFamily: {
                        'rubik': ['"Rubik"', 'sans-serif']
                    },
                    boxShadow: {
                        'japanese': '0 4px 20px rgba(239, 68, 68, 0.05)',
                        'japanese-hover': '0 8px 30px rgba(239, 68, 68, 0.1)'
                    }
                }
            }
        }
    </script>
    <style>
        .japanese-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(239, 68, 68, 0.1);
        }
        .dark .card-hover:hover {
            box-shadow: 0 8px 30px rgba(239, 68, 68, 0.2);
        }
        [x-cloak] { 
            display: none !important; 
        }
    </style>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('darkMode', {
                on: localStorage.getItem('darkMode') === 'true',
                toggle() {
                    this.on = !this.on;
                    localStorage.setItem('darkMode', this.on);
                }
            });

            // Initialize dark mode on page load
            if (localStorage.getItem('darkMode') === null) {
                Alpine.store('darkMode').on = window.matchMedia('(prefers-color-scheme: dark)').matches;
                localStorage.setItem('darkMode', Alpine.store('darkMode').on);
            }
        });
    </script>
    <script>
        // Mobile menu state
        document.addEventListener('alpine:init', () => {
            Alpine.data('mobileMenu', () => ({
                mobileMenuOpen: false,
                toggleMobileMenu() {
                    this.mobileMenuOpen = !this.mobileMenuOpen;
                },
                closeMobileMenu() {
                    this.mobileMenuOpen = false;
                }
            }));
        });
    </script>
</head>
    
<body class="min-h-screen font-rubik transition-colors duration-200" x-data="mobileMenu()">
    <!-- Abstract Background Shapes -->
    <div class="abstract-bg">
        <div class="abstract-shape shape-1"></div>
        <div class="abstract-shape shape-2"></div>
        <div class="abstract-shape shape-3"></div>
        <div class="abstract-shape shape-4"></div>
    </div>

    <!-- Navigation -->
    <nav class="modern-nav fixed top-0 left-0 right-0 z-50 transition-all duration-200">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex justify-between items-center w-full">
                    <!-- Logo - Always on the left -->
                    <div class="flex-shrink-0 flex items-center">
                        <a href="dashboard.php" class="text-2xl rubik-bold text-red-500 japanese-transition hover:text-red-600">
                            AiToManabi
                        </a>
                    </div>
                    
                    <!-- Desktop Navigation -->
                    <div class="hidden sm:flex sm:space-x-8">
                        <button onclick="window.location.href='dashboard.php'" 
                                class="border-red-500 text-red-500 japanese-transition hover:text-red-600 inline-flex items-center px-4 pt-1 border-b-2 text-sm rubik-medium focus:outline-none">
                            Dashboard
                        </button>
                        <button onclick="window.location.href='student_courses.php'" 
                                class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 inline-flex items-center px-4 pt-1 border-b-2 text-sm rubik-medium focus:outline-none">
                            Modules
                        </button>
                        <button onclick="window.location.href='my_learning.php'" 
                                class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 inline-flex items-center px-4 pt-1 border-b-2 text-sm rubik-medium focus:outline-none">
                            My Learning
                        </button>
                    </div>

                    <!-- Desktop Dark Mode Toggle and Profile -->
                    <div class="hidden sm:flex sm:items-center sm:space-x-4">
                        <!-- Dark Mode Toggle -->
                        <button 
                            @click="$store.darkMode.toggle()" 
                            class="p-2 rounded-full text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none"
                            :aria-label="$store.darkMode.on ? 'Disable dark mode' : 'Enable dark mode'"
                        >
                            <!-- Sun icon -->
                            <svg x-cloak x-show="$store.darkMode.on" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <!-- Moon icon -->
                            <svg x-cloak x-show="!$store.darkMode.on" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                        </button>

                        <!-- Profile Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" 
                                    class="flex items-center space-x-2 focus:outline-none">
                                <?php if (!empty($student['profile_picture'])): ?>
                                    <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                         class="w-10 h-10 rounded-full object-cover" 
                                         alt="Profile Picture">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white font-semibold">
                                        <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="text-gray-900 dark:text-white transition-colors duration-200">
                                    <?php echo htmlspecialchars(!empty($student['display_name']) ? $student['display_name'] : $student['username']); ?>
                                </span>
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" 
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div x-cloak x-show="open" 
                                 @click.away="open = false"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-48 bg-white dark:bg-dark-surface rounded-md shadow-lg py-1 z-50 transition-colors duration-200">
                                <a href="student_profile.php" 
                                   class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                                    Profile Settings
                                </a>
                                <a href="student_payment_history.php" 
                                   class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                                    Payment History
                                </a>
                                <hr class="my-1 border-gray-200 dark:border-gray-700">
                                <a href="logout.php" 
                                   class="block px-4 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile Menu Button - Always on the right -->
                    <div class="sm:hidden">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" 
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-500"
                                aria-expanded="false">
                            <span class="sr-only">Open main menu</span>
                            <!-- Hamburger icon -->
                            <svg x-cloak x-show="!mobileMenuOpen" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <!-- Close icon -->
                            <svg x-cloak x-show="mobileMenuOpen" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Menu Dropdown -->
            <div x-cloak x-show="mobileMenuOpen" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="sm:hidden">
                <div class="px-2 pt-2 pb-3 space-y-1 bg-white dark:bg-dark-surface border-t border-gray-200 dark:border-gray-700">
                    <!-- Navigation Links -->
                    <button onclick="mobileMenuOpen = false; window.location.href='dashboard.php'" 
                            class="border-red-500 text-red-500 japanese-transition hover:text-red-600 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        Dashboard
                    </button>
                    <button onclick="mobileMenuOpen = false; window.location.href='student_courses.php'" 
                            class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        Modules
                    </button>
                    <button onclick="mobileMenuOpen = false; window.location.href='my_learning.php'" 
                            class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        My Learning
                    </button>
                    
                    <!-- Divider -->
                    <hr class="my-2 border-gray-200 dark:border-gray-700">
                    
                    <!-- Profile Section -->
                    <div class="px-3 py-2">
                        <div class="flex items-center space-x-3 mb-3">
                            <?php if (!empty($student['profile_picture'])): ?>
                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                     class="w-10 h-10 rounded-full object-cover" 
                                     alt="Profile Picture">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white font-semibold">
                                    <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars(!empty($student['display_name']) ? $student['display_name'] : $student['username']); ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Student</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dark Mode Toggle -->
                    <button @click="$store.darkMode.toggle()" 
                            class="w-full text-left px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none flex items-center space-x-3">
                        <!-- Sun icon -->
                        <svg x-cloak x-show="$store.darkMode.on" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <!-- Moon icon -->
                        <svg x-cloak x-show="!$store.darkMode.on" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                        <span class="text-base font-medium">
                            <span x-show="!$store.darkMode.on">Enable Dark Mode</span>
                            <span x-show="$store.darkMode.on">Disable Dark Mode</span>
                        </span>
                    </button>
                    
                    <!-- Divider -->
                    <hr class="my-2 border-gray-200 dark:border-gray-700">
                    
                    <!-- Profile Actions -->
                    <a href="student_profile.php" 
                       @click="mobileMenuOpen = false"
                       class="block w-full text-left px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none">
                        Profile Settings
                    </a>
                    <a href="student_payment_history.php" 
                       @click="mobileMenuOpen = false"
                       class="block w-full text-left px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none">
                        Payment History
                    </a>
                    <a href="logout.php" 
                       @click="mobileMenuOpen = false"
                       class="block w-full text-left px-3 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-16 min-h-screen">
        <div class="dashboard-container">
            <!-- Stats Section -->
            <div class="stats-grid">
                <!-- Enrolled Modules -->
                <div class="stat-card enrolled">
                    <div class="stat-icon">
                        📖
                    </div>
                    <div class="stat-number"><?php echo (int)$student['enrolled_courses']; ?></div>
                    <h3 class="stat-title">Enrolled Modules</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm rubik-regular">Active learning paths</p>
                </div>

                <!-- Completed Modules -->
                <div class="stat-card completed">
                    <div class="stat-icon">
                        ✅
                    </div>
                    <div class="stat-number"><?php echo (int)$student['completed_courses']; ?></div>
                    <h3 class="stat-title">Completed Modules</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm rubik-regular">Successfully finished</p>
                </div>
            </div>

            <!-- Calendar Section -->
            <div class="calendar-section">
                <div class="calendar-container">
                    <div id="learning-calendar"></div>
                </div>
            </div>

            <!-- My Modules Section -->
            <div class="mb-8 mt-8">
                <div class="section-header">
                    <span class="section-icon">🎓</span>
                    <h2 class="section-title">My Enrolled Modules</h2>
                    <div class="section-actions">
                        <a href="my_learning.php" class="view-all-link rubik-semibold">View All</a>
                    </div>
                </div>
                
                <?php if (!empty($enrolled_courses)): ?>
                <div class="courses-grid">
                    <?php foreach ($enrolled_courses as $course): ?>
                    <div class="course-card" data-course-id="<?php echo $course['id']; ?>" data-status="<?php echo $course['progress_status']; ?>">
                        <div class="course-image">
                            <img src="<?php echo $course['image_path'] ? '../uploads/course_images/' . htmlspecialchars($course['image_path']) : '../uploads/course_images/default-course.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>"
                                 onerror="this.src='../uploads/course_images/default-course.jpg'">
                            <!-- Status Badge on Left -->
                            <div class="course-status-badge <?php echo str_replace('_', '-', $course['progress_status'] ?? 'not-started'); ?>">
                                <?php 
                                    $status = $course['progress_status'] ?? 'not_started';
                                    echo $status === 'completed' ? 'Completed' : 
                                         ($status === 'in_progress' ? 'In Progress' : 'Not Started');
                                ?>
                            </div>
                            <div class="course-level-badge <?php echo ($course['price'] > 0) ? 'paid' : 'free'; ?>">
                                <?php echo ($course['price'] > 0) ? 'Paid' : 'Free'; ?>
                            </div>
                        </div>
                        <div class="course-content">
                            <div class="course-category rubik-medium">
                                <?php echo htmlspecialchars($course['category_name'] ?? 'Japanese'); ?>
                            </div>
                            <h3 class="course-title rubik-bold">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h3>
                            <p class="course-teacher rubik-regular">
                                <?php 
                                    if ($course['teacher_profile_visible']) {
                                        echo 'By ' . htmlspecialchars($course['teacher_display_name'] ?? 'Instructor');
                                    }
                                ?>
                            </p>
                            <!-- Debug Info (remove after testing) -->
                            <?php if (isset($_GET['debug'])): ?>
                            <div style="background: #f0f0f0; padding: 5px; margin: 5px 0; font-size: 10px;">
                                DEBUG: Completed: <?php echo $course['completed_chapters']; ?> / Total: <?php echo $course['total_chapters']; ?> 
                                | Percentage: <?php echo $course['completion_percentage']; ?>% 
                                | Status: <?php echo $course['progress_status']; ?>
                            </div>
                            <?php endif; ?>
                            <div class="course-stats rubik-regular">
                                <span class="chapters-completed"><?php echo $course['completed_chapters'] ?? 0; ?> / <?php echo $course['total_chapters'] ?? 0; ?> chapters</span>
                                <span>Enrolled <?php echo date('M Y', strtotime($course['enrolled_at'] ?? 'now')); ?></span>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="progress-container">
                                <div class="progress-header">
                                    <span class="progress-label rubik-semibold">Progress</span>
                                    <span class="progress-percentage rubik-semibold">
                                        <?php 
                                            $percentage = round($course['completion_percentage'] ?? 0);
                                            echo $percentage . '%';
                                        ?>
                                    </span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" data-progress="<?php echo round($course['completion_percentage'] ?? 0); ?>"></div>
                                </div>
                            </div>
                            
                            <a href="continue_learning.php?id=<?php echo $course['id']; ?>" class="continue-btn rubik-semibold">
                                <?php 
                                    $percentage = $course['completion_percentage'] ?? 0;
                                    $status = $course['progress_status'] ?? 'not_started';
                                    
                                    if ($status === 'completed' || $percentage == 100) {
                                        echo 'Review Module';
                                    } elseif ($status === 'in_progress' || $percentage > 0) {
                                        echo 'Continue Learning';
                                    } else {
                                        echo 'Start Module';
                                    }
                                ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <h3 class="empty-title rubik-bold">No Enrolled Modules</h3>
                    <p class="empty-description rubik-regular">You haven't enrolled in any modules yet. Start your learning journey today!</p>
                    <a href="student_courses.php" class="empty-action rubik-semibold">
                        Browse Modules
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Modules Section -->
            <div>
                <div class="section-header">
                    <span class="section-icon">📚</span>
                    <h2 class="section-title">Recent Modules</h2>
                    <div class="section-actions">
                        <a href="student_courses.php" class="view-all-link rubik-semibold">View All</a>
                    </div>
                </div>
                <div class="courses-grid">
                    <?php foreach ($recent_courses as $course): ?>
                    <div class="course-card" data-course-id="<?php echo $course['id']; ?>">
                        <div class="course-image">
                            <img src="<?php echo $course['image_path'] ? '../uploads/course_images/' . htmlspecialchars($course['image_path']) : '../uploads/course_images/default-course.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>"
                                 onerror="this.src='../uploads/course_images/default-course.jpg'">
                            <?php if ($course['is_enrolled']): ?>
                            <!-- Status Badge for enrolled courses -->
                            <div class="course-status-badge <?php echo str_replace('_', '-', $course['progress_status'] ?? 'not-started'); ?>">
                                <?php 
                                    $status = $course['progress_status'] ?? 'not_started';
                                    echo $status === 'completed' ? 'Completed' : 
                                         ($status === 'in_progress' ? 'In Progress' : 'Enrolled');
                                ?>
                            </div>
                            <?php endif; ?>
                            <div class="course-level-badge">
                                <?php echo $course['price'] > 0 ? '₱' . number_format($course['price'], 2) : 'Free'; ?>
                            </div>
                        </div>
                        <div class="course-content">
                            <div class="course-category rubik-medium">
                                <?php echo htmlspecialchars($course['category_name'] ?? 'Japanese'); ?>
                            </div>
                            <h3 class="course-title rubik-bold">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h3>
                            <p class="course-teacher rubik-regular">
                                <?php 
                                    if ($course['teacher_profile_visible']) {
                                        echo 'By ' . htmlspecialchars($course['teacher_display_name'] ?? 'Instructor');
                                    }
                                ?>
                            </p>
                            <div class="course-stats rubik-regular">
                                <span class="enrollment-count"><?php echo (int)$course['enrolled_count']; ?> students</span>
                                <span>Created <?php echo date('M Y', strtotime($course['created_at'])); ?></span>
                            </div>
                            
                            <a href="<?php echo $course['is_enrolled'] ? 'continue_learning.php?id=' . $course['id'] : 'view_course.php?id=' . $course['id']; ?>" class="continue-btn rubik-semibold">
                                <?php 
                                    if ($course['is_enrolled']) {
                                        $percentage = $course['completion_percentage'] ?? 0;
                                        $status = $course['progress_status'] ?? 'not_started';
                                        
                                        if ($status === 'completed' || $percentage == 100) {
                                            echo 'Review Module';
                                        } elseif ($status === 'in_progress' || $percentage > 0) {
                                            echo 'Continue Learning';
                                        } else {
                                            echo 'Start Module';
                                        }
                                    } else {
                                        echo 'View Module';
                                    }
                                ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

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
                <button class="force-logout-button" onclick="window.location.href='/AIToManabi_Updated/dashboard/login.php'">
                    Go to Login Now
                </button>
            </div>
        </div>
    </div>
</div>
    
    <!-- Password Reset Notification Script -->
    <script src="js/password_reset_notification.js"></script>
    <script>
        // Initialize password reset notification for student
        document.addEventListener('DOMContentLoaded', function() {
            const passwordResetNotification = new PasswordResetNotification('student', 'student_profile.php');
            passwordResetNotification.init();
        });
    </script>

 <!-- Auto Force Logout Check -->
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

</body>
</html>
