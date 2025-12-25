/**
 * Engagement Monitoring JavaScript
 * Handles frontend interactions and data visualization
 */

class EngagementMonitor {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        // Add a small delay to ensure the page is fully loaded and session is established
        setTimeout(() => {
            this.loadCharts();
        }, 1000);
    }
    
    setupEventListeners() {
        // Filter form submission
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }
        
        // Export functionality
        window.exportData = () => this.exportData();
    }
    
    async applyFilters() {
        const dateFrom = document.getElementById('date_from')?.value;
        const dateTo = document.getElementById('date_to')?.value;
        const studentId = document.getElementById('student_filter')?.value;
        const courseId = document.getElementById('course_filter')?.value;
        
        this.showLoading();
        
        try {
            await Promise.all([
                this.loadOverallStats(dateFrom, dateTo),
                this.loadMostEngaged(dateFrom, dateTo),
                this.loadRecentEnrollments(dateFrom, dateTo),
                this.loadTimeSpentData(dateFrom, dateTo),
                this.loadCourseEngagementData(dateFrom, dateTo)
            ]);
        } catch (error) {
            console.error('Error loading data:', error);
            this.showError('Failed to load engagement data');
        } finally {
            this.hideLoading();
        }
    }
    
    async loadOverallStats(dateFrom, dateTo) {
        try {
            const response = await fetch(`api/engagement_monitoring_api.php?action=overall_stats&date_from=${dateFrom}&date_to=${dateTo}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateOverallStats(data.data);
            }
        } catch (error) {
            console.error('Error loading overall stats:', error);
        }
    }
    
    async loadMostEngaged(dateFrom, dateTo) {
        try {
            const response = await fetch(`api/engagement_monitoring_api.php?action=most_engaged&limit=10&date_from=${dateFrom}&date_to=${dateTo}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateMostEngagedTable(data.data);
            }
        } catch (error) {
            console.error('Error loading most engaged:', error);
        }
    }
    
    async loadCourseStats(dateFrom, dateTo) {
        try {
            const response = await fetch(`api/engagement_monitoring_api.php?action=course_stats&date_from=${dateFrom}&date_to=${dateTo}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateCourseStatsTable(data.data);
            }
        } catch (error) {
            console.error('Error loading course stats:', error);
        }
    }
    
    async loadRecentEnrollments(dateFrom, dateTo) {
        try {
            const response = await fetch(`api/engagement_monitoring_api.php?action=recent_enrollments&limit=20&date_from=${dateFrom}&date_to=${dateTo}`);
            const data = await response.json();
            
            if (data.success) {
                this.updateRecentEnrollmentsTable(data.data);
            }
        } catch (error) {
            console.error('Error loading recent enrollments:', error);
        }
    }
    
    async loadTimeSpentData(dateFrom, dateTo) {
        try {
            // Clean the date parameters to avoid undefined values
            const cleanDateFrom = (dateFrom && dateFrom !== 'undefined') ? dateFrom : '';
            const cleanDateTo = (dateTo && dateTo !== 'undefined') ? dateTo : '';
            
            const params = new URLSearchParams({
                action: 'time_spent',
                date_from: cleanDateFrom,
                date_to: cleanDateTo
            });
            
            const response = await fetch(`api/engagement_monitoring_api.php?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            let data;
            
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
            
            if (data.success) {
                this.updateTimeSpentChart(data.data);
            } else {
                console.error('API returned error:', data.error);
                this.showError('Failed to load time spent data: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading time spent data:', error);
            this.showError('Failed to load time spent data: ' + error.message);
        }
    }
    
    async loadCourseEngagementData(dateFrom, dateTo) {
        try {
            // Clean the date parameters to avoid undefined values
            const cleanDateFrom = (dateFrom && dateFrom !== 'undefined') ? dateFrom : '';
            const cleanDateTo = (dateTo && dateTo !== 'undefined') ? dateTo : '';
            
            const params = new URLSearchParams({
                action: 'course_stats',
                date_from: cleanDateFrom,
                date_to: cleanDateTo
            });
            
            const response = await fetch(`api/engagement_monitoring_api.php?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            let data;
            
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
            
            if (data.success) {
                this.updateCourseEngagementChart(data.data);
            } else {
                console.error('API returned error:', data.error);
                this.showError('Failed to load course engagement data: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading course engagement data:', error);
            this.showError('Failed to load course engagement data: ' + error.message);
        }
    }
    
    updateOverallStats(stats) {
        // Update statistics cards
        const elements = {
            loginFrequency: document.querySelector('[data-stat="login_frequency"]'),
            dropoffRate: document.querySelector('[data-stat="dropoff_rate"]'),
            avgEnrollmentDays: document.querySelector('[data-stat="avg_enrollment_days"]'),
            recentEnrollments: document.querySelector('[data-stat="recent_enrollments"]')
        };
        
        if (elements.loginFrequency) elements.loginFrequency.textContent = stats.login_frequency;
        if (elements.dropoffRate) elements.dropoffRate.textContent = stats.dropoff_rate;
        if (elements.avgEnrollmentDays) elements.avgEnrollmentDays.textContent = stats.avg_enrollment_days;
        if (elements.recentEnrollments) elements.recentEnrollments.textContent = stats.recent_enrollments;
    }
    
    updateMostEngagedTable(students) {
        const tbody = document.querySelector('#mostEngagedTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = students.map((student, index) => `
            <tr class="border-b border-gray-100 hover:bg-gradient-to-r hover:from-gray-50 hover:to-green-50 transition-all duration-200 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md">
                            ${student.username.charAt(0).toUpperCase()}
                        </div>
                        <div class="text-sm font-semibold text-gray-900">${student.username}</div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200">
                        ${student.enrolled_courses} courses
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-semibold text-gray-900">${Math.round(student.avg_enrollment_days)} days</div>
                </td>
            </tr>
        `).join('');
    }
    
    updateCourseStatsTable(courses) {
        const tbody = document.querySelector('#courseStatsTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = courses.map((course, index) => `
            <tr class="border-b border-gray-100 hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50 transition-all duration-200 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center text-white font-semibold text-sm shadow-md">
                            ${course.title.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">${course.title}</div>
                            <div class="text-xs text-gray-500">Course ID: ${course.id}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center space-x-2">
                        <div class="text-lg font-bold text-gray-900">${course.enrollment_count}</div>
                        <div class="text-xs text-gray-500">enrolled</div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-700 font-medium">${Math.round(course.avg_enrollment_days)} days</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200">
                        ${course.recent_enrollments} recent
                    </span>
                </td>
            </tr>
        `).join('');
    }
    
    updateRecentEnrollmentsTable(enrollments) {
        const tbody = document.querySelector('#recentEnrollmentsTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = enrollments.map((enrollment, index) => `
            <tr class="border-b border-gray-100 hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50 transition-all duration-200 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md">
                            ${enrollment.username.charAt(0).toUpperCase()}
                        </div>
                        <div class="text-sm font-semibold text-gray-900">${enrollment.username}</div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-700 font-medium">${enrollment.course_title}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-blue-100 to-cyan-100 text-blue-800 border border-blue-200">
                        ${enrollment.time_ago || this.formatTimeAgo(enrollment.enrolled_at)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500">${new Date(enrollment.enrolled_at).toLocaleDateString()}</div>
                </td>
            </tr>
        `).join('');
    }
    
    formatTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffInMs = now - date;
        const diffInMinutes = Math.floor(diffInMs / (1000 * 60));
        const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
        const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
        
        if (diffInMinutes < 60) {
            return `${diffInMinutes} minutes ago`;
        } else if (diffInHours < 24) {
            return `${diffInHours} hours ago`;
        } else if (diffInDays === 1) {
            return '1 day ago';
        } else {
            return `${diffInDays} days ago`;
        }
    }
    
    updateTimeSpentChart(data) {
        const ctx = document.getElementById('timeSpentChart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (this.timeSpentChart) {
            this.timeSpentChart.destroy();
        }
        
        this.timeSpentChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.period),
                datasets: [{
                    label: 'Average Time (minutes)',
                    data: data.map(d => d.avg_time_minutes || 0),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const minutes = context.parsed.y;
                                const hours = Math.floor(minutes / 60);
                                const mins = minutes % 60;
                                return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                const hours = Math.floor(value / 60);
                                const mins = value % 60;
                                return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    updateCourseEngagementChart(data) {
        const ctx = document.getElementById('courseEngagementChart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (this.courseEngagementChart) {
            this.courseEngagementChart.destroy();
        }
        
        this.courseEngagementChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.title),
                datasets: [{
                    data: data.map(d => d.enrollment_count || 0),
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(6, 182, 212, 0.8)',
                        'rgba(34, 197, 94, 0.8)'
                    ],
                    borderColor: [
                        'rgb(239, 68, 68)',
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(139, 92, 246)',
                        'rgb(236, 72, 153)',
                        'rgb(6, 182, 212)',
                        'rgb(34, 197, 94)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} students (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    loadCharts() {
        // Initialize charts when page loads with default date range
        const dateFromElement = document.getElementById('date_from');
        const dateToElement = document.getElementById('date_to');
        
        // Get values safely and ensure they're not undefined
        const dateFrom = (dateFromElement && dateFromElement.value) ? dateFromElement.value : '';
        const dateTo = (dateToElement && dateToElement.value) ? dateToElement.value : '';
        
        // Always load charts with clean parameters
        this.loadTimeSpentData(dateFrom, dateTo);
        this.loadCourseEngagementData(dateFrom, dateTo);
    }
    
    showFallbackCharts() {
        // Show empty charts when no data is available
        this.updateTimeSpentChart([]);
        this.updateCourseEngagementChart([]);
        this.showNotification('No data available. Please ensure you are logged in as a teacher and have course data.', 'info');
    }
    
    exportData() {
        // Export functionality
        const dateFrom = document.getElementById('date_from')?.value;
        const dateTo = document.getElementById('date_to')?.value;
        
        const params = new URLSearchParams({
            action: 'export',
            date_from: dateFrom || '',
            date_to: dateTo || ''
        });
        
        window.open(`api/engagement_monitoring_api.php?${params}`, '_blank');
    }
    
    showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.classList.remove('hidden');
    }
    
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.classList.add('hidden');
    }
    
    showError(message) {
        // Show error message
        console.error(message);
    }
    
    showNotification(message, type = 'info') {
        // Create a simple notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
            'bg-blue-100 text-blue-800 border border-blue-200'
        }`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Remove notification after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new EngagementMonitor();
});

// Global functions
window.applyFilters = function() {
    const monitor = new EngagementMonitor();
    monitor.applyFilters();
};

window.resetFilters = function() {
    document.getElementById('date_from').value = '';
    document.getElementById('date_to').value = '';
    document.getElementById('student_filter').value = '';
    document.getElementById('course_filter').value = '';
    
    const monitor = new EngagementMonitor();
    monitor.applyFilters();
};
