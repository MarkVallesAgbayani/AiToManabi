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

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

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

// Data masking functions for privacy protection
function maskEmail($email) {
    if (empty($email)) return 'Not provided';
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $username = $parts[0];
    $domain = $parts[1];
    
    if (strlen($username) <= 2) {
        $masked_username = str_repeat('*', strlen($username));
    } else {
        $masked_username = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
    }
    
    return $masked_username . '@' . $domain;
}

function maskPhone($phone) {
    if (empty($phone)) return 'Not provided';
    if (strlen($phone) <= 4) return str_repeat('*', strlen($phone));
    
    $visible_digits = 2;
    $masked = substr($phone, 0, $visible_digits) . str_repeat('*', strlen($phone) - $visible_digits - 2) . substr($phone, -2);
    return $masked;
}

function maskAge($age) {
    if (empty($age)) return 'Not provided';
    // Show age range instead of exact age for privacy
    if ($age < 18) return 'Under 18';
    if ($age < 25) return '18-24';
    if ($age < 35) return '25-34';
    if ($age < 45) return '35-44';
    if ($age < 55) return '45-54';
    return '55+';
}

function maskLastLogin($last_login) {
    if (empty($last_login)) return 'Never';
    
    $login_date = new DateTime($last_login);
    $now = new DateTime();
    $diff = $now->diff($login_date);
    
    if ($diff->days == 0) return 'Today';
    if ($diff->days == 1) return 'Yesterday';
    if ($diff->days < 7) return $diff->days . ' days ago';
    if ($diff->days < 30) return floor($diff->days / 7) . ' weeks ago';
    if ($diff->days < 365) return floor($diff->days / 30) . ' months ago';
    return 'Over a year ago';
}

// Get student ID from URL parameter
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    header("Location: student_profiles.php");
    exit();
}

// Fetch student data with all related information
try {
    // Main student information with profile picture
    $stmt = $pdo->prepare("
        SELECT u.*, COALESCE(sp.profile_picture, '') as profile_picture
        FROM users u
        LEFT JOIN student_preferences sp ON u.id = sp.student_id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: student_profiles.php");
        exit();
    }

    // Get enrollment data - with progress information
    $stmt = $pdo->prepare("
        SELECT c.title as course_name, c.description as course_description, 
               e.enrolled_at as enrollment_date, 'active' as status,
               e.course_id,
               COALESCE(cp.completion_percentage, 0) as completion_percentage,
               COALESCE(cp.completion_status, 'not_started') as completion_status
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON e.course_id = cp.course_id AND e.student_id = cp.student_id
        WHERE e.student_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$student_id]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate overall progress statistics
    $total_enrollments = count($enrollments);
    $completed_courses = 0;
    $total_progress = 0;
    
    foreach ($enrollments as $enrollment) {
        if ($enrollment['completion_status'] === 'completed') {
            $completed_courses++;
        }
        $total_progress += floatval($enrollment['completion_percentage']);
    }
    
    $overall_progress = $total_enrollments > 0 ? round($total_progress / $total_enrollments, 1) : 0;

    // Get login activity - simple query
    $stmt = $pdo->prepare("
        SELECT login_time, last_login_at
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$student_id]);
    $login_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Derive activity status similar to student_profiles
    $last_login_source = !empty($student['last_login_at']) ? $student['last_login_at'] : $student['login_time'];
    $activity_status = 'Long Inactive';
    if (!empty($last_login_source)) {
        $last = new DateTime($last_login_source);
        $now = new DateTime();
        $diff_days = (int)$now->diff($last)->days;
        if ($diff_days < 7) {
            $activity_status = 'Active';
        } elseif ($diff_days < 30) {
            $activity_status = 'Inactive';
        } else {
            $activity_status = 'Long Inactive';
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching student data: " . $e->getMessage());
    header("Location: student_profiles.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Japanese Learning Platform</title>
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
        .info-card {
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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
            <?php echo renderTeacherSidebarProfileSubdir($teacher_profile, false); ?>

            <!-- Sidebar Navigation -->
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: true }">
                <div class="space-y-1">
                    <a href="../teacher.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>

                    <a href="../courses_available.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                       <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        Courses
                    </a>

                    <a href="../teacher_create_module.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Module
                    </a>

                    <a href="../teacher_drafts.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        My Drafts
                    </a>

                    <a href="../teacher_archive.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-archive-restore-icon lucide-archive-restore"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg>
                        Archived
                    </a>

                    <!-- Student Management Dropdown -->
                    <div class="relative">
                        <button @click="studentDropdownOpen = !studentDropdownOpen" 
                                class="nav-link bg-primary-50 text-primary-700 w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg hover:bg-primary-100 transition-colors">
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
                            
                            <a href="student_profiles.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-primary-600 hover:bg-primary-50 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Student Profiles
                            </a>
                            
                            <a href="progress_tracking.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Progress Tracking
                            </a>
                            
                            <a href="quiz_performance.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Quiz Performance
                            </a>
                            
                            <a href="engagement_monitoring.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Engagement Monitoring
                            </a>
                            </a>
                            
                            <a href="completion_reports.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Completion Reports
                            </a>
                        </div>
                    </div>

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
                    <h1 class="text-2xl font-semibold text-gray-900">Student Profile</h1>
                    <div class="flex items-center gap-4">
                        <div class="text-sm text-gray-500">
                            <?php echo date('l, F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Back Button (small, below header) -->
            <div class="px-6 py-2 border-b bg-gray-50">
                <a href="student_profiles.php" 
                   class="inline-flex items-center text-sm text-primary-600 hover:text-primary-700 transition-colors">
                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Student Profiles
                </a>
            </div>

            <!-- Content Areas -->
            <main class="main-content p-6" style="height: calc(100vh - 120px); overflow-y: auto;">
                <!-- Privacy Notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Privacy Protection Notice</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>Student personal information is masked for privacy protection. Sensitive data like email addresses, phone numbers, and exact ages are partially hidden to comply with privacy regulations.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student Header -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300 p-6 mb-6">
                    <div class="flex flex-col items-center justify-center gap-4">
                        <div class="relative flex flex-col items-center justify-center">
                            <?php if (!empty($student['profile_picture'])): ?>
                                <img src="../../uploads/profile_pictures/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                     class="w-24 h-24 rounded-full object-cover shadow-lg" 
                                     alt="Profile Picture"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-3xl shadow-lg" style="display: none;">
                                    <?php echo strtoupper(substr($student['first_name'] ?: $student['username'], 0, 1) . substr($student['last_name'] ?: '', 0, 1)); ?>
                                </div>
                            <?php else: ?>
                                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-3xl shadow-lg">
                                    <?php echo strtoupper(substr($student['first_name'] ?: $student['username'], 0, 1) . substr($student['last_name'] ?: '', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="absolute bottom-2 right-2 w-6 h-6 bg-gradient-to-br from-green-400 to-green-600 rounded-full border-3 border-white shadow-md flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-1 text-center">
                            <?php 
                            $full_name = trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'] . ' ' . $student['suffix']);
                            echo htmlspecialchars($full_name ?: $student['username']); 
                            ?>
                        </h2>
                        <p class="text-primary-600 mb-1 font-medium text-center">@<?php echo htmlspecialchars($student['username']); ?></p>
                        <div class="flex flex-wrap items-center justify-center gap-2 mt-2">
                            <span class="flex items-center bg-gradient-to-r from-blue-50 to-indigo-50 px-3 py-1 rounded-full border border-blue-200">
                                <svg class="h-4 w-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <span class="text-blue-700 font-medium">Student</span>
                            </span>
                            <span class="flex items-center bg-gradient-to-r from-gray-50 to-gray-100 px-3 py-1 rounded-full border border-gray-200">
                                <svg class="h-4 w-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-gray-700 font-medium">Joined <?php echo date('M j, Y', strtotime($student['created_at'])); ?></span>
                            </span>
                            <?php 
                                $statusBadgeClass = $activity_status === 'Active' ? 'bg-green-100 text-green-800' : ($activity_status === 'Inactive' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                $statusDotClass = $activity_status === 'Active' ? 'bg-green-500' : ($activity_status === 'Inactive' ? 'bg-yellow-500' : 'bg-red-500');
                            ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $statusBadgeClass; ?>">
                                <span class="w-2 h-2 rounded-full mr-2 <?php echo $statusDotClass; ?>"></span>
                                <?php echo $activity_status; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Combined Information Card -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300 info-card mb-6">
                    <div class="p-4 bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="p-2 bg-white rounded-lg shadow-sm mr-3">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            Student Information
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <!-- Personal Info Section -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-3">
                                <label class="text-xs font-medium text-gray-500 block mb-1">Full Name</label>
                                <p class="text-sm text-gray-800 font-medium truncate"><?php echo htmlspecialchars($full_name ?: 'Not provided'); ?></p>
                            </div>
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-3">
                                <label class="text-xs font-medium text-gray-500 block mb-1">Email</label>
                                <p class="text-sm text-gray-800 font-medium truncate" title="Email address is masked for privacy"><?php echo htmlspecialchars(maskEmail($student['email'])); ?></p>
                            </div>
                            
                            
                            
                            <!-- Account Info Section -->
                            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-3">
                                <label class="text-xs font-medium text-gray-500 block mb-1">Username</label>
                                <p class="text-sm text-gray-800 font-medium truncate">@<?php echo htmlspecialchars($student['username']); ?></p>
                            </div>
                            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-3">
                                <label class="text-xs font-medium text-gray-500 block mb-1">Activity Status</label>
                                <?php 
                                    $infoBadgeClass = $activity_status === 'Active' ? 'bg-green-100 text-green-800' : ($activity_status === 'Inactive' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                    $infoDotClass = $activity_status === 'Active' ? 'bg-green-500' : ($activity_status === 'Inactive' ? 'bg-yellow-500' : 'bg-red-500');
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $infoBadgeClass; ?>">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1 <?php echo $infoDotClass; ?>"></span>
                                    <?php echo $activity_status; ?>
                                </span>
                            </div>
                            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-3">
                                <label class="text-xs font-medium text-gray-500 block mb-1">Email Verified</label>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $student['email_verified'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1 <?php echo $student['email_verified'] ? 'bg-green-500' : 'bg-yellow-500'; ?>"></span>
                                    <?php echo $student['email_verified'] ? 'Verified' : 'Unverified'; ?>
                                </span>
                            </div>
                            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-3">
                                <label class="text-xs font-medium text-gray-500 block mb-1">Last Login</label>
                                <p class="text-sm text-gray-800 font-medium" title="Login time is shown as relative for privacy"><?php echo maskLastLogin($student['last_login_at']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overall Progress Summary -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300 info-card p-6 mb-6">
                    <div class="bg-gradient-to-r from-primary-50 to-pink-50 -m-6 p-6 mb-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="p-2 bg-white rounded-lg shadow-sm mr-3">
                                <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            Learning Progress Overview
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Overall Progress Circle -->
                        <div class="text-center bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl p-6 border border-gray-100">
                            <div class="relative w-28 h-28 mx-auto mb-4">
                                <svg class="w-28 h-28 transform -rotate-90" viewBox="0 0 36 36">
                                    <path d="M18 2.0845
                                        a 15.9155 15.9155 0 0 1 0 31.831
                                        a 15.9155 15.9155 0 0 1 0 -31.831"
                                        fill="none"
                                        stroke="#e5e7eb"
                                        stroke-width="2"/>
                                    <path d="M18 2.0845
                                        a 15.9155 15.9155 0 0 1 0 31.831
                                        a 15.9155 15.9155 0 0 1 0 -31.831"
                                        fill="none"
                                        stroke="<?php echo $overall_progress >= 80 ? '#10b981' : ($overall_progress >= 50 ? '#f59e0b' : '#ef4444'); ?>"
                                        stroke-width="3"
                                        stroke-dasharray="<?php echo $overall_progress; ?>, 100"/>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-2xl font-bold <?php echo $overall_progress >= 80 ? 'text-green-600' : ($overall_progress >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                        <?php echo round($overall_progress); ?>%
                                    </span>
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-gray-700">Overall Progress</div>
                            <div class="text-xs text-gray-500 mt-1">Learning completion</div>
                        </div>
                        
                        <!-- Course Statistics -->
                        <div class="text-center bg-gradient-to-br from-primary-50 to-purple-50 rounded-xl p-6 border border-primary-100">
                            <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-primary-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <div class="text-3xl font-bold text-primary-600 mb-2"><?php echo $total_enrollments; ?></div>
                            <div class="text-sm font-semibold text-gray-700">Total Modules</div>
                            <div class="text-xs text-gray-500 mt-1">Enrolled</div>
                        </div>
                        
                        <!-- Completion Statistics -->
                        <div class="text-center bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border border-green-100">
                            <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="text-3xl font-bold text-green-600 mb-2"><?php echo $completed_courses; ?></div>
                            <div class="text-sm font-semibold text-gray-700">Completed</div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo $total_enrollments > 0 ? round(($completed_courses / $total_enrollments) * 100) : 0; ?>% completion rate
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Enrollments -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300 info-card mb-6">
                    <div class="p-6 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="p-2 bg-white rounded-lg shadow-sm mr-3">
                                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            Module Enrollments
                            <span class="ml-2 bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($enrollments); ?></span>
                        </h3>
                    </div>
                    <?php if (empty($enrollments)): ?>
                        <div class="text-center py-12">
                            <div class="bg-gradient-to-br from-gray-100 to-gray-200 rounded-full w-20 h-20 mx-auto mb-6 flex items-center justify-center">
                                <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No enrollments found</h4>
                            <p class="text-gray-600">This student hasn't enrolled in any courses yet.</p>
                        </div>
                    <?php else: ?>
                        <!-- Enhanced Table Layout -->
                        <div class="overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Module</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Progress</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Enrolled</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <?php foreach ($enrollments as $index => $enrollment): ?>
                                            <?php 
                                            $progress_percentage = max(0, min(100, floatval($enrollment['completion_percentage'])));
                                            $row_bg = $index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                            ?>
                                            <tr class="<?php echo $row_bg; ?> hover:bg-blue-50 transition-all duration-200 group">
                                                <!-- Course Name -->
                                                <td class="px-6 py-4">
                                                    <div class="group-hover:translate-x-1 transition-transform duration-200">
                                                        <div class="font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($enrollment['course_name']); ?></div>
                                                        <div class="text-sm text-gray-500 line-clamp-2 bg-gray-50 rounded p-2"><?php echo htmlspecialchars(substr($enrollment['course_description'] ?? '', 0, 100)) . (strlen($enrollment['course_description'] ?? '') > 100 ? '...' : ''); ?></div>
                                                    </div>
                                                </td>
                                                
                                                <!-- Progress Circle -->
                                                <td class="px-6 py-4 text-center">
                                                    <div class="flex justify-center">
                                                        <div class="relative w-16 h-16 group-hover:scale-110 transition-transform duration-200">
                                                            <svg class="w-16 h-16 transform -rotate-90" viewBox="0 0 36 36">
                                                                <path d="M18 2.0845
                                                                    a 15.9155 15.9155 0 0 1 0 31.831
                                                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                                                    fill="none"
                                                                    stroke="#e5e7eb"
                                                                    stroke-width="2"/>
                                                                <path d="M18 2.0845
                                                                    a 15.9155 15.9155 0 0 1 0 31.831
                                                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                                                    fill="none"
                                                                    stroke="<?php echo $progress_percentage >= 80 ? '#10b981' : ($progress_percentage >= 50 ? '#f59e0b' : '#ef4444'); ?>"
                                                                    stroke-width="3"
                                                                    stroke-dasharray="<?php echo $progress_percentage; ?>, 100"/>
                                                            </svg>
                                                            <div class="absolute inset-0 flex items-center justify-center">
                                                                <span class="text-sm font-bold <?php echo $progress_percentage >= 80 ? 'text-green-600' : ($progress_percentage >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                                    <?php echo round($progress_percentage); ?>%
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <!-- Status Badge -->
                                                <td class="px-6 py-4 text-center">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium shadow-sm
                                                        <?php echo $enrollment['completion_status'] === 'completed' ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : 
                                                               ($enrollment['completion_status'] === 'in_progress' ? 'bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 border border-blue-200' : 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 border border-gray-200'); ?>">
                                                        <span class="w-2 h-2 rounded-full mr-2 <?php echo $enrollment['completion_status'] === 'completed' ? 'bg-green-500' : ($enrollment['completion_status'] === 'in_progress' ? 'bg-blue-500' : 'bg-gray-500'); ?>"></span>
                                                        <?php echo ucwords(str_replace('_', ' ', $enrollment['completion_status'])); ?>
                                                    </span>
                                                </td>
                                                
                                                <!-- Enrollment Date -->
                                                <td class="px-6 py-4 text-center">
                                                    <div class="bg-gray-50 rounded-lg p-2">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo date('M j, Y', strtotime($enrollment['enrollment_date'])); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($enrollment['enrollment_date'])); ?></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/settings-teacher.js"></script>
    <script>
        // Initialize the page with dashboard content
        document.addEventListener('DOMContentLoaded', function() {
            // Any initialization code can go here
        });
    </script>
</body>
</html>
