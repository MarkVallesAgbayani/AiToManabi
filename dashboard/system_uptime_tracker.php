<?php
/**
 * System Uptime Tracker
 * Automatically tracks system uptime/downtime without requiring cron jobs
 * This runs when users visit the dashboard pages
 */

// Handle different directory levels
if (file_exists('../config/database.php')) {
    require_once '../config/database.php';
} elseif (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    error_log("Database config not found");
    return;
}

class SystemUptimeTracker {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    /**
     * Check and update system status
     * This method is called automatically when users visit dashboard pages
     */
    public function checkSystemStatus() {
        try {
            // Get the latest system status
            $stmt = $this->pdo->query("
                SELECT event_type, start_time, status 
                FROM system_uptime_log 
                ORDER BY start_time DESC 
                LIMIT 1
            ");
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentTime = date('Y-m-d H:i:s');
            
            if (!$latest) {
                // No previous records - start with uptime
                $this->startUptimeEvent();
                return;
            }
            
            // Check if we need to update status
            $timeSinceLastUpdate = time() - strtotime($latest['start_time']);
            
            // If last update was more than 5 minutes ago, check if system is still up
            if ($timeSinceLastUpdate > 300) { // 5 minutes
                if ($latest['event_type'] === 'uptime' && $latest['status'] === 'active') {
                    // System was up, check if it's still up
                    if ($this->isSystemOnline()) {
                        // Still online, update the current uptime record
                        $this->updateCurrentUptime();
                    } else {
                        // System went down, end uptime and start downtime
                        $this->endCurrentEvent($latest['start_time']);
                        $this->startDowntimeEvent();
                    }
                } else if ($latest['event_type'] === 'downtime' && $latest['status'] === 'active') {
                    // System was down, check if it's back up
                    if ($this->isSystemOnline()) {
                        // System is back up, end downtime and start uptime
                        $this->endCurrentEvent($latest['start_time']);
                        $this->startUptimeEvent();
                    } else {
                        // Still down, update the current downtime record
                        $this->updateCurrentDowntime();
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("System uptime tracking error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if system is currently online
     */
    private function isSystemOnline() {
        try {
            // Simple database connectivity test
            $stmt = $this->pdo->query("SELECT 1");
            $result = $stmt->fetch();
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Start a new uptime event
     */
    private function startUptimeEvent() {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_uptime_log (event_type, start_time, status, monitored_by) 
            VALUES ('uptime', NOW(), 'active', 'system')
        ");
        $stmt->execute();
    }
    
    /**
     * Start a new downtime event
     */
    private function startDowntimeEvent() {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_uptime_log (event_type, start_time, status, monitored_by) 
            VALUES ('downtime', NOW(), 'active', 'system')
        ");
        $stmt->execute();
    }
    
    /**
     * End current event and calculate duration
     */
    private function endCurrentEvent($startTime) {
        $stmt = $this->pdo->prepare("
            UPDATE system_uptime_log 
            SET end_time = NOW(), 
                duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                status = 'completed'
            WHERE start_time = ? AND status = 'active'
        ");
        $stmt->execute([$startTime]);
    }
    
    /**
     * Update current uptime record (extend duration)
     */
    private function updateCurrentUptime() {
        $stmt = $this->pdo->prepare("
            UPDATE system_uptime_log 
            SET end_time = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW())
            WHERE event_type = 'uptime' AND status = 'active'
            ORDER BY start_time DESC 
            LIMIT 1
        ");
        $stmt->execute();
    }
    
    /**
     * Update current downtime record (extend duration)
     */
    private function updateCurrentDowntime() {
        $stmt = $this->pdo->prepare("
            UPDATE system_uptime_log 
            SET end_time = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW())
            WHERE event_type = 'downtime' AND status = 'active'
            ORDER BY start_time DESC 
            LIMIT 1
        ");
        $stmt->execute();
    }
    
    /**
     * Get current system status
     */
    public function getCurrentStatus() {
        $stmt = $this->pdo->query("
            SELECT 
                event_type,
                start_time,
                TIMESTAMPDIFF(SECOND, start_time, NOW()) as current_duration,
                status
            FROM system_uptime_log 
            WHERE status = 'active'
            ORDER BY start_time DESC 
            LIMIT 1
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return [
                'current_status' => 'unknown',
                'current_duration' => 0,
                'current_since' => date('Y-m-d H:i:s')
            ];
        }
        
        return [
            'current_status' => $result['event_type'] === 'uptime' ? 'online' : 'offline',
            'current_duration' => $result['current_duration'],
            'current_since' => $result['start_time']
        ];
    }
}

// Auto-initialize uptime tracking when this file is included
if (defined('ENABLE_PERFORMANCE_MONITORING') && ENABLE_PERFORMANCE_MONITORING === true) {
    try {
        $uptimeTracker = new SystemUptimeTracker($pdo);
        $uptimeTracker->checkSystemStatus();
    } catch (Exception $e) {
        error_log("Auto uptime tracking failed: " . $e->getMessage());
    }
}
?>
