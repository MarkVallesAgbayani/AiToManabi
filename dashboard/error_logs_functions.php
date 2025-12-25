<?php
/**
 * Error Logs Monitor Class
 * Handles all error logging, retrieval, and export functionality
 */
class ErrorLogsMonitor {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Ensure database connection is valid
        if (!$this->pdo instanceof PDO) {
            throw new Exception("Invalid PDO connection provided");
        }
    }
    
    /**
     * Get error logs data with filtering and pagination
     */
    public function getErrorData($offset = 0, $limit = 20, $filters = []) {
        $whereConditions = [];
        $params = [];
        
        // Build WHERE conditions based on filters
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "occurred_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "occurred_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['category'])) {
            $whereConditions[] = "category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['severity'])) {
            $whereConditions[] = "severity = ?";
            $params[] = $filters['severity'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['user_id'])) {
            $whereConditions[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(error_message LIKE ? OR user_query LIKE ? OR module_name LIKE ? OR error_type LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT 
                    id, category, error_type, severity, error_message, user_query, 
                    module_name, endpoint, response_time_ms, expected_response_time_ms,
                    user_id, ip_address, device_type, browser_name, operating_system,
                    status, occurred_at, stack_trace, root_cause
                FROM system_error_log 
                {$whereClause}
                ORDER BY occurred_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching error data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of error records with filters
     */
    public function getTotalErrorRecords($filters = []) {
        $whereConditions = [];
        $params = [];
        
        // Build WHERE conditions (same as above)
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "occurred_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "occurred_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['category'])) {
            $whereConditions[] = "category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['severity'])) {
            $whereConditions[] = "severity = ?";
            $params[] = $filters['severity'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['user_id'])) {
            $whereConditions[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(error_message LIKE ? OR user_query LIKE ? OR module_name LIKE ? OR error_type LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) FROM system_error_log {$whereClause}";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting error records: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get error statistics for dashboard cards
     */
    public function getErrorStatistics() {
        try {
            // Check if the view exists, if not use direct query
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'error_logs_summary'");
            if ($stmt->rowCount() > 0) {
                $stmt = $this->pdo->query("SELECT * FROM error_logs_summary");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Fallback to direct query
                $sql = "SELECT 
                    COUNT(*) as total_errors,
                    COUNT(CASE WHEN occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as errors_24h,
                    COUNT(CASE WHEN occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as errors_7d,
                    COUNT(CASE WHEN occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as errors_30d,
                    COUNT(CASE WHEN category = 'ai_failure' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as ai_failures_24h,
                    COUNT(CASE WHEN category = 'ai_failure' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as ai_failures_7d,
                    COUNT(CASE WHEN category = 'ai_failure' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as ai_failures_30d,
                    COUNT(CASE WHEN severity = 'critical' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as critical_errors_24h,
                    COUNT(CASE WHEN category = 'response_time' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as slow_responses_24h,
                    AVG(CASE WHEN response_time_ms IS NOT NULL AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN response_time_ms END) as avg_response_time_24h,
                    MAX(occurred_at) as last_error_time
                FROM system_error_log";
                
                $stmt = $this->pdo->query($sql);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return $stats ?: [
                'total_errors' => 0,
                'errors_24h' => 0,
                'errors_7d' => 0,
                'errors_30d' => 0,
                'ai_failures_24h' => 0,
                'ai_failures_7d' => 0,
                'ai_failures_30d' => 0,
                'critical_errors_24h' => 0,
                'slow_responses_24h' => 0,
                'avg_response_time_24h' => 0,
                'last_error_time' => null
            ];
        } catch (PDOException $e) {
            error_log("Error fetching error statistics: " . $e->getMessage());
            return [
                'total_errors' => 0,
                'errors_24h' => 0,
                'errors_7d' => 0,
                'errors_30d' => 0,
                'ai_failures_24h' => 0,
                'ai_failures_7d' => 0,
                'ai_failures_30d' => 0,
                'critical_errors_24h' => 0,
                'slow_responses_24h' => 0,
                'avg_response_time_24h' => 0,
                'last_error_time' => null
            ];
        }
    }
    
    /**
     * Get error trend data for charts
     */
    public function getErrorTrendData($days = 7) {
        try {
            $sql = "SELECT 
                DATE(occurred_at) as error_date,
                COUNT(*) as total_errors,
                COUNT(CASE WHEN category = 'ai_failure' THEN 1 END) as ai_failures,
                COUNT(CASE WHEN category = 'response_time' THEN 1 END) as response_issues,
                COUNT(CASE WHEN category = 'backend_error' THEN 1 END) as backend_errors,
                COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_errors,
                COUNT(CASE WHEN severity = 'warning' THEN 1 END) as warning_errors,
                COUNT(CASE WHEN severity = 'info' THEN 1 END) as info_errors,
                AVG(CASE WHEN response_time_ms IS NOT NULL THEN response_time_ms END) as avg_response_time
            FROM system_error_log
            WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(occurred_at)
            ORDER BY error_date ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching trend data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get error category breakdown for charts
     */
    public function getErrorCategoryData($days = 7) {
        try {
            $sql = "SELECT 
                COUNT(CASE WHEN category = 'ai_failure' THEN 1 END) as ai_failures,
                COUNT(CASE WHEN category = 'response_time' THEN 1 END) as response_issues,
                COUNT(CASE WHEN category = 'backend_error' THEN 1 END) as backend_errors
            FROM system_error_log
            WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'ai_failures' => 0,
                'response_issues' => 0,
                'backend_errors' => 0
            ];
        } catch (PDOException $e) {
            error_log("Error fetching category data: " . $e->getMessage());
            return [
                'ai_failures' => 0,
                'response_issues' => 0,
                'backend_errors' => 0
            ];
        }
    }
    
    /**
     * Get recent error activities for notifications
     */
    public function getRecentActivities($limit = 10) {
        try {
            $sql = "SELECT 
                id, category, severity, error_type, error_message, module_name, 
                user_id, occurred_at, status,
                CASE 
                    WHEN category = 'ai_failure' THEN CONCAT('AI Failure: ', COALESCE(error_type, 'Unknown'))
                    WHEN category = 'response_time' THEN CONCAT('Slow Response: ', COALESCE(endpoint, module_name, 'Unknown'))
                    WHEN category = 'backend_error' THEN CONCAT('Backend Error: ', COALESCE(module_name, error_type, 'Unknown'))
                    ELSE CONCAT('System Error: ', COALESCE(error_type, 'Unknown'))
                END as activity_message,
                CASE 
                    WHEN severity = 'critical' THEN 'error'
                    WHEN severity = 'warning' THEN 'warning'
                    ELSE 'info'
                END as activity_type
            FROM system_error_log
            WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY occurred_at DESC
            LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching recent activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get detailed error information by ID
     */
    public function getErrorDetails($errorId) {
        try {
            $sql = "SELECT * FROM system_error_log WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$errorId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching error details: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Export error data to CSV
     */
    public function exportToCSV($filters = []) {
        $data = $this->getErrorData(0, 10000, $filters); // Get all data
        
        $filename = 'error_logs_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Category', 'Severity', 'Error Type', 'Error Message', 'User Query',
            'Module Name', 'Endpoint', 'Response Time (ms)', 'Expected Time (ms)',
            'User ID', 'IP Address', 'Device Type', 'Browser', 'OS',
            'Status', 'Occurred At', 'Root Cause'
        ]);
        
        // CSV data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['category'],
                $row['severity'],
                $row['error_type'],
                $row['error_message'],
                $row['user_query'],
                $row['module_name'],
                $row['endpoint'],
                $row['response_time_ms'],
                $row['expected_response_time_ms'],
                $row['user_id'],
                $row['ip_address'],
                $row['device_type'],
                $row['browser_name'],
                $row['operating_system'],
                $row['status'],
                $row['occurred_at'],
                $row['root_cause']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export error data to Excel
     */
    public function exportToExcel($filters = []) {
        $data = $this->getErrorData(0, 10000, $filters); // Get all data
        
        $filename = 'error_logs_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Simple Excel XML format
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Worksheet ss:Name="Error Logs">' . "\n";
        echo '<Table>' . "\n";
        
        // Headers
        echo '<Row>';
        $headers = ['ID', 'Category', 'Severity', 'Error Type', 'Error Message', 'User Query', 'Module Name', 'Endpoint', 'Response Time (ms)', 'User ID', 'IP Address', 'Device', 'Status', 'Occurred At'];
        foreach ($headers as $header) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";
        
        // Data rows
        foreach ($data as $row) {
            echo '<Row>';
            $values = [
                $row['id'], $row['category'], $row['severity'], $row['error_type'], 
                $row['error_message'], $row['user_query'], $row['module_name'], $row['endpoint'],
                $row['response_time_ms'], $row['user_id'], $row['ip_address'], 
                $row['device_type'], $row['status'], $row['occurred_at']
            ];
            foreach ($values as $value) {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars($value ?? '') . '</Data></Cell>';
            }
            echo '</Row>' . "\n";
        }
        
        echo '</Table>' . "\n";
        echo '</Worksheet>' . "\n";
        echo '</Workbook>' . "\n";
        exit;
    }
    
    /**
     * Export error data to PDF
     */
    public function exportToPDF($filters = []) {
        $data = $this->getErrorData(0, 10000, $filters); // Get all data
        
        $filename = 'error_logs_' . date('Y-m-d_H-i-s') . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Error Logs Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 10px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .header { text-align: center; margin-bottom: 20px; }
        .date { color: #666; font-size: 10px; }
        .severity-critical { color: #dc2626; font-weight: bold; }
        .severity-warning { color: #d97706; font-weight: bold; }
        .severity-info { color: #2563eb; font-weight: bold; }
        @media print {
            body { margin: 10px; }
            table { font-size: 8px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>System Error Logs Report</h2>
        <p class="date">Generated on: ' . date('Y-m-d H:i:s') . '</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Severity</th>
                <th>Category</th>
                <th>Error Type</th>
                <th>Message</th>
                <th>Occurred At</th>
                <th>User ID</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($data as $row) {
            $severityClass = 'severity-' . $row['severity'];
            echo '<tr>';
            echo '<td class="' . $severityClass . '">' . htmlspecialchars($row['severity']) . '</td>';
            echo '<td>' . htmlspecialchars($row['category']) . '</td>';
            echo '<td>' . htmlspecialchars($row['error_type']) . '</td>';
            echo '<td>' . htmlspecialchars(substr($row['error_message'], 0, 100)) . (strlen($row['error_message']) > 100 ? '...' : '') . '</td>';
            echo '<td>' . htmlspecialchars($row['occurred_at']) . '</td>';
            echo '<td>' . htmlspecialchars($row['user_id'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($row['status']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>
    </table>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>';
        exit;
    }
    
    /**
     * Log AI Failure
     */
    public static function logAIFailure($pdo, $userId, $userQuery, $errorType, $errorMessage, $rootCause = null, $ipAddress = null, $userAgent = null) {
        try {
            $sql = "CALL LogAIFailure(?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $userQuery,
                $errorType,
                $errorMessage,
                $rootCause,
                $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Error logging AI failure: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log Response Time Issue
     */
    public static function logResponseTimeIssue($pdo, $endpoint, $responseTimeMs, $expectedTimeMs, $userId = null, $severity = 'warning') {
        try {
            $sql = "CALL LogResponseTimeIssue(?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $endpoint,
                $responseTimeMs,
                $expectedTimeMs,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $severity
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Error logging response time issue: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log Backend Error
     */
    public static function logBackendError($pdo, $moduleName, $errorType, $errorMessage, $errorCode = null, $stackTrace = null, $userId = null, $severity = 'critical') {
        try {
            $sql = "CALL LogBackendError(?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $moduleName,
                $errorType,
                $errorMessage,
                $errorCode,
                $stackTrace,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['REQUEST_URI'] ?? 'unknown',
                $severity
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Error logging backend error: " . $e->getMessage());
            return false;
        }
    }
}
?>
