<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON header first
header('Content-Type: application/json');

// Start output buffering to catch any unexpected output
ob_start();

try {
    session_start();
    require_once '../../config/database.php';

    // Check if user is logged in and is a student
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    // Get section ID from query string
    $section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
    if (!$section_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Section ID is required']);
        exit();
    }

    // Get quiz page parameter (default to 1)
    $quiz_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $questions_per_page = 1; // One question per page
    
    // Get quiz details for this section
    $stmt = $pdo->prepare("
        SELECT q.*, s.title as section_title    
        FROM quizzes q
        LEFT JOIN sections s ON q.section_id = s.id
        WHERE q.section_id = ?
        ORDER BY q.order_index ASC, q.id ASC
        LIMIT 1
    ");
    $stmt->execute([$section_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['error' => 'Quiz not found']);
        exit();
    }

    // Get total questions count for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM quiz_questions WHERE quiz_id = ?");
    $stmt->execute([$quiz['id']]);
    $total_questions = $stmt->fetch()['total'];
    $total_pages = ceil($total_questions / $questions_per_page);
    
    // Calculate offset for current page
    $offset = ($quiz_page - 1) * $questions_per_page;
    
    // First, get the question for the current page
    $stmt = $pdo->prepare("
        SELECT 
            qq.id,
            qq.question_text as text,
            qq.question_type as type,
            qq.score as points,
            qq.word,
            qq.romaji,
            qq.meaning,
            qq.audio_url,
            qq.accuracy_threshold,
            qq.word_definition_pairs,
            qq.translation_pairs,
            qq.answers
        FROM quiz_questions qq
        WHERE qq.quiz_id = ?
        ORDER BY qq.order_index
        LIMIT 1 OFFSET ?
    ");
    $stmt->execute([$quiz['id'], $offset]);
    $current_question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_question) {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
        exit();
    }
    
    // Then get the choices for this specific question
    $stmt = $pdo->prepare("
        SELECT 
            qc.id as choice_id, 
            qc.choice_text, 
            qc.is_correct
        FROM quiz_choices qc
        WHERE qc.question_id = ?
        ORDER BY qc.order_index, qc.id
    ");
    $stmt->execute([$current_question['id']]);
    $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine question with its choices
    $results = [];
    foreach ($choices as $choice) {
        $results[] = array_merge($current_question, $choice);
    }
    
    // If no choices found, still include the question (for true/false or fill-in-the-blank)
    if (empty($results)) {
        $results[] = $current_question;
    }

    // Structure the quiz data
    $quizData = [
        'id' => $quiz['id'],
        'title' => $quiz['title'],
        'description' => $quiz['description'],
        'section_title' => $quiz['section_title'],
        'questions' => [],
        'pagination' => [
            'current_page' => $quiz_page,
            'total_pages' => $total_pages,
            'total_questions' => $total_questions,
            'has_next' => $quiz_page < $total_pages,
            'has_previous' => $quiz_page > 1,
            'next_page' => $quiz_page < $total_pages ? $quiz_page + 1 : null,
            'previous_page' => $quiz_page > 1 ? $quiz_page - 1 : null
        ]
    ];

    $currentQuestion = null;
    $lastQuestionId = null;
    foreach ($results as $row) {
        if ($currentQuestion === null || $currentQuestion['id'] !== $row['id']) {
            if ($currentQuestion !== null) {
                // For true_false, ensure choices are present
                if ($currentQuestion['type'] === 'true_false' && empty($currentQuestion['choices'])) {
                    $currentQuestion['choices'] = [
                        ['id' => $currentQuestion['id'].'_true', 'text' => 'True', 'is_correct' => null],
                        ['id' => $currentQuestion['id'].'_false', 'text' => 'False', 'is_correct' => null]
                    ];
                }
                $quizData['questions'][] = $currentQuestion;
            }
            $currentQuestion = [
                'id' => $row['id'],
                'text' => $row['text'],
                'type' => $row['type'],
                'points' => $row['points'],
                'choices' => [],
                'word' => $row['word'],
                'romaji' => $row['romaji'],
                'meaning' => $row['meaning'],
                'audio_url' => $row['audio_url'],
                'accuracy_threshold' => $row['accuracy_threshold'],
                'word_definition_pairs' => $row['word_definition_pairs'] ? json_decode($row['word_definition_pairs'], true) : null,
                'translation_pairs' => $row['translation_pairs'] ? json_decode($row['translation_pairs'], true) : null,
                'answers' => $row['answers'] ? json_decode($row['answers'], true) : null
            ];
            $lastQuestionId = $row['id'];
        }
        
        if ($row['choice_id']) {
            $currentQuestion['choices'][] = [
                'id' => $row['choice_id'],
                'text' => $row['choice_text'],
                'is_correct' => (bool)$row['is_correct']
            ];
        }
    }

    // Add the last question (even if there are no choices)
    if ($currentQuestion !== null && $lastQuestionId !== null) {
        // For true_false, ensure choices are present
        if ($currentQuestion['type'] === 'true_false' && empty($currentQuestion['choices'])) {
            $currentQuestion['choices'] = [
                ['id' => $currentQuestion['id'].'_true', 'text' => 'True', 'is_correct' => null],
                ['id' => $currentQuestion['id'].'_false', 'text' => 'False', 'is_correct' => null]
            ];
        }
        $quizData['questions'][] = $currentQuestion;
    }

    // Get user's last attempt and attempt count
    $stmt = $pdo->prepare("
        SELECT *
        FROM quiz_attempts
        WHERE quiz_id = ? AND student_id = ?
        ORDER BY completed_at DESC
        LIMIT 1
    ");
    $stmt->execute([$quiz['id'], $_SESSION['user_id']]);
    $last_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total attempt count for this quiz
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count
        FROM quiz_attempts
        WHERE quiz_id = ? AND student_id = ?
    ");
    $stmt->execute([$quiz['id'], $_SESSION['user_id']]);
    $attempt_count = $stmt->fetch()['attempt_count'];
    
    // Debug logging
    error_log("Quiz API Debug - Quiz ID: " . $quiz['id'] . ", Student ID: " . $_SESSION['user_id'] . ", Attempt Count: " . $attempt_count);
    error_log("Last attempt: " . json_encode($last_attempt));

    $quizData['last_attempt'] = $last_attempt ?: null;
    $quizData['attempt_count'] = (int)$attempt_count;
    
    // Check if max_retakes column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM quizzes LIKE 'max_retakes'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    $max_retakes = 3; // Default value
    if ($column_exists && array_key_exists('max_retakes', $quiz)) {
        $max_retakes = $quiz['max_retakes'] ?? 3;
    }
    
    // Debug logging
    error_log("DEBUG: Quiz max_retakes from DB: " . json_encode($quiz['max_retakes'] ?? 'NULL'));
    error_log("DEBUG: Final max_retakes value: " . $max_retakes);
    
    $quizData['max_retakes'] = $max_retakes;
    
    // Determine if user can retake the quiz
    $can_retake = false;
    if ($max_retakes === -1) {
        $can_retake = true; // Unlimited retakes
    } elseif ($max_retakes >= 0) {
        // Total allowed attempts = max_retakes + 1 (initial attempt + retakes)
        // max_retakes = 0 means 1 total attempt (0 retakes)
        // max_retakes = 1 means 2 total attempts (1 retake)
        $total_allowed_attempts = $max_retakes + 1;
        $can_retake = ($attempt_count < $total_allowed_attempts);
    }
    $quizData['can_retake'] = $can_retake;
    
    // Add quiz completion status - quiz is completed when retakes are exhausted
    $quizData['quizCompleted'] = !$can_retake && $attempt_count > 0;

    // Clean any output buffer and return the quiz data
    ob_clean();
    echo json_encode($quizData);
    exit();

} catch (PDOException $e) {
    error_log("Quiz API Error: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load quiz data']);
    exit();
} catch (Exception $e) {
    error_log("Quiz API General Error: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit();
}
