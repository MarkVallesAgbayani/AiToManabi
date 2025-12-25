<?php
/**
 * Test script for audit logging functionality
 * This script tests the audit logging for course creation and editing
 */

session_start();
require_once '../config/database.php';
require_once 'audit_logger.php';

// Set test session data
$_SESSION['user_id'] = 1; // Test user ID
$_SESSION['username'] = 'Test Teacher';
$_SESSION['role'] = 'teacher';

echo "<h2>Audit Logging Test</h2>\n";

try {
    $auditLogger = createAuditLogger($pdo);
    
    // Test 1: Course Creation (Published)
    echo "<h3>Test 1: Course Creation (Published)</h3>\n";
    $result1 = $auditLogger->logEntry([
        'user_id' => 1,
        'username' => 'Test Teacher',
        'user_role' => 'teacher',
        'action_type' => 'CREATE',
        'action_description' => 'Created and published new module',
        'resource_type' => 'Course',
        'resource_id' => 'Course ID: 123',
        'resource_name' => 'Test Japanese Course',
        'outcome' => 'Success',
        'new_value' => "Module 'Test Japanese Course' published successfully",
        'context' => [
            'module_id' => 123,
            'module_title' => 'Test Japanese Course',
            'status' => 'published',
            'sections_count' => 3,
            'price' => 99.99,
            'category_id' => 1,
            'course_category_id' => 2
        ]
    ]);
    
    echo "Result: " . ($result1 ? "SUCCESS" : "FAILED") . "<br>\n";
    
    // Test 2: Course Creation (Draft)
    echo "<h3>Test 2: Course Creation (Draft)</h3>\n";
    $result2 = $auditLogger->logEntry([
        'user_id' => 1,
        'username' => 'Test Teacher',
        'user_role' => 'teacher',
        'action_type' => 'CREATE',
        'action_description' => 'Created module as draft',
        'resource_type' => 'Course',
        'resource_id' => 'Course ID: 124',
        'resource_name' => 'Draft Japanese Course',
        'outcome' => 'Success',
        'new_value' => "Module 'Draft Japanese Course' saved as draft",
        'context' => [
            'module_id' => 124,
            'module_title' => 'Draft Japanese Course',
            'status' => 'draft',
            'sections_count' => 2,
            'price' => 0.00,
            'category_id' => 1,
            'course_category_id' => 2
        ]
    ]);
    
    echo "Result: " . ($result2 ? "SUCCESS" : "FAILED") . "<br>\n";
    
    // Test 3: Course Update (Published)
    echo "<h3>Test 3: Course Update (Published)</h3>\n";
    $result3 = $auditLogger->logEntry([
        'user_id' => 1,
        'username' => 'Test Teacher',
        'user_role' => 'teacher',
        'action_type' => 'UPDATE',
        'action_description' => 'Published course',
        'resource_type' => 'Course',
        'resource_id' => 'Course ID: 125',
        'resource_name' => 'Updated Japanese Course',
        'outcome' => 'Success',
        'new_value' => "Course 'Updated Japanese Course' published successfully",
        'context' => [
            'course_id' => 125,
            'course_title' => 'Updated Japanese Course',
            'status' => 'published',
            'mode' => 'edit',
            'sections_count' => 4,
            'chapters_count' => 8,
            'quizzes_count' => 2
        ]
    ]);
    
    echo "Result: " . ($result3 ? "SUCCESS" : "FAILED") . "<br>\n";
    
    // Test 4: Course Update (Draft)
    echo "<h3>Test 4: Course Update (Draft)</h3>\n";
    $result4 = $auditLogger->logEntry([
        'user_id' => 1,
        'username' => 'Test Teacher',
        'user_role' => 'teacher',
        'action_type' => 'UPDATE',
        'action_description' => 'Saved course as draft',
        'resource_type' => 'Course',
        'resource_id' => 'Course ID: 126',
        'resource_name' => 'Updated Draft Course',
        'outcome' => 'Success',
        'new_value' => "Course 'Updated Draft Course' saved as draft",
        'context' => [
            'course_id' => 126,
            'course_title' => 'Updated Draft Course',
            'status' => 'draft',
            'mode' => 'edit',
            'sections_count' => 2,
            'chapters_count' => 5,
            'quizzes_count' => 1
        ]
    ]);
    
    echo "Result: " . ($result4 ? "SUCCESS" : "FAILED") . "<br>\n";
    
    // Check if audit entries were created
    echo "<h3>Verification: Checking Audit Entries</h3>\n";
    
    try {
        // Check comprehensive audit trail table
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comprehensive_audit_trail WHERE user_id = 1 AND resource_type = 'Course'");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Audit entries found in comprehensive_audit_trail: $count<br>\n";
        
        if ($count > 0) {
            // Show recent entries
            $stmt = $pdo->prepare("SELECT action_type, action_description, resource_name, outcome, timestamp FROM comprehensive_audit_trail WHERE user_id = 1 AND resource_type = 'Course' ORDER BY timestamp DESC LIMIT 5");
            $stmt->execute();
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>Recent Audit Entries:</h4>\n";
            echo "<table border='1' cellpadding='5'>\n";
            echo "<tr><th>Action Type</th><th>Description</th><th>Resource Name</th><th>Outcome</th><th>Timestamp</th></tr>\n";
            
            foreach ($entries as $entry) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($entry['action_type']) . "</td>";
                echo "<td>" . htmlspecialchars($entry['action_description']) . "</td>";
                echo "<td>" . htmlspecialchars($entry['resource_name']) . "</td>";
                echo "<td>" . htmlspecialchars($entry['outcome']) . "</td>";
                echo "<td>" . htmlspecialchars($entry['timestamp']) . "</td>";
                echo "</tr>\n";
            }
            
            echo "</table>\n";
        }
        
    } catch (Exception $e) {
        echo "Error checking audit entries: " . $e->getMessage() . "<br>\n";
    }
    
    echo "<h3>Test Summary</h3>\n";
    echo "All tests completed. Check the audit trail table to verify entries were created.<br>\n";
    echo "If you see audit entries above, the audit logging is working correctly.<br>\n";
    
} catch (Exception $e) {
    echo "Test failed with error: " . $e->getMessage() . "<br>\n";
}

echo "<br><a href='javascript:history.back()'>Go Back</a>\n";
?>
