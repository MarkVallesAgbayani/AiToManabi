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
    
    // Verify enrollment
    $stmt = $pdo->prepare("
        SELECT e.enrolled_at
        FROM enrollments e
        WHERE e.course_id = ? AND e.student_id = ?
    ");
    $stmt->execute([$course_id, $student_id]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        exit();
    }
    
    // Get detailed course progress
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title,
            COALESCE(cp.completion_percentage, 0) as completion_percentage,
            COALESCE(cp.completed_sections, 0) as completed_sections,
            (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as total_sections,
            cp.last_accessed_at,
            COALESCE(cp.completion_status, 'not_started') as completion_status,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as total_enrollments
        FROM courses c
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$student_id, $course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        exit();
    }
    
    // Get actual completed sections count for accuracy
    $stmt_sections = $pdo->prepare("
        SELECT COUNT(DISTINCT p.section_id) as actual_completed
        FROM progress p
        INNER JOIN sections s ON p.section_id = s.id
        WHERE s.course_id = ? AND p.student_id = ? AND p.completed = 1
    ");
    $stmt_sections->execute([$course_id, $student_id]);
    $section_progress = $stmt_sections->fetch(PDO::FETCH_ASSOC);
    
    if ($section_progress) {
        $course['completed_sections'] = (int)$section_progress['actual_completed'];
        if ($course['total_sections'] > 0) {
            $course['completion_percentage'] = ($course['completed_sections'] / $course['total_sections']) * 100;
        }
    }
    
    // Determine status based on percentage
    if ($course['completion_percentage'] >= 100) {
        $course['completion_status'] = 'completed';
    } elseif ($course['completion_percentage'] > 0) {
        $course['completion_status'] = 'in_progress';
    } else {
        $course['completion_status'] = 'not_started';
    }
    
    // Get chapter progress for detailed tracking including quizzes
    $stmt = $pdo->prepare("
        SELECT 
            s.id as section_id,
            s.title as section_title,
            s.order_index,
            COUNT(DISTINCT ch.id) as total_chapters,
            COUNT(DISTINCT CASE WHEN vp.completed = 1 OR tp.completed = 1 THEN ch.id END) as completed_chapters,
            CASE WHEN q.id IS NOT NULL THEN 1 ELSE 0 END as has_quiz,
            CASE WHEN q.id IS NOT NULL AND EXISTS(
                SELECT 1 FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = ?
            ) THEN 1 ELSE 0 END as quiz_completed
        FROM sections s
        LEFT JOIN chapters ch ON s.id = ch.section_id
        LEFT JOIN video_progress vp ON ch.id = vp.chapter_id AND vp.student_id = ?
        LEFT JOIN text_progress tp ON ch.id = tp.chapter_id AND tp.student_id = ?
        LEFT JOIN quizzes q ON s.id = q.section_id
        WHERE s.course_id = ?
        GROUP BY s.id, s.title, s.order_index, q.id
        ORDER BY s.order_index
    ");
    $stmt->execute([$student_id, $student_id, $student_id, $course_id]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process sections data including quiz completion
    $total_items = 0;
    $completed_items = 0;
    
    foreach ($sections as &$section) {
        $section['section_id'] = (int)$section['section_id'];
        $section['total_chapters'] = (int)$section['total_chapters'];
        $section['completed_chapters'] = (int)$section['completed_chapters'];
        $section['has_quiz'] = (bool)$section['has_quiz'];
        $section['quiz_completed'] = (bool)$section['quiz_completed'];
        
        // Calculate section progress including quiz
        $section_total_items = $section['total_chapters'] + ($section['has_quiz'] ? 1 : 0);
        $section_completed_items = $section['completed_chapters'] + ($section['has_quiz'] && $section['quiz_completed'] ? 1 : 0);
        
        $section['total_items'] = $section_total_items;
        $section['completed_items'] = $section_completed_items;
        $section['completion_percentage'] = $section_total_items > 0 ? 
            ($section_completed_items / $section_total_items) * 100 : 0;
        
        // Add to overall totals
        $total_items += $section_total_items;
        $completed_items += $section_completed_items;
    }
    unset($section);
    
    // Update overall course progress
    $course['completion_percentage'] = $total_items > 0 ? ($completed_items / $total_items) * 100 : 0;
    $course['total_items'] = $total_items;
    $course['completed_items'] = $completed_items;
    
    echo json_encode([
        'success' => true,
        'course_id' => (int)$course['course_id'],
        'title' => $course['title'],
        'progress' => round($course['completion_percentage'], 2),
        'completion_percentage' => round($course['completion_percentage'], 2),
        'completed_items' => (int)$course['completed_items'],
        'total_items' => (int)$course['total_items'],
        'completed_sections' => (int)$course['completed_sections'],
        'total_sections' => (int)$course['total_sections'],
        'last_accessed_at' => $course['last_accessed_at'],
        'completion_status' => $course['completion_status'],
        'total_enrollments' => (int)$course['total_enrollments'],
        'sections' => $sections,
        'enrolled_at' => $enrollment['enrolled_at'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error in get_course_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Error in get_course_progress.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
