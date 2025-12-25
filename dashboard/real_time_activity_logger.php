<?php
/**
 * Real-Time User Activity Logger
 * Tracks user activities across the platform for usage analytics
 */

class RealTimeActivityLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log user activity for analytics
     */
    public function logActivity($userId, $activityType, $resourceType = null, $resourceId = null, $additionalData = []) {
        try {
            // Get user info
            $stmt = $this->pdo->prepare("SELECT username, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) return false;
            
            // Get real IP and device info
            $ipAddress = $this->getRealIPAddress();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $deviceType = $this->getDeviceType($userAgent);
            $browserName = $this->getBrowserName($userAgent);
            $operatingSystem = $this->getOperatingSystem($userAgent);
            
            // Insert into user_activity_log
            $sql = "INSERT INTO user_activity_log (
                user_id, username, activity_type, resource_type, resource_id,
                ip_address, user_agent, device_type, browser_name, operating_system,
                session_id, created_at, additional_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $user['username'],
                $activityType,
                $resourceType,
                $resourceId,
                $ipAddress,
                $userAgent,
                $deviceType,
                $browserName,
                $operatingSystem,
                session_id(),
                json_encode($additionalData)
            ]);
            
            // Also log to comprehensive audit trail if it exists
            $this->logToAuditTrail($userId, $user, $activityType, $resourceType, $resourceId, $additionalData);
            
            return true;
        } catch (PDOException $e) {
            error_log("Activity logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log page view activity
     */
    public function logPageView($pageName, $additionalData = []) {
        if (!isset($_SESSION['user_id'])) return false;
        
        return $this->logActivity(
            $_SESSION['user_id'],
            'page_view',
            'page',
            $pageName,
            array_merge($additionalData, [
                'page_url' => $_SERVER['REQUEST_URI'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
            ])
        );
    }
    
    /**
     * Log course activity
     */
    public function logCourseActivity($courseId, $activityType, $additionalData = []) {
        if (!isset($_SESSION['user_id'])) return false;
        
        return $this->logActivity(
            $_SESSION['user_id'],
            $activityType,
            'course',
            $courseId,
            $additionalData
        );
    }
    
    /**
     * Log lesson activity
     */
    public function logLessonActivity($lessonId, $activityType, $additionalData = []) {
        if (!isset($_SESSION['user_id'])) return false;
        
        return $this->logActivity(
            $_SESSION['user_id'],
            $activityType,
            'lesson',
            $lessonId,
            $additionalData
        );
    }
    
    /**
     * Log quiz activity
     */
    public function logQuizActivity($quizId, $activityType, $score = null, $additionalData = []) {
        if (!isset($_SESSION['user_id'])) return false;
        
        $data = $additionalData;
        if ($score !== null) {
            $data['score'] = $score;
        }
        
        return $this->logActivity(
            $_SESSION['user_id'],
            $activityType,
            'quiz',
            $quizId,
            $data
        );
    }
    
    /**
     * Get real IP address
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
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get device type from user agent
     */
    private function getDeviceType($userAgent) {
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
    private function getBrowserName($userAgent) {
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
    private function getOperatingSystem($userAgent) {
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
     * Log to comprehensive audit trail
     */
    private function logToAuditTrail($userId, $user, $activityType, $resourceType, $resourceId, $additionalData) {
        try {
            if (!$this->tableExists('comprehensive_audit_trail')) return;
            
            $sql = "INSERT INTO comprehensive_audit_trail (
                timestamp, user_id, username, user_role, action_type, action_description,
                resource_type, resource_id, resource_name, ip_address, outcome,
                browser_name, operating_system, device_type, session_id, request_method, request_url, created_at
            ) VALUES (
                NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Success',
                ?, ?, ?, ?, ?, ?, NOW()
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $user['username'],
                $user['role'],
                'READ', // Default action type for analytics
                "User activity: $activityType",
                $resourceType,
                $resourceId,
                $resourceId, // Use resource_id as resource_name for now
                $this->getRealIPAddress(),
                $this->getBrowserName($_SERVER['HTTP_USER_AGENT'] ?? ''),
                $this->getOperatingSystem($_SERVER['HTTP_USER_AGENT'] ?? ''),
                $this->getDeviceType($_SERVER['HTTP_USER_AGENT'] ?? ''),
                session_id(),
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_SERVER['REQUEST_URI'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Audit trail logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName) {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$tableName'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get activity statistics
     */
    public function getActivityStats($days = 7) {
        try {
            $sql = "SELECT 
                        DATE(ual.created_at) as date,
                        COUNT(DISTINCT ual.user_id) as unique_users,
                        COUNT(*) as total_activities,
                        COUNT(DISTINCT CASE WHEN u.role = 'student' THEN ual.user_id END) as unique_students,
                        COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN ual.user_id END) as unique_teachers,
                        COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN ual.user_id END) as unique_admins
                    FROM user_activity_log ual
                    JOIN users u ON ual.user_id = u.id
                    WHERE ual.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY DATE(ual.created_at)
                    ORDER BY date DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Activity stats error: " . $e->getMessage());
            return [];
        }
    }
}

// Global function for easy access
function logUserActivity($activityType, $resourceType = null, $resourceId = null, $additionalData = []) {
    if (!isset($_SESSION['user_id'])) return false;
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $logger = new RealTimeActivityLogger($pdo);
        return $logger->logActivity($_SESSION['user_id'], $activityType, $resourceType, $resourceId, $additionalData);
    } catch (Exception $e) {
        error_log("Global activity logging error: " . $e->getMessage());
        return false;
    }
}

// Auto-log page views
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $logger = new RealTimeActivityLogger($pdo);
        $logger->logPageView(basename($_SERVER['PHP_SELF']));
    } catch (Exception $e) {
        // Silent fail for auto-logging
    }
}
?>
