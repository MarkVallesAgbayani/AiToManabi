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
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    
    if (!$student_id) {
        throw new Exception('Student ID is required');
    }
    
    // Verify that this student is enrolled in teacher's courses
    $verifyQuery = "
        SELECT COUNT(*) 
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND c.teacher_id = ?
    ";
    $stmt = $pdo->prepare($verifyQuery);
    $stmt->execute([$student_id, $teacher_id]);
    
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Student not found or not enrolled in your courses');
    }
    
    // Get student basic information with completion rate
            $studentQuery = "
            SELECT u.id, u.username, u.email, 
                   COALESCE(u.first_name, '') as first_name,
                   COALESCE(u.last_name, '') as last_name,
                   COALESCE(u.last_login_at, u.login_time) as last_login, 
                   COALESCE(u.status, 'active') as status, 
                   u.created_at,
                   CASE 
                       WHEN COALESCE(u.last_login_at, u.login_time) IS NULL THEN 'Long Inactive'
                       WHEN COALESCE(u.last_login_at, u.login_time) < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Long Inactive'
                       WHEN COALESCE(u.last_login_at, u.login_time) < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Inactive'
                       ELSE 'Active'
                   END as activity_status,
               CONCAT(
                   COALESCE(SUM(CASE WHEN cp.completion_status = 'completed' THEN 1 ELSE 0 END), 0),
                   ' of ',
                   COALESCE(COUNT(DISTINCT c.id), 0),
                   ' modules completed'
               ) as completion_rate, 
               NULL as student_id, 
               NULL as bio
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.student_id
        LEFT JOIN courses c ON e.course_id = c.id AND c.teacher_id = ?
        LEFT JOIN course_progress cp ON e.student_id = cp.student_id AND e.course_id = cp.course_id
        WHERE u.id = ? AND u.role = 'student'
        GROUP BY u.id, u.username, u.email, u.first_name, u.last_name, u.last_login_at, u.login_time, u.status, u.created_at
    ";
    $stmt = $pdo->prepare($studentQuery);
    $stmt->execute([$teacher_id, $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student not found');
    }
    
    // Get student enrollments in teacher's courses
    $enrollmentQuery = "
        SELECT c.id as course_id, c.title as course_title, c.description,
               e.enrolled_at as enrollment_date,
               COALESCE(cp.completion_percentage, 0) as completion_percentage,
               COALESCE(cp.last_accessed_at, e.enrolled_at) as last_activity,
               CASE WHEN cp.completion_status = 'completed' THEN cp.updated_at ELSE NULL END as completion_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON e.student_id = cp.student_id AND e.course_id = cp.course_id
        WHERE e.student_id = ? AND c.teacher_id = ?
        ORDER BY e.enrolled_at DESC
    ";
    $stmt = $pdo->prepare($enrollmentQuery);
    $stmt->execute([$student_id, $teacher_id]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overall progress statistics
    $progressQuery = "
        SELECT 
            COUNT(DISTINCT c.id) as total_courses,
            COUNT(DISTINCT CASE WHEN cp.completion_status = 'completed' THEN c.id END) as completed_courses,
            COUNT(DISTINCT CASE WHEN cp.completion_status = 'in_progress' THEN c.id END) as in_progress_courses,
            COALESCE(AVG(cp.completion_percentage), 0) as overall_progress
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON e.student_id = cp.student_id AND e.course_id = cp.course_id
        WHERE e.student_id = ? AND c.teacher_id = ?
    ";
    $stmt = $pdo->prepare($progressQuery);
    $stmt->execute([$student_id, $teacher_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get quiz performance summary
    $quizQuery = "
        SELECT 
            COUNT(*) as total_attempts,
            ROUND(AVG(qa.score), 1) as average_score,
            SUM(CASE WHEN qa.score >= 70 THEN 1 ELSE 0 END) as passed_attempts,
            MAX(qa.score) as highest_score,
            MIN(qa.score) as lowest_score
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN sections s ON q.section_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE qa.student_id = ? AND c.teacher_id = ?
    ";
    $stmt = $pdo->prepare($quizQuery);
    $stmt->execute([$student_id, $teacher_id]);
    $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity
    $activityQuery = "
        SELECT 
            'quiz' as activity_type,
            q.title as title,
            qa.score,
            CASE WHEN qa.score >= 70 THEN 1 ELSE 0 END as passed,
            qa.completed_at as activity_date,
            c.title as course_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN sections s ON q.section_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE qa.student_id = ? AND c.teacher_id = ?
        
        UNION ALL
        
        SELECT 
            'module' as activity_type,
            c.title as title,
            cp.completion_percentage as score,
            CASE WHEN cp.completion_percentage = 100 THEN 1 ELSE 0 END as passed,
            cp.updated_at as activity_date,
            c.title as course_title
        FROM course_progress cp
        JOIN courses c ON cp.course_id = c.id
        WHERE cp.student_id = ? AND c.teacher_id = ? AND cp.completion_status = 'completed'
        
        ORDER BY activity_date DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($activityQuery);
    $stmt->execute([$student_id, $teacher_id, $student_id, $teacher_id]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'enrollments' => $enrollments,
        'progress' => $progress,
        'quiz_stats' => $quizStats,
        'recent_activity' => $recentActivity
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_student_detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
