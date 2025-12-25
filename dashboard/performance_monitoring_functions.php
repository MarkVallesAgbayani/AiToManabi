<?php
/**
 * System Performance Monitoring Functions
 * Tracks uptime, downtime, page load times, and system health
 */

class PerformanceMonitor {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    /**
     * Get performance data with pagination and filtering
     */
    public function getPerformanceData($offset = 0, $limit = 50, $filters = []) {
        $conditions = [];
        $params = [];
        
        // Build WHERE conditions based on filters
        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['event_type'])) {
            if ($filters['event_type'] === 'page_load') {
                $conditions[] = "1=1"; // Will query page_performance_log table
            } else {
                $conditions[] = "event_type = :event_type";
                $params['event_type'] = $filters['event_type'];
            }
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['duration_min'])) {
            if ($filters['event_type'] === 'page_load') {
                $conditions[] = "load_duration >= :duration_min";
            } else {
                $conditions[] = "duration_seconds >= :duration_min";
            }
            $params['duration_min'] = $filters['duration_min'];
        }
        
        if (!empty($filters['duration_max'])) {
            if ($filters['event_type'] === 'page_load') {
                $conditions[] = "load_duration <= :duration_max";
            } else {
                $conditions[] = "duration_seconds <= :duration_max";
            }
            $params['duration_max'] = $filters['duration_max'];
        }
        
        if (!empty($filters['search'])) {
            if ($filters['event_type'] === 'page_load') {
                $conditions[] = "(page_name LIKE :search OR action_name LIKE :search)";
            } else {
                $conditions[] = "(root_cause LIKE :search OR error_message LIKE :search)";
            }
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Determine which table to query
        if (!empty($filters['event_type']) && $filters['event_type'] === 'page_load') {
            $sql = "
                SELECT 
                    'page_load' as event_type,
                    page_name as title,
                    action_name as description,
                    start_time,
                    end_time,
                    load_duration as duration,
                    status,
                    user_id,
                    ip_address,
                    device_type,
                    browser,
                    os,
                    created_at
                FROM page_performance_log 
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT :offset, :limit
            ";
        } else {
            $sql = "
                SELECT 
                    event_type,
                    CONCAT(UPPER(event_type), ' Event') as title,
                    COALESCE(root_cause, 'System monitoring') as description,
                    start_time,
                    end_time,
                    duration_seconds as duration,
                    status,
                    NULL as user_id,
                    server_ip as ip_address,
                    NULL as device_type,
                    NULL as browser,
                    NULL as os,
                    created_at
                FROM system_uptime_log 
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT :offset, :limit
            ";
        }
        
        $params['offset'] = $offset;
        $params['limit'] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total count for pagination
     */
    public function getTotalPerformanceRecords($filters = []) {
        $conditions = [];
        $params = [];
        
        // Same filtering logic as getPerformanceData
        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['event_type'])) {
            if ($filters['event_type'] !== 'page_load') {
                $conditions[] = "event_type = :event_type";
                $params['event_type'] = $filters['event_type'];
            }
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            if (!empty($filters['event_type']) && $filters['event_type'] === 'page_load') {
                $conditions[] = "(page_name LIKE :search OR action_name LIKE :search)";
            } else {
                $conditions[] = "(root_cause LIKE :search OR error_message LIKE :search)";
            }
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        if (!empty($filters['event_type']) && $filters['event_type'] === 'page_load') {
            $sql = "SELECT COUNT(*) FROM page_performance_log {$whereClause}";
        } else {
            $sql = "SELECT COUNT(*) FROM system_uptime_log {$whereClause}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Initialize system status when no records exist
     */
    private function initializeSystemStatus() {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_uptime_log (event_type, start_time, status, monitored_by) 
                VALUES ('uptime', NOW(), 'active', 'system')
            ");
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to initialize system status: " . $e->getMessage());
        }
    }
    
    /**
     * Get performance statistics for dashboard cards
     */
    public function getPerformanceStatistics() {
        // Current system status - check if we have any uptime data
        $currentStatusSql = "
            SELECT 
                CASE 
                    WHEN latest.event_type = 'uptime' THEN 'online'
                    ELSE 'offline'
                END as current_status,
                TIMESTAMPDIFF(SECOND, latest.start_time, NOW()) as current_duration,
                latest.start_time as current_since
            FROM system_uptime_log latest
            WHERE latest.status = 'active'
            ORDER BY latest.start_time DESC
            LIMIT 1
        ";
        
        $stmt = $this->pdo->query($currentStatusSql);
        $currentStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no active status found, assume system is online and start tracking
        if (!$currentStatus) {
            // Check if we have any uptime records at all
            $checkSql = "SELECT COUNT(*) as count FROM system_uptime_log";
            $checkStmt = $this->pdo->query($checkSql);
            $hasRecords = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if (!$hasRecords) {
                // No records exist, start with online status
                $this->initializeSystemStatus();
                $currentStatus = [
                    'current_status' => 'online',
                    'current_duration' => 0,
                    'current_since' => date('Y-m-d H:i:s')
                ];
            } else {
                // Records exist but no active status - system might be offline
                $currentStatus = [
                    'current_status' => 'offline',
                    'current_duration' => 0,
                    'current_since' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Uptime statistics (last 30 days)
        $uptimeStatsSql = "
            SELECT 
                COALESCE(SUM(CASE WHEN event_type = 'uptime' THEN COALESCE(duration_seconds, 0) END), 0) as total_uptime,
                COALESCE(SUM(CASE WHEN event_type = 'downtime' THEN COALESCE(duration_seconds, 0) END), 0) as total_downtime,
                COALESCE(COUNT(CASE WHEN event_type = 'downtime' THEN 1 END), 0) as downtime_incidents,
                CASE 
                    WHEN COALESCE(SUM(COALESCE(duration_seconds, 0)), 0) = 0 THEN 100.00
                    ELSE ROUND(
                        SUM(CASE WHEN event_type = 'uptime' THEN COALESCE(duration_seconds, 0) END) * 100.0 / 
                        NULLIF(SUM(COALESCE(duration_seconds, 0)), 0), 2
                    )
                END as uptime_percentage
            FROM system_uptime_log 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND status = 'completed'
        ";
        
        $stmt = $this->pdo->query($uptimeStatsSql);
        $uptimeStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_uptime' => 0,
            'total_downtime' => 0,
            'downtime_incidents' => 0,
            'uptime_percentage' => 100.00
        ];
        
        // If no completed records exist, check for active uptime
        if ($uptimeStats['total_uptime'] == 0 && $uptimeStats['total_downtime'] == 0) {
            $activeUptimeSql = "
                SELECT 
                    TIMESTAMPDIFF(SECOND, start_time, NOW()) as current_uptime_seconds
                FROM system_uptime_log 
                WHERE event_type = 'uptime' 
                  AND status = 'active' 
                  AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY start_time DESC 
                LIMIT 1
            ";
            $activeStmt = $this->pdo->query($activeUptimeSql);
            $activeUptime = $activeStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($activeUptime && $activeUptime['current_uptime_seconds'] > 0) {
                $uptimeStats['total_uptime'] = $activeUptime['current_uptime_seconds'];
                $uptimeStats['uptime_percentage'] = 100.00;
            }
        }
        
        // Page load statistics (last 24 hours)
        $pageLoadStatsSql = "
            SELECT 
                COALESCE(COUNT(*), 0) as total_requests,
                COALESCE(AVG(load_duration), 0) as avg_load_time,
                COALESCE(COUNT(CASE WHEN status = 'fast' THEN 1 END), 0) as fast_requests,
                COALESCE(COUNT(CASE WHEN status = 'slow' THEN 1 END), 0) as slow_requests,
                COALESCE(COUNT(CASE WHEN status = 'timeout' OR status = 'error' THEN 1 END), 0) as failed_requests,
                CASE 
                    WHEN COUNT(*) = 0 THEN 100.00
                    ELSE ROUND(COUNT(CASE WHEN status = 'fast' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2)
                END as fast_percentage
            FROM page_performance_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";
        
        $stmt = $this->pdo->query($pageLoadStatsSql);
        $pageLoadStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_requests' => 0,
            'avg_load_time' => 0,
            'fast_requests' => 0,
            'slow_requests' => 0,
            'failed_requests' => 0,
            'fast_percentage' => 100.00
        ];
        
        return [
            'current_status' => $currentStatus,
            'uptime_stats' => $uptimeStats,
            'page_load_stats' => $pageLoadStats
        ];
    }
    
    /**
     * Get chart data for uptime/downtime timeline
     */
    public function getUptimeChartData($days = 7) {
        // Get completed uptime/downtime events
        $completedSql = "
            SELECT 
                DATE(start_time) as date,
                SUM(CASE WHEN event_type = 'uptime' THEN COALESCE(duration_seconds, 0) END) as uptime_seconds,
                SUM(CASE WHEN event_type = 'downtime' THEN COALESCE(duration_seconds, 0) END) as downtime_seconds
            FROM system_uptime_log 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
              AND status = 'completed'
            GROUP BY DATE(start_time)
            ORDER BY date ASC
        ";
        
        $stmt = $this->pdo->prepare($completedSql);
        $stmt->execute(['days' => $days]);
        $completedData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get active uptime events that started within the period
        $activeSql = "
            SELECT 
                DATE(start_time) as date,
                event_type,
                start_time,
                TIMESTAMPDIFF(SECOND, start_time, NOW()) as current_duration_seconds
            FROM system_uptime_log 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
              AND status = 'active'
            ORDER BY start_time ASC
        ";
        
        $stmt = $this->pdo->prepare($activeSql);
        $stmt->execute(['days' => $days]);
        $activeData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build result array for each day
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $nextDate = date('Y-m-d', strtotime("-{$i} days +1 day"));
            
            $uptimeSeconds = 0;
            $downtimeSeconds = 0;
            
            // Add completed events for this day
            foreach ($completedData as $row) {
                if ($row['date'] === $date) {
                    $uptimeSeconds += $row['uptime_seconds'] ?: 0;
                    $downtimeSeconds += $row['downtime_seconds'] ?: 0;
                }
            }
            
            // Add active events that span this day
            foreach ($activeData as $row) {
                $eventDate = date('Y-m-d', strtotime($row['start_time']));
                
                if ($eventDate <= $date) {
                    // Calculate how much of this active event belongs to this day
                    $dayStart = max(strtotime($date), strtotime($row['start_time']));
                    $dayEnd = min(strtotime($nextDate), time());
                    
                    if ($dayStart < $dayEnd) {
                        $dayDuration = $dayEnd - $dayStart;
                        
                        if ($row['event_type'] === 'uptime') {
                            $uptimeSeconds += $dayDuration;
                        } else {
                            $downtimeSeconds += $dayDuration;
                        }
                    }
                }
            }
            
            // If no data for this day, assume 24 hours uptime (system was running)
            if ($uptimeSeconds == 0 && $downtimeSeconds == 0) {
                $uptimeSeconds = 86400; // 24 hours in seconds
            }
            
            $result[] = [
                'date' => $date,
                'uptime_hours' => round($uptimeSeconds / 3600, 2),
                'downtime_hours' => round($downtimeSeconds / 3600, 2)
            ];
        }
        
        return $result;
    }
    
    /**
     * Get chart data for page load times by page
     */
    public function getPageLoadChartData($days = 7) {
        $sql = "
            SELECT 
                page_name,
                AVG(load_duration) as avg_load_time,
                COUNT(*) as request_count,
                COUNT(CASE WHEN status = 'fast' THEN 1 END) as fast_count,
                COUNT(CASE WHEN status = 'slow' THEN 1 END) as slow_count
            FROM page_performance_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY page_name
            ORDER BY avg_load_time DESC
            LIMIT 10
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent activities for notifications
     */
    public function getRecentActivities($limit = 10) {
        $sql = "
            (SELECT 
                'downtime' as activity_type,
                CONCAT('System Downtime: ', COALESCE(root_cause, 'Unknown cause')) as message,
                start_time as occurred_at,
                severity as priority
            FROM system_uptime_log 
            WHERE event_type = 'downtime' 
              AND start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            )
            UNION ALL
            (SELECT 
                'slow_page' as activity_type,
                CONCAT('Slow page load: ', page_name, ' (', ROUND(load_duration, 2), 's)') as message,
                start_time as occurred_at,
                CASE 
                    WHEN load_duration > 10 THEN 'critical'
                    WHEN load_duration > 5 THEN 'high'
                    ELSE 'medium'
                END as priority
            FROM page_performance_log 
            WHERE status IN ('slow', 'timeout') 
              AND start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            )
            ORDER BY occurred_at DESC
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['limit' => $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Log page performance automatically
     */
    public static function logPageLoad($page_name, $action_name = null, $start_time = null) {
        if (!$start_time) {
            $start_time = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        }
        
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        
        // Get database connection
        try {
            // Include database config which creates $pdo variable
            if (file_exists(__DIR__ . '/../config/database.php')) {
                require_once __DIR__ . '/../config/database.php';
            } elseif (file_exists(__DIR__ . '/../../config/database.php')) {
                require_once __DIR__ . '/../../config/database.php';
            } else {
                throw new Exception("Database config not found");
            }
            
            // Check if $pdo variable exists and is valid
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                throw new Exception("Database connection not available");
            }
            
            $status = $duration <= 3.0 ? 'fast' : ($duration <= 10.0 ? 'slow' : 'timeout');
            
            $sql = "
                INSERT INTO page_performance_log (
                    page_name, action_name, full_url, start_time, end_time, 
                    load_duration, status, user_id, session_id, ip_address, 
                    user_agent, device_type, browser, os
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $page_name,
                $action_name,
                $_SERVER['REQUEST_URI'] ?? '',
                date('Y-m-d H:i:s', $start_time),
                date('Y-m-d H:i:s', $end_time),
                round($duration, 3),
                $status,
                $_SESSION['user_id'] ?? null,
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                self::detectDevice(),
                self::detectBrowser(),
                self::detectOS()
            ]);
            
        } catch (Exception $e) {
            // Silently fail - don't break the page if logging fails
            error_log("Performance logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Detect device type from user agent
     */
    private static function detectDevice() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $user_agent)) {
            return 'Tablet';
        }
        if (preg_match('/(mobile|phone)/i', $user_agent)) {
            return 'Mobile';
        }
        return 'Desktop';
    }
    
    /**
     * Detect browser from user agent
     */
    private static function detectBrowser() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
        if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
        if (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) return 'Safari';
        if (strpos($user_agent, 'Edge') !== false) return 'Edge';
        if (strpos($user_agent, 'Opera') !== false) return 'Opera';
        
        return 'Other';
    }
    
    /**
     * Detect OS from user agent
     */
    private static function detectOS() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/windows/i', $user_agent)) return 'Windows';
        if (preg_match('/macintosh|mac os x/i', $user_agent)) return 'macOS';
        if (preg_match('/linux/i', $user_agent)) return 'Linux';
        if (preg_match('/android/i', $user_agent)) return 'Android';
        if (preg_match('/iphone|ipad|ipod/i', $user_agent)) return 'iOS';
        
        return 'Other';
    }
    
    /**
     * Export performance data to CSV
     */
    public function exportToCSV($filters = []) {
        $data = $this->getPerformanceData(0, 10000, $filters); // Get all data
        
        $filename = 'performance_log_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Event Type', 'Title', 'Description', 'Start Time', 'End Time', 
            'Duration', 'Status', 'User ID', 'IP Address', 'Device', 
            'Browser', 'OS', 'Created At'
        ]);
        
        // CSV data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['event_type'],
                $row['title'],
                $row['description'],
                $row['start_time'],
                $row['end_time'],
                $row['duration'],
                $row['status'],
                $row['user_id'],
                $row['ip_address'],
                $row['device_type'],
                $row['browser'],
                $row['os'],
                $row['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export performance data to Excel (XLSX format)
     */
    public function exportToExcel($filters = []) {
        $data = $this->getPerformanceData(0, 10000, $filters); // Get all data
        
        $filename = 'performance_log_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Simple Excel XML format (compatible with Excel and LibreOffice)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Create simple Excel XML
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Worksheet ss:Name="Performance Log">' . "\n";
        echo '<Table>' . "\n";
        
        // Headers
        echo '<Row>';
        $headers = ['Event Type', 'Title', 'Description', 'Start Time', 'End Time', 'Duration', 'Status', 'User ID', 'IP Address', 'Device', 'Browser', 'OS', 'Created At'];
        foreach ($headers as $header) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";
        
        // Data rows
        foreach ($data as $row) {
            echo '<Row>';
            $values = [
                $row['event_type'], $row['title'], $row['description'], $row['start_time'], 
                $row['end_time'], $row['duration'], $row['status'], $row['user_id'], 
                $row['ip_address'], $row['device_type'], $row['browser'], $row['os'], $row['created_at']
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
     * Export performance data to PDF
     */
    public function exportToPDF($filters = []) {
        $data = $this->getPerformanceData(0, 10000, $filters); // Get all data

        $filename = 'performance_log_' . date('Y-m-d_H-i-s') . '.pdf';

        // Use mPDF (via Composer) for reliable PDF generation
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
        }

        if (!file_exists($autoloadPath)) {
            // If mPDF not available, fall back to CSV download to avoid corrupt PDF
            error_log('mPDF not found for PDF export in PerformanceMonitor::exportToPDF');
            // Provide a helpful message to the user
            header('Content-Type: text/plain; charset=utf-8');
            echo "Error: PDF generation library not installed. Please run 'composer install' on the project root to install dependencies.";
            exit;
        }

        require_once $autoloadPath;

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 30,
                'margin_bottom' => 25,
                'margin_header' => 9,
                'margin_footer' => 9
            ]);

            $mpdf->SetTitle('System Performance & Error Logs');
            $mpdf->SetAuthor('AIToManabi');

            // Build consistent header/footer similar to ReportGenerator
            $reportId = 'RPT-PF-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $companyName = 'AiToManabi';
            $companyEmail = 'aitomanabilms@gmail.com';
            $companyWebsite = 'www.aitomanabi.com';
            $reportVersion = '1.0';
            $confidentiality = 'internal';

            // Logo (base64) if exists
            $logoPath = __DIR__ . '/../assets/images/logo.png';
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $logoBase64 = base64_encode(file_get_contents($logoPath));
            }

            $header = '
        <div style="width: 100%; color: #333; padding: 8px 0; border-bottom: 2px solid #0369a1;">
            <table style="width: 100%; border-collapse: collapse; margin: 0;">
                <tr>
                    <td style="width: 33.33%; vertical-align: middle; text-align: left;">
                        <div style="display: inline-block; vertical-align: middle;">' .
                            ($logoBase64 ? '<img src="data:image/png;base64,' . $logoBase64 . '" alt="AiToManabi Logo" style="width: 50px; height: 50px; vertical-align: middle;" />' :
                            '<div style="font-weight:bold;color:#1e40af;">' . htmlspecialchars($companyName) . '</div>') . '
                        </div>
                    </td>
                    <td style="width: 33.33%; text-align: center; vertical-align: middle;">
                        <div style="font-size: 9pt; font-weight: bold; margin-bottom: 1px; color: #333;">' . strtoupper('System Performance & Error Logs') . '</div>
                        <div style="font-size: 7pt; color: #666;">Report ID: ' . htmlspecialchars($reportId) . ' | Page {PAGENO} of {nbpg}</div>
                    </td>
                    <td style="width: 33.33%; text-align: right; vertical-align: middle;">
                        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 1px; color: #333;">AiToManabi</div>
                        <div style="font-size: 7pt; color: #666;">Learning Management System</div>
                    </td>
                </tr>
            </table>
        </div>';

            $mpdf->SetHTMLHeader($header);

            // Footer with contact and confidentiality
            $readableTime = date('M j, Y g:i A T');
            $footer = '
        <div style="width: 100%; font-size: 6pt; color: #374151; border-top: 1px solid #e5e7eb; padding: 6px 0; background: #f8fafc;">
            <table style="width: 100%; border-collapse: collapse; margin: 0;">
                <tr>
                    <td style="width: 40%; vertical-align: top;">
                        <div style="font-weight: bold; color: #1e40af; margin-bottom: 2px; font-size: 7pt;">' . htmlspecialchars($companyName) . '</div>
                        <div style="line-height: 1.3; color: #4b5563; font-size: 6pt;">
                            ' . htmlspecialchars($companyEmail) . ' | ' . htmlspecialchars($companyWebsite) . '<br>
                        </div>
                    </td>
                    <td style="width: 20%; text-align: center; vertical-align: middle;">
                        <div style="font-size: 6pt; color: #6b7280;">Generated by ' . htmlspecialchars($companyName) . ' Report System</div>
                    </td>
                    <td style="width: 40%; text-align: right; vertical-align: top;">
                        <div style="font-weight: bold; color: #1e40af; margin-bottom: 2px; font-size: 7pt;">Document Info</div>
                        <div style="line-height: 1.3; color: #4b5563; font-size: 6pt;">
                            Report v' . htmlspecialchars($reportVersion) . ' | Generated: ' . $readableTime . '<br>
                            Status: INTERNAL USE | Page {PAGENO} of {nbpg}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: center; padding-top: 4px; border-top: 1px solid #d1d5db; margin-top: 4px;">
                        <div style="font-size: 5pt; color: #dc2626; font-weight: bold; line-height: 1.2;">
                            ⚠ CONFIDENTIAL: For authorized use only. Unauthorized access, copying, or distribution is strictly prohibited.
                        </div>
                    </td>
                </tr>
            </table>
        </div>';

            $mpdf->SetHTMLFooter($footer);

            // Build main HTML body (consistent styling)
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"/><style>'
                . 'body{font-family:DejaVu Sans, Arial, sans-serif; font-size:10px; color:#333; margin:0; padding:0;}'
                . 'table{width:100%; border-collapse:collapse; margin-top:8px;}'
                . 'th, td{border:1px solid #d1d5db; padding:6px; text-align:left; vertical-align:top; font-size:9px;}'
                . 'th{background:linear-gradient(135deg,#374151,#4b5563); color:#fff; font-weight:bold; padding:6px;}'
                . '.meta{font-size:8pt; color:#666; margin-top:4px;}'
                . '</style></head><body>';

            $html .= '<div style="padding:6px 0 0 0;"><div style="font-size:11pt;font-weight:bold;color:#111;margin-bottom:4px;">System Performance & Error Logs Report</div>';
            $html .= '<div class="meta">Generated on: ' . date('Y-m-d H:i:s') . '</div></div>';

            $html .= '<table><thead><tr>';
            $cols = ['Event Type','Title','Start Time','End Time','Duration','Status','User ID','IP Address','Device','Browser','OS','Created At'];
            foreach ($cols as $c) { $html .= '<th>' . htmlspecialchars($c) . '</th>'; }
            $html .= '</tr></thead><tbody>';

            foreach ($data as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['event_type'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['title'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['start_time'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['end_time'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['duration'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['status'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['user_id'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['ip_address'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['device_type'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['browser'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['os'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['created_at'] ?? '') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></body></html>';

            $mpdf->WriteHTML($html);

            // Output PDF for download
            $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
            exit;

        } catch (Exception $e) {
            error_log('PerformanceMonitor exportToPDF error: ' . $e->getMessage());
            header('Content-Type: text/plain; charset=utf-8');
            echo "PDF generation failed: " . $e->getMessage();
            exit;
        }
    }
}

/**
 * Auto-start performance monitoring for any page that includes this file
 * To enable automatic performance logging on a page, add this line at the top:
 * define('ENABLE_PERFORMANCE_MONITORING', true);
 */
if (defined('ENABLE_PERFORMANCE_MONITORING') && ENABLE_PERFORMANCE_MONITORING === true) {
    // Store start time for this request
    if (!defined('PAGE_START_TIME')) {
        define('PAGE_START_TIME', microtime(true));
    }
    
    // Register shutdown function to log performance when page completes
    register_shutdown_function(function() {
        if (defined('PAGE_START_TIME')) {
            try {
                $page_name = basename($_SERVER['SCRIPT_NAME'], '.php');
                $page_name = ucwords(str_replace(['-', '_'], ' ', $page_name));
                
                PerformanceMonitor::logPageLoad($page_name, 'Page Load', PAGE_START_TIME);
            } catch (Exception $e) {
                // Silently fail to prevent breaking the page
                error_log("Auto performance logging failed: " . $e->getMessage());
            }
        }
    });
}
?>
