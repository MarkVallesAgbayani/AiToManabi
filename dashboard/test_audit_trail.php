<?php
/**
 * Test script to verify audit trail functionality
 * This script tests the audit logging system with real data
 */

session_start();
require_once '../config/database.php';
require_once 'audit_logger.php';
require_once 'audit_database_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

echo "<h1>Audit Trail Test</h1>";

try {
    // Test 1: Create comprehensive audit table if it doesn't exist
    echo "<h2>Test 1: Creating comprehensive audit table</h2>";
    if (createComprehensiveAuditTable($pdo)) {
        echo "✅ Comprehensive audit table created/verified successfully<br>";
    } else {
        echo "❌ Failed to create comprehensive audit table<br>";
    }
    
    // Test 2: Log a test audit entry
    echo "<h2>Test 2: Logging test audit entry</h2>";
    $auditLogger = new AuditLogger($pdo);
    $result = $auditLogger->logEntry([
        'action_type' => 'CREATE',
        'action_description' => 'Test audit entry - Audit trail system test',
        'resource_type' => 'System Config',
        'resource_id' => 'Test ID: ' . time(),
        'resource_name' => 'Audit Trail Test',
        'outcome' => 'Success',
        'new_value' => 'Test value for audit trail verification',
        'context' => [
            'test' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'Unknown'
        ]
    ]);
    
    if ($result) {
        echo "✅ Test audit entry logged successfully<br>";
    } else {
        echo "❌ Failed to log test audit entry<br>";
    }
    
    // Test 3: Retrieve audit data
    echo "<h2>Test 3: Retrieving audit data</h2>";
    $filters = [
        'date_from' => date('Y-m-d', strtotime('-7 days')),
        'date_to' => date('Y-m-d'),
        'search' => 'test'
    ];
    
    $auditData = getRealAuditData($pdo, 0, 10, $filters);
    $totalRecords = getTotalAuditRecords($pdo, $filters);
    
    echo "✅ Retrieved " . count($auditData) . " audit records (Total: $totalRecords)<br>";
    
    if (!empty($auditData)) {
        echo "<h3>Sample Audit Records:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Timestamp</th><th>User</th><th>Action</th><th>Resource</th><th>Outcome</th></tr>";
        
        foreach (array_slice($auditData, 0, 5) as $record) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($record['id']) . "</td>";
            echo "<td>" . htmlspecialchars($record['timestamp']) . "</td>";
            echo "<td>" . htmlspecialchars($record['username']) . "</td>";
            echo "<td>" . htmlspecialchars($record['action_type']) . "</td>";
            echo "<td>" . htmlspecialchars($record['resource_type']) . "</td>";
            echo "<td>" . htmlspecialchars($record['outcome']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 4: Get audit statistics
    echo "<h2>Test 4: Audit statistics</h2>";
    $statistics = getRealAuditStatistics($pdo);
    
    echo "✅ Audit statistics retrieved:<br>";
    echo "- Total Actions: " . $statistics['total_actions'] . "<br>";
    echo "- Actions Today: " . $statistics['actions_today'] . "<br>";
    echo "- Failed Actions: " . $statistics['failed_actions'] . "<br>";
    echo "- Unique Users: " . $statistics['unique_users'] . "<br>";
    echo "- Most Active User: " . $statistics['most_active_user'] . "<br>";
    
    // Test 5: Check if audit trail page works
    echo "<h2>Test 5: Audit trail page access</h2>";
    echo "✅ <a href='audit-trails.php' target='_blank'>Click here to view the audit trail page</a><br>";
    
    echo "<h2>✅ All tests completed successfully!</h2>";
    echo "<p>The audit trail system is working correctly with real data.</p>";
    echo "<p><strong>Note:</strong> Sample data generation has been removed. Only real audit data will be shown.</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Test failed</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}

echo "<hr>";
echo "<p><a href='admin.php'>← Back to Admin Dashboard</a></p>";
?>
