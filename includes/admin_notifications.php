<?php
/**
 * Centralized Admin Notification System
 * Provides unified notifications across all admin pages
 */

class AdminNotificationSystem {
    private $pdo;
    private $user_id;
    private $user_role;

    public function __construct($pdo, $user_id, $user_role = 'admin') {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->user_role = $user_role;
    }

    /**
     * Get all notifications for the current admin user
     */
    public function getNotifications($limit = 20) {
        $notifications = [];
        
        try {
            // Get recent user registrations (last 24 hours)
            $stmt = $this->pdo->prepare("
                SELECT 'user_registration' as type, username as title, 
                       CONCAT('New user \"', username, '\" registered') as message,
                       created_at as timestamp, 'info' as priority, 
                       CONCAT('users.php?highlight=', id) as action_url,
                       'user' as icon, id as related_id
                FROM users 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $user_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = array_merge($notifications, $user_notifications);

            // Get recent payment notifications (last 24 hours)
            $stmt = $this->pdo->prepare("
                SELECT 'payment' as type, 
                       CONCAT('Payment: ₱', amount) as title,
                       CONCAT('Payment of ₱', amount, ' received from user ID ', user_id) as message,
                       payment_date as timestamp, 'success' as priority,
                       CONCAT('admin.php?view=payment&highlight=', id) as action_url,
                       'payment' as icon, id as related_id
                FROM payments 
                WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  AND payment_status = 'completed'
                ORDER BY payment_date DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $payment_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = array_merge($notifications, $payment_notifications);

            // Get security alerts (failed login attempts)
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 'security' as type, 
                           CONCAT('Security Alert: Failed login attempts') as title,
                           CONCAT('IP: ', ip_address, ' - Multiple failed login attempts') as message,
                           last_attempt as timestamp, 'warning' as priority,
                           'security-warnings.php' as action_url,
                           'security' as icon, ip_address as related_id
                    FROM failed_login_attempts 
                    WHERE last_attempt >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY last_attempt DESC 
                    LIMIT 3
                ");
                $stmt->execute();
                $security_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $security_notifications);
            } catch (PDOException $e) {
                // Failed login attempts table doesn't exist or has different structure, skip
                error_log("Security notifications query failed: " . $e->getMessage());
            }

            // Get system errors (if error_logs table exists)
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 'error' as type,
                           CONCAT('System Error: ', category) as title,
                           LEFT(error_message, 100) as message,
                           created_at as timestamp, 'error' as priority,
                           'error-logs.php' as action_url,
                           'error' as icon, id as related_id
                    FROM error_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      AND severity IN ('high', 'critical')
                    ORDER BY created_at DESC 
                    LIMIT 3
                ");
                $stmt->execute();
                $error_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $error_notifications);
            } catch (PDOException $e) {
                // Error logs table doesn't exist, skip
                error_log("Error logs table query failed: " . $e->getMessage());
            }

            // Get audit trail alerts (suspicious admin activity)
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 'audit' as type,
                           CONCAT('Admin Action: ', action) as title,
                           CONCAT('User: ', username, ' - ', action) as message,
                           created_at as timestamp, 'info' as priority,
                           'audit-trails.php' as action_url,
                           'audit' as icon, id as related_id
                    FROM admin_audit_log aal
                    JOIN users u ON aal.admin_id = u.id
                    WHERE aal.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                    ORDER BY aal.created_at DESC 
                    LIMIT 3
                ");
                $stmt->execute();
                $audit_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $notifications = array_merge($notifications, $audit_notifications);
            } catch (PDOException $e) {
                // Admin audit log table doesn't exist, skip
                error_log("Admin audit log table query failed: " . $e->getMessage());
            }

            // Add some sample notifications if none found (for testing)
            if (empty($notifications)) {
                $notifications[] = [
                    'type' => 'system',
                    'title' => 'System Status',
                    'message' => 'All systems operational',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'priority' => 'info',
                    'action_url' => '#',
                    'icon' => 'system',
                    'related_id' => 'system'
                ];
            }

        } catch (PDOException $e) {
            error_log("Notification system error: " . $e->getMessage());
            // Add a fallback notification
            $notifications[] = [
                'type' => 'system',
                'title' => 'Notification System',
                'message' => 'Notification system is active',
                'timestamp' => date('Y-m-d H:i:s'),
                'priority' => 'info',
                'action_url' => '#',
                'icon' => 'system',
                'related_id' => 'system'
            ];
        }

        // Sort by timestamp and limit
        usort($notifications, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($notifications, 0, $limit);
    }

    /**
     * Get notification count
     */
    public function getNotificationCount() {
        return count($this->getNotifications());
    }

    /**
     * Get notifications by type
     */
    public function getNotificationsByType($type) {
        $all_notifications = $this->getNotifications();
        return array_filter($all_notifications, function($notification) use ($type) {
            return $notification['type'] === $type;
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
            'user_registration' => '👤',
            'payment' => '💰',
            'security' => '🔒',
            'error' => '⚠️',
            'audit' => '📋',
            'course' => '📚',
            'system' => '⚙️',
            'user' => '👤',
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
     * Render notification bell HTML
     */
    public function renderNotificationBell($page_title = 'Admin Notifications') {
        $notifications = $this->getNotifications(10);
        $count = count($notifications);
        
        $html = '
        <!-- Notifications -->
        <div class="relative notification-container">
            <div class="notification-bell cursor-pointer transition-transform hover:scale-110" onclick="toggleNotifications()" title="Click to view notifications">
                <span style="font-size: 1.25rem;">🔔</span>';
                
        // Always show the count badge, even if it's 0
        $html .= '<span class="notification-count ' . ($count > 0 ? 'animate-pulse' : '') . '" id="notificationCount">' . $count . '</span>';
        
        $html .= '
            </div>
            <div class="notification-dropdown" id="notificationDropdown">
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
            foreach ($notifications as $notification) {
                $icon = $this->getNotificationIcon($notification['type']);
                $priorityColor = $this->getPriorityColor($notification['priority']);
                $priorityBg = $this->getPriorityBg($notification['priority']);
                $timestamp = $this->formatTimestamp($notification['timestamp']);
                
                $html .= '
                    <div class="notification-item p-3 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer ' . $priorityBg . '" 
                         onclick="handleNotificationClick(\'' . htmlspecialchars($notification['action_url']) . '\')">
                        <div class="flex items-start gap-3">
                            <div class="text-lg flex-shrink-0 mt-0.5">' . $icon . '</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <p class="text-sm font-medium text-gray-900 truncate">' . htmlspecialchars($notification['title']) . '</p>
                                    <span class="text-xs text-gray-500 flex-shrink-0 ml-2">' . $timestamp . '</span>
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
        
        $html .= '
                </div>
                <div class="p-3 border-t border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between text-xs text-gray-600">
                        <span>Last updated: ' . date('g:i A') . '</span>
                        <button onclick="refreshNotifications()" class="text-blue-600 hover:text-blue-800 font-medium">
                            🔄 Refresh
                        </button>
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
        return '
        <style>
        /* Notification Bell Styles - High Specificity to Override Conflicts */
        .notification-container {
            position: relative;
            display: inline-block;
        }
        
        .notification-container .notification-bell {
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
        .notification-container .notification-bell:hover {
            transform: scale(1.1) !important;
            background: rgba(0,0,0,0.1) !important;
        }
        
        header {
            overflow: visible !important;
        }
        
        .notification-container .notification-count {
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
        .notification-container .notification-dropdown {
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            background: white !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
            width: 380px !important;
            max-height: 500px !important;
            overflow: hidden !important;
            z-index: 10000 !important;
            display: none !important;
            transform: translateY(-10px) !important;
            opacity: 0 !important;
            transition: all 0.2s ease !important;
            margin-top: 8px !important;
        }
        .notification-container .notification-dropdown.show {
            display: block !important;
            transform: translateY(0) !important;
            opacity: 1 !important;
        }
        .notification-container .notification-item {
            transition: all 0.2s ease !important;
        }
        .notification-container .notification-item:hover {
            transform: translateX(2px) !important;
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        @keyframes notificationPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .notification-container .animate-pulse {
            animation: notificationPulse 2s infinite !important;
        }
        </style>

        <script>
        // Notification System Functions
        function toggleNotifications() {
            console.log("toggleNotifications called");
            const dropdown = document.getElementById("notificationDropdown");
            if (dropdown) {
                const isVisible = dropdown.classList.contains("show");
                console.log("Dropdown found, current visibility:", isVisible);
                if (isVisible) {
                    dropdown.classList.remove("show");
                    console.log("Hiding dropdown");
                } else {
                    dropdown.classList.add("show");
                    console.log("Showing dropdown");
                }
            } else {
                console.error("Notification dropdown not found");
            }
        }

        function handleNotificationClick(url) {
            if (url && url !== "#") {
                window.location.href = url;
            }
            // Close dropdown after click
            const dropdown = document.getElementById("notificationDropdown");
            if (dropdown) {
                dropdown.classList.remove("show");
            }
        }

        function refreshNotifications() {
            // Simple page refresh for now - can be enhanced with AJAX
            window.location.reload();
        }

        // Close notifications when clicking outside
        document.addEventListener("click", function(event) {
            const bell = document.querySelector(".notification-bell");
            const dropdown = document.getElementById("notificationDropdown");
            
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove("show");
            }
        });

        // Prevent clicks inside dropdown from closing it
        document.addEventListener("DOMContentLoaded", function() {
            const dropdown = document.getElementById("notificationDropdown");
            if (dropdown) {
                dropdown.addEventListener("click", function(event) {
                    // Only prevent if clicking on refresh button or other controls
                    if (event.target.tagName === "BUTTON" || event.target.closest("button")) {
                        event.stopPropagation();
                    }
                });
            }
        });

        // Auto-refresh notifications every 5 minutes
        setInterval(function() {
            const countElement = document.getElementById("notificationCount");
            if (countElement) {
                fetch(window.location.pathname + "?ajax=notification_count")
                    .then(response => response.json())
                    .then(data => {
                        if (data.count !== undefined) {
                            countElement.textContent = data.count;
                            if (data.count > 0) {
                                countElement.classList.add("animate-pulse");
                            } else {
                                countElement.classList.remove("animate-pulse");
                            }
                        }
                    })
                    .catch(error => console.log("Notification refresh error:", error));
            }
        }, 300000); // 5 minutes
        </script>';
    }
}

/**
 * Helper function to initialize notification system
 */
function initializeAdminNotifications($pdo, $user_id, $user_role = 'admin') {
    return new AdminNotificationSystem($pdo, $user_id, $user_role);
}
?>
