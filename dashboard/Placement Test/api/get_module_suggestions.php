<?php
/**
 * Module Suggestions API - Placement Test Module
 * Provides module suggestions based on difficulty level
 */

session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $level = $_GET['level'] ?? '';
    
    if (empty($level)) {
        throw new Exception('Level parameter is required');
    }
    
    // Map level names to database values
    $level_mapping = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate', 
        'advanced' => 'Advanced'
    ];
    
    $db_level = $level_mapping[$level] ?? $level;
    
    // Get module suggestions based on level
    $sql = "
        SELECT 
            c.id,
            c.title,
            c.description,
            c.difficulty_level,
            c.created_by,
            u.first_name,
            u.last_name,
            COUNT(ce.student_id) as enrollment_count
        FROM courses c
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id
        WHERE c.status = 'published' 
        AND c.difficulty_level = ?
        GROUP BY c.id, c.title, c.description, c.difficulty_level, c.created_by, u.first_name, u.last_name
        ORDER BY enrollment_count DESC, c.created_at DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db_level]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formatted_modules = array_map(function($module) {
        return [
            'id' => $module['id'],
            'title' => $module['title'],
            'description' => $module['description'],
            'difficulty_level' => $module['difficulty_level'],
            'teacher_name' => trim($module['first_name'] . ' ' . $module['last_name']),
            'enrollment_count' => (int)$module['enrollment_count'],
            'created_by' => $module['created_by']
        ];
    }, $modules);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_modules,
        'level' => $level,
        'count' => count($formatted_modules)
    ]);
    
} catch (Exception $e) {
    error_log("Module suggestions API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>