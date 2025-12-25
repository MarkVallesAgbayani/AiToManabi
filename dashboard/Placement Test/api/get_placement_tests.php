<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../config/database.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $teacher_id = (int)$_SESSION['user_id'];
        
        // Fetch placement tests created by this teacher
        $stmt = $pdo->prepare("
            SELECT 
                id,
                title,
                description,
                is_published,
                archived,
                created_at,
                updated_at,
                questions,
                page_content,
                module_assignments,
                images
            FROM placement_test 
            WHERE created_by = ? 
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$teacher_id]);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging
        error_log("Fetching placement tests for teacher ID: $teacher_id");
        error_log("Found " . count($tests) . " tests");

        // Process the data to make it more readable
        $processedTests = [];
        foreach ($tests as $test) {
            $questions = json_decode($test['questions'], true) ?? [];
            $pages = json_decode($test['page_content'], true) ?? [];
            $modules = json_decode($test['module_assignments'], true) ?? [];
            $images = json_decode($test['images'], true) ?? [];
            
            // Debug logging for each test
            error_log("Processing test ID: {$test['id']}, Title: {$test['title']}, Published: {$test['is_published']}");
            error_log("Questions count: " . count($questions) . ", Pages count: " . count($pages) . ", Images count: " . count($images));
            
            // Determine status based on is_published and archived
            $status = 'draft';
            if ($test['archived']) {
                $status = 'archived';
            } elseif ($test['is_published']) {
                $status = 'published';
            }
            
            $processedTests[] = [
                'id' => $test['id'],
                'title' => $test['title'],
                'description' => $test['description'],
                'status' => $status,
                'created_at' => $test['created_at'],
                'updated_at' => $test['updated_at'],
                'questions_count' => count($questions),
                'pages_count' => count($pages),
                'modules_count' => array_sum(array_map('count', $modules)),
                'images_count' => count($images)
            ];
        }

        // Get statistics
        $totalTests = count($processedTests);
        $publishedTests = count(array_filter($processedTests, function($test) {
            return $test['status'] === 'published';
        }));
        $draftTests = $totalTests - $publishedTests;

        // Get total attempts (from placement_result table)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_attempts
            FROM placement_result pr
            JOIN placement_test pt ON pr.test_id = pt.id
            WHERE pt.created_by = ?
        ");
        $stmt->execute([$teacher_id]);
        $totalAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['total_attempts'];

        // Get completion rate
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_attempts
            FROM placement_result pr
            JOIN placement_test pt ON pr.test_id = pt.id
            WHERE pt.created_by = ?
        ");
        $stmt->execute([$teacher_id]);
        $completionData = $stmt->fetch(PDO::FETCH_ASSOC);
        $completionRate = $completionData['total_attempts'] > 0 
            ? round(($completionData['completed_attempts'] / $completionData['total_attempts']) * 100, 1)
            : 0;

        // Debug final response
        error_log("Final response - Total tests: $totalTests, Published: $publishedTests, Draft: $draftTests");
        
        sendJsonResponse(true, 'Placement tests loaded successfully', [
            'tests' => $processedTests,
            'statistics' => [
                'total_tests' => $totalTests,
                'published_tests' => $publishedTests,
                'draft_tests' => $draftTests,
                'total_attempts' => $totalAttempts,
                'completion_rate' => $completionRate
            ]
        ]);

    } catch (Exception $e) {
        error_log("Error fetching placement tests: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage(), null, 500);
    }
} else {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}
?>
