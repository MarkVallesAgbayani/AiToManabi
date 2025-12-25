<?php
session_start();
require_once '../config/database.php';

// Set content type for AJAX requests
header('Content-Type: application/json');

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    // Log the logout activity if user is logged in
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        try {
            require_once 'real_time_activity_logger.php';
            $logger = new RealTimeActivityLogger($pdo);
            $logger->logPageView('logout', [
                'page_title' => 'User Logout',
                'access_time' => date('Y-m-d H:i:s'),
                'logout_reason' => 'session_timeout'
            ]);
        } catch (Exception $e) {
            error_log("Activity logging error during logout: " . $e->getMessage());
        }
    }
    
    // Destroy session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear any additional cookies that might be set
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Clear any other authentication cookies
    $cookies_to_clear = ['auth_token', 'user_session', 'login_token'];
    foreach ($cookies_to_clear as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', time() - 3600, '/');
        }
    }
    
    // If this is an AJAX request, return JSON response
    if ($isAjax) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
        exit();
    }
    
    // For non-AJAX requests, redirect to login page
    $redirect_url = '../index.php';
    if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
        $redirect_url .= '?timeout=1';
        if (isset($_GET['message'])) {
            $redirect_url .= '&message=' . urlencode($_GET['message']);
        }
    }
    
    header("Location: " . $redirect_url);
    exit();
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    
    // Even if there's an error, try to destroy the session
    $_SESSION = array();
    session_destroy();
    
    if ($isAjax) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error during logout'
        ]);
        exit();
    }
    
    header("Location: ../index.php?error=logout_failed");
    exit();
}
?>