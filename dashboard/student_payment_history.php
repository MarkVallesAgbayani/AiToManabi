<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
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

// Fetch payment history (robust join, similar to admin.php logic)
$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        u.username as student_name,
        u.email as student_email,
        c.title as course_title,
        pd.course_name as payment_course_name
    FROM payments p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN courses c ON p.course_id = c.id
    LEFT JOIN payment_details pd ON p.id = pd.payment_id
    WHERE p.user_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" x-data :class="{ 'dark': $store.darkMode.on }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Japanese Learning Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard_modern.css">
    <link rel="stylesheet" href="css/student_payment_history.css">
    <link rel="stylesheet" href="css/reports-payment.css">
    <script src="js/student_payment_history.js" defer></script>
    <script src="js/reports-payment.js" defer></script>
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
        
        /* Modern navigation styles */
        .modern-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(239, 68, 68, 0.1);
        }
        .dark .modern-nav {
            background: rgba(24, 24, 27, 0.95);
            border-bottom: 1px solid rgba(239, 68, 68, 0.2);
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
<body class="min-h-screen font-rubik transition-colors duration-200" data-user-id="<?php echo $_SESSION['user_id']; ?>" x-data="mobileMenu()">
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
        <div class="max-w-4xl mx-auto px-4 py-10">
            <div class="mb-8 text-center">
                <h1 class="text-3xl md:text-4xl font-extrabold text-primary dark:text-white tracking-tight rubik-bold">Payment History</h1>
            </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Desktop Table View -->
        <div class="hidden md:block bg-white dark:bg-dark-surface rounded-2xl shadow-lg overflow-x-auto card-hover">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
                <thead class="bg-gray-50 dark:bg-dark-border">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider rubik-semibold">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider rubik-semibold">Module</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider rubik-semibold">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider rubik-semibold">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider rubik-semibold">Invoice</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-dark-surface divide-y divide-gray-100 dark:divide-dark-border">
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-dark-border transition-colors" data-payment-id="<?php echo $payment['id']; ?>">
                                <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 rubik-regular">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-900 dark:text-white font-medium rubik-medium">
                                    <?php 
                                        // Prefer course_title, fallback to payment_course_name
                                        echo htmlspecialchars($payment['course_title'] ?? $payment['payment_course_name'] ?? ''); 
                                    ?>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap text-sm text-gray-900 dark:text-white font-semibold rubik-semibold">
                                    <?php
                                        if (strtoupper($payment['payment_type']) === 'FREE') {
                                            echo 'Free';
                                        } else {
                                            echo '₱' . number_format($payment['amount'], 2);
                                        }
                                    ?>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full rubik-semibold
                                        <?php echo $payment['payment_status'] === 'completed' ? 'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400' : 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400'; ?>">
                                        <?php 
                                            if ($payment['payment_status'] === 'completed') {
                                                echo (strtoupper($payment['payment_type']) === 'FREE') ? 'FREE' : 'PAID';
                                            } else {
                                                echo ucfirst($payment['payment_status']);
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap text-sm">
                                    <?php if ($payment['payment_status'] === 'completed'): ?>
                                        <?php if ($payment['payment_type'] === 'FREE'): ?>
                                            <span class="text-gray-400 dark:text-gray-500 italic rubik-regular">Free Course</span>
                                        <?php else: ?>
                                            <a href="reports-payment.php?payment_id=<?php echo $payment['id']; ?>&user_id=<?php echo $_SESSION['user_id']; ?>"
                                               class="bg-primary hover:bg-red-600 text-white font-semibold px-4 py-2 rounded-lg shadow transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rubik-semibold"
                                               data-payment-id="<?php echo $payment['id']; ?>"
                                               data-user-id="<?php echo $_SESSION['user_id']; ?>"
                                               target="_blank">
                                                Download Invoice
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400 dark:text-gray-500 italic rubik-regular">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-16 text-gray-400 dark:text-gray-500 text-lg font-medium rubik-medium">
                                <div class="flex flex-col items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-primary mb-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                                    </svg>
                                    No payment history found.<br>
                                    <span class="text-sm text-gray-300 dark:text-gray-600 rubik-regular">You haven't made any payments yet.</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="md:hidden space-y-4">
            <?php if (count($payments) > 0): ?>
                <?php foreach ($payments as $payment): ?>
                    <div class="bg-white dark:bg-dark-surface rounded-xl shadow-lg p-5 border border-gray-200 dark:border-gray-700 card-hover">
                        <div class="space-y-4">
                            <!-- Date and Status Row -->
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-500 dark:text-gray-400 rubik-regular">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </div>
                                <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full rubik-semibold
                                    <?php echo $payment['payment_status'] === 'completed' ? 'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400' : 'bg-yellow-100 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400'; ?>">
                                    <?php 
                                        if ($payment['payment_status'] === 'completed') {
                                            echo (strtoupper($payment['payment_type']) === 'FREE') ? 'FREE' : 'PAID';
                                        } else {
                                            echo ucfirst($payment['payment_status']);
                                        }
                                    ?>
                                </span>
                            </div>
                            
                            <!-- Module Name -->
                            <div class="text-lg font-medium text-gray-900 dark:text-white rubik-medium">
                                <?php echo htmlspecialchars($payment['course_title'] ?? $payment['payment_course_name'] ?? ''); ?>
                            </div>
                            
                            <!-- Amount -->
                            <div class="text-xl font-semibold text-gray-900 dark:text-white rubik-semibold">
                                <?php
                                    if (strtoupper($payment['payment_type']) === 'FREE') {
                                        echo 'Free';
                                    } else {
                                        echo '₱' . number_format($payment['amount'], 2);
                                    }
                                ?>
                            </div>
                            
                            <!-- Invoice Action -->
                            <div class="pt-2">
                                <?php if ($payment['payment_status'] === 'completed'): ?>
                                    <?php if ($payment['payment_type'] === 'FREE'): ?>
                                        <span class="text-gray-400 dark:text-gray-500 italic rubik-regular text-sm">Free Course</span>
                                    <?php else: ?>
                                        <a href="reports-payment.php?payment_id=<?php echo $payment['id']; ?>&user_id=<?php echo $_SESSION['user_id']; ?>"
                                           class="inline-block w-full bg-primary hover:bg-red-600 text-white font-semibold px-4 py-3 rounded-lg shadow transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rubik-semibold text-center"
                                           data-payment-id="<?php echo $payment['id']; ?>"
                                           data-user-id="<?php echo $_SESSION['user_id']; ?>"
                                           target="_blank">
                                            Download Invoice
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 dark:text-gray-500 italic rubik-regular text-sm">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white dark:bg-dark-surface rounded-xl shadow-lg p-10 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                        </svg>
                        <div class="text-gray-400 dark:text-gray-500 text-lg font-medium rubik-medium">
                            No payment history found.
                        </div>
                        <div class="text-sm text-gray-300 dark:text-gray-600 rubik-regular">
                            You haven't made any payments yet.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>