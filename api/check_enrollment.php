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
    $course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
    $student_id = $_SESSION['user_id'];
    
    if (!$course_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        exit();
    }
    
    // Check if course exists and user has access
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.price,
               e.student_id as is_enrolled
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = ?
        WHERE c.id = ? AND c.status = 'published'
    ");
    $stmt->execute([$student_id, $course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        exit();
    }
    
    $response = [
        'success' => true,
        'course_id' => $course_id,
        'is_enrolled' => !is_null($course['is_enrolled']),
        'is_free' => floatval($course['price']) == 0,
        'price' => $course['price'],
        'title' => $course['title']
    ];
    
    // If enrolled, get progress data
    if ($response['is_enrolled']) {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(cp.completion_percentage, 0) as completion_percentage,
                COALESCE(cp.completed_sections, 0) as completed_sections,
                (SELECT COUNT(*) FROM sections s WHERE s.course_id = ?) as total_sections,
                cp.last_accessed_at,
                COALESCE(cp.completion_status, 'not_started') as completion_status
            FROM course_progress cp
            WHERE cp.course_id = ? AND cp.student_id = ?
        ");
        $stmt->execute([$course_id, $course_id, $student_id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            // Get actual completed sections count
            $stmt_sections = $pdo->prepare("
                SELECT COUNT(DISTINCT p.section_id) as actual_completed
                FROM progress p
                INNER JOIN sections s ON p.section_id = s.id
                WHERE s.course_id = ? AND p.student_id = ? AND p.completed = 1
            ");
            $stmt_sections->execute([$course_id, $student_id]);
            $section_progress = $stmt_sections->fetch(PDO::FETCH_ASSOC);
            
            if ($section_progress) {
                $progress['completed_sections'] = (int)$section_progress['actual_completed'];
                if ($progress['total_sections'] > 0) {
                    $progress['completion_percentage'] = ($progress['completed_sections'] / $progress['total_sections']) * 100;
                }
            }
            
            $response['progress'] = [
                'completion_percentage' => round($progress['completion_percentage'], 2),
                'completed_sections' => (int)$progress['completed_sections'],
                'total_sections' => (int)$progress['total_sections'],
                'last_accessed_at' => $progress['last_accessed_at'],
                'completion_status' => $progress['completion_status']
            ];
        }
    }
    
    // Get course statistics (for all users)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT e.student_id) as total_students,
            (SELECT COUNT(*) FROM course_progress cp WHERE cp.course_id = ? AND cp.completion_status = 'completed') as completed_students,
            (SELECT AVG(r.rating) FROM reviews r WHERE r.course_id = ?) as average_rating
        FROM enrollments e
        WHERE e.course_id = ?
    ");
    $stmt->execute([$course_id, $course_id, $course_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats && $stats['total_students'] > 0) {
        $response['stats'] = [
            'total_students' => (int)$stats['total_students'],
            'completion_rate' => $stats['completed_students'] > 0 ? 
                ($stats['completed_students'] / $stats['total_students']) * 100 : 0,
            'average_rating' => $stats['average_rating'] ? round($stats['average_rating'], 1) : 0
        ];
    } else {
        $response['stats'] = [
            'total_students' => 0,
            'completion_rate' => 0,
            'average_rating' => 0
        ];
    }
    
    $response['total_enrollments'] = $response['stats']['total_students'];
    $response['timestamp'] = date('Y-m-d H:i:s');
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database Error in check_enrollment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Error in check_enrollment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
