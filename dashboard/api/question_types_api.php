<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$teacher_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_saved_types':
            // Get teacher's saved question types
            $stmt = $pdo->prepare("SELECT question_type_id FROM teacher_question_types WHERE teacher_id = ? AND is_active = 1");
            $stmt->execute([$teacher_id]);
            $saved_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'success' => true,
                'saved_types' => $saved_types
            ]);
            break;
            
        case 'add_type':
            $question_type_id = $_POST['question_type_id'] ?? '';
            
            if (empty($question_type_id)) {
                echo json_encode(['success' => false, 'message' => 'Question type ID is required']);
                break;
            }
            
            // Check if already exists
            $checkStmt = $pdo->prepare("SELECT id FROM teacher_question_types WHERE teacher_id = ? AND question_type_id = ?");
            $checkStmt->execute([$teacher_id, $question_type_id]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update to active if exists
                $updateStmt = $pdo->prepare("UPDATE teacher_question_types SET is_active = 1 WHERE teacher_id = ? AND question_type_id = ?");
                $updateStmt->execute([$teacher_id, $question_type_id]);
            } else {
                // Insert new record
                $insertStmt = $pdo->prepare("INSERT INTO teacher_question_types (teacher_id, question_type_id, is_active) VALUES (?, ?, 1)");
                $insertStmt->execute([$teacher_id, $question_type_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Question type added successfully']);
            break;
            
        case 'remove_type':
            $question_type_id = $_POST['question_type_id'] ?? '';
            
            if (empty($question_type_id)) {
                echo json_encode(['success' => false, 'message' => 'Question type ID is required']);
                break;
            }
            
            // Set as inactive instead of deleting
            $stmt = $pdo->prepare("UPDATE teacher_question_types SET is_active = 0 WHERE teacher_id = ? AND question_type_id = ?");
            $stmt->execute([$teacher_id, $question_type_id]);
            
            echo json_encode(['success' => true, 'message' => 'Question type removed successfully']);
            break;
            
        case 'reset_to_defaults':
            // Remove all custom types and keep only defaults
            $stmt = $pdo->prepare("DELETE FROM teacher_question_types WHERE teacher_id = ?");
            $stmt->execute([$teacher_id]);
            
            // Add default types
            $defaultTypes = ['multiple_choice', 'true_false', 'pronunciation'];
            $insertStmt = $pdo->prepare("INSERT INTO teacher_question_types (teacher_id, question_type_id, is_active) VALUES (?, ?, 1)");
            
            foreach ($defaultTypes as $type) {
                $insertStmt->execute([$teacher_id, $type]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Reset to default question types successfully']);
            break;
            
        case 'get_available_types':
            // Return all available question types with their details
            $availableTypes = [
                // Basic Types
                'multiple_choice' => [
                    'id' => 'multiple_choice',
                    'name' => 'Multiple Choice',
                    'category' => 'Basic',
                    'description' => 'Standard multiple choice questions with selectable answers'
                ],
                'true_false' => [
                    'id' => 'true_false',
                    'name' => 'True/False',
                    'category' => 'Basic',
                    'description' => 'Simple true or false questions'
                ],
                'fill_blank' => [
                    'id' => 'fill_blank',
                    'name' => 'Fill in the Blank',
                    'category' => 'Basic',
                    'description' => 'Students fill in missing words or phrases'
                ],
                
                // Vocabulary Types
                'word_definition' => [
                    'id' => 'word_definition',
                    'name' => 'Word Definition',
                    'category' => 'Vocabulary',
                    'description' => 'Match words with their definitions'
                ],
                
                // Audio Types
                'pronunciation' => [
                    'id' => 'pronunciation',
                    'name' => 'Pronunciation Check',
                    'category' => 'Audio',
                    'description' => 'Voice recognition and pronunciation assessment'
                ],
                
                // Writing Types
                'sentence_translation' => [
                    'id' => 'sentence_translation',
                    'name' => 'Sentence Translation',
                    'category' => 'Writing',
                    'description' => 'Translate between Japanese and English'
                ],
            ];
            
            echo json_encode([
                'success' => true,
                'available_types' => $availableTypes
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database error in question_types_api.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in question_types_api.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
