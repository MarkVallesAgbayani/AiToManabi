<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');

// For testing, create temporary session if needed
if (!isset($_SESSION['user_id'])) {
    require_once '../../../config/database.php';
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
    $teacherId = $stmt->fetchColumn();
    if ($teacherId) {
        $_SESSION['user_id'] = $teacherId;
        $_SESSION['role'] = 'teacher';
    }
}

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
            
            -- Text progress calculation
            COALESCE(
                (SELECT COUNT(*) 
                 FROM text_progress tp 
                 WHERE tp.student_id = u.id AND tp.course_id = c.id AND tp.completed = 1), 
                0
            ) as completed_text_modules,
            
            -- Total sections in course (from sections table)
            COALESCE(
                (SELECT COUNT(*) 
                 FROM sections s 
                 WHERE s.course_id = c.id), 
                0
            ) as total_sections,
            
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
            
            -- Current module/section
            COALESCE(
                (SELECT s.title 
                 FROM sections s 
                 LEFT JOIN text_progress tp ON s.id = tp.section_id AND tp.student_id = u.id
                 WHERE s.course_id = c.id 
                 ORDER BY 
                    CASE WHEN tp.completed = 1 THEN 1 ELSE 0 END ASC,
                    s.order_index ASC
                 LIMIT 1), 
                'Not started'
            ) as current_module
            
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON u.id = cp.student_id AND c.id = cp.course_id
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
            AVG(COALESCE(cp.completion_percentage, 0)) as average_progress,
            COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN u.id END) as completed_students,
            COUNT(DISTINCT CASE WHEN cp.completion_status = 'in_progress' THEN u.id END) as in_progress_students,
            COUNT(DISTINCT CASE WHEN cp.last_accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.id END) as active_students_week,
            COUNT(DISTINCT CASE WHEN cp.last_accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) as active_students_month,
            
            -- Total modules completed across all students
            SUM(COALESCE(cp.completed_sections, 0)) as total_completed_sections,
            
            -- Quiz statistics
            COUNT(DISTINCT qa.id) as total_quiz_attempts,
            AVG(COALESCE(qa.score, 0)) as average_quiz_score
            
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON u.id = cp.student_id AND c.id = cp.course_id
        LEFT JOIN quiz_attempts qa ON u.id = qa.student_id
        LEFT JOIN quizzes q ON qa.quiz_id = q.id
        LEFT JOIN sections s ON q.section_id = s.id AND s.course_id = c.id
        WHERE u.role = 'student' 
        AND c.teacher_id = ?
        AND u.status = 'active'
    ";
    
    $stmt = $pdo->prepare($overviewQuery);
    $stmt->execute([$teacher_id]);
    $overview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Process and enhance progress data
    foreach ($progress as &$item) {
        // Calculate actual progress percentage if not set
        if ($item['course_completion_percentage'] == 0 && $item['total_sections'] > 0) {
            $item['course_completion_percentage'] = ($item['completed_text_modules'] / $item['total_sections']) * 100;
        }
        
        // Format data types
        $item['progress_percentage'] = round(floatval($item['course_completion_percentage']), 1);
        $item['total_modules'] = intval($item['total_sections']);
        $item['completed_modules'] = intval($item['completed_text_modules']);
        $item['quiz_attempts'] = intval($item['quiz_attempts']);
        $item['average_quiz_score'] = round(floatval($item['average_quiz_score']), 1);
        
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
        
        // Calculate days since last activity
        if ($item['last_activity']) {
            $lastActivity = new DateTime($item['last_activity']);
            $now = new DateTime();
            $item['days_since_activity'] = $now->diff($lastActivity)->days;
        } else {
            $item['days_since_activity'] = null;
        }
    }
    
    // Enhanced overview statistics
    $formattedOverview = [
        'total_students' => intval($overview['total_students'] ?? 0),
        'total_courses' => intval($overview['total_courses'] ?? 0),
        'completed_modules' => intval($overview['total_completed_sections'] ?? 0),
        'in_progress_modules' => intval($overview['in_progress_students'] ?? 0),
        'active_students' => intval($overview['active_students_week'] ?? 0),
        'active_students_month' => intval($overview['active_students_month'] ?? 0),
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
        'overview' => $formattedOverview,
        'teacher_id' => $teacher_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in comprehensive progress API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load progress data: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
}
?>
