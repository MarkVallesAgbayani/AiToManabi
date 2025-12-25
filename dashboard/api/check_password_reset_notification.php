<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';

// Set JSON header for AJAX responses
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, just log them

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    // Check if the password_reset_notification_shown column exists
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_notification_shown'")->fetch();
    
    if (!$columns) {
        // Column doesn't exist, user doesn't need notification
        echo json_encode([
            'success' => true,
            'needs_notification' => false,
            'message' => 'Notification column not available'
        ]);
        exit();
    }

    // Get user's notification status and check if their password was recently reset
    $stmt = $pdo->prepare("
        SELECT 
            u.password_reset_notification_shown, 
            u.updated_at,
            u.is_first_login,
            COUNT(ph.id) as recent_password_resets
        FROM users u
        LEFT JOIN password_history ph ON u.id = ph.user_id 
            AND ph.created_at > DATE_SUB(NOW(), INTERVAL 24 HOURS)
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    // Check if user needs to see notification
    $needsNotification = false;
    $resetInfo = null;

    // Show notification ONLY if:
    // 1. password_reset_notification_shown is explicitly FALSE (0) - meaning admin reset password and user hasn't seen notification yet
    // 2. AND there's a recent password reset in the last 24 hours (indicating admin reset)
    // 3. AND it's not just a first login scenario
    // 4. AND the user's account was recently updated (within last 24 hours) - indicating admin action
    $recentlyUpdated = strtotime($user['updated_at']) > (time() - 86400); // 24 hours ago
    
    if (($user['password_reset_notification_shown'] === '0' || $user['password_reset_notification_shown'] === 0) 
        && $user['recent_password_resets'] > 0 
        && !$user['is_first_login']
        && $recentlyUpdated) {
        
        $needsNotification = true;
        $resetInfo = [
            'reset_date' => $user['updated_at'],
            'reset_by' => 'Administrator'
        ];
    } else if (($user['password_reset_notification_shown'] === '0' || $user['password_reset_notification_shown'] === 0) 
               && (!$user['recent_password_resets'] || $user['is_first_login'] || !$recentlyUpdated)) {
        
        // Clean up: User has notification_shown = FALSE but doesn't meet criteria
        // This means they shouldn't see the notification, so mark it as shown
        try {
            $cleanup_stmt = $pdo->prepare("UPDATE users SET password_reset_notification_shown = TRUE WHERE id = ?");
            $cleanup_stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {
            error_log("Error cleaning up password reset notification: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'needs_notification' => $needsNotification,
        'reset_info' => $resetInfo
    ]);

} catch (Exception $e) {
    error_log("Password reset notification check error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking notification status'
    ]);
}
?>
