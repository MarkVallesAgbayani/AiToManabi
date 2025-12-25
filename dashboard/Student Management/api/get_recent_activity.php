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
    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;
    
    // Get recent student activities
    $query = "
        SELECT 
            u.id as student_id,
            u.username as student_name,
            'login' as type,
            'Logged into the platform' as description,
            u.last_login as created_at
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        WHERE u.role = 'student' 
        AND c.teacher_id = ? 
        AND u.last_login IS NOT NULL
        AND u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            qa.student_id as student_id,
            u.username as student_name,
            CASE WHEN qa.score >= 70 THEN 'quiz_completed' ELSE 'quiz_failed' END as type,
            CONCAT('Completed quiz: ', q.title, ' (Score: ', qa.score, '%)') as description,
            qa.completed_at as created_at
        FROM quiz_attempts qa
        JOIN users u ON qa.student_id = u.id
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN sections s ON q.section_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE c.teacher_id = ?
        AND qa.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            cp.student_id as student_id,
            u.username as student_name,
            'module_completed' as type,
            CONCAT('Completed course: ', c.title) as description,
            cp.updated_at as created_at
        FROM course_progress cp
        JOIN users u ON cp.student_id = u.id
        JOIN courses c ON cp.course_id = c.id
        WHERE c.teacher_id = ?
        AND cp.completion_status = 'completed'
        AND cp.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            e.student_id,
            u.username as student_name,
            'enrollment' as type,
            CONCAT('Enrolled in course: ', c.title) as description,
            e.enrolled_at as created_at
        FROM enrollments e
        JOIN users u ON e.student_id = u.id
        JOIN courses c ON e.course_id = c.id
        WHERE c.teacher_id = ?
        AND e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        ORDER BY created_at DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$teacher_id, $teacher_id, $teacher_id, $teacher_id, $teacher_id, $limit]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format activities
    foreach ($activities as &$activity) {
        if ($activity['created_at']) {
            $activity['formatted_date'] = date('Y-m-d H:i:s', strtotime($activity['created_at']));
            $activity['time_ago'] = time_ago($activity['created_at']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_recent_activity.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch recent activity'
    ]);
}

function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
