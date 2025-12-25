<?php
session_start();
require_once '../config/database.php';
require_once 'includes/student_profile_functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

// Get student's name and email (same pattern as other pages)
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Get student profile data for additional features
$student_profile = getStudentProfile($pdo, $_SESSION['user_id']);

// Get student preferences for display name, profile picture, and timestamps
$stmt = $pdo->prepare("SELECT display_name, profile_picture, created_at, updated_at FROM student_preferences WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$preferences = $stmt->fetch(PDO::FETCH_ASSOC);

// Merge preferences with student data
if ($preferences) {
    $student_profile['display_name'] = $preferences['display_name'] ?? '';
    $student_profile['profile_picture'] = $preferences['profile_picture'] ?? '';
    $student_profile['last_updated'] = $preferences['updated_at'] ?? $preferences['created_at'] ?? null;
} else {
    $student_profile['display_name'] = '';
    $student_profile['profile_picture'] = '';
    $student_profile['last_updated'] = null;
}

// If no preferences exist, use user creation date as fallback
if (!$student_profile['last_updated']) {
    $stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $student_profile['last_updated'] = $user_data['created_at'] ?? date('Y-m-d H:i:s');
}

// Display success/error messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard_modern.css">
    <link rel="stylesheet" href="css/student_settings.css">
    <link rel="stylesheet" href="css/student_profile.css">
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
<body class="min-h-screen font-rubik transition-colors duration-200" data-last-updated="<?php echo $student_profile['last_updated']; ?>" data-user-id="<?php echo $_SESSION['user_id']; ?>" x-data="mobileMenu()">
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
                                <?php if (!empty($student_profile['profile_picture'])): ?>
                                    <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student_profile['profile_picture']); ?>" 
                                         class="w-10 h-10 rounded-full object-cover" 
                                         alt="Profile Picture">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white font-semibold">
                                        <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="text-gray-900 dark:text-white transition-colors duration-200">
                                    <?php echo htmlspecialchars(!empty($student_profile['display_name']) ? $student_profile['display_name'] : $student['username']); ?>
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
                                <a href="../auth/logout.php" 
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
                            <?php if (!empty($student_profile['profile_picture'])): ?>
                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student_profile['profile_picture']); ?>" 
                                     class="w-10 h-10 rounded-full object-cover" 
                                     alt="Profile Picture">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white font-semibold">
                                    <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars(!empty($student_profile['display_name']) ? $student_profile['display_name'] : $student['username']); ?>
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
                    <a href="../auth/logout.php" 
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
        <div class="max-w-5xl mx-auto px-2 sm:px-4 py-4 sm:py-8" x-data="{ 
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
            <div class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 rounded-xl sm:rounded-2xl border border-red-100 dark:border-red-800 mb-4 sm:mb-8 card-hover">
                <div class="p-3 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex-1">
                            <h2 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2 sm:gap-3 rubik-bold">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-red-100 dark:bg-red-900/30 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <span class="text-sm sm:text-base md:text-lg lg:text-xl">Student Profile Settings</span>
                            </h2>
                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-300 mt-1 rubik-regular">Manage your profile information and account settings</p>
                        </div>
                        <div class="flex items-center justify-between sm:justify-end gap-4">
                            <div class="text-left sm:text-right">
                                <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                    <span class="text-xs sm:text-sm font-medium rubik-medium">All changes saved</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400" id="last-saved">Last updated: <span id="last-updated-time"><?php 
                                    if ($student_profile['last_updated']) {
                                        // Convert to Philippines timezone
                                        $date = new DateTime($student_profile['last_updated']);
                                        $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                        echo $date->format('M j, Y g:i A T');
                                    } else {
                                        echo 'Never';
                                    }
                                ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                    <!-- Success/Error Messages -->

            <!-- Display Messages -->
            <?php if ($success_message): ?>
                <div class="settings-message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="settings-message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Settings Sections -->
                <div class="space-y-4 sm:space-y-6">
                <!-- Profile Section -->
                <div id="profile-section" class="mb-4 sm:mb-6">
                    <div class="bg-white dark:bg-dark-surface rounded-xl sm:rounded-2xl shadow-sm border border-gray-200 dark:border-dark-border overflow-hidden card-hover">
                    <button type="button" @click="toggleSection('profile')" 
                            class="w-full px-3 sm:px-6 py-3 sm:py-4 text-left flex items-center justify-between hover:bg-gray-50 dark:hover:bg-dark-border transition-colors">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 bg-red-100 dark:bg-red-900/30 rounded-md sm:rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 md:w-5 md:h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-900 dark:text-white rubik-semibold">Profile Information</h3>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 rubik-regular">Update your profile and how you appear to others</p>
                            </div>
                        </div>
                        <svg :class="{ 'rotate-180': openSections.profile }" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 dark:text-gray-500 transform transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                            
                    <div x-show="openSections.profile" x-collapse class="border-t border-gray-200 dark:border-dark-border">
                        <div class="p-3 sm:p-6 space-y-4">
                            <!-- Profile Picture Section -->
                            <div class="flex flex-col sm:flex-row sm:items-center space-y-4 sm:space-y-0 sm:space-x-4">
                                <div class="relative flex-shrink-0 self-center sm:self-start">
                                    <?php if (!empty($student_profile['profile_picture'])): ?>
                                        <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student_profile['profile_picture']); ?>" 
                                             class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover shadow-md profile-picture" 
                                             alt="Profile Picture">
                                        <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-white font-bold text-sm sm:text-lg shadow-md profile-picture-placeholder" style="display: none;">
                                            <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-white font-bold text-sm sm:text-lg shadow-md profile-picture-placeholder">
                                            <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                        </div>
                                        <img class="w-12 h-12 sm:w-16 sm:h-16 rounded-full object-cover shadow-md profile-picture" style="display: none;" alt="Profile Picture">
                                    <?php endif; ?>
        <!-- Your responsive camera button -->
        <button type="button" 
                class="absolute -bottom-1 -right-1 
                       w-5 h-5 sm:w-6 sm:h-6
                       bg-red-500 hover:bg-red-600 
                       rounded-full border border-white dark:border-dark-surface 
                       flex items-center justify-center 
                       text-white hover:text-white 
                       transition-all duration-200 
                       shadow-sm hover:shadow-md
                       active:scale-95" 
                id="camera-icon-btn">
            <!-- Camera icon that scales with button size -->
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" 
                 fill="none" 
                 stroke="currentColor" 
                 viewBox="0 0 24 24" 
                 stroke-width="2.5">
                <path stroke-linecap="round" 
                      stroke-linejoin="round" 
                      d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                <path stroke-linecap="round" 
                      stroke-linejoin="round" 
                      d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
        </button>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-end space-y-3 sm:space-y-0 sm:space-x-3">
                                        <div class="flex-1 min-w-0">
                                            <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 rubik-medium">Display Name</label>
                                            <input type="text" name="display_name" id="display-name" class="w-full px-2 sm:px-3 py-2 text-sm sm:text-base border border-gray-300 dark:border-dark-border rounded-lg shadow-sm bg-white dark:bg-dark-surface text-gray-900 dark:text-white focus:border-red-500 focus:ring-red-500" placeholder="How your name appears to others" value="<?php echo htmlspecialchars($student_profile['display_name'] ?? ''); ?>">
                                        </div>
                                        <div class="text-left sm:text-right flex-shrink-0">
                                            <div class="text-xs sm:text-sm font-bold text-red-600 dark:text-red-400 px-2 sm:px-3 py-1.5 sm:py-2 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800 rubik-semibold">
                                                <?php echo htmlspecialchars(getStudentRoleDisplay($student_profile)); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" id="change-photo-btn" class="bg-red-600 text-white px-3 py-1.5 sm:py-2 rounded-lg hover:bg-red-700 transition-colors font-medium text-xs sm:text-sm rubik-medium">
                                            Change Photo
                                        </button>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 rubik-regular">JPG, PNG or GIF. Max size 2MB.</p>
                                    </div>
                                </div>
                                <input type="file" name="profile_picture" id="photo-input" accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;">
                            </div>

                            </div>
                        </div>
                    </div>

                <!-- Security Section -->
                <div id="security-section" class="mb-4 sm:mb-6">
                    <div class="bg-white dark:bg-dark-surface rounded-xl sm:rounded-2xl shadow-sm border border-gray-200 dark:border-dark-border overflow-hidden card-hover">
                    <button type="button" @click="toggleSection('security')" 
                            class="w-full px-3 sm:px-6 py-3 sm:py-4 text-left flex items-center justify-between hover:bg-gray-50 dark:hover:bg-dark-border transition-colors">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 bg-red-100 dark:bg-red-900/30 rounded-md sm:rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 md:w-5 md:h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-900 dark:text-white rubik-semibold">Security & Privacy</h3>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 rubik-regular">Manage your account security and privacy settings</p>
                            </div>
                        </div>
                        <svg :class="{ 'rotate-180': openSections.security }" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 dark:text-gray-500 transform transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div x-show="openSections.security" x-collapse class="border-t border-gray-200 dark:border-dark-border">
                        <div class="p-3 sm:p-6 space-y-4 sm:space-y-6">
                            <!-- Password Change -->
                            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg sm:rounded-xl p-3 sm:p-6 border border-red-100 dark:border-red-800">
                                <h4 class="text-sm sm:text-base font-semibold text-red-900 dark:text-red-100 mb-3 sm:mb-4 flex items-center gap-2 rubik-semibold">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                    </svg>
                                    Change Password
                                </h4>
                                <p class="text-xs sm:text-sm text-red-700 dark:text-red-200 mb-4 sm:mb-6">Update your password to keep your account secure. You'll receive an OTP verification after changing your password.</p>
                                
                                <form id="password-change-form" class="space-y-3 sm:space-y-4">
                                    <!-- Current Password -->
                                    <div class="settings-field">
                                        <label class="block text-xs sm:text-sm font-medium text-red-900 dark:text-red-100 mb-1 sm:mb-2">Current Password <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <input type="password" 
                                                   id="current-password" 
                                                   name="current_password"
                                                   class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-red-200 dark:border-red-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors bg-white dark:bg-dark-surface text-gray-900 dark:text-white" 
                                                   placeholder="Enter your current password"
                                                   required>
                                            <button type="button" 
                                                    class="absolute right-2 sm:right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                    data-input="current-password">
                                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="current-password-icon">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="text-xs text-red-600 mt-1" id="current-password-error"></div>
                                    </div>

                                    <!-- New Password -->
                                    <div class="settings-field relative">
                                        <label class="block text-xs sm:text-sm font-medium text-red-900 dark:text-red-100 mb-1 sm:mb-2">New Password <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <input type="password" 
                                                   id="new-password" 
                                                   name="new_password"
                                                   class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-red-200 dark:border-red-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors bg-white dark:bg-dark-surface text-gray-900 dark:text-white" 
                                                   placeholder="Create a strong password"
                                                   minlength="12" 
                                                   maxlength="64"
                                                   pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{12,64}"
                                                   required>
                                            <button type="button" 
                                                    class="absolute right-2 sm:right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                    data-input="new-password">
                                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="new-password-icon">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </div>
                                        
                                        <!-- Password Tooltip -->
                                        <div id="password-tooltip" class="hidden absolute z-10 bg-white dark:bg-dark-surface border border-gray-300 dark:border-dark-border rounded-lg shadow-lg p-3 sm:p-4 mt-2 w-64 sm:w-80">
                                            <p class="font-medium mb-2 text-xs sm:text-sm text-gray-900 dark:text-white">Password Requirements:</p>
                                            <ul class="list-disc pl-3 sm:pl-4 space-y-1">
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
                                        <span id="strength-text" class="text-xs text-gray-500 dark:text-gray-400 block mt-1"></span>
                                        <div class="text-xs text-red-600 mt-1" id="new-password-error"></div>
                                    </div>

                                    <!-- Confirm New Password -->
                                    <div class="settings-field">
                                        <label class="block text-xs sm:text-sm font-medium text-red-900 dark:text-red-100 mb-1 sm:mb-2">Confirm New Password <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <input type="password" 
                                                   id="confirm-new-password" 
                                                   name="confirm_new_password"
                                                   class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-red-200 dark:border-red-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors bg-white dark:bg-dark-surface text-gray-900 dark:text-white" 
                                                   placeholder="Confirm your new password"
                                                   required>
                                            <button type="button" 
                                                    class="absolute right-2 sm:right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                    data-input="confirm-new-password">
                                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" id="confirm-new-password-icon">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                            </div>
                                        <span id="password-match" class="text-xs block mt-1"></span>
                                        <div class="text-xs text-red-600 mt-1" id="confirm-password-error"></div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="flex justify-end pt-3 sm:pt-4">
                                        <button type="submit" 
                                                id="change-password-btn"
                                                class="bg-red-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base">
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                            </svg>
                                            <span id="change-password-text">Change Password</span>
                                        </button>
                                </div>
                                </form>
                            </div>

                            </div>
                        </div>
                    </div>

                <!-- Action Buttons -->
                <div class="bg-white dark:bg-dark-surface rounded-xl sm:rounded-2xl shadow-sm border border-gray-200 dark:border-dark-border p-3 sm:p-6 mt-4 sm:mt-8 card-hover">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4">
                        <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 rubik-regular text-center sm:text-left">
                            <span id="last-saved-bottom">Last updated: <span id="last-updated-time-bottom"><?php 
                                if ($student_profile['last_updated']) {
                                    // Convert to Philippines timezone
                                    $date = new DateTime($student_profile['last_updated']);
                                    $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                    echo $date->format('M j, Y g:i A T');
                                } else {
                                    echo 'Never';
                                }
                            ?></span></span>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-full sm:w-auto">
                            <button type="button" id="reset-settings" class="px-4 sm:px-6 py-2 sm:py-3 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-dark-border hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg sm:rounded-xl transition-colors font-medium flex items-center justify-center gap-2 rubik-medium text-sm sm:text-base">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Reset to Defaults
                            </button>
                            <button type="submit" id="save-settings" class="px-4 sm:px-6 py-2 sm:py-3 text-white bg-red-600 hover:bg-red-700 rounded-lg sm:rounded-xl transition-colors font-medium flex items-center justify-center gap-2 shadow-lg rubik-medium text-sm sm:text-base">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Save All Changes
                        </button>
                        </div>
                    </div>
                    </div>
                </div>
        </div>
    </main>
    
    <script src="js/student-settings.js"></script>
    <script src="js/student-password-change.js"></script>
    <style>
        /* Password strength meter styles */
        .password-strength-meter {
            background-color: #e5e7eb;
            border-radius: 0.5rem;
            height: 0.5rem;
            overflow: hidden;
        }
        
        .strength-weak {
            background-color: #ef4444;
        }
        
        .strength-medium {
            background-color: #f59e0b;
        }
        
        .strength-strong {
            background-color: #3b82f6;
        }
        
        .strength-very-strong {
            background-color: #10b981;
        }
        
        .requirement.met {
            color: #10b981;
        }
        
        .requirement.unmet {
            color: #ef4444;
        }
        
        #password-tooltip {
            position: absolute;
            z-index: 50;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 1rem;
            margin-top: 0.5rem;
            width: 20rem;
        }
        
        .dark #password-tooltip {
            background: #27272a;
            border-color: #3f3f46;
        }
    </style>
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

        // Format relative time (e.g., "2 minutes ago", "1 hour ago")
        function formatRelativeTime(timestamp) {
            if (!timestamp) return 'Never';
            
            const now = new Date();
            const past = new Date(timestamp);
            const diffMs = now - past;
            const diffSeconds = Math.floor(diffMs / 1000);
            const diffMinutes = Math.floor(diffSeconds / 60);
            const diffHours = Math.floor(diffMinutes / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffSeconds < 60) {
                return diffSeconds <= 1 ? 'Just now' : `${diffSeconds} seconds ago`;
            } else if (diffMinutes < 60) {
                return diffMinutes === 1 ? '1 minute ago' : `${diffMinutes} minutes ago`;
            } else if (diffHours < 24) {
                return diffHours === 1 ? '1 hour ago' : `${diffHours} hours ago`;
            } else if (diffDays < 7) {
                return diffDays === 1 ? '1 day ago' : `${diffDays} days ago`;
            } else {
                // For older dates, show the actual date
                return past.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: past.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
                });
            }
        }

        // Update timestamp displays
        function updateTimestampDisplays() {
            const lastUpdated = document.body.getAttribute('data-last-updated');
            if (!lastUpdated) return;
            
            const relativeTime = formatRelativeTime(lastUpdated);
            const elements = [
                document.getElementById('last-updated-time'),
                document.getElementById('last-updated-time-bottom')
            ];
            
            elements.forEach(element => {
                if (element) {
                    element.textContent = relativeTime;
                }
            });
        }

        // Initialize real-time clock and timestamp updates
        document.addEventListener('DOMContentLoaded', function() {
            updateRealTimeClock();
            updateTimestampDisplays();
            
            // Update clock every second
            setInterval(updateRealTimeClock, 1000);
            
            // Update timestamps every minute
            setInterval(updateTimestampDisplays, 60000);
        });

    </script>

    <!-- OTP Verification Modal -->
    <div id="otp-modal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="otp-modal-title" role="dialog" aria-modal="true" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <!-- Modal container -->
        <div class="flex min-h-screen items-center justify-center p-2 sm:p-4 text-center">
            <div class="relative transform overflow-hidden rounded-lg sm:rounded-xl text-left shadow-xl transition-all w-full max-w-sm sm:max-w-md bg-white dark:bg-dark-surface">
                <!-- Modal header -->
                <div class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 px-4 sm:px-6 py-3 sm:py-4 border-b border-red-200 dark:border-red-800">
                    <div class="flex items-center justify-between">
                        <h2 id="otp-modal-title" class="text-base sm:text-lg font-semibold text-red-900 dark:text-red-100 flex items-center gap-2">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <span class="text-sm sm:text-base">Verify Password Change</span>
                        </h2>
                        <button type="button" 
                                onclick="hideOTPModal()" 
                                class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal content -->
                <div class="bg-white dark:bg-dark-surface px-4 sm:px-6 py-4 sm:py-6">
                    <div class="text-center mb-4 sm:mb-6">
                        <div class="mx-auto flex items-center justify-center h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-red-100 dark:bg-red-900/30 mb-3 sm:mb-4">
                            <svg class="h-5 w-5 sm:h-6 sm:w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-medium text-gray-900 dark:text-white mb-2">Check Your Email</h3>
                        <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                            We've sent a verification code to your email address. Please enter the code below to complete your password change.
                        </p>
                    </div>

                    <form id="otp-verification-form" class="space-y-3 sm:space-y-4">
                        <div>
                            <label for="otp-code" class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 sm:mb-2">
                                Verification Code
                            </label>
                            <input type="text" 
                                   id="otp-code" 
                                   name="otp_code"
                                   class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-center tracking-widest bg-white dark:bg-dark-surface text-gray-900 dark:text-white" 
                                   placeholder="Enter 6-digit code"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   required>
                        </div>

                        <div class="flex items-center justify-between text-xs sm:text-sm">
                            <button type="button" 
                                    id="resend-otp-btn"
                                    class="text-red-600 hover:text-red-700 font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                Resend Code
                            </button>
                            <span id="otp-timer" class="text-gray-500 dark:text-gray-400"></span>
                        </div>

                        <div id="otp-error" class="text-xs sm:text-sm text-red-600 text-center"></div>

                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                            <button type="button" 
                                    onclick="hideOTPModal()"
                                    class="flex-1 bg-gray-100 dark:bg-dark-border text-gray-700 dark:text-gray-300 px-3 sm:px-4 py-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors font-medium text-sm sm:text-base">
                                Cancel
                            </button>
                            <button type="submit" 
                                    id="verify-otp-btn"
                                    class="flex-1 bg-red-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-red-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base">
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