/**
 * Teacher Notification System JavaScript
 * Enhanced functionality for teacher notifications
 */

class TeacherNotificationManager {
    constructor() {
        this.apiUrl = 'teacher.php'; // Updated to use teacher.php for AJAX
        this.refreshInterval = 120000; // 2 minutes
        this.notificationQueue = [];
        this.isInitialized = false;
        this.lastUpdateTime = null;
        
        // Don't auto-init in constructor to avoid double initialization
    }

    init() {
        if (this.isInitialized) return;
        
        this.bindEvents();
        this.startAutoRefresh();
        this.isInitialized = true;
        
        console.log('Teacher Notification Manager initialized');
        return this;
    }

    async refreshNotifications() {
        try {
            console.log('Refreshing teacher notifications...');
            
            // Fetch fresh notification data
            const response = await fetch(`${this.apiUrl}?ajax=teacher_notifications&t=${Date.now()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.notifications) {
                this.updateNotificationDisplay(data.notifications, data.count, data.last_updated);
                this.updateNotificationCount(data.count);
                this.lastUpdateTime = new Date();
                
                console.log(`Teacher notifications updated: ${data.count} notifications`);
                return data;
            }
            
        } catch (error) {
            console.error('Error refreshing teacher notifications:', error);
            // Fallback to count-only update
            this.refreshNotificationCount();
        }
    }

    async refreshNotificationCount() {
        try {
            const response = await fetch(`${this.apiUrl}?ajax=teacher_notification_count&t=${Date.now()}`);
            const data = await response.json();
            
            if (data.count !== undefined) {
                this.updateNotificationCount(data.count);
                return data.count;
            }
        } catch (error) {
            console.error('Error refreshing notification count:', error);
        }
        return 0;
    }

    updateNotificationCount(count) {
        const countElement = document.getElementById('teacherNotificationCount');
        if (countElement) {
            const currentCount = parseInt(countElement.textContent) || 0;
            countElement.textContent = count;
            
            // Show/hide based on count
            countElement.style.display = count > 0 ? 'flex' : 'none';
            
            // Add animation for new notifications
            if (count > currentCount && count > 0) {
                countElement.classList.add('animate-pulse');
                this.animateNotificationBell();
            } else if (count === 0) {
                countElement.classList.remove('animate-pulse');
            }
        }
    }

    updateNotificationDisplay(notifications, count, lastUpdated) {
        const dropdown = document.getElementById('teacherNotificationDropdown');
        if (!dropdown) return;

        // Update last updated time if element exists
        const lastUpdatedElement = dropdown.querySelector('.last-updated');
        if (lastUpdatedElement && lastUpdated) {
            lastUpdatedElement.textContent = `Last updated: ${lastUpdated}`;
        }

        // Update notification count in header
        const headerCount = dropdown.querySelector('.notification-header p');
        if (headerCount) {
            headerCount.textContent = `${count} notification${count !== 1 ? 's' : ''}`;
        }

        console.log(`Updated notification display with ${count} notifications`);
    }

    animateNotificationBell() {
        const bell = document.querySelector('.teacher-notification-bell');
        if (bell) {
            // Remove existing animation
            bell.style.animation = 'none';
            
            // Trigger reflow
            bell.offsetHeight;
            
            // Add new animation
            bell.style.animation = 'teacherNotificationPulse 0.6s ease-in-out 2';
            
            // Remove animation after completion
            setTimeout(() => {
                bell.style.animation = '';
            }, 1200);
        }
    }

    bindEvents() {
        // Close notifications when clicking outside
        document.addEventListener('click', (event) => {
            const bell = document.querySelector('.teacher-notification-bell');
            const dropdown = document.getElementById('teacherNotificationDropdown');
            
            if (bell && dropdown && 
                !bell.contains(event.target) && 
                !dropdown.contains(event.target)) {
                this.hideNotifications();
            }
        });

        // Prevent dropdown from closing when clicking inside
        const dropdown = document.getElementById('teacherNotificationDropdown');
        if (dropdown) {
            dropdown.addEventListener('click', (event) => {
                event.stopPropagation();
            });
        }

        // Handle keyboard navigation
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.hideNotifications();
            }
        });
    }

    async toggleNotifications() {
        const dropdown = document.getElementById('teacherNotificationDropdown');
        if (!dropdown) {
            console.error('Teacher notification dropdown not found');
            return;
        }

        const isVisible = dropdown.classList.contains('show');
        
        if (isVisible) {
            this.hideNotifications();
        } else {
            await this.showNotifications();
        }
    }

    showNotifications() {
        const dropdown = document.getElementById('teacherNotificationDropdown');
        if (dropdown) {
            dropdown.classList.add('show');
            this.refreshNotifications();
        }
    }

    hideNotifications() {
        const dropdown = document.getElementById('teacherNotificationDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }

    // Removed duplicate refreshNotifications() method that was using wrong endpoint

    // Removed duplicate updateNotificationCount() method that was using wrong endpoint

    updateNotificationDropdown(notifications) {
        const dropdown = document.getElementById('teacherNotificationDropdown');
        if (!dropdown) return;

        // Group notifications by category
        const grouped = this.groupNotificationsByCategory(notifications);
        
        let html = `
            <div class="notification-header">
                <h3>Teacher Notifications</h3>
                <p>${notifications.length} notification${notifications.length !== 1 ? 's' : ''}</p>
            </div>
        `;

        if (notifications.length === 0) {
            html += `
                <div class="teacher-notification-empty">
                    <div class="empty-icon">🔕</div>
                    <p class="empty-title">No new notifications</p>
                    <p class="empty-subtitle">All caught up!</p>
                </div>
            `;
        } else {
            html += '<div class="notification-scroll" style="max-height: 400px; overflow-y: auto;">';
            
            const categoryTitles = {
                'student_progress': 'Student Progress',
                'engagement': 'Engagement Alerts',
                'course_updates': 'Course & System Updates',
                'admin_updates': 'Admin Notifications'
            };

            for (const [category, categoryNotifications] of Object.entries(grouped)) {
                if (categoryNotifications.length > 0) {
                    html += `
                        <div class="teacher-notification-category-header">
                            <h4>${categoryTitles[category] || category}</h4>
                        </div>
                    `;

                    categoryNotifications.forEach(notification => {
                        html += this.renderNotificationItem(notification);
                    });
                }
            }
            
            html += '</div>';
        }

        html += `
            <div class="teacher-notification-footer">
                <span class="last-updated">Last updated: ${this.formatTime(new Date())}</span>
                <button class="refresh-btn" onclick="teacherNotificationManager.refreshNotifications()">
                    🔄 Refresh
                </button>
            </div>
        `;

        dropdown.innerHTML = html;
    }

    renderNotificationItem(notification) {
        const icon = this.getNotificationIcon(notification.type);
        const timestamp = this.formatTimestamp(notification.timestamp);
        const priorityClass = `priority-${notification.priority}`;
        const categoryClass = `category-${notification.category.replace('_', '-')}`;
        const bgPriorityClass = `bg-priority-${notification.priority}`;

        return `
            <div class="teacher-notification-item ${bgPriorityClass}" 
                 onclick="teacherNotificationManager.handleNotificationClick('${notification.action_url}')">
                <div class="teacher-notification-content">
                    <div class="teacher-notification-icon">${icon}</div>
                    <div class="teacher-notification-body">
                        <p class="teacher-notification-title">${this.escapeHtml(notification.title)}</p>
                        <p class="teacher-notification-message">${this.escapeHtml(notification.message)}</p>
                        <div class="teacher-notification-meta">
                            <span class="teacher-notification-category-badge ${categoryClass}">
                                ${notification.category.replace('_', ' ')}
                            </span>
                            <span class="teacher-notification-timestamp">${timestamp}</span>
                            <span class="teacher-notification-priority ${priorityClass}">
                                ${notification.priority}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    groupNotificationsByCategory(notifications) {
        const grouped = {
            'student_progress': [],
            'engagement': [],
            'course_updates': [],
            'admin_updates': []
        };

        notifications.forEach(notification => {
            const category = notification.category || 'system';
            if (grouped[category]) {
                grouped[category].push(notification);
            }
        });

        return grouped;
    }

    handleNotificationClick(url) {
        if (url && url !== '#') {
            window.location.href = url;
        }
        this.hideNotifications();
    }

    getNotificationIcon(type) {
        const icons = {
            'quiz_completion': '✅',
            'course_completion': '🎓',
            'low_performance': '⚠️',
            'inactive_student': '😴',
            'struggling_student': '📉',
            'new_enrollment': '👋',
            'milestone': '🏆',
            'admin_announcement': '📢',
            'course_status_change': '🔄',
            'system': '⚙️'
        };
        
        return icons[type] || '🔔';
    }

    formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) { // Less than 1 minute
            return 'Just now';
        } else if (diff < 3600000) { // Less than 1 hour
            const minutes = Math.floor(diff / 60000);
            return `${minutes} min ago`;
        } else if (diff < 86400000) { // Less than 1 day
            const hours = Math.floor(diff / 3600000);
            return `${hours} hr ago`;
        } else {
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }
    }

    formatTime(date) {
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    showNewNotificationIndicator() {
        // Visual indicator for new notifications
        const bell = document.querySelector('.teacher-notification-bell');
        if (bell) {
            bell.style.animation = 'teacherPulseGlow 0.5s ease-in-out 3';
        }
    }

    async getNotificationStats() {
        try {
            const response = await fetch(`${this.apiUrl}?ajax=teacher_notification_stats`);
            const data = await response.json();
            return data.stats;
        } catch (error) {
            console.error('Error getting notification stats:', error);
            return null;
        }
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch(`${this.apiUrl}?ajax=mark_notification_read&id=${notificationId}`);
            const data = await response.json();
            
            if (data.success) {
                this.refreshNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    startAutoRefresh() {
        // Clear any existing interval
        if (this.refreshIntervalId) {
            clearInterval(this.refreshIntervalId);
        }
        
        // Set up auto-refresh interval
        this.refreshIntervalId = setInterval(() => {
            console.log('Auto-refreshing teacher notifications...');
            this.refreshNotifications();
        }, this.refreshInterval);
        
        console.log(`Auto-refresh started with ${this.refreshInterval / 1000}s interval`);
    }

    stopAutoRefresh() {
        if (this.refreshIntervalId) {
            clearInterval(this.refreshIntervalId);
            this.refreshIntervalId = null;
            console.log('Auto-refresh stopped');
        }
    }

    // Public methods for external access
    async getStudentProgressNotifications() {
        try {
            const response = await fetch(`${this.apiUrl}?ajax=teacher_notifications`);
            const data = await response.json();
            return data.notifications.filter(n => n.category === 'student_progress' || n.type === 'student_progress');
        } catch (error) {
            console.error('Error getting student progress notifications:', error);
            return [];
        }
    }

    async getEngagementAlerts() {
        try {
            const response = await fetch(`${this.apiUrl}?ajax=teacher_notifications`);
            const data = await response.json();
            return data.notifications.filter(n => n.category === 'engagement' || n.type === 'engagement');
        } catch (error) {
            console.error('Error getting engagement alerts:', error);
            return [];
        }
    }

    async getCourseUpdates() {
        try {
            const response = await fetch(`${this.apiUrl}?ajax=teacher_notifications`);
            const data = await response.json();
            return data.notifications.filter(n => n.category === 'course' || n.type === 'course');
        } catch (error) {
            console.error('Error getting course updates:', error);
            return [];
        }
    }

    async getAdminUpdates() {
        try {
            const response = await fetch(`${this.apiUrl}?ajax=teacher_notifications`);
            const data = await response.json();
            return data.notifications.filter(n => n.category === 'admin' || n.type === 'admin');
        } catch (error) {
            console.error('Error getting admin updates:', error);
            return [];
        }
    }
}

// Global instance
let teacherNotificationManager;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    teacherNotificationManager = new TeacherNotificationManager();
});

// Global functions for backward compatibility
function toggleTeacherNotifications() {
    if (teacherNotificationManager) {
        teacherNotificationManager.toggleNotifications();
    }
}

function handleTeacherNotificationClick(url) {
    if (teacherNotificationManager) {
        teacherNotificationManager.handleNotificationClick(url);
    }
}

function refreshTeacherNotifications() {
    if (teacherNotificationManager) {
        teacherNotificationManager.refreshNotifications();
    }
}
