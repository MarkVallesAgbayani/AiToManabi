<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    // Fetch enrolled courses with progress count (using student_id instead of user_id)
    $sql = "SELECT 
                e.id,
                e.course_id,
                e.enrolled_at,
                e.completed_at,
                c.title,
                c.level,
                c.price,
                c.status,
                COALESCE(
                    (SELECT COUNT(*) 
                     FROM course_progress cp 
                     WHERE cp.course_id = e.course_id 
                     AND cp.student_id = e.student_id),
                    0
                ) as progress_count
            FROM enrollments e
            INNER JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ?
            ORDER BY e.enrolled_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format courses with status logic
    $formattedCourses = array_map(function($course) {
        // Determine status based on completed_at and progress_count
        if ($course['completed_at']) {
            $status = 'Completed';
            $progress = 100;
        } elseif ($course['progress_count'] > 0) {
            $status = 'In Progress';
            $progress = min(100, $course['progress_count'] * 10);
        } else {
            $status = 'Not Started';
            $progress = 0;
        }
        
        return [
            'id' => $course['id'],
            'title' => $course['title'],
            'category' => ucfirst($course['level']),
            'price' => number_format($course['price'] ?? 0, 2),
            'purchaseDate' => date('M j, Y', strtotime($course['enrolled_at'])),
            'progress' => (int)$progress,
            'status' => $status
        ];
    }, $courses);
    
    echo json_encode([
        'success' => true,
        'modules' => $formattedCourses
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching purchased modules: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
