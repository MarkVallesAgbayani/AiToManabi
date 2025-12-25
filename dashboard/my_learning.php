<?php
session_start();
require_once '../config/database.php';
require_once 'real_time_activity_logger.php';
require_once 'includes/placement_test_functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    error_log("User not logged in or not a student. user_id: " . ($_SESSION['user_id'] ?? 'not set') . ", role: " . ($_SESSION['role'] ?? 'not set'));
    header("Location: ../index.php");
    exit();
}

// Check if student needs to take placement test
if (needsPlacementTest($pdo, $_SESSION['user_id'])) {
    $redirectUrl = getPlacementTestRedirectUrl($pdo, '');
    if ($redirectUrl) {
        header('Location: ' . $redirectUrl);
        exit();
    }
}

// Log my learning page access
try {
    $logger = new RealTimeActivityLogger($pdo);
    $logger->logPageView('my_learning', [
        'page_title' => 'My Learning',
        'access_time' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log("Activity logging error: " . $e->getMessage());
}

try {
    // Add debug logging
    error_log("Session user_id: " . $_SESSION['user_id']);
    error_log("Session role: " . $_SESSION['role']);
    
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

    if (!$student) {
        error_log("Student not found in database with ID: " . $_SESSION['user_id']);
        throw new Exception("Student not found");
    }

    // Add debug logging
    error_log("Fetching courses for student ID: " . $_SESSION['user_id']);
    
    // First check enrollments directly
    $check_stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ?");
    $check_stmt->execute([$_SESSION['user_id']]);
    $enrollments = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Direct enrollments found: " . print_r($enrollments, true));

    // Check courses table (excluding archived courses)
    $check_stmt = $pdo->prepare("SELECT * FROM courses WHERE status = 'published' AND is_archived = 0");
    $check_stmt->execute();
    $available_courses = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Available published courses: " . print_r($available_courses, true));

    // Get enrolled courses with progress (excluding archived courses)
    $query = "
        SELECT 
            c.*,
            cat.name as category_name,
            u.username as teacher_name,
            e.enrolled_at,
            (SELECT COUNT(*) FROM sections s 
             WHERE s.course_id = c.id) as total_sections,
            COALESCE(cp.completed_sections, 0) as completed_sections,
            cp.last_accessed_at,
            COALESCE(cp.completion_percentage, 0) as completion_percentage,
            COALESCE(cp.completion_status, 'not_started') as progress_status
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE e.student_id = ? AND c.is_archived = 0
        ORDER BY cp.last_accessed_at DESC, e.enrolled_at DESC
    ";
    error_log("Executing query: " . str_replace('?', $_SESSION['user_id'], $query));
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recalculate chapter-based progress for each enrolled course to ensure accuracy
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
            
            // Update the course_progress table with chapter-based values
            $update_stmt = $pdo->prepare("
                UPDATE course_progress 
                SET completed_sections = ?, 
                    completion_percentage = ?, 
                    completion_status = ?
                WHERE course_id = ? AND student_id = ?
            ");
            $update_stmt->execute([
                $completed_chapters, // Store completed chapters count for compatibility
                $course['completion_percentage'], 
                $course['progress_status'],
                $course['id'], 
                $_SESSION['user_id']
            ]);
        } else {
            // No chapters found, set default values
            $course['completed_chapters'] = 0;
            $course['total_chapters'] = 0;
            $course['completion_percentage'] = 0;
            $course['progress_status'] = 'not_started';
        }
    }
    unset($course);

    // Add debug logging
    error_log("Found " . count($enrolled_courses) . " enrolled courses");
    if (empty($enrolled_courses)) {
        // Check each table for relevant data
        $debug_queries = [
            "SELECT * FROM users WHERE id = ?" => [$_SESSION['user_id']],
            "SELECT * FROM enrollments WHERE student_id = ?" => [$_SESSION['user_id']],
            "SELECT * FROM courses WHERE status = 'published' AND is_archived = 0" => [],
            "SELECT * FROM course_progress WHERE student_id = ?" => [$_SESSION['user_id']]
        ];

        foreach ($debug_queries as $debug_query => $params) {
            $debug_stmt = $pdo->prepare($debug_query);
            $debug_stmt->execute($params);
            $results = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Debug query '$debug_query': " . print_r($results, true));
        }
    }

} catch (PDOException $e) {
    // Log the error
    error_log("Database Error in my_learning.php: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Set default values
    $student = [
        'username' => $_SESSION['username'] ?? 'Student'
    ];
    $enrolled_courses = [];
} catch (Exception $e) {
    error_log("Application Error in my_learning.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Set default values
    $student = [
        'username' => $_SESSION['username'] ?? 'Student'
    ];
    $enrolled_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Learning - AiToManabi</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <!-- Modern CSS -->
    <link rel="stylesheet" href="css/my_learning_modern.css">
    
    <!-- Alpine.js for Dark Mode -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Modern JavaScript -->
    <script src="js/my_learning_modern.js"></script>
    <script src="js/session_timeout.js"></script>
    
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
                                class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 inline-flex items-center px-4 pt-1 border-b-2 text-sm rubik-medium focus:outline-none">
                            Dashboard
                        </button>
                        <button onclick="window.location.href='student_courses.php'" 
                                class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 inline-flex items-center px-4 pt-1 border-b-2 text-sm rubik-medium focus:outline-none">
                            Modules
                        </button>
                        <button onclick="window.location.href='my_learning.php'" 
                                class="border-red-500 text-red-500 japanese-transition hover:text-red-600 inline-flex items-center px-4 pt-1 border-b-2 text-sm rubik-medium focus:outline-none">
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
                            class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        Dashboard
                    </button>
                    <button onclick="mobileMenuOpen = false; window.location.href='student_courses.php'" 
                            class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        Modules
                    </button>
                    <button onclick="mobileMenuOpen = false; window.location.href='my_learning.php'" 
                            class="border-red-500 text-red-500 japanese-transition hover:text-red-600 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
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
        <div class="learning-container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">My Learning</h1>
            <p class="page-subtitle">Track your progress and continue learning</p>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <div class="filter-grid">
                <div>
                    <input type="text" 
                           id="courseSearch" 
                           placeholder="Search your modules..." 
                           class="filter-input rubik-medium">
                </div>
                <div>
                    <select id="categoryFilter" class="filter-input rubik-medium">
                        <option value="">All Categories</option>
                        <?php
                        $categories = array_unique(array_column($enrolled_courses, 'category_name'));
                        foreach($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php echo htmlspecialchars($category ?? 'Uncategorized'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <select id="progressFilter" class="filter-input rubik-medium">
                        <option value="">All Progress</option>
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div>
                    <select id="sortBy" class="filter-input rubik-medium">
                        <option value="last_accessed">Last Accessed</option>
                        <option value="enrolled_date">Enrollment Date</option>
                        <option value="progress">Progress</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if (!empty($enrolled_courses)): ?>
            <!-- Module Grid -->
            <div id="courseGrid" class="course-grid">
                <?php foreach ($enrolled_courses as $course): ?>
                <div class="course-card slide-up" data-course-id="<?php echo $course['id']; ?>">
                    <div class="course-layout">
                        <!-- Module Image -->
                        <div class="course-image-container">
                            <img src="<?php echo $course['image_path'] ? '../uploads/course_images/' . htmlspecialchars($course['image_path']) : '../uploads/course_images/default-course.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>"
                                 onerror="this.src='../uploads/course_images/default-course.jpg'"
                                 class="course-image">
                            
                            <!-- Status Badge -->
                            <div class="course-status-badge course-status <?php 
                                echo $course['progress_status'] === 'completed' 
                                    ? 'status-completed' 
                                    : ($course['progress_status'] === 'in_progress' ? 'status-in-progress' : 'status-not-started'); 
                            ?>">
                                <span class="course-status-text"><?php 
                                echo ucwords(str_replace('_', ' ', $course['progress_status'])); 
                                ?></span>
                            </div>
                        </div>
                        
                        <!-- Course Content -->
                        <div class="course-content">
                            <div class="course-meta">
                                <span class="course-category rubik-semibold">
                                    <?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                                <span class="course-enrolled-date rubik-medium">
                                    Enrolled: <?php echo date('M j, Y', strtotime($course['enrolled_at'])); ?>
                                </span>
                            </div>
                            
                            <h2 class="course-title rubik-bold">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h2>
                            
                            <p class="course-description rubik-regular">
                                <?php echo htmlspecialchars(substr(strip_tags($course['description'] ?? ''), 0, 150)) . '...'; ?>
                            </p>
                            
                            <!-- Progress Section -->
                            <div class="progress-section">
                                <div class="progress-header">
                                    <span class="progress-label rubik-semibold">Module Progress</span>
                                    <span class="progress-percentage rubik-bold"><?php echo round($course['completion_percentage']); ?>%</span>
                                </div>
                                <div class="progress-stats rubik-medium sections-completed">
                                    <?php echo (int)$course['completed_chapters']; ?>/<?php echo (int)$course['total_chapters']; ?> chapters completed
                                </div>
                                <!-- Debug: <?php echo "Percentage: " . $course['completion_percentage'] . ", Completed: " . $course['completed_sections'] . ", Total: " . $course['total_sections'] . ", Status: " . $course['progress_status']; ?> -->
                                <div class="progress-bar">
                                    <div class="progress-fill" data-progress="<?php echo round($course['completion_percentage']); ?>"></div>
                                </div>
                                <?php if ($course['last_accessed_at']): ?>
                                <div class="last-accessed rubik-regular">
                                    Last accessed: <?php echo date('M j, Y g:i A', strtotime($course['last_accessed_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="course-actions">
                                <div class="btn-group">
                                    <a href="view_course.php?id=<?php echo $course['id']; ?>" 
                                       class="btn btn-primary continue-btn rubik-semibold">
                                        <?php 
                                        if ($course['progress_status'] === 'completed') {
                                            echo 'Review Module';
                                        } elseif ($course['progress_status'] === 'in_progress') {
                                            echo 'Continue Learning';
                                        } else {
                                            echo 'Start Module';
                                        }
                                        ?>
                                    </a>
                                </div>
                                <span class="course-status-text rubik-semibold <?php 
                                    echo $course['progress_status'] === 'completed' 
                                        ? 'status-text-completed'   
                                        : ($course['progress_status'] === 'in_progress' ? 'status-text-in-progress' : 'status-text-not-started'); 
                                ?>">
                                    <?php 
                                    echo ucwords(str_replace('_', ' ', $course['progress_status'])); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state fade-in">
                <div class="empty-icon">📚</div>
                <h3 class="empty-title rubik-bold">No Enrolled Modules</h3>
                <p class="empty-description rubik-regular">Start your learning journey today!</p>
                <a href="student_courses.php" class="empty-action rubik-semibold">
                    Browse Modules
                </a>
            </div>
        <?php endif; ?>
        </div>
    </main>
    
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

            // Mobile menu state
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
</body>
</html> 