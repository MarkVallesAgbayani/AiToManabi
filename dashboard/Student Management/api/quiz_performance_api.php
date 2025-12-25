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
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    // Check if files exist before requiring them
    if (!file_exists('../../../config/database.php')) {
        throw new Exception('Database config file not found at ../../../config/database.php');
    }
    if (!file_exists('../includes/quiz_performance_functions.php')) {
        throw new Exception('Quiz performance functions file not found at ../includes/quiz_performance_functions.php');
    }
    
    require_once '../../../config/database.php';
    require_once '../includes/quiz_performance_functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load required files: ' . $e->getMessage()]);
    exit();
}

try {
    // Test database connection
    $pdo->query("SELECT 1");
    $quizAnalyzer = new QuizPerformanceAnalyzer($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to initialize quiz analyzer: ' . $e->getMessage()]);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_overall_stats':
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $stats = $quizAnalyzer->getOverallStatistics($date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'get_top_performers':
            $limit = (int)($_GET['limit'] ?? 10);
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $performers = $quizAnalyzer->getTopPerformers($limit, $date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $performers]);
            break;
            
        case 'get_quiz_stats':
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $stats = $quizAnalyzer->getQuizStatistics($date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'get_recent_attempts':
            $limit = (int)($_GET['limit'] ?? 20);
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $attempts = $quizAnalyzer->getRecentAttempts($limit, $date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $attempts]);
            break;
            
        case 'get_performance_trend':
            $days = (int)($_GET['days'] ?? 30);
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            try {
                $trend = $quizAnalyzer->getPerformanceTrendData($days, $date_from, $date_to);
                error_log("Performance trend data: " . json_encode($trend));
                echo json_encode(['success' => true, 'data' => $trend]);
            } catch (Exception $e) {
                error_log("Error in get_performance_trend: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'get_quiz_difficulty':
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            try {
                $difficulty = $quizAnalyzer->getQuizDifficultyData($date_from, $date_to);
                error_log("Quiz difficulty data: " . json_encode($difficulty));
                echo json_encode(['success' => true, 'data' => $difficulty]);
            } catch (Exception $e) {
                error_log("Error in get_quiz_difficulty: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'get_student_performance':
            $student_id = (int)($_GET['student_id'] ?? 0);
            if (!$student_id) {
                throw new Exception('Student ID is required');
            }
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $performance = $quizAnalyzer->getStudentPerformance($student_id, $date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $performance]);
            break;
            
        case 'get_quiz_performance':
            $quiz_id = (int)($_GET['quiz_id'] ?? 0);
            if (!$quiz_id) {
                throw new Exception('Quiz ID is required');
            }
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            $performance = $quizAnalyzer->getQuizPerformance($quiz_id, $date_from, $date_to);
            echo json_encode(['success' => true, 'data' => $performance]);
            break;
            
        case 'export_data':
            $format = $_GET['format'] ?? 'csv';
            $filters = [
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'student_id' => $_GET['student_id'] ?? '',
                'quiz_id' => $_GET['quiz_id'] ?? ''
            ];
            $quizAnalyzer->exportQuizPerformance($format, $filters);
            break;
            
        case 'test_connection':
            // Test database connection
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM quiz_attempts");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => ['quiz_attempts_count' => $result['count']]]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_chart_data':
            $chart_type = $_GET['chart_type'] ?? 'performance_trend';
            $date_from = $_GET['date_from'] ?? null;
            $date_to = $_GET['date_to'] ?? null;
            
            switch ($chart_type) {
                case 'performance_trend':
                    $data = $quizAnalyzer->getPerformanceTrendData(30, $date_from, $date_to);
                    break;
                case 'quiz_difficulty':
                    $data = $quizAnalyzer->getQuizDifficultyData($date_from, $date_to);
                    break;
                default:
                    throw new Exception('Invalid chart type');
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
