<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../config/database.php';

try {
    $course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($course_id <= 0) {
        throw new Exception('Invalid course ID');
    }
    
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
        throw new Exception('Course not found or access denied');
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

    // Fetch chapters
    $stmt = $pdo->prepare("
        SELECT c.* FROM chapters c
        JOIN sections s ON c.section_id = s.id
        WHERE s.course_id = ?
        ORDER BY s.order_index, c.order_index
    ");
    $stmt->execute([$course_id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch quizzes with complete question information
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

    // Convert questions from associative to indexed array
    foreach ($quizzes as &$quiz) {
        $quiz['questions'] = array_values($quiz['questions']);
    }
    unset($quiz);

    echo json_encode([
        'success' => true,
        'data' => [
            'course' => $course,
            'sections' => $sections,
            'chapters' => $chapters,
            'quizzes' => array_values($quizzes)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error loading course data: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
