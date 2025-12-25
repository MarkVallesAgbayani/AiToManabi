<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/rbac_helper.php';
require_once 'includes/teacher_profile_functions.php';
require_once 'audit_logger.php';

// Validate that the current user has teacher role and exists in database
$teacher_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    // User doesn't exist, isn't a teacher, or isn't active - clear session and redirect
    session_destroy();
    header("Location: ../login.php?error=invalid_session");
    exit();
}

// Check if user has permission to create new modules
if (!hasPermission($pdo, $_SESSION['user_id'], 'create_new_module')) {
    header("Location: teacher.php?error=access_denied");
    exit();
}

// Get teacher profile data
$teacher_profile = getTeacherProfile($pdo, $_SESSION['user_id']);

// Get all user permissions to check for hybrid status
$stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$all_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if teacher has any admin permissions (making them hybrid)
$admin_permissions = ['nav_user_management', 'nav_reports', 'nav_payments', 'nav_course_management', 'nav_content_management', 'nav_users'];
$user_admin_permissions = array_intersect($all_permissions, $admin_permissions);
$is_hybrid = !empty($user_admin_permissions);

// Initialize variables
$module_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = $module_id > 0 ? 'edit' : 'create';
$module = null;
$sections = [];
$section_id_map = []; // Initialize mapping array for section IDs
$error_message = "";

// Fetch categories for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch course categories
$stmt = $pdo->prepare("SELECT id, name FROM course_category ORDER BY name");
$stmt->execute();
$courseCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Handle file upload
function handleFileUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    try {
        $upload_dir = __DIR__ . '/../uploads/course_images/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        // File validation
        $file_info = pathinfo($file['name']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!isset($file_info['extension']) || !in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            throw new Exception('Invalid file extension. Only JPG, PNG and GIF are allowed.');
        }
        
        // Generate unique filename
        $new_filename = uniqid('module_') . '.' . strtolower($file_info['extension']);
        $upload_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return $new_filename;
    } catch (Exception $e) {
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        throw $e;
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

// Handle POST request for module creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Process form data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $course_category_id = isset($_POST['course_category_id']) ? (int)$_POST['course_category_id'] : null;
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'draft';
        $teacher_id = (int)$_SESSION['user_id'];
        
        // Validate required fields
        if (empty($title)) {
            throw new Exception('Module title is required');
        }
        
        if (empty($course_category_id)) {
            throw new Exception('Course category is required');
        }
        
        // Process file upload
        $image_path = null;
        if (isset($_FILES['module_image'])) {
            $image_path = handleFileUpload($_FILES['module_image']);
        }
        
        // Ensure that description is handled properly, in case it's NULL or empty
        $description = !empty($description) ? $description : null;
        
        // Set the 'published_at' value based on the status
        $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
        
        // Prepare the SQL statement
        $sql = "INSERT INTO courses (
            title, description, category_id, price, status,
            teacher_id, is_published, created_at, updated_at, image_path, published_at, course_category_id
        ) VALUES (
            :title, :description, :category_id, :price, :status,
            :teacher_id, :is_published, :created_at, :updated_at, :image_path, :published_at, :course_category_id
        )";
        
        // Prepare the statement
        $stmt = $pdo->prepare($sql);
        
        // Execute the statement with the values
        try {
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':category_id' => $category_id,
                ':price' => $price,
                ':status' => $status,
                ':teacher_id' => $teacher_id,
                ':is_published' => ($status === 'published' ? 1 : 0),
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
                ':image_path' => $image_path,
                ':published_at' => $published_at,
                ':course_category_id' => $course_category_id
            ]);
        } catch (PDOException $e) {
            // Log detailed error information
            error_log("Error inserting course: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Teacher ID: " . $teacher_id);
            
            // Check if it's a foreign key constraint error for teacher_id
            if (strpos($e->getMessage(), 'teacher_id') !== false) {
                throw new Exception("Invalid teacher ID. Please log out and log back in.");
            }
            throw $e;
        }
        
        $module_id = $pdo->lastInsertId();
        
        // Debug logging
        error_log("Course creation result - module_id: " . $module_id);
        error_log("Teacher ID used: " . $teacher_id);
        
        // Validate that course was created successfully
        if (!$module_id || $module_id <= 0) {
            throw new Exception("Failed to create course. Course ID is: " . $module_id);
        }
        
        // Double-check that the course exists in database
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->execute([$module_id]);
        $courseExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$courseExists) {
            throw new Exception("Course was not properly saved to database. ID: " . $module_id);
        }
        
        // Commit the course creation first to ensure it's saved
        $pdo->commit();
        error_log("Course committed successfully with ID: $module_id");
        
        // Start a new transaction for sections and chapters
        $pdo->beginTransaction();
        
        // Process sections if any - NEW STRUCTURE: sections belong to courses
        if (isset($_POST['sections'])) {
            $sections = json_decode($_POST['sections'], true);
            error_log("Processing sections - count: " . (is_array($sections) ? count($sections) : 0));
            error_log("Sections data: " . print_r($sections, true));
            
            if (is_array($sections)) {
                foreach ($sections as $index => $section) {
                    error_log("Creating section $index with course_id: $module_id");
                    error_log("Section title: " . ($section['title'] ?? 'empty'));
                    
                    try {
                        // Create section directly linked to course (not to a chapter)
                        $stmt = $pdo->prepare("
                            INSERT INTO sections (course_id, title, description, order_index)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $module_id,
                            $section['title'],
                            $section['description'] ?? '',
                            $index
                        ]);
                        
                        $section_id = $pdo->lastInsertId();
                        error_log("Section created successfully with ID: $section_id");
                        
                        // Store the mapping of section ID to database ID
                        if (isset($section['id']) && strpos($section['id'], 'new_') === 0) {
                            $section_id_map[$section['id']] = $section_id;
                        }
                    } catch (PDOException $e) {
                        error_log("ERROR creating section: " . $e->getMessage());
                        error_log("Course ID being used: $module_id");
                        error_log("Section data: " . print_r($section, true));
                        throw $e;
                    }
                }
            }
        }
        
        // Process chapters if any
        if (isset($_POST['chapters'])) {
            $chapters = json_decode($_POST['chapters'], true);
            error_log("Processing chapters - count: " . (is_array($chapters) ? count($chapters) : 0));
            error_log("Chapters data: " . print_r($chapters, true));
            
            if (is_array($chapters)) {
                foreach ($chapters as $index => $chapter) {
                    error_log("Creating chapter: " . ($chapter['title'] ?? 'empty'));
                    
                    try {
                        // Get the actual section ID from mapping or use directly
                        $section_db_id = null;
                        if (isset($chapter['section_id'])) {
                            if (isset($section_id_map[$chapter['section_id']])) {
                                $section_db_id = $section_id_map[$chapter['section_id']];
                            } else {
                                // If it's a numeric ID, use it directly
                                $section_db_id = is_numeric($chapter['section_id']) ? (int)$chapter['section_id'] : null;
                            }
                        }
                        
                        // Handle video file upload if present
                        $video_file_path = null;
                        if (isset($chapter['video_file_field']) && isset($_FILES[$chapter['video_file_field']])) {
                            $video_file_path = handleVideoUpload($_FILES[$chapter['video_file_field']]);
                        }
                        
                        // Determine video URL and type
                        $video_url = null;
                        $video_type = $chapter['video_type'] ?? null;
                        
                        if ($video_type === 'url') {
                            $video_url = $chapter['video_url'] ?? null;
                        } elseif ($video_type === 'upload' && $video_file_path) {
                            // For uploaded files, we don't set video_url, just video_file_path
                            $video_url = null;
                        }
                        
                        // Create chapter
                        $stmt = $pdo->prepare("
                            INSERT INTO chapters (section_id, title, content_type, content, video_url, video_type, video_file_path, order_index, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $section_db_id,
                            $chapter['title'],
                            $chapter['content_type'] ?? 'text',
                            $chapter['content'] ?? '',
                            $video_url,
                            $video_type,
                            $video_file_path,
                            $chapter['order_index'] ?? 0
                        ]);
                        
                        $chapter_id = $pdo->lastInsertId();
                        error_log("Chapter created successfully with ID: $chapter_id, video_type: $video_type, video_file_path: $video_file_path");
                        
                    } catch (PDOException $e) {
                        error_log("ERROR creating chapter: " . $e->getMessage());
                        error_log("Chapter data: " . print_r($chapter, true));
                        throw $e;
                    }
                }
            }
        }
        
        // Process quizzes if any
        if (isset($_POST['quizzes'])) {
            $quizzes = json_decode($_POST['quizzes'], true);
            error_log("Processing quizzes - count: " . (is_array($quizzes) ? count($quizzes) : 0));
            error_log("Quizzes data: " . print_r($quizzes, true));
            
            if (is_array($quizzes)) {
                foreach ($quizzes as $quiz) {
                    error_log("Creating quiz: " . ($quiz['title'] ?? 'empty'));
                    
                    try {
                        // Get the actual section ID from mapping or use directly
                        $section_db_id = null;
                        if (isset($quiz['section_id'])) {
                            if (isset($section_id_map[$quiz['section_id']])) {
                                $section_db_id = $section_id_map[$quiz['section_id']];
                            } else {
                                // If it's a numeric ID, use it directly
                                $section_db_id = is_numeric($quiz['section_id']) ? (int)$quiz['section_id'] : null;
                            }
                        }
                        
                        // Check if max_retakes column exists
                        $stmt = $pdo->prepare("SHOW COLUMNS FROM quizzes LIKE 'max_retakes'");
                        $stmt->execute();
                        $column_exists = $stmt->fetch();
                        
                        if ($column_exists) {
                            // Column exists, include it in the INSERT
                            $stmt = $pdo->prepare("
                            INSERT INTO quizzes (section_id, title, description, passing_score, total_points, max_retakes, order_index, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([
                                $section_db_id,
                                $quiz['title'],
                                $quiz['description'] ?? '',
                                $quiz['passing_score'] ?? 70,
                                $quiz['total_points'] ?? 0,
                                $quiz['max_retakes'] ?? 3,
                                $quiz['order_index'] ?? 0
                            ]);
                        } else {
                            // Column doesn't exist yet, use basic INSERT
                            $stmt = $pdo->prepare("
                                INSERT INTO quizzes (section_id, title, description, order_index, created_at, updated_at)
                                VALUES (?, ?, ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([
                                $section_db_id,
                                $quiz['title'],
                                $quiz['description'] ?? '',
                                $quiz['order_index'] ?? 0
                            ]);
                        }
                        
                        $quiz_id = $pdo->lastInsertId();
                        error_log("Quiz created successfully with ID: $quiz_id");
                        
                        // Process questions if any
                        if (isset($quiz['questions']) && is_array($quiz['questions'])) {
                            foreach ($quiz['questions'] as $question_index => $question) {
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
                                
                                // Create question with all possible fields
                                $stmt = $pdo->prepare("
                                    INSERT INTO quiz_questions (quiz_id, question_text, question_type, word_definition_pairs, translation_pairs, word, romaji, meaning, audio_url, accuracy_threshold, answers, score, order_index, created_at, updated_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                                ");
                                $stmt->execute([
                                    $quiz_id,
                                    $question['question'],
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
                                    $question_index
                                ]);
                                
                                $question_id = $pdo->lastInsertId();
                                error_log("Question created successfully with ID: $question_id");
                                
                                // Process choices if any
                                if (isset($question['choices']) && is_array($question['choices'])) {
                                    foreach ($question['choices'] as $choice_index => $choice) {
                                        $stmt = $pdo->prepare("
                                            INSERT INTO quiz_choices (question_id, choice_text, is_correct, order_index, created_at, updated_at)
                                            VALUES (?, ?, ?, ?, NOW(), NOW())
                                        ");
                                        $stmt->execute([
                                            $question_id,
                                            $choice['text'],
                                            $choice['is_correct'] ? 1 : 0,
                                            $choice_index
                                        ]);
                                    }
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("ERROR creating quiz: " . $e->getMessage());
                        error_log("Quiz data: " . print_r($quiz, true));
                        throw $e;
                    }
                }
            }
        }
        
        $pdo->commit();
        
        // Log audit entry for module save/publish
        try {
            $auditLogger = createAuditLogger($pdo);
            $teacher_username = $_SESSION['username'] ?? 'Unknown Teacher';
            
            if ($status === 'published') {
                $auditLogger->logEntry([
                    'user_id' => $_SESSION['user_id'],
                    'username' => $teacher_username,
                    'user_role' => 'teacher',
                    'action_type' => 'CREATE',
                    'action_description' => 'Created and published new module',
                    'resource_type' => 'Course',
                    'resource_id' => "Course ID: $module_id",
                    'resource_name' => $title,
                    'outcome' => 'Success',
                    'new_value' => "Module '$title' published successfully",
                    'context' => [
                        'module_id' => $module_id,
                        'module_title' => $title,
                        'status' => $status,
                        'sections_count' => isset($sections) ? count($sections) : 0,
                        'price' => $price,
                        'category_id' => $category_id,
                        'course_category_id' => $course_category_id
                    ]
                ]);
            } else {
                $auditLogger->logEntry([
                    'user_id' => $_SESSION['user_id'],
                    'username' => $teacher_username,
                    'user_role' => 'teacher',
                    'action_type' => 'CREATE',
                    'action_description' => 'Created module as draft',
                    'resource_type' => 'Course',
                    'resource_id' => "Course ID: $module_id",
                    'resource_name' => $title,
                    'outcome' => 'Success',
                    'new_value' => "Module '$title' saved as draft",
                    'context' => [
                        'module_id' => $module_id,
                        'module_title' => $title,
                        'status' => $status,
                        'sections_count' => isset($sections) ? count($sections) : 0,
                        'price' => $price,
                        'category_id' => $category_id,
                        'course_category_id' => $course_category_id
                    ]
                ]);
            }
        } catch (Exception $auditError) {
            // Don't fail the main operation if audit logging fails
            error_log("Audit logging error: " . $auditError->getMessage());
        }
        
        sendJsonResponse(true, 
            $status === 'published' ? 'Module published successfully' : 'Module saved as draft',
            ['module_id' => $module_id]
        );
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
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
    <title>Create Module - Japanese LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="js/question-type-manager.js"></script>
    <script src="js/teacher_create_module.js"></script>

    <script>
        // Make teacher ID available globally
        window.teacherId = <?php echo json_encode($_SESSION['user_id'] ?? 'default'); ?>;
        
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
                        sans: ['Inter', 'Noto Sans JP', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/question-type-manager.css" rel="stylesheet">
    <link href="css/question-answer-forms.css" rel="stylesheet">
    <link href="css/settings-teacher.css" rel="stylesheet">
    
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
        .content-area {
            display: none;
        }
        .content-area.active {
            display: block;
        }
        
        /* Dropdown transition styles */
        .dropdown-enter {
            transition: all 0.2s ease-in-out;
        }
        .dropdown-enter-start {
            opacity: 0;
            transform: translateY(-10px);
        }
        .dropdown-enter-end {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Navigation active state */
        .nav-link.active {
            background-color: #fff1f2 !important;
            color: #be123c !important;
        }
            display: block;
        }
        .nav-link.active,
        .nav-link.bg-primary-50 {
            background-color: #fff1f2 !important;
            color: #be123c !important;
        }
        /* Override hardcoded red styling when Alpine.js is active */
        .nav-link[data-page].bg-primary-50 {
            background-color: #fff1f2 !important;
            color: #be123c !important;
        }
        /* Force red theme for all primary colors */
        .bg-primary-50 {
            background-color: #fff1f2 !important;
        }
        .text-primary-700 {
            color: #be123c !important;
        }
        .bg-primary-600 {
            background-color: #e11d48 !important;
        }
        .hover\:bg-primary-700:hover {
            background-color: #be123c !important;
        }
        /* Override any blue styling that might be applied */
        .btn-primary {
            background-color: #e11d48 !important;
            color: white !important;
        }
        .btn-primary:hover {
            background-color: #be123c !important;
        }
        /* Ensure navigation stays red */
        .nav-link.active,
        .nav-link.bg-primary-50,
        .nav-link[data-page].bg-primary-50 {
            background-color: #fff1f2 !important;
            color: #be123c !important;
        }
        /* Step navigation buttons - ensure they're red */
        #nextToStep2, #nextToStep3, #nextToStep4,
        #backToStep1, #backToStep2, #backToStep3,
        #addSectionBtn, #addQuizBtn,
        #saveDraft, #publishModule {
            background-color: #e11d48 !important;
            color: white !important;
        }
        #nextToStep2:hover, #nextToStep3:hover, #nextToStep4:hover,
        #addSectionBtn:hover, #addQuizBtn:hover,
        #publishModule:hover {
            background-color: #be123c !important;
        }
        /* Secondary buttons (Previous buttons) */
        #backToStep1, #backToStep2, #backToStep3 {
            background-color: #6b7280 !important;
            color: white !important;
        }
        #backToStep1:hover, #backToStep2:hover, #backToStep3:hover {
            background-color: #4b5563 !important;
        }
        /* Save Draft button - keep yellow */
        #saveDraft {
            background-color: #f59e0b !important;
            color: white !important;
        }
        #saveDraft:hover {
            background-color: #d97706 !important;
        }
        /* Ensure all primary buttons are red - ULTRA SPECIFIC */
        .btn.btn-primary,
        button.btn-primary,
        input[type="button"].btn-primary,
        input[type="submit"].btn-primary,
        .btn-primary,
        button[class*="btn-primary"],
        input[class*="btn-primary"] {
            background-color: #e11d48 !important;
            color: white !important;
            border-color: #e11d48 !important;
            background-image: none !important;
        }
        .btn.btn-primary:hover,
        button.btn-primary:hover,
        input[type="button"].btn-primary:hover,
        input[type="submit"].btn-primary:hover,
        .btn-primary:hover,
        button[class*="btn-primary"]:hover,
        input[class*="btn-primary"]:hover {
            background-color: #be123c !important;
            border-color: #be123c !important;
            background-image: none !important;
        }
        /* Override any Tailwind blue classes */
        .bg-blue-600,
        .hover\:bg-blue-700:hover,
        .bg-blue-500,
        .hover\:bg-blue-600:hover {
            background-color: #e11d48 !important;
            color: white !important;
        }
        .bg-blue-600:hover,
        .bg-blue-500:hover {
            background-color: #be123c !important;
        }
        /* Target specific step navigation buttons */
        #nextToStep2,
        #nextToStep3,
        #nextToStep4,
        #addSectionBtn,
        #addQuizBtn,
        #publishModule {
            background-color: #e11d48 !important;
            color: white !important;
            border-color: #e11d48 !important;
        }
        #nextToStep2:hover,
        #nextToStep3:hover,
        #nextToStep4:hover,
        #addSectionBtn:hover,
        #addQuizBtn:hover,
        #publishModule:hover {
            background-color: #be123c !important;
            border-color: #be123c !important;
        }
        /* Override any remaining blue styles with maximum specificity */
        button.btn.btn-primary,
        button[class*="btn-primary"],
        button[class*="btn"][class*="primary"] {
            background-color: #e11d48 !important;
            color: white !important;
            border-color: #e11d48 !important;
            background-image: none !important;
            background: #e11d48 !important;
        }
        button.btn.btn-primary:hover,
        button[class*="btn-primary"]:hover,
        button[class*="btn"][class*="primary"]:hover {
            background-color: #be123c !important;
            border-color: #be123c !important;
            background-image: none !important;
            background: #be123c !important;
        }
        /* Final override for any blue buttons */
        [style*="background-color: rgb(59, 130, 246)"],
        [style*="background-color: #3b82f6"],
        [style*="background-color: blue"] {
            background-color: #e11d48 !important;
            color: white !important;
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
        .step-indicator { display: flex; justify-content: center; align-items: center; margin-bottom: 2rem; }
        .step { display: flex; align-items: center; }
        .step-circle { width: 2.5rem; height: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.3s ease; }
        .step.active .step-circle { background-color: #e11d48; color: white; }
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
        
        /* Modern Confirmation Dialog Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        @keyframes scaleIn {
            from { 
                transform: scale(0.95); 
                opacity: 0; 
            }
            to { 
                transform: scale(1); 
                opacity: 1; 
            }
        }
        
        @keyframes scaleOut {
            from { 
                transform: scale(1); 
                opacity: 1; 
            }
            to { 
                transform: scale(0.95); 
                opacity: 0; 
            }
        }
        
        @keyframes slideInFromTop {
            from { 
                transform: translateY(-20px) scale(0.95); 
                opacity: 0; 
            }
            to { 
                transform: translateY(0) scale(1); 
                opacity: 1; 
            }
        }
        
        @keyframes slideOutToTop {
            from { 
                transform: translateY(0) scale(1); 
                opacity: 1; 
            }
            to { 
                transform: translateY(-20px) scale(0.95); 
                opacity: 0; 
            }
        }
        
        /* Modern Dialog Styles */
        .modern-confirm-dialog {
            backdrop-filter: blur(4px);
        }
        
        .modern-confirm-dialog #confirmDialog {
            animation: scaleIn 0.3s ease-out;
        }
        
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
            background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.3);
        }
        
        #stayOnPageBtn:hover, #confirmLeaveBtn:hover {
            background: linear-gradient(135deg, #be123c 0%, #9f1239 100%);
            box-shadow: 0 6px 20px rgba(225, 29, 72, 0.4);
            transform: translateY(-2px);
        }
        
        #leaveAnywayBtn, #cancelLeaveBtn {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        #leaveAnywayBtn:hover, #cancelLeaveBtn:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }
        
        /* Icon animations */
        .fa-exclamation-triangle, .fa-times-circle {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-white w-64 min-h-screen shadow-lg fixed h-full z-10">
            <div class="flex items-center justify-center h-16 border-b bg-gradient-to-r from-red-600 to-red-800 text-white">
                <span class="text-2xl font-bold">Teacher Portal</span>
            </div>
            
            <!-- Teacher Profile -->
            <?php echo renderTeacherSidebarProfile($teacher_profile, $is_hybrid); ?>

            <!-- Sidebar Navigation -->
            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['module_performance_analytics', 'student_progress_overview', 'teacher_dashboard_view_active_modules', 'teacher_dashboard_view_active_students', 'teacher_dashboard_view_completion_rate', 'teacher_dashboard_view_published_modules', 'teacher_dashboard_view_learning_analytics', 'teacher_dashboard_view_quick_actions', 'teacher_dashboard_view_recent_activities'])): ?>
            <nav class="p-4 flex flex-col h-[calc(100%-132px)]" x-data="{ studentDropdownOpen: false }">
                <div class="space-y-1">
                    <a href="teacher.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                    <?php endif; ?>

                     <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['unpublished_modules', 'edit_course_module', 'archived_course_module', 'courses'])): ?>
                    <a href="courses_available.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                       <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        Courses
                    </a>
                    <?php endif; ?>

                     <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['create_new_module', 'delete_level', 'edit_level', 'add_level', 'add_quiz'])): ?>
                    <a href="teacher_create_module.php" 
                       class="nav-link active bg-primary-50 text-primary-700 w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Module
                    </a>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['my_drafts', 'create_new_draft', 'archived_modules', 'published_modules', 'edit_modules'])): ?>
                    <a href="teacher_drafts.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        My Drafts
                    </a>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['archived', 'delete_permanently', 'restore_to_drafts'])): ?>
                    <a href="teacher_archive.php" 
                       class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-archive-restore-icon lucide-archive-restore"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg>
                        Archived
                    </a>
                    <?php endif; ?>

                    <!-- Student Management Dropdown -->
                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['student_profiles', 'progress_tracking', 'quiz_performance', 'engagement_monitoring', 'completion_reports'])): ?>
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
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['student_profiles', 'view_progress_button', 'view_profile_button', 'search_and_filter'])): ?>
                            <a href="Student Management/student_profiles.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Student Profiles
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['progress_tracking', 'export_progress', 'complete_modules', 'in_progress', 'average_progress', 'active_students', 'progress_distribution', 'module_completion', 'detailed_progress_tracking'])): ?>
                            <a href="Student Management/progress_tracking.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Progress Tracking
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['quiz_performance', 'filter_search', 'export_pdf_quiz', 'average_score', 'total_attempts', 'active_students_quiz', 'total_quiz_students', 'performance_trend', 'quiz_difficulty_analysis', 'top_performer', 'recent_quiz_attempt'])): ?>
                            <a href="Student Management/quiz_performance.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Quiz Performance
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['engagement_monitoring', 'filter_engagement_monitoring', 'export_pdf_engagement', 'login_frequency', 'drop_off_rate', 'average_enrollment_days', 'recent_enrollments', 'time_spent_learning', 'module_engagement', 'most_engaged_students', 'recent_enrollments_card'])): ?>
                            <a href="Student Management/engagement_monitoring.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Engagement Monitoring
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['completion_reports', 'filter_completion_reports', 'export_completion_reports', 'overall_completion_rate', 'average_progress_completion_reports', 'on_time_completions', 'delayed_completions', 'module_completion_breakdown', 'completion_timeline', 'completion_breakdown'])): ?>
                            <a href="Student Management/completion_reports.php" 
                               class="nav-link w-full flex items-center gap-3 px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Completion Reports
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (hasAnyPermission($pdo, $_SESSION['user_id'], ['preview_placement', 'placement_test', 'teacher_placement_test_create', 'teacher_placement_test_edit', 'teacher_placement_test_delete', 'teacher_placement_test_publish'])): ?>
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
                    <?php endif; ?>

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
                    <h1 class="text-2xl font-semibold text-gray-900 ml-64">Create New Module</h1>
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
                    <?php if (hasPermission($pdo, $_SESSION['user_id'], 'preview_module') && $module_id > 0): ?>
                    <a href="includes/preview_course.php?id=<?php echo $course_id; ?>&preview=true" target="_blank" 
                        class="absolute right-0 top-0 inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white text-sm rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Preview Module
                    </a>
                    <?php elseif (hasPermission($pdo, $_SESSION['user_id'], 'preview_module')): ?>
                    <div class="absolute right-0 top-0 inline-flex items-center px-4 py-2 bg-gray-400 text-white text-sm rounded-xl shadow-lg cursor-not-allowed opacity-60">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Save Module First
                    </div>
                    <?php endif; ?>
                    
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
                                <div class="step-circle w-12 h-12 bg-gray-200 text-gray-400 rounded-full flex items-center justify-center shadow-md">
                                    <i class="fas fa-layer-group text-lg"></i>
                                </div>
                                <div class="step-text ml-3">
                                    <div class="font-semibold text-gray-900">Sections</div>
                                    <div class="text-sm text-gray-500">Course structure</div>
                                </div>
                            </div>
                            <div class="step-line h-1 w-16 bg-gray-200 rounded-full"></div>
                            <div class="step flex items-center" id="step3">
                                <div class="step-circle w-12 h-12 bg-gray-200 text-gray-400 rounded-full flex items-center justify-center shadow-md">
                                    <i class="fas fa-book-open text-lg"></i>
                                </div>
                                <div class="step-text ml-3">
                                    <div class="font-semibold text-gray-900">Chapters</div>
                                    <div class="text-sm text-gray-500">Content creation</div>
                                </div>
                            </div>
                            <div class="step-line h-1 w-16 bg-gray-200 rounded-full"></div>
                            <div class="step flex items-center" id="step4">
                                <div class="step-circle w-12 h-12 bg-gray-200 text-gray-400 rounded-full flex items-center justify-center shadow-md">
                                    <i class="fas fa-question-circle text-lg"></i>
                                </div>
                                <div class="step-text ml-3">
                                    <div class="font-semibold text-gray-900">Quizzes</div>
                                    <div class="text-sm text-gray-500">Assessments</div>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Step 1: Module Information -->
                <div id="step1-content" class="step-content active">
                    <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                        <div class="flex items-center mb-8">
                            <div class="bg-gradient-to-r from-red-500 to-red-600 p-3 rounded-xl shadow-lg">
                                <i class="fas fa-info-circle text-white text-xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold ml-4 bg-gradient-to-r from-red-600 to-red-700 bg-clip-text text-transparent">
                                Module Information
                            </h2>
                        </div>
                        
                        <form id="moduleForm" method="POST" enctype="multipart/form-data">
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
                                        <input type="text" name="title" id="title" class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" required>
                                    </div>
                                    
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                            <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                                <i class="fas fa-align-left text-red-600 text-sm"></i>
                                            </div>
                                            Description
                                        </label>
                                        <textarea name="description" id="description" class="form-textarea w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200 resize-none" rows="4"></textarea>
                                    </div>
                                    
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                            <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                                <i class="fas fa-peso-sign text-red-600 text-sm"></i>
                                            </div>
                                            Price (₱)
                                        </label>
                                        <input type="number" name="price" id="price" class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" min="0" step="0.01">
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
                                            <select name="category_id" id="category_id" class="form-select flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" required>
                                                <option value="">Select Level</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category['id']); ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'add_level')): ?>
                                            <button type="button" id="addCategoryBtn" class="btn bg-gradient-to-r from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700 px-4 py-3 rounded-xl shadow-lg transform hover:scale-105 transition-all duration-200" title="Add Level">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'edit_level')): ?>
                                            <button type="button" id="editCategoryBtn" class="btn bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 px-4 py-3 rounded-xl shadow-lg transform hover:scale-105 transition-all duration-200" title="Edit Level">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'delete_level')): ?>
                                            <button type="button" id="deleteCategoryBtn" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 px-4 py-3 rounded-xl shadow-lg transform hover:scale-105 transition-all duration-200" title="Delete Level">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="group">
                                        <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                            <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                                                <i class="fas fa-tags text-red-600 text-sm"></i>
                                            </div>
                                            Course Category *
                                        </label>
                                        <select name="course_category_id" id="course_category_id" class="form-select w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200" required>
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
                                            <input type="file" name="module_image" id="module_image" class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-200 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100" accept="image/*">
                                            <p class="text-xs text-gray-500 mt-2 flex items-center">
                                                <i class="fas fa-info-circle text-gray-400 mr-1"></i>
                                                Recommended: 800x450px, Max: 2MB
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                        <div class="flex justify-end mt-8 pt-6 border-t border-gray-100">
                            <button type="button" id="nextToStep2" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
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
                            <div class="text-gray-500 text-center py-12 border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50">
                                <div class="bg-red-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-folder-plus text-3xl text-red-600"></i>
                                </div>
                                <p class="text-xl font-semibold text-gray-700 mb-2">No sections added yet</p>
                                <p class="text-gray-500">Click "Add Section" to create your first section</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-between pt-6 border-t border-gray-100">
                            <button type="button" id="backToStep1" class="btn bg-gray-100 text-gray-700 px-6 py-3 rounded-xl shadow-lg hover:bg-gray-200 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                                <i class="fas fa-arrow-left mr-2"></i> Previous
                            </button>
                            <button type="button" id="nextToStep3" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
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
                            </h2>
                        </div>
                        
                        <div id="chaptersContainer" class="mb-8">
                            <!-- Chapters will be dynamically rendered here -->
                        </div>
                        
                        <div class="flex justify-between pt-6 border-t border-gray-100">
                            <button type="button" id="backToStep2" class="btn bg-gray-100 text-gray-700 px-6 py-3 rounded-xl shadow-lg hover:bg-gray-200 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                                <i class="fas fa-arrow-left mr-2"></i> Previous
                            </button>
                            <button type="button" id="nextToStep4" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
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
                            <?php if (hasPermission($pdo, $_SESSION['user_id'], 'add_quiz')): ?>
                            <button type="button" id="addQuizBtn" class="btn bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl shadow-lg hover:from-red-600 hover:to-red-700 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
                                <i class="fas fa-plus mr-2"></i> Add Quiz
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <div id="quizzesContainer" class="mb-8">
                            <!-- Quizzes will be dynamically rendered here -->
                        </div>
                        
                        <div class="flex justify-between pt-6 border-t border-gray-100">
                            <button type="button" id="backToStep3" class="btn bg-gray-100 text-gray-700 px-6 py-3 rounded-xl shadow-lg hover:bg-gray-200 transform hover:scale-105 transition-all duration-200 flex items-center font-semibold">
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


    </div>
    
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

    <script src="js/settings-teacher.js"></script>
    <script>
        // Unsaved Changes Management
        let hasUnsavedChanges = false;
        let formSubmitted = false;
        let originalFormData = {};
        
        // Initialize form monitoring
        function initializeFormMonitoring() {
            // Store original form data
            const form = document.getElementById('moduleForm');
            if (form) {
                const formData = new FormData(form);
                originalFormData = Object.fromEntries(formData.entries());
                
                // Add change listeners to all form inputs
                const inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.addEventListener('input', markAsChanged);
                    input.addEventListener('change', markAsChanged);
                });
            }
            
            // Monitor sections, chapters, and quizzes containers for changes
            const containers = ['sectionsContainer', 'chaptersContainer', 'quizzesContainer'];
            containers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    // Use MutationObserver to detect dynamic content changes
                    const observer = new MutationObserver(() => {
                        markAsChanged();
                    });
                    observer.observe(container, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        characterData: true
                    });
                }
            });
        }
        
        function markAsChanged() {
            hasUnsavedChanges = true;
        }
        
        function markAsSaved() {
            hasUnsavedChanges = false;
            formSubmitted = true;
        }
        
        // Check if form has actual changes
        function hasFormChanges() {
            if (formSubmitted) return false;
            
            const form = document.getElementById('moduleForm');
            if (!form) return false;
            
            const currentFormData = new FormData(form);
            const currentData = Object.fromEntries(currentFormData.entries());
            
            // Check basic form fields
            for (const [key, value] of Object.entries(currentData)) {
                if (originalFormData[key] !== value && value.trim() !== '') {
                    return true;
                }
            }
            
            // Check if there are any sections, chapters, or quizzes
            const sectionsCount = document.querySelectorAll('#sectionsContainer .section-item').length;
            const chaptersCount = document.querySelectorAll('#chaptersContainer .chapter-item').length;
            const quizzesCount = document.querySelectorAll('#quizzesContainer .quiz-item').length;
            
            return sectionsCount > 0 || chaptersCount > 0 || quizzesCount > 0;
        }
        
        // Show unsaved changes modal
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
        
        // Show leave anyway confirmation modal
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
        
        function hideUnsavedChangesModal() {
            const modal = document.getElementById('unsavedChangesModal');
            const modalContent = document.getElementById('unsavedModalContent');
            
            modalContent.classList.add('scale-95', 'opacity-0');
            modalContent.classList.remove('scale-100', 'opacity-100');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
        
        // Enhanced browser navigation handling with modern dialog
        let isNavigatingAway = false;
        let isBrowserNavigation = false;
        
        // Handle beforeunload for tab close and other navigation
        window.addEventListener('beforeunload', function(e) {
            try {
                if (window.__suppressBeforeUnload) {
                    // Temporary suppression for programmatic updates
                    return undefined;
                }
            } catch (ignore) {}

            if (hasFormChanges() && !isNavigatingAway) {
                // Set navigation type for tab close
                navigationType = 'close';
                navigationTarget = null;
                
                // Prevent the browser dialog - we handle everything with our custom modal
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
            // If no changes or already navigating away, allow normal behavior
        });
        
        // Track when user is navigating via our custom modal
        function setNavigatingAway(flag) {
            isNavigatingAway = flag;
            if (flag) {
                // Reset browser navigation flag after a short delay
                setTimeout(() => {
                    isBrowserNavigation = false;
                }, 500);
            }
        }
        
        // Show modern modal for browser navigation (back/forward/refresh/close)
        function showBrowserNavigationModal() {
            // Don't show if we already have a modal open
            if (document.getElementById('unsavedChangesModal').classList.contains('hidden') === false) {
                return;
            }
            
            // Show our modern modal with browser-specific messaging immediately
            showUnsavedChangesModal(() => {
                setNavigatingAway(true);
                
                // Handle different navigation types
                if (navigationType === 'back') {
                    // Navigate back to teacher dashboard
                    window.location.href = navigationTarget || 'teacher.php';
                } else if (navigationType === 'refresh') {
                    // Refresh the current page
                    try { window.__suppressBeforeUnload = true; } catch (e) {}
                    window.location.reload();
                } else if (navigationType === 'close') {
                    // For tab close, just allow the navigation to proceed
                    // The browser will handle the actual closing
                    return;
                } else {
                    // Default fallback - go back to teacher dashboard
                    window.location.href = 'teacher.php';
                }
                
                // Reset navigation tracking
                navigationType = null;
                navigationTarget = null;
                
                setTimeout(() => {
                    isBrowserNavigation = false;
                }, 100);
            }, true); // Pass true to indicate this is browser navigation
        }
        
        // Track navigation type and target
        let navigationType = null;
        let navigationTarget = null;
        
        // Intercept browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            if (hasFormChanges() && !isNavigatingAway) {
                // Push state back to prevent navigation
                history.pushState(null, null, window.location.href);
                
                // Set navigation type and target
                navigationType = 'back';
                navigationTarget = 'teacher.php';
                
                // Show our modern modal instead
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
            // Intercept F5, Ctrl+R, Ctrl+F5
            if ((e.key === 'F5') || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'R')) {
                if (hasFormChanges() && !isNavigatingAway) {
                    e.preventDefault();
                    
                    // Set navigation type and target
                    navigationType = 'refresh';
                    navigationTarget = window.location.href;
                    
                    showBrowserNavigationModal();
                }
            }
        });
        
        // Handle navigation links
        document.addEventListener('DOMContentLoaded', function() {
            initializeFormMonitoring();
            
            // Add click handlers to all navigation links
            const navLinks = document.querySelectorAll('.nav-link, a[href]');
            navLinks.forEach(link => {
                // Skip if it's a form submission button, same-page anchor, or JavaScript links
                if (link.type === 'submit' || 
                    link.href.includes('#') || 
                    link.href === 'javascript:void(0)' ||
                    link.onclick ||
                    link.classList.contains('no-unsaved-warning')) {
                    return;
                }
                
                // Skip if it's already been processed
                if (link.hasAttribute('data-unsaved-processed')) return;
                link.setAttribute('data-unsaved-processed', 'true');
                
                link.addEventListener('click', function(e) {
                    if (hasFormChanges()) {
                        e.preventDefault();
                        const targetUrl = this.href;
                        
                        showUnsavedChangesModal(() => {
                            // Set flag to prevent browser dialog from showing
                            setNavigatingAway(true);
                            // Navigate after a short delay to ensure flag is set
                            setTimeout(() => {
                                window.location.href = targetUrl;
                            }, 100);
                        });
                    }
                });
            });
            
            // Monitor form submission to disable warning
            const form = document.getElementById('moduleForm');
            if (form) {
                form.addEventListener('submit', function() {
                    markAsSaved();
                });
            }
            
            // Monitor save/publish buttons
            const saveButtons = document.querySelectorAll('#saveDraft, #publishModule');
            saveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    markAsSaved();
                });
            });
        });
        
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
        });
    </script>
<!-- Session Timeout Manager -->
<script src="js/session_timeout.js"></script>
</body>
</html>
