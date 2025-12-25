<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    sendJsonResponse(false, 'Unauthorized access', null, 401);
    exit();
}

// Get request data
$requestData = json_decode(file_get_contents('php://input'), true);

if (!isset($requestData['action'])) {
    sendJsonResponse(false, 'Missing action parameter', null, 400);
    exit();
}

$action = $requestData['action'];

try {
    switch ($action) {
        case 'add':
            // Validate name parameter
            if (!isset($requestData['name']) || empty(trim($requestData['name']))) {
                sendJsonResponse(false, 'Category name is required', null, 400);
                exit();
            }
            
            $name = trim($requestData['name']);
            
            // Check if category already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                sendJsonResponse(false, 'Category with this name already exists', null, 400);
                exit();
            }
            
            // Insert new category
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
            
            $category_id = $pdo->lastInsertId();
            
            sendJsonResponse(true, 'Category added successfully', ['category_id' => $category_id]);
            break;
            
        case 'update':
            // Validate required parameters
            if (!isset($requestData['id']) || !is_numeric($requestData['id'])) {
                sendJsonResponse(false, 'Invalid category ID', null, 400);
                exit();
            }
            
            if (!isset($requestData['name']) || empty(trim($requestData['name']))) {
                sendJsonResponse(false, 'Category name is required', null, 400);
                exit();
            }
            
            $id = (int)$requestData['id'];
            $name = trim($requestData['name']);
            
            // Check if category exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() == 0) {
                sendJsonResponse(false, 'Category not found', null, 404);
                exit();
            }
            
            // Check if name already exists for another category
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn() > 0) {
                sendJsonResponse(false, 'Another category with this name already exists', null, 400);
                exit();
            }
            
            // Update category
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            
            sendJsonResponse(true, 'Category updated successfully');
            break;
            
        case 'delete':
            // Validate ID parameter
            if (!isset($requestData['id']) || !is_numeric($requestData['id'])) {
                sendJsonResponse(false, 'Invalid category ID', null, 400);
                exit();
            }
            
            $id = (int)$requestData['id'];
            
            // Check if category exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() == 0) {
                sendJsonResponse(false, 'Category not found', null, 404);
                exit();
            }
            
            // Check if category is being used by any courses
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                sendJsonResponse(false, 'Cannot delete category as it is being used by one or more courses', null, 400);
                exit();
            }
            
            // Delete category
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            
            sendJsonResponse(true, 'Category deleted successfully');
            break;
            
        default:
            sendJsonResponse(false, 'Invalid action', null, 400);
    }
} catch (Exception $e) {
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}

// Function to handle JSON responses
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
?> 