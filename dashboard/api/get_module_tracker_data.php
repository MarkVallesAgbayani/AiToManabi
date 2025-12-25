<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $studentId = $_SESSION['user_id'];
    
    // Get completed modules with detailed information
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            c.description,
            c.image_path,
            cat.name as category_name,
            u.username as teacher_name,
            cp.completion_percentage,
            cp.last_accessed_at as completion_date,
            e.enrolled_at,
            c.price,
            cp.completed_sections
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE e.student_id = ? 
        AND cp.completion_status = 'completed'
        AND cp.last_accessed_at IS NOT NULL
        ORDER BY cp.last_accessed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$studentId]);
    $completedModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get new modules uploaded by teachers
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            c.description,
            c.image_path,
            c.created_at as upload_date,
            u.username as teacher_name,
            cat.name as category_name,
            c.price,
            CASE WHEN e.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
        FROM courses c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
        WHERE c.status = 'published'
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$studentId]);
    $newModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get in-progress modules
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            c.description,
            c.image_path,
            cat.name as category_name,
            u.username as teacher_name,
            cp.completion_percentage,
            cp.last_accessed_at,
            e.enrolled_at,
            c.price,
            cp.completed_sections
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
        WHERE e.student_id = ? 
        AND (cp.completion_status = 'in_progress' OR cp.completion_percentage > 0 AND cp.completion_percentage < 100)
        ORDER BY cp.last_accessed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$studentId]);
    $inProgressModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare response data
    $response = [
        'completed' => count($completedModules),
        'inProgress' => count($inProgressModules),
        'newModules' => count($newModules),
        'completedModules' => $completedModules,
        'inProgressModules' => $inProgressModules,
        'newModules' => $newModules
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database Error in module tracker: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error fetching module tracker data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
?>
