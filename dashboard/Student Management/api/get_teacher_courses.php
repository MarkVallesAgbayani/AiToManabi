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
    
    // Check what columns exist in courses table
    $columns = [];
    $columnCheck = $pdo->query("SHOW COLUMNS FROM courses");
    while ($row = $columnCheck->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    // Build query based on available columns
    $statusColumn = in_array('status', $columns) ? 'status' : (in_array('is_active', $columns) ? 'is_active' : null);
    
    // Get courses taught by this teacher
    $query = "
        SELECT id, title
        FROM courses 
        WHERE teacher_id = ?";
    
    if ($statusColumn === 'status') {
        $query .= " AND status = 'active'";
    } elseif ($statusColumn === 'is_active') {
        $query .= " AND is_active = 1";
    }
    
    $query .= " ORDER BY title ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$teacher_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'courses' => $courses
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_teacher_courses.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch courses'
    ]);
}
