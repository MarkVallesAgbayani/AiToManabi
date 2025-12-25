<?php
/**
 * Teacher Settings API - Placement Test Module
 * Handles saving and loading teacher preferences and settings
 */

// Disable error display to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$teacher_id = $_SESSION['user_id'];

try {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    switch ($method) {
        case 'GET':
            // Load teacher settings
            $settings = loadTeacherSettings($pdo, $teacher_id);
            echo json_encode(['success' => true, 'data' => $settings]);
            break;
            
        case 'POST':
            // Save teacher settings
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            $result = saveTeacherSettings($pdo, $teacher_id, $input);
            echo json_encode(['success' => $result, 'message' => $result ? 'Settings saved successfully' : 'Failed to save settings']);
            break;
            
        case 'PUT':
            // Handle password change
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            $result = changePassword($pdo, $teacher_id, $input);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    error_log("Teacher settings API error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    error_log("Error file: " . $e->getFile() . " line: " . $e->getLine());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API Error: ' . $e->getMessage()]);
}

function loadTeacherSettings($pdo, $teacher_id) {
    // Check if teacher_preferences table exists, create if not
    createTeacherPreferencesTable($pdo);
    
    $stmt = $pdo->prepare("SELECT * FROM teacher_preferences WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$preferences) {
        // Return default settings
        return getDefaultSettings();
    }
    
    // Parse fields
    $settings = [
        'profile' => [
            'firstName' => $preferences['first_name'] ?? '',
            'lastName' => $preferences['last_name'] ?? '',
            'displayName' => $preferences['display_name'] ?? '',
            'profilePicture' => $preferences['profile_picture'] ?? '',
            'bio' => $preferences['bio'] ?? '',
            'contactEmail' => $preferences['contact_email'] ?? '',
            'phoneNumber' => $preferences['phone_number'] ?? '',
            'website' => $preferences['website'] ?? '',
            'socialLinks' => json_decode($preferences['social_links'] ?? '{}', true)
        ],
        'notifications' => [
            'emailNotifications' => (bool)($preferences['email_notifications'] ?? true),
            'pushNotifications' => (bool)($preferences['push_notifications'] ?? true),
            'studentProgress' => (bool)($preferences['notify_student_progress'] ?? true),
            'courseUpdates' => (bool)($preferences['notify_course_updates'] ?? true),
            'systemAnnouncements' => (bool)($preferences['notify_system_announcements'] ?? true),
            'weeklyReports' => (bool)($preferences['notify_weekly_reports'] ?? false)
        ],
        'privacy' => [
            'profileVisibility' => $preferences['profile_visibility'] ?? 'public',
            'contactVisibility' => $preferences['contact_visibility'] ?? 'students',
            'activityTracking' => (bool)($preferences['activity_tracking'] ?? true),
            'dataSharing' => (bool)($preferences['data_sharing'] ?? false)
        ],
        'preferences' => [
            'language' => $preferences['language'] ?? 'en',
            'timezone' => $preferences['timezone'] ?? 'Asia/Manila',
            'dateFormat' => $preferences['date_format'] ?? 'Y-m-d',
            'timeFormat' => $preferences['time_format'] ?? '24',
            'itemsPerPage' => (int)($preferences['items_per_page'] ?? 10),
            'defaultView' => $preferences['default_view'] ?? 'grid',
            'autoSave' => (bool)($preferences['auto_save'] ?? true),
            'darkMode' => (bool)($preferences['dark_mode'] ?? false)
        ]
    ];
    
    return $settings;
}

function saveTeacherSettings($pdo, $teacher_id, $data) {
    try {
        // Check if teacher_preferences table exists, create if not
        createTeacherPreferencesTable($pdo);
        
        // Check if record exists
        $stmt = $pdo->prepare("SELECT teacher_id FROM teacher_preferences WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing record
            $sql = "UPDATE teacher_preferences SET 
                    first_name = ?, last_name = ?, display_name = ?, profile_picture = ?, bio = ?,
                    contact_email = ?, phone_number = ?, website = ?, social_links = ?,
                    email_notifications = ?, push_notifications = ?, notify_student_progress = ?,
                    notify_course_updates = ?, notify_system_announcements = ?, notify_weekly_reports = ?,
                    profile_visibility = ?, contact_visibility = ?, activity_tracking = ?, data_sharing = ?,
                    language = ?, timezone = ?, date_format = ?, time_format = ?, items_per_page = ?,
                    default_view = ?, auto_save = ?, dark_mode = ?, updated_at = NOW()
                    WHERE teacher_id = ?";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $data['profile']['firstName'] ?? '',
                $data['profile']['lastName'] ?? '',
                $data['profile']['displayName'] ?? '',
                $data['profile']['profilePicture'] ?? '',
                $data['profile']['bio'] ?? '',
                $data['profile']['contactEmail'] ?? '',
                $data['profile']['phoneNumber'] ?? '',
                $data['profile']['website'] ?? '',
                json_encode($data['profile']['socialLinks'] ?? []),
                (int)($data['notifications']['emailNotifications'] ?? true),
                (int)($data['notifications']['pushNotifications'] ?? true),
                (int)($data['notifications']['studentProgress'] ?? true),
                (int)($data['notifications']['courseUpdates'] ?? true),
                (int)($data['notifications']['systemAnnouncements'] ?? true),
                (int)($data['notifications']['weeklyReports'] ?? false),
                $data['privacy']['profileVisibility'] ?? 'public',
                $data['privacy']['contactVisibility'] ?? 'students',
                (int)($data['privacy']['activityTracking'] ?? true),
                (int)($data['privacy']['dataSharing'] ?? false),
                $data['preferences']['language'] ?? 'en',
                $data['preferences']['timezone'] ?? 'Asia/Manila',
                $data['preferences']['dateFormat'] ?? 'Y-m-d',
                $data['preferences']['timeFormat'] ?? '24',
                (int)($data['preferences']['itemsPerPage'] ?? 10),
                $data['preferences']['defaultView'] ?? 'grid',
                (int)($data['preferences']['autoSave'] ?? true),
                (int)($data['preferences']['darkMode'] ?? false),
                $teacher_id
            ]);
        } else {
            // Insert new record
            $sql = "INSERT INTO teacher_preferences (
                    teacher_id, first_name, last_name, display_name, profile_picture, bio,
                    contact_email, phone_number, website, social_links,
                    email_notifications, push_notifications, notify_student_progress,
                    notify_course_updates, notify_system_announcements, notify_weekly_reports,
                    profile_visibility, contact_visibility, activity_tracking, data_sharing,
                    language, timezone, date_format, time_format, items_per_page,
                    default_view, auto_save, dark_mode, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $teacher_id,
                $data['profile']['firstName'] ?? '',
                $data['profile']['lastName'] ?? '',
                $data['profile']['displayName'] ?? '',
                $data['profile']['profilePicture'] ?? '',
                $data['profile']['bio'] ?? '',
                $data['profile']['contactEmail'] ?? '',
                $data['profile']['phoneNumber'] ?? '',
                $data['profile']['website'] ?? '',
                json_encode($data['profile']['socialLinks'] ?? []),
                (int)($data['notifications']['emailNotifications'] ?? true),
                (int)($data['notifications']['pushNotifications'] ?? true),
                (int)($data['notifications']['studentProgress'] ?? true),
                (int)($data['notifications']['courseUpdates'] ?? true),
                (int)($data['notifications']['systemAnnouncements'] ?? true),
                (int)($data['notifications']['weeklyReports'] ?? false),
                $data['privacy']['profileVisibility'] ?? 'public',
                $data['privacy']['contactVisibility'] ?? 'students',
                (int)($data['privacy']['activityTracking'] ?? true),
                (int)($data['privacy']['dataSharing'] ?? false),
                $data['preferences']['language'] ?? 'en',
                $data['preferences']['timezone'] ?? 'Asia/Manila',
                $data['preferences']['dateFormat'] ?? 'Y-m-d',
                $data['preferences']['timeFormat'] ?? '24',
                (int)($data['preferences']['itemsPerPage'] ?? 10),
                $data['preferences']['defaultView'] ?? 'grid',
                (int)($data['preferences']['autoSave'] ?? true),
                (int)($data['preferences']['darkMode'] ?? false)
            ]);
        }
    } catch (Exception $e) {
        error_log("Error saving teacher settings: " . $e->getMessage());
        return false;
    }
}

function changePassword($pdo, $teacher_id, $data) {
    try {
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';
        
        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return ['success' => false, 'error' => 'All password fields are required'];
        }
        
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'error' => 'New passwords do not match'];
        }
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
        }
        
        // Check current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$teacher_id]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $teacher_id]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to update password'];
        }
    } catch (Exception $e) {
        error_log("Error changing password: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function createTeacherPreferencesTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS teacher_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            display_name VARCHAR(200),
            profile_picture VARCHAR(500),
            bio TEXT,
            contact_email VARCHAR(255),
            phone_number VARCHAR(20),
            website VARCHAR(255),
            social_links JSON,
            email_notifications TINYINT(1) DEFAULT 1,
            push_notifications TINYINT(1) DEFAULT 1,
            notify_student_progress TINYINT(1) DEFAULT 1,
            notify_course_updates TINYINT(1) DEFAULT 1,
            notify_system_announcements TINYINT(1) DEFAULT 1,
            notify_weekly_reports TINYINT(1) DEFAULT 0,
            profile_visibility ENUM('public', 'students', 'private') DEFAULT 'public',
            contact_visibility ENUM('public', 'students', 'private') DEFAULT 'students',
            activity_tracking TINYINT(1) DEFAULT 1,
            data_sharing TINYINT(1) DEFAULT 0,
            language VARCHAR(10) DEFAULT 'en',
            timezone VARCHAR(50) DEFAULT 'Asia/Manila',
            date_format VARCHAR(20) DEFAULT 'Y-m-d',
            time_format ENUM('12', '24') DEFAULT '24',
            items_per_page INT DEFAULT 10,
            default_view ENUM('grid', 'list') DEFAULT 'grid',
            auto_save TINYINT(1) DEFAULT 1,
            dark_mode TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_teacher (teacher_id),
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log("Error creating teacher_preferences table: " . $e->getMessage());
    }
}

function getDefaultSettings() {
    return [
        'profile' => [
            'firstName' => '',
            'lastName' => '',
            'displayName' => '',
            'profilePicture' => '',
            'bio' => '',
            'contactEmail' => '',
            'phoneNumber' => '',
            'website' => '',
            'socialLinks' => []
        ],
        'notifications' => [
            'emailNotifications' => true,
            'pushNotifications' => true,
            'studentProgress' => true,
            'courseUpdates' => true,
            'systemAnnouncements' => true,
            'weeklyReports' => false
        ],
        'privacy' => [
            'profileVisibility' => 'public',
            'contactVisibility' => 'students',
            'activityTracking' => true,
            'dataSharing' => false
        ],
        'preferences' => [
            'language' => 'en',
            'timezone' => 'Asia/Manila',
            'dateFormat' => 'Y-m-d',
            'timeFormat' => '24',
            'itemsPerPage' => 10,
            'defaultView' => 'grid',
            'autoSave' => true,
            'darkMode' => false
        ]
    ];
}
?>