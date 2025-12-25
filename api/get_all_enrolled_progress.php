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
    
    // Get enrolled courses with detailed progress
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title,
            c.description,
            c.image_path,
            cat.name as category_name,
            u.username as teacher_name,
            e.enrolled_at,
            COALESCE(cp.completion_percentage, 0) as completion_percentage,
            COALESCE(cp.completed_sections, 0) as completed_sections,
            (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as total_sections,
            cp.last_accessed_at,
            COALESCE(cp.completion_status, 'not_started') as completion_status,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as total_enrollments
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE e.student_id = ?
        ORDER BY cp.last_accessed_at DESC NULLS LAST, e.enrolled_at DESC
    ");
    $stmt->execute([$student_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process course data
    foreach ($courses as &$course) {
        // Calculate completion percentage if not properly set
        if ($course['total_sections'] > 0) {
            // Get actual completed sections count
            $stmt_sections = $pdo->prepare("
                SELECT COUNT(DISTINCT p.section_id) as actual_completed
                FROM progress p
                INNER JOIN sections s ON p.section_id = s.id
                WHERE s.course_id = ? AND p.student_id = ? AND p.completed = 1
            ");
            $stmt_sections->execute([$course['course_id'], $student_id]);
            $section_progress = $stmt_sections->fetch(PDO::FETCH_ASSOC);
            
            if ($section_progress) {
                $course['completed_sections'] = (int)$section_progress['actual_completed'];
                $course['completion_percentage'] = ($course['completed_sections'] / $course['total_sections']) * 100;
            }
        }
        
        // Ensure completion percentage is rounded
        $course['completion_percentage'] = round($course['completion_percentage'], 2);
        
        // Determine status based on percentage
        if ($course['completion_percentage'] >= 100) {
            $course['completion_status'] = 'completed';
        } elseif ($course['completion_percentage'] > 0) {
            $course['completion_status'] = 'in_progress';
        } else {
            $course['completion_status'] = 'not_started';
        }
        
        // Format image path
        if ($course['image_path'] && !str_starts_with($course['image_path'], 'http')) {
            if (str_starts_with($course['image_path'], 'uploads/')) {
                $course['image_path'] = '../' . $course['image_path'];
            } else {
                $course['image_path'] = '../uploads/' . $course['image_path'];
            }
        }
        
        // Ensure numeric values
        $course['course_id'] = (int)$course['course_id'];
        $course['completed_sections'] = (int)$course['completed_sections'];
        $course['total_sections'] = (int)$course['total_sections'];
        $course['total_enrollments'] = (int)$course['total_enrollments'];
    }
    unset($course);
    
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'total_courses' => count($courses),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error in get_all_enrolled_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Error in get_all_enrolled_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
