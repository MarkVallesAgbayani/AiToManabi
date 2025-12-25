<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../config/database.php';

try {
    $teacher_id = $_SESSION['user_id'];
    
    // Get comprehensive student progress data with real calculations
    $progressQuery = "
        SELECT DISTINCT
            u.id as student_id,
            u.username as student_name,
            u.email as student_email,
            u.first_name,
            u.last_name,
            c.id as course_id,
            c.title as course_title,
            c.level,
            e.enrolled_at,
            e.completed_at,
            
            -- Course progress from course_progress table
            COALESCE(cp.completion_percentage, 0) as course_completion_percentage,
            COALESCE(cp.completed_sections, 0) as completed_sections_count,
            cp.completion_status,
            COALESCE(cp.last_accessed_at, e.enrolled_at) as last_activity,
            
            -- Chapter progress calculation (completed chapters)
            COALESCE(
                (SELECT COUNT(*) 
                 FROM text_progress tp 
                 WHERE tp.student_id = u.id AND tp.course_id = c.id AND tp.completed = 1), 
                0
            ) as completed_chapters,
            
            -- Total chapters in course
            COALESCE(
                (SELECT COUNT(*) 
                 FROM chapters ch
                 JOIN sections s ON ch.section_id = s.id
                 WHERE s.course_id = c.id), 
                0
            ) as total_chapters,
            
            -- Quiz attempts and scores
            COALESCE(
                (SELECT COUNT(*) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 JOIN sections s ON q.section_id = s.id 
                 WHERE qa.student_id = u.id AND s.course_id = c.id), 
                0
            ) as quiz_attempts,
            
            COALESCE(
                (SELECT AVG(qa.score) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 JOIN sections s ON q.section_id = s.id 
                 WHERE qa.student_id = u.id AND s.course_id = c.id), 
                0
            ) as average_quiz_score,
            
            -- Current module/section with progress count
            CASE 
                WHEN (
                    SELECT COUNT(DISTINCT tp.section_id) 
                    FROM text_progress tp 
                    JOIN sections s ON tp.section_id = s.id 
                    WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1
                ) >= (
                    SELECT COUNT(*) FROM sections WHERE course_id = c.id
                ) THEN CONCAT(
                    'All Sections Completed (',
                    (SELECT COUNT(DISTINCT tp.section_id) 
                     FROM text_progress tp 
                     JOIN sections s ON tp.section_id = s.id 
                     WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1),
                    '/',
                    (SELECT COUNT(*) FROM sections WHERE course_id = c.id),
                    ')'
                )
                ELSE CONCAT(
                    COALESCE(
                        (SELECT s.title 
                         FROM sections s 
                         LEFT JOIN text_progress tp ON s.id = tp.section_id AND tp.student_id = u.id AND tp.completed = 1
                         WHERE s.course_id = c.id AND tp.section_id IS NULL
                         ORDER BY s.order_index ASC 
                         LIMIT 1),
                        (SELECT s.title 
                         FROM sections s 
                         WHERE s.course_id = c.id 
                         ORDER BY s.order_index ASC 
                         LIMIT 1)
                    ),
                    ' (',
                    (SELECT COUNT(DISTINCT tp.section_id) 
                     FROM text_progress tp 
                     JOIN sections s ON tp.section_id = s.id 
                     WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1),
                    '/',
                    (SELECT COUNT(*) FROM sections WHERE course_id = c.id),
                    ')'
                )
            END as current_module,
            
            CASE 
                WHEN (
                    SELECT COUNT(DISTINCT tp.section_id) 
                    FROM text_progress tp 
                    JOIN sections s ON tp.section_id = s.id 
                    WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1
                ) >= (
                    SELECT COUNT(*) FROM sections WHERE course_id = c.id
                ) THEN CONCAT(
                    'All Sections Completed (',
                    (SELECT COUNT(DISTINCT tp.section_id) 
                     FROM text_progress tp 
                     JOIN sections s ON tp.section_id = s.id 
                     WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1),
                    '/',
                    (SELECT COUNT(*) FROM sections WHERE course_id = c.id),
                    ')'
                )
                ELSE CONCAT(
                    COALESCE(
                        (SELECT s.title 
                         FROM sections s 
                         LEFT JOIN text_progress tp ON s.id = tp.section_id AND tp.student_id = u.id AND tp.completed = 1
                         WHERE s.course_id = c.id AND tp.section_id IS NULL
                         ORDER BY s.order_index ASC 
                         LIMIT 1),
                        (SELECT s.title 
                         FROM sections s 
                         WHERE s.course_id = c.id 
                         ORDER BY s.order_index ASC 
                         LIMIT 1)
                    ),
                    ' (',
                    (SELECT COUNT(DISTINCT tp.section_id) 
                     FROM text_progress tp 
                     JOIN sections s ON tp.section_id = s.id 
                     WHERE tp.student_id = u.id AND s.course_id = c.id AND tp.completed = 1),
                    '/',
                    (SELECT COUNT(*) FROM sections WHERE course_id = c.id),
                    ')'
                )
            END as current_section,
            COALESCE(sp.profile_picture, '') as profile_picture
            
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON u.id = cp.student_id AND c.id = cp.course_id
        LEFT JOIN student_preferences sp ON u.id = sp.student_id
        WHERE u.role = 'student' 
        AND c.teacher_id = ?
        AND u.status = 'active'
        ORDER BY u.username ASC, c.title ASC
    ";
    
    $stmt = $pdo->prepare($progressQuery);
    $stmt->execute([$teacher_id]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate comprehensive overview statistics
    $overviewQuery = "
        SELECT 
            COUNT(DISTINCT u.id) as total_students,
            COUNT(DISTINCT c.id) as total_courses,
            
            -- Calculate proper average progress across all student enrollments
            CASE 
                WHEN COUNT(DISTINCT CONCAT(u.id, '-', c.id)) > 0 
                THEN AVG(
                    CASE 
                        WHEN (SELECT COUNT(*) FROM chapters ch JOIN sections s ON ch.section_id = s.id WHERE s.course_id = c.id) > 0
                        THEN LEAST(100.0, 
                            (SELECT COUNT(*) FROM text_progress tp WHERE tp.student_id = u.id AND tp.course_id = c.id AND tp.completed = 1) * 100.0 / 
                            (SELECT COUNT(*) FROM chapters ch JOIN sections s ON ch.section_id = s.id WHERE s.course_id = c.id)
                        )
                        ELSE 0
                    END
                )
                ELSE 0
            END as average_progress,
            
            -- Count distinct active students (enrolled and active status)
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_students,
            
            -- Quiz statistics
            COUNT(DISTINCT qa.id) as total_quiz_attempts,
            AVG(COALESCE(qa.score, 0)) as average_quiz_score,
            COALESCE(sp.profile_picture, '') as profile_picture
            
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN quiz_attempts qa ON u.id = qa.student_id
        LEFT JOIN quizzes q ON qa.quiz_id = q.id
        LEFT JOIN sections s ON q.section_id = s.id AND s.course_id = c.id
        LEFT JOIN student_preferences sp ON u.id = sp.student_id
        WHERE u.role = 'student' 
        AND c.teacher_id = ?
        AND u.status = 'active'
    ";
    
    $stmt = $pdo->prepare($overviewQuery);
    $stmt->execute([$teacher_id]);
    $overview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Process and enhance progress data
    foreach ($progress as &$item) {
        // Calculate actual progress percentage based on completed chapters
        if ($item['total_chapters'] > 0) {
            $actual_percentage = ($item['completed_chapters'] / $item['total_chapters']) * 100;
            $item['course_completion_percentage'] = $actual_percentage;
        }
        
        // Format data types
        $item['progress_percentage'] = round(floatval($item['course_completion_percentage']), 1);
        $item['total_modules'] = intval($item['total_chapters']);
        $item['completed_modules'] = intval($item['completed_chapters']);
        $item['quiz_attempts'] = intval($item['quiz_attempts']);
        $item['average_quiz_score'] = round(floatval($item['average_quiz_score']), 1);
        
        // Create modules display string for chapters
        $item['modules_completed'] = $item['completed_modules'] . '/' . $item['total_modules'];
        
        // Determine progress status
        if ($item['progress_percentage'] >= 100) {
            $item['status'] = 'completed';
        } elseif ($item['progress_percentage'] > 0) {
            $item['status'] = 'in_progress';
        } else {
            $item['status'] = 'not_started';
        }
        
        // Format student name
        $item['student_display_name'] = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
        if (empty($item['student_display_name'])) {
            $item['student_display_name'] = $item['student_name'];
        }
    }
    
    // Calculate completed and in-progress modules from progress data
    $totalCompletedModules = 0;
    $totalInProgressModules = 0;
    
    foreach ($progress as $item) {
        if ($item['status'] === 'completed') {
            $totalCompletedModules += $item['completed_modules'];
        } elseif ($item['status'] === 'in_progress') {
            $totalInProgressModules += $item['completed_modules'];
        }
    }
    
    // Enhanced overview statistics
    $formattedOverview = [
        'total_students' => intval($overview['total_students'] ?? 0),
        'total_courses' => intval($overview['total_courses'] ?? 0),
        'completed_modules' => $totalCompletedModules,
        'in_progress_modules' => $totalInProgressModules,
        'active_students' => intval($overview['active_students'] ?? 0),
        'average_progress' => round(floatval($overview['average_progress'] ?? 0), 1),
        'total_quiz_attempts' => intval($overview['total_quiz_attempts'] ?? 0),
        'average_quiz_score' => round(floatval($overview['average_quiz_score'] ?? 0), 1)
    ];
    
    // Calculate additional statistics from progress data
    $completedCourses = 0;
    $inProgressCourses = 0;
    $notStartedCourses = 0;
    
    foreach ($progress as $item) {
        switch ($item['status']) {
            case 'completed':
                $completedCourses++;
                break;
            case 'in_progress':
                $inProgressCourses++;
                break;
            case 'not_started':
                $notStartedCourses++;
                break;
        }
    }
    
    $formattedOverview['completed_courses'] = $completedCourses;
    $formattedOverview['in_progress_courses'] = $inProgressCourses;
    $formattedOverview['not_started_courses'] = $notStartedCourses;
    
    echo json_encode([
        'success' => true,
        'progress' => $progress,
        'overview' => $formattedOverview
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_all_student_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load progress data: ' . $e->getMessage()
    ]);
}
?>
