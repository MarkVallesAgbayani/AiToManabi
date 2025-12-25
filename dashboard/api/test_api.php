<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Simple API test endpoint
echo json_encode([
    'success' => true,
    'message' => 'API connection successful',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>