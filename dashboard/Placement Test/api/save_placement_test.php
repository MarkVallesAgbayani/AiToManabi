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

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        // Validate required fields
        $title = trim($input['title'] ?? '');
        $status = $input['status'] ?? 'draft'; // 'draft' or 'published'
        $questions = $input['questions'] ?? [];
        $pages = $input['pages'] ?? [];
        $modules = $input['modules'] ?? [];
        $test_id = $input['test_id'] ?? null; // For updates
        
        if (empty($title)) {
            throw new Exception('Test title is required');
        }

        // Validate status
        if (!in_array($status, ['draft', 'published'])) {
            throw new Exception('Invalid status. Must be "draft" or "published"');
        }

        $teacher_id = (int)$_SESSION['user_id'];
        
        // Verify teacher exists and is active
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
        $stmt->execute([$teacher_id]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$teacher) {
            throw new Exception('Invalid teacher account. Please log out and log back in.');
        }
        
        // Start transaction
        $pdo->beginTransaction();

        // Prepare the data for database insertion
        // Extract images from pages
        $images = [];
        foreach ($pages as $page) {
            if (!empty($page['image'])) {
                $images[] = [
                    'page_id' => $page['id'],
                    'image_path' => $page['image'],
                    'page_type' => $page['type']
                ];
            }
        }

        $testData = [
            'title' => $title,
            'description' => $input['description'] ?? '',
            'instructions' => $input['instructions'] ?? '',
            'is_published' => ($status === 'published') ? 1 : 0,
            'questions' => json_encode($questions),
            'design_settings' => json_encode($input['design_settings'] ?? []),
            'page_content' => json_encode($pages),
            'module_assignments' => json_encode($modules),
            'images' => json_encode($images),
            'created_by' => $teacher_id
        ];

        // Check if this is an update or insert
        if ($test_id) {
            // Update existing test
            $sql = "UPDATE placement_test SET 
                title = :title, 
                description = :description, 
                instructions = :instructions, 
                is_published = :is_published,
                questions = :questions, 
                design_settings = :design_settings, 
                page_content = :page_content, 
                module_assignments = :module_assignments, 
                images = :images,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :test_id AND created_by = :created_by";
            
            $testData['test_id'] = $test_id;
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($testData);
            
            if (!$result) {
                throw new Exception('Failed to update test in database');
            }
        } else {
            // Insert new test
            $sql = "INSERT INTO placement_test (
                title, description, instructions, is_published, 
                questions, design_settings, page_content, module_assignments, images, created_by
            ) VALUES (
                :title, :description, :instructions, :is_published,
                :questions, :design_settings, :page_content, :module_assignments, :images, :created_by
            )";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($testData);
            
            if (!$result) {
                throw new Exception('Failed to save test to database');
            }
            
            $test_id = $pdo->lastInsertId();
        }

        // Commit transaction
        $pdo->commit();

        // Log successful save
        $action = ($test_id && $status === 'update') ? 'updated' : 'saved';
        error_log("Placement test $action successfully - ID: $test_id, Title: $title, Status: $status, Teacher ID: $teacher_id");

        // Return success response
        $message = ($test_id && $status === 'update') 
            ? 'Test updated successfully!' 
            : ($status === 'published' ? 'Placement test published successfully!' : 'Placement test saved as draft!');
            
        sendJsonResponse(true, $message,
            [
                'test_id' => $test_id,
                'status' => $status,
                'title' => $title,
                'teacher_id' => $teacher_id,
                'questions_count' => count($questions),
                'pages_count' => count($pages),
                'modules_count' => array_sum(array_map('count', $modules))
            ]
        );

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error saving placement test: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage(), null, 400);
    }
} else {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}
?>
