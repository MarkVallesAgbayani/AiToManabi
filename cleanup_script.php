<?php
/**
 * Hostinger Cleanup Script
 * Run this via Hostinger's cron job (weekly)
 * Safe for production - only removes old data
 */

require_once 'config/database.php';
require_once 'config/hostinger_database_optimization.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Log cleanup start
error_log("=== HOSTINGER CLEANUP STARTED: " . date('Y-m-d H:i:s') . " ===");

try {
    // Check connection health first
    if (!checkHostingerConnectionHealth($pdo)) {
        error_log("Database connection unhealthy - aborting cleanup");
        exit(1);
    }
    
    // Run cleanup
    $result = cleanupHostingerDatabase($pdo);
    
    if ($result) {
        error_log("Cleanup completed successfully: " . json_encode($result));
    } else {
        error_log("Cleanup failed");
        exit(1);
    }
    
} catch (Exception $e) {
    error_log("Cleanup script error: " . $e->getMessage());
    exit(1);
}

// Log cleanup end
error_log("=== HOSTINGER CLEANUP COMPLETED: " . date('Y-m-d H:i:s') . " ===");
?>
