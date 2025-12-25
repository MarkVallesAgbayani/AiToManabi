<?php
/**
 * Enhanced Login Logger for Production
 * Handles real-time login logging with proper IP detection and location services
 */

class EnhancedLoginLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log successful login attempt
     */
    public function logSuccessfulLogin($userId, $additionalData = []) {
        try {
            $ipAddress = $this->getRealIPAddress();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $location = $this->getLocationFromIP($ipAddress);
            
            $sql = "INSERT INTO login_logs (
                user_id, 
                login_time, 
                ip_address, 
                user_agent, 
                status, 
                location,
                device_type,
                browser_name,
                operating_system,
                session_id,
                created_at
            ) VALUES (?, NOW(), ?, ?, 'success', ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $ipAddress,
                $userAgent,
                $location,
                $this->getDeviceType($userAgent),
                $this->getBrowserName($userAgent),
                $this->getOperatingSystem($userAgent),
                session_id()
            ]);
            
            // Also log to comprehensive audit trail
            $this->logToAuditTrail($userId, 'login_success', 'User logged in successfully', [
                'ip_address' => $ipAddress,
                'location' => $location,
                'user_agent' => $userAgent
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Login logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log failed login attempt
     */
    public function logFailedLogin($emailOrUsername, $reason = 'invalid_credentials') {
        try {
            $ipAddress = $this->getRealIPAddress();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $location = $this->getLocationFromIP($ipAddress);
            
            // Try to get user_id if email/username exists
            $userId = $this->getUserIdByEmailOrUsername($emailOrUsername);
            
            $sql = "INSERT INTO login_logs (
                user_id, 
                login_time, 
                ip_address, 
                user_agent, 
                status, 
                location,
                device_type,
                browser_name,
                operating_system,
                session_id,
                created_at
            ) VALUES (?, NOW(), ?, ?, 'failed', ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $ipAddress,
                $userAgent,
                $location,
                $this->getDeviceType($userAgent),
                $this->getBrowserName($userAgent),
                $this->getOperatingSystem($userAgent),
                session_id()
            ]);
            
            // Also log to comprehensive audit trail
            $this->logToAuditTrail($userId, 'login_failed', "Failed login attempt: $reason", [
                'ip_address' => $ipAddress,
                'location' => $location,
                'user_agent' => $userAgent,
                'attempted_credential' => $emailOrUsername
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed login logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get real IP address (handles proxies, load balancers, etc.)
     */
    private function getRealIPAddress() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (take the first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR even if it's private
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get location from IP address (production-ready with fallback)
     */
    private function getLocationFromIP($ipAddress) {
        // Skip location lookup for private/local IPs
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local Network';
        }
        
        // For production, you can use a service like ipapi.co, ipinfo.io, or ip-api.com
        // This is a free service with rate limits - for production, consider a paid service
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3, // 3 second timeout
                    'user_agent' => 'Japanese Learning Platform/1.0'
                ]
            ]);
            
            $response = @file_get_contents("http://ip-api.com/json/$ipAddress?fields=status,country,city,region", false, $context);
            
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
    
    /**
     * Get device type from user agent
     */
    public function getDeviceType($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'mobile') !== false || 
            strpos($userAgent, 'android') !== false || 
            strpos($userAgent, 'iphone') !== false || 
            strpos($userAgent, 'ipad') !== false) {
            return 'Mobile';
        } elseif (strpos($userAgent, 'tablet') !== false) {
            return 'Tablet';
        } else {
            return 'Desktop';
        }
    }
    
    /**
     * Get browser name from user agent
     */
    public function getBrowserName($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'edge') !== false) {
            return 'Edge';
        } elseif (strpos($userAgent, 'opera') !== false) {
            return 'Opera';
        } else {
            return 'Unknown';
        }
    }
    
    /**
     * Get operating system from user agent
     */
    public function getOperatingSystem($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'mac') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'ios') !== false || strpos($userAgent, 'iphone') !== false) {
            return 'iOS';
        } else {
            return 'Unknown';
        }
    }
    
    /**
     * Get user ID by email or username
     */
    private function getUserIdByEmailOrUsername($emailOrUsername) {
        try {
            if (filter_var($emailOrUsername, FILTER_VALIDATE_EMAIL)) {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            } else {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
            }
            $stmt->execute([$emailOrUsername]);
            $result = $stmt->fetch();
            return $result ? $result['id'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Log to comprehensive audit trail
     */
    private function logToAuditTrail($userId, $actionType, $description, $additionalData = []) {
        try {
            // Get user info
            $stmt = $this->pdo->prepare("SELECT username, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) return;
            
            $sql = "INSERT INTO comprehensive_audit_trail (
                timestamp, user_id, username, user_role, action_type, action_description,
                resource_type, resource_id, resource_name, ip_address, outcome,
                browser_name, operating_system, device_type, location_city, location_country,
                session_id, request_method, request_url, created_at
            ) VALUES (
                NOW(), ?, ?, ?, ?, ?, 'user', ?, ?, ?, 'Success',
                ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )";
            
            $locationParts = explode(', ', $additionalData['location'] ?? '');
            $city = $locationParts[0] ?? null;
            $country = end($locationParts) ?? null;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $user['username'],
                $user['role'],
                $actionType,
                $description,
                $userId,
                $user['username'],
                $additionalData['ip_address'] ?? '',
                $this->getBrowserName($additionalData['user_agent'] ?? ''),
                $this->getOperatingSystem($additionalData['user_agent'] ?? ''),
                $this->getDeviceType($additionalData['user_agent'] ?? ''),
                $city,
                $country,
                session_id(),
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_SERVER['REQUEST_URI'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Audit trail logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Get recent login statistics
     */
    public function getLoginStatistics($days = 7) {
        try {
            $sql = "SELECT 
                        DATE(login_time) as date,
                        COUNT(*) as total_logins,
                        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_logins,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_logins,
                        COUNT(DISTINCT user_id) as unique_users
                    FROM login_logs 
                    WHERE login_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY DATE(login_time)
                    ORDER BY date DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Login statistics error: " . $e->getMessage());
            return [];
        }
    }
}
?>
