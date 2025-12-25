<?php
// Real database audit trail data functions
require_once 'ip_address_utils.php';

// Main function to get audit data from database
function getRealAuditData($pdo, $offset, $limit, $filters = []) {
    try {
        // Build WHERE conditions
        $whereConditions = [];
        $params = [];
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(cat.timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(cat.timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // User filter
        if (!empty($filters['user_filter'])) {
            $whereConditions[] = "(cat.username LIKE ? OR CAST(cat.user_id AS CHAR) LIKE ?)";
            $params[] = '%' . $filters['user_filter'] . '%';
            $params[] = '%' . $filters['user_filter'] . '%';
        }
        
        // Action type filter
        if (!empty($filters['action_filter'])) {
            $whereConditions[] = "cat.action_type = ?";
            $params[] = $filters['action_filter'];
        }
        
        // Outcome filter
        if (!empty($filters['outcome_filter'])) {
            $whereConditions[] = "cat.outcome = ?";
            $params[] = $filters['outcome_filter'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereConditions[] = "(cat.action_description LIKE ? OR cat.resource_name LIKE ? OR cat.username LIKE ? OR cat.resource_type LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        // First, try to get from comprehensive_audit_trail table
        $sql = "SELECT 
                    cat.id,
                    cat.timestamp,
                    cat.user_id,
                    cat.username,
                    cat.user_role,
                    cat.action_type,
                    cat.action_description,
                    cat.resource_type,
                    COALESCE(cat.resource_id, CONCAT(cat.resource_type, ' ID: ', cat.id)) as resource_id,
                    COALESCE(cat.resource_name, cat.resource_type) as resource_name,
                    cat.ip_address,
                    cat.outcome,
                    cat.old_value_text as old_value,
                    cat.new_value_text as new_value,
                    COALESCE(
                        CASE 
                            WHEN cat.browser_name IS NOT NULL AND cat.operating_system IS NOT NULL 
                            THEN CONCAT(cat.browser_name, ' ', SUBSTRING_INDEX(cat.browser_name, ' ', -1), ' on ', cat.operating_system)
                            ELSE cat.device_info
                        END,
                        'Unknown Browser on Unknown OS'
                    ) as device_info,
                    cat.browser_name,
                    cat.operating_system,
                    cat.device_type,
                    COALESCE(
                        CASE 
                            WHEN cat.location_city IS NOT NULL AND cat.location_country IS NOT NULL 
                            THEN CONCAT(cat.location_city, ', ', cat.location_country)
                            WHEN cat.location_country IS NOT NULL 
                            THEN cat.location_country
                            ELSE NULL
                        END,
                        NULL
                    ) as location,
                    cat.location_city,
                    cat.location_country,
                    cat.session_id,
                    cat.request_method,
                    cat.request_url,
                    cat.response_code,
                    cat.response_time_ms,
                    cat.error_message,
                    cat.additional_context
                FROM comprehensive_audit_trail cat
                {$whereClause}
                ORDER BY cat.timestamp DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If comprehensive table is empty, fall back to existing tables
        if (empty($result)) {
            return getFallbackAuditData($pdo, $offset, $limit, $filters);
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Audit data query error: " . $e->getMessage());
        // Fall back to existing audit tables
        return getFallbackAuditData($pdo, $offset, $limit, $filters);
    }
}

// Fallback function to get data from existing audit tables
function getFallbackAuditData($pdo, $offset, $limit, $filters = []) {
    try {
        $auditData = [];
        
        // Get data from admin_audit_log table if it exists
        try {
            $sql = "SELECT 
                        aal.id,
                        aal.created_at as timestamp,
                        aal.admin_id as user_id,
                        u.username,
                        u.role as user_role,
                        'ADMIN' as action_type,
                        aal.action as action_description,
                        'System Config' as resource_type,
                        CONCAT('Admin Action ID: ', aal.id) as resource_id,
                        aal.action as resource_name,
                        COALESCE(aal.ip_address, '0.0.0.0') as ip_address,
                        'Success' as outcome,
                        NULL as old_value,
                        NULL as new_value,
                        COALESCE(aal.user_agent, 'Unknown Browser') as device_info,
                        CASE 
                            WHEN aal.ip_address LIKE '192.168.%' OR aal.ip_address LIKE '10.%' OR aal.ip_address LIKE '172.%' THEN 'Local Network'
                            WHEN aal.ip_address LIKE '127.%' THEN 'Localhost'
                            ELSE 'External Location'
                        END as location,
                        NULL as session_id,
                        'POST' as request_method,
                        NULL as browser_name,
                        NULL as operating_system,
                        'Desktop' as device_type,
                        NULL as location_city,
                        NULL as location_country,
                        NULL as request_url,
                        200 as response_code,
                        NULL as response_time_ms,
                        NULL as error_message,
                        aal.details as additional_context
                    FROM admin_audit_log aal
                    JOIN users u ON aal.admin_id = u.id
                    ORDER BY aal.created_at DESC
                    LIMIT ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit]);
            $adminAudit = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $auditData = array_merge($auditData, $adminAudit);
        } catch (PDOException $e) {
            // admin_audit_log table doesn't exist, skip
        }
        
        // Get data from original audit_trail table if it exists
        try {
            $sql2 = "SELECT 
                        at.id + 1000 as id,
                        at.created_at as timestamp,
                        at.user_id,
                        u.username,
                        'UPDATE' as action_type,
                        at.action as action_description,
                        'Course' as resource_type,
                        CONCAT('Course ID: ', COALESCE(at.course_id, 'Unknown')) as resource_id,
                        '0.0.0.0' as ip_address,
                        'Success' as outcome,
                        NULL as old_value,
                        at.details as new_value,
                        'Unknown Browser' as device_info,
                        'Unknown' as location,
                        NULL as session_id,
                        'POST' as request_method
                    FROM audit_trail at
                    JOIN users u ON at.user_id = u.id
                    ORDER BY at.created_at DESC
                    LIMIT ?";
            
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$limit]);
            $courseAudit = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $auditData = array_merge($auditData, $courseAudit);
        } catch (PDOException $e) {
            // audit_trail table doesn't exist, skip
        }
        
        // Get recent login data if login_logs table exists
        try {
            $sql3 = "SELECT 
                        ll.id + 2000 as id,
                        ll.login_time as timestamp,
                        ll.user_id,
                        u.username,
                        'LOGIN' as action_type,
                        'User logged in' as action_description,
                        'User Account' as resource_type,
                        CONCAT('User ID: ', ll.user_id) as resource_id,
                        COALESCE(ll.ip_address, '0.0.0.0') as ip_address,
                        'Success' as outcome,
                        NULL as old_value,
                        NULL as new_value,
                        COALESCE(ll.user_agent, 'Unknown Browser') as device_info,
                        'Unknown' as location,
                        NULL as session_id,
                        'POST' as request_method
                    FROM login_logs ll
                    JOIN users u ON ll.user_id = u.id
                    ORDER BY ll.login_time DESC
                    LIMIT ?";
            
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute([$limit]);
            $loginAudit = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            $auditData = array_merge($auditData, $loginAudit);
        } catch (PDOException $e) {
            // login_logs table doesn't exist, skip
        }
        
        // Return empty array if no audit data found
        // This ensures we only show real audit data, not sample data
        
        // Sort by timestamp descending
        usort($auditData, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Apply filters to fallback data
        $filteredData = applyFiltersToData($auditData, $filters);
        
        // Return limited results
        return array_slice($filteredData, $offset, $limit);
        
    } catch (PDOException $e) {
        error_log("Fallback audit data query error: " . $e->getMessage());
        return [];
    }
}

// Apply filters to data array (for fallback data)
function applyFiltersToData($data, $filters) {
    if (empty($filters)) return $data;
    
    return array_filter($data, function($record) use ($filters) {
        // Date filter
        if (!empty($filters['date_from'])) {
            if (date('Y-m-d', strtotime($record['timestamp'])) < $filters['date_from']) {
                return false;
            }
        }
        
        if (!empty($filters['date_to'])) {
            if (date('Y-m-d', strtotime($record['timestamp'])) > $filters['date_to']) {
                return false;
            }
        }
        
        // User filter
        if (!empty($filters['user_filter'])) {
            $userMatch = stripos($record['username'], $filters['user_filter']) !== false ||
                        stripos($record['user_id'], $filters['user_filter']) !== false;
            if (!$userMatch) return false;
        }
        
        // Action filter
        if (!empty($filters['action_filter'])) {
            if ($record['action_type'] !== $filters['action_filter']) {
                return false;
            }
        }
        
        // Outcome filter
        if (!empty($filters['outcome_filter'])) {
            if ($record['outcome'] !== $filters['outcome_filter']) {
                return false;
            }
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $searchFields = [
                $record['action_description'],
                $record['resource_type'],
                $record['username'],
                $record['resource_id'] ?? ''
            ];
            
            $searchMatch = false;
            foreach ($searchFields as $field) {
                if (stripos($field, $filters['search']) !== false) {
                    $searchMatch = true;
                    break;
                }
            }
            
            if (!$searchMatch) return false;
        }
        
        return true;
    });
}

// Get total count of audit records
function getTotalAuditRecords($pdo, $filters = []) {
    try {
        // Build WHERE conditions
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(cat.timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(cat.timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['user_filter'])) {
            $whereConditions[] = "(cat.username LIKE ? OR CAST(cat.user_id AS CHAR) LIKE ?)";
            $params[] = '%' . $filters['user_filter'] . '%';
            $params[] = '%' . $filters['user_filter'] . '%';
        }
        
        if (!empty($filters['action_filter'])) {
            $whereConditions[] = "cat.action_type = ?";
            $params[] = $filters['action_filter'];
        }
        
        if (!empty($filters['outcome_filter'])) {
            $whereConditions[] = "cat.outcome = ?";
            $params[] = $filters['outcome_filter'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(cat.action_description LIKE ? OR cat.resource_name LIKE ? OR cat.username LIKE ? OR cat.resource_type LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Try comprehensive table first
        $sql = "SELECT COUNT(*) FROM comprehensive_audit_trail cat {$whereClause}";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        // If comprehensive table is empty, count from fallback tables
        if ($count == 0) {
            return getFallbackTotalCount($pdo, $filters);
        }
        
        return $count;
        
    } catch (PDOException $e) {
        error_log("Total audit records query error: " . $e->getMessage());
        return getFallbackTotalCount($pdo, $filters);
    }
}

// Get total count from fallback tables
function getFallbackTotalCount($pdo, $filters = []) {
    $totalCount = 0;
    
    // Count from admin_audit_log
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_log");
        $stmt->execute();
        $totalCount += $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Table doesn't exist
    }
    
    // Count from audit_trail
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_trail");
        $stmt->execute();
        $totalCount += $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Table doesn't exist
    }
    
    // Count from login_logs
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs");
        $stmt->execute();
        $totalCount += $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Table doesn't exist
    }
    
    // Return 0 if no audit tables exist
    // This ensures we only count real audit data, not sample data
    
    return $totalCount;
}

// Get real audit statistics
function getRealAuditStatistics($pdo) {
    try {
        $stats = [
            'total_actions' => 0,
            'actions_today' => 0,
            'failed_actions' => 0,
            'unique_users' => 0,
            'most_active_user' => 'N/A',
            'peak_hour' => 'N/A'
        ];
        
        // Get real audit statistics from database
        
        // Try comprehensive table first
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM comprehensive_audit_trail");
            $stmt->execute();
            $stats['total_actions'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM comprehensive_audit_trail WHERE DATE(timestamp) = CURDATE()");
            $stmt->execute();
            $stats['actions_today'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM comprehensive_audit_trail WHERE outcome = 'Failed'");
            $stmt->execute();
            $stats['failed_actions'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM comprehensive_audit_trail");
            $stmt->execute();
            $stats['unique_users'] = $stmt->fetchColumn();
            
            // Get most active user
            $stmt = $pdo->prepare("
                SELECT username, COUNT(*) as action_count 
                FROM comprehensive_audit_trail 
                GROUP BY user_id, username 
                ORDER BY action_count DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $mostActive = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($mostActive) {
                $stats['most_active_user'] = $mostActive['username'];
            }
            
        } catch (PDOException $e) {
            // Fall back to existing tables
            $stats = getFallbackStatistics($pdo);
        }
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Audit statistics query error: " . $e->getMessage());
        return [
            'total_actions' => 0,
            'actions_today' => 0,
            'failed_actions' => 0,
            'unique_users' => 0,
            'most_active_user' => 'N/A',
            'peak_hour' => 'N/A'
        ];
    }
}

// Fallback statistics from existing tables
function getFallbackStatistics($pdo) {
    $stats = [
        'total_actions' => 0,
        'actions_today' => 0,
        'failed_actions' => 0,
        'unique_users' => 0,
        'most_active_user' => 'N/A',
        'peak_hour' => 'N/A'
    ];
    
    // Count from various tables
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_action_logs");
        $stmt->execute();
        $stats['total_actions'] += $stmt->fetchColumn();
    } catch (PDOException $e) {}
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_trail");
        $stmt->execute();
        $stats['total_actions'] += $stmt->fetchColumn();
    } catch (PDOException $e) {}
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs");
        $stmt->execute();
        $stats['total_actions'] += $stmt->fetchColumn();
    } catch (PDOException $e) {}
    
    // Get actions today from existing tables
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_action_logs WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['actions_today'] += $stmt->fetchColumn();
    } catch (PDOException $e) {}
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_trail WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['actions_today'] += $stmt->fetchColumn();
    } catch (PDOException $e) {}
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE DATE(login_time) = CURDATE()");
        $stmt->execute();
        $stats['actions_today'] += $stmt->fetchColumn();
    } catch (PDOException $e) {}
    
    // Get unique users count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $stats['unique_users'] = $stmt->fetchColumn();
    } catch (PDOException $e) {}
    
    // Get most active user (from users table)
    try {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            $stats['most_active_user'] = $admin['username'];
        }
    } catch (PDOException $e) {}
    
    return $stats;
}

// Create comprehensive audit trail table if it doesn't exist
function createComprehensiveAuditTable($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `comprehensive_audit_trail` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `user_id` INT NOT NULL,
                `username` VARCHAR(255) NOT NULL,
                `user_role` ENUM('student', 'teacher', 'admin') NOT NULL,
                `action_type` ENUM('CREATE', 'READ', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'ACCESS', 'DOWNLOAD', 'SUBMIT') NOT NULL,
                `action_description` TEXT NOT NULL,
                `resource_type` ENUM('User Account', 'Course', 'Lesson', 'Chapter', 'Section', 'Category', 'Enrollment', 'Progress', 'Quiz', 'Assignment', 'Forum Post', 'Profile', 'Dashboard', 'System Config', 'Materials', 'Assessment', 'Application', 'Payment') NOT NULL,
                `resource_id` VARCHAR(255) NULL,
                `resource_name` VARCHAR(500) NULL,
                `ip_address` VARCHAR(45) NOT NULL,
                `outcome` ENUM('Success', 'Failed', 'Partial') DEFAULT 'Success',
                `old_value_text` TEXT NULL,
                `new_value_text` TEXT NULL,
                `device_info` TEXT NULL,
                `browser_name` VARCHAR(100) NULL,
                `operating_system` VARCHAR(100) NULL,
                `device_type` ENUM('Desktop', 'Mobile', 'Tablet') NULL,
                `location_country` VARCHAR(100) NULL,
                `location_city` VARCHAR(100) NULL,
                `session_id` VARCHAR(255) NULL,
                `request_method` ENUM('GET', 'POST', 'PUT', 'PATCH', 'DELETE') NULL,
                `request_url` TEXT NULL,
                `response_code` INT NULL,
                `response_time_ms` INT NULL,
                `error_message` TEXT NULL,
                `additional_context` JSON NULL,
                
                INDEX `idx_timestamp` (`timestamp`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_username` (`username`),
                INDEX `idx_action_type` (`action_type`),
                INDEX `idx_resource_type` (`resource_type`),
                INDEX `idx_outcome` (`outcome`),
                INDEX `idx_ip_address` (`ip_address`),
                INDEX `idx_session_id` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating comprehensive audit table: " . $e->getMessage());
        return false;
    }
}
?>
