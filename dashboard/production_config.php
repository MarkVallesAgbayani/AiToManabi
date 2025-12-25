<?php
/**
 * Production Configuration for Login Activity Reports
 * This file contains production-ready settings for Hostinger deployment
 */

// Production-ready IP detection settings
define('PRODUCTION_IP_HEADERS', [
    'HTTP_CF_CONNECTING_IP',     // Cloudflare
    'HTTP_CLIENT_IP',            // Proxy
    'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
    'HTTP_X_FORWARDED',          // Proxy
    'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
    'HTTP_FORWARDED_FOR',        // Proxy
    'HTTP_FORWARDED',            // Proxy
    'REMOTE_ADDR'                // Standard
]);

// Location service configuration
define('LOCATION_SERVICE_URL', 'http://ip-api.com/json/');
define('LOCATION_SERVICE_TIMEOUT', 3);
define('LOCATION_SERVICE_FIELDS', 'status,country,city,region');

// Rate limiting settings
define('LOGIN_RATE_LIMIT_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_WINDOW', 900); // 15 minutes in seconds
define('LOGIN_RATE_LIMIT_DELAY_MAX', 5); // Maximum delay in seconds

// Database optimization settings
define('LOGIN_LOGS_RETENTION_DAYS', 90); // Keep logs for 90 days
define('LOGIN_LOGS_BATCH_SIZE', 1000);   // Batch size for cleanup operations

// Security settings
define('LOGIN_LOG_PRIVATE_IPS', false);  // Set to true to log private IPs
define('LOGIN_LOG_FAILED_ATTEMPTS', true); // Log failed login attempts
define('LOGIN_LOG_SUCCESS_ATTEMPTS', true); // Log successful logins

// Performance settings
define('LOGIN_LOGS_PAGINATION_LIMIT', 20);
define('LOGIN_LOGS_EXPORT_LIMIT', 10000);

// Production environment detection
function isProductionEnvironment() {
    return !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) && 
           !str_contains($_SERVER['HTTP_HOST'] ?? '', '.local');
}

// Get real IP address for production
function getProductionIPAddress() {
    foreach (PRODUCTION_IP_HEADERS as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Handle comma-separated IPs (take the first one)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // For production, accept all IPs (including private ones)
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Production-ready location lookup
function getProductionLocation($ipAddress) {
    // Skip location lookup for private/local IPs in production
    if (!isProductionEnvironment() || 
        filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return 'Local Network';
    }
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => LOCATION_SERVICE_TIMEOUT,
                'user_agent' => 'Japanese Learning Platform/1.0'
            ]
        ]);
        
        $url = LOCATION_SERVICE_URL . $ipAddress . '?fields=' . LOCATION_SERVICE_FIELDS;
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $location = [];
                if (!empty($data['city'])) $location[] = $data['city'];
                if (!empty($data['region'])) $location[] = $data['region'];
                if (!empty($data['country'])) $location[] = $data['country'];
                
                return implode(', ', $location);
            }
        }
    } catch (Exception $e) {
        error_log("Location lookup error: " . $e->getMessage());
    }
    
    return 'Unknown Location';
}

// Production database optimization
function optimizeLoginLogsTable($pdo) {
    try {
        // Add indexes for better performance
        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_login_logs_user_time ON login_logs(user_id, login_time)',
            'CREATE INDEX IF NOT EXISTS idx_login_logs_status_time ON login_logs(status, login_time)',
            'CREATE INDEX IF NOT EXISTS idx_login_logs_ip_time ON login_logs(ip_address, login_time)'
        ];
        
        foreach ($indexes as $index) {
            $pdo->exec($index);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database optimization error: " . $e->getMessage());
        return false;
    }
}

// Cleanup old login logs (run periodically)
function cleanupOldLoginLogs($pdo) {
    try {
        $sql = "DELETE FROM login_logs WHERE login_time < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([LOGIN_LOGS_RETENTION_DAYS]);
        
        $deleted = $stmt->rowCount();
        error_log("Cleaned up $deleted old login log records");
        
        return $deleted;
    } catch (PDOException $e) {
        error_log("Login logs cleanup error: " . $e->getMessage());
        return false;
    }
}

// Production-ready error handling
function handleProductionError($error, $context = []) {
    $message = "Login Activity Error: " . $error;
    if (!empty($context)) {
        $message .= " Context: " . json_encode($context);
    }
    
    error_log($message);
    
    // In production, don't expose detailed errors to users
    if (isProductionEnvironment()) {
        return "An error occurred. Please try again later.";
    }
    
    return $error;
}

// Production-ready session security
function secureSessionForProduction() {
    if (isProductionEnvironment()) {
        // Use secure session settings for production
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
    }
}

// Initialize production settings
if (isProductionEnvironment()) {
    secureSessionForProduction();
}
?>
