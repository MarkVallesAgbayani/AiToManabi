<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/database.php';
require_once '../includes/completion_reports_functions.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Initialize the completion reports analyzer
    $analyzer = new CompletionReportsAnalyzer($pdo, $_SESSION['user_id']);
    
    switch ($action) {
        case 'get_completion_stats':
            getCompletionStats($analyzer);
            break;
        case 'get_module_breakdown':
            getModuleBreakdown($analyzer);
            break;
        case 'get_timeliness_data':
            getTimelinessData($analyzer);
            break;
        case 'get_courses':
            getCourses($analyzer);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getCompletionStats($analyzer) {
    $date_from = $_GET['date_from'] ?? date('Y-m-01', strtotime('-3 months'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $course_id = $_GET['course_id'] ?? '';
    
    // Get overall completion stats
    $overall_stats = $analyzer->getOverallCompletionStats($date_from, $date_to, $course_id);
    
    // Get progress stats
    $progress_stats = $analyzer->getAverageProgressPerStudent($date_from, $date_to, $course_id);
    
    echo json_encode([
        'completion_rate' => $overall_stats['completion_rate'],
        'avg_progress' => $progress_stats['avg_progress'],
        'total_enrollments' => $overall_stats['total_enrollments'],
        'completed_enrollments' => $overall_stats['completed_enrollments']
    ]);
}

function getModuleBreakdown($analyzer) {
    $date_from = $_GET['date_from'] ?? date('Y-m-01', strtotime('-3 months'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $course_id = $_GET['course_id'] ?? '';
    
    $modules = $analyzer->getModuleCompletionBreakdown($date_from, $date_to, $course_id);
    echo json_encode($modules);
}

function getTimelinessData($analyzer) {
    $date_from = $_GET['date_from'] ?? date('Y-m-01', strtotime('-3 months'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $course_id = $_GET['course_id'] ?? '';
    
    $timeliness_data = $analyzer->getTimelinessData($date_from, $date_to, $course_id);
    echo json_encode($timeliness_data);
}

function getCourses($analyzer) {
    $courses = $analyzer->getCourses();
    echo json_encode($courses);
}
?>
