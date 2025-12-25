<?php
session_start();
require_once '../config/database.php';
require_once '../PaymentPayMongo/config.php';
require_once 'real_time_activity_logger.php';
require_once 'includes/placement_test_functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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

// Log student courses page access
try {
    $logger = new RealTimeActivityLogger($pdo);
    $logger->logPageView('student_courses', [
        'page_title' => 'Student Modules',
        'access_time' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log("Activity logging error: " . $e->getMessage());
}

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

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    $error_message = '';
    $success_message = '';
    
    try {
        $pdo->beginTransaction();
        
        $course_id = (int)$_POST['course_id'];
        
        // Verify course exists, is published, not archived, and get price
        $stmt = $pdo->prepare("SELECT id, price FROM courses WHERE id = ? AND status = 'published' AND is_archived = 0");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        if (!$course) {
            throw new Exception("Course not found or not available for enrollment.");
        }
        // If course is paid, redirect to payment page
        if ($course['price'] > 0) {
            $pdo->commit(); // End transaction before redirect
            header("Location: ../PaymentPayMongo/create_checkout_session.php?" . http_build_query([
                'course_id' => $course_id,
                'user_id' => $_SESSION['user_id']
            ]));
            exit();
        }
        
        // Check if already enrolled
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE course_id = ? AND student_id = ?");
        $stmt->execute([$course_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            // Create enrollment
            $stmt = $pdo->prepare("INSERT INTO enrollments (course_id, student_id, enrolled_at) VALUES (?, ?, NOW())");
            $stmt->execute([$course_id, $_SESSION['user_id']]);
            
            // Log course enrollment activity for analytics
            try {
                $logger = new RealTimeActivityLogger($pdo);
                $logger->logCourseActivity($course_id, 'course_enrollment', [
                    'enrollment_date' => date('Y-m-d H:i:s'),
                    'course_price' => $course['price']
                ]);
            } catch (Exception $e) {
                error_log("Activity logging error: " . $e->getMessage());
            }
            
            // Initialize course progress - using INSERT IGNORE to handle duplicates
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO course_progress 
                (course_id, student_id, completed_sections, completion_percentage, completion_status)
                VALUES (?, ?, 0, 0, 'not_started')
            ");
            $stmt->execute([$course_id, $_SESSION['user_id']]);
            
            // Initialize progress for sections - using INSERT IGNORE to handle duplicates
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO progress (student_id, section_id, completed)
                SELECT ?, s.id, 0
                FROM sections s
                WHERE s.course_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $course_id]);

            // Create a free payment record for tracking
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    user_id,
                    course_id,
                    amount,
                    payment_status,
                    payment_date,
                    payment_type
                ) VALUES (?, ?, 0, 'completed', NOW(), 'FREE')
            ");
            $stmt->execute([$_SESSION['user_id'], $course_id]);
            
            $pdo->commit();
            // Set session flag for JS notification
            $_SESSION['enrolled_success'] = true;
            // Allow caller to request a different post-enroll redirect via a safe whitelist
            $allowed_returns = ['continue_learning'];
            $return_to = isset($_POST['return_to']) && in_array($_POST['return_to'], $allowed_returns) ? $_POST['return_to'] : 'continue_learning';

            if ($return_to === 'continue_learning') {
                header("Location: continue_learning.php?id=" . urlencode($course_id));
                exit();
            }

            // Fallback: redirect back to courses page
            header("Location: student_courses.php");
            exit();
        } else {
            $error_message = "You are already enrolled in this course.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Enrollment error: " . $e->getMessage());
        $error_message = "An error occurred during enrollment. Please try again. Error: " . $e->getMessage();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Enrollment error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// Fetch all published courses with category information and enrollment status
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, u.username as teacher_name,
           COALESCE(tp.display_name, u.username) as teacher_display_name,
           COALESCE(tp.profile_visible, 1) as teacher_profile_visible,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           CASE WHEN e.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
           COALESCE(cp.completion_percentage, 0) as completion_percentage,
           COALESCE(cp.completed_sections, 0) as completed_sections,
           (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as total_sections,
           cp.last_accessed_at,
           COALESCE(cp.completion_status, 'not_started') as progress_status
    FROM courses c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN users u ON c.teacher_id = u.id
    LEFT JOIN teacher_preferences tp ON u.id = tp.teacher_id
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
    WHERE c.status = 'published' AND c.is_archived = 0
    ORDER BY COALESCE(c.sort_order, 999999), c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Recalculate chapter-based progress for enrolled courses to ensure accuracy
foreach ($courses as &$course) {
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
            
            // Debug logging to track progress updates
            error_log("Course ID: " . $course['id'] . " - Updated status: " . $course['progress_status'] . " - Progress: " . $course['completion_percentage'] . "%");
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
unset($course);
?>

<!DOCTYPE html>
<html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Modules - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student_courses_modern.css">
    <link rel="stylesheet" href="css/freecourse.css">
    <link rel="stylesheet" href="css/paidcourse.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="../PaymentPayMongo/js/payment.js"></script>
    <script src="js/student_courses_modern.js"></script>
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
                                class="border-red-500 text-red-500 japanese-transition hover:text-red-600 inline-flex items-center px-4 pt-1 border-b-2 text-sm rubik-medium focus:outline-none">
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
                            class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        Dashboard
                    </button>
                    <button onclick="mobileMenuOpen = false; window.location.href='student_courses.php'" 
                            class="border-red-500 text-red-500 japanese-transition hover:text-red-600 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
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
        <div class="courses-container">
            <!-- Alert Messages -->
            <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="alert alert-error rubik-medium">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="alert alert-success rubik-medium">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['enrolled_success']) && $_SESSION['enrolled_success']): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof showNotification === 'function') {
                        showNotification('You are successfully enrolled', 'success');
                    } else {
                        showNotification('You are successfully enrolled', 'success');
                    }
                });
            </script>
            <?php unset($_SESSION['enrolled_success']); ?>
            <?php endif; ?>

            <!-- Header Section -->
            <div class="page-header">
                <h1 class="text-4xl md:text-5xl lg:text-5xl xl:text-6xl font-black mb-4 text-center rubik-black page-title">Available Modules</h1>
                <p class="text-lg md:text-xl lg:text-2xl xl:text-3xl font-medium text-center rubik-medium page-subtitle">Explore our wide range of Japanese language modules</p>
            </div>

            <!-- Course Categories Filter -->
            <div class="filter-container">
                <h2 class="text-xl md:text-2xl lg:text-3xl font-bold mb-6 text-center rubik-bold filter-title">Filter by Category</h2>
                <div class="filter-buttons">
                    <button class="filter-btn active rubik-semibold" data-category="all">
                        All Modules
                    </button>
                    <?php
                    $stmt = $pdo->query("SELECT DISTINCT name FROM categories ORDER BY name");
                    while ($category = $stmt->fetch()) {
                        echo '<button class="filter-btn rubik-semibold" data-category="' . htmlspecialchars($category['name']) . '">';
                        echo htmlspecialchars($category['name']);
                        echo '</button>';
                    }
                    ?>
                </div>
            </div>

            <!-- Modules Grid -->
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                <div class="course-card" 
                     data-category="<?php echo $course['category_name'] !== null ? htmlspecialchars($course['category_name']) : ''; ?>" 
                     data-course-id="<?php echo $course['id']; ?>"
                     data-progress-status="<?php echo $course['progress_status']; ?>"
                     data-completion-percentage="<?php echo $course['completion_percentage']; ?>">
                    <!-- Course Image -->
                    <div class="course-image">
                        <img src="<?php echo $course['image_path'] ? '../uploads/course_images/' . htmlspecialchars($course['image_path']) : '../uploads/course_images/default-course.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($course['title']); ?>"
                             onerror="this.src='../uploads/course_images/default-course.jpg'">
                        <div class="course-price-badge <?php echo $course['price'] > 0 ? 'paid' : 'free'; ?>">
                            <?php echo $course['price'] > 0 ? '₱' . number_format($course['price'], 2) : 'Free'; ?>
                        </div>
                        
                        <?php if ($course['is_enrolled']): ?>
                        <!-- Progress Status Badge for Enrolled Modules -->
                        <div class="course-status-badge <?php 
                            echo $course['progress_status'] === 'completed' 
                                ? 'status-completed' 
                                : ($course['progress_status'] === 'in_progress' ? 'status-in-progress' : 'status-not-started'); 
                        ?>">
                            <?php 
                            echo $course['progress_status'] === 'completed' ? 'Completed' : 
                                 ($course['progress_status'] === 'in_progress' ? 'In Progress' : 'Not Started');
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Course Info -->
                    <div class="course-content">
                        <div class="course-meta">
                            <span class="course-category rubik-semibold">
                                <?php echo $course['category_name'] !== null ? htmlspecialchars($course['category_name']) : 'Uncategorized'; ?>
                            </span>
                            <span class="course-students rubik-medium enrollment-count">
                                <?php echo $course['student_count']; ?> students
                            </span>
                        </div>
                        <h3 class="course-title rubik-bold">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </h3>
                        <div class="course-teacher rubik-medium">
                            <?php 
                                if ($course['teacher_profile_visible']) {
                                    echo 'By ' . htmlspecialchars($course['teacher_display_name'] ?? 'Instructor');
                                }
                            ?>
                        </div>
                        
                        <?php if ($course['is_enrolled']): ?>
                            <a href="view_course.php?id=<?php echo $course['id']; ?>" 
                               class="course-btn course-btn-success continue-btn rubik-bold">
                                <?php 
                                    $percentage = $course['completion_percentage'] ?? 0;
                                    if ($percentage == 100) {
                                        echo 'Review Module';
                                    } elseif ($percentage > 0) {
                                        echo 'Continue Learning';
                                    } else {
                                        echo 'Start Learning';
                                    }
                                ?>
                            </a>
                        <?php else: ?>
                            <!-- Enroll Button - Redirects to Module Overview -->
                            <a href="Module_overview.php?id=<?php echo $course['id']; ?>" 
                               class="course-btn course-btn-primary enroll-btn rubik-bold">
                                <?php if ($course['price'] > 0): ?>
                                    Enroll (₱<?php echo number_format($course['price'], 2); ?>)
                                <?php else: ?>
                                    Enroll Free
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- No Modules Message -->
            <?php if (empty($courses)): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3 class="text-2xl md:text-3xl lg:text-4xl font-bold mb-2 rubik-bold empty-title">No Modules Available</h3>
                <p class="text-lg md:text-xl rubik-regular empty-description">Check back later for new courses!</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Enrollment Confirmation Modal -->
    <div id="enrollModal" class="hidden">
        <div class="modal-container">
            <div class="modal-header">
                <div class="icon-container">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <h3 class="modal-title rubik-bold">Confirm Free Enrollment</h3>
            </div>
            
            <div class="modal-body">
                <p class="modal-message rubik-medium" id="modalCourseTitle">
                    Are you sure you want to enroll in this course for free? Once confirmed, it will be added to your courses.
                </p>
            </div>
            
            <div class="modal-actions">
                <button id="confirmEnroll" class="btn-confirm rubik-bold">
                    Yes, Enroll Free
                </button>
                <button id="cancelEnroll" class="btn-cancel rubik-medium">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Paid Course Confirmation Modal -->
    <div id="paidEnrollModal" class="hidden">
        <div class="modal-container">
            <div class="modal-header">
                <div class="icon-container">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3 class="modal-title rubik-bold">Paid Course Enrollment</h3>
            </div>
            
            <div class="modal-body">
                <p class="modal-message rubik-medium" id="paidModalCourseTitle">
                    This is a paid course. By confirming, you will proceed to the payment process. Do you wish to continue?
                </p>
                <div class="price-display rubik-bold" id="paidCoursePrice">
                    ₱0.00
                </div>
            </div>
            
            <div class="modal-actions">
                <button id="confirmPaidEnroll" class="btn-confirm rubik-bold">
                    Yes, Proceed to Payment
                </button>
                <button id="cancelPaidEnroll" class="btn-cancel rubik-medium">
                    No, Go Back
                </button>
            </div>
        </div>
    </div>

    <script>
        // Category filter functionality - Updated to work with new CSS classes
        document.addEventListener('DOMContentLoaded', function() { 
            const filterButtons = document.querySelectorAll('.filter-btn');
            const courseCards = document.querySelectorAll('.course-card');

            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => {
                        btn.classList.remove('active');
                    });

                    // Add active class to clicked button
                    button.classList.add('active');

                    const category = button.dataset.category;

                    // Filter courses
                    courseCards.forEach(card => {
                        if (category === 'all' || card.dataset.category === category) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });

            // Free enrollment confirmation modal functionality
            let currentCourseId = null;
            
            window.showEnrollConfirmation = function(courseId, courseTitle) {
                currentCourseId = courseId;
                document.getElementById('modalCourseTitle').innerHTML = 
                    `Are you sure you want to enroll in "<strong>${courseTitle}</strong>" for free? Once confirmed, it will be added to your courses.`;
                document.getElementById('enrollModal').classList.remove('hidden');
            };

            // Paid enrollment confirmation modal functionality
            let currentPaidCourseData = null;
            
            window.showPaidEnrollConfirmation = function(courseId, courseTitle, coursePrice, userId) {
                currentPaidCourseData = {
                    courseId: courseId,
                    courseTitle: courseTitle,
                    coursePrice: coursePrice,
                    userId: userId
                };
                document.getElementById('paidModalCourseTitle').innerHTML = 
                    `This is a paid course "<strong>${courseTitle}</strong>". By confirming, you will proceed to the payment process. Do you wish to continue?`;
                document.getElementById('paidCoursePrice').textContent = `₱${coursePrice}`;
                document.getElementById('paidEnrollModal').classList.remove('hidden');
            };

            document.getElementById('confirmEnroll').addEventListener('click', function() {
                if (currentCourseId) {
                    document.getElementById(`enroll-form-${currentCourseId}`).submit();
                }
            });

            document.getElementById('cancelEnroll').addEventListener('click', function() {
                document.getElementById('enrollModal').classList.add('hidden');
                currentCourseId = null;
            });

            // Close modal when clicking outside
            document.getElementById('enrollModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    document.getElementById('enrollModal').classList.add('hidden');
                    currentCourseId = null;
                }
            });

            // Paid course modal event listeners
            document.getElementById('confirmPaidEnroll').addEventListener('click', function() {
                if (currentPaidCourseData) {
                    // Create a fake button element with the required data attributes
                    const fakeButton = document.createElement('button');
                    fakeButton.setAttribute('data-course-id', currentPaidCourseData.courseId);
                    fakeButton.setAttribute('data-user-id', currentPaidCourseData.userId);
                    
                    // Create a fake event object
                    const fakeEvent = {
                        preventDefault: () => {},
                        currentTarget: fakeButton
                    };
                    
                    // Close the modal first
                    document.getElementById('paidEnrollModal').classList.add('hidden');
                    currentPaidCourseData = null;
                    
                    // Call the original payment handler
                    if (typeof handlePaymentButtonClick === 'function') {
                        handlePaymentButtonClick(fakeEvent);
                    }
                }
            });

            document.getElementById('cancelPaidEnroll').addEventListener('click', function() {
                document.getElementById('paidEnrollModal').classList.add('hidden');
                currentPaidCourseData = null;
            });

            // Close paid modal when clicking outside
            document.getElementById('paidEnrollModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    document.getElementById('paidEnrollModal').classList.add('hidden');
                    currentPaidCourseData = null;
                }
            });
        });
    </script>
</body>
</html> 