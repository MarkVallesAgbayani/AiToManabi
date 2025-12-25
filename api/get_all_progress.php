<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

try {
    $student_id = $_SESSION['user_id'];
    
    // Get overall statistics
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(DISTINCT e.course_id) FROM enrollments e WHERE e.student_id = ?) as enrolled_courses,
            (SELECT COUNT(DISTINCT cp.course_id) 
             FROM course_progress cp 
             WHERE cp.student_id = ? AND cp.completion_status = 'completed') as completed_courses,
            (SELECT COALESCE(AVG(cp.completion_percentage), 0) 
             FROM course_progress cp 
             WHERE cp.student_id = ?) as overall_progress
    ");
    $stmt->execute([$student_id, $student_id, $student_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get detailed course progress
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title,
            c.image_path,
            COALESCE(cp.completion_percentage, 0) as completion_percentage,
            COALESCE(cp.completed_sections, 0) as completed_sections,
            (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as total_sections,
            cp.last_accessed_at,
            COALESCE(cp.completion_status, 'not_started') as completion_status,
            e.enrolled_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE e.student_id = ?
        ORDER BY cp.last_accessed_at DESC, e.enrolled_at DESC
    ");
    $stmt->execute([$student_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process course data
    foreach ($courses as &$course) {
        // Calculate completion percentage if not set
        if ($course['total_sections'] > 0 && $course['completion_percentage'] == 0) {
            $course['completion_percentage'] = ($course['completed_sections'] / $course['total_sections']) * 100;
        }
        
        // Determine status based on percentage
        if ($course['completion_percentage'] == 100) {
            $course['completion_status'] = 'completed';
        } elseif ($course['completion_percentage'] > 0) {
            $course['completion_status'] = 'in_progress';
        } else {
            $course['completion_status'] = 'not_started';
        }
        
        // Format image path
        if ($course['image_path'] && !str_starts_with($course['image_path'], 'http')) {
            $course['image_path'] = '../' . $course['image_path'];
        }
    }
    unset($course);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'enrolled_courses' => (int)$stats['enrolled_courses'],
            'completed_courses' => (int)$stats['completed_courses'],
            'overall_progress' => (float)$stats['overall_progress']
        ],
        'courses' => $courses,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error in get_all_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Error in get_all_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
