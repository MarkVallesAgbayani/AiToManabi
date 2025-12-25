<?php
/**
 * Global Authentication Check
 * Include this at the top of every protected page
 */

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /AIToManabi_Updated/dashboard/login.php");
    exit();
}

// Load database connection FIRST (if not already loaded)
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

// Validate session is still active (not banned/deleted)
require_once __DIR__ . '/session_validator.php';
$sessionValidator = new SessionValidator($pdo);

if (!$sessionValidator->isSessionValid($_SESSION['user_id'])) {
    // Determine appropriate message based on user status
    try {
        $stmt = $pdo->prepare("SELECT status, deleted_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        $message = 'Your session has been terminated.';
        if ($user && $user['status'] === 'banned') {
            $message = 'Your account has been banned. Please contact support for more information.';
        } elseif ($user && $user['deleted_at'] !== null) {
            $message = 'Your account has been deleted. Please contact support if you believe this is an error.';
        }
    } catch (Exception $e) {
        $message = 'Your session has been terminated.';
    }
    
    $sessionValidator->forceLogout($message);
}

// Session is valid, continue with page logic
?>
