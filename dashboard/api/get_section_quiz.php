<?php
header('Content-Type: application/json');

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

    // Get quiz ID for this section
    $stmt = $pdo->prepare("SELECT id as quiz_id FROM quizzes WHERE section_id = ?");
    $stmt->execute([$section_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($quiz) {
        echo json_encode($quiz);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'No quiz found for this section']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
} 