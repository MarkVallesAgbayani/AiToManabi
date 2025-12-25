<?php
// Ensure session is started with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// Function to handle JSON responses
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

// Error handler to catch all errors and convert to JSON response
function handleError($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $errorType = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'Unknown Error';
    $errorMessage = "$errorType: $errstr in $errfile on line $errline";
    error_log("=== ERROR HANDLER TRIGGERED ===");
    error_log($errorMessage);
    
    if ($errno == E_ERROR || $errno == E_USER_ERROR || $errno == E_RECOVERABLE_ERROR) {
        sendJsonResponse(false, "A server error occurred. Please try again.", null, 500);
        return true;
    }
    return false;
}

// Exception handler
function handleException($e) {
    $errorMessage = sprintf(
        "Uncaught Exception: %s\nFile: %s\nLine: %d\nTrace:\n%s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log("=== EXCEPTION HANDLER TRIGGERED ===");
    error_log($errorMessage);
    
    // Send a user-friendly message
    sendJsonResponse(false, "An unexpected error occurred. Please try again.", null, 500);
}

// Set error and exception handlers
set_error_handler("handleError");
set_exception_handler("handleException");

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    sendJsonResponse(false, 'Unauthorized access', null, 401);
}

// Check if database file exists
$db_path = '../../../config/database.php';
if (!file_exists($db_path)) {
    error_log("Database file not found at: " . realpath($db_path));
    error_log("Current working directory: " . getcwd());
    sendJsonResponse(false, "Database configuration not found", null, 500);
}

require_once $db_path;

// Validate database connection
try {
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    sendJsonResponse(false, "Database connection failed", null, 500);
}

// Get test ID from URL parameter
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

if (!$test_id) {
    sendJsonResponse(false, 'Test ID is required', null, 400);
}

try {
    // Get test data
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            description,
            instructions,
            is_published,
            COALESCE(archived, 0) as archived,
            questions,
            design_settings,
            page_content,
            module_assignments,
            images,
            created_at,
            updated_at
        FROM placement_test 
        WHERE id = ?
    ");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        sendJsonResponse(false, 'Test not found or you do not have permission to edit it', null, 404);
    }
    
    // Decode JSON fields safely
    $test['questions'] = json_decode($test['questions'], true) ?? [];
    $test['design_settings'] = json_decode($test['design_settings'], true) ?? [];
    $test['page_content'] = json_decode($test['page_content'], true) ?? [];
    
    // Debug module assignments
    error_log("Raw module_assignments from DB: " . $test['module_assignments']);
    $test['module_assignments'] = json_decode($test['module_assignments'], true) ?? [];
    error_log("Decoded module_assignments: " . json_encode($test['module_assignments']));
    
    $test['images'] = json_decode($test['images'], true) ?? [];
    
    // Determine status
    $status = 'draft';
    if ($test['archived']) {
        $status = 'archived';
    } elseif ($test['is_published']) {
        $status = 'published';
    }
    $test['status'] = $status;
    
    // Debug logging
    error_log("Loading test data - Test ID: $test_id, is_published: {$test['is_published']}, archived: {$test['archived']}, determined status: $status");
    
    sendJsonResponse(true, 'Test data retrieved successfully', $test);
    
} catch (PDOException $e) {
    error_log("Database Error in get_test_data: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Test ID: " . $test_id . ", User ID: " . $_SESSION['user_id']);
    sendJsonResponse(false, "Database error occurred. Please try again.", null, 500);
    
} catch (Exception $e) {
    error_log("General Error in get_test_data: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Test ID: " . $test_id . ", User ID: " . $_SESSION['user_id']);
    sendJsonResponse(false, "An unexpected error occurred. Please try again.", null, 500);
}
?>
