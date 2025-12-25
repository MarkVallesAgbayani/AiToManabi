<?php
/**
 * Teacher Notification API
 * Handles AJAX requests for teacher notifications
 */

session_start();
require_once '../../config/database.php';
require_once 'teacher_notifications.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? '';
    $teacherNotificationSystem = initializeTeacherNotifications($pdo, $_SESSION['user_id'], $_SESSION['role']);

    switch ($action) {
        case 'get_count':
            $count = $teacherNotificationSystem->getNotificationCount();
            echo json_encode(['count' => $count]);
            break;

        case 'get_notifications':
            $limit = intval($_GET['limit'] ?? 20);
            $notifications = $teacherNotificationSystem->getNotifications($limit);
            echo json_encode(['notifications' => $notifications]);
            break;

        case 'get_by_category':
            $category = $_GET['category'] ?? '';
            if (empty($category)) {
                throw new Exception('Category parameter is required');
            }
            $notifications = $teacherNotificationSystem->getNotificationsByCategory($category);
            echo json_encode(['notifications' => $notifications, 'category' => $category]);
            break;

        case 'mark_as_read':
            // This would be implemented when we add a notifications table
            // For now, just return success
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            break;

        case 'get_student_progress':
            $notifications = $teacherNotificationSystem->getNotificationsByCategory('student_progress');
            echo json_encode(['notifications' => $notifications]);
            break;

        case 'get_engagement_alerts':
            $notifications = $teacherNotificationSystem->getNotificationsByCategory('engagement');
            echo json_encode(['notifications' => $notifications]);
            break;

        case 'get_course_updates':
            $notifications = $teacherNotificationSystem->getNotificationsByCategory('course_updates');
            echo json_encode(['notifications' => $notifications]);
            break;

        case 'get_admin_updates':
            $notifications = $teacherNotificationSystem->getNotificationsByCategory('admin_updates');
            echo json_encode(['notifications' => $notifications]);
            break;

        case 'get_stats':
            $all_notifications = $teacherNotificationSystem->getNotifications();
            $stats = [
                'total' => count($all_notifications),
                'student_progress' => count($teacherNotificationSystem->getNotificationsByCategory('student_progress')),
                'engagement' => count($teacherNotificationSystem->getNotificationsByCategory('engagement')),
                'course_updates' => count($teacherNotificationSystem->getNotificationsByCategory('course_updates')),
                'admin_updates' => count($teacherNotificationSystem->getNotificationsByCategory('admin_updates')),
                'last_updated' => date('Y-m-d H:i:s')
            ];
            echo json_encode(['stats' => $stats]);
            break;

        default:
            throw new Exception('Invalid action parameter');
    }

} catch (Exception $e) {
    error_log("Teacher notification API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
