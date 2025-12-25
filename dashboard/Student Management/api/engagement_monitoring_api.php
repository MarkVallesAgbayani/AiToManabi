<?php
// Enable error reporting for debugging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a teacher or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

try {
    // Check if files exist before requiring them
    if (!file_exists('../../../config/database.php')) {
        throw new Exception('Database config file not found at ../../../config/database.php');
    }
    if (!file_exists('../includes/engagement_monitoring_functions.php')) {
        throw new Exception('Engagement monitoring functions file not found at ../includes/engagement_monitoring_functions.php');
    }
    
    require_once '../../../config/database.php';
    require_once '../includes/engagement_monitoring_functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load required files: ' . $e->getMessage()]);
    exit();
}

try {
    // Test database connection
    $pdo->query("SELECT 1");
    $engagementMonitor = new EngagementMonitor($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to initialize engagement monitor: ' . $e->getMessage()]);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'overall_stats':
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $stats = $engagementMonitor->getOverallStatistics($date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'most_engaged':
            $limit = (int)($_GET['limit'] ?? 10);
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $students = $engagementMonitor->getMostEngagedStudents($limit, $date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $students]);
            break;
            
        case 'course_stats':
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            
            // Clean the parameters to avoid "undefined" strings
            if ($date_from === 'undefined' || $date_from === '') {
                $date_from = null;
            }
            if ($date_to === 'undefined' || $date_to === '') {
                $date_to = null;
            }
            
            $courses = $engagementMonitor->getCourseEngagementStats($date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $courses]);
            break;
            
        case 'recent_enrollments':
            $limit = (int)($_GET['limit'] ?? 20);
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $enrollments = $engagementMonitor->getRecentEnrollments($limit, $date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $enrollments]);
            break;
            
        case 'enrollment_trend':
            $days = (int)($_GET['days'] ?? 30);
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $trend = $engagementMonitor->getEnrollmentTrend($days, $date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $trend]);
            break;
            
        case 'time_spent':
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            
            // Clean the parameters to avoid "undefined" strings
            if ($date_from === 'undefined' || $date_from === '') {
                $date_from = null;
            }
            if ($date_to === 'undefined' || $date_to === '') {
                $date_to = null;
            }
            
            $timeData = $engagementMonitor->getTimeSpentData($date_from, $date_to);
            $response = ['success' => true, 'data' => $timeData];
            echo json_encode($response);
            break;
            
        case 'test':
            echo json_encode(['success' => true, 'message' => 'API is working', 'session' => [
                'user_id' => $_SESSION['user_id'] ?? 'not set',
                'username' => $_SESSION['username'] ?? 'not set',
                'role' => $_SESSION['role'] ?? 'not set'
            ]]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
