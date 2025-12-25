<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header("Location: dashboard.php");
    exit();
}

// Fetch course information
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               cp.completion_percentage,
               cp.completion_status,
               cp.updated_at as completed_at
        FROM courses c
        LEFT JOIN course_progress cp ON c.id = cp.course_id AND cp.student_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header("Location: dashboard.php");
        exit();
    }

    // Mark course as completed if not already done
    if ($course['completion_status'] !== 'completed') {
        $update_stmt = $pdo->prepare("
            UPDATE course_progress 
            SET completion_status = 'completed',
                completion_percentage = 100,
                updated_at = NOW()
            WHERE course_id = ? AND student_id = ?
        ");
        $update_stmt->execute([$course_id, $_SESSION['user_id']]);
    }

} catch (PDOException $e) {
    error_log("Database error in congratulations.php: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Congratulations! - AiToManabi</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'brand-red': '#C8102E',
                        'dark-bg': '#0f0f0f',
                        'dark-card': '#1a1a1a',
                        'dark-border': '#2a2a2a'
                    }
                }
            }
        }
    </script>
    
    <!-- Custom CSS -->
    <style>
        .rubik-light { font-family: "Rubik", sans-serif; font-weight: 300; }
        .rubik-regular { font-family: "Rubik", sans-serif; font-weight: 400; }
        .rubik-medium { font-family: "Rubik", sans-serif; font-weight: 500; }
        .rubik-semibold { font-family: "Rubik", sans-serif; font-weight: 600; }
        .rubik-bold { font-family: "Rubik", sans-serif; font-weight: 700; }
        
        .celebration-animation {
            animation: celebration 0.8s ease-out;
        }
        
        @keyframes celebration {
            0% { transform: scale(0.5) rotate(-5deg); opacity: 0; }
            50% { transform: scale(1.1) rotate(2deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-dark-bg transition-colors min-h-screen">
    <!-- Main Content -->
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full">
            <!-- Simple Congratulations Card -->
            <div class="bg-white dark:bg-dark-card rounded-xl shadow-lg p-6 celebration-animation border border-gray-200 dark:border-dark-border text-center">
                <!-- Success Icon -->
                <div class="mb-4">
                    <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Congratulations Text -->
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 rubik-bold">
                    🎉 Congratulations!
                </h1>
                
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4 rubik-medium">
                    You have successfully completed
                </p>
                
                <!-- Course Title -->
                <div class="bg-brand-red/10 dark:bg-brand-red/20 rounded-lg p-3 mb-4 border border-brand-red/20">
                    <h2 class="text-lg font-bold text-brand-red dark:text-red-400 rubik-bold">
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h2>
                </div>

                <!-- Simple Achievement -->
                <div class="bg-gray-50 dark:bg-dark-bg rounded-lg p-3 mb-4">
                    <div class="text-lg font-bold text-brand-red dark:text-red-400 rubik-bold">100% Complete</div>
                    <div class="text-xs text-gray-600 dark:text-gray-400 rubik-medium">Course Finished</div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2">
                    <a href="dashboard.php" 
                       class="block w-full px-4 py-2 text-sm font-medium text-white bg-brand-red hover:bg-red-700 rounded-lg transition-colors duration-200 rubik-medium">
                        Back to Dashboard
                    </a>
                    
                    <a href="view_course.php" 
                       class="block w-full px-4 py-2 text-sm font-medium text-brand-red bg-white dark:bg-dark-card border border-brand-red hover:bg-brand-red hover:text-white rounded-lg transition-colors duration-200 rubik-medium">
                        Explore More Courses
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Dark Mode Toggle -->
    <button onclick="toggleDarkMode()" 
            class="fixed top-4 right-4 p-2 bg-white dark:bg-dark-card rounded-full shadow-lg border border-gray-200 dark:border-dark-border text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white transition-colors z-20">
        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
    </button>

    <script>
        // Dark mode functionality
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }

        // Initialize dark mode
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        });
    </script>
</body>
</html>
