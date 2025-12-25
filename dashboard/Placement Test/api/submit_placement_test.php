<?php
// Clear any previous output
ob_clean();

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');

require_once '../../../config/database.php';

// Function to send JSON response
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

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $raw_input = file_get_contents('php://input');
        $input = json_decode($raw_input, true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg());
        }
        
        // Log the input for debugging
        error_log("API received input: " . print_r($input, true));

        $test_id = (int)($input['test_id'] ?? 0);
        $session_token = trim($input['session_token'] ?? '');
        $answers = $input['answers'] ?? [];
        $skipped = (bool)($input['skipped'] ?? false);
        
        if (!$test_id || !$session_token) {
            throw new Exception('Missing required parameters');
        }

        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
            throw new Exception('User not logged in');
        }
        
        $student_id = (int)$_SESSION['user_id'];

        // Verify test exists and is published
        $stmt = $pdo->prepare("SELECT * FROM placement_test WHERE id = ? AND is_published = 1");
        $stmt->execute([$test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            throw new Exception('Test not found or not published');
        }

        // Decode test data
        $test['questions'] = json_decode($test['questions'], true) ?? [];
        $test['module_assignments'] = json_decode($test['module_assignments'], true) ?? [];

        // Check if student has already taken this test
        $stmt = $pdo->prepare("SELECT id FROM placement_result WHERE student_id = ? AND test_id = ?");
        $stmt->execute([$student_id, $test_id]);
        if ($stmt->fetch()) {
            throw new Exception('You have already taken this placement test');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Calculate results
        $total_questions = count($test['questions']);
        $correct_answers = 0;
        $difficulty_scores = [
            'beginner' => ['correct' => 0, 'total' => 0], 
            'intermediate' => ['correct' => 0, 'total' => 0], 
            'advanced' => ['correct' => 0, 'total' => 0]
        ];
        $recommended_level = 'beginner';
        $recommended_course_id = null;
        $detailed_feedback = '';
        $percentage_score = 0;

        if (!$skipped) {
            // Process answers
            error_log("Processing answers: " . print_r($answers, true));
            error_log("Test questions count: " . count($test['questions']));
            
            foreach ($test['questions'] as $index => $question) {
                $user_answer = $answers[$index] ?? null;
                
                if ($user_answer !== null && isset($question['choices'][$user_answer])) {
                    $is_correct = $question['choices'][$user_answer]['is_correct'] ?? false;
                    if ($is_correct) {
                        $correct_answers++;
                    }
                    
                    // Track difficulty scores
                    $difficulty = $question['difficulty_level'] ?? 'beginner';
                    if (isset($difficulty_scores[$difficulty])) {
                        $difficulty_scores[$difficulty]['total']++;
                        if ($is_correct) {
                            $difficulty_scores[$difficulty]['correct']++;
                        }
                    }
                } else {
                    // Count unanswered questions towards total for their difficulty level
                    $difficulty = $question['difficulty_level'] ?? 'beginner';
                    if (isset($difficulty_scores[$difficulty])) {
                        $difficulty_scores[$difficulty]['total']++;
                    }
                }
            }

            // Determine recommended level based on scoring system
            $beginner_score = $difficulty_scores['beginner']['correct'] ?? 0;
            $beginner_total = $difficulty_scores['beginner']['total'] ?? 0;
            $intermediate_score = $difficulty_scores['intermediate']['correct'] ?? 0;
            $intermediate_total = $difficulty_scores['intermediate']['total'] ?? 0;
            $advanced_score = $difficulty_scores['advanced']['correct'] ?? 0;
            $advanced_total = $difficulty_scores['advanced']['total'] ?? 0;
            
            // Calculate placement based on criteria
            if ($beginner_score >= 6 && $beginner_total >= 7) {
                // Clearly past the basics
                if ($intermediate_score >= 2 && $intermediate_total >= 6) {
                    // Good at intermediate too - Advanced Beginner
                    $recommended_level = 'advanced_beginner';
                } else {
                    // Intermediate Beginner
                    $recommended_level = 'intermediate_beginner';
                }
            } else {
                // True beginner
                $recommended_level = 'beginner';
            }
            
            // Calculate overall percentage for display
            $percentage_score = $total_questions > 0 ? ($correct_answers / $total_questions) * 100 : 0;

            // Get recommended course
            if (isset($test['module_assignments'][$recommended_level]) && !empty($test['module_assignments'][$recommended_level])) {
                $recommended_course = $test['module_assignments'][$recommended_level][0];
                $recommended_course_id = $recommended_course['course_id'] ?? null;
            }

            // Create detailed feedback based on scoring system
            $detailed_feedback = "Based on your performance: ";
            $detailed_feedback .= "Beginner: {$beginner_score}/{$beginner_total}, ";
            $detailed_feedback .= "Intermediate: {$intermediate_score}/{$intermediate_total}, ";
            $detailed_feedback .= "Advanced: {$advanced_score}/{$advanced_total}. ";
            
            // Get recommended modules from placement test configuration
            $module_assignments = $test['module_assignments'] ?? [];
            $assigned_modules = $module_assignments[$recommended_level] ?? [];
            
            if ($recommended_level === 'advanced_beginner') {
                $detailed_feedback .= "You're an Advanced Beginner!";
            } elseif ($recommended_level === 'intermediate_beginner') {
                $detailed_feedback .= "You're an Intermediate Beginner!";
            } else {
                $detailed_feedback .= "You're a Beginner!";
            }
            
            // Add module recommendations
            if (!empty($assigned_modules)) {
                $first_module = $assigned_modules[0];
                $last_module = end($assigned_modules);
                
                if (count($assigned_modules) == 1) {
                    $detailed_feedback .= " Start with " . ($first_module['title'] ?? 'Module 1') . ".";
                } else {
                    $detailed_feedback .= " Start with " . ($first_module['title'] ?? 'Module 1') . " - " . ($last_module['title'] ?? 'Final Module') . ".";
                }
            } else {
                $detailed_feedback .= " No specific modules assigned for this level.";
            }
        } else {
            $detailed_feedback = "You chose to skip the placement test. You have been assigned to the beginner level.";
        }

        // Save placement result
        error_log("About to save placement result...");
        error_log("Data: student_id=$student_id, test_id=$test_id, total_questions=$total_questions, correct_answers=$correct_answers");
        
        $stmt = $pdo->prepare("
            INSERT INTO placement_result (
                student_id, test_id, session_token, answers, total_questions, 
                correct_answers, percentage_score, difficulty_scores, 
                recommended_level, recommended_course_id, detailed_feedback, 
                status, ip_address, user_agent, completed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
        ");
        
        // Prepare insert data with proper null handling
        $insert_data = [
            $student_id,
            $test_id,
            $session_token,
            json_encode($answers),
            $total_questions,
            $correct_answers,
            round($percentage_score, 2),
            json_encode($difficulty_scores),
            $recommended_level,
            $recommended_course_id,
            $detailed_feedback,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        error_log("Insert data prepared, executing...");
        $stmt->execute($insert_data);
        error_log("Placement result saved successfully");

        // Update session status
        $stmt = $pdo->prepare("UPDATE placement_session SET status = 'completed' WHERE session_token = ?");
        $stmt->execute([$session_token]);

        // Commit transaction
        $pdo->commit();

        sendJsonResponse(true, 'Placement test submitted successfully', [
            'recommended_level' => $recommended_level,
            'recommended_course_id' => $recommended_course_id,
            'percentage_score' => round($percentage_score, 2),
            'correct_answers' => $correct_answers,
            'total_questions' => $total_questions,
            'skipped' => $skipped
        ]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Database error in placement test submission: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        sendJsonResponse(false, 'Database error occurred. Please try again.', null, 500);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error submitting placement test: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage(), null, 400);
    }
} else {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}
?>