<?php
/**
 * Enhanced Audit Logger with IPv4/IPv6 Support
 * Provides easy-to-use functions for logging audit entries
 */

require_once 'ip_address_utils.php';

class AuditLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log an audit entry with enhanced IP and device detection
     */
    public function logEntry($params) {
        try {
            // Get real IP address
            $ipAddress = IPAddressUtils::getRealIPAddress();
            
            // Get user agent and parse device info
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent';
            $deviceInfo = $this->parseUserAgent($userAgent);
            
            // Get location info (placeholder for now)
            $locationInfo = IPAddressUtils::getLocationInfo($ipAddress);
            
            // Get user info
            $userId = $params['user_id'] ?? $_SESSION['user_id'] ?? null;
            $username = $params['username'] ?? $_SESSION['username'] ?? 'Unknown User';
            $userRole = $params['user_role'] ?? $_SESSION['role'] ?? 'unknown';
            
            // Prepare audit entry data
            $auditData = [
                'user_id' => $userId,
                'username' => $username,
                'user_role' => $userRole,
                'action_type' => $params['action_type'],
                'action_description' => $params['action_description'],
                'resource_type' => $params['resource_type'],
                'resource_id' => $params['resource_id'] ?? null,
                'resource_name' => $params['resource_name'] ?? null,
                'ip_address' => $ipAddress,
                'outcome' => $params['outcome'] ?? 'Success',
                'old_value_text' => $params['old_value'] ?? null,
                'new_value_text' => $params['new_value'] ?? null,
                'device_info' => $userAgent,
                'browser_name' => $deviceInfo['browser'],
                'operating_system' => $deviceInfo['os'],
                'device_type' => $deviceInfo['device_type'],
                'location_city' => $locationInfo['city'],
                'location_country' => $locationInfo['country'],
                'session_id' => session_id(),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'request_url' => $_SERVER['REQUEST_URI'] ?? null,
                'additional_context' => isset($params['context']) ? json_encode($params['context']) : null
            ];
            
            // Try to use comprehensive audit trail table first
            if ($this->tableExists('comprehensive_audit_trail')) {
                $this->insertComprehensiveAudit($auditData);
            } else {
                // Fall back to existing tables
                $this->insertFallbackAudit($auditData);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Audit logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert into comprehensive audit trail table
     */
    private function insertComprehensiveAudit($data) {
        $sql = "INSERT INTO comprehensive_audit_trail (
            user_id, username, user_role, action_type, action_description,
            resource_type, resource_id, resource_name, ip_address, outcome,
            old_value_text, new_value_text, device_info, browser_name,
            operating_system, device_type, location_city, location_country,
            session_id, request_method, request_url, additional_context
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['user_id'], $data['username'], $data['user_role'],
            $data['action_type'], $data['action_description'],
            $data['resource_type'], $data['resource_id'], $data['resource_name'],
            $data['ip_address'], $data['outcome'],
            $data['old_value_text'], $data['new_value_text'],
            $data['device_info'], $data['browser_name'],
            $data['operating_system'], $data['device_type'],
            $data['location_city'], $data['location_country'],
            $data['session_id'], $data['request_method'],
            $data['request_url'], $data['additional_context']
        ]);
    }
    
    /**
     * Insert into fallback tables (admin_audit_log, etc.)
     */
    private function insertFallbackAudit($data) {
        // Insert into admin_audit_log if user is admin
        if ($data['user_role'] === 'admin' && $this->tableExists('admin_audit_log')) {
            $sql = "INSERT INTO admin_audit_log (admin_id, action, details, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['user_id'],
                $data['action_description'],
                $data['additional_context'],
                $data['ip_address'],
                $data['device_info']
            ]);
        }
        
        // Insert into audit_trail if resource is course-related
        if (in_array($data['resource_type'], ['Course', 'Lesson', 'Chapter']) && $this->tableExists('audit_trail')) {
            $courseId = null;
            if (preg_match('/Course ID: (\d+)/', $data['resource_id'] ?? '', $matches)) {
                $courseId = $matches[1];
            }
            
            $sql = "INSERT INTO audit_trail (user_id, course_id, action, details) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['user_id'],
                $courseId,
                $data['action_description'],
                $data['additional_context']
            ]);
        }
    }
    
    /**
     * Parse user agent string to extract device information
     */
    private function parseUserAgent($userAgent) {
        $browser = 'Unknown Browser';
        $os = 'Unknown OS';
        $deviceType = 'Desktop';
        
        // Browser detection
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches) && !strpos($userAgent, 'Chrome')) {
            $browser = 'Safari ' . $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        } elseif (preg_match('/Opera\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Opera ' . $matches[1];
        }
        
        // OS detection
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
            $versions = [
                '10.0' => 'Windows 10/11',
                '6.3' => 'Windows 8.1',
                '6.2' => 'Windows 8',
                '6.1' => 'Windows 7'
            ];
            $os = $versions[$matches[1]] ?? 'Windows ' . $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
            $version = str_replace('_', '.', $matches[1]);
            $os = 'macOS ' . $version;
        } elseif (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
            $os = 'Android ' . $matches[1];
            $deviceType = 'Mobile';
        } elseif (preg_match('/iPhone OS ([0-9_]+)/', $userAgent, $matches)) {
            $version = str_replace('_', '.', $matches[1]);
            $os = 'iOS ' . $version;
            $deviceType = 'Mobile';
        } elseif (preg_match('/iPad/', $userAgent)) {
            $os = 'iPadOS';
            $deviceType = 'Tablet';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        }
        
        // Device type detection
        if (preg_match('/Mobile|iPhone|Android/', $userAgent)) {
            $deviceType = 'Mobile';
        } elseif (preg_match('/Tablet|iPad/', $userAgent)) {
            $deviceType = 'Tablet';
        }
        
        return [
            'browser' => $browser,
            'os' => $os,
            'device_type' => $deviceType
        ];
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName) {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM `$tableName` LIMIT 1");
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Quick logging methods for common actions
     */
    public function logLogin($userId, $username, $success = true) {
        return $this->logEntry([
            'user_id' => $userId,
            'username' => $username,
            'action_type' => 'LOGIN',
            'action_description' => $success ? 'User logged in successfully' : 'Failed login attempt',
            'resource_type' => 'User Account',
            'resource_id' => "User ID: $userId",
            'outcome' => $success ? 'Success' : 'Failed'
        ]);
    }
    
    public function logLogout($userId, $username) {
        return $this->logEntry([
            'user_id' => $userId,
            'username' => $username,
            'action_type' => 'LOGOUT',
            'action_description' => 'User logged out',
            'resource_type' => 'User Account',
            'resource_id' => "User ID: $userId"
        ]);
    }
    
    public function logUserUpdate($adminId, $adminName, $targetUserId, $targetUserName, $changes) {
        return $this->logEntry([
            'user_id' => $adminId,
            'username' => $adminName,
            'action_type' => 'UPDATE',
            'action_description' => "Updated user account: $targetUserName",
            'resource_type' => 'User Account',
            'resource_id' => "User ID: $targetUserId",
            'resource_name' => $targetUserName,
            'old_value' => $changes['old'] ?? null,
            'new_value' => $changes['new'] ?? null,
            'context' => $changes
        ]);
    }
    
    public function logCourseAccess($userId, $username, $courseId, $courseName) {
        return $this->logEntry([
            'user_id' => $userId,
            'username' => $username,
            'action_type' => 'ACCESS',
            'action_description' => "Accessed course: $courseName",
            'resource_type' => 'Course',
            'resource_id' => "Course ID: $courseId",
            'resource_name' => $courseName
        ]);
    }
    
    public function logSystemConfig($adminId, $adminName, $setting, $oldValue, $newValue) {
        return $this->logEntry([
            'user_id' => $adminId,
            'username' => $adminName,
            'action_type' => 'UPDATE',
            'action_description' => "Updated system configuration: $setting",
            'resource_type' => 'System Config',
            'resource_id' => "Setting: $setting",
            'old_value' => $oldValue,
            'new_value' => $newValue
        ]);
    }
}

/**
 * Helper function to create audit logger instance
 */
function createAuditLogger($pdo) {
    return new AuditLogger($pdo);
}

/**
 * Quick audit logging function
 */
function logAuditEntry($pdo, $params) {
    $logger = new AuditLogger($pdo);
    return $logger->logEntry($params);
}
?>
