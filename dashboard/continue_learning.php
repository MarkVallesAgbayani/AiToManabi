<?php
session_start();

// Enable performance monitoring
if (!defined('ENABLE_PERFORMANCE_MONITORING')) {
    define('ENABLE_PERFORMANCE_MONITORING', true);
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once 'performance_monitoring_functions.php';
require_once 'system_uptime_tracker.php';

// Manual performance logging for continue learning dashboard
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
                'Continue Learning',
                'Page Load',
                $_SERVER['REQUEST_URI'] ?? '',
                date('Y-m-d H:i:s', (int)$start_time),
                date('Y-m-d H:i:s', (int)$end_time),
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
            error_log("Continue Learning performance logging failed: " . $e->getMessage());
        }
    });
}

// Check if a specific course is being viewed
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($course_id) {
    // Verify enrollment
    $stmt = $pdo->prepare("
        SELECT e.* FROM enrollments e
        WHERE e.course_id = ? AND e.student_id = ?
    ");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        header("Location: student_courses.php");
        exit();
    }

    // Update last_accessed_at ONLY when user actually visits continue_learning.php for this specific course
    try {
        $stmt = $pdo->prepare("
            INSERT INTO course_progress (student_id, course_id, content_type, last_accessed_at, created_at, updated_at)
            VALUES (?, ?, 'course', NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                last_accessed_at = NOW(),
                updated_at = NOW()
        ");
        $stmt->execute([$_SESSION['user_id'], $course_id]);
    } catch (Exception $e) {
        error_log("Error updating last_accessed_at for course $course_id: " . $e->getMessage());
    }

    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.*, u.username as teacher_name, cat.name as category_name
        FROM courses c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ? AND c.is_published = 1
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    // Get sections first - simplified query to test
    $stmt = $pdo->prepare("
        SELECT 
            s.id as section_id,
            s.title as section_title,
            s.content as section_content,
            s.order_index as section_order
        FROM sections s
        WHERE s.course_id = ?
        ORDER BY s.order_index ASC, s.id ASC
    ");
    $stmt->execute([$course_id]);
    $sections_data = $stmt->fetchAll();

    // Initialize sections array
    $sections = [];
    
    // Process each section and fetch its chapters separately
    foreach ($sections_data as $section_row) {
        $section_id = $section_row['section_id'];
        
        // Get progress for this section
        $progress_stmt = $pdo->prepare("SELECT COALESCE(completed, 0) as is_completed FROM progress WHERE section_id = ? AND student_id = ?");
        $progress_stmt->execute([$section_id, $_SESSION['user_id']]);
        $progress_result = $progress_stmt->fetch();
        $is_completed = $progress_result ? $progress_result['is_completed'] : 0;
        
        // Initialize section
        $sections[$section_id] = [
            'id' => $section_id,
            'title' => $section_row['section_title'],
            'description' => $section_row['section_content'] ?? '',
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
        $chapters_data = $chapter_stmt->fetchAll();
        
        // Add chapters to this section
        foreach ($chapters_data as $chapter_row) {
            $sections[$section_id]['chapters'][] = [
                'id' => $chapter_row['id'],
                'title' => $chapter_row['title'],
                'description' => $chapter_row['description'] ?? '',
                'content' => $chapter_row['content'] ?? '',
                'content_type' => $chapter_row['content_type'],
                'order_index' => $chapter_row['order_index'],
                'video_url' => $chapter_row['video_url'],
                'video_type' => $chapter_row['video_type'],
                'video_file_path' => $chapter_row['video_file_path'],
                'is_completed' => 0
            ];
        }
    }

    // Sort sections by order_index and chapters within each section
    uasort($sections, function($a, $b) {
        return $a['order_index'] - $b['order_index'];
    });
    
    // Sort chapters within each section
    foreach ($sections as &$section) {
        if (!empty($section['chapters'])) {
            usort($section['chapters'], function($a, $b) {
                return $a['order_index'] - $b['order_index'];
            });
        }
    }
    unset($section);
    
    // Create backward compatibility: flatten all chapters into a single array
    $chapters = [];
    foreach ($sections as $section) {
        if (!empty($section['chapters'])) {
            foreach ($section['chapters'] as $chapter) {
                $chapter['section_id'] = $section['id'];
                $chapter['section_title'] = $section['title'];
                $chapters[] = $chapter;
            }
        }
    }
}

// Calculate overall course progress based on individual chapters
$total_chapters = 0;
$completed_chapters = 0;

foreach ($sections as $section_id => $section) {
    if (!empty($section['chapters'])) {
        foreach ($section['chapters'] as $chapter) {
            $total_chapters++;
            
            $chapter_completed = false;
            
            if ($chapter['content_type'] === 'video') {
                $stmt = $pdo->prepare("SELECT completed FROM video_progress WHERE student_id = ? AND chapter_id = ?");
                $stmt->execute([$_SESSION['user_id'], $chapter['id']]);
                $progress = $stmt->fetch();
                $chapter_completed = $progress && $progress['completed'];
            } else if ($chapter['content_type'] === 'text') {
                $stmt = $pdo->prepare("SELECT completed FROM text_progress WHERE student_id = ? AND chapter_id = ?");
                $stmt->execute([$_SESSION['user_id'], $chapter['id']]);
                $progress = $stmt->fetch();
                $chapter_completed = $progress && $progress['completed'];
            }
            
            if ($chapter_completed) {
                $completed_chapters++;
            }
            
            $sections[$section_id]['chapters'][array_search($chapter, $section['chapters'])]['is_completed'] = $chapter_completed;
        }
    }
}

// Calculate section completion based on chapter completions and quiz completion
foreach ($sections as $section_id => $section) {
    $section_total_chapters = count($section['chapters']);
    $section_completed_chapters = 0;
    
    foreach ($section['chapters'] as $chapter) {
        if ($chapter['is_completed']) {
            $section_completed_chapters++;
        }
    }
    
    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE section_id = ?");
    $stmt->execute([$section_id]);
    $has_quiz = $stmt->fetch() !== false;
    
    $quiz_completed = false;
    if ($has_quiz) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM quiz_attempts qa INNER JOIN quizzes q ON qa.quiz_id = q.id WHERE q.section_id = ? AND qa.student_id = ?");
        $stmt->execute([$section_id, $_SESSION['user_id']]);
        $quiz_completed = $stmt->fetch()['attempt_count'] > 0;
    }
    
    $all_chapters_complete = ($section_completed_chapters === $section_total_chapters && $section_total_chapters > 0);
    $quiz_requirement_met = !$has_quiz || $quiz_completed;
    
    $sections[$section_id]['is_completed'] = $all_chapters_complete && $quiz_requirement_met;
    $sections[$section_id]['completed_chapters'] = $section_completed_chapters;
    $sections[$section_id]['total_chapters'] = $section_total_chapters;
    $sections[$section_id]['has_quiz'] = $has_quiz;
    $sections[$section_id]['quiz_completed'] = $quiz_completed;
}

// Calculate course progress including quiz completion
$total_items = $total_chapters;
$completed_items = $completed_chapters;

foreach ($sections as $section) {
    if ($section['has_quiz']) {
        $total_items++;
        if ($section['quiz_completed']) {
            $completed_items++;
        }
    }
}

$course_progress = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;

// Update course_progress table with current progress
try {
    $completion_status = 'not_started';
    if ($course_progress >= 100) {
        $completion_status = 'completed';
    } elseif ($course_progress > 0) {
        $completion_status = 'in_progress';
    }
    
    $stmt = $pdo->prepare("
        UPDATE course_progress 
        SET completed_sections = ?, 
            completion_percentage = ?, 
            completion_status = ?,
            completed_at = CASE 
                WHEN ? = 'completed' AND completion_status != 'completed' THEN NOW()
                ELSE completed_at
            END,
            last_accessed_at = NOW()
        WHERE course_id = ? AND student_id = ?
    ");
    $stmt->execute([
        $completed_items,
        $course_progress,
        $completion_status,
        $completion_status,
        $course_id,
        $_SESSION['user_id']
    ]);
} catch (Exception $e) {
    error_log("Error updating course progress: " . $e->getMessage());
}

// Get student's name and email
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Get student preferences
$stmt = $pdo->prepare("SELECT display_name, profile_picture FROM student_preferences WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$preferences = $stmt->fetch(PDO::FETCH_ASSOC);

if ($preferences) {
    $student['display_name'] = $preferences['display_name'] ?? '';
    $student['profile_picture'] = $preferences['profile_picture'] ?? '';
} else {
    $student['display_name'] = '';
    $student['profile_picture'] = '';
}

// Initialize current chapter and section
$current_chapter = null;
$current_section = null;
$current_chapter_type = 'section';

$selected_section_id = isset($_GET['section']) ? (int)$_GET['section'] : null;
$selected_chapter_id = isset($_GET['chapter']) ? (int)$_GET['chapter'] : null;
$is_quiz = isset($_GET['quiz']) && $_GET['quiz'] === '1';

if ($selected_section_id && isset($sections)) {
    foreach ($sections as $section) {
        if ($section['id'] === $selected_section_id) {
            $current_section = $section;
            
            if ($selected_chapter_id) {
                foreach ($section['chapters'] as $chapter) {
                    if ($chapter['id'] === $selected_chapter_id) {
                        $current_chapter = $chapter;
                        $current_chapter_type = 'chapter';
                        break;
                    }
                }
            } else if ($is_quiz) {
                $current_chapter_type = 'quiz';
                $current_chapter = !empty($section['chapters']) ? $section['chapters'][0] : null;
            } else {
                $current_chapter = !empty($section['chapters']) ? $section['chapters'][0] : null;
                $current_chapter_type = 'section';
            }
            break;
        }
    }
} else if (isset($sections) && !empty($sections)) {
    $current_section = $sections[array_key_first($sections)];
    $current_chapter = !empty($current_section['chapters']) ? $current_section['chapters'][0] : null;
    $current_chapter_type = 'section';
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $course_id ? htmlspecialchars($course['title']) : 'Continue Learning'; ?> - AITOMANABI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="js/continue_learning.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="js/quiz.js" defer></script>
    <script src="../assets/js/video-progress-tracker.js" defer></script>
    <script src="js/session_timeout.js"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link href="css/continue_learning.css" rel="stylesheet">

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

    <script>
    // CRITICAL: Define Alpine stores BEFORE Alpine initializes
    document.addEventListener('alpine:init', () => {
        // Mobile state management
        Alpine.store('mobile', {
            isMobile: window.innerWidth < 768,
            isTablet: window.innerWidth >= 768 && window.innerWidth < 1024,
            isDesktop: window.innerWidth >= 1024,
            
            init() {
                window.addEventListener('resize', () => {
                    this.isMobile = window.innerWidth < 768;
                    this.isTablet = window.innerWidth >= 768 && window.innerWidth < 1024;
                    this.isDesktop = window.innerWidth >= 1024;
                    
                    if (this.isMobile && !document.querySelector('[x-data="{ sidebarCollapsed: false }"]').__x.$data.sidebarCollapsed) {
                        this.collapseSidebarOnMobile();
                    }
                });
                
                if (this.isMobile) {
                    this.collapseSidebarOnMobile();
                }
            },
            
            collapseSidebarOnMobile() {
                const sidebarElement = document.querySelector('[x-data="{ sidebarCollapsed: false }"]');
                if (sidebarElement && Alpine.$data(sidebarElement)) {
                    Alpine.$data(sidebarElement).sidebarCollapsed = true;
                }
            },
            
            toggleSidebar() {
                const sidebarElement = document.querySelector('[x-data="{ sidebarCollapsed: false }"]');
                if (sidebarElement && Alpine.$data(sidebarElement)) {
                    Alpine.$data(sidebarElement).sidebarCollapsed = !Alpine.$data(sidebarElement).sidebarCollapsed;
                }
            }
        });
        
        Alpine.store('content', {
            activeContent: <?php echo isset($current_section) ? $current_section['id'] : 'null' ?>,
            activeChapter: <?php echo isset($current_chapter) ? $current_chapter['id'] : 'null' ?>,
            activeChapterType: '<?php echo $current_chapter_type; ?>',
            showQuiz: <?php echo $is_quiz ? 'true' : 'false' ?>,
            quizLoading: false,
            quizData: null,
            quizError: false,
            sections: <?php echo json_encode(array_values($sections)); ?>,
            
            async initQuizData() {
                if (this.showQuiz && this.activeContent) {
                    console.log('Initializing quiz data for section:', this.activeContent);
                    this.quizLoading = true;
                    this.quizError = false;
                    try {
                        const response = await fetch(`api/get_quiz.php?section_id=${this.activeContent}&page=1`);
                        const data = await response.json();
                        this.quizData = data.error ? null : data;
                        this.quizError = !!data.error;
                        console.log('Quiz data loaded:', this.quizData);
                        
                        this.updateNextButton();
                    } catch (error) {
                        console.error('Error loading quiz data:', error);
                        this.quizError = true;
                    } finally {
                        this.quizLoading = false;
                    }
                }
            },
            
            init() {
    // Use $watch properly within Alpine context
    Alpine.effect(() => {
        const quizCompleted = this.quizData?.quizCompleted;
        if (quizCompleted !== undefined) {
            console.log('Quiz completion status changed:', quizCompleted);
            this.updateNextButton();
        }
    });
    
    if (this.showQuiz && this.activeContent && !this.quizData && !this.quizLoading) {
        console.log('Auto-initializing quiz data...');
        this.initQuizData();
    }
    
    console.log('Store init - updating next button...');
    setTimeout(() => {
        this.updateNextButton();
    }, 100);
},
            
            async setContent(sectionId, isQuiz = false) {
                this.activeContent = sectionId;
                this.showQuiz = isQuiz;
                this.activeChapterType = isQuiz ? 'quiz' : 'section';
                
                if (isQuiz) {
                    this.quizLoading = true;
                    this.quizError = false;
                    try {
                        const response = await fetch(`api/get_quiz.php?section_id=${sectionId}&page=1`);
                        const data = await response.json();
                        this.quizData = data.error ? null : data;
                        this.quizError = !!data.error;
                    } catch (error) {
                        this.quizError = true;
                    } finally {
                        this.quizLoading = false;
                    }
                } else {
                    this.quizData = null;
                }
                
                const url = new URL(window.location);
                url.searchParams.set('section', sectionId);
                if (isQuiz) {
                    url.searchParams.set('quiz', '1');
                } else {
                    url.searchParams.delete('quiz');
                }
                window.history.pushState({}, '', url);
            },
            
            async setActiveContent(sectionId, isQuiz = false) {
                return this.setContent(sectionId, isQuiz);
            },
            
            async setChapterContent(sectionId, chapterId) {
                this.activeContent = sectionId;
                this.activeChapter = chapterId;
                this.activeChapterType = 'chapter';
                this.showQuiz = false;
                this.quizData = null;
                
                this.updateNextButton();
                
                const url = new URL(window.location);
                url.searchParams.set('section', sectionId);
                url.searchParams.set('chapter', chapterId);
                url.searchParams.delete('quiz');
                window.history.pushState({}, '', url);
            },
            
            updateNextButton() {
                const nextButton = document.getElementById('next-button');
                const nextButtonText = document.getElementById('next-button-text');
                
                if (!nextButton || !nextButtonText) {
                    console.log('Next button elements not found, retrying...');
                    setTimeout(() => this.updateNextButton(), 500);
                    return;
                }
                
                if (this.activeChapterType === 'chapter') {
                    console.log('Viewing chapter - quiz data should not affect button state');
                }
                
                console.log('Updating next button:', {
                    activeChapterType: this.activeChapterType,
                    activeContent: this.activeContent,
                    activeChapter: this.activeChapter,
                    showQuiz: this.showQuiz,
                    sections: this.sections
                });
                
                const allItems = [];
                
                if (!this.sections || this.sections.length === 0) {
                    console.log('Sections data not available yet, retrying...');
                    setTimeout(() => {
                        this.updateNextButton();
                    }, 500);
                    return;
                }
                
                this.sections.forEach(section => {
                    if (section.chapters && section.chapters.length > 0) {
                        section.chapters.forEach((chapter, chapterIndex) => {
                            allItems.push({
                                type: 'chapter',
                                sectionId: section.id,
                                chapterId: chapter.id,
                                title: chapter.title,
                                sectionTitle: section.title,
                                sectionOrder: section.order_index,
                                chapterIndex: chapterIndex,
                                totalChaptersInSection: section.chapters.length,
                                hasQuiz: section.has_quiz
                            });
                        });
                    }
                    
                    if (section.has_quiz) {
                        allItems.push({
                            type: 'quiz',
                            sectionId: section.id,
                            title: 'Section Quiz',
                            sectionTitle: section.title,
                            sectionOrder: section.order_index,
                            hasQuiz: true
                        });
                    }
                });
                
                let currentIndex = -1;
                
                if (this.activeChapterType === 'chapter') {
                    currentIndex = allItems.findIndex(item => 
                        item.type === 'chapter' && item.chapterId === this.activeChapter
                    );
                } else if (this.activeChapterType === 'quiz') {
                    currentIndex = allItems.findIndex(item => 
                        item.type === 'quiz' && item.sectionId === this.activeContent
                    );
                }
                
                console.log('Current index found:', currentIndex, 'All items:', allItems);
                
                if (currentIndex !== -1) {
                    const currentItem = allItems[currentIndex];
                    const isLastItemInCourse = currentIndex === allItems.length - 1;
                    
                    const isLastSectionQuiz = this.activeChapterType === 'quiz' && 
                        this.sections.length > 0 && 
                        this.activeContent === this.sections[this.sections.length - 1].id &&
                        this.sections[this.sections.length - 1].has_quiz;
                    
                    const shouldShowFinishModule = isLastItemInCourse || isLastSectionQuiz;
                    
                    if (shouldShowFinishModule) {
                        const isCurrentQuiz = currentItem && currentItem.type === 'quiz';
                        const isQuizCompleted = isCurrentQuiz ? 
                            (window.Alpine && window.Alpine.store('content') && 
                             window.Alpine.store('content').quizData && 
                             window.Alpine.store('content').quizData.quizCompleted) : true;
                        
                        const buttonText = isCurrentQuiz && !isQuizCompleted ? 
                            'Complete Quiz First' : 'Finish Module';
                        const buttonIcon = isCurrentQuiz && !isQuizCompleted ? 
                            'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z' :
                            'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
                        
                        nextButtonText.innerHTML = `
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${buttonIcon}"></path>
                            </svg>
                            ${buttonText}
                        `;
                        nextButton.style.display = 'flex';
                        
                        if (isCurrentQuiz && !isQuizCompleted) {
                            nextButton.classList.add('ml-auto', 'bg-gray-400', 'cursor-not-allowed', 'opacity-60');
                            nextButton.classList.remove('bg-red-600', 'hover:bg-red-700', 'bg-blue-600', 'hover:bg-blue-700', 'bg-green-600', 'hover:bg-green-700');
                            nextButton.onclick = null;
                            nextButton.disabled = true;
                        } else {
                            nextButton.classList.add('ml-auto', 'bg-green-600', 'hover:bg-green-700');
                            nextButton.classList.remove('bg-red-600', 'hover:bg-red-700', 'bg-blue-600', 'hover:bg-blue-700', 'bg-gray-400', 'cursor-not-allowed', 'opacity-60');
                            nextButton.disabled = false;
                            
                            nextButton.onclick = () => {
                                showCourseCompletionConfirmation();
                            };
                        }
                    } else {
                        const nextItem = allItems[currentIndex + 1];
                        
                        if (nextItem.type === 'quiz') {
                            nextButtonText.innerHTML = `Next Quiz <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>`;
                            nextButton.style.display = 'flex';
                            
                            nextButton.classList.add('ml-auto', 'bg-yellow-600', 'hover:bg-yellow-700');
                            nextButton.classList.remove('bg-red-600', 'hover:bg-red-700', 'bg-blue-600', 'hover:bg-blue-700', 'bg-green-600', 'hover:bg-green-700', 'bg-gray-400', 'cursor-not-allowed', 'opacity-60');
                            nextButton.disabled = false;
                            
                            nextButton.onclick = () => {
                                window.location.href = `continue_learning.php?id=<?php echo $course_id; ?>&section=${nextItem.sectionId}&quiz=1`;
                            };
                        } else if (nextItem.type === 'chapter') {
                            const currentSectionItems = allItems.filter(item => item.sectionId === currentItem.sectionId);
                            const isLastChapterInSection = currentItem.type === 'chapter' && 
                                currentItem.chapterIndex === currentItem.totalChaptersInSection - 1;
                            
                            if (isLastChapterInSection && nextItem.sectionId !== currentItem.sectionId) {
                                nextButtonText.innerHTML = `
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                    Next Section: ${nextItem.sectionTitle}
                                `;
                                nextButton.style.display = 'flex';
                                nextButton.classList.add('ml-auto', 'bg-blue-600', 'hover:bg-blue-700');
                                nextButton.classList.remove('bg-red-600', 'hover:bg-red-700', 'bg-yellow-600', 'hover:bg-yellow-700', 'bg-green-600', 'hover:bg-green-700');
                                
                                nextButton.onclick = () => {
                                    handleNextButtonClick(`continue_learning.php?id=<?php echo $course_id; ?>&section=${nextItem.sectionId}&chapter=${nextItem.chapterId}`);
                                };
                            } else {
                                const nextTitle = nextItem.title.length > 30 ? 
                                    nextItem.title.substring(0, 30) + '...' : 
                                    nextItem.title;
                                
                                nextButtonText.innerHTML = `
                                    Next: ${nextTitle}
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                `;
                                nextButton.style.display = 'flex';
                                nextButton.classList.add('ml-auto', 'bg-red-600', 'hover:bg-red-700');
                                nextButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'bg-yellow-600', 'hover:bg-yellow-700', 'bg-green-600', 'hover:bg-green-700');
                                
                                nextButton.onclick = () => {
                                    handleNextButtonClick(`continue_learning.php?id=<?php echo $course_id; ?>&section=${nextItem.sectionId}&chapter=${nextItem.chapterId}`);
                                };
                            }
                        }
                    }
                } else {
                    console.log('Current item not found, showing default next button');
                    nextButtonText.innerHTML = `
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    `;
                    nextButton.style.display = 'flex';
                    nextButton.classList.add('ml-auto', 'bg-red-600', 'hover:bg-red-700');
                    nextButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'bg-yellow-600', 'hover:bg-yellow-700', 'bg-green-600', 'hover:bg-green-700');
                    
                    nextButton.onclick = () => {
                        console.log('Default next button clicked');
                        window.location.reload();
                    };
                }
            }
        });
    });

    // Initialize after DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        if (Alpine.store('mobile')) {
            Alpine.store('mobile').init();
        }
        
        setTimeout(() => {
            if (Alpine.store('content')) {
                console.log('Initializing store...');
                Alpine.store('content').init();
                Alpine.store('content').updateNextButton();
            }
        }, 500);
        
        setTimeout(() => {
            if (Alpine.store('content')) {
                console.log('Secondary initialization - updating next button...');
                Alpine.store('content').updateNextButton();
            }
        }, 1000);
        
        setTimeout(() => {
            if (Alpine.store('content')) {
                console.log('Tertiary initialization - updating next button...');
                Alpine.store('content').updateNextButton();
            }
        }, 2000);
    });
    </script>
</head>
<body class="bg-gray-50 dark:bg-black transition-colors" x-data="{ 
    sidebarCollapsed: false, 
    mobileMenuOpen: false,
    handleBackNavigation() {
        // Simple: just use browser's back button
        window.history.back();
    },
    
    // Initialize popstate listener for browser back/forward buttons
    init() {
        // Listen for browser back/forward events
        window.addEventListener('popstate', async () => {
            console.log('Browser navigation detected');
            await this.syncWithURL();
        });
        
        // Also sync on page load
        this.$nextTick(async () => {
            console.log('Initial page sync');
            await this.syncWithURL();
        });
    },
    
    // Sync Alpine.js store with current URL
    async syncWithURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section');
        const chapter = urlParams.get('chapter');
        const isQuiz = urlParams.get('quiz') === '1';
        
        console.log('Syncing with URL:', { section, chapter, isQuiz });
        
        // PRIORITY: Quiz takes precedence over everything
        if (isQuiz && section) {
            // Show quiz for section
            console.log('Loading quiz content...');
            $store.content.activeContent = parseInt(section);
            $store.content.activeChapter = null;
            $store.content.activeChapterType = 'quiz';
            $store.content.showQuiz = true;
            $store.content.quizData = null;
            
            // Load quiz data
            await $store.content.initQuizData();
            console.log('Quiz loaded successfully');
            
        } else if (section && chapter) {
            // Show specific chapter (text, video, or other content)
            console.log('Loading chapter content...');
            $store.content.activeContent = parseInt(section);
            $store.content.activeChapter = parseInt(chapter);
            $store.content.activeChapterType = 'chapter';
            $store.content.showQuiz = false;
            $store.content.quizData = null;
            
        } else if (section) {
            // Show section overview
            console.log('Loading section overview...');
            $store.content.activeContent = parseInt(section);
            $store.content.activeChapter = null;
            $store.content.activeChapterType = 'section';
            $store.content.showQuiz = false;
            $store.content.quizData = null;
            
        } else {
            // Show welcome screen
            console.log('Loading welcome screen...');
            $store.content.activeContent = null;
            $store.content.activeChapter = null;
            $store.content.activeChapterType = 'section';
            $store.content.showQuiz = false;
            $store.content.quizData = null;
        }
        
        // Update next button
        setTimeout(() => {
            if ($store.content && $store.content.updateNextButton) {
                $store.content.updateNextButton();
            }
        }, 300);
    }
}"
    <!-- Top Navigation Bar -->
    <nav class="fixed top-0 right-0 left-0 z-40 bg-white dark:bg-black shadow-lg border-b border-gray-200 dark:border-dark-border h-16">
        <div class="max-w-full px-4 mx-auto h-full">
            <div class="flex justify-between items-center h-full">
                <!-- Logo and Mobile Sidebar Toggle -->
                <div class="flex-shrink-0 flex items-center space-x-3">
                    <!-- Mobile Sidebar Toggle Button -->
                    <button @click="sidebarCollapsed = !sidebarCollapsed" 
                            class="md:hidden p-2 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                            aria-label="Toggle sidebar">
                        <svg class="w-5 h-5 transition-transform duration-200" 
                             :class="{ 'rotate-180': !sidebarCollapsed }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    
                    <!-- Logo and Home Button -->
                    <div class="flex items-center space-x-3">
                        <a href="../dashboard/dashboard.php" class="text-2xl rubik-bold text-red-500 japanese-transition hover:text-red-600 max-[630px]:hidden">
                            AiToManabi
                        </a>
                        <a href="dashboard.php" 
                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transform hover:scale-105">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Home
                        </a>
                    </div>
                </div>

                <!-- Desktop Dark Mode Toggle and Profile -->
                <div class="hidden sm:flex sm:items-center sm:space-x-4">
                    <!-- Dark Mode Toggle -->
                    <button 
                        onclick="toggleDarkMode()" 
                        class="p-2 rounded-full text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none"
                        aria-label="Toggle dark mode"
                    >
                        <!-- Sun icon -->
                        <svg class="w-6 h-6 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <!-- Moon icon -->
                        <svg class="w-6 h-6 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            <a href="../dashboard/student_profile.php" 
                               class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                                Profile Settings
                            </a>
                            <a href="../dashboard/student_payment_history.php" 
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
                    <button onclick="mobileMenuOpen = false; window.location.href='../dashboard/dashboard.php'" 
                            class="border-transparent text-gray-500 dark:text-gray-400 japanese-transition hover:border-red-500 hover:text-red-500 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        Dashboard
                    </button>
                    <button onclick="mobileMenuOpen = false; window.location.href='../dashboard/student_courses.php'" 
                            class="border-red-500 text-red-500 japanese-transition hover:text-red-600 block w-full text-left px-3 py-2 border-l-4 text-base font-medium focus:outline-none">
                        Modules
                    </button>
                    <button onclick="mobileMenuOpen = false; window.location.href='../dashboard/my_learning.php'" 
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
                    <button onclick="toggleDarkMode()" 
                            class="w-full text-left px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none flex items-center space-x-3">
                        <!-- Sun icon -->
                        <svg class="w-5 h-5 hidden dark:block text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <!-- Moon icon -->
                        <svg class="w-5 h-5 block dark:hidden text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                        <span class="text-base font-medium">Toggle Dark Mode</span>
                    </button>
                    
                    <!-- Divider -->
                    <hr class="my-2 border-gray-200 dark:border-gray-700">
                    
                    <!-- Profile Actions -->
                    <a href="../dashboard/student_profile.php" 
                       @click="mobileMenuOpen = false"
                       class="block w-full text-left px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-50 dark:hover:bg-red-900/20 japanese-transition focus:outline-none">
                        Profile Settings
                    </a>
                    <a href="../dashboard/student_payment_history.php" 
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

    <!-- Main Layout -->
    <div class="flex min-h-screen pt-16 main-layout-container">
        <!-- Mobile Overlay -->
        <div x-show="!sidebarCollapsed && window.innerWidth < 768" 
             @click="sidebarCollapsed = true"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-20 bg-black bg-opacity-50 md:hidden"
             style="display: none;">
        </div>
        
        <!-- Sidebar -->
        <aside class="fixed top-16 left-0 h-[calc(100vh-4rem)] bg-white dark:bg-black border-r border-gray-200 dark:border-dark-border transition-all duration-300 ease-in-out z-30 flex flex-col"
               :class="{ 
                   'w-64 translate-x-0': !sidebarCollapsed, 
                   'w-16 -translate-x-full md:translate-x-0': sidebarCollapsed
               }">
            
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-200 dark:border-dark-border flex items-center justify-between flex-shrink-0">
                <div class="flex-1 overflow-hidden" x-show="!sidebarCollapsed">
                    <?php if ($course_id && isset($course)): ?>
                        <h2 class="text-lg font-bold rubik-bold text-gray-900 dark:text-white truncate">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Module Content</p>
                    <?php endif; ?>
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

            <!-- Module Content Section - Scrollable -->
            <?php if ($course_id && isset($sections)): ?>
                <div class="flex-1 overflow-y-auto overflow-x-hidden sidebar-scrollable">
                    <div class="mt-2 px-4 pb-6">
                    <!-- Module Progress Section -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold rubik-semibold text-gray-800 dark:text-white mb-4" x-show="!sidebarCollapsed">Module Progress</h3>
                        
                        <!-- Module Progress Bar -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 p-4 mb-4" x-show="!sidebarCollapsed">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium rubik-medium text-gray-600 dark:text-gray-300">Overall Progress</span>
                                <span class="text-sm font-medium rubik-medium text-blue-600 dark:text-blue-400"><?php echo $course_progress; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo $course_progress; ?>%"></div>
                            </div>
                        </div>

                        <!-- Hierarchical Section Navigation -->
                        <div class="space-y-3" x-data="{ expandedSections: {} }">
                            <?php 
                            $section_index = 0;
                            $current_section_found = false;
                            foreach ($sections as $section): 
                                $section_index++;
                                
                                // Determine section status
                                $is_completed = $section['is_completed'];
                                $is_current = false;
                                
                                // Check if this is the current section (first incomplete section)
                                if (!$current_section_found && !$is_completed) {
                                    $is_current = true;
                                    $current_section_found = true;
                                }
                                
                                // Status classes
                                if ($is_completed) {
                                    $dot_classes = "bg-green-500 border-green-500";
                                    $icon_classes = "text-white";
                                    $text_classes = "text-green-700 dark:text-green-400";
                                    $status_icon = "✓";
                                } elseif ($is_current) {
                                    $dot_classes = "bg-blue-500 border-blue-500";
                                    $icon_classes = "text-white";
                                    $text_classes = "text-blue-700 dark:text-blue-400 font-medium rubik-medium";
                                    $status_icon = $section_index;
                                } else {
                                    $dot_classes = "bg-gray-300 dark:bg-gray-600 border-gray-300 dark:border-gray-600";
                                    $icon_classes = "text-gray-600 dark:text-gray-400";
                                    $text_classes = "text-gray-600 dark:text-gray-400";
                                    $status_icon = $section_index;
                                }
                                
                                // Check if section has quiz and if it's completed
                                $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE section_id = ?");
                                $stmt->execute([$section['id']]);
                                $has_quiz = $stmt->fetch() !== false;
                                
                                // Check if quiz is completed
                                $quiz_completed = false;
                                if ($has_quiz) {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM quiz_attempts qa INNER JOIN quizzes q ON qa.quiz_id = q.id WHERE q.section_id = ? AND qa.student_id = ?");
                                    $stmt->execute([$section['id'], $_SESSION['user_id']]);
                                    $quiz_completed = $stmt->fetch()['attempt_count'] > 0;
                                }
                                
                                // Use the new chapter-based progress calculation
                                $total_items = $section['total_chapters'] + ($has_quiz ? 1 : 0);
                                $completed_items = $section['completed_chapters'] + ($has_quiz && $quiz_completed ? 1 : 0);
                                $progress_percentage = $total_items > 0 ? ($completed_items / $total_items) * 100 : 0;
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
                                                        <div class="h-1.5 rounded-full transition-all duration-300 <?php 
                                                            echo $is_completed ? 'bg-green-500' : ($is_current ? 'bg-blue-500' : 'bg-gray-400'); 
                                                        ?>" style="width: <?php echo $progress_percentage; ?>%"></div>
                                                    </div>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        <?php echo $completed_items; ?>/<?php echo $total_items; ?>
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
                                                    <button @click="$store.content.setChapterContent(<?php echo $section['id']; ?>, <?php echo $chapter['id']; ?>)" 
                                                        class="chapter-item w-full flex items-center py-2 px-3 text-sm <?php echo !empty($chapter['is_completed']) ? 'text-gray-400 dark:text-gray-500 completed-chapter' : 'text-gray-600 dark:text-gray-300'; ?> hover:bg-gray-100 dark:hover:bg-gray-600 rounded-md transition-colors duration-200 <?php echo !empty($chapter['is_completed']) ? 'opacity-75' : ''; ?>"
                                                        :class="{'bg-gray-100 dark:bg-gray-600 ring-2 ring-blue-500': $store.content.activeChapter === <?php echo $chapter['id']; ?> && $store.content.activeChapterType === 'chapter'}">
                                                        
                                                        <!-- Chapter Number -->
                                                        <span class="w-6 h-6 rounded-full <?php echo !empty($chapter['is_completed']) ? 'bg-green-300 text-green-700 dark:bg-green-800 dark:text-green-200' : 'bg-gray-200 dark:bg-gray-500 text-gray-600 dark:text-gray-300'; ?> text-xs font-medium rubik-medium flex items-center justify-center mr-3 flex-shrink-0">
                                                            <?php if (!empty($chapter['is_completed'])): ?>
                                                                <!-- Checkmark for completed chapters -->
                                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            <?php else: ?>
                                                                <?php echo $chapter_index + 1; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                        
                                                        <!-- Chapter Icon -->
                                                        <?php if ($chapter['content_type'] === 'video'): ?>
                                                            <svg class="w-4 h-4 mr-2 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                                            </svg>
                                                        <?php else: ?>
                                                            <svg class="w-4 h-4 mr-2 text-blue-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0v12h8V4H6z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Chapter Title -->
                                                        <span class="flex-1 text-left truncate <?php echo !empty($chapter['is_completed']) ? 'line-through text-red-500' : ''; ?>">
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
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Quiz Section -->
                                    <?php if ($has_quiz): ?>
                                        <div class="border-t border-gray-200 dark:border-gray-600 p-2">
                                            <button @click="$store.content.setContent(<?php echo $section['id']; ?>, true)"
                                                class="quiz-item w-full flex items-center py-2 px-3 text-sm text-gray-600 dark:text-gray-300 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 rounded-md transition-colors duration-200"
                                                :class="{'bg-yellow-50 dark:bg-yellow-900/30 ring-2 ring-yellow-500': $store.content.activeContent === <?php echo $section['id']; ?> && $store.content.showQuiz}">
                                                
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
                                            </button>
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
                </div>
            <?php endif; ?>
        </aside>

<!-- Main Content Area -->
<div class="flex-1 transition-all duration-300 ease-in-out" :class="{ 
    'ml-0 md:ml-16': sidebarCollapsed, 
    'ml-0 md:ml-64': !sidebarCollapsed 
}">
    <div class="min-h-[calc(100vh-4rem)] overflow-y-auto main-content-area">
        <div class="w-full px-2 sm:px-3 md:px-4 lg:px-6 py-2 md:py-4">
            <?php if ($course_id && isset($course)): ?>
            <!-- Section Header -->
            <div class="mb-4 md:mb-6 text-left" x-data="{ 
                currentSection: <?php echo is_array($current_section) ? $current_section['id'] : 'null' ?>,
                currentChapter: <?php echo is_array($current_chapter) ? $current_chapter['id'] : 'null' ?>
            }" x-init="
                $watch('$store.content.activeContent', value => {
                    currentSection = value;
                    // Find the chapter for this section
                    <?php foreach ($sections as $section): ?>
                        <?php if (isset($section['chapters']) && is_array($section['chapters'])): ?>
                            <?php foreach ($section['chapters'] as $chapter): ?>
                                if (value === <?php echo $section['id']; ?>) {
                                    currentChapter = <?php echo $chapter['id']; ?>;
                                }
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                });
                
                // Watch for chapter changes and update Next button
                $watch('$store.content.activeChapter', () => {
                    setTimeout(() => {
                        if ($store.content.updateNextButton) {
                            $store.content.updateNextButton();
                        }
                    }, 100);
                });
                
                // Watch for quiz state changes and update Next button
                $watch('$store.content.showQuiz', () => {
                    setTimeout(() => {
                        if ($store.content.updateNextButton) {
                            $store.content.updateNextButton();
                        }
                    }, 100);
                });
                
                // Watch for active content changes and update Next button
                $watch('$store.content.activeContent', () => {
                    setTimeout(() => {
                        if ($store.content.updateNextButton) {
                            $store.content.updateNextButton();
                        }
                    }, 100);
                });
            ">
                <!-- Only show section title when viewing actual chapter content -->
                <?php foreach ($sections as $section): ?>
                    <div x-show="currentSection === <?php echo $section['id']; ?> && $store.content.activeChapterType === 'chapter'">
                        <h1 class="text-2xl md:text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white mb-2 md:mb-4 rubik-bold">
                            <?php echo htmlspecialchars($section['title']); ?>
                        </h1>
                        <div class="text-sm md:text-base text-gray-600 dark:text-gray-300 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($section['description'] ?? '')); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

<!-- Back Button - Only show when viewing specific content -->
<div x-show="$store.content.activeChapterType === 'chapter' || $store.content.activeChapterType === 'quiz'" 
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-2"
     class="mb-4">
    <button @click="handleBackNavigation()" 
            class="group inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 hover:shadow-md transition-all duration-200 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
        <svg class="w-4 h-4 transition-transform duration-200 group-hover:-translate-x-0.5" 
             fill="none" 
             stroke="currentColor" 
             viewBox="0 0 24 24">
            <path stroke-linecap="round" 
                  stroke-linejoin="round" 
                  stroke-width="2" 
                  d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="text-sm font-medium">Back</span>
    </button>
</div>

<!-- Content Card -->
<div class="bg-white dark:bg-black rounded-lg shadow-lg border border-gray-200 dark:border-dark-border relative min-h-[50vh] md:min-h-0">
    <div class="p-6 md:p-8">
        <div class="prose dark:prose-invert max-w-none">
            <div id="content-area"
                 class="min-h-[50vh] md:min-h-[50vh] text-base md:text-lg leading-relaxed text-gray-700 dark:text-gray-300"
                 x-data="{ 
                     currentSection: <?php echo is_array($current_section) ? $current_section['id'] : 'null' ?>,
                     currentChapter: <?php echo is_array($current_chapter) ? $current_chapter['id'] : 'null' ?>,
                     quizError: false,
                     quizLoading: true,
                     quizData: null,
                     results: null,
                     showRetakeLimitModal: false
                 }"
                 x-init="
                    $watch('$store.content.activeContent', value => {
                        currentSection = value;
                        // Find the chapter for this section
                        <?php foreach ($sections as $section): ?>
                            <?php if (!empty($section['chapters'])): ?>
                                <?php foreach ($section['chapters'] as $chapter): ?>
                                    if (value === <?php echo $section['id']; ?>) {
                                        currentChapter = <?php echo $chapter['id']; ?>;
                                    }
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    });
                 ">

                <!-- Show Title and Content Only if Quiz is Hidden -->
                <div x-show="!$store.content.showQuiz">
                    <!-- Display individual chapter when chapter is selected -->
                    <div x-show="$store.content.activeChapterType === 'chapter'">
                        <?php foreach ($chapters as $chapter): ?>
                            <div x-show="$store.content.activeChapter === <?php echo $chapter['id']; ?>">
                                <!-- Chapter Header -->
                                <div class="chapter-header">
                                    <h1 class="chapter-title text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white mb-3 md:mb-4 leading-tight">
                                        <?php echo htmlspecialchars($chapter['title']); ?>
                                    </h1>
                                    <?php if (!empty($chapter['description'])): ?>
                                        <p class="chapter-description text-sm sm:text-base md:text-lg text-gray-600 dark:text-gray-400 leading-relaxed">
                                            <?php echo htmlspecialchars($chapter['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Display content based on content_type -->
                                <?php if ($chapter['content_type'] === 'video' && !empty($chapter['video_type'])): ?>
                                    <!-- Video Content Section -->
                                    <div class="video-section mb-8 md:mb-10">
                                        <h3 class="flex items-center text-lg md:text-xl font-semibold text-gray-900 dark:text-white mb-4 rubik-semibold">
                                            <svg class="w-5 h-5 md:w-6 md:h-6 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                            </svg>
                                            Video Content
                                        </h3>
                                        
                                        <?php if ($chapter['video_type'] === 'url'): ?>
                                            <!-- Video URL Content -->
                                            <div class="video-container w-full aspect-video rounded-lg overflow-hidden shadow-lg">
                                                <?php
                                                $video_url = $chapter['video_url'];
                                                $embed_url = '';
                                                
                                                // Convert YouTube URLs to embed format with API support
                                                if (strpos($video_url, 'youtube.com/watch') !== false || strpos($video_url, 'youtu.be/') !== false) {
                                                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $matches)) {
                                                        $video_id = $matches[1];
                                                        $origin = urlencode($_SERVER['HTTP_HOST']);
                                                        $embed_url = "https://www.youtube.com/embed/{$video_id}?enablejsapi=1&origin={$origin}&autoplay=0&controls=1&rel=0&showinfo=0";
                                                    }
                                                }
                                                // Convert Vimeo URLs to embed format with API support
                                                elseif (strpos($video_url, 'vimeo.com') !== false) {
                                                    if (preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches)) {
                                                        $embed_url = 'https://player.vimeo.com/video/' . $matches[1] . '?api=1&player_id=vimeo_' . $chapter['id'];
                                                    }
                                                }
                                                // For direct video URLs, use video element
                                                else {
                                                    $embed_url = $video_url;
                                                }
                                                ?>
                                                
                                                <?php if ($embed_url && (strpos($embed_url, 'youtube.com') !== false || strpos($embed_url, 'vimeo.com') !== false)): ?>
                                                    <iframe src="<?php echo htmlspecialchars($embed_url); ?>" 
                                                            frameborder="0" 
                                                            allowfullscreen
                                                            class="w-full h-full"
                                                            data-chapter-id="<?php echo $chapter['id']; ?>"
                                                            data-section-id="<?php echo $chapter['section_id']; ?>"
                                                            data-course-id="<?php echo $course_id; ?>">
                                                    </iframe>
                                                <?php elseif ($embed_url): ?>
                                                    <video controls
                                                           class="w-full h-full"
                                                           data-chapter-id="<?php echo $chapter['id']; ?>"
                                                           data-section-id="<?php echo $chapter['section_id']; ?>"
                                                           data-course-id="<?php echo $course_id; ?>">
                                                        <source src="<?php echo htmlspecialchars($embed_url); ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php else: ?>
                                                    <div class="flex items-center justify-center h-full text-white bg-gray-800">
                                                        <div class="text-center">
                                                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            <p>Invalid video URL format</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                        <?php elseif ($chapter['video_type'] === 'upload' && !empty($chapter['video_file_path'])): ?>
                                            <!-- Uploaded Video Content -->
                                            <div class="video-container w-full aspect-video rounded-lg overflow-hidden shadow-lg">
                                                <video controls
                                                       class="w-full h-full"
                                                       data-chapter-id="<?php echo $chapter['id']; ?>"
                                                       data-section-id="<?php echo $chapter['section_id']; ?>"
                                                       data-course-id="<?php echo $course_id; ?>">
                                                    <source src="../uploads/chapter_videos/<?php echo htmlspecialchars($chapter['video_file_path']); ?>" type="video/mp4">
                                                    Your browser does not support the video tag.
                                                </video>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                <?php elseif ($chapter['content_type'] === 'text'): ?>
                                    <!-- Text Content Section -->
                                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 sm:p-6 md:p-8 mb-6 sm:mb-8 md:mb-10"
                                         data-content-type="text"
                                         data-chapter-id="<?php echo $chapter['id']; ?>"
                                         data-section-id="<?php echo $chapter['section_id']; ?>"
                                         data-course-id="<?php echo $course_id; ?>">
                                        <div class="flex items-center mb-4 sm:mb-6">
                                             <div class="w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10 bg-red-500 dark:bg-red-600 rounded-lg flex items-center justify-center mr-3 sm:mr-4">
                                                 <svg class="w-3 h-3 sm:w-4 sm:h-4 md:w-5 md:h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0v12h8V4H6z" clip-rule="evenodd"></path>
                                                    <path fill-rule="evenodd" d="M8 6a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1zm0 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1zm0 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <h3 class="text-base sm:text-lg md:text-xl font-semibold text-red-600 dark:text-white rubik-semibold">
                                                Reading Material
                                            </h3>
                                        </div>
                                        
                                        <div>
                                            <?php if (!empty($chapter['content'])): ?>
                                                <div class="prose prose-xs sm:prose-sm md:prose-base dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                                                    <?php echo $chapter['content']; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-6 sm:py-8 md:py-12">
                                                    <div class="animate-spin rounded-full h-6 w-6 sm:h-8 sm:w-8 md:h-12 md:w-12 border-b-2 border-blue-600 mx-auto mb-3 sm:mb-4"></div>
                                                    <h4 class="text-base sm:text-lg md:text-xl font-semibold text-gray-900 dark:text-white mb-2 rubik-semibold">Content Coming Soon</h4>
                                                    <p class="text-xs sm:text-sm md:text-base text-gray-600 dark:text-gray-400 rubik-regular">This reading material is being prepared and will be available shortly.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                <?php else: ?>
                                    <!-- Other content types or generic chapter display -->
                                    <div class="modern-content-card"
                                         data-content-type="text"
                                         data-chapter-id="<?php echo $chapter['id']; ?>"
                                         data-section-id="<?php echo $chapter['section_id']; ?>"
                                         data-course-id="<?php echo $course_id; ?>">
                                        <div class="content-card-header">
                                            <div class="content-icon-wrapper">
                                                <svg class="content-card-icon" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0v12h8V4H6z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <h3 class="rubik-semibold content-card-title">
                                                Chapter Content
                                            </h3>
                                        </div>
                                        
                                        <div class="content-card-body">
                                            <?php if (!empty($chapter['content'])): ?>
                                                <div class="reading-content">
                                                    <?php echo $chapter['content']; ?>
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
                                                        <p class="rubik-regular empty-description">This chapter is being prepared and will be available shortly.</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quiz Content -->
                <div x-show="$store.content.showQuiz" class="quiz-content">
                    <!-- Loading State -->
                    <div x-show="$store.content.quizLoading" class="text-center py-8">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-red mx-auto"></div>
                        <p class="mt-4 text-gray-600 dark:text-gray-400">Loading quiz...</p>
                    </div>


                    <!-- Error State -->
                    <div x-show="$store.content.quizError" class="text-center py-8">
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                            <p class="text-red-600 dark:text-red-400">Failed to load quiz. Please try again.</p>
                            <button @click="$store.content.setContent($store.content.activeContent, true)" 
                                    class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                                Try Again
                            </button>
                        </div>
                    </div>

<!-- Quiz Component -->
<template x-if="!$store.content.quizLoading && !$store.content.quizError && $store.content.quizData">
<div x-data="{
    ...quizComponent($store.content.quizData?.id || $store.content.activeContent),
    isRecording: false,
    // Safe pagination accessor with proper fallback
    get safePagination() {
        if (!this.pagination) {
            return {
                current_page: 1,
                total_pages: 1,
                total_questions: 0,
                has_previous: false,
                has_next: false
            };
        }
        return this.pagination;
    }
}"
         x-init="
            // Initialize quiz data safely
            $nextTick(() => {
                if ($store.content.quizData && !this.quizData) {
                    this.quizData = $store.content.quizData;
                    
                // Initialize pagination with safe defaults
                this.pagination = $store.content.quizData.pagination || {
                    current_page: 1,
                    total_pages: Math.max(1, ($store.content.quizData.questions?.length || 0)),
                    total_questions: $store.content.quizData.questions?.length || 0,
                    has_previous: false,
                    has_next: ($store.content.quizData.questions?.length || 0) > 1
                };
                
                this.questions = $store.content.quizData.questions || [];
                
                // Initialize retake data if available
                if ($store.content.quizData.attempt_count !== undefined) {
                    this.attemptCount = $store.content.quizData.attempt_count;
                }
                if ($store.content.quizData.max_retakes !== undefined) {
                    this.maxRetakes = $store.content.quizData.max_retakes;
                }
                
                // Debug logging
                console.log('DEBUG: max_retakes:', this.maxRetakes);
                console.log('DEBUG: attempt_count:', this.attemptCount);
            }
        });

    // Watch for answer changes to update completion status
    $watch('answers', () => {
        const totalQuestions = pagination?.totalquestions || questions?.length || 0;
        const answeredCount = getAnsweredCount();
        isComplete = answeredCount >= totalQuestions && totalQuestions > 0;
        console.log('Quiz completion check:', { answeredCount, totalQuestions, isComplete });
    });

            
        // Watch for quiz completion changes
        Alpine.effect(() => {
            const quizCompleted = this.quizData?.quizCompleted;
            if (quizCompleted !== undefined) {
                console.log('Quiz completion status changed:', quizCompleted);
                if (this.updateNextButton) {
                    this.updateNextButton();
                }
            }
        });
        
        // Update next button
        setTimeout(() => {
            if (this.updateNextButton) {
                this.updateNextButton();
            }
        }, 100);
     "
     class="quiz-container bg-white dark:bg-dark-surface rounded-lg shadow p-6 border border-gray-200 dark:border-dark-border max-md:p-4 max-[500px]:p-2">        
    <!-- Quiz Header -->
    <div class="mb-6 md:mb-8 quiz-header max-md:mb-4 max-[500px]:mb-2">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-2 gap-3 quiz-metadata max-md:flex-col max-md:gap-2 max-[500px]:gap-1">
            <h3 class="text-xl md:text-2xl font-bold rubik-bold text-gray-900 dark:text-white max-md:text-lg max-[500px]:text-base" 
                x-text="$store.content.quizData?.title || 'Section Quiz'"></h3>
            <div class="flex items-center space-x-3">
                <!-- Question Counter -->
                <template x-if="safePagination.total_pages > 1">
                    <span class="text-sm text-gray-500 dark:text-gray-400 rubik-medium bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full">
                        Question <span x-text="safePagination.current_page"></span> of <span x-text="safePagination.total_pages"></span>
                    </span>
                </template>
            </div>
        </div>
        <template x-if="$store.content.quizData?.description">
            <p class="text-gray-600 dark:text-gray-400 mb-4" x-text="$store.content.quizData.description"></p>
        </template>
        
        <!-- Progress Indicator -->
        <div x-show="safePagination.total_pages > 1" class="mb-4">
            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                <span class="rubik-medium">Quiz Progress</span>
                <div class="flex items-center space-x-4">
                    <span class="rubik-medium" x-text="getAnsweredCount() + ' / ' + (safePagination.total_questions || 0) + ' answered'"></span>
                </div>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                     :style="'width: ' + getProgressPercentage() + '%'"></div>
            </div>
        </div>
    </div>
                            <!-- Quiz Already Completed Message -->
                            <div x-show="!loading && !error && quizCompleted && submitted" class="text-center py-8">
                                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-6 mb-6">
                                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-green-100 dark:bg-green-800 rounded-full">
                                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-xl font-bold text-green-800 dark:text-green-200 mb-2">Quiz Already Completed!</h3>
                                    <p class="text-green-600 dark:text-green-300 mb-4">You have already completed this quiz. Your previous score will be displayed below.</p>
                                    <button @click="showRetakeDialog()" 
                                            class="px-6 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700 transition-colors">
                                        Retake Quiz
                                    </button>
                                </div>
                            </div>

                            <!-- Quiz Questions -->
    <!-- Quiz Questions -->
    <div x-show="!loading && !error && !submitted && !quizCompleted" class="space-y-6 md:space-y-8">
        <template x-for="(question, index) in (questions || [])" :key="question.id">
            <div class="question-container p-4 md:p-6 bg-gray-50 dark:bg-dark-border rounded-lg max-md:p-3 max-[500px]:p-2">
                <div class="flex items-start question-header max-md:flex-col max-md:gap-3 max-[500px]:gap-2">
                                        <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-brand-red text-white font-medium rubik-medium text-sm md:text-base question-number max-md:w-10 max-md:h-10 max-md:text-base max-[500px]:w-8 max-[500px]:h-8 max-[500px]:text-sm" x-text="safePagination.current_page"></span>
                                        <div class="ml-3 md:ml-4 flex-grow question-text max-md:ml-0 max-md:w-full">
                                                <!-- Display the full question text -->
                                                <p class="text-base md:text-lg font-medium rubik-medium text-gray-900 dark:text-white mb-3 md:mb-4 leading-relaxed max-md:text-sm max-md:mb-3 max-[500px]:text-xs max-[500px]:mb-2" 
                                                   x-text="question.text"></p>
                                                
                                                <!-- Multiple Choice -->
                                                <template x-if="question.type === 'multiple_choice'">
                                                    <div class="space-y-2 md:space-y-3 answer-choices max-md:space-y-2 max-[500px]:space-y-1">
                                                        <template x-for="choice in question.choices" :key="choice.id">
                                                            <label class="flex items-start md:items-center p-3 md:p-4 rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:bg-gray-100 dark:hover:bg-dark-surface transition-colors answer-choice max-md:p-3 max-md:items-center max-[500px]:p-2">
                                                                <input type="radio" 
                                                                       :name="'question_' + question.id"
                                                                       :value="choice.id"
                                                                       x-model="answers[question.id]"
                                                                       @change="onAnswerChange()"
                                                                       class="h-4 w-4 text-brand-red focus:ring-brand-red mt-1 md:mt-0 max-md:h-5 max-md:w-5 max-md:mt-0 max-[500px]:h-4 max-[500px]:w-4">
                                                                <span class="ml-3 text-sm md:text-base text-gray-700 dark:text-gray-300 leading-relaxed max-md:text-sm max-md:ml-2 max-[500px]:text-xs max-[500px]:ml-1" x-text="choice.text"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </template>

                                                <!-- True/False -->
                                                <template x-if="question.type === 'true_false'">
                                                    <div class="space-y-2 md:space-y-3">
                                                        <label class="flex items-center p-3 md:p-4 rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:bg-gray-100 dark:hover:bg-dark-surface transition-colors">
                                                            <input type="radio" 
                                                                   :name="'question_' + question.id"
                                                                   value="true"
                                                                   x-model="answers[question.id]"
                                                                   @change="onAnswerChange()"
                                                                   class="h-4 w-4 text-brand-red focus:ring-brand-red">
                                                            <span class="ml-3 text-sm md:text-base text-gray-700 dark:text-gray-300">True</span>
                                                        </label>
                                                        <label class="flex items-center p-3 md:p-4 rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:bg-gray-100 dark:hover:bg-dark-surface transition-colors">
                                                            <input type="radio" 
                                                                   :name="'question_' + question.id"
                                                                   value="false"
                                                                   x-model="answers[question.id]"
                                                                   @change="onAnswerChange()"
                                                                   class="h-4 w-4 text-brand-red focus:ring-brand-red">
                                                            <span class="ml-3 text-sm md:text-base text-gray-700 dark:text-gray-300">False</span>
                                                        </label>
                                                    </div>
                                                </template>

                                                <!-- Fill in the Blank -->
                                                <template x-if="question.type === 'fill_blank'">
                                                    <div class="mt-2">
                                                        <input type="text" 
                                                               x-model="answers[question.id]"
                                                               @input="onAnswerChange()"
                                                               class="w-full px-3 md:px-4 py-2 md:py-3 text-sm md:text-base border border-gray-300 dark:border-dark-border rounded-lg focus:ring-brand-red focus:border-brand-red"
                                                               placeholder="Enter your answer">
                                                    </div>
                                                </template>

                                                <!-- Word Definition -->
                                                <template x-if="question.type === 'word_definition'">
                                                    <div class="mt-2">
                                                        <div class="bg-amber-50 dark:bg-amber-900/20 border-2 border-dashed border-amber-200 dark:border-amber-700 rounded-lg p-4 mb-4">
                                                            <h3 class="text-lg font-semibold text-amber-800 dark:text-amber-200 mb-4 text-center">Word-Definition Pairs</h3>
                                                            
                                                            <template x-for="(pair, index) in question.word_definition_pairs" :key="index">
                                                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 border border-gray-200 dark:border-gray-600">
                                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                                                        <!-- Japanese Word -->
                                                                        <div>
                                                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Japanese Word</label>
                                                                            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                                                                <p class="text-gray-900 dark:text-white font-medium japanese-font" x-text="pair.word"></p>
                                                                    </div>
                                                                        </div>
                                                                        
                                                                        <!-- Arrow -->
                                                                        <div class="flex justify-center">
                                                                            <div class="w-10 h-10 bg-amber-600 text-white rounded-full flex items-center justify-center">
                                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                                                                </svg>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <!-- Definition Input -->
                                                                        <div>
                                                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Definition</label>
                                                                    <input type="text" 
                                                                           :name="'question_' + question.id + '_' + index"
                                                                           x-model="answers[question.id + '_' + index]"
                                                                           @input="onAnswerChange()"
                                                                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-amber-500 focus:border-amber-500 dark:bg-gray-700 dark:text-white"
                                                                                   placeholder="e.g., A greeting used during the day">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            
                                                            <!-- Show message if no word definition pairs -->
                                                            <div x-show="!question.word_definition_pairs || question.word_definition_pairs.length === 0" 
                                                                 class="text-center py-8 text-gray-500 dark:text-gray-400">
                                                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                                                <p>No word-definition pairs available for this question.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>


                                                <!-- Pronunciation Check -->
                                                <template x-if="question.type === 'pronunciation'">
                                                    <div class="mt-2">
                                                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                                            <!-- Display question text if available (but not for pronunciation questions since word is shown above) -->
                                                            <div x-show="question.text && question.type !== 'pronunciation'" class="mb-4 p-3 bg-white dark:bg-gray-800 rounded-lg border border-blue-200 dark:border-blue-700">
                                                                <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Question:</p>
                                                                <p class="text-lg font-medium text-gray-900 dark:text-white" x-text="question.text"></p>
                                                            </div>
                                                            
                                                            <!-- Display Japanese word and details if available -->
                                                            <div x-show="question.word || question.romaji || question.meaning" class="mb-4 p-4 bg-white dark:bg-gray-800 rounded-lg border border-blue-200 dark:border-blue-700">
                                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                                                                    <div x-show="question.word" class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                                                                        <p class="text-xs text-blue-600 dark:text-blue-400 mb-1 font-medium">Japanese</p>
                                                                        <p class="text-2xl font-bold text-gray-900 dark:text-white japanese-font" x-text="question.word"></p>
                                                                    </div>
                                                                    <div x-show="question.romaji" class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1 font-medium">Romaji</p>
                                                                        <p class="text-lg text-gray-700 dark:text-gray-300 font-medium" x-text="question.romaji"></p>
                                                                    </div>
                                                                    <div x-show="question.meaning" class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                                                        <p class="text-xs text-green-600 dark:text-green-400 mb-1 font-medium">Meaning</p>
                                                                        <p class="text-sm text-gray-700 dark:text-gray-300" x-text="question.meaning"></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Click the microphone to record your pronunciation:</p>
                                                            <button type="button" 
                                                                    @click="recordPronunciation(question.id)"
                                                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center justify-center space-x-2">
                                                                <i class="fas fa-microphone text-lg"></i>
                                                                <span class="font-medium">Record Pronunciation</span>
                                                            </button>
                                                            
                                                            <!-- Recording indicator -->
                                                            <div x-show="isRecording" class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg">
                                                                <div class="flex items-center space-x-2">
                                                                    <div class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
                                                                    <span class="text-sm text-red-700 dark:text-red-300 font-medium">Recording... Speak now!</span>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Recording completed -->
                                                            <div x-show="answers[question.id] && !isRecording" class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg">
                                                                <div class="flex items-center space-x-2 mb-2">
                                                                    <i class="fas fa-check-circle text-green-600"></i>
                                                                    <span class="text-sm text-green-700 dark:text-green-300 font-medium">Recording completed!</span>
                                                                </div>
                                                                <p class="text-xs text-green-600 dark:text-green-400 mb-2">Your pronunciation recording:</p>
                                                                <div x-show="answers[question.id]" x-text="'Debug: ' + JSON.stringify(answers[question.id]).substring(0, 100) + '...'"></div>
                                                                <audio controls class="w-full" preload="metadata" @error="console.log('Audio error:', $event)" @loadstart="console.log('Audio load start')" @loadeddata="console.log('Audio loaded')">
                                                                    <source :src="answers[question.id]?.audioUrl || answers[question.id]" :type="answers[question.id]?.mimeType || 'audio/webm'">
                                                                    Your browser does not support the audio element.
                                                                </audio>
                                                                <button @click="if (answers[question.id]?.audioUrl) { URL.revokeObjectURL(answers[question.id].audioUrl); } answers[question.id] = null; onAnswerChange()" 
                                                                        class="mt-2 text-xs text-red-600 hover:text-red-800 underline">
                                                                    Delete recording
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>



                                                <!-- Sentence Translation -->
                                                <template x-if="question.type === 'sentence_translation'">
                                                    <div class="mt-2">
                                                        <div class="bg-green-50 dark:bg-green-900/20 border-2 border-dashed border-green-200 dark:border-green-700 rounded-lg p-4 mb-4">
                                                            <h3 class="text-lg font-semibold text-green-800 dark:text-green-200 mb-4 text-center">Translation Pairs</h3>
                                                            
                                                            <template x-for="(pair, index) in question.translation_pairs" :key="index">
                                                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 border border-gray-200 dark:border-gray-600">
                                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                                                        <!-- Japanese Sentence -->
                                                                        <div>
                                                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Japanese Sentence</label>
                                                                            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                                                                <p class="text-gray-900 dark:text-white font-medium" x-text="pair.japanese"></p>
                                                                    </div>
                                                                        </div>
                                                                        
                                                                        <!-- Arrow -->
                                                                        <div class="flex justify-center">
                                                                            <div class="w-10 h-10 bg-green-600 text-white rounded-full flex items-center justify-center">
                                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                                                                </svg>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <!-- English Translation Input -->
                                                                        <div>
                                                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">English Translation</label>
                                                                    <input type="text" 
                                                                           :name="'question_' + question.id + '_' + index"
                                                                           x-model="answers[question.id + '_' + index]"
                                                                           @input="onAnswerChange()"
                                                                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white"
                                                                                   placeholder="e.g., I am a student.">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            
                                                            <!-- Show message if no translation pairs -->
                                                            <div x-show="!question.translation_pairs || question.translation_pairs.length === 0" 
                                                                 class="text-center py-8 text-gray-500 dark:text-gray-400">
                                                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                                                <p>No translation pairs available for this question.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>


                                                <!-- Fallback for unknown question types -->
                                                <template x-if="!['multiple_choice', 'true_false', 'fill_blank', 'word_definition', 'pronunciation', 'sentence_translation'].includes(question.type)">
                                                    <div class="mt-2">
                                                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
                                                            <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Question Type: <span class="font-medium" x-text="question.type"></span></p>
                                                            <p class="text-xs text-blue-600 dark:text-blue-400 mb-3">This question type is supported. Please provide your answer below:</p>
                                                            <textarea x-model="answers[question.id]"
                                                                      @input="onAnswerChange()"
                                                                      class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-brand-red focus:border-brand-red"
                                                                      rows="3"
                                                                      placeholder="Enter your answer here..."></textarea>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>

        <!-- Pagination Controls -->
        <div x-show="safePagination.total_pages > 1" class="mt-8 flex justify-between items-center quiz-navigation max-sm:flex-col max-sm:gap-3 max-sm:mt-6 max-[500px]:mt-4 max-[500px]:gap-2">
            <!-- Previous Button - FIXED -->
            <button @click="previousPage()" 
                    :disabled="!safePagination.has_previous"
                    class="flex items-center px-4 py-2 text-sm font-medium rubik-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed max-sm:w-full max-sm:justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Previous Question
            </button>

                                    
            <!-- Page Indicator - FIXED -->
            <div class="flex items-center space-x-2 max-sm:order-first">
                <span class="text-sm text-gray-600 dark:text-gray-400 rubik-medium max-sm:text-xs">
                    Question <span x-text="safePagination.current_page"></span> of <span x-text="safePagination.total_pages"></span>
                </span>
            </div>
            
            <!-- Next Button - FIXED -->
            <button @click="nextPage()" 
                    :disabled="!safePagination.has_next"
                    class="flex items-center px-4 py-2 text-sm font-medium rubik-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed max-sm:w-full max-sm:justify-center">
                Next Question
                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        </div>

        <!-- Submit Button - FIXED to use safePagination -->
        <div x-show="safePagination.current_page === safePagination.total_pages" class="mt-6 md:mt-8 flex justify-center md:justify-end">
            <button @click="testDialog()" 
                    :disabled="!isComplete"
                    class="w-full md:w-auto px-6 py-3 bg-brand-red text-white rounded-lg font-medium rubik-medium shadow-lg hover:bg-brand-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed submit-button">
                Submit Quiz
            </button>
        </div>
    </div>



                            <!-- Results -->
                            <div x-show="submitted && !error" class="space-y-6">
                            <div class="text-center p-6 bg-gray-50 dark:bg-dark-border rounded-lg">
                                    <template x-if="results">
                                        <div>
                                            <h4 class="text-2xl font-bold rubik-bold mb-4" x-text="'Score: ' + (results.score || 0) + '/' + (results.total || 0)"></h4>
                                            <p class="text-lg text-gray-600 dark:text-gray-400 mb-2" x-text="'Percentage: ' + (results && results.total > 0 ? Math.round((results.score || 0)/(results.total || 1) * 100) : 0) + '%'"></p>
                                            <p class="text-sm text-blue-600 dark:text-blue-400 font-medium" x-text="'Attempt #' + (results.attempt_number || 1)"></p>
                                        </div>
                                    </template>
                                    <template x-if="!results">
                                        <div>
                                            <p class="text-gray-500">No results available</p>
                                        </div>
                                    </template>
                                </div>

                                <!-- Question Review -->
                                <div class="space-y-6" x-show="results && results.questions && results.questions.length > 0">
                                <template x-for="(result, index) in (results && results.questions ? results.questions : [])" :key="result.id">                                        <div class="p-4 bg-gray-50 dark:bg-dark-border rounded-lg">
                                            <div class="flex items-start">
                                                <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full"
                                                      :class="result.correct ? 'bg-green-500' : 'bg-red-500'"
                                                      x-text="index + 1"></span>
                                                <div class="ml-4 flex-grow">
                                                    <p class="text-lg font-medium rubik-medium text-gray-900 dark:text-white mb-2" x-text="result.text"></p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        Your answer: <span x-text="result.type === 'pronunciation' ? 'Audio response submitted' : (result.user_answer || 'No answer provided')"></span>
                                                    </p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        Correct answer: <span x-text="result.correct_answer || 'N/A'"></span>
                                                    </p>
                                                    <p class="text-sm font-medium" :class="result.correct ? 'text-green-600' : 'text-red-600'">
                                                        <span x-text="result.correct ? '✓ Correct' : '✗ Incorrect'"></span>
                                                        <span x-text="' (' + result.points + ' points)'"></span>
                                                    </p>
                                                </div>
                                            </div>
                        </div>
                    </template>
                                </div>
                                
                            </div>
                            <!-- Submit Confirmation Modal -->
                            <div x-show="showSubmitDialog" 
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="fixed inset-0 z-50 overflow-y-auto" 
                                 style="display: none;">
                                <div class="flex items-center justify-center min-h-screen p-4">
                                    <!-- Background overlay -->
                                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                                         @click="showSubmitDialog = false"></div>

                                    <!-- Modal panel -->
                                    <div class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                        <div class="bg-white dark:bg-dark-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                            <div class="sm:flex sm:items-start">
                                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                                                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                                                    </svg>
                                                </div>
                                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                                        Submit Quiz
                                                    </h3>
                                                    <div class="mt-2">
                                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                                            Are you sure you want to submit your quiz? You have answered 
                                                            <span x-text="getAnsweredCount()"></span> out of 
                                                            <span x-text="pagination?.total_questions || 0"></span> questions.
                                                        </p>
                                                        <div class="mt-3">
                                                            <div class="bg-gray-200 dark:bg-dark-border rounded-full h-2">
                                                                <div class="bg-brand-red h-2 rounded-full transition-all duration-300" 
                                                                     :style="'width: ' + getProgressPercentage() + '%'"></div>
                                                            </div>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                                Progress: <span x-text="Math.round(getProgressPercentage())"></span>%
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-dark-border px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                            <button @click="submitQuiz(); showSubmitDialog = false"
                                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                                Submit Quiz
                                            </button>
                                            <button @click="showSubmitDialog = false"
                                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-border shadow-sm px-4 py-2 bg-white dark:bg-dark-surface text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-dark-border focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Retake Confirmation Modal -->
                            <div x-show="showRetakeConfirmation" 
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="fixed inset-0 z-50 overflow-y-auto"> 
                                <div class="flex items-center justify-center min-h-screen p-4">
                                    <!-- Background overlay -->
                                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                                         @click="cancelRetake()"></div>

                                    <!-- Modal panel -->
                                    <div class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                        <div class="bg-white dark:bg-dark-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                            <div class="sm:flex sm:items-start">
                                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 dark:bg-orange-900/20 sm:mx-0 sm:h-10 sm:w-10">
                                                    <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                                                    </svg>
                                                </div>
                                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                                        Retake Quiz
                                                    </h3>
                                                    <div class="mt-2">
                                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                                            Are you sure you want to retake this quiz? Your previous score and progress will be reset, and you'll start fresh.
                                                        </p>
                                                        <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                                                <strong>Warning:</strong> This action cannot be undone. Your current score of 
                                                                <span x-text="results ? results.score + '/' + results.total : 'N/A'"></span> 
                                                                will be lost.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-dark-border px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                            <button @click="confirmRetake()"
                                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-orange-600 text-base font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:ml-3 sm:w-auto sm:text-sm">
                                                Yes, Retake Quiz
                                            </button>
                                            <button @click="cancelRetake()"
                                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-border shadow-sm px-4 py-2 bg-white dark:bg-dark-surface text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-dark-border focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Retake Limit Modal -->
                            <div x-show="showRetakeLimitModal"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-200"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="fixed inset-0 z-50 overflow-y-auto">
                                <div class="flex items-center justify-center min-h-screen p-4">
                                    <!-- Background overlay -->
                                    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity" 
                                         @click="showRetakeLimitModal = false"></div>

                                    <!-- Modal panel -->
                                    <div class="relative bg-white dark:bg-dark-surface rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
                                        <!-- Header with gradient background -->
                                        <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 rounded-t-2xl">
                                            <div class="flex items-center justify-center">
                                                <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 bg-white bg-opacity-20 rounded-full">
                                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Content -->
                                        <div class="px-6 py-6 text-center">
                                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">
                                                Maximum Retakes Reached
                                            </h3>
                                            <div class="mb-6">
                                                <p class="text-gray-600 dark:text-gray-400 mb-4"
                                                   x-text="
                                                       (typeof maxRetakes !== 'undefined' && maxRetakes === 0)
                                                           ? 'This quiz allows only one attempt and you have already completed it.'
                                                           : 'You have reached the maximum number of retakes allowed for this quiz.'
                                                   ">
                                                </p>
                                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                                    <div class="flex items-center justify-center mb-2">
                                                        <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <span class="text-sm font-medium text-red-800 dark:text-red-200"
                                                              x-text="
                                                                  (typeof maxRetakes !== 'undefined' && maxRetakes === 0) 
                                                                      ? 'No retakes allowed'
                                                                      : (typeof maxRetakes !== 'undefined' && maxRetakes > 0)
                                                                          ? (typeof attemptCount !== 'undefined' ? attemptCount : 1) + ' / ' + (maxRetakes + 1) + ' attempts used'
                                                                          : (typeof maxRetakes !== 'undefined' && maxRetakes === 1)
                                                                              ? '1/1 attempts used'
                                                                              : 'Unlimited attempts'
                                                              ">
                                                        </span>
                                                    </div>
                                                    <p class="text-xs text-red-700 dark:text-red-300"
                                                       x-text="
                                                           (typeof maxRetakes !== 'undefined' && maxRetakes === 0)
                                                               ? 'No retakes are allowed for this quiz.'
                                                               : 'No more retakes are available for this quiz.'
                                                       ">
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <!-- Action button -->
                                            <button @click="showRetakeLimitModal = false"
                                                    class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white font-medium py-3 px-6 rounded-lg hover:from-red-600 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                                                Understood
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>


                <!-- Modern Welcome Message - shows when no chapter is selected or when section is selected without chapter content -->
                <div x-show="($store.content.activeChapterType !== 'chapter' && !$store.content.showQuiz) || (!$store.content.activeContent && !$store.content.showQuiz)" class="welcome-container max-md:h-auto max-md:min-h-[40vh] max-md:py-4">
                    <div class="modern-welcome-card">
                        <!-- Animated Background Elements -->
                        <div class="welcome-bg-animation">
                            <div class="floating-element element-1"></div>
                            <div class="floating-element element-2"></div>
                            <div class="floating-element element-3"></div>
                            <div class="floating-element element-4"></div>
                        </div>
                        
                        <!-- Main Welcome Content -->
                        <div class="welcome-content max-md:py-2">
                            <!-- Animated Icon -->
                            <div class="welcome-icon-container max-md:mb-2">
                                <div class="welcome-icon-wrapper max-md:w-12 max-md:h-12">
                                    <svg class="welcome-icon max-md:w-8 max-md:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                    <div class="icon-pulse"></div>
                                </div>
                            </div>
                            
                            <!-- Welcome Text with Animation -->
                            <div class="welcome-text-container text-center px-4 sm:px-6 md:px-8 max-md:px-3">
                                <h1 class="welcome-title rubik-bold text-2xl sm:text-3xl md:text-4xl lg:text-5xl mb-4 sm:mb-6 leading-tight max-md:text-xl max-md:mb-3 max-md:leading-snug">
                                    <span class="title-gradient block sm:inline">Start Your</span>
                                    <span class="title-accent block sm:inline sm:ml-2">Learning Journey</span>
                                </h1>
                                <p class="welcome-subtitle rubik-medium text-sm sm:text-base md:text-lg lg:text-xl text-gray-600 dark:text-gray-400 leading-relaxed max-w-3xl mx-auto max-md:hidden">
                                    Dive into engaging content, interactive videos, and challenging quizzes designed to enhance your knowledge
                                </p>
                            </div>
                            
                            <!-- Interactive Features -->
                            <div class="welcome-features grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 md:gap-8 px-4 sm:px-6 md:px-8 max-md:hidden">
                                <div class="feature-item flex flex-col items-center text-center p-4 sm:p-6 rounded-lg bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm">
                                    <div class="feature-icon w-12 h-12 sm:w-16 sm:h-16 mb-3 sm:mb-4 flex items-center justify-center rounded-full bg-red-500 text-white">
<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="w-6 h-6 sm:w-8 sm:h-8">
  <path fill-rule="evenodd" clip-rule="evenodd"
        d="M9 3H5a3 3 0 0 0-3 3v10a.75.75 0 0 0 1.2.6A4.5 4.5 0 0 1 5.5 15H9V3zm2 0h4a3 3 0 0 1 3 3v10a.75.75 0 0 1-1.2.6A4.5 4.5 0 0 0 14.5 15H11V3z"/>
</svg>

                                    </div>
                                    <span class="feature-text rubik-medium text-sm sm:text-base md:text-lg text-gray-700 dark:text-gray-300">Rich Content</span>
                                </div>
                                <div class="feature-item flex flex-col items-center text-center p-4 sm:p-6 rounded-lg bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm">
                                    <div class="feature-icon w-12 h-12 sm:w-16 sm:h-16 mb-3 sm:mb-4 flex items-center justify-center rounded-full bg-red-500 text-white">
                                        <svg fill="currentColor" viewBox="0 0 20 20" class="w-6 h-6 sm:w-8 sm:h-8">
                                            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                        </svg>
                                    </div>
                                    <span class="feature-text rubik-medium text-sm sm:text-base md:text-lg text-gray-700 dark:text-gray-300">Video Learning</span>
                                </div>
                                <div class="feature-item flex flex-col items-center text-center p-4 sm:p-6 rounded-lg bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm">
                                    <div class="feature-icon w-12 h-12 sm:w-16 sm:h-16 mb-3 sm:mb-4 flex items-center justify-center rounded-full bg-red-500 text-white">
                                        <svg fill="currentColor" viewBox="0 0 20 20" class="w-6 h-6 sm:w-8 sm:h-8">
                                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <span class="feature-text rubik-medium text-sm sm:text-base md:text-lg text-gray-700 dark:text-gray-300">Interactive Quizzes</span>
                                </div>
                            </div>
                            
                            <!-- Call to Action -->
                            <div class="welcome-cta flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 px-4 sm:px-6 md:px-8 max-md:gap-1 max-md:px-2 max-md:mt-2 max-md:py-1">
                                <div class="cta-arrow w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center max-md:w-4 max-md:h-4">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 sm:w-6 sm:h-6 max-md:w-3 max-md:h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path>
                                    </svg>
                                </div>
                                <span class="cta-text rubik-semibold text-sm sm:text-base md:text-lg text-center max-md:text-[10px] max-md:leading-tight">Select a chapter from the sidebar to begin</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
        </div>
    </div>
</div>

                <!-- Navigation buttons - show when viewing chapter or quiz content -->
                <div x-show="$store.content.activeChapterType === 'chapter' || $store.content.activeChapterType === 'quiz'" class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4 mt-6 md:mt-8 pt-4 md:pt-6 border-t border-gray-200 dark:border-gray-700">
    <div class="flex-1 order-2 md:order-1">
        <!-- Previous Chapter/Section Button -->
        <?php 
        // Find previous chapter/section
        $prev_section_id = null;
        $prev_chapter_id = null;
        $current_found = false;
        $prev_item = null;
        
        foreach ($sections as $section) {
            if (!empty($section['chapters'])) {
                foreach ($section['chapters'] as $chapter) {
                    if ($current_found) {
                        break 2; // Break out of both loops
                    }
                    if (isset($current_chapter) && $chapter['id'] == $current_chapter['id']) {
                        $current_found = true;
                        break;
                    }
                    $prev_item = ['type' => 'chapter', 'section_id' => $section['id'], 'chapter_id' => $chapter['id'], 'title' => $chapter['title']];
                }
            }
            if ($current_found) break;
            if (!isset($current_chapter) && isset($current_section) && $section['id'] == $current_section['id']) {
                $current_found = true;
                break;
            }
            $prev_item = ['type' => 'section', 'section_id' => $section['id'], 'title' => $section['title']];
        }
        ?>
        
        <?php if ($prev_item): ?>
            <a href="continue_learning.php?id=<?php echo $course_id; ?>&section=<?php echo $prev_item['section_id']; ?><?php echo isset($prev_item['chapter_id']) ? '&chapter=' . $prev_item['chapter_id'] : ''; ?>"
               class="inline-flex items-center justify-center md:justify-start px-3 md:px-4 py-2 md:py-2 text-sm font-medium rubik-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors w-full md:w-auto">
                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                <span class="truncate">Previous: <?php echo htmlspecialchars(substr($prev_item['title'], 0, 20)) . (strlen($prev_item['title']) > 20 ? '...' : ''); ?></span>
            </a>
        <?php endif; ?>
    </div>

    <div class="flex-1 order-1 md:order-2 md:text-right">
        <!-- Next Chapter/Section Button -->
        <?php 
        // Find next chapter/section - Fixed logic for correct navigation
        $next_item = null;
        $found_current = false;
        
        // Create a flat list of all chapters in order
        $all_chapters = [];
        foreach ($sections as $section) {
            if (!empty($section['chapters'])) {
                foreach ($section['chapters'] as $chapter) {
                    $all_chapters[] = [
                        'type' => 'chapter',
                        'section_id' => $section['id'],
                        'chapter_id' => $chapter['id'],
                        'title' => $chapter['title'],
                        'section_title' => $section['title']
                    ];
                }
            }
        }
        
        // Find current chapter and get the next one
        if (isset($current_chapter)) {
            for ($i = 0; $i < count($all_chapters); $i++) {
                if ($all_chapters[$i]['chapter_id'] == $current_chapter['id']) {
                    // Found current chapter, check if there's a next one
                    if ($i + 1 < count($all_chapters)) {
                        $next_item = $all_chapters[$i + 1];
                    }
                    break;
                }
            }
        }
        ?>
        
        <!-- Next Chapter/Section Button - Always present, JavaScript will control visibility -->
        <button id="next-button" style="display: none;"
               class="rubik-medium inline-flex items-center justify-center md:justify-end px-3 md:px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 border border-transparent rounded-lg transition-all duration-200 w-full md:w-auto md:ml-auto">
            <span id="next-button-text" class="flex items-center">
                <span class="truncate">Loading...</span>
                <svg class="w-4 h-4 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </span>
        </button>
    </div>
</div>


                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="no-course-container">
                    <div class="no-course-card">
                        <svg class="no-course-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <h2 class="rubik-semibold no-course-title">No Course Selected</h2>
                        <p class="rubik-regular no-course-text">Please select a course to continue learning</p>
                        <a href="student_courses.php" class="rubik-medium no-course-button">Browse Modules</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>




    <!-- AI Chat Widget (included from separate file) -->
    <?php include 'AI/aitomanabi_ai.php'; ?>

    <style>
    /* Japanese transition for smooth animations */
    .japanese-transition {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Chapter Header Styling */
    .chapter-header {
        border-bottom: 2px solid #e53e3e;
        padding-bottom: 1rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .dark .chapter-header {
        border-color: #fc8181;
    }
    
    .chapter-title {
        font-size: 2.5rem;
        font-weight: 700;
        text-align: center;
        color: #dc2626;
        margin-bottom: 1rem;
        font-family: "Rubik", sans-serif;
        line-height: 1.2;
    }
    
    .dark .chapter-title {
        color: #f87171;
    }
    
    .chapter-description {
        text-align: center;
        color: #6b7280;
        font-size: 1.125rem;
        margin-bottom: 2rem;
        font-family: "Rubik", sans-serif;
    }
    
    .dark .chapter-description {
        color: #9ca3af;
    }
    
    /* Reading Content Styling - Exact TinyMCE Match */
    .reading-content {
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        line-height: 1.6;
        color: #374151;
        background-color: #ffffff;
        margin: 16px 0;
        word-wrap: break-word;
        /* Allow HTML formatting to work properly */
    }
    
    .dark .reading-content {
        color: #d1d5db;
        background-color: transparent;
    }
    
    /* TinyMCE Block Elements - Exact Match */
    .reading-content div {
        margin-bottom: 1em;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        line-height: 1.6;
        color: #374151;
    }
    
    .reading-content div:last-child {
        margin-bottom: 0;
    }
    
    .dark .reading-content div {
        color: #d1d5db;
    }
    
    /* TinyMCE Headings - Exact Match */
    .reading-content h1, .reading-content h2, .reading-content h3, 
    .reading-content h4, .reading-content h5, .reading-content h6 {
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #111827;
        margin-top: 1.5em;
        margin-bottom: 0.5em;
        font-weight: 600;
        line-height: 1.2;
    }
    
    .dark .reading-content h1, .dark .reading-content h2, .dark .reading-content h3,
    .dark .reading-content h4, .dark .reading-content h5, .dark .reading-content h6 {
        color: #f9fafb;
    }
    
    .reading-content h1 {
        font-size: 1.875rem;
    }
    
    .reading-content h2 {
        font-size: 1.5rem;
    }
    
    .reading-content h3 {
        font-size: 1.25rem;
    }
    
    .reading-content h4 {
        font-size: 1.125rem;
    }
    
    .reading-content h5 {
        font-size: 1rem;
    }
    
    .reading-content h6 {
        font-size: 0.875rem;
    }
    
    /* TinyMCE Paragraphs - Exact Match */
    .reading-content p {
        margin-bottom: 1em;
        line-height: 1.6;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
    }
    
    .reading-content p:last-child {
        margin-bottom: 0;
    }
    
    /* TinyMCE Lists - Exact Match */
    .reading-content ul, .reading-content ol {
        padding-left: 1.5em;
        margin-bottom: 1em;
        line-height: 1.6;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
    }
    
    .reading-content ul {
        list-style-type: disc;
    }
    
    .reading-content ol {
        list-style-type: decimal;
    }
    
    .reading-content li {
        margin-bottom: 0.25em;
        line-height: 1.6;
    }
    
    /* Nested Lists */
    .reading-content ul ul, .reading-content ol ol, 
    .reading-content ul ol, .reading-content ol ul {
        margin-top: 0.25em;
        margin-bottom: 0;
    }
    
    .reading-content ul ul {
        list-style-type: circle;
    }
    
    .reading-content ul ul ul {
        list-style-type: square;
    }
    
    /* TinyMCE Text Formatting - Exact Match */
    .reading-content strong, .reading-content b {
        font-weight: 700;
        color: #374151;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .reading-content em, .reading-content i {
        font-style: italic;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .reading-content u {
        text-decoration: underline;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .reading-content s, .reading-content strike, .reading-content del {
        text-decoration: line-through;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .reading-content sup {
        vertical-align: super;
        font-size: 0.75em;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .reading-content sub {
        vertical-align: sub;
        font-size: 0.75em;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .reading-content mark {
        background-color: #fef08a;
        padding: 0.125em 0.25em;
        border-radius: 0.25em;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .dark .reading-content strong, .dark .reading-content b {
        color: #d1d5db;
    }
    
    .dark .reading-content mark {
        background-color: #451a03;
        color: #fbbf24;
    }
    
    /* TinyMCE Links */
    .reading-content a {
        color: #ef4444;
        text-decoration: underline;
        transition: color 0.2s ease;
    }
    
    .reading-content a:hover {
        color: #dc2626;
        text-decoration: none;
    }
    
    /* TinyMCE Code Elements - Exact Match */
    .reading-content code {
        background-color: #f3f4f6;
        padding: 0.125em 0.25em;
        border-radius: 0.25em;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.875em;
        color: #374151;
    }
    
    .reading-content pre {
        background-color: #f3f4f6;
        padding: 1em;
        border-radius: 0.5em;
        overflow-x: auto;
        margin: 1em 0;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .reading-content pre code {
        background: none;
        padding: 0;
        color: #374151;
        font-size: inherit;
    }
    
    .dark .reading-content code {
        background-color: #374151;
        color: #d1d5db;
    }
    
    .dark .reading-content pre {
        background-color: #374151;
        color: #d1d5db;
    }
    
    .dark .reading-content pre code {
        color: #d1d5db;
    }
    
    /* TinyMCE Blockquotes - Exact Match */
    .reading-content blockquote {
        border-left: 4px solid #e5e7eb;
        padding-left: 1em;
        margin: 1em 0;
        font-style: italic;
        color: #6b7280;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .reading-content blockquote p {
        margin-bottom: 0;
    }
    
    .dark .reading-content blockquote {
        border-left-color: #4b5563;
        color: #9ca3af;
    }
    
    /* TinyMCE Tables - Exact Match */
    .reading-content table {
        border-collapse: collapse;
        width: 100%;
        margin: 1em 0;
        font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
    }
    
    .reading-content table, .reading-content th, .reading-content td {
        border: 1px solid #d1d5db;
    }
    
    .reading-content th, .reading-content td {
        padding: 0.75em;
        text-align: left;
    }
    
    .reading-content th {
        background-color: #f9fafb;
        font-weight: 600;
        color: #374151;
    }
    
    .dark .reading-content table, .dark .reading-content th, .dark .reading-content td {
        border-color: #4b5563;
    }
    
    .dark .reading-content th {
        background-color: #374151;
        color: #f9fafb;
    }
    
    .dark .reading-content td {
        color: #d1d5db;
    }
    
    /* TinyMCE Horizontal Rules */
    .reading-content hr {
        border: none;
        border-top: 2px solid #e5e7eb;
        margin: 2rem 0;
    }
    
    .dark .reading-content hr {
        border-top-color: #4b5563;
    }
    
    /* TinyMCE Images - Exact Match */
    .reading-content img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5em;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin: 1em 0;
    }
    
    /* TinyMCE Alignment Classes */
    .reading-content .mce-content-body {
        font-family: inherit;
    }
    
    .reading-content [style*="text-align: center"] {
        text-align: center !important;
    }
    
    .reading-content [style*="text-align: right"] {
        text-align: right !important;
    }
    
    .reading-content [style*="text-align: justify"] {
        text-align: justify !important;
    }
    
    /* TinyMCE Font Sizes - Preserve custom sizes */
    .reading-content [style*="font-size"] {
        /* Preserve custom font sizes from TinyMCE */
    }
    
    /* TinyMCE Colors - Preserve custom colors */
    .reading-content [style*="color"] {
        /* Preserve custom text colors from TinyMCE */
    }
    
    /* TinyMCE Background Colors */
    .reading-content [style*="background-color"] {
        /* Preserve background colors from TinyMCE */
        padding: 0.125rem 0.25rem;
        border-radius: 0.25rem;
    }
    
    /* TinyMCE Indentation */
    .reading-content [style*="margin-left"] {
        /* Preserve margin indentation from TinyMCE */
    }
    
    .reading-content [style*="padding-left"] {
        /* Preserve padding indentation from TinyMCE */
    }
    
    /* TinyMCE Line Height */
    .reading-content [style*="line-height"] {
        /* Preserve custom line heights from TinyMCE */
    }
    
    /* TinyMCE Spacing */
    .reading-content [style*="margin"] {
        /* Preserve custom margins from TinyMCE */
    }
    
    .reading-content [style*="padding"] {
        /* Preserve custom padding from TinyMCE */
    }
    
    /* TinyMCE Specific Elements */
    .reading-content div {
        margin-bottom: 1rem;
    }
    
    .reading-content span {
        /* Preserve inline formatting */
    }
    
    /* TinyMCE Lists */
    .reading-content ul li {
        list-style-type: disc;
        margin-left: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .reading-content ol li {
        list-style-type: decimal;
        margin-left: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    /* TinyMCE Text Formatting */
    .reading-content .mce-content-body {
        font-family: inherit;
    }
    
    /* TinyMCE Alignment */
    .reading-content .mce-content-body[style*="text-align: center"] {
        text-align: center;
    }
    
    .reading-content .mce-content-body[style*="text-align: right"] {
        text-align: right;
    }
    
    .reading-content .mce-content-body[style*="text-align: justify"] {
        text-align: justify;
    }
    
    /* TinyMCE Font Sizes */
    .reading-content [style*="font-size"] {
        /* Preserve custom font sizes */
    }
    
    /* TinyMCE Colors */
    .reading-content [style*="color"] {
        /* Preserve custom colors */
    }
    
    /* TinyMCE Background Colors */
    .reading-content [style*="background-color"] {
        /* Preserve background colors */
    }
    
    /* TinyMCE Indentation */
    .reading-content [style*="margin-left"] {
        /* Preserve indentation */
    }
    
    .reading-content [style*="padding-left"] {
        /* Preserve padding */
    }
    
    /* Preserve line breaks and formatting */
    .reading-content br {
        display: block;
        margin: 0.5rem 0;
        content: "";
    }
    
    /* TinyMCE Tables */
    .reading-content table.mce-item-table {
        border-collapse: collapse;
        width: 100%;
        margin: 1rem 0;
    }
    
    .reading-content table.mce-item-table td,
    .reading-content table.mce-item-table th {
        border: 1px solid #d1d5db;
        padding: 0.5rem;
    }
    
    .dark .reading-content table.mce-item-table td,
    .dark .reading-content table.mce-item-table th {
        border-color: #4b5563;
    }
    
    /* Sidebar scrolling improvements */
    .sidebar-scrollable {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e0 #f7fafc;
    }
    
    .sidebar-scrollable::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar-scrollable::-webkit-scrollbar-track {
        background: #f7fafc;
        border-radius: 3px;
    }
    
    .sidebar-scrollable::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 3px;
    }
    
    .sidebar-scrollable::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }
    
    /* Dark mode scrollbar */
    .dark .sidebar-scrollable {
        scrollbar-color: #4a5568 #2d3748;
    }
    
    .dark .sidebar-scrollable::-webkit-scrollbar-track {
        background: #2d3748;
    }
    
    .dark .sidebar-scrollable::-webkit-scrollbar-thumb {
        background: #4a5568;
    }
    
    .dark .sidebar-scrollable::-webkit-scrollbar-thumb:hover {
        background: #718096;
    }
    
    /* Smooth transitions for chapter completion states */
    .chapter-item {
        transition: all 0.3s ease-in-out;
    }
    
    .chapter-item .line-through {
        transition: text-decoration 0.2s ease-in-out;
        text-decoration-color: #ef4444; /* Red color for strikethrough */
        text-decoration-thickness: 2px;
    }
    
    /* Completion animation */
    @keyframes checkmark-pop {
        0% { transform: scale(0.8); opacity: 0; }
        50% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
    
    .chapter-item .w-6.bg-green-100 svg {
        animation: checkmark-pop 0.4s ease-out;
    }
    
    /* Green glow effect for completed chapters */
    .chapter-item.completed-chapter {
        border-left: 3px solid #10b981;
        background: linear-gradient(90deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);
    }
    
    /* Hover effects for completed chapters */
    .chapter-item.completed-chapter:hover {
        background: linear-gradient(90deg, rgba(16, 185, 129, 0.1) 0%, transparent 100%);
    }
    
    /* Japanese font styling for pronunciation questions */
    .japanese-font {
        font-family: 'Noto Sans JP', 'Hiragino Sans', 'Yu Gothic', 'Meiryo', sans-serif;
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    
    /* Reading content scrollable styling */
    .reading-content {
        max-height: 60vh; /* Maximum height of 60% of viewport height */
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 8px; /* Add some padding for the scrollbar */
        margin-right: -8px; /* Compensate for the padding */
    }
    
    /* Custom scrollbar styling for reading content */
    .reading-content::-webkit-scrollbar {
        width: 8px;
    }
    
    .reading-content::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    
    .reading-content::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }
    
    .reading-content::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* Dark mode scrollbar styling */
    .dark .reading-content::-webkit-scrollbar-track {
        background: #374151;
    }
    
    .dark .reading-content::-webkit-scrollbar-thumb {
        background: #6b7280;
    }
    
    .dark .reading-content::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
    
    /* Firefox scrollbar styling */
    .reading-content {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
    }
    
    .dark .reading-content {
        scrollbar-color: #6b7280 #374151;
    }
    
    /* Ensure content card body doesn't overflow */
    .content-card-body {
        overflow: hidden;
    }
    
    /* Ensure browser scrolling works properly */
    html, body {
        overflow-x: hidden;
        overflow-y: auto;
        height: auto;
        min-height: 100vh;
    }
    
    /* Main content area scrolling */
    .main-content-area {
        overflow-y: auto;
        overflow-x: hidden;
        height: auto;
        min-height: calc(100vh - 4rem);
    }
    
    /* Ensure the main layout container allows scrolling */
    .main-layout-container {
        min-height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    /* Mobile quiz responsiveness */
    @media (max-width: 767px) {
        .quiz-container {
            margin: 0;
            border-radius: 0;
            box-shadow: none;
            border-left: none;
            border-right: none;
        }
        
        .question-container {
            margin: 0 -1rem;
            border-radius: 0;
        }
        
        .quiz-container .p-6 {
            padding: 1rem;
        }
        
        .quiz-container .p-4 {
            padding: 0.75rem;
        }
        
        /* Ensure text inputs are touch-friendly on mobile */
        input[type="text"], input[type="radio"] {
            min-height: 44px;
        }
        
        /* Make radio buttons larger on mobile */
        input[type="radio"] {
            transform: scale(1.2);
            margin-right: 0.5rem;
        }
        
    }
    
        /* Mobile devices (< 768px) */
        @media (max-width: 767px) {
            /* Quiz container responsiveness for mobile */
            .quiz-container {
                padding: 1rem !important;
                margin: 0.75rem !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }
            
            /* Quiz header optimization for mobile */
            .quiz-header {
                margin-bottom: 1.5rem !important;
            }
            
            .quiz-header h3 {
                font-size: 1.25rem !important;
                line-height: 1.4 !important;
                margin-bottom: 0.75rem !important;
            }
            
            /* Quiz metadata (question count) */
            .quiz-metadata {
                flex-direction: column !important;
                gap: 0.5rem !important;
                align-items: flex-start !important;
            }
            
            }
            
            /* Question number and text layout */
            .question-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.75rem !important;
            }
            
            .question-number {
                width: 2.5rem !important;
                height: 2.5rem !important;
                font-size: 1rem !important;
                flex-shrink: 0 !important;
            }
            
            .question-text {
                font-size: 1rem !important;
                line-height: 1.5 !important;
                width: 100% !important;
            }
            
            /* Answer choices optimization */
            .answer-choices {
                margin-top: 1rem !important;
                gap: 0.75rem !important;
            }
            
            .answer-choice {
                padding: 1rem !important;
                border-radius: 0.75rem !important;
                border: 2px solid #e5e7eb !important;
                transition: all 0.2s ease !important;
            }
            
            .answer-choice:hover {
                border-color: #ef4444 !important;
                background-color: #fef2f2 !important;
            }
            
            .answer-choice input[type="radio"] {
                width: 1.25rem !important;
                height: 1.25rem !important;
                margin-right: 0.75rem !important;
                flex-shrink: 0 !important;
            }
            
            .answer-choice label {
                font-size: 0.9375rem !important;
                line-height: 1.5 !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                width: 100% !important;
            }
            
            /* Submit button optimization */
            .submit-button {
                width: 100% !important;
                padding: 1rem 1.5rem !important;
                font-size: 1rem !important;
                margin-top: 2rem !important;
                border-radius: 0.75rem !important;
            }
        }

        /* Ultra small mobile devices (< 500px) */
        @media (max-width: 499px) {
            /* Quiz container for ultra small screens */
            .quiz-container {
                padding: 0.5rem !important;
                margin: 0.25rem !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                border-radius: 0.5rem !important;
            }
            
            /* Quiz header for ultra small screens */
            .quiz-header {
                margin-bottom: 1rem !important;
            }
            
            .quiz-header h3 {
                font-size: 1.125rem !important; /* 18px */
                line-height: 1.3 !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Quiz metadata for ultra small screens */
            .quiz-metadata {
                gap: 0.25rem !important;
            }
            
            }
            
            /* Question layout for ultra small screens */
            .question-header {
                gap: 0.5rem !important;
            }
            
            .question-number {
                width: 2rem !important;
                height: 2rem !important;
                font-size: 0.875rem !important;
            }
            
            .question-text {
                font-size: 0.875rem !important; /* 14px */
                line-height: 1.4 !important;
            }
            
            .question-container {
                padding: 0.75rem !important;
                margin: 0 -0.25rem !important;
            }
            
            /* Answer choices for ultra small screens */
            .answer-choices {
                margin-top: 0.75rem !important;
                gap: 0.5rem !important;
            }
            
            .answer-choice {
                padding: 0.75rem !important;
                border-radius: 0.5rem !important;
                border: 1px solid #e5e7eb !important;
            }
            
            .answer-choice input[type="radio"] {
                width: 1rem !important;
                height: 1rem !important;
                margin-right: 0.5rem !important;
            }
            
            .answer-choice label {
                font-size: 0.8125rem !important; /* 13px */
                line-height: 1.4 !important;
            }
            
            /* Submit button for ultra small screens */
            .submit-button {
                padding: 0.75rem 1rem !important;
                font-size: 0.875rem !important;
                margin-top: 1.5rem !important;
                border-radius: 0.5rem !important;
            }
            
            /* Quiz navigation for ultra small screens */
            .quiz-navigation {
                padding: 0.5rem !important;
                gap: 0.5rem !important;
                margin-top: 1rem !important;
            }
            
            .quiz-navigation button {
                padding: 0.625rem 0.75rem !important;
                font-size: 0.8125rem !important;
                min-height: 2.25rem !important;
            }
            
            /* Page indicator for ultra small screens */
            .quiz-navigation .max-sm\:text-xs {
                font-size: 0.75rem !important;
            }
        }

        /* Small mobile devices (< 620px) */
        @media (max-width: 619px) {
            /* Quiz container responsiveness for small screens */
            .quiz-container {
                padding: 0.75rem !important;
                margin: 0.5rem !important;
                height: auto !important;
                overflow: visible !important;
            }
            
            .quiz-container h3 {
                font-size: 1.25rem !important; /* 20px */
                line-height: 1.4 !important;
                margin-bottom: 1rem !important;
            }
            
            .question-container {
                padding: 1rem !important;
                margin: 0 -0.25rem !important;
            }
            
            .question-container p {
                font-size: 0.9375rem !important; /* 15px */
                line-height: 1.5 !important;
                margin-bottom: 1rem !important;
            }
            
            .question-container label {
                padding: 0.75rem !important;
                font-size: 0.9375rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .question-container input[type="text"] {
                padding: 0.75rem !important;
                font-size: 0.9375rem !important;
            }
            
            .question-container input[type="radio"] {
                transform: scale(1.1) !important;
                margin-right: 0.5rem !important;
            }
            
            
            /* Quiz navigation buttons for small screens */
            .quiz-navigation {
                padding: 0.75rem !important;
                gap: 0.75rem !important;
            }
            
            .quiz-navigation button {
                padding: 0.75rem 1rem !important;
                font-size: 0.875rem !important;
                min-height: 2.5rem !important;
            }
            
            /* Submit button for small screens */
            .quiz-container button[type="submit"] {
                padding: 0.875rem 1.25rem !important;
                font-size: 0.9375rem !important;
                width: 100% !important;
                margin-top: 1rem !important;
            }
            
            /* Quiz progress for small screens */
            .quiz-progress {
                padding: 0.75rem !important;
                font-size: 0.875rem !important;
            }
            
            /* Question number circle for small screens */
            .question-number {
                width: 2rem !important;
                height: 2rem !important;
                font-size: 0.875rem !important;
            }
        }

        /* Extra small devices (< 330px) */
        @media (max-width: 329px) {
        /* Quiz responsiveness for very small screens */
        .quiz-container {
            padding: 0.5rem !important;
            height: auto !important;
            overflow: visible !important;
        }
        
        .quiz-container h3 {
            font-size: 1.125rem !important; /* 18px */
            line-height: 1.4 !important;
        }
        
        .question-container {
            padding: 0.75rem !important;
            margin: 0 -0.5rem !important;
        }
        
        .question-container p {
            font-size: 0.875rem !important; /* 14px */
            line-height: 1.5 !important;
            margin-bottom: 0.75rem !important;
        }
        
        .question-container label {
            padding: 0.5rem !important;
            font-size: 0.875rem !important;
        }
        
        .question-container input[type="text"] {
            padding: 0.5rem !important;
            font-size: 0.875rem !important;
        }
        
        .question-container input[type="radio"] {
            transform: scale(1.1) !important;
            margin-right: 0.25rem !important;
        }
        
        
        /* Submit button for very small screens */
        .quiz-container button {
            padding: 0.75rem 1rem !important;
            font-size: 0.875rem !important;
            width: 100% !important;
        }
        
        /* Chapter text responsiveness for very small screens */
        .chapter-title {
            font-size: 1.25rem !important; /* 20px */
            line-height: 1.3 !important;
            margin-bottom: 0.75rem !important;
        }
        
        .chapter-description {
            font-size: 0.875rem !important; /* 14px */
            line-height: 1.4 !important;
        }
        
        /* Welcome text responsiveness for very small screens */
        .welcome-title {
            font-size: 1.5rem !important; /* 24px */
            line-height: 1.2 !important;
            margin-bottom: 1rem !important;
        }
        
        .welcome-subtitle {
            font-size: 0.875rem !important; /* 14px */
            line-height: 1.4 !important;
            padding: 0 1rem !important;
        }
        
        .welcome-features {
            gap: 1rem !important;
            padding: 0 1rem !important;
        }
        
        .feature-item {
            padding: 1rem !important;
        }
        
        .feature-icon {
            width: 2.5rem !important;
            height: 2.5rem !important;
            margin-bottom: 0.5rem !important;
        }
        
        .feature-icon svg {
            width: 1.25rem !important;
            height: 1.25rem !important;
        }
        
        .feature-text {
            font-size: 0.75rem !important; /* 12px */
        }
        
        .cta-text {
            font-size: 0.75rem !important; /* 12px */
        }
        
        .cta-arrow {
            width: 1.75rem !important;
            height: 1.75rem !important;
        }
        
        .cta-arrow svg {
            width: 1rem !important;
            height: 1rem !important;
        }
        
        /* Text content responsiveness for very small screens */
        .prose {
            font-size: 0.875rem !important; /* 14px */
            line-height: 1.5 !important;
        }
        
        .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
            font-size: 1rem !important;
            line-height: 1.3 !important;
            margin-bottom: 0.5rem !important;
        }
        
        .prose p {
            margin-bottom: 0.75rem !important;
        }
        
        .prose ul, .prose ol {
            padding-left: 1rem !important;
        }
        
        .prose li {
            margin-bottom: 0.25rem !important;
        }
        
        /* Video section responsiveness for very small screens */
        .video-section h3 {
            font-size: 1rem !important;
            margin-bottom: 0.75rem !important;
        }
        
        .video-section svg {
            width: 1rem !important;
            height: 1rem !important;
            margin-right: 0.5rem !important;
        }
        
        /* Reading material section for very small screens */
        .bg-red-50 .flex.items-center {
            margin-bottom: 1rem !important;
        }
        
        .bg-red-50 .w-6 {
            width: 1.5rem !important;
            height: 1.5rem !important;
            margin-right: 0.5rem !important;
        }
        
        .bg-red-50 .w-3 {
            width: 0.75rem !important;
            height: 0.75rem !important;
        }
        
        .bg-red-50 h3 {
            font-size: 0.875rem !important;
        }
    }
    </style>

    <script>
    // Function to update chapter visual state when completed
    function updateChapterCompletionUI(chapterId) {
        try {
            // Find all chapter buttons for this chapter
            const chapterButtons = document.querySelectorAll(`button[onclick*="setChapterContent"][onclick*="${chapterId}"]`);
            
            chapterButtons.forEach(button => {
                // Update button styling for completed state
                button.classList.add('opacity-75', 'completed-chapter');
                button.classList.remove('text-gray-600', 'dark:text-gray-300');
                button.classList.add('text-gray-400', 'dark:text-gray-500');
                
                // Update chapter number circle
                const numberSpan = button.querySelector('span.w-6.h-6');
                if (numberSpan) {
                    numberSpan.classList.remove('bg-gray-200', 'dark:bg-gray-500', 'text-gray-600', 'dark:text-gray-300');
                    numberSpan.classList.add('bg-green-100', 'text-green-700', 'dark:bg-green-800', 'dark:text-green-200');
                    
                    // Replace number with checkmark
                    numberSpan.innerHTML = `
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    `;
                }
                
                // Add red strikethrough to title
                const titleSpan = button.querySelector('span.flex-1');
                if (titleSpan) {
                    titleSpan.classList.add('line-through', 'text-red-500');
                }
            });
            
            // Update Next button after marking chapter complete
            if (window.Alpine && Alpine.store('content')) {
                Alpine.store('content').updateNextButton();
            }
        } catch (error) {
            console.error('❌ Error updating chapter completion UI:', error);
        }
    }

    // Enhanced Next button click handler with improved feedback
    async function handleNextButtonClick(nextUrl) {
        console.log('🚀 Next button clicked, processing current content...');
        
        // Check if we're navigating to a quiz - skip progress tracking
        const urlParams = new URLSearchParams(nextUrl.split('?')[1] || '');
        const isNavigatingToQuiz = urlParams.has('quiz') && urlParams.get('quiz') === '1';
        
        if (isNavigatingToQuiz) {
            console.log('📝 Navigating to quiz - skipping progress tracking');
            window.location.href = nextUrl;
            return;
        }
        
        try {
            // Check if video progress tracker is available
            if (window.videoProgressTracker) {
                // Attempt to mark current text chapter as complete
                const result = await window.videoProgressTracker.markCurrentTextChapterComplete();
                
                console.log('📋 Text completion result:', result);
                
                if (result && result.success) {
                    // Show success notification for text completion
                    console.log('✅ Text chapter completed successfully:', result);
                    
                    // Update chapter visual state immediately
                    updateChapterCompletionUI(result.chapter_id);
                    
                    // Brief delay to show the progress update, then navigate
                    setTimeout(() => {
                        console.log('🔄 Navigating to next content...');
                        window.location.href = nextUrl;
                    }, 1200);
                } else if (result && result.success === false) {
                    // There was an error but we got a result
                    console.warn('⚠️ Text completion failed:', result.message);
                    window.videoProgressTracker.showErrorNotification(`⚠️ ${result.message || 'Could not save progress'}, but continuing...`);
                    
                    // Still navigate after showing error
                    setTimeout(() => {
                        console.log('🔄 Navigating despite error...');
                        window.location.href = nextUrl;
                    }, 2000);
                } else {
                    // No text chapter to mark, already completed, or viewing other content - navigate immediately
                    console.log('ℹ️ No text completion needed, navigating immediately');
                    window.location.href = nextUrl;
                }
            } else {
                // Fallback if video tracker not loaded
                console.warn('⚠️ Video progress tracker not available, navigating anyway');
                window.location.href = nextUrl;
            }
        } catch (error) {
            console.error('❌ Error in handleNextButtonClick:', error);
            
            // Show error notification but still navigate
            if (window.videoProgressTracker) {
                window.videoProgressTracker.showErrorNotification(`⚠️ Error: ${error.message || 'Could not save progress'}, but continuing...`);
            }
            
            // Still navigate after a short delay
            setTimeout(() => {
                console.log('🔄 Navigating despite error...');
                window.location.href = nextUrl;
            }, 2000);
        }
    }
    
    // Show course completion confirmation dialog
    function showCourseCompletionConfirmation() {
        // Create confirmation modal
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 overflow-y-auto';
        modal.style.display = 'block';
        
        modal.innerHTML = `
            <div class="flex items-center justify-center min-h-screen p-4">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCourseCompletionModal()"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-dark-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/20 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Complete Course
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to finish this module? This will mark the course as completed.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-dark-border px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button onclick="confirmCourseCompletion()"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Yes, Complete Course
                        </button>
                        <button onclick="closeCourseCompletionModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-border shadow-sm px-4 py-2 bg-white dark:bg-dark-surface text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-dark-border focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    // Close course completion modal
    function closeCourseCompletionModal() {
        const modal = document.querySelector('.fixed.inset-0.z-50');
        if (modal) {
            modal.remove();
        }
    }
    
    // Confirm course completion
    async function confirmCourseCompletion() {
        console.log('🎉 Course completion confirmed...');
        
        // Close modal first
        closeCourseCompletionModal();
        
        try {
            // Only mark chapter as complete if we're viewing a chapter, not a quiz
            if (window.videoProgressTracker && window.Alpine && Alpine.store('content')) {
                const activeChapterType = Alpine.store('content').activeChapterType;
                console.log('Current content type:', activeChapterType);
                
                if (activeChapterType === 'chapter') {
                    const result = await window.videoProgressTracker.markCurrentTextChapterComplete();
                    console.log('📋 Final chapter completion result:', result);
                    
                    if (result && result.success) {
                        updateChapterCompletionUI(result.chapter_id);
                    }
                } else {
                    console.log('📝 Skipping chapter completion - not viewing a chapter');
                }
            }
            
            // Update course progress to completed
            try {
                console.log('📊 Attempting to mark course as completed...');
                const response = await fetch('../api/update_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        course_id: <?php echo $course_id; ?>,
                        action: 'complete_course'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const progressResult = await response.json();
                console.log('📊 Course completion API response:', progressResult);
                
                if (progressResult.success) {
                    console.log('✅ Course marked as completed in database');
                } else {
                    console.warn('⚠️ Course completion API warning:', progressResult.message);
                }
            } catch (apiError) {
                console.error('❌ Error updating course completion:', apiError);
            }
            
            // Send module completion email
            try {
                console.log('📧 Sending module completion email...');
                const emailResponse = await fetch('../api/send_module_completion_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        course_id: <?php echo $course_id; ?>
                    })
                });
                
                if (emailResponse.ok) {
                    const emailResult = await emailResponse.json();
                    if (emailResult.success) {
                        console.log('✅ Module completion email sent successfully');
                    } else {
                        console.warn('⚠️ Email sending warning:', emailResult.message);
                    }
                } else {
                    console.warn('⚠️ Email sending failed with status:', emailResponse.status);
                }
            } catch (emailError) {
                console.error('❌ Error sending module completion email:', emailError);
                // Don't block the completion process if email fails
            }
            
            // Navigate to congratulations page
            console.log('🔄 Redirecting to congratulations page...');
            window.location.href = `congratulations.php?id=<?php echo $course_id; ?>`;
            
        } catch (error) {
            console.error('❌ Error in confirmCourseCompletion:', error);
            
            // Still navigate to congratulations page even if there's an error
            setTimeout(() => {
                console.log('🔄 Navigating to congratulations despite error...');
                window.location.href = `congratulations.php?id=<?php echo $course_id; ?>`;
            }, 1000);
        }
    }
    
    // Add page load handler to show content type info
    document.addEventListener('DOMContentLoaded', function() {
        // Check what type of content is currently being viewed
        const videoElements = document.querySelectorAll('video[data-chapter-id], iframe[data-chapter-id]');
        const textElements = document.querySelectorAll('[data-content-type="text"]');
        
        if (videoElements.length > 0) {
            console.log('🎥 Video content detected - completion will be tracked automatically when video ends');
        }
        
        if (textElements.length > 0) {
            console.log('📖 Text content detected - completion will be tracked when Next button is clicked');
        }
        
        // Update Next button on page load
        setTimeout(() => {
            if (window.Alpine && Alpine.store('content')) {
                console.log('Updating next button on page load...');
                Alpine.store('content').updateNextButton();
            }
        }, 1000);
        
        // Also update on window load (after all resources loaded)
        window.addEventListener('load', () => {
            setTimeout(() => {
                if (window.Alpine && Alpine.store('content')) {
                    console.log('Updating next button on window load...');
                    Alpine.store('content').updateNextButton();
                }
            }, 500);
        });
        
        // Periodic check to ensure button is always up to date (every 3 seconds)
        setInterval(() => {
            if (window.Alpine && Alpine.store('content')) {
                const nextButton = document.getElementById('next-button');
                if (nextButton && nextButton.style.display === 'none') {
                    console.log('Next button is hidden, checking if it should be visible...');
                    Alpine.store('content').updateNextButton();
                }
            }
        }, 3000);
    });
    </script>
</body>
</html>
