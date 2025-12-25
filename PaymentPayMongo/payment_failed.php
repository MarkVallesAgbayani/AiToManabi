<?php
require_once 'config.php';
require_once '../config/database.php';

// Get session ID if available
$sessionId = $_GET['session_id'] ?? null;

if ($sessionId) {
    // Update payment session status to failed
    $stmt = $conn->prepare("UPDATE payment_sessions SET status = 'failed', completed_at = NOW() WHERE checkout_session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
}

// Redirect to dashboard with error message
header('Location: ../dashboard/student_dashboard.php?error=payment_failed');
exit; 