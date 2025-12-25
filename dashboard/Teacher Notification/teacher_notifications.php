<?php
/**
 * Centralized Teacher Notification System
 * Provides unified notifications across all teacher dashboard pages
 */

class TeacherNotificationSystem {
    private $pdo;
    private $teacher_id;
    private $user_role;

    public function __construct($pdo, $teacher_id, $user_role = 'teacher') {
        $this->pdo = $pdo;
        
        // Set timezone to Philippines
        date_default_timezone_set('Asia/Manila');
        $this->teacher_id = $teacher_id;
        $this->user_role = $user_role;
    }

    /**
     * Get all notifications for the current teacher
     */
    public function getNotifications($limit = 20) {
        $notifications = [];
        
        try {
            // Get teacher's notification preferences
            $preferences = $this->getTeacherNotificationPreferences();
            
            // 1. Generate and store dynamic notifications first
            $this->generateAndStoreDynamicNotifications($preferences);
            
            // 2. Get stored notifications from database
            $stored_notifications = $this->getStoredNotifications($preferences, $limit);
            $notifications = array_merge($notifications, $stored_notifications);
            
            // 3. Always add sample notifications (they will be shown alongside real notifications)
            $sample_notifications = $this->getSampleNotifications();
            $notifications = array_merge($notifications, $sample_notifications);


        } catch (PDOException $e) {
            error_log("Teacher notification system error: " . $e->getMessage());
            // Add a fallback notification
            $notifications[] = [
                'type' => 'system',
                'category' => 'system',
                'title' => 'Notification System',
                'message' => 'Teacher notification system is active',
                'timestamp' => date('Y-m-d H:i:s'),
                'priority' => 'info',
                'action_url' => '#',
                'icon' => 'system',
                'related_id' => 'system',
                'is_read' => false,
                'id' => 'system'
            ];
        }

        // Sort by priority and timestamp, then limit
        usort($notifications, function($a, $b) {
            $priority_order = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4, 'info' => 5];
            $a_priority = $priority_order[$a['priority']] ?? 5;
            $b_priority = $priority_order[$b['priority']] ?? 5;
            
            if ($a_priority === $b_priority) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            }
            return $a_priority - $b_priority;
        });

        return array_slice($notifications, 0, $limit);
    }

    /**
     * Generate and store dynamic notifications in the database
     */
    private function generateAndStoreDynamicNotifications($preferences) {
        try {
            // 1. Student Progress Notifications (quiz completion, course completion)
            $this->storeStudentProgressNotifications($preferences);
            
            // 2. Engagement Alerts (inactive students, low participation)
            $this->storeEngagementAlerts($preferences);
            
            // 3. Course & System Updates (new enrollments, admin messages)
            $this->storeCourseSystemUpdates($preferences);
            
            // 4. Admin → Teacher notifications
            $this->storeAdminNotifications($preferences);
            
            error_log("Dynamic notifications generation completed for teacher ID: " . $this->teacher_id);
            
        } catch (PDOException $e) {
            error_log("Error generating dynamic notifications: " . $e->getMessage());
        }
    }

    /**
     * Get teacher notification preferences
     */
    private function getTeacherNotificationPreferences() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    preference_category,
                    preference_key,
                    is_enabled,
                    notification_method,
                    priority_level,
                    frequency
                FROM teacher_notification_preferences 
                WHERE teacher_id = ?
            ");
            $stmt->execute([$this->teacher_id]);
            $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group preferences by category and key
            $grouped = [];
            foreach ($preferences as $pref) {
                $grouped[$pref['preference_category']][$pref['preference_key']] = $pref;
            }
            
            return $grouped;
        } catch (PDOException $e) {
            error_log("Error getting notification preferences: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get stored notifications from database
     */
    private function getStoredNotifications($preferences, $limit) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    notification_type,
                    category,
                    title,
                    message,
                    action_url,
                    priority,
                    is_read,
                    read_at,
                    related_id,
                    created_at
                FROM teacher_notifications 
                WHERE teacher_id = ?
                    AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY 
                    CASE priority 
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$this->teacher_id, $limit]);
            $stored = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter based on preferences
            $filtered = [];
            foreach ($stored as $notification) {
                $category = $notification['category'];
                $type = $notification['notification_type'];
                
                // Check if this notification type is enabled in preferences
                if (isset($preferences[$category][$type]) && $preferences[$category][$type]['is_enabled']) {
                    $filtered[] = [
                        'type' => $notification['notification_type'],
                        'category' => $notification['category'],
                        'title' => $notification['title'],
                        'message' => $notification['message'],
                        'timestamp' => $notification['created_at'],
                        'priority' => $notification['priority'],
                        'action_url' => $notification['action_url'],
                        'icon' => $this->getNotificationIcon($notification['notification_type']),
                        'related_id' => $notification['related_id'],
                        'is_read' => (bool)$notification['is_read'],
                        'id' => $notification['id'],
                        'read_at' => $notification['read_at']
                    ];
                }
            }
            
            return $filtered;
        } catch (PDOException $e) {
            error_log("Error getting stored notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notification_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE teacher_notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([$notification_id, $this->teacher_id]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store student progress notifications in database
     */
    private function storeStudentProgressNotifications($preferences) {
        // Get recent quiz completions
        if (isset($preferences['student_progress']['quiz_completions']) && $preferences['student_progress']['quiz_completions']['is_enabled']) {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT qa.quiz_id, qa.student_id, q.title as quiz_title, q.section_id, s.title as section_title, c.title as course_title, u.username, qa.completed_at
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN sections s ON q.section_id = s.id
                JOIN courses c ON s.course_id = c.id
                JOIN users u ON qa.student_id = u.id
                WHERE c.teacher_id = ? 
                AND qa.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM teacher_notifications tn 
                    WHERE tn.teacher_id = ? 
                    AND tn.notification_type = 'quiz_completion'
                    AND tn.related_id = CONCAT('quiz_', qa.quiz_id, '_', qa.student_id)
                )
                ORDER BY qa.completed_at DESC
                LIMIT 10
            ");
            $stmt->execute([$this->teacher_id, $this->teacher_id]);
            $quiz_completions = $stmt->fetchAll();
            
            foreach ($quiz_completions as $completion) {
                $this->createNotification(
                    'quiz_completion',
                    'student_progress',
                    'Quiz Completed: ' . $completion['quiz_title'],
                    'Student "' . $completion['username'] . '" completed quiz "' . $completion['quiz_title'] . '" in "' . $completion['course_title'] . '"',
                    'view_course.php?id=' . $completion['section_id'],
                    'medium',
                    'quiz_' . $completion['quiz_id'] . '_' . $completion['student_id']
                );
            }
        }
    }

    /**
     * Store engagement alerts in database
     */
    private function storeEngagementAlerts($preferences) {
        // This would store engagement-related notifications
        // Implementation depends on your specific engagement tracking logic
    }

    /**
     * Store course system updates in database
     */
    private function storeCourseSystemUpdates($preferences) {
        // Get recent enrollments
        if (isset($preferences['course_management']['new_enrollments']) && $preferences['course_management']['new_enrollments']['is_enabled']) {
            $stmt = $this->pdo->prepare("
                SELECT e.id, e.course_id, c.title as course_title, u.username, e.enrolled_at
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                JOIN users u ON e.student_id = u.id
                WHERE c.teacher_id = ? 
                AND e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM teacher_notifications tn 
                    WHERE tn.teacher_id = ? 
                    AND tn.notification_type = 'new_enrollment'
                    AND tn.related_id = CONCAT('enrollment_', e.id)
                )
                ORDER BY e.enrolled_at DESC
                LIMIT 10
            ");
            $stmt->execute([$this->teacher_id, $this->teacher_id]);
            $enrollments = $stmt->fetchAll();
            
            foreach ($enrollments as $enrollment) {
                $this->createNotification(
                    'new_enrollment',
                    'course_management',
                    'New Enrollment: ' . $enrollment['username'],
                    'Student "' . $enrollment['username'] . '" enrolled in "' . $enrollment['course_title'] . '"',
                    'view_course.php?id=' . $enrollment['course_id'],
                    'medium',
                    'enrollment_' . $enrollment['id']
                );
            }
        }
    }

    /**
     * Store admin notifications in database
     */
    private function storeAdminNotifications($preferences) {
        // This would store admin-related notifications
        // Implementation depends on your specific admin notification logic
    }

    /**
     * Get sample notifications that are always visible
     */
    private function getSampleNotifications() {
        return [
            [
                'type' => 'system',
                'category' => 'system',
                'title' => 'Welcome to Teacher Dashboard',
                'message' => 'Your teacher dashboard is ready. Start creating courses and managing your students.',
                'timestamp' => date('Y-m-d H:i:s'),
                'priority' => 'info',
                'action_url' => '#',
                'icon' => '🎓',
                'related_id' => 'welcome',
                'is_read' => false,
                'id' => 'welcome',
                'is_sample' => true
            ],
            [
                'type' => 'course_management',
                'category' => 'course_management',
                'title' => 'Create Your First Course',
                'message' => 'Ready to start teaching? Create your first course and begin your teaching journey.',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'priority' => 'medium',
                'action_url' => 'teacher_create_module.php',
                'icon' => '📚',
                'related_id' => 'create_course',
                'is_read' => false,
                'id' => 'create_course',
                'is_sample' => true
            ],
            [
                'type' => 'student_progress',
                'category' => 'student_progress',
                'title' => 'Student Progress Tracking',
                'message' => 'Monitor your students\' progress and performance through detailed analytics.',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'priority' => 'low',
                'action_url' => 'teacher_course_editor.php',
                'icon' => '📊',
                'related_id' => 'progress_tracking',
                'is_read' => false,
                'id' => 'progress_tracking',
                'is_sample' => true
            ]
        ];
    }

    /**
     * Mark all notifications as read (excluding sample notifications)
     */
    public function markAllNotificationsAsRead() {
        try {
            // Mark stored notifications as read (excluding sample notifications)
            $stmt = $this->pdo->prepare("
                UPDATE teacher_notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE teacher_id = ? AND is_read = 0
                AND related_id NOT IN ('welcome', 'create_course', 'progress_tracking')
            ");
            $stmt->execute([$this->teacher_id]);
            $stored_count = $stmt->rowCount();
            
            return $stored_count;
        } catch (PDOException $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create a new notification
     */
    public function createNotification($type, $category, $title, $message, $action_url = '#', $priority = 'medium', $related_id = null, $expires_at = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO teacher_notifications 
                (teacher_id, notification_type, category, title, message, action_url, priority, related_id, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->teacher_id,
                $type,
                $category,
                $title,
                $message,
                $action_url,
                $priority,
                $related_id,
                $expires_at
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Get student progress notifications
     */
    private function getStudentProgressNotifications($preferences = []) {
        $notifications = [];
        
        try {
            // Quiz completions in last 24 hours
            if (isset($preferences['student_progress']['quiz_completions']) && $preferences['student_progress']['quiz_completions']['is_enabled']) {
                $stmt = $this->pdo->prepare("
                    SELECT 'quiz_completions' as type, 'student_progress' as category,
                           CONCAT('Quiz Completed: ', u.username) as title,
                           CONCAT('Student \"', u.username, '\" completed a quiz in \"', c.title, '\"') as message,
                           qa.created_at as timestamp, 'success' as priority,
                           CONCAT('Student Management/quiz_performance.php?student_id=', u.id) as action_url,
                           'quiz' as icon, qa.id as related_id
                    FROM quiz_attempts qa
                    JOIN users u ON qa.student_id = u.id
                    JOIN sections s ON qa.section_id = s.id
                    JOIN courses c ON s.course_id = c.id
                    WHERE c.teacher_id = ? 
                      AND qa.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY qa.created_at DESC 
                    LIMIT 5
                ");
                $stmt->execute([$this->teacher_id]);
                $quiz_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $quiz_notifications);
            }

            // Course completions in last 7 days
            if (isset($preferences['student_progress']['course_completions']) && $preferences['student_progress']['course_completions']['is_enabled']) {
                $stmt = $this->pdo->prepare("
                    SELECT 'course_completions' as type, 'student_progress' as category,
                           CONCAT('Course Completed: ', u.username) as title,
                           CONCAT('Student \"', u.username, '\" completed course \"', c.title, '\"') as message,
                           cp.updated_at as timestamp, 'success' as priority,
                           CONCAT('Student Management/completion_reports.php?course_id=', c.id) as action_url,
                           'graduation' as icon, cp.id as related_id
                    FROM course_progress cp
                    JOIN users u ON cp.student_id = u.id
                    JOIN courses c ON cp.course_id = c.id
                    WHERE c.teacher_id = ? 
                      AND cp.completion_status = 'completed'
                      AND cp.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY cp.updated_at DESC 
                    LIMIT 3
                ");
                $stmt->execute([$this->teacher_id]);
                $completion_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $completion_notifications);
            }

            // Low quiz performance alerts
            if (isset($preferences['student_progress']['low_performance_alerts']) && $preferences['student_progress']['low_performance_alerts']['is_enabled']) {
                $stmt = $this->pdo->prepare("
                    SELECT 'low_performance_alerts' as type, 'student_progress' as category,
                           CONCAT('Low Performance: ', u.username) as title,
                           CONCAT('Student \"', u.username, '\" scored below 60% in \"', c.title, '\"') as message,
                           qa.created_at as timestamp, 'warning' as priority,
                           CONCAT('Student Management/quiz_performance.php?student_id=', u.id) as action_url,
                           'warning' as icon, qa.id as related_id
                    FROM quiz_attempts qa
                    JOIN users u ON qa.student_id = u.id
                    JOIN sections s ON qa.section_id = s.id
                    JOIN courses c ON s.course_id = c.id
                    WHERE c.teacher_id = ? 
                      AND qa.score < 60
                      AND qa.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY qa.created_at DESC 
                    LIMIT 3
                ");
                $stmt->execute([$this->teacher_id]);
                $performance_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $performance_notifications);
            }

        } catch (PDOException $e) {
            error_log("Student progress notifications error: " . $e->getMessage());
        }

        return $notifications;
    }

    /**
     * Get engagement alerts
     */
    private function getEngagementAlerts($preferences = []) {
        $notifications = [];
        
        try {
            // Inactive students (enrolled but no activity in 7 days)
            if (isset($preferences['student_engagement']['inactive_students']) && $preferences['student_engagement']['inactive_students']['is_enabled']) {
                $stmt = $this->pdo->prepare("
                    SELECT 'inactive_students' as type, 'student_engagement' as category,
                           CONCAT('Inactive Student: ', u.username) as title,
                           CONCAT('Student \"', u.username, '\" has been inactive for 7+ days in \"', c.title, '\"') as message,
                           e.enrolled_at as timestamp, 'warning' as priority,
                           CONCAT('Student Management/engagement_monitoring.php?student_id=', u.id) as action_url,
                           'inactive' as icon, e.id as related_id
                    FROM enrollments e
                    JOIN users u ON e.student_id = u.id
                    JOIN courses c ON e.course_id = c.id
                    LEFT JOIN progress p ON (p.student_id = e.student_id AND p.section_id IN (
                        SELECT id FROM sections WHERE course_id = c.id
                    ))
                    WHERE c.teacher_id = ?
                      AND e.enrolled_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
                      AND (p.updated_at IS NULL OR p.updated_at <= DATE_SUB(NOW(), INTERVAL 7 DAY))
                    GROUP BY e.id, u.id, u.username, c.title
                    ORDER BY e.enrolled_at DESC
                    LIMIT 5
                ");
                $stmt->execute([$this->teacher_id]);
                $inactive_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $inactive_notifications);
            }

            // Students struggling with multiple quizzes
            if (isset($preferences['student_progress']['struggling_students']) && $preferences['student_progress']['struggling_students']['is_enabled']) {
                $stmt = $this->pdo->prepare("
                    SELECT 'struggling_students' as type, 'student_progress' as category,
                           CONCAT('Student Struggling: ', u.username) as title,
                           CONCAT('Student \"', u.username, '\" failed multiple quizzes in \"', c.title, '\"') as message,
                           MAX(qa.created_at) as timestamp, 'error' as priority,
                           CONCAT('Student Management/quiz_performance.php?student_id=', u.id) as action_url,
                           'struggle' as icon, u.id as related_id
                    FROM quiz_attempts qa
                    JOIN users u ON qa.student_id = u.id
                    JOIN sections s ON qa.section_id = s.id
                    JOIN courses c ON s.course_id = c.id
                    WHERE c.teacher_id = ? 
                      AND qa.score < 50
                      AND qa.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY u.id, u.username, c.id, c.title
                    HAVING COUNT(qa.id) >= 2
                    ORDER BY timestamp DESC
                    LIMIT 3
                ");
                $stmt->execute([$this->teacher_id]);
                $struggling_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $struggling_notifications);
            }

        } catch (PDOException $e) {
            error_log("Engagement alerts error: " . $e->getMessage());
        }

        return $notifications;
    }

    /**
     * Get course and system update notifications
     */
    private function getCourseSystemUpdates($preferences = []) {
        $notifications = [];
        
        try {
            // New enrollments in last 24 hours
            if (isset($preferences['student_progress']['new_enrollments']) && $preferences['student_progress']['new_enrollments']['is_enabled']) {
                $stmt = $this->pdo->prepare("
                    SELECT 'new_enrollments' as type, 'student_progress' as category,
                           CONCAT('New Enrollment: ', u.username) as title,
                           CONCAT('Student \"', u.username, '\" enrolled in \"', c.title, '\"') as message,
                           e.enrolled_at as timestamp, 'success' as priority,
                           CONCAT('Student Management/student_profiles.php?student_id=', u.id) as action_url,
                           'enrollment' as icon, e.id as related_id
                    FROM enrollments e
                    JOIN users u ON e.student_id = u.id
                    JOIN courses c ON e.course_id = c.id
                    WHERE c.teacher_id = ? 
                      AND e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY e.enrolled_at DESC 
                    LIMIT 5
                ");
                $stmt->execute([$this->teacher_id]);
                $enrollment_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $enrollment_notifications);
            }

            // Course milestone achievements
            if (isset($preferences['course_management']['course_milestones']) && $preferences['course_management']['course_milestones']['is_enabled']) {
                $stmt = $this->pdo->prepare("
                    SELECT 'course_milestones' as type, 'course_management' as category,
                           CONCAT('Milestone: ', c.title) as title,
                           CONCAT('Course \"', c.title, '\" reached ', COUNT(e.id), ' enrollments!') as message,
                           MAX(e.enrolled_at) as timestamp, 'info' as priority,
                           CONCAT('courses_available.php?course_id=', c.id) as action_url,
                           'milestone' as icon, c.id as related_id
                    FROM courses c
                    JOIN enrollments e ON c.id = e.course_id
                    WHERE c.teacher_id = ?
                    GROUP BY c.id, c.title
                    HAVING COUNT(e.id) IN (10, 25, 50, 100)
                      AND MAX(e.enrolled_at) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY timestamp DESC
                    LIMIT 2
                ");
                $stmt->execute([$this->teacher_id]);
                $milestone_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $milestone_notifications);
            }

        } catch (PDOException $e) {
            error_log("Course system updates error: " . $e->getMessage());
        }

        return $notifications;
    }

    /**
     * Get admin notifications directed to teachers
     */
    private function getAdminNotifications($preferences = []) {
        $notifications = [];
        
        try {
            // 1. Course Category Updates
            $stmt = $this->pdo->prepare("
                SELECT 'course_category_update' as type, 'course_management' as category,
                       CONCAT('Course Category Updated: ', name) as title,
                       CONCAT('Category \"', name, '\" has been updated by admin') as message,
                       updated_at as timestamp, 'info' as priority,
                       'course_management_admin.php' as action_url,
                       'category' as icon, id as related_id
                FROM course_category
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  AND updated_at != created_at
                ORDER BY updated_at DESC 
                LIMIT 3
            ");
            $stmt->execute();
            $category_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = array_merge($notifications, $category_notifications);

            // 2. Announcement Banner Updates
            $stmt = $this->pdo->prepare("
                SELECT 'announcement_update' as type, 'course_management' as category,
                       'New Announcement Banner' as title,
                       CONCAT('A new announcement banner has been published by admin') as message,
                       updated_at as timestamp, 'info' as priority,
                       'contentmanagement/announcement_banner.php' as action_url,
                       'announcement' as icon, id as related_id
                FROM announcement_banner
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  AND is_published = 1
                ORDER BY updated_at DESC 
                LIMIT 2
            ");
            $stmt->execute();
            $announcement_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = array_merge($notifications, $announcement_notifications);

            // 3. Terms & Conditions Updates
            $stmt = $this->pdo->prepare("
                SELECT 'terms_update' as type, 'course_management' as category,
                       'Terms & Conditions Updated' as title,
                       'Terms and conditions have been updated by admin') as message,
                       updated_at as timestamp, 'warning' as priority,
                       'contentmanagement/terms_conditions.php' as action_url,
                       'terms' as icon, id as related_id
                FROM terms_conditions
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  AND updated_at != created_at
                ORDER BY updated_at DESC 
                LIMIT 2
            ");
            $stmt->execute();
            $terms_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = array_merge($notifications, $terms_notifications);

            // 4. Admin Logs (System Actions)
            $stmt = $this->pdo->prepare("
                SELECT 'admin_action' as type, 'course_management' as category,
                       CONCAT('Admin Action: ', action) as title,
                       CONCAT('Admin performed action: ', action, ' - ', COALESCE(action_detail, 'No details')) as message,
                       created_at as timestamp, 'info' as priority,
                       'admin_logs.php' as action_url,
                       'admin_log' as icon, id as related_id
                FROM admin_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $admin_log_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = array_merge($notifications, $admin_log_notifications);

            // 5. Course Status Changes (existing functionality)
                $stmt = $this->pdo->prepare("
                    SELECT 'course_status_changes' as type, 'course_management' as category,
                           CONCAT('Course Status Changed: ', title) as title,
                           CONCAT('Your course \"', title, '\" status was updated by admin') as message,
                           updated_at as timestamp, 'warning' as priority,
                           CONCAT('courses_available.php?course_id=', id) as action_url,
                           'course_change' as icon, id as related_id
                    FROM courses
                    WHERE teacher_id = ?
                      AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      AND updated_at != created_at
                    ORDER BY updated_at DESC 
                    LIMIT 3
                ");
                $stmt->execute([$this->teacher_id]);
                $status_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $status_notifications);

        } catch (PDOException $e) {
            error_log("Admin notifications error: " . $e->getMessage());
        }

        return $notifications;
    }

    /**
     * Get notification count (always show 0 to hide count badge)
     */
    public function getNotificationCount() {
        // Always return 0 to hide the notification count badge
        return 0;
    }

    /**
     * Get notifications by category
     */
    public function getNotificationsByCategory($category) {
        $all_notifications = $this->getNotifications();
        return array_filter($all_notifications, function($notification) use ($category) {
            return isset($notification['category']) && $notification['category'] === $category;
        });
    }

    /**
     * Format timestamp for display
     */
    public function formatTimestamp($timestamp) {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' min ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hr ago';
        } else {
            return date('M j, g:i A', $time);
        }
    }

    /**
     * Get icon for notification type
     */
    public function getNotificationIcon($type) {
        $icons = [
            'quiz_completions' => '✅',
            'course_completions' => '🎓',
            'low_performance_alerts' => '⚠️',
            'struggling_students' => '📉',
            'new_enrollments' => '👋',
            'inactive_students' => '😴',
            'high_performing_students' => '⭐',
            'course_milestones' => '🏆',
            'course_status_changes' => '🔄',
            'security_alerts' => '🔒',
            'daily_activity_summaries' => '📊',
            'weekly_engagement_reports' => '📈',
            'system' => '⚙️',
            'quiz' => '❓',
            'graduation' => '🎓',
            'warning' => '⚠️',
            'inactive' => '💤',
            'struggle' => '😓',
            'enrollment' => '📝',
            'announcement' => '📢',
            'course_change' => '🔄',
            'course_category_update' => '📂',
            'announcement_update' => '📢',
            'terms_update' => '📋',
            'admin_action' => '👨‍💼',
            'admin_log' => '📝',
            'category' => '📂',
            'terms' => '📋',
            'default' => '🔔'
        ];
        
        return $icons[$type] ?? $icons['default'];
    }

    /**
     * Get priority color class
     */
    public function getPriorityColor($priority) {
        $colors = [
            'error' => 'text-red-600',
            'warning' => 'text-orange-600',
            'success' => 'text-green-600',
            'info' => 'text-blue-600',
            'default' => 'text-gray-600'
        ];
        
        return $colors[$priority] ?? $colors['default'];
    }

    /**
     * Get priority background color
     */
    public function getPriorityBg($priority) {
        $colors = [
            'error' => 'bg-red-50 border-red-200',
            'warning' => 'bg-orange-50 border-orange-200',
            'success' => 'bg-green-50 border-green-200',
            'info' => 'bg-blue-50 border-blue-200',
            'default' => 'bg-gray-50 border-gray-200'
        ];
        
        return $colors[$priority] ?? $colors['default'];
    }

    /**
     * Get category color
     */
    public function getCategoryColor($category) {
        $colors = [
            'student_progress' => 'bg-green-100 text-green-800',
            'student_engagement' => 'bg-orange-100 text-orange-800',
            'course_management' => 'bg-blue-100 text-blue-800',
            'system_administrative' => 'bg-red-100 text-red-800',
            'reporting_analytics' => 'bg-indigo-100 text-indigo-800',
            'system' => 'bg-gray-100 text-gray-800'
        ];
        
        return $colors[$category] ?? $colors['system'];
    }

    /**
     * Render notification bell HTML
     */
    public function renderNotificationBell($page_title = 'Teacher Notifications') {
        $notifications = $this->getNotifications(15);
        $count = count($notifications);
        
        // Group notifications by category
        $grouped = [
            'student_progress' => [],
            'student_engagement' => [],
            'course_management' => [],
            'system_administrative' => [],
            'reporting_analytics' => []
        ];
        
        foreach ($notifications as $notification) {
            $category = $notification['category'] ?? 'system';
            if (isset($grouped[$category])) {
                $grouped[$category][] = $notification;
            }
        }
        
        $html = '
        <!-- Teacher Notifications -->
        <div class="relative teacher-notification-container">
            <div class="teacher-notification-bell cursor-pointer transition-transform hover:scale-110" onclick="toggleTeacherNotifications()" title="Click to view notifications">
                <span style="font-size: 1.25rem;">🔔</span>';
                
        // Show count badge only if there are notifications
        if ($count > 0) {
            $html .= '<span class="teacher-notification-count animate-pulse" id="teacherNotificationCount" style="display: flex;">' . $count . '</span>';
        } else {
            $html .= '<span class="teacher-notification-count" id="teacherNotificationCount" style="display: none;">0</span>';
        }
        
        $html .= '
            </div>
            <div class="teacher-notification-dropdown" id="teacherNotificationDropdown">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="font-semibold text-gray-900">' . htmlspecialchars($page_title) . '</h3>
                    <p class="text-sm text-gray-600 mt-1">' . $count . ' notification' . ($count != 1 ? 's' : '') . '</p>
                </div>
                <div class="max-h-80 overflow-y-auto">';
        
        if (empty($notifications)) {
            $html .= '
                    <div class="p-6 text-center text-gray-500">
                        <div class="text-2xl mb-2">🔕</div>
                        <p class="text-sm">No new notifications</p>
                        <p class="text-xs text-gray-400 mt-1">All caught up!</p>
                    </div>';
        } else {
            // Display notifications grouped by category (keeping the grouping but simplifying the style)
            $category_titles = [
                'student_progress' => 'Student Progress & Performance',
                'student_engagement' => 'Student Engagement & Activity', 
                'course_management' => 'Course & Content Management',
                'system_administrative' => 'Security & Administrative',
                'reporting_analytics' => 'Reporting & Analytics'
            ];
            
            foreach ($grouped as $category => $category_notifications) {
                if (!empty($category_notifications)) {
                    $html .= '
                        <div class="px-3 py-2 bg-gray-100 border-b">
                            <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wider">' . $category_titles[$category] . '</h4>
                        </div>';
                    
                    foreach ($category_notifications as $notification) {
                        $icon = $this->getNotificationIcon($notification['type']);
                        $priorityColor = $this->getPriorityColor($notification['priority']);
                        $priorityBg = $this->getPriorityBg($notification['priority']);
                        $categoryColor = $this->getCategoryColor($notification['category']);
                        $timestamp = $this->formatTimestamp($notification['timestamp']);
                        
                        $read_status = isset($notification['is_read']) && $notification['is_read'] ? 'read' : 'unread';
                        $read_class = $read_status === 'read' ? 'opacity-75' : '';
                        $notification_id = isset($notification['id']) ? $notification['id'] : 'system';
                        
                        $html .= '
                            <div class="teacher-notification-item p-3 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer ' . $priorityBg . ' ' . $read_class . '" 
                                 onclick="handleTeacherNotificationClick(\'' . htmlspecialchars($notification['action_url']) . '\', \'' . $notification_id . '\')">
                                <div class="flex items-start gap-3">
                                    <div class="text-lg flex-shrink-0 mt-0.5">' . $icon . '</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between">
                                            <p class="text-sm font-medium text-gray-900 truncate">' . htmlspecialchars($notification['title']) . '</p>
                                            <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                                <span class="text-xs text-gray-500">' . $timestamp . '</span>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-600 mt-1 line-clamp-2">' . htmlspecialchars($notification['message']) . '</p>
                                        <div class="flex items-center justify-between mt-2">
                                            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 capitalize">' . str_replace('_', ' ', $notification['type']) . '</span>
                                            <span class="text-xs ' . $priorityColor . ' font-medium capitalize">' . $notification['priority'] . '</span>
                                        </div>
                                    </div>
                                </div>
                            </div>';
                    }
                }
            }
        }
        
        $html .= '
                </div>
                <div class="p-3 border-t border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between text-xs text-gray-600">
                        <span class="notification-last-updated">Last updated: ' . date('g:i A', strtotime('now')) . '</span>
                        <div class="flex items-center gap-3">
                            <button onclick="markAllNotificationsAsRead()" class="text-green-600 hover:text-green-800 font-medium">
                                ✓ Mark All Read
                            </button>
                            <button onclick="refreshTeacherNotifications()" class="text-blue-600 hover:text-blue-800 font-medium">
                                🔄 Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }

    /**
     * Render notification styles and scripts
     */
    public function renderNotificationAssets() {
        return <<<'HTML'
        <style>
        /* Teacher Notification Bell Styles */
        .teacher-notification-container {
            position: relative;
            display: inline-block;
        }
        
        .teacher-notification-container .teacher-notification-bell {
            position: relative !important;
            font-size: 1.25rem !important;
            transition: transform 0.2s ease !important;
            z-index: 1 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 8px !important;
            border-radius: 50% !important;
            background: rgba(0,0,0,0.05) !important;
            cursor: pointer !important;
        }
        .teacher-notification-container .teacher-notification-bell:hover {
            transform: scale(1.1) !important;
            background: rgba(0,0,0,0.1) !important;
        }
        
        .teacher-notification-container .teacher-notification-count {
            position: absolute !important;
            top: -6px !important;
            right: -6px !important;
            background: #ef4444 !important;
            color: white !important;
            border-radius: 50% !important;
            min-width: 18px !important;
            height: 18px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 11px !important;
            font-weight: bold !important;
            border: 2px solid white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            line-height: 1 !important;
            padding: 0 2px !important;
            visibility: visible !important;
        }
        
        .teacher-notification-container .teacher-notification-dropdown {
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            background: white !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
            width: 420px !important;
            max-height: 550px !important;
            overflow: hidden !important;
            z-index: 10000 !important;
            display: none !important;
            transform: translateY(-10px) !important;
            opacity: 0 !important;
            transition: all 0.2s ease !important;
            margin-top: 8px !important;
        }
        
        .teacher-notification-container .teacher-notification-dropdown.show {
            display: block !important;
            transform: translateY(0) !important;
            opacity: 1 !important;
        }
        
        .teacher-notification-container .teacher-notification-item {
            transition: all 0.2s ease !important;
        }
        
        .teacher-notification-container .teacher-notification-item:hover {
            transform: translateX(2px) !important;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        @keyframes teacherNotificationPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .teacher-notification-container .animate-pulse {
            animation: teacherNotificationPulse 2s infinite !important;
        }
        </style>

        <script>
        // Teacher Notification System Functions
        function toggleTeacherNotifications() {
            console.log("toggleTeacherNotifications called");
            const dropdown = document.getElementById("teacherNotificationDropdown");
            if (dropdown) {
                const isVisible = dropdown.classList.contains("show");
                console.log("Teacher dropdown found, current visibility:", isVisible);
                if (isVisible) {
                    dropdown.classList.remove("show");
                    console.log("Hiding teacher dropdown");
                } else {
                    dropdown.classList.add("show");
                    console.log("Showing teacher dropdown");
                }
            } else {
                console.error("Teacher notification dropdown not found");
            }
        }

        function handleTeacherNotificationClick(url, notificationId) {
            // Mark notification as read if it has an ID and is not a sample notification
            if (notificationId && notificationId !== "system" && !notificationId.includes("welcome") && !notificationId.includes("create_course") && !notificationId.includes("progress_tracking")) {
                markNotificationAsRead(notificationId);
            }
            
            if (url && url !== "#") {
                window.location.href = url;
            }
            // Close dropdown after click
            const dropdown = document.getElementById("teacherNotificationDropdown");
            if (dropdown) {
                dropdown.classList.remove("show");
            }
        }

        function markNotificationAsRead(notificationId) {
            fetch("teacher.php?ajax=mark_notification_read&id=" + notificationId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the notification count
                        refreshTeacherNotifications();
                    }
                })
                .catch(error => console.log("Error marking notification as read:", error));
        }

        function markAllNotificationsAsRead() {
            fetch("teacher.php?ajax=mark_all_notifications_read")
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide the notification count immediately
                        const countElement = document.getElementById("teacherNotificationCount");
                        if (countElement) {
                            countElement.textContent = "0";
                            countElement.style.display = "none";
                            countElement.classList.remove("animate-pulse");
                        }
                        
                        // Update the dropdown content to show "no notifications"
                        const dropdown = document.getElementById("teacherNotificationDropdown");
                        if (dropdown) {
                            const contentArea = dropdown.querySelector(".max-h-80");
                            if (contentArea) {
                                contentArea.innerHTML = `
                                    <div class="p-6 text-center text-gray-500">
                                        <div class="text-2xl mb-2">🔕</div>
                                        <p class="text-sm">No new notifications</p>
                                        <p class="text-xs text-gray-400 mt-1">All caught up!</p>
                                    </div>`;
                            }
                            
                            // Update header count
                            const headerCount = dropdown.querySelector("p");
                            if (headerCount) {
                                headerCount.textContent = "0 notifications";
                            }
                        }
                        
                        // Show success message
                        showNotificationMessage("All notifications marked as read", "success");
                    }
                })
                .catch(error => {
                    console.log("Error marking all notifications as read:", error);
                    showNotificationMessage("Error marking notifications as read", "error");
                });
        }

        function showNotificationMessage(message, type) {
            // Create a temporary message element
            const messageEl = document.createElement("div");
            messageEl.className = "fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 " +
                (type === "success" ? "bg-green-500" : "bg-red-500");
            messageEl.textContent = message;
            document.body.appendChild(messageEl);
            
            // Remove after 3 seconds
            setTimeout(() => {
                messageEl.remove();
            }, 3000);
        }


        function refreshTeacherNotifications() {
            // Refresh notification count and dropdown content
            const countElement = document.getElementById("teacherNotificationCount");
            const dropdown = document.getElementById("teacherNotificationDropdown");
            
            if (countElement) {
                fetch(window.location.pathname + "?ajax=teacher_notification_count")
                    .then(response => response.json())
                    .then(data => {
                        if (data.count !== undefined) {
                            countElement.textContent = data.count;
                            if (data.count > 0) {
                                countElement.style.display = "flex";
                                countElement.classList.add("animate-pulse");
                            } else {
                                countElement.style.display = "none";
                                countElement.classList.remove("animate-pulse");
                            }
                        }
                    })
                    .catch(error => console.log("Error refreshing notification count:", error));
            }
            
            // Refresh notification content
            fetch(window.location.pathname + "?ajax=teacher_notifications")
                .then(response => response.json())
                .then(data => {
                    if (data.notifications) {
                        updateNotificationDropdown(data.notifications, data.count);
                        // Update last updated time with Philippines timezone
                        const lastUpdated = document.querySelector(".notification-last-updated");
                        if (lastUpdated) {
                            const now = new Date();
                            const philippinesTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Manila"}));
                            const timeOptions = {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true,
                                timeZone: 'Asia/Manila'
                            };
                            const formattedTime = philippinesTime.toLocaleTimeString("en-US", timeOptions);
                            lastUpdated.textContent = "Last updated: " + formattedTime;
                        }
                    }
                })
                .catch(error => console.log("Error refreshing notifications:", error));
        }
        
        function updateNotificationDropdown(notifications, count) {
            const dropdown = document.getElementById("teacherNotificationDropdown");
            if (!dropdown) return;
            
            // Update header count
            const headerCount = dropdown.querySelector("p");
            if (headerCount) {
                headerCount.textContent = count + " notification" + (count != 1 ? "s" : "");
            }
            
            // Update notification content
            const contentArea = dropdown.querySelector(".max-h-80");
            if (contentArea) {
                if (notifications.length === 0) {
                    contentArea.innerHTML = `
                        <div class="p-6 text-center text-gray-500">
                            <div class="text-2xl mb-2">🔕</div>
                            <p class="text-sm">No new notifications</p>
                            <p class="text-xs text-gray-400 mt-1">All caught up!</p>
                        </div>`;
                } else {
                    // Group notifications by category
                    const grouped = {
                        "student_progress": [],
                        "student_engagement": [],
                        "course_management": [],
                        "system_administrative": [],
                        "reporting_analytics": []
                    };
                    
                    notifications.forEach(notification => {
                        const category = notification.category || "system";
                        if (grouped[category]) {
                            grouped[category].push(notification);
                        }
                    });
                    
                    const categoryTitles = {
                        "student_progress": "Student Progress & Performance",
                        "student_engagement": "Student Engagement & Activity", 
                        "course_management": "Course & Content Management",
                        "system_administrative": "Security & Administrative",
                        "reporting_analytics": "Reporting & Analytics"
                    };
                    
                    let html = '';
                    Object.keys(grouped).forEach(category => {
                        if (grouped[category].length > 0) {
                            html += `
                                <div class="px-3 py-2 bg-gray-100 border-b">
                                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wider">${categoryTitles[category]}</h4>
                                </div>`;
                            
                            grouped[category].forEach(notification => {
                                const readStatus = notification.is_read ? "read" : "unread";
                                const readClass = readStatus === "read" ? "opacity-75" : "";
                                const notificationId = notification.id || "system";
                                
                                html += `
                                    <div class="teacher-notification-item p-3 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer ${readClass}" 
                                         data-action-url="${notification.action_url}" data-notification-id="${notificationId}">
                                        <div class="flex items-start gap-3">
                                            <div class="text-lg flex-shrink-0 mt-0.5">${notification.icon || "🔔"}</div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-start justify-between">
                                                    <p class="text-sm font-medium text-gray-900 truncate">${notification.title}</p>
                                                    <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                                        <span class="text-xs text-gray-500">${notification.timestamp}</span>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">${notification.message}</p>
                                                <div class="flex items-center justify-between mt-2">
                                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600 capitalize">${notification.type.replace("_", " ")}</span>
                                                    <span class="text-xs font-medium capitalize">${notification.priority}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>`;
                            });
                        }
                    });
                    
                    contentArea.innerHTML = html;
                }
            }
        }

        // Close teacher notifications when clicking outside
        document.addEventListener("click", function(event) {
            const bell = document.querySelector(".teacher-notification-bell");
            const dropdown = document.getElementById("teacherNotificationDropdown");
            
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove("show");
            }
        });

        // Prevent clicks inside teacher dropdown from closing it
        document.addEventListener("DOMContentLoaded", function() {
            const dropdown = document.getElementById("teacherNotificationDropdown");
            if (dropdown) {
                dropdown.addEventListener("click", function(event) {
                    if (event.target.tagName === "BUTTON" || event.target.closest("button")) {
                        event.stopPropagation();
                    }
                });
            }
            
            // Add click event listener for notification items
            document.addEventListener("click", function(event) {
                const notificationItem = event.target.closest(".teacher-notification-item");
                if (notificationItem) {
                    const actionUrl = notificationItem.getAttribute("data-action-url");
                    const notificationId = notificationItem.getAttribute("data-notification-id");
                    if (actionUrl && notificationId) {
                        handleTeacherNotificationClick(actionUrl, notificationId);
                    }
                }
            });
        });

        // Auto-refresh teacher notifications every 30 seconds
        setInterval(function() {
            refreshTeacherNotifications();
        }, 30000); // 30 seconds
        </script>
HTML;
        
        return $html;
    }
}

/**
 * Helper function to initialize teacher notification system
 */
function initializeTeacherNotifications($pdo, $teacher_id, $user_role = 'teacher') {
    return new TeacherNotificationSystem($pdo, $teacher_id, $user_role);
}
?>
