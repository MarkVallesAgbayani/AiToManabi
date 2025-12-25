<?php
/**
 * System Uptime Monitor
 * Background script to monitor system uptime and log downtime events
 * Run this script via cron job every minute or as a background service
 */

require_once __DIR__ . '/../config/database.php';
require_once 'performance_monitoring_functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

class SystemMonitor {
    private $pdo;
    private $lastCheckTime;
    private $monitorInterval = 60; // Check every 60 seconds
    
    public function __construct($database) {
        $this->pdo = $database;
        $this->lastCheckTime = time();
    }
    
    /**
     * Check system health and log events
     */
    public function checkSystemHealth() {
        try {
            // Test database connection
            $dbHealthy = $this->checkDatabaseHealth();
            
            // Test file system
            $fsHealthy = $this->checkFileSystemHealth();
            
            // Test memory usage
            $memoryHealthy = $this->checkMemoryHealth();
            
            // Overall system status
            $systemHealthy = $dbHealthy && $fsHealthy && $memoryHealthy;
            
            if ($systemHealthy) {
                $this->logUptime();
            } else {
                $this->logDowntime($dbHealthy, $fsHealthy, $memoryHealthy);
            }
            
            // Log system health metrics
            $this->logHealthMetrics();
            
            return $systemHealthy;
            
        } catch (Exception $e) {
            $this->logDowntime(false, false, false, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth() {
        try {
            $stmt = $this->pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check file system health
     */
    private function checkFileSystemHealth() {
        try {
            // Check if we can write to uploads directory
            $uploadsDir = __DIR__ . '/../uploads';
            if (!is_dir($uploadsDir)) {
                return false;
            }
            
            // Test write permission
            $testFile = $uploadsDir . '/.health_check_' . time();
            $result = file_put_contents($testFile, 'health check');
            if ($result === false) {
                return false;
            }
            
            // Clean up test file
            unlink($testFile);
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check memory health
     */
    private function checkMemoryHealth() {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        // Consider unhealthy if using more than 80% of memory limit
        return ($memoryUsage / $memoryLimit) < 0.8;
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        
        switch($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }
    
    /**
     * Log uptime event
     */
    private function logUptime() {
        // Check if there's already an active uptime event
        $sql = "SELECT id FROM system_uptime_log WHERE event_type = 'uptime' AND status = 'active' ORDER BY start_time DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $existingUptime = $stmt->fetch();
        
        if (!$existingUptime) {
            // Start new uptime event
            $sql = "INSERT INTO system_uptime_log (event_type, start_time, status, server_ip, monitored_by) VALUES ('uptime', NOW(), 'active', ?, 'system-monitor')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$_SERVER['SERVER_ADDR'] ?? '127.0.0.1']);
        }
    }
    
    /**
     * Log downtime event
     */
    private function logDowntime($dbHealthy, $fsHealthy, $memoryHealthy, $errorMessage = null) {
        // Check if there's an active uptime event to close
        $sql = "SELECT id FROM system_uptime_log WHERE event_type = 'uptime' AND status = 'active' ORDER BY start_time DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $activeUptime = $stmt->fetch();
        
        if ($activeUptime) {
            // Close the uptime event
            $sql = "UPDATE system_uptime_log SET end_time = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()), status = 'completed' WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$activeUptime['id']]);
        }
        
        // Check if there's already an active downtime event
        $sql = "SELECT id FROM system_uptime_log WHERE event_type = 'downtime' AND status = 'active' ORDER BY start_time DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $existingDowntime = $stmt->fetch();
        
        if (!$existingDowntime) {
            // Determine root cause
            $rootCause = [];
            if (!$dbHealthy) $rootCause[] = 'Database';
            if (!$fsHealthy) $rootCause[] = 'File System';
            if (!$memoryHealthy) $rootCause[] = 'Memory';
            
            $cause = empty($rootCause) ? 'Unknown' : implode(', ', $rootCause);
            $severity = count($rootCause) > 1 ? 'high' : 'medium';
            
            // Start new downtime event
            $sql = "INSERT INTO system_uptime_log (event_type, start_time, status, error_message, root_cause, severity, server_ip, monitored_by) VALUES ('downtime', NOW(), 'active', ?, ?, ?, ?, 'system-monitor')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $errorMessage,
                $cause,
                $severity,
                $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'
            ]);
        }
    }
    
    /**
     * Log system health metrics
     */
    private function logHealthMetrics() {
        $metrics = [
            'cpu' => $this->getCpuUsage(),
            'memory' => memory_get_usage(true) / 1024 / 1024, // MB
            'disk' => $this->getDiskUsage(),
            'database' => $this->getDatabaseResponseTime(),
            'response_time' => $this->getSystemResponseTime()
        ];
        
        foreach ($metrics as $type => $value) {
            $status = $this->getMetricStatus($type, $value);
            
            $sql = "INSERT INTO system_health_metrics (metric_type, metric_value, metric_unit, threshold_warning, threshold_critical, status, recorded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            
            switch ($type) {
                case 'cpu':
                    $stmt->execute([$type, $value, '%', 70, 90, $status]);
                    break;
                case 'memory':
                    $stmt->execute([$type, $value, 'MB', 2048, 3072, $status]);
                    break;
                case 'disk':
                    $stmt->execute([$type, $value, 'GB', 5, 2, $status]);
                    break;
                case 'database':
                    $stmt->execute([$type, $value, 'ms', 500, 1000, $status]);
                    break;
                case 'response_time':
                    $stmt->execute([$type, $value, 'ms', 1000, 3000, $status]);
                    break;
            }
        }
    }
    
    /**
     * Get CPU usage (simplified)
     */
    private function getCpuUsage() {
        // This is a simplified CPU usage calculation
        // In production, you might want to use system commands or extensions
        return rand(10, 80); // Simulated for demo
    }
    
    /**
     * Get disk usage
     */
    private function getDiskUsage() {
        $bytes = disk_free_space(__DIR__);
        $total = disk_total_space(__DIR__);
        return round(($total - $bytes) / 1024 / 1024 / 1024, 2); // GB
    }
    
    /**
     * Get database response time
     */
    private function getDatabaseResponseTime() {
        $start = microtime(true);
        $this->pdo->query("SELECT 1");
        return round((microtime(true) - $start) * 1000, 2); // ms
    }
    
    /**
     * Get system response time
     */
    private function getSystemResponseTime() {
        // Simulate system response time
        return rand(50, 500); // ms
    }
    
    /**
     * Get metric status based on value and thresholds
     */
    private function getMetricStatus($type, $value) {
        switch ($type) {
            case 'cpu':
                return $value > 90 ? 'critical' : ($value > 70 ? 'warning' : 'healthy');
            case 'memory':
                return $value > 3072 ? 'critical' : ($value > 2048 ? 'warning' : 'healthy');
            case 'disk':
                return $value > 2 ? 'critical' : ($value > 5 ? 'warning' : 'healthy');
            case 'database':
                return $value > 1000 ? 'critical' : ($value > 500 ? 'warning' : 'healthy');
            case 'response_time':
                return $value > 3000 ? 'critical' : ($value > 1000 ? 'warning' : 'healthy');
            default:
                return 'healthy';
        }
    }
    
    /**
     * Run continuous monitoring
     */
    public function runContinuous() {
        echo "Starting system monitoring...\n";
        echo "Monitoring interval: {$this->monitorInterval} seconds\n";
        echo "Press Ctrl+C to stop\n\n";
        
        while (true) {
            $healthy = $this->checkSystemHealth();
            $status = $healthy ? 'HEALTHY' : 'UNHEALTHY';
            $timestamp = date('Y-m-d H:i:s');
            
            echo "[$timestamp] System Status: $status\n";
            
            sleep($this->monitorInterval);
        }
    }
    
    /**
     * Run single check
     */
    public function runSingleCheck() {
        $healthy = $this->checkSystemHealth();
        $status = $healthy ? 'HEALTHY' : 'UNHEALTHY';
        $timestamp = date('Y-m-d H:i:s');
        
        echo "[$timestamp] System Status: $status\n";
        return $healthy;
    }
}

// Run the monitor
if (php_sapi_name() === 'cli') {
    $monitor = new SystemMonitor($pdo);
    
    // Check command line arguments
    if (isset($argv[1]) && $argv[1] === 'continuous') {
        $monitor->runContinuous();
    } else {
        $monitor->runSingleCheck();
    }
} else {
    // Web interface - just run a single check
    $monitor = new SystemMonitor($pdo);
    $healthy = $monitor->runSingleCheck();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => 'Asia/Manila'
    ]);
}
?>
