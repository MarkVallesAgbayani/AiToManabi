<?php
/**
 * Teacher Notification Preferences API
 * Handles saving and retrieving notification preferences
 */

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $teacher_id = $_SESSION['user_id'];

    switch ($action) {
        case 'get_preferences':
            $preferences = getTeacherNotificationPreferences($pdo, $teacher_id);
            echo json_encode(['success' => true, 'preferences' => $preferences]);
            break;

        case 'save_preferences':
            $preferences = json_decode($_POST['preferences'] ?? '{}', true);
            $result = saveTeacherNotificationPreferences($pdo, $teacher_id, $preferences);
            echo json_encode($result);
            break;

        case 'save_single_preference':
            $category = $_POST['category'] ?? '';
            $key = $_POST['key'] ?? '';
            $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $frequency = $_POST['frequency'] ?? 'real_time';
            
            if (empty($category) || empty($key)) {
                throw new Exception('Category and key are required');
            }
            
            $result = saveSingleNotificationPreference($pdo, $teacher_id, $category, $key, $enabled, $frequency);
            echo json_encode($result);
            break;

        case 'reset_to_defaults':
            $result = resetNotificationPreferencesToDefaults($pdo, $teacher_id);
            echo json_encode($result);
            break;

        default:
            throw new Exception('Invalid action parameter');
    }

} catch (Exception $e) {
    error_log("Notification preferences API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get teacher notification preferences
 */
function getTeacherNotificationPreferences($pdo, $teacher_id) {
    $stmt = $pdo->prepare("
        SELECT 
            preference_category,
            preference_key,
            is_enabled,
            notification_method,
            priority_level,
            frequency
        FROM teacher_notification_preferences 
        WHERE teacher_id = ?
        ORDER BY preference_category, preference_key
    ");
    $stmt->execute([$teacher_id]);
    $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group preferences by category
    $grouped = [];
    foreach ($preferences as $pref) {
        $grouped[$pref['preference_category']][$pref['preference_key']] = $pref;
    }
    
    return $grouped;
}

/**
 * Save teacher notification preferences
 */
function saveTeacherNotificationPreferences($pdo, $teacher_id, $preferences) {
    $pdo->beginTransaction();
    
    try {
        // Clear existing preferences for this teacher
        $stmt = $pdo->prepare("DELETE FROM teacher_notification_preferences WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        
        // Insert new preferences
        $stmt = $pdo->prepare("
            INSERT INTO teacher_notification_preferences 
            (teacher_id, preference_category, preference_key, is_enabled, notification_method, priority_level, frequency)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $saved_count = 0;
        foreach ($preferences as $category => $category_prefs) {
            foreach ($category_prefs as $key => $pref) {
                $stmt->execute([
                    $teacher_id,
                    $category,
                    $key,
                    $pref['enabled'] ? 1 : 0,
                    $pref['method'] ?? 'in_app',
                    $pref['priority'] ?? 'medium',
                    $pref['frequency'] ?? 'real_time'
                ]);
                $saved_count++;
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Successfully saved {$saved_count} notification preferences",
            'saved_count' => $saved_count
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Save a single notification preference
 */
function saveSingleNotificationPreference($pdo, $teacher_id, $category, $key, $enabled, $frequency = 'real_time') {
    // Determine priority based on category and key
    $priority = determinePriority($category, $key);
    
    $stmt = $pdo->prepare("
        INSERT INTO teacher_notification_preferences 
        (teacher_id, preference_category, preference_key, is_enabled, notification_method, priority_level, frequency)
        VALUES (?, ?, ?, ?, 'in_app', ?, ?)
        ON DUPLICATE KEY UPDATE
        is_enabled = VALUES(is_enabled),
        frequency = VALUES(frequency),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([$teacher_id, $category, $key, $enabled ? 1 : 0, $priority, $frequency]);
    
    return [
        'success' => true,
        'message' => 'Preference saved successfully',
        'preference' => [
            'category' => $category,
            'key' => $key,
            'enabled' => $enabled,
            'frequency' => $frequency,
            'priority' => $priority
        ]
    ];
}

/**
 * Reset notification preferences to defaults
 */
function resetNotificationPreferencesToDefaults($pdo, $teacher_id) {
    $pdo->beginTransaction();
    
    try {
        // Delete existing preferences
        $stmt = $pdo->prepare("DELETE FROM teacher_notification_preferences WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        
        // Insert default preferences
        $default_preferences = getDefaultNotificationPreferences();
        
        $stmt = $pdo->prepare("
            INSERT INTO teacher_notification_preferences 
            (teacher_id, preference_category, preference_key, is_enabled, notification_method, priority_level, frequency)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $inserted_count = 0;
        foreach ($default_preferences as $pref) {
            $stmt->execute([
                $teacher_id,
                $pref['category'],
                $pref['key'],
                $pref['enabled'] ? 1 : 0,
                $pref['method'],
                $pref['priority'],
                $pref['frequency']
            ]);
            $inserted_count++;
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Reset to defaults completed. {$inserted_count} preferences restored.",
            'preferences' => getTeacherNotificationPreferences($pdo, $teacher_id)
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get default notification preferences
 */
function getDefaultNotificationPreferences() {
    return [
        // Student Progress & Performance
        ['category' => 'student_progress', 'key' => 'new_enrollments', 'enabled' => true, 'method' => 'in_app', 'priority' => 'high', 'frequency' => 'real_time'],
        ['category' => 'student_progress', 'key' => 'course_completions', 'enabled' => true, 'method' => 'in_app', 'priority' => 'high', 'frequency' => 'real_time'],
        ['category' => 'student_progress', 'key' => 'quiz_completions', 'enabled' => false, 'method' => 'in_app', 'priority' => 'medium', 'frequency' => 'real_time'],
        ['category' => 'student_progress', 'key' => 'low_performance_alerts', 'enabled' => true, 'method' => 'in_app', 'priority' => 'high', 'frequency' => 'real_time'],
        ['category' => 'student_progress', 'key' => 'struggling_students', 'enabled' => true, 'method' => 'in_app', 'priority' => 'high', 'frequency' => 'real_time'],
        ['category' => 'student_progress', 'key' => 'weekly_progress_summaries', 'enabled' => true, 'method' => 'in_app', 'priority' => 'medium', 'frequency' => 'weekly_summary'],
        
        // Student Engagement & Activity
        ['category' => 'student_engagement', 'key' => 'inactive_students', 'enabled' => true, 'method' => 'in_app', 'priority' => 'medium', 'frequency' => 'daily_digest'],
        ['category' => 'student_engagement', 'key' => 'high_performing_students', 'enabled' => false, 'method' => 'in_app', 'priority' => 'medium', 'frequency' => 'real_time'],
        
        // Course & Content Management
        ['category' => 'course_management', 'key' => 'course_milestones', 'enabled' => false, 'method' => 'in_app', 'priority' => 'medium', 'frequency' => 'real_time'],
        ['category' => 'course_management', 'key' => 'course_status_changes', 'enabled' => true, 'method' => 'in_app', 'priority' => 'high', 'frequency' => 'real_time'],
        
        // Security & Administrative
        ['category' => 'system_administrative', 'key' => 'security_alerts', 'enabled' => true, 'method' => 'in_app', 'priority' => 'critical', 'frequency' => 'real_time'],
        
        // Reporting & Analytics
        ['category' => 'reporting_analytics', 'key' => 'daily_activity_summaries', 'enabled' => false, 'method' => 'in_app', 'priority' => 'low', 'frequency' => 'daily_digest'],
        ['category' => 'reporting_analytics', 'key' => 'weekly_engagement_reports', 'enabled' => true, 'method' => 'in_app', 'priority' => 'medium', 'frequency' => 'weekly_summary']
    ];
}

/**
 * Determine priority level based on category and key
 */
function determinePriority($category, $key) {
    $priority_map = [
        'student_progress' => [
            'new_enrollments' => 'high',
            'course_completions' => 'high',
            'quiz_completions' => 'medium',
            'low_performance_alerts' => 'high',
            'struggling_students' => 'high',
            'weekly_progress_summaries' => 'medium'
        ],
        'student_engagement' => [
            'inactive_students' => 'medium',
            'high_performing_students' => 'medium'
        ],
        'course_management' => [
            'course_milestones' => 'medium',
            'course_status_changes' => 'high'
        ],
        'system_administrative' => [
            'security_alerts' => 'critical'
        ],
        'reporting_analytics' => [
            'daily_activity_summaries' => 'low',
            'weekly_engagement_reports' => 'medium'
        ]
    ];
    
    return $priority_map[$category][$key] ?? 'medium';
}
?>
