<?php
/**
 * Test API connectivity
 */

session_start();

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'API connection successful',
    'timestamp' => date('Y-m-d H:i:s'),
    'user_logged_in' => isset($_SESSION['user_id']),
    'user_role' => $_SESSION['role'] ?? 'none'
]);
?>