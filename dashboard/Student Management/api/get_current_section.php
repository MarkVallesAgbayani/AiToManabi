<?php
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/database.php';

// Get parameters
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$student_id || !$course_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // First, get total sections count for this course
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_sections
        FROM sections 
        WHERE course_id = ?
    ");
    $stmt->execute([$course_id]);
    $totalSections = $stmt->fetch(PDO::FETCH_ASSOC)['total_sections'];
    
    // Get completed sections count for this student in this course
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT tp.section_id) as completed_sections
        FROM text_progress tp
        JOIN sections s ON tp.section_id = s.id
        WHERE tp.student_id = ? AND s.course_id = ? AND tp.completed = 1
    ");
    $stmt->execute([$student_id, $course_id]);
    $completedSections = $stmt->fetch(PDO::FETCH_ASSOC)['completed_sections'];
    
    // Determine current section status
    if ($completedSections >= $totalSections) {
        // All sections completed
        $currentSection = "All Sections Completed ({$completedSections}/{$totalSections})";
    } else {
        // Find the next incomplete section
        $stmt = $pdo->prepare("
            SELECT s.title as current_section, s.section_order
            FROM sections s
            LEFT JOIN text_progress tp ON s.id = tp.section_id AND tp.student_id = ? AND tp.completed = 1
            WHERE s.course_id = ? AND tp.section_id IS NULL
            ORDER BY s.section_order ASC
            LIMIT 1
        ");
        $stmt->execute([$student_id, $course_id]);
        $nextSection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nextSection) {
            $currentSection = "{$nextSection['current_section']} ({$completedSections}/{$totalSections})";
        } else {
            // Fallback: get the first section
            $stmt = $pdo->prepare("
                SELECT title as current_section, section_order
                FROM sections 
                WHERE course_id = ? 
                ORDER BY section_order ASC 
                LIMIT 1
            ");
            $stmt->execute([$course_id]);
            $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentSection = $fallback ? "{$fallback['current_section']} ({$completedSections}/{$totalSections})" : "Not Started ({$completedSections}/{$totalSections})";
        }
    }
    
    echo json_encode([
        'success' => true,
        'current_section' => $currentSection,
        'completed_sections' => $completedSections,
        'total_sections' => $totalSections
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_current_section.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
