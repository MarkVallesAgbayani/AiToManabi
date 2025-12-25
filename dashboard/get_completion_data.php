<?php
session_start();
require_once '../config/database.php';
require_once '../includes/rbac_helper.php';

// Check if user is logged in and has admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check admin access
$has_admin_access = false;
if (function_exists('hasPermission')) {
    $has_admin_access = hasPermission($pdo, $_SESSION['user_id'], 'nav_dashboard');
}
if (!$has_admin_access && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $has_admin_access = true;
}
if (!$has_admin_access) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        $has_admin_access = true;
    }
}

if (!$has_admin_access) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// Get time range from request
$timeRange = isset($_GET['range']) ? $_GET['range'] : 'all';

// Validate time range
$allowedRanges = ['all', 'daily', 'weekly', 'monthly'];
if (!in_array($timeRange, $allowedRanges)) {
    $timeRange = 'all';
}

try {
    // Build WHERE clause based on time range
    $whereClause = '';
    switch($timeRange) {
        case 'daily':
            $whereClause = "WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            break;
        case 'weekly':
            $whereClause = "WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'monthly':
            $whereClause = "WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        default:
            $whereClause = '';
    }
    
    $sql = "SELECT 
                c.title as course_name,
                c.id as course_id,
                COUNT(e.id) as total_enrolled,
                COUNT(CASE WHEN cp.completion_status = 'completed' THEN 1 END) as completed_count,
                ROUND((COUNT(CASE WHEN cp.completion_status = 'completed' THEN 1 END) / COUNT(e.id)) * 100, 1) as completion_rate,
                AVG(CASE WHEN cp.completion_status = 'completed' THEN cp.completion_percentage END) as avg_score
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.student_id = e.student_id
            $whereClause
            GROUP BY c.id, c.title
            HAVING total_enrolled > 0
            ORDER BY completion_rate DESC";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timeRange' => $timeRange
    ]);
    
} catch (PDOException $e) {
    error_log("Course completion data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
