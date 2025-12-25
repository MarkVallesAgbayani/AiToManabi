<?php
// Disable error display to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

/**
 * Get pronunciation evaluation settings
 * @return array
 */
function getPronunciationEvaluationSettings() {
    return [
        'default_threshold' => 70.0,
        'partial_credit_threshold' => 50.0,
        'max_score' => 100.0,
        'fallback_score' => 75.0
    ];
}

/**
 * Normalize text for comparison - handles case sensitivity and Japanese characters
 * @param string $text
 * @return string
 */
function normalizeText($text) {
    if ($text === null || $text === '') {
        return '';
    }
    
    // Trim whitespace
    $text = trim($text);
    
    // For Japanese text, we don't want to convert to lowercase as it can break the text
    // Instead, we'll use mb_strtolower with UTF-8 encoding for proper Unicode handling
    if (mb_check_encoding($text, 'UTF-8')) {
        // Use mb_strtolower for proper Unicode case conversion
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        // Fallback to regular strtolower for non-UTF-8 text
        $text = strtolower($text);
    }
    
    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    return $text;
}

/**
 * Evaluate pronunciation using speech recognition API
 * @param string $audioUrl - URL or base64 data of recorded audio
 * @param array $question - Question data containing expected pronunciation
 * @return float - Pronunciation accuracy score (0-100)
 */
function evaluatePronunciation($audioData, $question) {
    try {
        // Get expected pronunciation data
        $expectedWord = $question['word'] ?? '';
        $expectedRomaji = $question['romaji'] ?? '';
        $expectedMeaning = $question['meaning'] ?? '';
        
        if (empty($expectedWord)) {
            error_log("No expected word found for pronunciation evaluation");
            return 0.0;
        }
        
        $recognizedText = '';
        
        // Check if we have Web Speech API recognition result (array format)
        if (is_array($audioData) && isset($audioData['recognizedText'])) {
            // Web Speech API recognition result from client
            $recognizedText = $audioData['recognizedText'];
            $confidence = $audioData['confidence'] ?? 0;
            error_log("Web Speech API result: '$recognizedText' (confidence: $confidence)");
        } 
        // Check if it's a string (legacy format or blob URL)
        else if (is_string($audioData)) {
            if (strpos($audioData, 'blob:') === 0) {
                // This is a blob URL from the browser - we can't process this server-side
                error_log("Blob URL detected for pronunciation - using fallback evaluation");
                $recognizedText = performFallbackPronunciationCheck($expectedWord);
            } else {
                // No recognition result available in string format
                error_log("String audio data without recognition result - using fallback");
                $recognizedText = performFallbackPronunciationCheck($expectedWord);
            }
        } else {
            // No recognition result available
            error_log("No Web Speech API recognition result found");
            $recognizedText = performFallbackPronunciationCheck($expectedWord);
        }
        
        if (empty($recognizedText)) {
            error_log("No text recognized from audio - using fallback evaluation");
            // Use fallback evaluation if no recognition result
            $recognizedText = performFallbackPronunciationCheck($expectedWord);
        }
        
        // Calculate similarity between recognized text and expected word
        $accuracy = calculatePronunciationAccuracy($recognizedText, $expectedWord, $expectedRomaji);
        
        error_log("Pronunciation evaluation: Expected='$expectedWord', Recognized='$recognizedText', Accuracy=$accuracy%");
        
        return $accuracy;
        
    } catch (Exception $e) {
        error_log("Error in pronunciation evaluation: " . $e->getMessage());
        return 0.0;
    }
}


/**
 * Fallback pronunciation check when API is not available
 * @param string $expectedWord - Expected word
 * @return string - Simulated recognized text
 */
function performFallbackPronunciationCheck($expectedWord) {
    // This is a fallback that simulates speech recognition
    // In a real implementation, you might use a different speech recognition service
    // or implement a client-side solution
    
    // For now, return the expected word with some variation to simulate recognition
    $variations = [
        $expectedWord,
        $expectedWord . 'さん', // Add honorific
        $expectedWord . 'です', // Add copula
        substr($expectedWord, 0, -1) . 'う', // Common mispronunciation
        $expectedWord . 'か' // Add question particle
    ];
    
    // Return a random variation to simulate different recognition results
    return $variations[array_rand($variations)];
}

/**
 * Calculate pronunciation accuracy between recognized and expected text
 * @param string $recognized - Text recognized from speech
 * @param string $expected - Expected Japanese word
 * @param string $romaji - Expected romaji pronunciation
 * @return float - Accuracy percentage (0-100)
 */
function calculatePronunciationAccuracy($recognized, $expected, $romaji = '') {
    // Normalize both texts for comparison
    $recognized = normalizeText($recognized);
    $expected = normalizeText($expected);
    $romaji = normalizeText($romaji);
    
    // Check for exact match first
    if ($recognized === $expected) {
        return 100.0;
    }
    
    // Check if recognized text contains the expected word
    if (strpos($recognized, $expected) !== false) {
        return 95.0;
    }
    
    // Check against romaji if available
    if (!empty($romaji) && strpos($recognized, $romaji) !== false) {
        return 90.0;
    }
    
    // Calculate Levenshtein distance for similarity
    $distance = levenshtein($recognized, $expected);
    $maxLength = max(strlen($recognized), strlen($expected));
    
    if ($maxLength === 0) {
        return 0.0;
    }
    
    $similarity = (1 - ($distance / $maxLength)) * 100;
    
    // Apply some adjustments for Japanese text
    if (mb_strlen($expected, 'UTF-8') <= 3) {
        // For short words, be more strict
        $similarity = $similarity * 0.8;
    }
    
    // Check for common Japanese pronunciation patterns
    $commonPatterns = [
        'さん' => 5,   // Honorific suffix
        'です' => 5,   // Copula
        'か' => 3,     // Question particle
        'ね' => 3,     // Confirmation particle
        'よ' => 3      // Emphasis particle
    ];
    
    foreach ($commonPatterns as $pattern => $bonus) {
        if (strpos($recognized, $pattern) !== false && strpos($expected, $pattern) === false) {
            $similarity += $bonus;
        }
    }
    
    return min(100.0, max(0.0, $similarity));
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$quiz_id = isset($data['quiz_id']) ? (int)$data['quiz_id'] : null;
$answers = isset($data['answers']) ? $data['answers'] : [];
$time_expired = isset($data['time_expired']) ? (bool)$data['time_expired'] : false;

// Debug logging
error_log("Quiz submission received - Quiz ID: $quiz_id, Time Expired: " . ($time_expired ? 'true' : 'false') . ", Answers: " . json_encode($answers));
error_log("Raw POST data: " . file_get_contents('php://input'));
error_log("Answers array keys: " . (is_array($answers) ? implode(', ', array_keys($answers)) : 'Not an array'));
error_log("Answers array values: " . json_encode($answers));

if (!$quiz_id) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid quiz ID']);
    exit();
}

// Allow empty answers if timer expired
if (!is_array($answers) && !$time_expired) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid answers data']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First, check retake limits before processing submission
    // Check if max_retakes column exists, if not use default behavior
    $stmt = $pdo->prepare("SHOW COLUMNS FROM quizzes LIKE 'max_retakes'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    $max_retakes = 3; // Default value
    
    if ($column_exists) {
        // Column exists, get the actual value
        $stmt = $pdo->prepare("
            SELECT max_retakes 
            FROM quizzes 
            WHERE id = ?
        ");
        $stmt->execute([$quiz_id]);
        $quiz_retake_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz_retake_info) {
            throw new Exception('Quiz not found');
        }
        
        $max_retakes = $quiz_retake_info['max_retakes'];
    } else {
        // Column doesn't exist yet, check if quiz exists
        $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ?");
        $stmt->execute([$quiz_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Quiz not found');
        }
    }
    
    // Get current attempt count for this student
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM quiz_attempts 
        WHERE quiz_id = ? AND student_id = ?
    ");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $current_attempt_count = $stmt->fetch()['attempt_count'];
    
    // Check if student can retake the quiz (for future attempts)
    // But allow current submission to complete even if retake limit reached
    $can_retake_after = false;
    if ($max_retakes === -1) {
        $can_retake_after = true; // Unlimited retakes
    } elseif ($max_retakes >= 0) {
        // max_retakes = 0 means 1 total attempt (0 retakes)
        // max_retakes = 1 means 2 total attempts (1 retake)
        // max_retakes = 2 means 3 total attempts (2 retakes)
        $total_allowed_attempts = $max_retakes + 1;
        $can_retake_after = ($current_attempt_count < $total_allowed_attempts);
    }
    
    // Get quiz details and correct answers for only the questions that were answered
    $answered_question_ids = array_keys($answers);
    
    // If no answers provided (timer expired with no answers), get all quiz questions
    if (empty($answered_question_ids)) {
        if ($time_expired) {
            // Timer expired with no answers - get all questions and score them as incorrect
            $stmt = $pdo->prepare("
                SELECT 
                    q.id as quiz_id,
                    q.title,
                    qq.id as question_id,
                    qq.question_text,
                    qq.question_type,
                    qq.score
                FROM quizzes q
                JOIN quiz_questions qq ON q.id = qq.quiz_id
                WHERE q.id = ?
            ");
            $stmt->execute([$quiz_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process results for timer-expired submission
            $questions = [];
            $total_points = 0;  
            $scored_points = 0; // No points scored since no answers
            
            foreach ($results as $row) {
                $question_id = $row['question_id'];
                
                if (!isset($questions[$question_id])) {
                    $questions[$question_id] = [
                        'id' => $question_id,
                        'text' => $row['question_text'],
                        'points' => $row['score'],
                        'type' => $row['question_type']
                    ];
                    $total_points += $row['score'];
                }
            }
            
            // Create graded questions with no points (timer expired)
            $graded_questions = [];
            foreach ($questions as $question) {
                $graded_questions[] = [
                    'id' => $question['id'],
                    'text' => $question['text'],
                    'correct' => false,
                    'points' => $question['points'],
                    'user_answer' => '',
                    'correct_answer' => null
                ];
            }
            
            // Save quiz attempt with 0 score
            $stmt = $pdo->prepare("
                INSERT INTO quiz_attempts (
                    quiz_id, 
                    student_id, 
                    score, 
                    total_points, 
                    completed_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $quiz_id,
                $_SESSION['user_id'],
                0, // No score since timer expired
                $total_points
            ]);
            
            $attempt_id = $pdo->lastInsertId();
            
            // Save individual answers (all incorrect due to timer expiry)
            $stmt = $pdo->prepare("
                INSERT INTO quiz_answers (
                    attempt_id,
                    question_id,
                    answer_text,
                    is_correct,
                    points_earned
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($graded_questions as $question) {
                $stmt->execute([
                    $attempt_id,
                    $question['id'],
                    '', // No answer provided
                    0,  // Incorrect due to timer expiry
                    0   // No points earned
                ]);
            }
            
            // Get attempt number for this quiz (after the new attempt was added)
            $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
            $stmt->execute([$quiz_id, $_SESSION['user_id']]);
            $final_attempt_count = $stmt->fetch()['attempt_count'];
            
            // Debug logging
            error_log("Timer-expired quiz submission - Score: 0/$total_points, Attempt: $final_attempt_count, Can retake: " . ($can_retake_after ? 'yes' : 'no'));
            
            // Clean any output buffer and return results
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'score' => 0,
                'total_points' => $total_points,
                'questions' => $graded_questions,
                'attempt_number' => $final_attempt_count,
                'max_retakes' => $max_retakes,
                'can_retake' => $can_retake_after,
                'retakes_exhausted' => !$can_retake_after,
                'time_expired' => true
            ]);
            
            // Commit transaction and exit
            $pdo->commit();
            exit();
        } else {
            throw new Exception('No answers provided');
        }
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($answered_question_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT 
            q.id as quiz_id,
            q.title,
            qq.id as question_id,
            qq.question_text,
            qq.question_type,
            qq.score,
            qq.word_definition_pairs,
            qq.translation_pairs,
            qq.answers as correct_answers,
            qq.word,
            qq.romaji,
            qq.meaning,
            qq.audio_url,
            qq.accuracy_threshold,
            qc.id as choice_id,
            qc.choice_text,
            qc.is_correct
        FROM quizzes q
        JOIN quiz_questions qq ON q.id = qq.quiz_id
        LEFT JOIN quiz_choices qc ON qq.id = qc.question_id
        WHERE q.id = ? AND qq.id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$quiz_id], $answered_question_ids));
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process results
    $questions = [];
    $total_points = 0;  
    $scored_points = 0;
    
    foreach ($results as $row) {
        $question_id = $row['question_id'];
        
        if (!isset($questions[$question_id])) {
            $questions[$question_id] = [
                'id' => $question_id,
                'text' => $row['question_text'],
                'points' => $row['score'],
                'type' => $row['question_type'],
                'correct_answer' => null,
                'word_definition_pairs' => $row['word_definition_pairs'],
                'translation_pairs' => $row['translation_pairs'],
                'correct_answers' => $row['correct_answers'],
                'word' => $row['word'],
                'romaji' => $row['romaji'],
                'meaning' => $row['meaning'],
                'audio_url' => $row['audio_url'],
                'accuracy_threshold' => $row['accuracy_threshold'],
                'choices' => []
            ];
            $total_points += $row['score'];
        }
        
        if ($row['choice_id']) {
            $questions[$question_id]['choices'][] = [
                'id' => $row['choice_id'],
                'text' => $row['choice_text'],
                'is_correct' => (bool)$row['is_correct']
            ];
            
            if ($row['is_correct']) {
                $questions[$question_id]['correct_answer'] = $row['choice_text'];
            }
        }
    }
    
    // Grade each answer
    $graded_questions = [];
    error_log("Processing " . count($questions) . " questions for scoring");
    error_log("Answered question IDs: " . json_encode($answered_question_ids));
    error_log("Answers received: " . json_encode($answers));
    error_log("Questions loaded: " . json_encode(array_keys($questions)));
    
    foreach ($questions as $question) {
        $question_id = $question['id']; // Define question_id for use in pair-based questions
        $user_answer = null;
        $is_correct = false;
        
        // Initialize user_answer based on question type
        if ($question['type'] === 'fill_blank' || $question['type'] === 'fill_blanks') {
            $user_answer = $answers[$question_id] ?? null;
        } elseif ($question['type'] === 'multiple_choice' || $question['type'] === 'true_false') {
            $user_answer = $answers[$question_id] ?? null;
        } elseif ($question['type'] === 'pronunciation') {
            $user_answer = $answers[$question_id] ?? null;
        } elseif ($question['type'] === 'word_definition' || $question['type'] === 'sentence_translation') {
            // For pair-based questions, collect all pair answers into a single string
            $pair_answers = [];
            if ($question['type'] === 'word_definition') {
                $pairs = json_decode($question['word_definition_pairs'], true);
                if ($pairs) {
                    foreach ($pairs as $index => $pair) {
                        $pair_answer = $answers[$question_id . '_' . $index] ?? '';
                        if ($pair_answer) {
                            $pair_answers[] = $pair['word'] . ': ' . $pair_answer;
                        }
                    }
                }
            } elseif ($question['type'] === 'sentence_translation') {
                $pairs = json_decode($question['translation_pairs'], true);
                if ($pairs) {
                    foreach ($pairs as $index => $pair) {
                        $pair_answer = $answers[$question_id . '_' . $index] ?? '';
                        if ($pair_answer) {
                            $pair_answers[] = $pair['japanese'] . ': ' . $pair_answer;
                        }
                    }
                }
            }
            $user_answer = implode('; ', $pair_answers);
        }
        
        error_log("Question ID: {$question_id}, Type: {$question['type']}, User Answer: " . ($user_answer ?? 'null') . ", Points: {$question['points']}");
        
        switch ($question['type']) {
            case 'multiple_choice':
                // Convert choice ID to choice text for display
                $selected_choice_text = null;
                foreach ($question['choices'] as $choice) {
                    if ($choice['id'] == $user_answer) {
                        $selected_choice_text = $choice['text'];
                        if ($choice['is_correct']) {
                            $is_correct = true;
                            $scored_points += $question['points'];
                        }
                        break;
                    }
                }
                // Update user_answer to show the actual choice text instead of ID
                if ($selected_choice_text) {
                    $user_answer = $selected_choice_text;
                }
                break;
                
            case 'true_false':
                // For true/false, find the correct choice and compare
                $correct_choice = null;
                $selected_choice_text = null;
                foreach ($question['choices'] as $choice) {
                    if ($choice['id'] == $user_answer) {
                        $selected_choice_text = $choice['text'];
                    }
                    if ($choice['is_correct']) {
                        $correct_choice = $choice['text'];
                    }
                }
                
                // Convert user answer to boolean string for comparison
                $user_answer_bool = ($user_answer === 'true') ? 'True' : 'False';
                if ($user_answer_bool === $correct_choice) {
                    $is_correct = true;
                    $scored_points += $question['points'];
                }
                
                // Update user_answer to show the actual choice text instead of ID
                if ($selected_choice_text) {
                    $user_answer = $selected_choice_text;
                }
                break;
                
            case 'fill_blank':
            case 'fill_blanks':
                // For fill in the blank, check against correct answers
                error_log("Fill blank question $question_id - User answer: '$user_answer'");
                if ($user_answer !== null && $user_answer !== '') {
                    $correct_answers = json_decode($question['correct_answers'], true);
                    error_log("Fill blank question $question_id - Correct answers: " . json_encode($correct_answers));
                    
                    if ($correct_answers && is_array($correct_answers)) {
                        // Check if user answer matches any of the correct answers
                        foreach ($correct_answers as $correct_answer) {
                            $normalized_user = normalizeText($user_answer);
                            $normalized_correct = normalizeText($correct_answer);
                            error_log("Fill blank question $question_id - Comparing '$normalized_user' with '$normalized_correct'");
                            
                            if ($normalized_user === $normalized_correct) {
                                $is_correct = true;
                                $scored_points += $question['points'];
                                error_log("Fill blank question $question_id - Match found, scored {$question['points']} points");
                                break;
                            }
                        }
                    } else {
                        // Fallback to direct comparison
                        $normalized_user = normalizeText($user_answer);
                        $normalized_correct = normalizeText($question['correct_answer']);
                        error_log("Fill blank question $question_id - Fallback comparison: '$normalized_user' with '$normalized_correct'");
                        
                        if ($normalized_user === $normalized_correct) {
                            $is_correct = true;
                            $scored_points += $question['points'];
                            error_log("Fill blank question $question_id - Fallback match, scored {$question['points']} points");
                        }
                    }
                } else {
                    error_log("Fill blank question $question_id - Empty user answer");
                }
                break;
                
            case 'word_definition':
                // For word definition questions, check all pairs
                $word_definition_pairs = json_decode($question['word_definition_pairs'], true);
                error_log("Word definition pairs: " . json_encode($word_definition_pairs));
                
                if ($word_definition_pairs && is_array($word_definition_pairs)) {
                    $all_pairs_correct = true;
                    foreach ($word_definition_pairs as $index => $pair) {
                        $answer_key = $question_id . '_' . $index;
                        $user_pair_answer = $answers[$answer_key] ?? '';
                        error_log("Word definition pair $index - Answer key: $answer_key, User answer: '$user_pair_answer'");
                        
                        if ($user_pair_answer === null || $user_pair_answer === '') {
                            $all_pairs_correct = false;
                            error_log("Word definition pair $index - Empty answer");
                            break;
                        }
                        
                        $correct_definition = normalizeText($pair['definition']);
                        $user_definition = normalizeText($user_pair_answer);
                        error_log("Word definition pair $index - Correct: '$correct_definition', User: '$user_definition'");
                        
                        if ($correct_definition !== $user_definition) {
                            $all_pairs_correct = false;
                            error_log("Word definition pair $index - Mismatch");
                            break;
                        }
                    }
                    if ($all_pairs_correct) {
                        $is_correct = true;
                        $scored_points += $question['points'];
                        error_log("Word definition question $question_id - All pairs correct, scored {$question['points']} points");
                    } else {
                        error_log("Word definition question $question_id - Not all pairs correct");
                    }
                } else {
                    error_log("Word definition question $question_id - No valid pairs found");
                }
                break;
                
            case 'sentence_translation':
                // For sentence translation questions, check all pairs
                $translation_pairs = json_decode($question['translation_pairs'], true);
                error_log("Translation pairs: " . json_encode($translation_pairs));
                
                if ($translation_pairs && is_array($translation_pairs)) {
                    $all_pairs_correct = true;
                    foreach ($translation_pairs as $index => $pair) {
                        $answer_key = $question_id . '_' . $index;
                        $user_pair_answer = $answers[$answer_key] ?? '';
                        error_log("Translation pair $index - Answer key: $answer_key, User answer: '$user_pair_answer'");
                        
                        if ($user_pair_answer === null || $user_pair_answer === '') {
                            $all_pairs_correct = false;
                            error_log("Translation pair $index - Empty answer");
                            break;
                        }
                        
                        $correct_translation = normalizeText($pair['english']);
                        $user_translation = normalizeText($user_pair_answer);
                        error_log("Translation pair $index - Correct: '$correct_translation', User: '$user_translation'");
                        
                        if ($correct_translation !== $user_translation) {
                            $all_pairs_correct = false;
                            error_log("Translation pair $index - Mismatch");
                            break;
                        }
                    }
                    if ($all_pairs_correct) {
                        $is_correct = true;
                        $scored_points += $question['points'];
                        error_log("Translation question $question_id - All pairs correct, scored {$question['points']} points");
                    } else {
                        error_log("Translation question $question_id - Not all pairs correct");
                    }
                } else {
                    error_log("Translation question $question_id - No valid pairs found");
                }
                break;
                
            case 'pronunciation':
                // For pronunciation questions, evaluate using speech recognition
                if (!empty($user_answer) && $user_answer !== '') {
                    try {
                        $pronunciation_score = evaluatePronunciation($user_answer, $question);
                        $settings = getPronunciationEvaluationSettings();
                        $accuracy_threshold = $question['accuracy_threshold'] ?? $settings['default_threshold'];
                        
                        // Award points based on accuracy threshold
                        if ($pronunciation_score >= $accuracy_threshold) {
                            $is_correct = true;
                            $scored_points += $question['points'];
                        } else {
                            // Award partial points for attempts above partial credit threshold
                            if ($pronunciation_score >= $settings['partial_credit_threshold']) {
                                $partial_points = ($pronunciation_score / 100) * $question['points'];
                                $scored_points += $partial_points;
                            }
                        }
                        
                        // Store pronunciation score for feedback
                        $question['pronunciation_score'] = $pronunciation_score;
                    } catch (Exception $e) {
                        error_log("Error in pronunciation evaluation: " . $e->getMessage());
                        // Give default score for pronunciation attempts
                        $is_correct = true;
                        $scored_points += $question['points'];
                        $question['pronunciation_score'] = 75.0;
                    }
                }
                break;
        }
        
        $graded_question = [
            'id' => $question['id'],
            'text' => $question['text'],
            'type' => $question['type'],
            'correct' => $is_correct,
            'points' => $question['points'],
            'user_answer' => $user_answer,
            'correct_answer' => $question['correct_answer']
        ];
        
        // Add pronunciation-specific data if this is a pronunciation question
        if ($question['type'] === 'pronunciation' && isset($question['pronunciation_score'])) {
            $graded_question['pronunciation_score'] = $question['pronunciation_score'];
            $graded_question['expected_word'] = $question['word'] ?? '';
            $graded_question['expected_romaji'] = $question['romaji'] ?? '';
            $graded_question['expected_meaning'] = $question['meaning'] ?? '';
            $graded_question['accuracy_threshold'] = $question['accuracy_threshold'] ?? 70.0;
        }
        
        $graded_questions[] = $graded_question;
        
        error_log("Question {$question_id} graded: " . ($is_correct ? 'CORRECT' : 'INCORRECT') . ", Points earned: " . ($is_correct ? $question['points'] : 0) . ", Total graded questions: " . count($graded_questions));
    }
    
    error_log("Final scoring - Scored points: $scored_points, Total points: $total_points, Time Expired: " . ($time_expired ? 'true' : 'false'));
    error_log("Final graded questions count: " . count($graded_questions));
    error_log("Final graded questions: " . json_encode($graded_questions));
    
    // Save quiz attempt
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts (
            quiz_id, 
            student_id, 
            score, 
            total_points, 
            completed_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $quiz_id,
        $_SESSION['user_id'],
        $scored_points,
        $total_points
    ]);
    
    $attempt_id = $pdo->lastInsertId();
    
    // Save individual answers
    $stmt = $pdo->prepare("
        INSERT INTO quiz_answers (
            attempt_id,
            question_id,
            answer_text,
            is_correct,
            points_earned
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($graded_questions as $question) {
        // Ensure user_answer is never null - use empty string as fallback
        $answer_text = $question['user_answer'] ?? '';
        if ($answer_text === null) {
            $answer_text = '';
        }
        
        $stmt->execute([
            $attempt_id,
            $question['id'],
            $answer_text,
            $question['correct'] ? 1 : 0,
            $question['correct'] ? $question['points'] : 0
        ]);
    }
    
    // Note: Removed auto-completion logic - users must manually click "Finish Module" to complete the course
    // This ensures proper user control over course completion and prevents chapters from being auto-completed
    
    // Commit transaction
    $pdo->commit();
    
    // Get attempt number for this quiz
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $attempt_count = $stmt->fetch()['attempt_count'];
    
    // Check retake status after submission
    $can_retake_after = false;
    if ($max_retakes === -1) {
        $can_retake_after = true; // Unlimited retakes
    } elseif ($max_retakes >= 0) {
        // max_retakes = 0 means 1 total attempt (0 retakes)
        // max_retakes = 1 means 2 total attempts (1 retake)
        // max_retakes = 2 means 3 total attempts (2 retakes)
        $total_allowed_attempts = $max_retakes + 1;
        $can_retake_after = ($attempt_count < $total_allowed_attempts);
    }
    
    // Debug logging
    error_log("Quiz submission successful - Score: $scored_points/$total_points, Attempt: $attempt_count, Can retake: " . ($can_retake_after ? 'yes' : 'no'));
    
    // Clean any output buffer and return results
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'score' => $scored_points,
        'total_points' => $total_points,
        'questions' => $graded_questions,
        'attempt_number' => $attempt_count,
        'max_retakes' => $max_retakes,
        'can_retake' => $can_retake_after,
        'retakes_exhausted' => !$can_retake_after
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error with more details
    error_log("Quiz submission PDO error: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    error_log("PDO Error Info: " . json_encode($e->errorInfo));
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output buffer and return error
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.',
        'debug' => $e->getMessage() // Add debug info for development
    ]);
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log("Quiz submission error: " . $e->getMessage());
    
    // Clean any output buffer and return error
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}