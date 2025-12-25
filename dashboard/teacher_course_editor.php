<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Debug logging
error_log("=== TEACHER COURSE EDITOR LOADED ===");
error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set'));
error_log("Script name: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set'));
error_log("Current working directory: " . getcwd());
error_log("Error log path: " . ini_get('error_log'));

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once 'includes/teacher_profile_functions.php';
require_once 'audit_logger.php';

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Check if teacher has hybrid permissions
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ? AND permission_name IN ('nav_users', 'nav_reports')");
$stmt->execute([$_SESSION['user_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
$is_hybrid = !empty($permissions);

// Initialize variables
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = $course_id > 0 ? 'edit' : 'create';
$course = null;
$chapters = [];
$sections = [];
$error_message = "";

// If editing, fetch existing course data
if ($mode === 'edit') {
    try {
        $pdo->beginTransaction();

        // Fetch course details
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   cat.name as category,
                   cc.name as course_category_name,
                   cc.id as course_category_id
            FROM courses c 
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN course_category cc ON c.course_category_id = cc.id
            WHERE c.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$course_id, $_SESSION['user_id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            header("Location: teacher_courses.php");
            exit();
        }

        // Fetch sections
        $stmt = $pdo->prepare("
            SELECT s.* 
            FROM sections s
            WHERE s.course_id = ? 
            ORDER BY s.order_index
        ");
        $stmt->execute([$course_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug sections
        error_log("Sections found: " . count($sections));

        // Fetch chapters (chapters belong to sections, not directly to courses)
        $stmt = $pdo->prepare("
            SELECT c.* FROM chapters c
            JOIN sections s ON c.section_id = s.id
            WHERE s.course_id = ?
            ORDER BY s.order_index, c.order_index
        ");
        $stmt->execute([$course_id]);
        $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Chapters found: " . count($chapters));

        // Fetch quizzes (quizzes belong to sections, not directly to courses)
        $stmt = $pdo->prepare("
            SELECT q.*, 
                   qq.id as question_id, qq.question_text, qq.question_type, qq.score as points,
                   qq.word, qq.romaji, qq.meaning, qq.audio_url, qq.accuracy_threshold,
                   qq.word_definition_pairs, qq.translation_pairs, qq.answers,
                   qq.order_index as question_order,
                   qc.id as choice_id, qc.choice_text, qc.is_correct, qc.order_index as choice_order
            FROM quizzes q
            JOIN sections s ON q.section_id = s.id
            LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id
            LEFT JOIN quiz_choices qc ON qq.id = qc.question_id
            WHERE s.course_id = ?
            ORDER BY s.order_index, q.order_index, qq.order_index, qc.order_index
        ");
        $stmt->execute([$course_id]);
        $quizResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organize quiz data with complete question information
        $quizzes = [];
        foreach ($quizResults as $row) {
            if (!isset($quizzes[$row['id']])) {
                $quizzes[$row['id']] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'instructions' => $row['description'], // For compatibility
                    'time_limit' => $row['time_limit'],
                    'max_retakes' => $row['max_retakes'] ?? 3,
                    'passing_score' => $row['passing_score'],
                    'total_points' => $row['total_points'],
                    'order_index' => $row['order_index'],
                    'section_id' => $row['section_id'],
                    'questions' => []
                ];
            }
            
            if ($row['question_id'] && !isset($quizzes[$row['id']]['questions'][$row['question_id']])) {
                // Parse JSON fields if they exist
                $wordDefinitionPairs = null;
                $translationPairs = null;
                $answers = null;
                
                if ($row['word_definition_pairs']) {
                    $wordDefinitionPairs = json_decode($row['word_definition_pairs'], true);
                }
                if ($row['translation_pairs']) {
                    $translationPairs = json_decode($row['translation_pairs'], true);
                }
                if ($row['answers']) {
                    $answers = json_decode($row['answers'], true);
                }
                
                $quizzes[$row['id']]['questions'][$row['question_id']] = [
                    'id' => $row['question_id'],
                    'question_text' => $row['question_text'],
                    'question' => $row['question_text'], // For compatibility
                    'text' => $row['question_text'], // For compatibility
                    'type' => $row['question_type'],
                    'points' => $row['points'],
                    'score' => $row['points'], // For compatibility
                    'word' => $row['word'],
                    'romaji' => $row['romaji'],
                    'meaning' => $row['meaning'],
                    'audio_url' => $row['audio_url'],
                    'accuracy_threshold' => $row['accuracy_threshold'],
                    'word_definition_pairs' => $wordDefinitionPairs,
                    'translation_pairs' => $translationPairs,
                    'answers' => $answers,
                    'correct_answers' => $answers, // For compatibility
                    'order_index' => $row['question_order'] ?? 0,
                    'choices' => []
                ];
                
                // Add evaluation object for pronunciation questions
                if ($row['question_type'] === 'pronunciation' && $row['accuracy_threshold']) {
                    $quizzes[$row['id']]['questions'][$row['question_id']]['evaluation'] = [
                        'accuracy_threshold' => $row['accuracy_threshold'],
                        'expected' => $row['word']
                    ];
                }
            }
            
            if ($row['choice_id']) {
                $quizzes[$row['id']]['questions'][$row['question_id']]['choices'][] = [
                    'id' => $row['choice_id'],
                    'text' => $row['choice_text'],
                    'is_correct' => (bool)$row['is_correct'],
                    'order_index' => $row['choice_order'] ?? 0
                ];
            }
        }

        // Convert questions from associative to indexed array and ensure proper structure
        foreach ($quizzes as &$quiz) {
            $quiz['questions'] = array_values($quiz['questions']);
        }
        unset($quiz);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error loading course: " . $e->getMessage();
    }
}

// Fetch categories for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch course categories for dropdown (add after fetching $categories)
$stmt = $pdo->prepare("SELECT id, name FROM course_category ORDER BY name");
$stmt->execute();
$courseCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle file upload first if present
$image_path = null;
if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
    try {
        error_log("Processing file upload: " . print_r($_FILES['course_image'], true));
        
        $upload_dir = __DIR__ . '/../uploads/course_images/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        // Ensure directory is writable
        if (!is_writable($upload_dir)) {
            chmod($upload_dir, 0755);
            if (!is_writable($upload_dir)) {
                throw new Exception('Upload directory is not writable');
            }
        }

        // Basic file checks
        if (!isset($_FILES['course_image']['tmp_name']) || empty($_FILES['course_image']['tmp_name'])) {
            throw new Exception('No file was uploaded');
        }

        // Use is_uploaded_file() for security
        if (!is_uploaded_file($_FILES['course_image']['tmp_name'])) {
            error_log("Invalid upload attempt - file: " . $_FILES['course_image']['tmp_name']);
            throw new Exception('Invalid file upload');
        }

        // Get file information
        $file_info = pathinfo($_FILES['course_image']['name']);
        if (!isset($file_info['extension'])) {
            throw new Exception('File has no extension');
        }
        
        $file_extension = strtolower($file_info['extension']);
        
        // Validate file extension
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file extension. Only JPG, PNG and GIF are allowed.');
        }

        // Generate unique filename
        $new_filename = uniqid('course_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        // Try to move the file
        if (!move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_path)) {
            $move_error = error_get_last();
            error_log("Failed to move uploaded file from {$_FILES['course_image']['tmp_name']} to {$upload_path}");
            error_log("Move error: " . print_r($move_error, true));
            throw new Exception('Failed to move uploaded file. Error: ' . ($move_error['message'] ?? 'Unknown error'));
        }
        
        // Verify the file was actually saved
        if (!file_exists($upload_path)) {
            throw new Exception('File was not saved to destination');
        }
        
        // Set the image path to be stored in the database
        $image_path = $new_filename;
        error_log("Image uploaded successfully: " . $image_path);
        
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        error_log("File upload details: " . print_r($_FILES['course_image'], true));
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path); // Clean up any partially uploaded file
        }
        throw $e;
    }
} elseif (isset($_FILES['course_image']) && $_FILES['course_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Handle other upload errors
    switch ($_FILES['course_image']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('File size exceeds limit');
        case UPLOAD_ERR_PARTIAL:
            throw new Exception('File was only partially uploaded');
        default:
            throw new Exception('Unknown upload error: ' . $_FILES['course_image']['error']);
    }
}

// Handle video file upload
function handleVideoUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    try {
        $upload_dir = __DIR__ . '/../uploads/chapter_videos/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create video upload directory');
            }
        }
        
        // File validation
        $file_info = pathinfo($file['name']);
        $allowed_extensions = ['mp4', 'webm', 'avi', 'mov', 'wmv'];
        
        if (!isset($file_info['extension']) || !in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            throw new Exception('Invalid video file extension. Only MP4, WEBM, AVI, MOV and WMV are allowed.');
        }
        
        // Check file size (50MB limit)
        if ($file['size'] > 50 * 1024 * 1024) {
            throw new Exception('Video file is too large. Maximum size is 50MB.');
        }
        
        // Generate unique filename
        $new_filename = uniqid('video_') . '.' . strtolower($file_info['extension']);
        $upload_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to move uploaded video file');
        }
        
        return $new_filename;
    } catch (Exception $e) {
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        throw $e;
    }
}

// Handle audio file upload for pronunciation questions
function handleAudioUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    try {
        $upload_dir = __DIR__ . '/../uploads/pronunciation_audio/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create audio upload directory');
            }
        }
        
        // File validation
        $file_info = pathinfo($file['name']);
        $allowed_extensions = ['mp3', 'wav', 'm4a', 'ogg'];
        
        if (!isset($file_info['extension']) || !in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            throw new Exception('Invalid audio file extension. Only MP3, WAV, M4A and OGG are allowed.');
        }
        
        // Check file size (10MB limit for audio)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('Audio file is too large. Maximum size is 10MB.');
        }
        
        // Generate unique filename
        $new_filename = uniqid('audio_') . '.' . strtolower($file_info['extension']);
        $upload_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to move uploaded audio file');
        }
        
        return 'uploads/pronunciation_audio/' . $new_filename;
    } catch (Exception $e) {
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        throw $e;
    }
}

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
    error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set'));
    error_log("Script name: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set'));
    
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
    error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set'));
    error_log("Script name: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set'));
    
    // Send a user-friendly message
    sendJsonResponse(false, "An unexpected error occurred. Please try again.", null, 500);
}

// Set error and exception handlers
set_error_handler("handleError");
set_exception_handler("handleException");

// Validate database connection
try {
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log the start of request processing
        error_log("=== STARTING COURSE SAVE PROCESS ===");
        error_log("POST data received: " . print_r($_POST, true));
        error_log("FILES data received: " . print_r($_FILES, true));
        error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
        error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
        error_log("Session role: " . ($_SESSION['role'] ?? 'not set'));

        // Start transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Rolled back existing transaction");
        }
        $pdo->beginTransaction();
        error_log("Started new transaction");

        // Validate and sanitize input data
        $input = filter_input_array(INPUT_POST, [
            'title' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'description' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'category' => FILTER_VALIDATE_INT,
            'course_category_id' => FILTER_VALIDATE_INT,
            'price' => FILTER_VALIDATE_FLOAT,
            'status' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'chapters' => FILTER_UNSAFE_RAW,
            'sections' => FILTER_UNSAFE_RAW,
            'quizzes' => FILTER_UNSAFE_RAW
        ]);

        error_log("Filtered input data: " . print_r($input, true));

        if ($input === null || $input === false) {
            throw new Exception('Invalid input data received: ' . print_r($_POST, true));
        }

        // Validate required fields
        if (empty($input['title'])) {
            error_log("Validation failed: Course title is required");
            throw new Exception('Course title is required');
        }

        if (empty($input['course_category_id'])) {
            error_log("Validation failed: Course category is required");
            throw new Exception('Course category is required');
        }

        if (empty($input['category'])) {
            error_log("Validation failed: Module level is required");
            throw new Exception('Module level is required');
        }

        // Validate status
        $allowedStatuses = ['draft', 'published'];
        if (!in_array($input['status'], $allowedStatuses)) {
            throw new Exception('Invalid course status');
        }

        // Validate data types
        $title = trim($input['title']);
        $description = trim($input['description'] ?? '');
        $category_id = $input['category'] ? (int)$input['category'] : null;
        $course_category_id = (int)$input['course_category_id'];
        $price = is_numeric($input['price']) ? (float)$input['price'] : 0.00;
        $status = trim($input['status']);
        $teacher_id = (int)$_SESSION['user_id'];
        
        // Validate category_id exists if provided
        if ($category_id) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new Exception("Invalid category ID: $category_id");
            }
        }

        // Validate course_category_id exists
        $stmt = $pdo->prepare("SELECT id FROM course_category WHERE id = ?");
        $stmt->execute([$course_category_id]);
        $courseCategory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courseCategory) {
            throw new Exception("Invalid course category ID: $course_category_id");
        }

        // Decode JSON data for sections and chapters
        $sections = !empty($input['sections']) ? json_decode($input['sections'], true) : [];
        $chapters = !empty($input['chapters']) ? json_decode($input['chapters'], true) : [];
        $quizzes = !empty($input['quizzes']) ? json_decode($input['quizzes'], true) : [];

        // Validate JSON decoding
        if ($input['sections'] && $sections === null) {
            throw new Exception('Invalid sections data format');
        }
        if ($input['chapters'] && $chapters === null) {
            throw new Exception('Invalid chapters data format');
        }
        if ($input['quizzes'] && $quizzes === null) {
            throw new Exception('Invalid quizzes data format');
        }

        // Debug log
        error_log("Processing course save - Mode: " . $mode . ", Course ID: " . $course_id);
        error_log("Processed data: " . json_encode([
            'title' => $title,
            'category_id' => $category_id,
            'price' => $price,
            'status' => $status,
            'teacher_id' => $teacher_id
        ]));

        // Validate teacher ownership if editing
        if ($mode === 'edit' && $course_id > 0) {
            $stmt = $pdo->prepare("SELECT id, image_path FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$course_id, $teacher_id]);
            $existing_course = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing_course) {
                throw new Exception('Unauthorized access to course');
            }
            
            // If new image uploaded, delete old image if exists
            if ($image_path && !empty($existing_course['image_path'])) {
                $old_image_path = '../uploads/course_images/' . $existing_course['image_path'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
        }

        if ($mode === 'edit' && $course_id > 0) {
            // Update existing course
            $sql = "UPDATE courses SET 
                    title = :title,
                    description = :description,
                    category_id = :category_id,
                    course_category_id = :course_category_id,
                    price = :price,
                    status = :status,
                    level = :level,
                    is_published = :is_published,
                    updated_at = :updated_at,
                    published_at = CASE 
                        WHEN :status2 = 'published' AND (is_published = 0 OR is_published IS NULL) THEN NOW()
                        WHEN :status3 = 'published' THEN published_at
                        ELSE NULL
                    END";

            if ($image_path !== null) {
                $sql .= ", image_path = :image_path";
            }

            $sql .= " WHERE id = :course_id AND teacher_id = :teacher_id";

            $params = [
                ':title' => $title,
                ':description' => $description,
                ':category_id' => $category_id,
                ':course_category_id' => $course_category_id,
                ':price' => $price,
                ':status' => $status,
                ':status2' => $status, // Second reference to status for the CASE statement
                ':status3' => $status, // Third reference to status for the CASE statement
                ':level' => 'beginner', // Default level - you might want to make this configurable
                ':is_published' => ($status === 'published' ? 1 : 0),
                ':updated_at' => date('Y-m-d H:i:s'),
                ':course_id' => $course_id,
                ':teacher_id' => $teacher_id
            ];

            if ($image_path !== null) {
                $params[':image_path'] = $image_path;
            }

            $stmt = $pdo->prepare($sql);
            if (!$stmt->execute($params)) {
                $errorInfo = $stmt->errorInfo();
                error_log("Course update failed - SQL: " . $sql);
                error_log("Course update failed - Params: " . json_encode($params));
                error_log("Course update failed - Error: " . json_encode($errorInfo));
                throw new Exception('Failed to update course: ' . implode(', ', $errorInfo));
            }

        } else {
            // Create new course
            $sql = "INSERT INTO courses (
                    title, description, category_id, course_category_id, price, status, level,
                    teacher_id, is_published, created_at, updated_at, image_path, published_at
                ) VALUES (
                    :title, :description, :category_id, :course_category_id, :price, :status, :level,
                    :teacher_id, :is_published, :created_at, :updated_at, :image_path,
                    CASE WHEN :status2 = 'published' THEN NOW() ELSE NULL END
                )";

            $stmt = $pdo->prepare($sql);
            $createParams = [
                ':title' => $title,
                ':description' => $description,
                ':category_id' => $category_id,
                ':course_category_id' => $course_category_id,
                ':price' => $price,
                ':status' => $status,
                ':status2' => $status, // Second reference to status for the CASE statement
                ':level' => 'beginner', // Default level - you might want to make this configurable
                ':teacher_id' => $teacher_id,
                ':is_published' => ($status === 'published' ? 1 : 0),
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
                ':image_path' => $image_path
            ];
            
            if (!$stmt->execute($createParams)) {
                $errorInfo = $stmt->errorInfo();
                error_log("Course creation failed - SQL: " . $sql);
                error_log("Course creation failed - Params: " . json_encode($createParams));
                error_log("Course creation failed - Error: " . json_encode($errorInfo));
                throw new Exception('Failed to create course: ' . implode(', ', $errorInfo));
            }
            
            $course_id = $pdo->lastInsertId();
            
            // Validate course was created successfully
            if (!$course_id || $course_id <= 0) {
                throw new Exception("Failed to create course. Course ID is: " . $course_id);
            }
        }

        // Process sections first (chapters depend on sections)
        $sectionIdMapping = []; // Map temporary IDs to real IDs
        if (!empty($sections)) {
            // Get existing section IDs for this course
            $stmt = $pdo->prepare("
                SELECT s.id 
                FROM sections s
                WHERE s.course_id = ?
            ");
            $stmt->execute([$course_id]);
            $existingSectionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Track processed section IDs
            $processedSectionIds = [];

            foreach ($sections as $section) {
                try {
                    if (empty($section['title'])) {
                        error_log("Section title is required: " . json_encode($section));
                        continue;
                    }

                    if (empty($section['id']) || strpos($section['id'], 'new_') === 0) {
                        // Insert new section
                        $stmt = $pdo->prepare("
                            INSERT INTO sections (course_id, title, description, order_index)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $course_id,
                            $section['title'],
                            $section['description'] ?? '',
                            $section['order_index']
                        ]);
                        $newSectionId = $pdo->lastInsertId();
                        $processedSectionIds[] = $newSectionId;
                        
                        // Map temporary ID to real ID
                        if (strpos($section['id'], 'new_') === 0) {
                            $sectionIdMapping[$section['id']] = $newSectionId;
                            error_log("Mapped section ID: {$section['id']} -> {$newSectionId}");
                        }
                    } else {
                        // Update existing section
                        $stmt = $pdo->prepare("
                            UPDATE sections 
                            SET title = ?, description = ?, order_index = ?
                            WHERE id = ? AND course_id = ?
                        ");
                        $stmt->execute([
                            $section['title'],
                            $section['description'] ?? '',
                            $section['order_index'],
                            $section['id'],
                            $course_id
                        ]);
                        $processedSectionIds[] = $section['id'];
                    }
                } catch (Exception $e) {
                    error_log("Error processing section: " . json_encode($section) . " - Error: " . $e->getMessage());
                    throw new Exception('Error processing section: ' . $e->getMessage());
                }
            }

            // Delete sections that were not in the update
            $sectionsToDelete = array_diff($existingSectionIds, $processedSectionIds);
            if (!empty($sectionsToDelete)) {
                try {
                    // Delete the sections (chapters will be deleted by cascade)
                    $stmt = $pdo->prepare("DELETE FROM sections WHERE id IN (" . implode(',', array_fill(0, count($sectionsToDelete), '?')) . ")");
                    $stmt->execute($sectionsToDelete);
                    error_log("Deleted sections: " . implode(', ', $sectionsToDelete));
                } catch (Exception $e) {
                    error_log("Error deleting sections: " . $e->getMessage());
                    throw new Exception('Error deleting sections: ' . $e->getMessage());
                }
            }
        }

        // Process chapters after sections (chapters depend on sections)
        if (!empty($chapters)) {
            // Get existing chapter IDs for this course through sections
            $stmt = $pdo->prepare("SELECT ch.id FROM chapters ch JOIN sections s ON ch.section_id = s.id WHERE s.course_id = ?");
            $stmt->execute([$course_id]);
            $existingChapterIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Track processed chapter IDs
            $processedChapterIds = [];

            foreach ($chapters as $chapter) {
                try {
                    $video_file_path = null;
                    
                    // Handle video file upload if present
                    if (isset($chapter['video_file_field']) && isset($_FILES[$chapter['video_file_field']])) {
                        $video_file_path = handleVideoUpload($_FILES[$chapter['video_file_field']]);
                    }
                    
                    $video_type = $chapter['video_type'] ?? null;
                    $video_url = null;
                    $video_copyright = $chapter['video_copyright'] ?? null;
                    
                    if ($video_type === 'url') {
                        $video_url = $chapter['video_url'] ?? null;
                    }

                    if (empty($chapter['id']) || strpos($chapter['id'], 'new_') === 0) {
                        // Insert new chapter - require section_id
                        if (empty($chapter['section_id'])) {
                            error_log("Chapter creation requires section_id: " . json_encode($chapter));
                            continue;
                        }
                        
                        // Map section_id if it's a temporary ID
                        $sectionId = $chapter['section_id'];
                        if (isset($sectionIdMapping[$sectionId])) {
                            $sectionId = $sectionIdMapping[$sectionId];
                            error_log("Mapped chapter section_id: {$chapter['section_id']} -> {$sectionId}");
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO chapters (section_id, title, content_type, content, video_url, video_type, video_file_path, video_copyright, order_index, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $sectionId,
                            $chapter['title'],
                            $chapter['content_type'] ?? 'text',
                            $chapter['content'] ?? '',
                            $video_url,
                            $video_type,
                            $video_file_path,
                            $video_copyright,
                            $chapter['order_index'] ?? 0
                        ]);
                        $chapter_id = $pdo->lastInsertId();
                        $processedChapterIds[] = $chapter_id;
                        
                        error_log("Chapter created successfully with ID: $chapter_id, video_type: $video_type, video_file_path: $video_file_path, video_copyright: " . ($video_copyright ? 'yes' : 'no'));
                    } else {
                        // Update existing chapter
                        $existing_video_file = null;
                        
                        // Get existing video file path if updating
                        if ($video_file_path === null) {
                            $stmt = $pdo->prepare("SELECT video_file_path FROM chapters WHERE id = ?");
                            $stmt->execute([$chapter['id']]);
                            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($existing) {
                                $existing_video_file = $existing['video_file_path'];
                            }
                        }
                        
                        $stmt = $pdo->prepare("
                            UPDATE chapters 
                            SET title = ?, content_type = ?, content = ?, video_url = ?, video_type = ?, video_file_path = ?, video_copyright = ?, order_index = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $chapter['title'],
                            $chapter['content_type'] ?? 'text',
                            $chapter['content'] ?? '',
                            $video_url,
                            $video_type,
                            $video_file_path ?? $existing_video_file,
                            $video_copyright,
                            $chapter['order_index'] ?? 0,
                            $chapter['id']
                        ]);
                        $processedChapterIds[] = $chapter['id'];
                        
                        // Delete old video file if new one uploaded
                        if ($video_file_path && $existing_video_file && $existing_video_file !== $video_file_path) {
                            $old_video_path = '../uploads/chapter_videos/' . $existing_video_file;
                            if (file_exists($old_video_path)) {
                                unlink($old_video_path);
                            }
                        }
                        
                        error_log("Chapter updated successfully with ID: " . $chapter['id'] . ", video_type: $video_type, video_file_path: " . ($video_file_path ?? $existing_video_file) . ", video_copyright: " . ($video_copyright ? 'yes' : 'no'));
                    }
                } catch (Exception $e) {
                    error_log("Error processing chapter: " . json_encode($chapter) . " - Error: " . $e->getMessage());
                    throw new Exception('Error processing chapter: ' . $e->getMessage());
                }
            }

            // Delete chapters that were not in the update
            $chaptersToDelete = array_diff($existingChapterIds, $processedChapterIds);
            if (!empty($chaptersToDelete)) {
                try {
                    // Delete the chapters (sections will be deleted by cascade if needed)
                    $stmt = $pdo->prepare("DELETE FROM chapters WHERE id IN (" . implode(',', array_fill(0, count($chaptersToDelete), '?')) . ")");
                    $stmt->execute($chaptersToDelete);
                } catch (Exception $e) {
                    error_log("Error deleting chapters: " . json_encode($chaptersToDelete) . " - Error: " . $e->getMessage());
                    throw new Exception('Error deleting chapters: ' . $e->getMessage());
                }
            }
        }

        // Process quizzes after sections and chapters (quizzes depend on sections)
        if (!empty($quizzes)) {
            // Get existing quiz IDs for this course through sections
            $stmt = $pdo->prepare("SELECT q.id FROM quizzes q JOIN sections s ON q.section_id = s.id WHERE s.course_id = ?");
            $stmt->execute([$course_id]);
            $existingQuizIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Track processed quiz IDs
            $processedQuizIds = [];

            foreach ($quizzes as $quiz) {
                try {
                    if (empty($quiz['title'])) {
                        error_log("Quiz title is required: " . json_encode($quiz));
                        continue;
                    }

                    if (empty($quiz['id']) || strpos($quiz['id'], 'new_') === 0) {
                        // Insert new quiz - require section_id
                        if (empty($quiz['section_id'])) {
                            error_log("Quiz creation requires section_id: " . json_encode($quiz));
                            continue;
                        }
                        
                        // Map section_id if it's a temporary ID
                        $sectionId = $quiz['section_id'];
                        if (isset($sectionIdMapping[$sectionId])) {
                            $sectionId = $sectionIdMapping[$sectionId];
                            error_log("Mapped quiz section_id: {$quiz['section_id']} -> {$sectionId}");
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO quizzes (section_id, title, description, max_retakes, passing_score, order_index, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $sectionId,
                            $quiz['title'],
                            $quiz['description'] ?? '',
                            $quiz['max_retakes'] ?? 3,
                            $quiz['passing_score'] ?? 70,
                            $quiz['order_index'] ?? 0
                        ]);
                        $quizId = $pdo->lastInsertId();
                        $processedQuizIds[] = $quizId;
                        
                        // Process quiz questions if any
                        if (!empty($quiz['questions'])) {
                            foreach ($quiz['questions'] as $question) {
                                // Validate question has required content based on type
                                $hasContent = false;
                                if (isset($question['type'])) {
                                    switch ($question['type']) {
                                        case 'pronunciation':
                                            $hasContent = !empty($question['word']) && !empty($question['romaji']) && !empty($question['meaning']);
                                            break;
                                        case 'word_definition':
                                            $hasContent = !empty($question['word_definition_pairs']) && is_array($question['word_definition_pairs']);
                                            break;
                                        case 'sentence_translation':
                                            $hasContent = !empty($question['translation_pairs']) && is_array($question['translation_pairs']);
                                            break;
                                        case 'fill_blank':
                                            $hasContent = !empty($question['question_text']) && !empty($question['answers']);
                                            break;
                                        default:
                                            $hasContent = !empty($question['question_text']) || !empty($question['text']) || !empty($question['question']);
                                    }
                                } else {
                                    // Fallback validation for backward compatibility
                                    $hasContent = !empty($question['text']) || !empty($question['question']) || !empty($question['word']);
                                }
                                
                                if (!$hasContent) {
                                    error_log("Skipping invalid question: " . json_encode($question));
                                    continue;
                                }
                                
                                // Handle audio file upload for pronunciation questions
                                $audio_url = null;
                                if (isset($question['type']) && $question['type'] === 'pronunciation') {
                                    // Check if there's an audio file to upload
                                    if (isset($question['audio_file_field']) && isset($_FILES[$question['audio_file_field']])) {
                                        $audio_url = handleAudioUpload($_FILES[$question['audio_file_field']]);
                                        error_log("Audio file uploaded: " . $audio_url);
                                    }
                                    
                                    // Debug logging for pronunciation questions
                                    error_log("Pronunciation question data: " . json_encode($question));
                                    error_log("Word: " . ($question['word'] ?? 'NULL'));
                                    error_log("Romaji: " . ($question['romaji'] ?? 'NULL'));
                                    error_log("Meaning: " . ($question['meaning'] ?? 'NULL'));
                                    error_log("Audio URL: " . ($audio_url ?? ($question['audio_url'] ?? 'NULL')));
                                    error_log("Accuracy Threshold (top-level): " . (isset($question['accuracy_threshold']) ? $question['accuracy_threshold'] : 'NULL'));
                                    error_log("Accuracy Threshold (evaluation): " . (isset($question['evaluation']['accuracy_threshold']) ? $question['evaluation']['accuracy_threshold'] : 'NULL'));
                                }
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO quiz_questions (quiz_id, question_text, question_type, word_definition_pairs, translation_pairs, word, romaji, meaning, audio_url, accuracy_threshold, answers, score, order_index, created_at, updated_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                                ");
                                
                                // Ensure all parameters are properly set
                                $executeParams = [
                                    $quizId,
                                    $question['question_text'] ?? $question['question'] ?? $question['text'] ?? '',
                                    $question['type'] ?? 'multiple_choice',
                                    isset($question['word_definition_pairs']) ? json_encode($question['word_definition_pairs']) : null,
                                    isset($question['translation_pairs']) ? json_encode($question['translation_pairs']) : null,
                                    $question['word'] ?? null,
                                    $question['romaji'] ?? null,
                                    $question['meaning'] ?? null,
                                    $audio_url ?? $question['audio_url'] ?? null,
                                    isset($question['accuracy_threshold']) ? $question['accuracy_threshold'] : (isset($question['evaluation']['accuracy_threshold']) ? $question['evaluation']['accuracy_threshold'] : null),
                                    isset($question['answers']) ? json_encode($question['answers']) : (isset($question['correct_answers']) ? json_encode($question['correct_answers']) : null),
                                    $question['points'] ?? $question['score'] ?? 1,
                                    $question['order_index'] ?? 0
                                ];
                                
                                // Validate parameter count
                                if (count($executeParams) !== 13) {
                                    error_log("Parameter count mismatch: Expected 13, got " . count($executeParams));
                                    error_log("Execute params: " . json_encode($executeParams));
                                    throw new Exception("Parameter count mismatch in quiz question insert");
                                }
                                
                                error_log("Executing quiz question insert with " . count($executeParams) . " parameters");
                                error_log("Question type: " . ($question['type'] ?? 'not set'));
                                error_log("Question data: " . json_encode($question));
                                error_log("Execute params: " . json_encode($executeParams));
                                error_log("SQL statement: " . $stmt->queryString);
                                
                                // Debug each parameter individually
                                for ($i = 0; $i < count($executeParams); $i++) {
                                    error_log("Parameter $i: " . json_encode($executeParams[$i]));
                                }
                                
                                $stmt->execute($executeParams);
                                $questionId = $pdo->lastInsertId();
                                
                                // Process question choices if any
                                if (!empty($question['choices'])) {
                                    foreach ($question['choices'] as $choice) {
                                        if (empty($choice['text'])) continue;
                                        
                                        $stmt = $pdo->prepare("
                                            INSERT INTO quiz_choices (question_id, choice_text, is_correct, order_index, created_at, updated_at)
                                            VALUES (?, ?, ?, ?, NOW(), NOW())
                                        ");
                                        error_log("Executing quiz choice insert with question_id: $questionId, text: " . $choice['text']);
                                        error_log("Choice data: " . json_encode($choice));
                                        $stmt->execute([
                                            $questionId,
                                            $choice['text'],
                                            $choice['is_correct'] ? 1 : 0,
                                            $choice['order_index'] ?? 0
                                        ]);
                                    }
                                }
                            }
                        }
                        
                        error_log("Quiz created successfully with ID: $quizId");
                    } else {
                        // Update existing quiz
                        $stmt = $pdo->prepare("
                            UPDATE quizzes 
                            SET title = ?, description = ?, max_retakes = ?, order_index = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $quiz['title'],
                            $quiz['description'] ?? '',
                            $quiz['max_retakes'] ?? 3,
                            $quiz['order_index'] ?? 0,
                            $quiz['id']
                        ]);
                        $processedQuizIds[] = $quiz['id'];
                        
                        // Handle quiz questions updates
                        if (!empty($quiz['questions'])) {
                            // Get existing question IDs for this quiz
                            $stmt = $pdo->prepare("SELECT id FROM quiz_questions WHERE quiz_id = ?");
                            $stmt->execute([$quiz['id']]);
                            $existingQuestionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            $processedQuestionIds = [];
                            
                            foreach ($quiz['questions'] as $question) {
                                // Validate question has required content based on type
                                $hasContent = false;
                                if (isset($question['type'])) {
                                    switch ($question['type']) {
                                        case 'pronunciation':
                                            $hasContent = !empty($question['word']) && !empty($question['romaji']) && !empty($question['meaning']);
                                            break;
                                        case 'word_definition':
                                            $hasContent = !empty($question['word_definition_pairs']) && is_array($question['word_definition_pairs']);
                                            break;
                                        case 'sentence_translation':
                                            $hasContent = !empty($question['translation_pairs']) && is_array($question['translation_pairs']);
                                            break;
                                        case 'fill_blank':
                                            $hasContent = !empty($question['question_text']) && !empty($question['answers']);
                                            break;
                                        default:
                                            $hasContent = !empty($question['question_text']) || !empty($question['question']) || !empty($question['text']);
                                    }
                                } else {
                                    // Fallback validation for backward compatibility
                                    $hasContent = !empty($question['question_text']) || !empty($question['question']) || !empty($question['word']);
                                }
                                
                                if (!$hasContent) {
                                    error_log("Skipping invalid question in update: " . json_encode($question));
                                    // Add to processed list to avoid deletion
                                    if (!empty($question['id'])) {
                                        $processedQuestionIds[] = $question['id'];
                                    }
                                    continue;
                                }
                                
                                // Handle audio file upload for pronunciation questions
                                $audio_url = null;
                                if (isset($question['type']) && $question['type'] === 'pronunciation') {
                                    if (isset($question['audio_file_field']) && isset($_FILES[$question['audio_file_field']])) {
                                        $audio_url = handleAudioUpload($_FILES[$question['audio_file_field']]);
                                        error_log("Audio file uploaded for existing question: " . $audio_url);
                                    } else {
                                        // Keep existing audio URL if no new file uploaded
                                        $stmt = $pdo->prepare("SELECT audio_url FROM quiz_questions WHERE id = ?");
                                        $stmt->execute([$question['id']]);
                                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($existing) {
                                            $audio_url = $existing['audio_url'];
                                        }
                                    }
                                }
                                
                                if (empty($question['id']) || strpos($question['id'], 'new_') === 0) {
                                    // Insert new question
                                    $stmt = $pdo->prepare("
                                        INSERT INTO quiz_questions (quiz_id, question_text, question_type, word_definition_pairs, translation_pairs, word, romaji, meaning, audio_url, accuracy_threshold, answers, score, order_index, created_at, updated_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                                    ");
                                    
                                    // Ensure all parameters are properly set
                                    $executeParams = [
                                        $quiz['id'],
                                        $question['question_text'] ?? $question['question'] ?? '',
                                        $question['type'] ?? 'multiple_choice',
                                        isset($question['word_definition_pairs']) ? json_encode($question['word_definition_pairs']) : null,
                                        isset($question['translation_pairs']) ? json_encode($question['translation_pairs']) : null,
                                        $question['word'] ?? null,
                                        $question['romaji'] ?? null,
                                        $question['meaning'] ?? null,
                                        $audio_url ?? $question['audio_url'] ?? null,
                                        isset($question['accuracy_threshold']) ? $question['accuracy_threshold'] : (isset($question['evaluation']['accuracy_threshold']) ? $question['evaluation']['accuracy_threshold'] : null),
                                        isset($question['answers']) ? json_encode($question['answers']) : (isset($question['correct_answers']) ? json_encode($question['correct_answers']) : null),
                                        $question['points'] ?? $question['score'] ?? 1,
                                        $question['order_index'] ?? 0
                                    ];
                                    
                                    // Validate parameter count
                                    if (count($executeParams) !== 13) {
                                        error_log("Parameter count mismatch (update): Expected 13, got " . count($executeParams));
                                        error_log("Execute params (update): " . json_encode($executeParams));
                                        throw new Exception("Parameter count mismatch in quiz question insert (update)");
                                    }
                                    
                                    error_log("Executing quiz question insert (update) with " . count($executeParams) . " parameters");
                                    error_log("Question type (update): " . ($question['type'] ?? 'not set'));
                                    error_log("Question data (update): " . json_encode($question));
                                    error_log("Execute params (update): " . json_encode($executeParams));
                                    error_log("SQL statement (update): " . $stmt->queryString);
                                    
                                    // Debug each parameter individually
                                    for ($i = 0; $i < count($executeParams); $i++) {
                                        error_log("Parameter $i (update): " . json_encode($executeParams[$i]));
                                    }
                                    
                                    $stmt->execute($executeParams);
                                    $questionId = $pdo->lastInsertId();
                                    $processedQuestionIds[] = $questionId;
                                    
                                    // Process new question choices
                                    if (!empty($question['choices'])) {
                                        try {
                                            foreach ($question['choices'] as $choice) {
                                                if (empty($choice['text'])) continue;
                                                
                                                $stmt = $pdo->prepare("
                                                    INSERT INTO quiz_choices (question_id, choice_text, is_correct, order_index, created_at, updated_at)
                                                    VALUES (?, ?, ?, ?, NOW(), NOW())
                                                ");
                                                error_log("Executing quiz choice insert (new question) with question_id: $questionId, text: " . $choice['text']);
                                                $stmt->execute([
                                                    $questionId,
                                                    $choice['text'],
                                                    $choice['is_correct'] ? 1 : 0,
                                                    $choice['order_index'] ?? 0
                                                ]);
                                            }
                                        } catch (Exception $e) {
                                            error_log("Error processing choices for new question: " . $e->getMessage());
                                            throw new Exception('Error processing choices for new question: ' . $e->getMessage());
                                        }
                                    }
                                } else {
                                    // Update existing question
                                    $stmt = $pdo->prepare("
                                        UPDATE quiz_questions 
                                        SET question_text = ?, question_type = ?, word_definition_pairs = ?, translation_pairs = ?, word = ?, romaji = ?, meaning = ?, audio_url = ?, accuracy_threshold = ?, answers = ?, score = ?, order_index = ?, updated_at = NOW()
                                        WHERE id = ?
                                    ");
                                    
                                    $executeParams = [
                                        $question['question_text'] ?? $question['question'] ?? '',
                                        $question['type'] ?? 'multiple_choice',
                                        isset($question['word_definition_pairs']) ? json_encode($question['word_definition_pairs']) : null,
                                        isset($question['translation_pairs']) ? json_encode($question['translation_pairs']) : null,
                                        $question['word'] ?? null,
                                        $question['romaji'] ?? null,
                                        $question['meaning'] ?? null,
                                        $audio_url ?? $question['audio_url'] ?? null,
                                        isset($question['accuracy_threshold']) ? $question['accuracy_threshold'] : (isset($question['evaluation']['accuracy_threshold']) ? $question['evaluation']['accuracy_threshold'] : null),
                                        isset($question['answers']) ? json_encode($question['answers']) : (isset($question['correct_answers']) ? json_encode($question['correct_answers']) : null),
                                        $question['points'] ?? $question['score'] ?? 1,
                                        $question['order_index'] ?? 0,
                                        $question['id']
                                    ];
                                    
                                    // Validate parameter count
                                    if (count($executeParams) !== 13) {
                                        error_log("Parameter count mismatch (update existing): Expected 13, got " . count($executeParams));
                                        error_log("Execute params (update existing): " . json_encode($executeParams));
                                        throw new Exception("Parameter count mismatch in quiz question update (existing)");
                                    }
                                    
                                    error_log("Executing quiz question update with " . count($executeParams) . " parameters");
                                    error_log("SQL statement (update existing): " . $stmt->queryString);
                                    
                                    // Debug each parameter individually
                                    for ($i = 0; $i < count($executeParams); $i++) {
                                        error_log("Parameter $i (update existing): " . json_encode($executeParams[$i]));
                                    }
                                    
                                    $stmt->execute($executeParams);
                                    $processedQuestionIds[] = $question['id'];
                                    
                                    // Handle choices for existing questions
                                    if (!empty($question['choices'])) {
                                        try {
                                            // Delete existing choices
                                            $stmt = $pdo->prepare("DELETE FROM quiz_choices WHERE question_id = ?");
                                            $stmt->execute([$question['id']]);
                                            
                                            // Insert updated choices
                                            foreach ($question['choices'] as $choice) {
                                                if (empty($choice['text'])) continue;
                                                
                                                $stmt = $pdo->prepare("
                                                    INSERT INTO quiz_choices (question_id, choice_text, is_correct, order_index, created_at, updated_at)
                                                    VALUES (?, ?, ?, ?, NOW(), NOW())
                                                ");
                                                error_log("Executing quiz choice insert (existing question) with question_id: " . $question['id'] . ", text: " . $choice['text']);
                                                $stmt->execute([
                                                    $question['id'],
                                                    $choice['text'],
                                                    $choice['is_correct'] ? 1 : 0,
                                                    $choice['order_index'] ?? 0
                                                ]);
                                            }
                                        } catch (Exception $e) {
                                            error_log("Error processing choices for question {$question['id']}: " . $e->getMessage());
                                            throw new Exception('Error processing choices for question: ' . $e->getMessage());
                                        }
                                    }
                                }
                            }
                            
                            // Delete questions that were not in the update
                            $questionsToDelete = array_diff($existingQuestionIds, $processedQuestionIds);
                            if (!empty($questionsToDelete)) {
                                $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE id IN (" . implode(',', array_fill(0, count($questionsToDelete), '?')) . ")");
                                $stmt->execute($questionsToDelete);
                                error_log("Deleted questions: " . implode(', ', $questionsToDelete));
                            }
                        }
                        
                        error_log("Quiz updated successfully with ID: {$quiz['id']}");
                    }
                } catch (Exception $e) {
                    error_log("Error processing quiz: " . json_encode($quiz) . " - Error: " . $e->getMessage());
                    error_log("Quiz processing error details: " . $e->getTraceAsString());
                    throw new Exception('Error processing quiz: ' . $e->getMessage());
                }
            }

            // Delete quizzes that were not in the update
            $quizzesToDelete = array_diff($existingQuizIds, $processedQuizIds);
            if (!empty($quizzesToDelete)) {
                try {
                    // Delete the quizzes (questions and choices will be deleted by cascade)
                    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id IN (" . implode(',', array_fill(0, count($quizzesToDelete), '?')) . ")");
                    $stmt->execute($quizzesToDelete);
                    error_log("Deleted quizzes: " . implode(', ', $quizzesToDelete));
                } catch (Exception $e) {
                    error_log("Error deleting quizzes: " . $e->getMessage());
                    throw new Exception('Error deleting quizzes: ' . $e->getMessage());
                }
            }
        }

        $pdo->commit();
        
        // Log audit entry for course save/publish
        try {
            $auditLogger = createAuditLogger($pdo);
            $teacher_username = $_SESSION['username'] ?? 'Unknown Teacher';
            
            if ($status === 'published') {
                $auditLogger->logEntry([
                    'user_id' => $_SESSION['user_id'],
                    'username' => $teacher_username,
                    'user_role' => 'teacher',
                    'action_type' => 'UPDATE',
                    'action_description' => $mode === 'edit' ? 'Published course' : 'Created and published new course',
                    'resource_type' => 'Course',
                    'resource_id' => "Course ID: $course_id",
                    'resource_name' => $title,
                    'outcome' => 'Success',
                    'new_value' => "Course '$title' published successfully",
                    'context' => [
                        'course_id' => $course_id,
                        'course_title' => $title,
                        'status' => $status,
                        'mode' => $mode,
                        'sections_count' => count($sections),
                        'chapters_count' => count($chapters),
                        'quizzes_count' => count($quizzes)
                    ]
                ]);
            } else {
                $auditLogger->logEntry([
                    'user_id' => $_SESSION['user_id'],
                    'username' => $teacher_username,
                    'user_role' => 'teacher',
                    'action_type' => 'UPDATE',
                    'action_description' => $mode === 'edit' ? 'Saved course as draft' : 'Created course as draft',
                    'resource_type' => 'Course',
                    'resource_id' => "Course ID: $course_id",
                    'resource_name' => $title,
                    'outcome' => 'Success',
                    'new_value' => "Course '$title' saved as draft",
                    'context' => [
                        'course_id' => $course_id,
                        'course_title' => $title,
                        'status' => $status,
                        'mode' => $mode,
                        'sections_count' => count($sections),
                        'chapters_count' => count($chapters),
                        'quizzes_count' => count($quizzes)
                    ]
                ]);
            }
        } catch (Exception $auditError) {
            // Don't fail the main operation if audit logging fails
            error_log("Audit logging error: " . $auditError->getMessage());
        }
        
        sendJsonResponse(true, 
            $status === 'published' ? 'Course published successfully' : 'Course saved as draft',
            ['course_id' => $course_id]
        );

    } catch (PDOException $e) {
        error_log("Database Error in course save: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        error_log("Error Message: " . $e->getMessage());
        
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Transaction rolled back due to database error");
        }
        
        sendJsonResponse(false, "Database error occurred. Please try again.", null, 500);

    } catch (Exception $e) {
        error_log("General Error in course save: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Transaction rolled back due to general error");
        }
        
        sendJsonResponse(false, $e->getMessage(), null, 400);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Editor - Japanese LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="js/question-type-manager.js"></script>
    <script>
        // Make teacher ID available globally
        window.teacherId = <?php echo json_encode($_SESSION['user_id'] ?? 'default'); ?>;
        window.currentPage = 'course-editor';
        
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            200: '#fecdd3',
                            300: '#fda4af',
                            400: '#fb7185',
                            500: '#f43f5e',
                            600: '#e11d48',
                            700: '#be123c',
                            800: '#9f1239',
                            900: '#881337',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="css/question-type-manager.css" rel="stylesheet">
    <link href="css/question-answer-forms.css" rel="stylesheet">
    <link href="css/course-editor-enhancements.css" rel="stylesheet">
    <script src="../assets/js/editor-shared.js"></script>
    
    <!-- TinyMCE for Chapter Content -->
    <script src="../assets/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
    <script>
// TinyMCE configuration - DO NOT set license key globally
// The license key will be set in each individual init call

window.addEventListener('DOMContentLoaded', function() {
    console.log('TinyMCE library loaded, ready for initialization');
    
    // Safety override to ensure ALL tinymce.init calls have license key
    if (typeof tinymce !== 'undefined') {
        const originalInit = tinymce.init;
        tinymce.init = function(config) {
            if (!config.license_key) {
                console.warn('Adding missing license_key to TinyMCE init call');
                config.license_key = 'gpl';
            }
            return originalInit.call(this, config);
        };
    }
});
</script>
    <script src="js/tinymce-chapter-editor.js"></script>

    <!-- Unsaved Changes Warning Modal -->
    <div id="unsavedChangesModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-[9999] hidden">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full mx-4 transform transition-all duration-500 scale-95 opacity-0 border border-gray-100" id="unsavedModalContent">
            <!-- Modal Header with gradient -->
            <div class="bg-gradient-to-r from-red-50 to-pink-50 p-6 rounded-t-3xl border-b border-gray-100">
                <div class="flex items-center">
                    <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-4 rounded-2xl shadow-lg mr-4">
                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-1">Unsaved Changes</h3>
                        <p class="text-gray-600 text-sm font-medium" id="unsavedChangesMessage">Your work might be lost</p>
                    </div>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="mb-6">
                    <p class="text-gray-700 leading-relaxed text-base mb-4">
                        You have made changes to your module that haven't been saved yet. 
                        If you leave now, all your progress will be lost.
                    </p>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i class="fas fa-lightbulb text-amber-600 text-lg mr-3 mt-0.5"></i>
                            <p class="text-amber-800 text-sm font-medium">
                                <strong>Tip:</strong> Save your work as a draft to continue later without losing progress.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Actions -->
                <div class="flex gap-3 justify-end">
                    <button type="button" id="leaveAnywayBtn" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all duration-200 transform hover:scale-105 border border-gray-200">
                        Leave Anyway
                    </button>
                    <button type="button" id="stayOnPageBtn" class="px-8 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl font-semibold hover:from-red-600 hover:to-red-700 transition-all duration-200 transform hover:scale-105 shadow-lg border border-red-600">
                        <i class="fas fa-save mr-2"></i>Stay & Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Anyway Confirmation Modal -->
    <div id="leaveAnywayModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-[9999] hidden">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-500 scale-95 opacity-0 border border-gray-100" id="leaveAnywayModalContent">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-red-50 to-pink-50 p-6 rounded-t-3xl border-b border-gray-100">
                <div class="flex items-center">
                    <div class="bg-gradient-to-r from-red-500 to-red-600 p-4 rounded-2xl shadow-lg mr-4">
                        <i class="fas fa-times-circle text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Confirm Exit</h3>
                        <p class="text-gray-600 text-sm font-medium">Are you absolutely sure?</p>
                    </div>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="mb-6">
                    <p class="text-gray-700 leading-relaxed text-base mb-4">
                        This will permanently discard all your unsaved changes. 
                        This action cannot be undone.
                    </p>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-600 text-lg mr-3 mt-0.5"></i>
                            <p class="text-red-800 text-sm font-medium">
                                <strong>Warning:</strong> All sections, chapters, and quizzes you've added will be lost.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Actions -->
                <div class="flex gap-3 justify-end">
                    <button type="button" id="cancelLeaveBtn" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all duration-200 transform hover:scale-105 border border-gray-200">
                        <i class="fas fa-arrow-left mr-2"></i>Go Back
                    </button>
                    <button type="button" id="confirmLeaveBtn" class="px-8 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl font-semibold hover:from-red-600 hover:to-red-700 transition-all duration-200 transform hover:scale-105 shadow-lg border border-red-600">
                        <i class="fas fa-times mr-2"></i>Yes, Leave
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        body { font-family: 'Inter', 'Noto Sans JP', sans-serif; background-color: #f3f4f6; min-height: 100vh; }
        .main-content { margin-left: 16rem; min-height: calc(100vh - 4rem); padding: 1.5rem; }
        [x-cloak] { display: none !important; }
        .form-input, .form-textarea, .form-select { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
        .btn { display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; cursor: pointer; }
        .btn-primary { background-color: #e11d48; color: white; }
        .btn-primary:hover { background-color: #be123c; }
        .btn-secondary { background-color: #6b7280; color: white; }
        .btn-secondary:hover { background-color: #4b5563; }
        .nav-link { transition: all 0.2s ease-in-out; }
        .nav-link:hover { background-color: #f3f4f6; }
        .nav-link.active { background-color: #fff1f2; color: #be123c; }
        .step-indicator { display: flex; justify-content: center; align-items: center; margin-bottom: 2rem; }
        .step { display: flex; align-items: center; }
        .step-circle { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.3s ease; }
        .step.active .step-circle { background-color: #dc2626; color: white; }
        .step.completed .step-circle { background-color: #10b981; color: white; }
        .step-line { width: 4rem; height: 2px; background-color: #e5e7eb; margin: 0 1rem; }
        .step.completed + .step .step-line { background-color: #10b981; }
        .step-content { display: none; }
        .step-content.active { display: block; }
        .notification { position: fixed; top: 1rem; right: 1rem; padding: 0.75rem 1.25rem; border-radius: 0.375rem; color: white; font-weight: 500; z-index: 100; transform: translateX(100%); transition: transform 0.3s ease-in-out; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .notification.show { transform: translateX(0); }
        .notification-success { background-color: #10b981; }
        .notification-error { background-color: #ef4444; }
        .notification-info { background-color: #3b82f6; }
        
        /* Enhanced Modal Styling */
        #unsavedChangesModal, #leaveAnywayModal {
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        #unsavedModalContent, #leaveAnywayModalContent {
            animation: slideInFromTop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        /* Button hover effects */
        #stayOnPageBtn, #confirmLeaveBtn {
            position: relative;
            overflow: hidden;
        }
        
        #stayOnPageBtn::before, #confirmLeaveBtn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        #stayOnPageBtn:hover::before, #confirmLeaveBtn:hover::before {
            left: 100%;
        }
        
        /* Pulsing warning icon */
        @keyframes pulseWarning {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        #unsavedChangesModal .fa-exclamation-triangle,
        #leaveAnywayModal .fa-exclamation-circle {
            animation: pulseWarning 2s infinite;
        }
        
        /* Modal entrance animations */
        @keyframes slideInFromTop {
            0% {
                transform: translateY(-50px) scale(0.95);
                opacity: 0;
            }
            100% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }
        
        @keyframes slideOutToTop {
            0% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
            100% {
                transform: translateY(-50px) scale(0.95);
                opacity: 0;
            }
        }
        
        /* Red Theme Consistency */
        .btn-primary, button.btn-primary, .btn.btn-primary { background-color: #e11d48 !important; color: white !important; border-color: #e11d48 !important; }
        .btn-primary:hover, button.btn-primary:hover, .btn.btn-primary:hover { background-color: #be123c !important; border-color: #be123c !important; }
        .nav-link.active, .nav-link.bg-primary-50 { background-color: #fff1f2 !important; color: #be123c !important; }
        .bg-primary-50 { background-color: #fff1f2 !important; }
        .text-primary-700 { color: #be123c !important; }
        .bg-primary-600 { background-color: #e11d48 !important; }
        .hover\:bg-primary-700:hover { background-color: #be123c !important; }
        
        /* Force red theme for step indicators and buttons */
        #addSectionBtn, #addQuizBtn, #addCategoryBtn, #editCategoryBtn, #deleteCategoryBtn {
            background-color: #e11d48 !important;
            color: white !important;
        }
        #addSectionBtn:hover, #addQuizBtn:hover {
            background-color: #be123c !important;
        }
        /* Pronunciation question styling */
        #pronunciationContainer .form-input[type="text"] { border-color: #a855f7; }
        #pronunciationContainer .form-input[type="text"]:focus { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1); }
        #pronunciationContainer .form-input[type="file"] { border: 2px dashed #d1d5db; background-color: #f9fafb; }
        #pronunciationContainer .form-input[type="file"]:hover { border-color: #9ca3af; background-color: #f3f4f6; }
        
        /* Question Type Picker Styles */
        .btn-outline { background-color: transparent; border: 1px solid currentColor; }
        .btn-outline:hover { background-color: currentColor; color: white; }
        .question-type-card { position: relative; overflow: hidden; }
        .question-type-card:hover { transform: translateY(-2px); }
        .question-type-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #3b82f6, #8b5cf6); opacity: 0; transition: opacity 0.2s; }
        .question-type-card:hover::before { opacity: 1; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        /* Accessibility improvements */
        .question-type-card:focus { outline: 2px solid #3b82f6; outline-offset: 2px; }
        .question-type-card[aria-selected="true"] { border-color: #3b82f6; background-color: #eff6ff; }
        
        /* TinyMCE Integration Styles */
        .tox-tinymce { 
            border: 1px solid #d1d5db !important; 
            border-radius: 0.375rem !important; 
            font-family: Inter, 'Noto Sans JP', sans-serif !important;
        }
        .tox-tinymce:focus-within { 
            border-color: #3b82f6 !important; 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important; 
        }
        .tox .tox-toolbar { 
            background: #f9fafb !important; 
            border-bottom: 1px solid #e5e7eb !important; 
        }
        .tox .tox-toolbar__group { 
            border-color: #e5e7eb !important; 
        }
        .tox .tox-tbtn { 
            color: #374151 !important; 
        }
        .tox .tox-tbtn:hover { 
            background: #e5e7eb !important; 
        }
        .tox .tox-tbtn--enabled { 
            background: #dbeafe !important; 
            color: #1d4ed8 !important; 
        }
        .tox .tox-statusbar { 
            border-top: 1px solid #e5e7eb !important; 
            background: #f9fafb !important; 
        }
        .tox .tox-edit-area { 
            border: none !important; 
        }
        
        /* Responsive TinyMCE */
        @media (max-width: 768px) {
            .tox-tinymce {
                font-size: 14px !important;
            }
            .tox .tox-toolbar {
                flex-wrap: wrap !important;
            }
        }
        
        /* Navigation styles */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }
        .main-content {
            height: calc(100vh - 64px);
            overflow-y: auto;
        }
        .main-content-container {
            margin-left: 16rem;
            min-height: calc(100vh - 4rem);
            padding: 1.5rem;
        }
        [x-cloak] { 
            display: none !important; 
        }
        .nav-link.active,
        .nav-link.bg-primary-50 {
            background-color: #fff1f2 !important;
            color: #be123c !important;
        }
        .dropdown-enter {
            transition: all 0.2s ease-out;
        }
        .dropdown-enter-start {
            opacity: 0;
            transform: translateY(-10px);
        }
        .dropdown-enter-end {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Quiz container scrolling */
        .quiz-container {
            max-height: 400px;
            overflow-y: auto;
        }

        /* Custom scrollbar for quiz container */
        .quiz-container::-webkit-scrollbar {
            width: 6px;
        }

        .quiz-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .quiz-container::-webkit-scrollbar-thumb {
            background:rgb(54, 51, 51);
            border-radius: 3px;
        }

        .quiz-container::-webkit-scrollbar-thumb:hover {
            background:rgb(65, 61, 62);
        }
        
        /* Custom scrollbar styles for quiz preview */
        .scrollbar-thin {
            scrollbar-width: thin;
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
            background: #d1d5db;
        }
        
        .scrollbar-track-gray-100::-webkit-scrollbar-track {
            background: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
        <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-red-600 to-red-800 text-white">
            <span class="text-2xl font-bold">Teacher Portal</span>
        </div>
        
        <!-- Teacher Profile -->
        <?php echo renderTeacherSidebarProfile($teacher_profile, $is_hybrid); ?>

        <!-- Sidebar Navigation -->
        <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: false }">
            <div class="space-y-1">
                <a href="teacher.php" 
                   class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </a>

                <a href="courses_available.php" 
                   class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                   <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                    Courses
                </a>

                <a href="teacher_create_module.php" 
                   class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Create New Module
                </a>

                <a href="teacher_drafts.php" 
                   class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                   :class="{ 'bg-primary-50 text-primary-700': currentPage === 'my-drafts' }">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    My Drafts
                </a>

                <a href="teacher_archive.php" 
                   class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                   :class="{ 'bg-primary-50 text-primary-700': currentPage === 'archived' }">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-archive-restore-icon lucide-archive-restore"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg>
                    Archived
                </a>

                <!-- Student Management Dropdown -->
                <div class="relative">
                    <button @click="studentDropdownOpen = !studentDropdownOpen" 
                            class="nav-link w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                            :class="{ 'bg-primary-50 text-primary-700': studentDropdownOpen }">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Student Management
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" 
                             :class="{ 'rotate-180': studentDropdownOpen }" 
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div x-show="studentDropdownOpen" 
                         x-transition:enter="dropdown-enter"
                         x-transition:enter-start="dropdown-enter-start"
                         x-transition:enter-end="dropdown-enter-end"
                         x-cloak
                         class="mt-1 ml-4 space-y-1">
                        
                        <a href="Student Management/student_profiles.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Student Profiles
                        </a>
                        
                        <a href="Student Management/progress_tracking.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Progress Tracking
                        </a>
                        
                        <a href="Student Management/quiz_performance.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Quiz Performance
                        </a>
                        
                        <a href="Student Management/engagement_monitoring.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Engagement Monitoring
                        </a>
                        
                        <a href="Student Management/completion_reports.php" 
                           class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Completion Reports
                        </a>
                    </div>
                </div>

                <a href="Placement Test/placement_test.php" 
                   class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
        <svg fill="#000000" viewBox="0 0 64 64" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg">
            <g id="SVGRepo_iconCarrier">
                            <g data-name="Layer 2" id="Layer_2">
                <path d="M51,5H41.21A2.93,2.93,0,0,0,39,4H34.21a2.94,2.94,0,0,0-4.42,0H25a2.93,2.93,0,0,0-2.21,1H13a2,2,0,0,0-2,2V59a2,2,0,0,0,2,2H51a2,2,0,0,0,2-2V7A2,2,0,0,0,51,5Zm-19.87.5A1,1,0,0,1,32,5a1,1,0,0,1,.87.51A1,1,0,0,1,33,6a1,1,0,0,1-2,0A1.09,1.09,0,0,1,31.13,5.5ZM32,9a3,3,0,0,0,3-3h4a1,1,0,0,1,.87.51A1,1,0,0,1,40,7V9a1,1,0,0,1-1,1H25a1,1,0,0,1-1-1V7a1.09,1.09,0,0,1,.13-.5A1,1,0,0,1,25,6h4A3,3,0,0,0,32,9ZM51,59H13V7h9V9a3,3,0,0,0,3,3H39a3,3,0,0,0,3-3V7h9Z"></path>
                <path d="M16,56H48V15H16Zm2-39H46V54H18Z"></path>
                <rect height="2" width="18" x="26" y="22"></rect>
                <rect height="2" width="4" x="20" y="22"></rect>
                <rect height="2" width="18" x="26" y="27"></rect>
                <rect height="2" width="4" x="20" y="27"></rect>
                <rect height="2" width="18" x="26" y="32"></rect>
                <rect height="2" width="4" x="20" y="32"></rect>
                <rect height="2" width="18" x="26" y="37"></rect>
                <rect height="2" width="4" x="20" y="37"></rect>
                <rect height="2" width="18" x="26" y="42"></rect>
                <rect height="2" width="4" x="20" y="42"></rect>
                <rect height="2" width="18" x="26" y="47"></rect>
                <rect height="2" width="4" x="20" y="47"></rect>
                            </g>
            </g>
        </svg>
        Placement Test
                </a>

                <a href="settings.php" 
                   class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </a>
            </div>
            
            <div class="mt-auto pt-4">
                <a href="../auth/logout.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-4">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm sticky top-0 z-0">
            <div class="px-6 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-900 ml-64">Module Editor</h1>
                <div class="text-sm text-gray-500">
                    <div id="current-date"><?php echo date('l, F j, Y'); ?></div>
                    <div id="current-time" class="text-xs text-gray-400 font-mono"></div>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <div id="courses-content" class="content-area active">
            <div class="main-content">
                <!-- Step Indicator with Preview Button -->
                <div class="relative mb-8">
                    <a href="includes/preview_course.php?id=<?php echo $course_id; ?>&preview=true" target="_blank" 
                       class="absolute right-0 top-0 inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Preview Module
                    </a> 
                    
            <!-- Step Indicator -->
            <div class="step-indicator bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                <div class="flex items-center justify-center space-x-8">
                    <div class="step active flex items-center" id="step1">
                        <div class="step-circle w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-full flex items-center justify-center shadow-lg">
                            <i class="fas fa-info-circle text-lg"></i>
                        </div>
                        <div class="step-text ml-3">
                            <div class="font-semibold text-gray-900">Module Info</div>
                            <div class="text-sm text-gray-500">Basic details</div>
                        </div>
                    </div>
                    <div class="step-line h-1 w-16 bg-gray-200 rounded-full"></div>
                    <div class="step flex items-center" id="step2">
                        <div class="step-circle w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center shadow-sm">
                            <i class="fas fa-layer-group text-lg"></i>
                        </div>
                        <div class="step-text ml-3">
                            <div class="font-semibold text-gray-500">Sections</div>
                            <div class="text-sm text-gray-400">Course structure</div>
                        </div>
                    </div>
                    <div class="step-line h-1 w-16 bg-gray-200 rounded-full"></div>
                    <div class="step flex items-center" id="step3">
                        <div class="step-circle w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center shadow-sm">
                            <i class="fas fa-book-open text-lg"></i>
                        </div>
                        <div class="step-text ml-3">
                            <div class="font-semibold text-gray-500">Chapters</div>
                            <div class="text-sm text-gray-400">Content creation</div>
                        </div>
                    </div>
                    <div class="step-line h-1 w-16 bg-gray-200 rounded-full"></div>
                    <div class="step flex items-center" id="step4">
                        <div class="step-circle w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center shadow-sm">
                            <i class="fas fa-question-circle text-lg"></i>
                        </div>
                        <div class="step-text ml-3">
                            <div class="font-semibold text-gray-500">Quizzes</div>
                            <div class="text-sm text-gray-400">Assessments</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 1: Module Information -->
            <div id="step1-content" class="step-content">
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="flex items-center mb-8">
                        <div class="bg-gradient-to-r from-red-500 to-red-600 p-3 rounded-xl shadow-lg">
                            <i class="fas fa-info-circle text-white text-xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold ml-4 bg-gradient-to-r from-red-600 to-red-700 bg-clip-text text-transparent">
                            Module Information
                        </h2>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Left Column -->
                        <div class="space-y-6">
                            <div class="group">
                                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                        <i class="fas fa-heading text-red-600 text-sm"></i>
                                    </div>
                                    Module Title *
                                </label>
                                <input type="text" id="moduleTitle" class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" required>
                            </div>
                            
                            <div class="group">
                                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                        <i class="fas fa-align-left text-red-600 text-sm"></i>
                                    </div>
                                    Description
                                </label>
                                <textarea id="moduleDescription" class="form-textarea w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200 resize-none" rows="4"></textarea>
                            </div>
                            
                            <div class="group">
                                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                        <i class="fas fa-peso-sign text-red-600 text-sm"></i>
                                    </div>
                                    Price (₱)
                                </label>
                                <input type="number" id="modulePrice" class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" min="0" step="0.01">
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="space-y-6">
                            <div class="group">
                                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                        <i class="fas fa-layer-group text-red-600 text-sm"></i>
                                    </div>
                                    Module Level *
                                </label>
                                <div class="flex gap-3">
                                    <select id="categorySelect" class="form-select flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" required>
                                        <option value="">Select Level</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['id']); ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="addCategoryBtn" class="btn bg-gradient-to-r from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700 px-4 py-3 rounded-xl shadow-lg transform hover:scale-105 transition-all duration-200" title="Add Level">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" id="editCategoryBtn" class="btn bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 px-4 py-3 rounded-xl shadow-lg transform hover:scale-105 transition-all duration-200" title="Edit Level">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" id="deleteCategoryBtn" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 px-4 py-3 rounded-xl shadow-lg transform hover:scale-105 transition-all duration-200" title="Delete Level">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="group">
                                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                        <i class="fas fa-tags text-red-600 text-sm"></i>
                                    </div>
                                    Course Category *
                                </label>
                                <select id="courseCategorySelect" class="form-select w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($courseCategories as $courseCategory): ?>
                                        <option value="<?php echo htmlspecialchars($courseCategory['id']); ?>">
                                            <?php echo htmlspecialchars($courseCategory['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="group">
                                <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                        <i class="fas fa-image text-red-600 text-sm"></i>
                                    </div>
                                    Module Image
                                </label>
                                <div class="relative">
                                    <input type="file" id="moduleImage" class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100" accept="image/*">
                                    <p class="text-xs text-gray-500 mt-2 flex items-center">
                                        <i class="fas fa-info-circle text-gray-400 mr-1"></i>
                                        Recommended: 800x450px, Max: 2MB
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end pt-6 border-t border-gray-100 mt-8">
                        <button type="button" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold" onclick="showStep(2)">
                            Next: Add Sections 
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Sections -->
            <div id="step2-content" class="step-content">
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="flex justify-between items-center mb-8">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-red-500 to-red-600 p-3 rounded-xl shadow-lg">
                                <i class="fas fa-folder text-white text-xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold ml-4 bg-gradient-to-r from-red-600 to-red-700 bg-clip-text text-transparent">
                                Course Sections
                            </h2>
                        </div>
                        <button type="button" id="addSectionBtn" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                            <i class="fas fa-plus mr-2"></i> Add Section
                        </button>
                    </div>
                    
                    <div id="sectionsContainer" class="mb-8">
                        <!-- Sections will be dynamically rendered here -->
                    </div>
                    
                    <div class="flex justify-between pt-6 border-t border-gray-100">
                        <button type="button" class="btn bg-gray-100 text-gray-700 px-6 py-3 rounded-xl shadow-lg hover:bg-gray-200 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold" onclick="showStep(1)">
                            <i class="fas fa-arrow-left mr-2"></i> Previous
                        </button>
                        <button type="button" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold" onclick="showStep(3)">
                            Next: Add Chapters 
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Chapters -->
            <div id="step3-content" class="step-content">
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="flex justify-between items-center mb-8">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-red-500 to-red-600 p-3 rounded-xl shadow-lg">
                                <i class="fas fa-book-open text-white text-xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold ml-4 bg-gradient-to-r from-red-600 to-red-700 bg-clip-text text-transparent">
                                Course Chapters
                            </h2>
                        </div>
                    </div>
                    
                    <div id="chaptersContainer" class="mb-8">
                        <!-- Chapters will be dynamically rendered here -->
                    </div>
                    
                    <div class="flex justify-between pt-6 border-t border-gray-100">
                        <button type="button" class="btn bg-gray-100 text-gray-700 px-6 py-3 rounded-xl shadow-lg hover:bg-gray-200 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold" onclick="showStep(2)">
                            <i class="fas fa-arrow-left mr-2"></i> Previous
                        </button>
                        <button type="button" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold" onclick="showStep(4)">
                            Next: Add Quizzes 
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 4: Quizzes -->
            <div id="step4-content" class="step-content">
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="flex justify-between items-center mb-8">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-red-500 to-red-600 p-3 rounded-xl shadow-lg">
                                <i class="fas fa-question-circle text-white text-xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold ml-4 bg-gradient-to-r from-red-600 to-red-700 bg-clip-text text-transparent">
                                Course Quizzes
                            </h2>
                        </div>
                        <button type="button" id="addQuizBtn" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                            <i class="fas fa-plus mr-2"></i> Add Quiz
                        </button>
                    </div>
                    
                    <div id="quizzesContainer" class="mb-8">
                        <!-- Quizzes will be dynamically rendered here -->
                    </div>
                    
                    <div class="flex justify-between pt-6 border-t border-gray-100">
                        <button type="button" class="btn bg-gray-100 text-gray-700 px-6 py-3 rounded-xl shadow-lg hover:bg-gray-200 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold" onclick="showStep(3)">
                            <i class="fas fa-arrow-left mr-2"></i> Previous
                        </button>
                        <div class="flex space-x-4">
                            <button type="button" id="saveDraft" class="btn bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-6 py-3 rounded-xl shadow-lg hover:from-yellow-600 hover:to-yellow-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                                <i class="fas fa-save mr-2"></i> Save as Draft
                            </button>
                            <button type="button" id="publishModule" class="btn bg-gradient-to-r from-green-500 to-green-600 text-white px-8 py-3 rounded-xl shadow-lg hover:from-green-600 hover:to-green-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                                <i class="fas fa-check mr-2"></i> Publish Module
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/editor-shared.js"></script>
<script>
    // Make course data available globally for JavaScript
    window.courseId = <?php echo json_encode($course_id); ?>;
    window.mode = <?php echo json_encode($mode); ?>;
    
    // Real-time clock for Philippines timezone
    function updateRealTimeClock() {
        const now = new Date();
        const philippinesTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
        
        const timeOptions = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Manila'
        };
        
        const dateOptions = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: 'Asia/Manila'
        };
        
        const timeElement = document.getElementById('current-time');
        const dateElement = document.getElementById('current-date');
        
        if (timeElement) {
            timeElement.textContent = philippinesTime.toLocaleTimeString('en-US', timeOptions) + ' (PHT)';
        }
        
        if (dateElement) {
            dateElement.textContent = philippinesTime.toLocaleDateString('en-US', dateOptions);
        }
    }

    // Initialize real-time clock
    document.addEventListener('DOMContentLoaded', function() {
        updateRealTimeClock();
        setInterval(updateRealTimeClock, 1000); // Update every second
        
        // Initialize unsaved changes monitoring
        initializeUnsavedChangesSystem();
        
        // Mark as saved initially since we're loading existing course data
        // This prevents the modal from showing when there are no actual changes
        setTimeout(() => {
            if (typeof markAsSaved === 'function') {
                markAsSaved();
            }
        }, 1000); // Small delay to ensure all form data is loaded
    });
    
    // Unsaved Changes System
    let hasUnsavedChanges = false;
    let isNavigatingAway = false;
    let navigationType = null;
    let navigationTarget = null;
    
    function initializeUnsavedChangesSystem() {
        // Monitor form changes
        initializeFormMonitoring();
        
        // Set up navigation handlers
        setupNavigationHandlers();
        
        // Set up beforeunload handler
        setupBeforeUnloadHandler();
    }
    
    function initializeFormMonitoring() {
        // Monitor all form inputs
        const formInputs = document.querySelectorAll('input, textarea, select');
        formInputs.forEach(input => {
            input.addEventListener('input', markAsChanged);
            input.addEventListener('change', markAsChanged);
        });
        
        // Monitor TinyMCE editors
        if (typeof tinymce !== 'undefined') {
            tinymce.on('AddEditor', function(e) {
                e.editor.on('change', markAsChanged);
            });
        }
        
        // Monitor dynamic content changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if new form elements were added
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            const newInputs = node.querySelectorAll ? node.querySelectorAll('input, textarea, select') : [];
                            newInputs.forEach(input => {
                                input.addEventListener('input', markAsChanged);
                                input.addEventListener('change', markAsChanged);
                            });
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    function markAsChanged() {
        hasUnsavedChanges = true;
    }
    
    function markAsSaved() {
        hasUnsavedChanges = false;
    }
    
    function hasFormChanges() {
        // Only return true if we have explicitly marked changes or there are unsaved changes
        // For course editor, we don't want to trigger on pre-populated data
        return hasUnsavedChanges;
    }
    
    function setupNavigationHandlers() {
        // Intercept browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            if (hasFormChanges() && !isNavigatingAway) {
                history.pushState(null, null, window.location.href);
                navigationType = 'back';
                navigationTarget = 'teacher.php';
                showBrowserNavigationModal();
            }
        });
        
        // Initialize history state for popstate detection
        if (!window.historyInitialized) {
            history.pushState(null, null, window.location.href);
            window.historyInitialized = true;
        }
        
        // Intercept page refresh attempts
        document.addEventListener('keydown', function(e) {
            if ((e.key === 'F5') || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'R')) {
                if (hasFormChanges() && !isNavigatingAway) {
                    e.preventDefault();
                    navigationType = 'refresh';
                    navigationTarget = window.location.href;
                    showBrowserNavigationModal();
                }
            }
        });
        
        // Add click handlers to navigation links
        const navLinks = document.querySelectorAll('.nav-link, a[href]');
        navLinks.forEach(link => {
            if (link.type === 'submit' || link.href.includes('#') || link.href.includes('javascript:')) return;
            
            link.addEventListener('click', function(e) {
                if (hasFormChanges()) {
                    e.preventDefault();
                    const targetUrl = this.href;
                    
                    showUnsavedChangesModal(() => {
                        setNavigatingAway(true);
                        window.location.href = targetUrl;
                    }, false);
                }
            });
        });
    }
    
    function setupBeforeUnloadHandler() {
        window.addEventListener('beforeunload', function(e) {
            try {
                if (window.__suppressBeforeUnload) {
                    return undefined;
                }
            } catch (ignore) {}

            if (hasFormChanges() && !isNavigatingAway) {
                navigationType = 'close';
                navigationTarget = null;
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    }
    
    function setNavigatingAway(flag) {
        isNavigatingAway = flag;
        if (flag) {
            setTimeout(() => {
                isNavigatingAway = false;
            }, 500);
        }
    }
    
    function showBrowserNavigationModal() {
        if (document.getElementById('unsavedChangesModal').classList.contains('hidden') === false) {
            return;
        }
        
        showUnsavedChangesModal(() => {
            setNavigatingAway(true);
            
            if (navigationType === 'back') {
                window.location.href = navigationTarget || 'teacher.php';
            } else if (navigationType === 'refresh') {
                try { window.__suppressBeforeUnload = true; } catch (e) {}
                window.location.reload();
            } else if (navigationType === 'close') {
                return;
            } else {
                window.location.href = 'teacher.php';
            }
            
            navigationType = null;
            navigationTarget = null;
            
            setTimeout(() => {
                isNavigatingAway = false;
            }, 100);
        }, true);
    }
    
    function showUnsavedChangesModal(callback, isBrowserNav = false) {
        const modal = document.getElementById('unsavedChangesModal');
        const modalContent = document.getElementById('unsavedModalContent');
        const messageElement = document.getElementById('unsavedChangesMessage');
        
        // Update message based on navigation type
        if (isBrowserNav) {
            messageElement.textContent = 'You\'re about to leave this page';
        } else {
            messageElement.textContent = 'Your work might be lost';
        }
        
        modal.classList.remove('hidden');
        
        // Animate in
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
        
        // Set up event listeners
        document.getElementById('stayOnPageBtn').onclick = () => {
            hideUnsavedChangesModal();
        };
        
        document.getElementById('leaveAnywayBtn').onclick = () => {
            hideUnsavedChangesModal();
            // Show the confirmation modal instead of directly calling callback
            showLeaveAnywayConfirmation(callback);
        };
        
        // Close on backdrop click
        modal.onclick = (e) => {
            if (e.target === modal) {
                hideUnsavedChangesModal();
            }
        };
        
        // Focus on stay button for accessibility
        document.getElementById('stayOnPageBtn').focus();
        
        // Handle keyboard navigation
        modal.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideUnsavedChangesModal();
            } else if (e.key === 'Enter' && e.target.id === 'stayOnPageBtn') {
                hideUnsavedChangesModal();
            }
        });
    }
    
    function hideUnsavedChangesModal() {
        const modal = document.getElementById('unsavedChangesModal');
        const modalContent = document.getElementById('unsavedModalContent');
        
        modalContent.classList.add('scale-95', 'opacity-0');
        modalContent.classList.remove('scale-100', 'opacity-100');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    function showLeaveAnywayConfirmation(callback) {
        const modal = document.getElementById('leaveAnywayModal');
        const modalContent = document.getElementById('leaveAnywayModalContent');
        
        modal.classList.remove('hidden');
        
        // Animate in
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
        
        // Set up event listeners
        document.getElementById('cancelLeaveBtn').onclick = () => {
            hideLeaveAnywayModal();
            // Show the original modal again
            setTimeout(() => {
                showUnsavedChangesModal(callback);
            }, 300);
        };
        
        document.getElementById('confirmLeaveBtn').onclick = () => {
            hideLeaveAnywayModal();
            if (callback) callback();
        };
        
        // Close on backdrop click
        modal.onclick = (e) => {
            if (e.target === modal) {
                hideLeaveAnywayModal();
                // Show the original modal again
                setTimeout(() => {
                    showUnsavedChangesModal(callback);
                }, 300);
            }
        };
        
        // Focus on cancel button for safety
        document.getElementById('cancelLeaveBtn').focus();
        
        // Handle keyboard navigation
        modal.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLeaveAnywayModal();
                // Show the original modal again
                setTimeout(() => {
                    showUnsavedChangesModal(callback);
                }, 300);
            }
        });
    }
    
    function hideLeaveAnywayModal() {
        const modal = document.getElementById('leaveAnywayModal');
        const modalContent = document.getElementById('leaveAnywayModalContent');
        
        modalContent.classList.add('scale-95', 'opacity-0');
        modalContent.classList.remove('scale-100', 'opacity-100');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // Make functions available globally for integration with existing code
    window.markAsSaved = markAsSaved;
    window.markAsChanged = markAsChanged;
    window.hasFormChanges = hasFormChanges;
</script>
<script src="js/teacher_course_editor.js"></script>
<!-- Session Timeout Manager -->
<script src="js/session_timeout.js"></script>
</body>
</html> 