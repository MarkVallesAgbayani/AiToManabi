<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// If user is not logged in, return invalid
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'valid' => false,
        'reason' => 'Not logged in'
    ]);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session_validator.php';

$sessionValidator = new SessionValidator($pdo);
$isValid = $sessionValidator->isSessionValid($_SESSION['user_id']);

if (!$isValid) {
    // Get the reason
    try {
        $stmt = $pdo->prepare("SELECT status, deleted_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $user['status'] === 'banned') {
            $reason = 'Your account has been banned.';
        } elseif ($user && $user['deleted_at'] !== null) {
            $reason = 'Your account has been deleted.';
        } else {
            $reason = 'Your session has been terminated.';
        }
    } catch (Exception $e) {
        $reason = 'Your session has been terminated.';
    }
    
    echo json_encode([
        'valid' => false,
        'reason' => $reason
    ]);
} else {
    echo json_encode([
        'valid' => true
    ]);
}
?>
