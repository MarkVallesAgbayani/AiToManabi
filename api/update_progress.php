<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if this is a course completion action
    if (isset($input['action']) && $input['action'] === 'complete_course') {
        $course_id = isset($input['course_id']) ? (int)$input['course_id'] : 0;
        $student_id = $_SESSION['user_id'];
        
        if (!$course_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course ID required for course completion']);
            exit();
        }
        
        // Verify student is enrolled in the course
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$student_id, $course_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
            exit();
        }
        
        $pdo->beginTransaction();
        
        try {
            // Calculate actual completion percentage including quizzes
            $stmt = $pdo->prepare("
                SELECT c.id, c.content_type, s.id as section_id
                FROM chapters c 
                JOIN sections s ON c.section_id = s.id
                WHERE s.course_id = ? 
                ORDER BY s.order_index ASC, c.order_index ASC
            ");
            $stmt->execute([$course_id]);
            $all_course_chapters = $stmt->fetchAll();
            
            $total_course_items = count($all_course_chapters);
            $completed_course_items = 0;
            
            // Count completed chapters
            foreach ($all_course_chapters as $ch) {
                if ($ch['content_type'] === 'video') {
                    $stmt = $pdo->prepare("SELECT completed FROM video_progress WHERE student_id = ? AND chapter_id = ?");
                    $stmt->execute([$student_id, $ch['id']]);
                    $progress = $stmt->fetch();
                    if ($progress && $progress['completed']) {
                        $completed_course_items++;
                    }
                } else if ($ch['content_type'] === 'text') {
                    $stmt = $pdo->prepare("SELECT completed FROM text_progress WHERE student_id = ? AND chapter_id = ?");
                    $stmt->execute([$student_id, $ch['id']]);
                    $progress = $stmt->fetch();
                    if ($progress && $progress['completed']) {
                        $completed_course_items++;
                    }
                }
            }
            
            // Add quizzes to the total count
            $stmt = $pdo->prepare("
                SELECT DISTINCT s.id as section_id
                FROM sections s 
                JOIN quizzes q ON s.id = q.section_id
                WHERE s.course_id = ?
            ");
            $stmt->execute([$course_id]);
            $sections_with_quizzes = $stmt->fetchAll();
            
            foreach ($sections_with_quizzes as $section) {
                $total_course_items++; // Add quiz to total count
                
                // Check if quiz is completed
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as attempt_count 
                    FROM quiz_attempts qa 
                    JOIN quizzes q ON qa.quiz_id = q.id 
                    WHERE q.section_id = ? AND qa.student_id = ?
                ");
                $stmt->execute([$section['section_id'], $student_id]);
                $quiz_completed = $stmt->fetch()['attempt_count'] > 0;
                
                if ($quiz_completed) {
                    $completed_course_items++;
                }
            }
            
            // When manually completing course via "Finish Module", always set to 100%
            $actual_completion_percentage = 100;
            
            // Mark any remaining chapters as completed when manually finishing the course
            foreach ($all_course_chapters as $ch) {
                if ($ch['content_type'] === 'video') {
                    // Check if already completed
                    $stmt = $pdo->prepare("SELECT completed FROM video_progress WHERE student_id = ? AND chapter_id = ?");
                    $stmt->execute([$student_id, $ch['id']]);
                    $progress = $stmt->fetch();
                    
                    if (!$progress || !$progress['completed']) {
                        // Mark as completed
                        $stmt = $pdo->prepare("
                            INSERT INTO video_progress 
                            (student_id, chapter_id, section_id, course_id, completed, completion_percentage, completed_at) 
                            VALUES (?, ?, ?, ?, 1, 100, NOW())
                            ON DUPLICATE KEY UPDATE 
                            completed = 1,
                            completion_percentage = 100,
                            completed_at = NOW(),
                            updated_at = CURRENT_TIMESTAMP
                        ");
                        $stmt->execute([$student_id, $ch['id'], $ch['section_id'], $course_id]);
                    }
                } else if ($ch['content_type'] === 'text') {
                    // Check if already completed
                    $stmt = $pdo->prepare("SELECT completed FROM text_progress WHERE student_id = ? AND chapter_id = ?");
                    $stmt->execute([$student_id, $ch['id']]);
                    $progress = $stmt->fetch();
                    
                    if (!$progress || !$progress['completed']) {
                        // Mark as completed
                        $stmt = $pdo->prepare("
                            INSERT INTO text_progress 
                            (student_id, chapter_id, section_id, course_id, completed, completed_at) 
                            VALUES (?, ?, ?, ?, 1, NOW())
                            ON DUPLICATE KEY UPDATE 
                            completed = 1,
                            completed_at = NOW(),
                            updated_at = CURRENT_TIMESTAMP
                        ");
                        $stmt->execute([$student_id, $ch['id'], $ch['section_id'], $course_id]);
                    }
                }
            }
            
            // Update course progress to completed
            $stmt = $pdo->prepare("
                UPDATE course_progress 
                SET completion_status = 'completed',
                    completion_percentage = ?,
                    updated_at = NOW()
                WHERE course_id = ? AND student_id = ?
            ");
            $stmt->execute([$actual_completion_percentage, $course_id, $student_id]);
            
            // If no course_progress record exists, create one
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO course_progress (course_id, student_id, completed_sections, completion_percentage, completion_status)
                    SELECT ?, ?, COUNT(s.id), ?, 'completed'
                    FROM sections s 
                    WHERE s.course_id = ?
                ");
                $stmt->execute([$course_id, $student_id, $actual_completion_percentage, $course_id]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Course marked as completed successfully',
                'course_id' => $course_id,
                'completion_status' => 'completed'
            ]);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    // Regular chapter progress update logic continues below
    $chapter_id = isset($input['chapter_id']) ? (int)$input['chapter_id'] : 0;
    $section_id = isset($input['section_id']) ? (int)$input['section_id'] : 0;
    $course_id = isset($input['course_id']) ? (int)$input['course_id'] : 0;
    $content_type = isset($input['content_type']) ? $input['content_type'] : '';
    $completed = isset($input['completed']) ? (bool)$input['completed'] : false;
    $completion_percentage = isset($input['completion_percentage']) ? (float)$input['completion_percentage'] : 0;
    $watch_time = isset($input['watch_time']) ? (int)$input['watch_time'] : 0;
    $total_duration = isset($input['total_duration']) ? (int)$input['total_duration'] : 0;
    
    $student_id = $_SESSION['user_id'];
    
    // Validate required fields
    if (!$chapter_id || !$section_id || !$course_id || !$content_type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    // Verify the chapter belongs to the course and the student is enrolled
    $stmt = $pdo->prepare("
        SELECT c.id, c.content_type, s.course_id 
        FROM chapters c 
        JOIN sections s ON c.section_id = s.id 
        JOIN enrollments e ON s.course_id = e.course_id 
        WHERE c.id = ? AND s.id = ? AND s.course_id = ? AND e.student_id = ?
    ");
    $stmt->execute([$chapter_id, $section_id, $course_id, $student_id]);
    $chapter = $stmt->fetch();
    
    if (!$chapter) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Chapter not found or access denied']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    if ($content_type === 'video') {
        // Update or insert video progress
        $stmt = $pdo->prepare("
            INSERT INTO video_progress 
            (student_id, chapter_id, section_id, course_id, completed, completion_percentage, watch_time_seconds, total_duration_seconds, completed_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            completed = VALUES(completed),
            completion_percentage = VALUES(completion_percentage),
            watch_time_seconds = VALUES(watch_time_seconds),
            total_duration_seconds = VALUES(total_duration_seconds),
            completed_at = VALUES(completed_at),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $completed_at = $completed ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$student_id, $chapter_id, $section_id, $course_id, $completed, $completion_percentage, $watch_time, $total_duration, $completed_at]);
        
    } else if ($content_type === 'text') {
        // Update or insert text progress
        $stmt = $pdo->prepare("
            INSERT INTO text_progress 
            (student_id, chapter_id, section_id, course_id, completed, completed_at) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            completed = VALUES(completed),
            completed_at = VALUES(completed_at),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $completed_at = $completed ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$student_id, $chapter_id, $section_id, $course_id, $completed, $completed_at]);
    }
    
    // Calculate section progress based on chapter completions
    // Get all chapters in this section
    $stmt = $pdo->prepare("
        SELECT c.id, c.content_type 
        FROM chapters c 
        WHERE c.section_id = ? 
        ORDER BY c.order_index ASC
    ");
    $stmt->execute([$section_id]);
    $section_chapters = $stmt->fetchAll();
    
    $total_chapters = count($section_chapters);
    $completed_chapters = 0;
    
    foreach ($section_chapters as $ch) {
        if ($ch['content_type'] === 'video') {
            $stmt = $pdo->prepare("SELECT completed FROM video_progress WHERE student_id = ? AND chapter_id = ?");
            $stmt->execute([$student_id, $ch['id']]);
            $progress = $stmt->fetch();
            if ($progress && $progress['completed']) {
                $completed_chapters++;
            }
        } else if ($ch['content_type'] === 'text') {
            $stmt = $pdo->prepare("SELECT completed FROM text_progress WHERE student_id = ? AND chapter_id = ?");
            $stmt->execute([$student_id, $ch['id']]);
            $progress = $stmt->fetch();
            if ($progress && $progress['completed']) {
                $completed_chapters++;
            }
        }
    }
    
    // Update section progress in the existing progress table
    $section_completed = ($completed_chapters === $total_chapters && $total_chapters > 0);
    $stmt = $pdo->prepare("
        INSERT INTO progress 
        (student_id, section_id, completed, completed_at) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        completed = VALUES(completed),
        completed_at = VALUES(completed_at),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $section_completed_at = $section_completed ? date('Y-m-d H:i:s') : null;
    $stmt->execute([$student_id, $section_id, $section_completed, $section_completed_at]);
    
    // Calculate overall course progress including quizzes
    $stmt = $pdo->prepare("
        SELECT c.id, c.content_type, s.id as section_id
        FROM chapters c 
        JOIN sections s ON c.section_id = s.id
        WHERE s.course_id = ? 
        ORDER BY s.order_index ASC, c.order_index ASC
    ");
    $stmt->execute([$course_id]);
    $all_course_chapters = $stmt->fetchAll();
    
    $total_course_items = count($all_course_chapters);
    $completed_course_items = 0;
    
    // Count completed chapters
    foreach ($all_course_chapters as $ch) {
        if ($ch['content_type'] === 'video') {
            $stmt = $pdo->prepare("SELECT completed FROM video_progress WHERE student_id = ? AND chapter_id = ?");
            $stmt->execute([$student_id, $ch['id']]);
            $progress = $stmt->fetch();
            if ($progress && $progress['completed']) {
                $completed_course_items++;
            }
        } else if ($ch['content_type'] === 'text') {
            $stmt = $pdo->prepare("SELECT completed FROM text_progress WHERE student_id = ? AND chapter_id = ?");
            $stmt->execute([$student_id, $ch['id']]);
            $progress = $stmt->fetch();
            if ($progress && $progress['completed']) {
                $completed_course_items++;
            }
        }
    }
    
    // Add quizzes to the total count
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id as section_id
        FROM sections s 
        JOIN quizzes q ON s.id = q.section_id
        WHERE s.course_id = ?
    ");
    $stmt->execute([$course_id]);
    $sections_with_quizzes = $stmt->fetchAll();
    
    foreach ($sections_with_quizzes as $section) {
        $total_course_items++; // Add quiz to total count
        
        // Check if quiz is completed
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM quiz_attempts qa 
            JOIN quizzes q ON qa.quiz_id = q.id 
            WHERE q.section_id = ? AND qa.student_id = ?
        ");
        $stmt->execute([$section['section_id'], $student_id]);
        $quiz_completed = $stmt->fetch()['attempt_count'] > 0;
        
        if ($quiz_completed) {
            $completed_course_items++;
        }
    }
    
    $course_progress_percentage = $total_course_items > 0 ? 
        round(($completed_course_items / $total_course_items) * 100) : 0;
    
    
    $pdo->commit();
    
    // Return updated progress information
    echo json_encode([
        'success' => true,
        'message' => 'Progress updated successfully',
        'section_progress' => [
            'completed_chapters' => $completed_chapters,
            'total_chapters' => $total_chapters,
            'section_completed' => $section_completed
        ],
        'course_progress' => [
            'completed_items' => $completed_course_items,
            'total_items' => $total_course_items,
            'percentage' => $course_progress_percentage
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
