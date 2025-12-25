<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../../config/database.php';
require_once 'preview_middleware.php';

// Check if course ID is provided
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$preview_mode = isPreviewMode();
$preview_access_mode = getPreviewAccessMode();

if (!$course_id || !$preview_mode) {
    header("Location: teacher_create_module.php");
    exit();
}

// Verify teacher owns this course or has permission to preview
$course = canPreviewCourse($pdo, $course_id, $_SESSION['user_id']);

if (!$course) {
    header("Location: teacher_create_module.php?error=course_not_found");
    exit();
}

// Get teacher profile for display
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch();

// Get sections and chapters
$stmt = $pdo->prepare("
    SELECT 
        s.id as section_id,
        s.title as section_title,
        s.description as section_description,
        s.order_index as section_order
    FROM sections s
    WHERE s.course_id = ?
    ORDER BY s.order_index ASC, s.id ASC
");
$stmt->execute([$course_id]);
$sections_data = $stmt->fetchAll();

// Initialize sections array
$sections = [];

// Process each section and fetch its chapters
foreach ($sections_data as $section_row) {
    $section_id = $section_row['section_id'];
    
    // In preview mode, we don't check actual progress - just simulate it
    $is_completed = false; // Always show as not completed in preview
    
    // Initialize section
    $sections[$section_id] = [
        'id' => $section_id,
        'title' => $section_row['section_title'],
        'description' => $section_row['section_description'] ?? '',
        'order_index' => $section_row['section_order'],
        'is_completed' => $is_completed,
        'chapters' => []
    ];
    
    // Fetch chapters for this specific section
    $chapter_stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            description,
            content,
            content_type,
            order_index,
            video_url,
            video_type,
            video_file_path
        FROM chapters
        WHERE section_id = ?
        ORDER BY order_index ASC, id ASC
    ");
    $chapter_stmt->execute([$section_id]);
    $chapters = $chapter_stmt->fetchAll();
    
    // Process chapters
    foreach ($chapters as $chapter) {
        $sections[$section_id]['chapters'][] = [
            'id' => $chapter['id'],
            'title' => $chapter['title'],
            'description' => $chapter['description'],
            'content' => $chapter['content'],
            'content_type' => $chapter['content_type'],
            'order_index' => $chapter['order_index'],
            'video_url' => $chapter['video_url'],
            'video_type' => $chapter['video_type'],
            'video_file_path' => $chapter['video_file_path'],
            'is_completed' => false // Always show as not completed in preview
        ];
    }
    
    // Check if section has quiz
    $quiz_stmt = $pdo->prepare("SELECT id, title, description, passing_score FROM quizzes WHERE section_id = ?");
    $quiz_stmt->execute([$section_id]);
    $quiz = $quiz_stmt->fetch();
    
    if ($quiz) {
        $sections[$section_id]['quiz'] = [
            'id' => $quiz['id'],
            'title' => $quiz['title'],
            'description' => $quiz['description'],
            'passing_score' => $quiz['passing_score'],
            'is_completed' => false // Always show as not completed in preview
        ];
        $sections[$section_id]['has_quiz'] = true;
    } else {
        $sections[$section_id]['has_quiz'] = false;
    }
}

// Sort sections by order_index
usort($sections, function($a, $b) {
    return $a['order_index'] <=> $b['order_index'];
});

// Set the first section as active by default
$active_section = !empty($sections) ? $sections[0] : null;
$active_content = $active_section ? $active_section['id'] : null;
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?php echo htmlspecialchars($course['title']); ?> - AITOMANABI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../css/continue_learning.css" rel="stylesheet">

    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    'brand-red': '#FF0000',
                    'brand-light': '#FF4B4B',
                    'brand-dark': '#CC0000',
                    'dark-bg': '#1F2937',
                    'dark-surface': '#374151',
                    'dark-border': '#4B5563',
                    'dark-text-primary': '#F3F4F6',
                    'dark-text-secondary': '#D1D5DB',
                    'dark-text-muted': '#9CA3AF',
                    'dark-text-link': '#93C5FD'
                },
                fontFamily: {
                    'comfortaa': ['"Comfortaa"', 'sans-serif'],
                    'japanese': ['"Noto Sans JP"', 'sans-serif'],
                    'mincho': ['"Sawarabi Mincho"', 'serif'],
                    'rubik': ['"Rubik"', 'sans-serif']
                }
            }
        }
    }
    </script>
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* Preview Banner Styles */
        .preview-banner {
            background: linear-gradient(135deg, #FF0000, #CC0000);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .preview-banner .toggle-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .preview-banner .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .preview-banner .toggle-btn.active {
            background: rgba(255, 255, 255, 0.9);
            color: #FF0000;
        }
        
        /* Course content styling */
        .course-card {
            transition: all 0.3s ease;
        }
        
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .section-card {
            border-left: 4px solid #FF0000;
        }
        
        .chapter-item {
            transition: all 0.2s ease;
        }
        
        .chapter-item:hover {
            background-color: #fef2f2;
        }
        
        .quiz-item {
            background: linear-gradient(135deg, #fef2f2, #fce7f3);
            border: 1px solid #fecdd3;
        }
        
        /* Ensure clickable areas are properly styled */
        .chapter-item, .quiz-item {
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        
        .chapter-item:hover, .quiz-item:hover {
            transform: translateX(2px);
        }
        
        .chapter-item:active, .quiz-item:active {
            transform: translateX(1px);
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-black transition-colors">
    <!-- Preview Mode Banner -->
    <div class="preview-banner fixed top-0 left-0 right-0 z-50 text-white p-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-eye text-lg"></i>
                    <span class="font-semibold">Preview Mode</span>
                </div>
                <div class="text-sm opacity-90">
                    You are viewing this course as a student would see it
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Preview Mode Toggle -->
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium">Access Mode:</span>
                    <button 
                        @click="updatePreviewMode('enrolled')"
                        :class="previewAccessMode === 'enrolled' ? 'active' : ''"
                        class="toggle-btn px-3 py-1 rounded-full text-xs font-medium transition-all duration-200">
                        As Enrolled Student
                    </button>
                    <button 
                        @click="updatePreviewMode('all')"
                        :class="previewAccessMode === 'all' ? 'active' : ''"
                        class="toggle-btn px-3 py-1 rounded-full text-xs font-medium transition-all duration-200">
                        All Access
                    </button>
                </div>
                
                <!-- Exit Preview Button -->
                <a href="../teacher_create_module.php?id=<?php echo $course_id; ?>" 
                   class="bg-white text-brand-red px-4 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors duration-200 flex items-center space-x-2">
                    <i class="fas fa-times"></i>
                    <span>Exit Preview</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="fixed top-16 right-0 left-0 z-40 bg-white dark:bg-black shadow-lg border-b border-gray-200 dark:border-dark-border h-16">
        <div class="max-w-full px-4 mx-auto h-full">
            <div class="flex justify-between items-center h-full">
                <!-- Logo -->
                <a href="../dashboard/dashboard.php" class="flex items-center">
                    <span class="text-2xl font-bold rubik-bold text-brand-red dark:text-[#9B3922]">AiToManabi</span>
                </a>

                <!-- Right side controls -->
                <div class="flex items-center space-x-4">
                    <!-- Dark mode toggle -->
                    <button onclick="toggleDarkMode()" 
                            class="p-2 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-border transition-colors duration-200">
                        <svg class="w-6 h-6 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg class="w-6 h-6 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>

                    <!-- User Profile -->
                    <div class="relative" x-data="{ userMenuOpen: false }">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex items-center space-x-3 p-2 rounded-md hover:bg-gray-100 dark:hover:bg-dark-border transition-colors duration-200">
                            <div class="w-8 h-8 rounded-full bg-brand-red text-white flex items-center justify-center">
                                <?php echo strtoupper(substr($teacher['username'], 0, 1)); ?>
                            </div>
                            <span class="text-sm font-medium rubik-medium text-gray-700 dark:text-white"><?php echo htmlspecialchars($teacher['username']); ?></span>
                        </button>
                        <!-- Dropdown menu -->
                        <div x-show="userMenuOpen" 
                             @click.away="userMenuOpen = false"
                             class="absolute right-0 mt-2 w-48 bg-white dark:bg-dark-surface rounded-lg shadow-lg py-1 border border-gray-200 dark:border-dark-border">
                            <a href="../teacher.php" class="block px-4 py-2 text-sm rubik-regular text-gray-700 dark:text-white hover:bg-gray-100 dark:hover:bg-dark-border transition-colors duration-200">
                                Dashboard
                            </a>
                            <a href="../teacher_create_module.php" class="block px-4 py-2 text-sm rubik-regular text-gray-700 dark:text-white hover:bg-gray-100 dark:hover:bg-dark-border transition-colors duration-200">
                                Course Editor
                            </a>
                            <hr class="my-1 border-gray-200 dark:border-gray-700">
                            <a href="../auth/logout.php" class="block px-4 py-2 text-sm rubik-regular text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Layout -->
    <div class="flex min-h-screen pt-32" x-data="coursePreview()" x-init="init()">

        <!-- Sidebar -->
        <aside class="fixed top-32 left-0 h-[calc(100vh-8rem)] bg-white dark:bg-black border-r border-gray-200 dark:border-dark-border transition-all duration-300 ease-in-out z-30"
               :class="{ 'w-64': !sidebarCollapsed, 'w-30': sidebarCollapsed, '-translate-x-full md:translate-x-0': true }">
            
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-200 dark:border-dark-border flex items-center justify-between">
                <div class="flex-1 overflow-hidden" x-show="!sidebarCollapsed">
                    <h2 class="text-lg font-bold rubik-bold text-gray-900 dark:text-white truncate">
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Course > Modules</p>
                </div>  
                
                <!-- Toggle Button -->
                <button @click="sidebarCollapsed = !sidebarCollapsed" 
                        class="p-1 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-dark-border transition-colors duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         :class="{ 'transform rotate-180': sidebarCollapsed }">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <!-- Module Content Section -->
            <div class="mt-2 px-4">
                <!-- Module Progress Section -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold rubik-semibold text-gray-800 dark:text-white mb-4" x-show="!sidebarCollapsed">Module Progress</h3>
                    
                    <!-- Module Progress Bar -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4 mb-4" x-show="!sidebarCollapsed">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium rubik-medium text-gray-600 dark:text-gray-300">Overall Progress</span>
                            <span class="text-sm font-medium rubik-medium text-brand-red dark:text-brand-light">Preview Mode</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                            <div class="bg-brand-red h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Hierarchical Section Navigation -->
                    <div class="space-y-3" x-data="{ expandedSections: {} }">
                        <?php 
                        $section_index = 0;
                        foreach ($sections as $section): 
                            $section_index++;
                            
                            // Status classes for preview mode
                            $dot_classes = "bg-gray-300 dark:bg-gray-600 border-gray-300 dark:border-gray-600";
                            $icon_classes = "text-gray-600 dark:text-gray-400";
                            $text_classes = "text-gray-600 dark:text-gray-400";
                            $status_icon = $section_index;
                        ?>
                        
                        <!-- Section Container -->
                        <div class="section-card bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden" 
                             x-show="!sidebarCollapsed">
                            
                            <!-- Section Header (Clickable to expand/collapse only) -->
                            <div class="section-header p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                 @click="expandedSections[<?php echo $section['id']; ?>] = !expandedSections[<?php echo $section['id']; ?>]">
                                
                                <div class="flex items-center justify-between">
                                    <!-- Section Info -->
                                    <div class="flex items-center flex-1 min-w-0">
                                        <!-- Status Indicator -->
                                        <div class="flex items-center justify-center w-8 h-8 rounded-full border-2 <?php echo $dot_classes; ?> mr-3 flex-shrink-0">
                                            <span class="text-sm font-bold rubik-bold <?php echo $icon_classes; ?>">
                                                <?php echo $status_icon; ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Section Details -->
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-semibold rubik-semibold <?php echo $text_classes; ?> truncate">
                                                <?php echo htmlspecialchars($section['title']); ?>
                                            </h4>
                                            
                                            <!-- Progress Bar -->
                                            <div class="mt-2 flex items-center space-x-2">
                                                <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full">
                                                    <div class="h-1.5 rounded-full transition-all duration-300 bg-gray-400" style="width: 0%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo count($section['chapters']); ?>/<?php echo count($section['chapters']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Expand/Collapse Arrow -->
                                    <div class="ml-3 flex-shrink-0">
                                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" 
                                             :class="{'transform rotate-180': expandedSections[<?php echo $section['id']; ?>]}"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Expandable Content (Chapters + Quiz) -->
                            <div x-show="expandedSections[<?php echo $section['id']; ?>]" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 max-h-0"
                                 x-transition:enter-end="opacity-100 max-h-96"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 max-h-96"
                                 x-transition:leave-end="opacity-0 max-h-0"
                                 class="border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 overflow-hidden">
                                
                                <!-- Chapters List -->
                                <?php if (!empty($section['chapters'])): ?>
                                    <div class="p-2">
                                        <div class="space-y-1">
                                            <?php
                                            // Sort chapters by order_index
                                            $section_chapters = $section['chapters'];
                                            usort($section_chapters, function($a, $b) {
                                                return $a['order_index'] - $b['order_index'];
                                            });
                                            
                                            foreach ($section_chapters as $chapter_index => $chapter):
                                            ?>
                                                <div @click="selectContent(<?php echo $section['id']; ?>, 'chapter', <?php echo $chapter['id']; ?>)"
                                                    class="chapter-item w-full flex items-center py-2 px-3 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-md transition-colors duration-200 cursor-pointer"
                                                    :class="{'bg-gray-100 dark:bg-gray-600 ring-2 ring-brand-red': activeContent === <?php echo $section['id']; ?> && activeType === 'chapter' && activeChapterId === <?php echo $chapter['id']; ?>}">
                                                    
                                                    <!-- Chapter Number -->
                                                    <span class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-500 text-gray-600 dark:text-gray-300 text-xs font-medium rubik-medium flex items-center justify-center mr-3 flex-shrink-0">
                                                        <?php echo $chapter_index + 1; ?>
                                                    </span>
                                                    
                                                    <!-- Chapter Icon -->
                                                    <?php if ($chapter['content_type'] === 'video'): ?>
                                                        <svg class="w-4 h-4 mr-2 text-brand-red flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg class="w-4 h-4 mr-2 text-brand-red flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0v12h8V4H6z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Chapter Title -->
                                                    <span class="flex-1 text-left truncate">
                                                        <?php echo htmlspecialchars($chapter['title']); ?>
                                                    </span>
                                                    
                                                    <!-- Chapter Type Badge -->
                                                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full flex-shrink-0 <?php 
                                                        echo $chapter['content_type'] === 'video' 
                                                            ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200' 
                                                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200';
                                                    ?>">
                                                        <?php echo ucfirst($chapter['content_type']); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Quiz Section -->
                                <?php if ($section['has_quiz']): ?>
                                    <div class="border-t border-gray-200 dark:border-gray-600 p-2">
                                        <div @click="selectContent(<?php echo $section['id']; ?>, 'quiz', <?php echo $section['quiz']['id']; ?>)"
                                            class="quiz-item w-full flex items-center py-2 px-3 text-sm text-gray-600 dark:text-gray-300 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 rounded-md transition-colors duration-200 cursor-pointer"
                                            :class="{'bg-yellow-50 dark:bg-yellow-900/30 ring-2 ring-yellow-500': activeContent === <?php echo $section['id']; ?> && activeType === 'quiz'}">
                                            
                                            <!-- Quiz Icon -->
                                            <div class="w-6 h-6 rounded-full bg-yellow-500 text-white text-xs font-medium rubik-medium flex items-center justify-center mr-3 flex-shrink-0">
                                                ?
                                            </div>
                                            
                                            <svg class="w-4 h-4 mr-2 text-yellow-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                            </svg>
                                            
                                            <span class="flex-1 text-left">Section Quiz</span>
                                            
                                            <span class="ml-2 px-2 py-0.5 text-xs bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200 rounded-full flex-shrink-0">
                                                Quiz
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Collapsed View -->
                        <div x-show="sidebarCollapsed" class="flex justify-center">
                            <div class="w-8 h-8 rounded-full border-2 <?php echo $dot_classes; ?> flex items-center justify-center hover:scale-105 transition-transform"
                                 @click="expandedSections[<?php echo $section['id']; ?>] = !expandedSections[<?php echo $section['id']; ?>]; sidebarCollapsed = false">
                                <span class="text-sm font-bold rubik-bold <?php echo $icon_classes; ?>">
                                    <?php echo $status_icon; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 ml-0 md:ml-64 transition-all duration-300 ease-in-out" :class="{ 'md:ml-16': sidebarCollapsed }">
            <div class="min-h-[calc(100vh-8rem)] overflow-y-auto">
                <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
                    <!-- Section Header -->
                    <div class="mb-6 text-left" x-data="{ 
                        currentSection: activeContent,
                        currentChapter: activeChapterId
                    }" x-init="
                        $watch('activeContent', value => {
                            currentSection = value;
                        });
                        $watch('activeChapterId', value => {
                            currentChapter = value;
                        });
                    ">
                        <!-- Only show section title when viewing actual chapter content -->
                        <?php foreach ($sections as $section): ?>
                            <div x-show="currentSection === <?php echo $section['id']; ?> && activeType === 'chapter'">
                                <h1 class="section-page-title">
                                    <?php echo htmlspecialchars($section['title']); ?>
                                </h1>
                                <div class="section-page-description">
                                    <?php echo nl2br(htmlspecialchars($section['description'] ?? '')); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Content Card -->
                    <div class="bg-white dark:bg-black rounded-lg shadow-lg border border-gray-200 dark:border-dark-border relative">
                        <div class="p-6">
                            <div class="prose dark:prose-invert max-w-none">
                                <div id="content-area"
                                     class="min-h-[50vh] text-lg leading-relaxed text-gray-700 dark:text-gray-300"
                                     x-data="{ 
                                         currentSection: activeContent,
                                         currentChapter: activeChapterId,
                                         quizError: false,
                                         quizLoading: true,
                                         quizData: null,
                                         results: null
                                     }"
                                     x-init="
                                        $watch('activeContent', value => {
                                            currentSection = value;
                                        });
                                        $watch('activeChapterId', value => {
                                            currentChapter = value;
                                        });
                                     ">

                                    <!-- Show Title and Content Only if Quiz is Hidden -->
                                    <div x-show="!showQuiz">
                                        <!-- Display individual chapter when chapter is selected -->
                                        <div x-show="activeType === 'chapter'">
                                            <?php foreach ($sections as $section): ?>
                                                <?php foreach ($section['chapters'] as $chapter): ?>
                                                    <div x-show="activeContent === <?php echo $section['id']; ?> && activeType === 'chapter' && activeChapterId === <?php echo $chapter['id']; ?>"
                                                         x-transition:enter="transition ease-out duration-300"
                                                         x-transition:enter-start="opacity-0 transform scale-95"
                                                         x-transition:enter-end="opacity-100 transform scale-100"
                                                         x-transition:leave="transition ease-in duration-200"
                                                         x-transition:leave-start="opacity-100 transform scale-100"
                                                         x-transition:leave-end="opacity-0 transform scale-95">
                                                        <!-- Chapter Header -->
                                                        <div class="chapter-header mb-6">
                                                            <h1 class="chapter-title">
                                                                <?php echo htmlspecialchars($chapter['title']); ?>
                                                            </h1>
                                                            <?php if (!empty($chapter['description'])): ?>
                                                                <p class="chapter-description">
                                                                    <?php echo htmlspecialchars($chapter['description']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Display content based on content_type -->
                                                        <?php if ($chapter['content_type'] === 'video' && !empty($chapter['video_type'])): ?>
                                                            <!-- Video Content Section -->
                                                            <div class="video-section mb-8">
                                                                <h3 class="content-section-title">
                                                                    <svg class="content-icon" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                                                    </svg>
                                                                    Video Content
                                                                </h3>
                                                                
                                                                <div class="video-container relative">
                                                                    <div class="aspect-video bg-gradient-to-br from-gray-800 to-gray-900 rounded-lg flex items-center justify-center relative overflow-hidden">
                                                                        <!-- Video Thumbnail/Preview -->
                                                                        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
                                                                        
                                                                        <!-- Video Info -->
                                                                        <div class="relative z-10 text-white text-center p-6">
                                                                            <div class="mb-4">
                                                                                <i class="fas fa-video text-5xl text-brand-red mb-3"></i>
                                                                                <h4 class="text-xl font-semibold mb-2">Video Content Preview</h4>
                                                                                <p class="text-sm text-gray-300 mb-4">
                                                                                    <?php echo $chapter['video_type'] === 'url' ? 'External Video URL' : 'Uploaded Video File'; ?>
                                                                                </p>
                                                                            </div>
                                                                            
                                                                            <!-- Professional Notice -->
                                                                            <div class="bg-yellow-600 bg-opacity-90 rounded-lg p-4 mb-4 max-w-md mx-auto">
                                                                                <div class="flex items-center justify-center mb-2">
                                                                                    <i class="fas fa-exclamation-triangle text-yellow-200 text-lg mr-2"></i>
                                                                                    <span class="font-semibold text-yellow-100">Preview Notice</span>
                                                                                </div>
                                                                                <p class="text-sm text-yellow-100">
                                                                                    This video cannot be played in preview mode. Students will be able to watch the full video content when enrolled.
                                                                                </p>
                                                                            </div>
                                                                            
                                                                            <!-- Video Details -->
                                                                            <div class="text-xs text-gray-400 space-y-1">
                                                                                <p><strong>Video Type:</strong> <?php echo ucfirst($chapter['video_type']); ?></p>
                                                                                <?php if ($chapter['video_type'] === 'url' && !empty($chapter['video_url'])): ?>
                                                                                    <p><strong>Source:</strong> External URL</p>
                                                                                <?php elseif ($chapter['video_type'] === 'upload' && !empty($chapter['video_file_path'])): ?>
                                                                                    <p><strong>File:</strong> <?php echo htmlspecialchars($chapter['video_file_path']); ?></p>
                                                                                <?php endif; ?>
                                                                                <p><strong>Access Mode:</strong> <?php echo $preview_access_mode === 'all' ? 'Full Access' : 'Enrolled Student View'; ?></p>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <!-- Preview Mode Overlay -->
                                                                        <div class="absolute top-4 right-4">
                                                                            <div class="bg-brand-red text-white px-3 py-1 rounded-full text-xs font-semibold shadow-lg">
                                                                                <i class="fas fa-eye mr-1"></i>
                                                                                PREVIEW
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                        <?php elseif ($chapter['content_type'] === 'text'): ?>
                                                            <!-- Text Content Section -->
                                                            <div class="modern-content-card"
                                                                 data-content-type="text"
                                                                 data-chapter-id="<?php echo $chapter['id']; ?>"
                                                                 data-section-id="<?php echo $section['id']; ?>"
                                                                 data-course-id="<?php echo $course_id; ?>">
                                                                <div class="content-card-header">
                                                                    <div class="content-icon-wrapper">
                                                                        <svg class="content-card-icon" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0v12h8V4H6z" clip-rule="evenodd"></path>
                                                                            <path fill-rule="evenodd" d="M8 6a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1zm0 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1zm0 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                                                        </svg>
                                                                    </div>
                                                                    <h3 class="rubik-semibold content-card-title">
                                                                        Reading Material
                                                                    </h3>
                                                                    <!-- Preview Mode Notice for Text Content -->
                                                                    <div class="ml-auto">
                                                                        <div class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-semibold flex items-center">
                                                                            <i class="fas fa-eye mr-1"></i>
                                                                            PREVIEW
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="content-card-body">
                                                                    <?php if (!empty($chapter['content'])): ?>
                                                                        <div class="reading-content">
                                                                            <?php echo $chapter['content']; ?>
                                                                        </div>
                                                                        
                                                                        <!-- Preview Notice for Text Content -->
                                                                        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                                                                            <div class="flex items-start">
                                                                                <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-2"></i>
                                                                                <div class="text-sm text-blue-700 dark:text-blue-300">
                                                                                    <p class="font-medium mb-1">Preview Mode Notice</p>
                                                                                    <p>This is how the text content will appear to students. In preview mode, progress tracking is disabled.</p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div class="empty-content-modern">
                                                                            <div class="empty-animation">
                                                                                <div class="pulse-loader">
                                                                                    <div class="pulse-dot"></div>
                                                                                    <div class="pulse-dot"></div>
                                                                                    <div class="pulse-dot"></div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="empty-content-text-modern">
                                                                                <h4 class="rubik-semibold empty-title">Content Coming Soon</h4>
                                                                                <p class="rubik-regular empty-description">This reading material is being prepared and will be available shortly.</p>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Quiz Content -->
                                    <div x-show="activeType === 'quiz' && showQuiz" 
                                         class="quiz-content"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95">
                                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-6">
                                            <div class="flex items-center space-x-3 mb-4">
                                                <i class="fas fa-question-circle text-yellow-600 dark:text-yellow-400 text-xl"></i>
                                                <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200">Quiz Preview Mode</h3>
                                                <div class="ml-auto bg-brand-red text-white px-2 py-1 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-eye mr-1"></i>
                                                    PREVIEW
                                                </div>
                                            </div>
                                            
                                            <!-- Professional Notice -->
                                            <div class="bg-yellow-600 bg-opacity-90 rounded-lg p-4 mb-4">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-exclamation-triangle text-yellow-200 text-lg mr-2"></i>
                                                    <span class="font-semibold text-yellow-100">Preview Notice</span>
                                                </div>
                                                <p class="text-sm text-yellow-100">
                                                    This quiz cannot be taken in preview mode. Students will be able to complete the quiz and receive scores when enrolled.
                                                </p>
                                            </div>
                                            
                                            <!-- Quiz Information -->
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-yellow-200 dark:border-yellow-700 mb-4">
                                                <h4 class="font-semibold text-gray-800 dark:text-white mb-3">Quiz Information</h4>
                                                <div class="space-y-2 text-sm">
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600 dark:text-gray-300">Quiz Title:</span>
                                                        <span class="font-medium text-gray-900 dark:text-white" x-text="getCurrentQuiz()?.title || 'Section Quiz'"></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600 dark:text-gray-300">Description:</span>
                                                        <span class="text-gray-900 dark:text-white" x-text="getCurrentQuiz()?.description || 'No description provided'"></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600 dark:text-gray-300">Passing Score:</span>
                                                        <span class="font-semibold text-brand-red" x-text="getCurrentQuiz()?.passing_score + '%'"></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600 dark:text-gray-300">Access Mode:</span>
                                                        <span class="text-gray-900 dark:text-white"><?php echo $preview_access_mode === 'all' ? 'Full Access' : 'Enrolled Student View'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Mock Quiz Interface -->
                                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                                <h4 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center">
                                                    <i class="fas fa-clipboard-list mr-2 text-brand-red"></i>
                                                    Sample Quiz Question (Preview)
                                                </h4>
                                                <div class="space-y-3">
                                                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                                        <div class="flex items-start mb-3">
                                                            <span class="flex-shrink-0 w-6 h-6 bg-brand-red text-white rounded-full flex items-center justify-center text-sm font-semibold mr-3">1</span>
                                                            <p class="text-sm text-gray-700 dark:text-gray-300 font-medium">What is the main topic of this section?</p>
                                                        </div>
                                                        <div class="space-y-2 ml-9">
                                                            <label class="flex items-center space-x-3 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-not-allowed opacity-60">
                                                                <input type="radio" disabled class="text-brand-red">
                                                                <span class="text-sm text-gray-600 dark:text-gray-400">Option A (Preview)</span>
                                                            </label>
                                                            <label class="flex items-center space-x-3 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-not-allowed opacity-60">
                                                                <input type="radio" disabled class="text-brand-red">
                                                                <span class="text-sm text-gray-600 dark:text-gray-400">Option B (Preview)</span>
                                                            </label>
                                                            <label class="flex items-center space-x-3 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-not-allowed opacity-60">
                                                                <input type="radio" disabled class="text-brand-red">
                                                                <span class="text-sm text-gray-600 dark:text-gray-400">Option C (Preview)</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Submit Button (Disabled) -->
                                                    <div class="flex justify-end">
                                                        <button disabled class="px-6 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center">
                                                            <i class="fas fa-lock mr-2"></i>
                                                            Submit Quiz (Disabled in Preview)
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-2"></i>
                                                        <p class="text-xs text-blue-700 dark:text-blue-300">
                                                            <strong>Note:</strong> Quiz questions, answers, and scoring will be fully functional for enrolled students.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modern Welcome Message - shows when no chapter is selected -->
                                    <div x-show="(!activeContent && !showQuiz) || (activeType !== 'chapter' && activeType !== 'quiz')" class="welcome-container">
                                        <div class="modern-welcome-card">
                                            <!-- Animated Background Elements -->
                                            <div class="welcome-bg-animation">
                                                <div class="floating-element element-1"></div>
                                                <div class="floating-element element-2"></div>
                                                <div class="floating-element element-3"></div>
                                                <div class="floating-element element-4"></div>
                                            </div>
                                            
                                            <!-- Main Welcome Content -->
                                            <div class="welcome-content">
                                                <!-- Animated Icon -->
                                                <div class="welcome-icon-container">
                                                    <div class="welcome-icon-wrapper">
                                                        <svg class="welcome-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                                        </svg>
                                                        <div class="icon-pulse"></div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Welcome Text with Animation -->
                                                <div class="welcome-text-container">
                                                    <h1 class="welcome-title rubik-bold">
                                                        <span class="title-gradient">Start Your</span>
                                                        <span class="title-accent">Learning Journey</span>
                                                    </h1>
                                                    <p class="welcome-subtitle rubik-medium">
                                                        Dive into engaging content, interactive videos, and challenging quizzes designed to enhance your knowledge
                                                    </p>
                                                </div>
                                                
                                                <!-- Interactive Features -->
                                                <div class="welcome-features">
                                                    <div class="feature-item">
                                                        <div class="feature-icon">
                                                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                                      d="M9 3H5a3 3 0 0 0-3 3v10a.75.75 0 0 0 1.2.6A4.5 4.5 0 0 1 5.5 15H9V3zm2 0h4a3 3 0 0 1 3 3v10a.75.75 0 0 1-1.2.6A4.5 4.5 0 0 0 14.5 15H11V3z"/>
                                                            </svg>
                                                        </div>
                                                        <span class="feature-text rubik-medium">Rich Content</span>
                                                    </div>
                                                    <div class="feature-item">
                                                        <div class="feature-icon">
                                                            <svg fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                                            </svg>
                                                        </div>
                                                        <span class="feature-text rubik-medium">Video Learning</span>
                                                    </div>
                                                    <div class="feature-item">
                                                        <div class="feature-icon">
                                                            <svg fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </div>
                                                        <span class="feature-text rubik-medium">Interactive Quizzes</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Call to Action -->
                                                <div class="welcome-cta">
                                                    <div class="cta-arrow">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path>
                                                        </svg>
                                                    </div>
                                                    <span class="cta-text rubik-semibold">Select a chapter from the sidebar to begin</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Sidebar Toggle -->
    <button @click="sidebarCollapsed = !sidebarCollapsed" 
            class="fixed bottom-4 right-4 md:hidden bg-brand-red text-white p-3 rounded-full shadow-lg">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <script>
        function coursePreview() {
            return {
                sidebarCollapsed: false,
                previewAccessMode: '<?php echo $preview_access_mode; ?>',
                activeContent: <?php echo $active_content ?: 'null'; ?>,
                activeType: 'chapter',
                activeChapterId: null,
                showQuiz: false,
                sections: <?php echo json_encode($sections); ?>,
                
                init() {
                    // Set initial content if available
                    if (this.activeContent) {
                        const section = this.sections.find(s => s.id == this.activeContent);
                        if (section && section.chapters.length > 0) {
                            this.activeType = 'chapter';
                            this.activeChapterId = section.chapters[0].id;
                        } else if (section && section.has_quiz) {
                            this.activeType = 'quiz';
                            this.showQuiz = true;
                        }
                    } else if (this.sections.length > 0) {
                        // If no active content, select the first chapter of the first section
                        const firstSection = this.sections[0];
                        if (firstSection && firstSection.chapters && firstSection.chapters.length > 0) {
                            this.activeContent = firstSection.id;
                            this.activeType = 'chapter';
                            this.activeChapterId = firstSection.chapters[0].id;
                            this.showQuiz = false;
                        }
                    }
                    
                    // Log preview mode initialization
                    console.log('Preview Mode Initialized:', {
                        courseId: <?php echo $course_id; ?>,
                        accessMode: this.previewAccessMode,
                        sectionsCount: this.sections.length,
                        activeContent: this.activeContent,
                        activeType: this.activeType,
                        activeChapterId: this.activeChapterId
                    });
                },
                
                selectContent(sectionId, type, itemId = null) {
                    console.log('selectContent called:', { sectionId, type, itemId });
                    
                    // Update all state variables - ensure consistent data types
                    this.activeContent = parseInt(sectionId);
                    this.activeType = type;
                    this.activeChapterId = itemId ? parseInt(itemId) : null;
                    this.showQuiz = (type === 'quiz');
                    
                    // Force immediate reactivity update
                    this.$nextTick(() => {
                        console.log('Immediate state check:', {
                            activeContent: this.activeContent,
                            activeType: this.activeType,
                            activeChapterId: this.activeChapterId,
                            showQuiz: this.showQuiz
                        });
                    });
                    
                    // Force reactivity update
                    this.$nextTick(() => {
                        console.log('State updated:', {
                            activeContent: this.activeContent,
                            activeType: this.activeType,
                            activeChapterId: this.activeChapterId,
                            showQuiz: this.showQuiz
                        });
                    });
                    
                    // Log content selection in preview mode
                    console.log('Preview Mode: Content selected', {
                        sectionId: sectionId,
                        type: type,
                        itemId: itemId,
                        activeContent: this.activeContent,
                        activeType: this.activeType,
                        activeChapterId: this.activeChapterId,
                        showQuiz: this.showQuiz
                    });
                },
                
                getCurrentChapter() {
                    if (this.activeType !== 'chapter' || !this.activeChapterId) return null;
                    
                    for (let section of this.sections) {
                        if (section.id == this.activeContent) {
                            return section.chapters.find(ch => ch.id == this.activeChapterId);
                        }
                    }
                    return null;
                },
                
                getCurrentQuiz() {
                    if (this.activeType !== 'quiz') return null;
                    
                    for (let section of this.sections) {
                        if (section.id == this.activeContent) {
                            return section.quiz;
                        }
                    }
                    return null;
                },
                
                updatePreviewMode(mode) {
                    this.previewAccessMode = mode;
                    const url = new URL(window.location);
                    url.searchParams.set('access', mode);
                    window.history.replaceState({}, '', url);
                    
                    // Show notification about mode change
                    this.showNotification(`Switched to ${mode === 'enrolled' ? 'Enrolled Student' : 'All Access'} preview mode`);
                },
                
                showNotification(message) {
                    // Create a temporary notification
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 transition-all duration-300';
                    notification.textContent = message;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.style.opacity = '0';
                        setTimeout(() => notification.remove(), 300);
                    }, 2000);
                }
            }
        }
        
        // Dark mode toggle function
        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            
            if (isDark) {
                html.classList.remove('dark');
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                localStorage.setItem('darkMode', 'true');
            }
        }
        
        // Initialize dark mode from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.classList.add('dark');
            }
            
            // Prevent any form submissions that might save data
            document.addEventListener('submit', function(e) {
                e.preventDefault();
                alert('Preview Mode: Form submissions are disabled. No data will be saved.');
                return false;
            });
            
            // Prevent any AJAX calls that might save data
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                console.warn('Preview Mode: AJAX request blocked:', args[0]);
                return Promise.reject(new Error('Preview Mode: Data saving is disabled'));
            };
        });
    </script>
</body>
</html>
