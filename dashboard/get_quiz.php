<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

// Get quiz ID from request
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$quiz_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Quiz ID is required']);
    exit();
}

try {
    // Get quiz details and questions
    $stmt = $pdo->prepare("
        SELECT 
            q.id as quiz_id,
            q.title as quiz_title,
            q.description as quiz_description,
            qq.id as question_id,
            qq.question_text,
            qq.question_type,
            qq.score as question_score,
            qc.id as choice_id,
            qc.choice_text,
            COALESCE(qa.score, 0) as attempt_score,
            COALESCE(qa.total_points, 0) as attempt_total_points,
            qa.completed_at as last_attempt_date
        FROM quizzes q
        JOIN quiz_questions qq ON q.id = qq.quiz_id
        LEFT JOIN quiz_choices qc ON qq.id = qc.question_id
        LEFT JOIN (
            SELECT quiz_id, score, total_points, completed_at
            FROM quiz_attempts
            WHERE student_id = ?
            ORDER BY completed_at DESC
            LIMIT 1
        ) qa ON q.id = qa.quiz_id
        WHERE q.id = ?
        ORDER BY qq.order_index, qc.order_index
    ");
    $stmt->execute([$_SESSION['user_id'], $quiz_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Quiz not found']);
        exit();
    }

    // Structure the data
    $quiz = [
        'id' => $results[0]['quiz_id'],
        'title' => $results[0]['quiz_title'],
        'description' => $results[0]['quiz_description'],
        'last_attempt' => [
            'score' => $results[0]['attempt_score'],
            'total_points' => $results[0]['attempt_total_points'],
            'completed_at' => $results[0]['last_attempt_date']
        ],
        'questions' => []
    ];

    foreach ($results as $row) {
        $question_id = $row['question_id'];
        
        if (!isset($quiz['questions'][$question_id])) {
            $quiz['questions'][$question_id] = [
                'id' => $question_id,
                'text' => $row['question_text'],
                'type' => $row['question_type'],
                'score' => $row['question_score'],
                'choices' => []
            ];
        }

        if ($row['choice_id']) {
            $quiz['questions'][$question_id]['choices'][] = [
                'id' => $row['choice_id'],
                'text' => $row['choice_text']
            ];
        }
    }

    // Convert questions from associative to indexed array
    $quiz['questions'] = array_values($quiz['questions']);

    header('Content-Type: application/json');
    echo json_encode($quiz);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
} 