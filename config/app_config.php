<?php
/**
 * Application Configuration
 * This file contains settings that need to be adjusted for different environments
 */

// Environment detection
function isProduction() {
    // Check if we're on a production server
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_localhost = in_array($host, ['localhost', '127.0.0.1', '::1']);
    $is_xampp = strpos($host, 'localhost') !== false;
    
    return !$is_localhost && !$is_xampp;
}

// Base URL configuration
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (isProduction()) {
        // Production (Hostinger) - your domain
        return $protocol . '://' . $host;
    } else {
        // Local development
        $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Extract project folder name from script path
        if (preg_match('/(\/[^\/]+)\//', $script_path, $matches)) {
            $project_root = $matches[1];
        } else {
            $project_root = '';
        }
        
        return $protocol . '://' . $host . $project_root;
    }
}

// Email configuration
function getEmailConfig() {
    if (isProduction()) {
        // Production email settings (you may want to use different credentials)
        return [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'aitomanabilms@gmail.com',
            'smtp_password' => 'efpn syzp lvqq ykvu',
            'from_email' => 'aitomanabilms@gmail.com',
            'from_name' => 'AiToManabi LMS'
        ];
    } else {
        // Local development email settings
        return [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'aitomanabilms@gmail.com',
            'smtp_password' => 'efpn syzp lvqq ykvu',
            'from_email' => 'aitomanabilms@gmail.com',
            'from_name' => 'AiToManabi LMS (Dev)'
        ];
    }
}

// Database configuration (if needed for different environments)
function getDatabaseConfig() {
    if (isProduction()) {
        // Production database settings (Hostinger)
        return [
            'host' => 'localhost', // Usually localhost on Hostinger
            'dbname' => 'u367042766_japanese_lms', // Replace with your actual database name
            'username' => 'u367042766_aitomanabi', // Replace with your actual username
            'password' => 'Aitomanabi12@' // Replace with your actual password
        ];
    } else {
        // Local development database settings
        return [
            'host' => 'localhost',
            'dbname' => 'japanese_lms',
            'username' => 'root',
            'password' => ''
        ];
    }
}

// Debug settings
function isDebugMode() {
    return !isProduction();
}

// App settings
define('APP_NAME', 'AiToManabi LMS');
define('APP_VERSION', '1.0.0');
define('APP_ENVIRONMENT', isProduction() ? 'production' : 'development');
define('APP_DEBUG', isDebugMode());
define('APP_BASE_URL', getBaseUrl());

// Email settings
$email_config = getEmailConfig();
define('SMTP_HOST', $email_config['smtp_host']);
define('SMTP_PORT', $email_config['smtp_port']);
define('SMTP_USERNAME', $email_config['smtp_username']);
define('SMTP_PASSWORD', $email_config['smtp_password']);
define('SMTP_SECURE', 'tls'); // Use TLS encryption for SMTP
define('FROM_EMAIL', $email_config['from_email']);
define('FROM_NAME', $email_config['from_name']);
?>
