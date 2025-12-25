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
    
    // Get student basic info
    $studentQuery = "
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.created_at as joined_date
        FROM users u
        WHERE u.id = ? AND u.role = 'student'
    ";
    $stmt = $pdo->prepare($studentQuery);
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student not found');
    }
    
    // Get detailed progress for each course with real data
    $progressQuery = "
        SELECT 
            c.id as course_id,
            c.title as course_title,
            c.level,
            c.description,
            e.enrolled_at as enrollment_date,
            e.completed_at as completion_date,
            
            -- Course progress
            COALESCE(cp.completion_percentage, 0) as completion_percentage,
            COALESCE(cp.completed_sections, 0) as completed_sections,
            cp.completion_status,
            cp.last_accessed_at as last_activity,
            
            -- Total sections
            COALESCE(
                (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id), 
                0
            ) as total_modules,
            
            -- Text progress
            COALESCE(
                (SELECT COUNT(*) 
                 FROM text_progress tp 
                 WHERE tp.student_id = ? AND tp.course_id = c.id AND tp.completed = 1), 
                0
            ) as completed_text_modules,
            
            -- Quiz statistics
            COALESCE(
                (SELECT COUNT(*) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 JOIN sections s ON q.section_id = s.id 
                 WHERE qa.student_id = ? AND s.course_id = c.id), 
                0
            ) as quiz_attempts,
            
            COALESCE(
                (SELECT AVG(qa.score) 
                 FROM quiz_attempts qa 
                 JOIN quizzes q ON qa.quiz_id = q.id 
                 JOIN sections s ON q.section_id = s.id 
                 WHERE qa.student_id = ? AND s.course_id = c.id), 
                0
            ) as quiz_average,
            
            -- Recent activity
            COALESCE(
                (SELECT tp.completed_at 
                 FROM text_progress tp 
                 WHERE tp.student_id = ? AND tp.course_id = c.id 
                 ORDER BY tp.completed_at DESC 
                 LIMIT 1), 
                e.enrolled_at
            ) as recent_activity
            
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON e.student_id = cp.student_id AND c.id = cp.course_id
        WHERE e.student_id = ? AND c.teacher_id = ?
        ORDER BY e.enrolled_at DESC
    ";
    
    $stmt = $pdo->prepare($progressQuery);
    $stmt->execute([$student_id, $student_id, $student_id, $student_id, $student_id, $teacher_id]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the progress data
    foreach ($progress as &$course) {
        // Calculate actual progress if not set
        if ($course['completion_percentage'] == 0 && $course['total_modules'] > 0) {
            $course['completion_percentage'] = ($course['completed_text_modules'] / $course['total_modules']) * 100;
        }
        
        $course['total_modules'] = intval($course['total_modules']);
        $course['completed_modules'] = intval($course['completed_text_modules']);
        $course['quiz_attempts'] = intval($course['quiz_attempts']);
        $course['completion_percentage'] = round(floatval($course['completion_percentage']), 1);
        
        if ($course['quiz_average'] === null || $course['quiz_average'] == 0) {
            $course['quiz_average'] = 'N/A';
        } else {
            $course['quiz_average'] = round(floatval($course['quiz_average']), 1);
        }
        
        // Calculate module completion rate
        if ($course['total_modules'] > 0) {
            $course['module_completion_rate'] = round(($course['completed_modules'] / $course['total_modules']) * 100, 1);
        } else {
            $course['module_completion_rate'] = 0;
        }
        
        // Determine status
        if ($course['completion_percentage'] >= 100) {
            $course['status'] = 'completed';
        } elseif ($course['completion_percentage'] > 0) {
            $course['status'] = 'in_progress';
        } else {
            $course['status'] = 'not_started';
        }
    }
    
    // Add student display name
    $student['display_name'] = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    if (empty($student['display_name'])) {
        $student['display_name'] = $student['username'];
    }
    
    echo json_encode([
        'success' => true,
        'student' => $student,
        'progress' => $progress
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_student_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
