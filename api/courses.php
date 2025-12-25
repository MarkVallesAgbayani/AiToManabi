<?php
session_start();

// Add error handling to prevent HTML error output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

// Get course ID from URL if present
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Log the request for debugging
error_log("courses.php API called - Method: " . $_SERVER['REQUEST_METHOD'] . ", Course ID: " . $course_id . ", User ID: " . $_SESSION['user_id']);

try {
    // Check database connection
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $pdo->beginTransaction();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($course_id) {
                // Fetch specific course
                $stmt = $pdo->prepare("
                    SELECT * FROM courses 
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([$course_id, $_SESSION['user_id']]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$course) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Course not found', 'data' => null]);
                    exit();
                }

                // Fetch chapters (chapters belong to sections, not directly to courses)
                $chapters = [];
                try {
                    // Check if chapters table exists
                    $stmt = $pdo->query("SHOW TABLES LIKE 'chapters'");
                    if ($stmt->rowCount() > 0) {
                        // Check if sections table exists too
                        $stmt = $pdo->query("SHOW TABLES LIKE 'sections'");
                        if ($stmt->rowCount() > 0) {
                            $stmt = $pdo->prepare("
                                SELECT c.* FROM chapters c
                                JOIN sections s ON c.section_id = s.id
                                WHERE s.course_id = ? 
                                ORDER BY s.order_index, c.order_index
                            ");
                            $stmt->execute([$course_id]);
                            $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching chapters: " . $e->getMessage());
                    // Continue without chapters if table doesn't exist
                }

                // Fetch sections
                $sections = [];
                try {
                    // Check if sections table exists
                    $stmt = $pdo->query("SHOW TABLES LIKE 'sections'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT s.* FROM sections s
                            WHERE s.course_id = ?
                            ORDER BY s.order_index
                        ");
                        $stmt->execute([$course_id]);
                        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching sections: " . $e->getMessage());
                    // Continue without sections if table doesn't exist
                }

                // Fetch quizzes with questions and choices
                $quizzes = [];
                try {
                    // Check if quiz tables exist
                    $stmt = $pdo->query("SHOW TABLES LIKE 'quizzes'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $pdo->query("SHOW TABLES LIKE 'sections'");
                        if ($stmt->rowCount() > 0) {
                            $stmt = $pdo->prepare("
                                SELECT q.*, qq.id as question_id, qq.question_text, qq.question_type, qq.order_index as question_order,
                                       qc.id as choice_id, qc.choice_text, qc.is_correct
                                FROM quizzes q
                                JOIN sections s ON q.section_id = s.id
                                LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id
                                LEFT JOIN quiz_choices qc ON qq.id = qc.question_id
                                WHERE s.course_id = ?
                                ORDER BY s.order_index, q.order_index, qq.order_index, qc.order_index
                            ");
                            $stmt->execute([$course_id]);
                            $quizResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            // Organize quiz data
                            foreach ($quizResults as $row) {
                                if (!isset($quizzes[$row['id']])) {
                                    $quizzes[$row['id']] = [
                                        'id' => $row['id'],
                                        'title' => $row['title'],
                                        'description' => $row['description'],
                                        'section_id' => $row['section_id'],
                                        'time_limit' => $row['time_limit'],
                                        'passing_score' => $row['passing_score'],
                                        'max_retakes' => $row['max_retakes'],
                                        'order_index' => $row['order_index'],
                                        'questions' => []
                                    ];
                                }
                                
                                if ($row['question_id'] && !isset($quizzes[$row['id']]['questions'][$row['question_id']])) {
                                    $quizzes[$row['id']]['questions'][$row['question_id']] = [
                                        'id' => $row['question_id'],
                                        'question' => $row['question_text'],
                                        'type' => $row['question_type'],
                                        'order_index' => $row['question_order'],
                                        'choices' => []
                                    ];
                                }
                                
                                if ($row['choice_id']) {
                                    $quizzes[$row['id']]['questions'][$row['question_id']]['choices'][] = [
                                        'id' => $row['choice_id'],
                                        'text' => $row['choice_text'],
                                        'is_correct' => (bool)$row['is_correct']
                                    ];
                                }
                            }

                            // Convert questions from associative to indexed array
                            foreach ($quizzes as &$quiz) {
                                $quiz['questions'] = array_values($quiz['questions']);
                            }
                            unset($quiz);

                            // Convert to indexed array
                            $quizzes = array_values($quizzes);
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching quizzes: " . $e->getMessage());
                    // Continue without quizzes if tables don't exist
                    $quizzes = [];
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Course loaded',
                    'data' => [
                        'course' => $course,
                        'chapters' => $chapters,
                        'sections' => $sections,
                        'quizzes' => $quizzes
                    ]
                ]);
            } else {
                // Check if include_all_published parameter is set
                $include_all_published = isset($_GET['include_all_published']) && $_GET['include_all_published'] === 'true';
                
                if ($include_all_published) {
                    // Fetch all published courses from all teachers for placement test module assignment
                    $stmt = $pdo->prepare("
                        SELECT c.*, u.first_name, u.last_name, u.email,
                               CASE 
                                   WHEN c.teacher_id = ? THEN 'own'
                                   ELSE 'other'
                               END as ownership_type
                        FROM courses c 
                        LEFT JOIN users u ON c.teacher_id = u.id 
                        WHERE c.status = 'published' 
                           AND c.is_archived = 0
                           AND (c.is_published = 1 OR c.status = 'published')
                        ORDER BY 
                            CASE WHEN c.teacher_id = ? THEN 0 ELSE 1 END,
                            c.created_at DESC
                    ");
                    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Add teacher display name for easier identification
                    foreach ($courses as &$course) {
                        if ($course['ownership_type'] === 'own') {
                            $course['teacher_display_name'] = 'You';
                            $course['teacher_full_name'] = 'Your Module';
                        } else {
                            $course['teacher_display_name'] = trim(($course['first_name'] ?? '') . ' ' . ($course['last_name'] ?? ''));
                            if (empty($course['teacher_display_name'])) {
                                $course['teacher_display_name'] = $course['email'] ?? 'Unknown Teacher';
                            }
                            $course['teacher_full_name'] = $course['teacher_display_name'];
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'All published courses loaded',
                        'data' => $courses
                    ]);
                } else {
                    // Fetch only courses for the current teacher (original behavior)
                    $stmt = $pdo->prepare("
                        SELECT * FROM courses 
                        WHERE teacher_id = ? 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Courses loaded',
                        'data' => $courses
                    ]);
                }
            }
            break;

        case 'POST':
            // Validate required fields
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['title']) || empty(trim($data['title']))) {
                throw new Exception('Course title is required');
            }

            // Insert course
            $stmt = $pdo->prepare("
                INSERT INTO courses (
                    title, description, category_id, price, 
                    image_path, teacher_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $data['category_id'] ?? null,
                $data['price'] ?? 0.00,
                $data['image_path'] ?? null,
                $_SESSION['user_id'],
                $data['status'] ?? 'draft'
            ]);
            $course_id = $pdo->lastInsertId();

            echo json_encode(['success' => true, 'id' => $course_id]);
            break;

        case 'PUT':
            if (!$course_id) {
                throw new Exception('Course ID is required for updates');
            }

            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$course_id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Course not found or access denied');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['title']) || empty(trim($data['title']))) {
                throw new Exception('Course title is required');
            }

            // Update course
            $stmt = $pdo->prepare("
                UPDATE courses 
                SET title = ?, description = ?, category_id = ?, 
                    price = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $data['category_id'] ?? null,
                $data['price'] ?? 0.00,
                $data['status'] ?? 'draft',
                $course_id,
                $_SESSION['user_id']
            ]);

            echo json_encode(['success' => true, 'message' => 'Course updated']);
            break;

        case 'DELETE':
            if (!$course_id) {
                throw new Exception('Course ID is required for deletion');
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$course_id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Course not found or access denied');
            }

            // Delete the course and all related data (CASCADE should handle related data)
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$course_id, $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Course deleted']);
            break;

        default:
            throw new Exception('Invalid request method');
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in courses.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'error' => 'Database connection or query error'
    ]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General error in courses.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error' => 'Request processing error'
    ]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in courses.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'A system error occurred',
        'error' => 'System error'
    ]);
}
?>
