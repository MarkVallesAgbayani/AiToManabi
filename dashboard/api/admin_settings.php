<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Handle GET request - Load settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get basic user info
        $sql = "SELECT id, username, email FROM users WHERE id = ? AND role = 'admin'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Get preferences from admin_preferences table
        $sql = "SELECT display_name, profile_picture FROM admin_preferences WHERE admin_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $displayName = $prefs['display_name'] ?? $user['username'];
        $profilePicture = $prefs['profile_picture'] ?? '';
        
        $response = [
            'success' => true,
            'data' => [
                'profile' => [
                    'userId' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'displayName' => $displayName,
                    'profilePicture' => $profilePicture
                ]
            ]
        ];
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        error_log("Error loading admin settings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Handle POST request - Save settings
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['profile'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }
        
        $displayName = $input['profile']['displayName'] ?? '';
        $profilePicture = $input['profile']['profilePicture'] ?? '';
        
        // Check if preferences record exists
        $stmt = $pdo->prepare("SELECT id FROM admin_preferences WHERE admin_id = ?");
        $stmt->execute([$userId]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing record
            $sql = "UPDATE admin_preferences 
                    SET display_name = ?,
                        profile_picture = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE admin_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$displayName, $profilePicture, $userId]);
        } else {
            // Insert new record
            $sql = "INSERT INTO admin_preferences (admin_id, display_name, profile_picture) 
                    VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $displayName, $profilePicture]);
        }
        
        // Log to audit trail
        try {
            $auditSql = "INSERT INTO audit_trail (user_id, action, details, created_at) 
                         VALUES (?, 'admin_settings_update', 'Admin updated profile settings', NOW())";
            $auditStmt = $pdo->prepare($auditSql);
            $auditStmt->execute([$userId]);
        } catch (PDOException $auditError) {
            // Log error but don't fail the main operation
            error_log("Audit trail logging failed: " . $auditError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);
        
    } catch (PDOException $e) {
        error_log("Error saving admin settings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
