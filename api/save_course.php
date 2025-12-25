<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/database.php';

try {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['action']) || $_POST['action'] !== 'save_course') {
        throw new Exception('Invalid action');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Get course data
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category'] ?? null;
    $price = $_POST['price'] ?? 0;
    $status = $_POST['status'] ?? 'draft';
    $teacher_id = $_SESSION['user_id'];
    $course_id = $_POST['course_id'] ?? null;

    // Debug log
    error_log("Received course data: " . print_r($_POST, true));

    // Validate required fields
    if (empty($title)) {
        throw new Exception('Course title is required');
    }

    // Handle course image upload
    $image_path = null;
    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
        try {
            $upload_dir = __DIR__ . '/../uploads/course_images/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            // Validate file type
            $file_extension = strtolower(pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_extension, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG and GIF are allowed.');
            }
            
            $new_filename = uniqid('course_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_path)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            $image_path = $new_filename;
        } catch (Exception $e) {
            error_log("Course image upload error: " . $e->getMessage());
            throw new Exception('Failed to upload course image: ' . $e->getMessage());
        }
    }

    // Save or update course
    if ($course_id) {
        // Update existing course
        $sql = "UPDATE courses SET 
                title = ?, 
                description = ?, 
                category_id = ?, 
                price = ?, 
                status = ?, 
                updated_at = NOW()";
        $params = [$title, $description, $category_id, $price, $status];

        if ($image_path) {
            $sql .= ", image_path = ?";
            $params[] = $image_path;
        }

        $sql .= " WHERE id = ? AND teacher_id = ?";
        $params[] = $course_id;
        $params[] = $teacher_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Check if update was successful
        if ($stmt->rowCount() === 0) {
            throw new Exception('Course not found or you do not have permission to edit it');
        }
    } else {
        // Create new course
        $stmt = $pdo->prepare("
            INSERT INTO courses (title, description, category_id, price, status, teacher_id, image_path, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$title, $description, $category_id, $price, $status, $teacher_id, $image_path]);
        $course_id = $pdo->lastInsertId();
    }

    // Debug log
    error_log("Course saved with ID: " . $course_id);

    // Process sections
    $sections = json_decode($_POST['sections'] ?? '[]', true);
    error_log("Processing sections: " . print_r($sections, true));

    foreach ($sections as $section) {
        if (isset($section['id']) && strpos($section['id'], 'new_') === 0) {
            // Insert new section
            $stmt = $pdo->prepare("
                INSERT INTO sections (course_id, title, description, order_index)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$course_id, $section['title'], $section['description'], $section['order_index']]);
            $section_id = $pdo->lastInsertId();
            error_log("Created new section with ID: " . $section_id);
        } else {
            // Update existing section
            $stmt = $pdo->prepare("
                UPDATE sections 
                SET title = ?, description = ?, order_index = ?
                WHERE id = ? AND course_id = ?
            ");
            $stmt->execute([$section['title'], $section['description'], $section['order_index'], $section['id'], $course_id]);
            $section_id = $section['id'];
            error_log("Updated section with ID: " . $section_id);
        }
    }

    // Process chapters
    $chapters = json_decode($_POST['chapters'] ?? '[]', true);
    error_log("Processing chapters: " . print_r($chapters, true));

    foreach ($chapters as $chapter) {
        if (isset($chapter['id']) && strpos($chapter['id'], 'new_') === 0) {
            // Insert new chapter first
            $stmt = $pdo->prepare("
                INSERT INTO chapters (course_id, title, content, content_type, order_index)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $course_id, 
                $chapter['title'], 
                $chapter['content'] ?? '', 
                $chapter['content_type'] ?? 'text',
                $chapter['order_index']
            ]);
            $new_chapter_id = $pdo->lastInsertId();
            error_log("Created new chapter with ID: " . $new_chapter_id);
            
            // Now create or update the section to link to this chapter
            if (isset($chapter['section_title']) && !empty($chapter['section_title'])) {
                // Check if section already exists for this chapter
                $stmt = $pdo->prepare("SELECT id FROM sections WHERE chapter_id = ?");
                $stmt->execute([$new_chapter_id]);
                $existing_section = $stmt->fetch();
                
                if (!$existing_section) {
                    // Create new section for this chapter
                    $stmt = $pdo->prepare("
                        INSERT INTO sections (chapter_id, title, content, order_index)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $new_chapter_id,
                        $chapter['section_title'],
                        $chapter['section_content'] ?? '',
                        $chapter['order_index']
                    ]);
                    error_log("Created new section for chapter: " . $new_chapter_id);
                }
            }
        } else {
            // Update existing chapter
            $stmt = $pdo->prepare("
                UPDATE chapters 
                SET title = ?, content = ?, content_type = ?, order_index = ?
                WHERE id = ? AND course_id = ?
            ");
            $stmt->execute([
                $chapter['title'],
                $chapter['content'] ?? '',
                $chapter['content_type'] ?? 'text',
                $chapter['order_index'], 
                $chapter['id'], 
                $course_id
            ]);
            error_log("Updated chapter with ID: " . $chapter['id']);
        }
    }

    // Process quizzes
    $quizzes = json_decode($_POST['quizzes'] ?? '[]', true);
    error_log("Processing quizzes: " . print_r($quizzes, true));

    foreach ($quizzes as $quiz) {
        if (isset($quiz['id']) && strpos($quiz['id'], 'new_') === 0) {
            // Insert new quiz
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (section_id, title, order_index)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$quiz['section_id'], $quiz['title'], $quiz['order_index']]);
            error_log("Created new quiz with ID: " . $pdo->lastInsertId());
        } else {
            // Update existing quiz
            $stmt = $pdo->prepare("
                UPDATE quizzes 
                SET order_index = ?
                WHERE id = ? AND section_id IN (SELECT id FROM sections WHERE course_id = ?)
            ");
            $stmt->execute([$quiz['order_index'], $quiz['id'], $course_id]);
            error_log("Updated quiz with ID: " . $quiz['id']);
        }
    }

    // Commit transaction
    $pdo->commit();
    error_log("Transaction committed successfully");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $status === 'published' ? 'Course published successfully' : 'Course saved as draft',
        'course_id' => $course_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error
    error_log("Error saving course: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving course: ' . $e->getMessage()
    ]);
} 