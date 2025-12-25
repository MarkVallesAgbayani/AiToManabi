<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'check_username':
            $username = $_POST['username'] ?? '';
            
            if (empty($username)) {
                echo json_encode(['error' => 'Username is required']);
                exit();
            }
            
            // Validate username format
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                echo json_encode(['error' => 'Invalid username format']);
                exit();
            }
            
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $exists = $stmt->rowCount() > 0;
            
            echo json_encode(['exists' => $exists]);
            break;
            
        case 'check_email':
            $email = $_POST['email'] ?? '';
            
            if (empty($email)) {
                echo json_encode(['error' => 'Email is required']);
                exit();
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['error' => 'Invalid email format']);
                exit();
            }
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $exists = $stmt->rowCount() > 0;
            
            echo json_encode(['exists' => $exists]);
            break;
            
        case 'check_phone':
            $phone_number = $_POST['phone_number'] ?? '';
            $country_code = $_POST['country_code'] ?? '+63';
            
            if (empty($phone_number)) {
                echo json_encode(['error' => 'Phone number is required']);
                exit();
            }
            
            // CRITICAL FIX: Remove ALL spaces and non-digit characters first
            $cleaned_input = preg_replace('/[^\d]/', '', $phone_number);
            
            error_log("Phone Check - Raw Input: '$phone_number'");
            error_log("Phone Check - Cleaned Input: '$cleaned_input'");
            error_log("Phone Check - Country Code: '$country_code'");
            
            // Normalize country code (remove +)
            $cleaned_country_code = preg_replace('/[^\d]/', '', $country_code);
            
            // NORMALIZE PHILIPPINE MOBILE NUMBERS: Remove leading 0 if present
            if ($cleaned_country_code === '63' && substr($cleaned_input, 0, 1) === '0') {
                $cleaned_input = substr($cleaned_input, 1);
                error_log("Phone Check - Removed leading 0: '$cleaned_input'");
            }
            
            // Build the full normalized phone number
            $full_phone = $cleaned_country_code . $cleaned_input;
            
            error_log("Phone Check - Full Normalized: '$full_phone'");
            
            // Enhanced phone check: Search ALL accounts (active, deleted, banned, etc.)
            // Use MySQL REPLACE to normalize database phone numbers for comparison
            // This removes +, spaces, and dashes from database values
            $sql = "SELECT id, phone_number, status, deleted_at FROM users 
                    WHERE REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', '') = ?";
            
            error_log("Phone Check - SQL Query: $sql");
            error_log("Phone Check - Search Parameter: '$full_phone'");
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_phone]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $exists = $result !== false;
            
            if ($exists) {
                error_log("Phone Check - FOUND! ID: {$result['id']}, Phone: {$result['phone_number']}, Status: {$result['status']}, Deleted: " . ($result['deleted_at'] ? 'YES' : 'NO'));
            } else {
                error_log("Phone Check - NOT FOUND (available)");
            }
            
            echo json_encode(['exists' => $exists]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log('Database error in check_availability.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('General error in check_availability.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
?>
