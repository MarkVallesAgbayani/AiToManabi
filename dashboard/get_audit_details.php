<?php
session_start();
require_once '../config/database.php';
require_once 'audit_database_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get audit ID
$audit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($audit_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid audit ID']);
    exit();
}

try {
    // First try comprehensive audit trail table
    $sql = "SELECT 
                cat.*,
                u.email as user_email
            FROM comprehensive_audit_trail cat
            LEFT JOIN users u ON cat.user_id = u.id
            WHERE cat.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$audit_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found in comprehensive table, try fallback tables
    if (!$record) {
        // Try admin_audit_log
        $sql = "SELECT 
                    aal.id,
                    aal.created_at as timestamp,
                    aal.admin_id as user_id,
                    u.username,
                    u.role as user_role,
                    u.email as user_email,
                    'ADMIN' as action_type,
                    aal.action as action_description,
                    'System Config' as resource_type,
                    CONCAT('Admin Action ID: ', aal.id) as resource_id,
                    aal.action as resource_name,
                    aal.ip_address,
                    'Success' as outcome,
                    NULL as old_value_text,
                    NULL as new_value_text,
                    aal.user_agent as device_info,
                    NULL as browser_name,
                    NULL as operating_system,
                    'Desktop' as device_type,
                    NULL as location_city,
                    NULL as location_country,
                    NULL as session_id,
                    'POST' as request_method,
                    NULL as request_url,
                    200 as response_code,
                    NULL as response_time_ms,
                    NULL as error_message,
                    aal.details as additional_context
                FROM admin_audit_log aal
                JOIN users u ON aal.admin_id = u.id
                WHERE aal.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$audit_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If still not found, try other tables
        if (!$record) {
            // Try audit_trail table (adjust ID since we add 1000 to it)
            $original_id = $audit_id - 1000;
            if ($original_id > 0) {
                $sql = "SELECT 
                            at.id + 1000 as id,
                            at.created_at as timestamp,
                            at.user_id,
                            u.username,
                            u.role as user_role,
                            u.email as user_email,
                            'UPDATE' as action_type,
                            at.action as action_description,
                            'Course' as resource_type,
                            CONCAT('Course ID: ', at.course_id) as resource_id,
                            at.action as resource_name,
                            '0.0.0.0' as ip_address,
                            'Success' as outcome,
                            NULL as old_value_text,
                            at.details as new_value_text,
                            'Unknown Browser' as device_info,
                            NULL as browser_name,
                            NULL as operating_system,
                            'Desktop' as device_type,
                            NULL as location_city,
                            NULL as location_country,
                            NULL as session_id,
                            'POST' as request_method,
                            NULL as request_url,
                            200 as response_code,
                            NULL as response_time_ms,
                            NULL as error_message,
                            NULL as additional_context
                        FROM audit_trail at
                        JOIN users u ON at.user_id = u.id
                        WHERE at.id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$original_id]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
    
    if ($record) {
        // Process additional context if it's JSON
        if ($record['additional_context'] && is_string($record['additional_context'])) {
            $decoded = json_decode($record['additional_context'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $record['additional_context'] = json_encode($decoded);
            }
        }
        
        // Add computed fields
        $record['location'] = 'Unknown Location';
        if ($record['location_city'] && $record['location_country']) {
            $record['location'] = $record['location_city'] . ', ' . $record['location_country'];
        } elseif ($record['location_country']) {
            $record['location'] = $record['location_country'];
        }
        
        // Format device info
        if ($record['browser_name'] && $record['operating_system']) {
            $record['formatted_device'] = $record['browser_name'] . ' on ' . $record['operating_system'];
        } else {
            $record['formatted_device'] = $record['device_info'];
        }
        
        echo json_encode([
            'success' => true,
            'record' => $record
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Audit record not found'
        ]);
    }

} catch (PDOException $e) {
    error_log("Audit details query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Audit details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching audit details'
    ]);
}
?>
