// Hybrid Admin Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeHybridAdminDashboard();
});

function initializeHybridAdminDashboard() {
    setupSidebarNavigation();
    setupMobileMenu();
    setupContentLoading();
    initializeAnimations();
    setupTooltips();
    loadDashboardData();
}

// Sidebar Navigation
function setupSidebarNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Handle navigation
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                handleNavigation(href);
            }
        });
    });
}

// Mobile Menu Toggle
function setupMobileMenu() {
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            if (overlay) {
                overlay.classList.toggle('hidden');
            }
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            this.classList.add('hidden');
        });
    }
}

// Content Loading
function setupContentLoading() {
    const contentArea = document.getElementById('main-content');
    
    window.addEventListener('resize', function() {
        adjustContentLayout();
    });
    
    adjustContentLayout();
}

function adjustContentLayout() {
    const sidebar = document.querySelector('.sidebar');
    const content = document.getElementById('main-content');
    
    if (sidebar && content) {
        const sidebarWidth = window.getComputedStyle(sidebar).width;
        if (window.innerWidth > 768) {
            content.style.marginLeft = sidebarWidth;
        } else {
            content.style.marginLeft = '0';
        }
    }
}

// Admin Interface Loading Functions
function loadUserManagement() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'admin.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadCourseManagement() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'course_management_admin.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadSystemSettings() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'system_settings.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadReportsAnalytics() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'reports_analytics.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadAuditLogs() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'audit-trails.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadPerformanceMonitoring() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'performance-logs.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadErrorLogs() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'error-logs.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadLoginActivity() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = 'login-activity.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

// Teacher Interface Loading Functions
function loadCreateCourse() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = '../teacher/create_course.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadMyCourses() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = '../teacher/my_courses.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadStudentProgress() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = '../teacher/student_progress.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadGradeBook() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = '../teacher/gradebook.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadCreateQuiz() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = '../teacher/create_quiz.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

function loadQuizResults() {
    const iframe = document.getElementById('content-frame');
    if (iframe) {
        showLoading();
        iframe.src = '../teacher/quiz_results.php';
        iframe.onload = function() {
            hideLoading();
        };
    }
}

// Navigation Handler
function handleNavigation(href) {
    // Handle iframe-based navigation
    const iframe = document.getElementById('content-frame');
    if (iframe && href !== '#') {
        showLoading();
        iframe.src = href;
        iframe.onload = function() {
            hideLoading();
        };
    }
}

// Loading States
function showLoading() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.remove('hidden');
    }
    
    // Add loading class to content area
    const contentArea = document.getElementById('main-content');
    if (contentArea) {
        contentArea.classList.add('loading');
    }
}

function hideLoading() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
    
    // Remove loading class from content area
    const contentArea = document.getElementById('main-content');
    if (contentArea) {
        contentArea.classList.remove('loading');
    }
}

// Animations
function initializeAnimations() {
    // Animate stats cards on load
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('fade-in');
        }, index * 100);
    });
    
    // Animate quick action cards
    const actionCards = document.querySelectorAll('.quick-action-card');
    actionCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('slide-in-right');
        }, 300 + (index * 50));
    });
}

// Tooltips
function setupTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            showTooltip(this);
        });
        
        element.addEventListener('mouseleave', function() {
            hideTooltip(this);
        });
    });
}

function showTooltip(element) {
    element.classList.add('tooltip');
}

function hideTooltip(element) {
    element.classList.remove('tooltip');
}

// Dashboard Data Loading
function loadDashboardData() {
    // Load real-time statistics
    fetchStatistics();
    
    // Setup auto-refresh for stats
    setInterval(fetchStatistics, 30000); // Refresh every 30 seconds
    
    // Load recent activities
    fetchRecentActivities();
}

function fetchStatistics() {
    fetch('get_admin_stats.php')
        .then(response => response.json())
        .then(data => {
            updateStatistics(data);
        })
        .catch(error => {
            console.error('Error fetching statistics:', error);
        });
}

function updateStatistics(data) {
    // Update teacher count
    const teacherCount = document.getElementById('teacher-count');
    if (teacherCount && data.total_teachers) {
        animateCounter(teacherCount, data.total_teachers);
    }
    
    // Update student count
    const studentCount = document.getElementById('student-count');
    if (studentCount && data.total_students) {
        animateCounter(studentCount, data.total_students);
    }
    
    // Update course count
    const courseCount = document.getElementById('course-count');
    if (courseCount && data.active_courses) {
        animateCounter(courseCount, data.active_courses);
    }
    
    // Update enrollment count
    const enrollmentCount = document.getElementById('enrollment-count');
    if (enrollmentCount && data.total_enrollments) {
        animateCounter(enrollmentCount, data.total_enrollments);
    }
}

function animateCounter(element, targetValue) {
    const currentValue = parseInt(element.textContent) || 0;
    const increment = Math.ceil((targetValue - currentValue) / 20);
    
    if (currentValue < targetValue) {
        element.textContent = currentValue + increment;
        setTimeout(() => animateCounter(element, targetValue), 50);
    } else {
        element.textContent = targetValue;
    }
}

function fetchRecentActivities() {
    fetch('get_recent_activities.php')
        .then(response => response.json())
        .then(data => {
            updateRecentActivities(data);
        })
        .catch(error => {
            console.error('Error fetching recent activities:', error);
        });
}

function updateRecentActivities(activities) {
    const activityContainer = document.getElementById('recent-activities');
    if (activityContainer && activities.length > 0) {
        const activityHTML = activities.map(activity => `
            <div class="activity-item p-3 border-l-4 border-purple-400 bg-purple-50 mb-2 rounded">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-medium text-gray-900">${activity.action}</p>
                        <p class="text-xs text-gray-600">${activity.user_name}</p>
                    </div>
                    <span class="text-xs text-gray-500">${activity.timestamp}</span>
                </div>
            </div>
        `).join('');
        
        activityContainer.innerHTML = activityHTML;
    }
}

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${getNotificationClass(type)}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <span class="flex-1">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationClass(type) {
    switch (type) {
        case 'success':
            return 'bg-green-100 border-green-400 text-green-700';
        case 'error':
            return 'bg-red-100 border-red-400 text-red-700';
        case 'warning':
            return 'bg-yellow-100 border-yellow-400 text-yellow-700';
        default:
            return 'bg-purple-100 border-purple-400 text-purple-700';
    }
}

// Utility Functions
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Error Handling
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    showNotification('An error occurred. Please refresh the page.', 'error');
});

// Handle iframe errors
window.addEventListener('message', function(e) {
    if (e.data.type === 'error') {
        showNotification(e.data.message, 'error');
        hideLoading();
    }
});

// Export functions for global access
window.hybridAdminDashboard = {
    loadUserManagement,
    loadCourseManagement,
    loadSystemSettings,
    loadReportsAnalytics,
    loadAuditLogs,
    loadPerformanceMonitoring,
    loadErrorLogs,
    loadLoginActivity,
    loadCreateCourse,
    loadMyCourses,
    loadStudentProgress,
    loadGradeBook,
    loadCreateQuiz,
    loadQuizResults,
    showNotification,
    confirmAction
};
