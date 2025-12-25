<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';

// Set JSON header for AJAX responses
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if the password_reset_notification_shown column exists
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_notification_shown'")->fetch();
        
        if (!$columns) {
            echo json_encode([
                'success' => false,
                'message' => 'Notification column not available'
            ]);
            exit();
        }

        // Mark notification as shown
        $stmt = $pdo->prepare("UPDATE users SET password_reset_notification_shown = TRUE WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as shown'
        ]);

    } catch (Exception $e) {
        error_log("Mark notification error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error marking notification as shown'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
