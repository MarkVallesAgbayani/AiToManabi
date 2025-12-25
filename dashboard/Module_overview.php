<?php
session_start();
require_once '../config/database.php';
require_once '../PaymentPayMongo/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

// Check if course ID is provided
if (!isset($_GET['id'])) {
    header("Location: student_courses.php");
    exit();
}

$course_id = $_GET['id'];

// Get student's name and email
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

// Get course details with error handling
try {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name, u.username as teacher_name,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as total_sections
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ? AND c.status = 'published'
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        throw new Exception("Course not found or not published");
    }

    // Check if student is already enrolled
    $stmt = $pdo->prepare("
        SELECT e.*, 
               COALESCE(cp.completed_sections, 0) as completed_sections,
               COALESCE(cp.completion_percentage, 0) as completion_percentage,
               COALESCE(cp.completion_status, 'not_started') as progress_status,
               cp.last_accessed_at
        FROM enrollments e
        LEFT JOIN course_progress cp ON cp.course_id = e.course_id AND cp.student_id = e.student_id
        WHERE e.course_id = ? AND e.student_id = ?
    ");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $enrollment = $stmt->fetch();

    // If already enrolled, redirect to view course
    if ($enrollment) {
        header("Location: view_course.php?id=" . urlencode($course_id));
        exit();
    }

} catch (Exception $e) {
    error_log("Course loading error: " . $e->getMessage());
    header("Location: student_courses.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Module Overview - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/view_course_modern.css">
    <link rel="stylesheet" href="css/validation_signup.css">
    <link rel="stylesheet" href="css/freecourse.css">
    <link rel="stylesheet" href="css/paidcourse.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="../PaymentPayMongo/js/payment.js"></script>
    <script src="js/view_course_modern.js"></script>
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
                            class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
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
        <div class="course-container">
            <!-- Course Overview Header -->
            <div class="course-header-card" data-course-id="<?php echo $course['id']; ?>">
                <div class="course-image-header">
                    <img src="<?php echo $course['image_path'] ? '../uploads/course_images/' . htmlspecialchars($course['image_path']) : '../uploads/course_images/default-course.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($course['title']); ?>">
                    <div class="course-image-overlay">
                        <div class="course-header-content">
                            <h1 class="course-title rubik-black"><?php echo htmlspecialchars($course['title'] ?? ''); ?></h1>
                            <div class="course-meta-badges">
                                <span class="course-badge rubik-semibold">
                                    <?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                                <span class="course-badge rubik-semibold total-students">
                                    <?php echo intval($course['student_count']); ?> students enrolled
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="course-content">
                    <!-- Module Overview Section -->
                    <div class="enrollment-section">
                        <div class="enrollment-status">
                            <div class="enrollment-info">
                                <div class="enrollment-icon"></div>
                                <div class="enrollment-text">
                                    <h3 class="rubik-bold">Module Overview</h3>
                                    <p class="rubik-regular">Review the module details before enrolling</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Course Details -->
                        <div class="overview-details">
                            <div class="overview-section">
                                <h4 class="overview-section-title rubik-bold">Module Description</h4>
                                <p class="overview-section-content rubik-regular">
                                    <?php echo htmlspecialchars($course['description'] ?? 'No description available for this module.'); ?>
                                </p>
                            </div>
                            
                            <div class="overview-section">
                                <h4 class="overview-section-title rubik-bold">Module Information</h4>
                                <div class="overview-stats">
                                    <div class="stat-item">
                                        <span class="stat-label rubik-medium">Sections:</span>
                                        <span class="stat-value rubik-semibold"><?php echo intval($course['total_sections']); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label rubik-medium">Category:</span>
                                        <span class="stat-value rubik-semibold"><?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label rubik-medium">Teacher:</span>
                                        <span class="stat-value rubik-semibold"><?php echo htmlspecialchars($course['teacher_name'] ?? 'Instructor'); ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label rubik-medium">Students Enrolled:</span>
                                        <span class="stat-value rubik-semibold"><?php echo intval($course['student_count']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Enrollment Actions -->
                            <div class="enrollment-actions">
                                <?php if (floatval($course['price']) > 0): ?>
                                    <!-- Paid Course -->
                                    <div class="price-display rubik-bold">
                                        ₱<?php echo number_format($course['price'], 2); ?>
                                    </div>
                                    <button onclick="showPaidEnrollConfirmation(<?php echo htmlspecialchars($course['id']); ?>, '<?php echo addslashes($course['title']); ?>', '<?php echo number_format($course['price'], 2); ?>', <?php echo htmlspecialchars($_SESSION['user_id']); ?>)" 
                                            class="btn btn-primary enroll-btn rubik-semibold">
                                        Enroll Now (₱<?php echo number_format($course['price'], 2); ?>)
                                    </button>
                                <?php else: ?>
                                    <!-- Free Course -->
                                    <div class="price-display price-free rubik-bold">
                                        Free
                                    </div>
                                    <form method="POST" action="student_courses.php" id="enroll-form-<?php echo $course['id']; ?>" class="block">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <input type="hidden" name="enroll" value="1">
                                        <!-- Instruct student_courses.php where to redirect after enrollment (allow only known targets) -->
                                        <input type="hidden" name="return_to" value="continue_learning">
                                        <button type="button" onclick="showEnrollConfirmation(<?php echo $course['id']; ?>, '<?php echo addslashes($course['title']); ?>')" class="btn btn-primary enroll-btn rubik-semibold">
                                            Enroll Free
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button onclick="window.location.href='student_courses.php'" 
                                        class="btn btn-secondary rubik-medium">
                                    Back to Modules
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                    Are you sure you want to enroll in this module for free? Once confirmed, it will be added to your courses.
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
                    This is a paid module. By confirming, you will proceed to the payment process. Do you wish to continue?
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

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('confirmEnroll').addEventListener('click', function() {
                if (currentCourseId) {
                    // Submit the form
                    const form = document.querySelector('form[method="POST"]');
                    if (form) {
                        form.submit();
                    }
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
