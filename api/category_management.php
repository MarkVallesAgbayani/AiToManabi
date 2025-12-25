<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get all categories
            $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        case 'POST':
            // Add new category
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || empty(trim($data['name']))) {
                throw new Exception('Category name is required');
            }
            
            // Check if category already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $stmt->execute([trim($data['name'])]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Category already exists');
            }
            
            // Insert new category
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([trim($data['name'])]);
            
            echo json_encode([
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'message' => 'Category added successfully'
            ]);
            break;
            
        case 'PUT':
            // Update category
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['id']) || !isset($data['name']) || empty(trim($data['name']))) {
                throw new Exception('Category ID and name are required');
            }
            
            // Check if new name already exists for different category
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
            $stmt->execute([trim($data['name']), $data['id']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Category name already exists');
            }
            
            // Update category
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([trim($data['name']), $data['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete category
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($id <= 0) {
                throw new Exception('Invalid category ID');
            }
            
            // Check if category is in use
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete category that is in use');
            }
            
            // Delete category
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} 