// Student Management JavaScript

// Global variables
let currentPage = 1;
let studentsPerPage = 10;
let currentFilter = 'all';
let currentSearch = '';

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize event listeners
    initializeEventListeners();
    
    // Load initial data if on appropriate pages
    if (window.location.pathname.includes('student_profiles.php')) {
        loadStudents();
    }
    
    if (window.location.pathname.includes('progress_tracking.php')) {
        loadProgressData();
    }
    
    if (window.location.pathname.includes('quiz_performance.php')) {
        loadQuizData();
    }
    
    if (window.location.pathname.includes('engagement_monitoring.php')) {
        loadEngagementData();
    }
});

// Initialize event listeners
function initializeEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('student-search');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    // Filter functionality
    const filterSelect = document.getElementById('status-filter');
    if (filterSelect) {
        filterSelect.addEventListener('change', handleFilter);
    }
    
    // Export functionality
    const exportBtn = document.getElementById('export-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', handleExport);
    }
    
    // Modal close handlers
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            closeModal();
        }
    });
    
    // Pagination handlers
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('page-btn')) {
            const page = parseInt(e.target.dataset.page);
            if (page) {
                currentPage = page;
                loadStudents();
            }
        }
    });
}

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Load recent activity for dashboard
function loadRecentActivity() {
    fetch('api/get_recent_activity.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecentActivity(data.activities);
            } else {
                console.error('Failed to load recent activity:', data.message);
                displayRecentActivityError();
            }
        })
        .catch(error => {
            console.error('Error loading recent activity:', error);
            displayRecentActivityError();
        });
}

// Display recent activity
function displayRecentActivity(activities) {
    const container = document.getElementById('recent-activity');
    if (!container) return;
    
    if (activities.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-4 text-gray-300"></i>
                <p>No recent activity to display</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    let html = '';
    activities.forEach((activity, index) => {
        const timeAgo = formatTimeAgo(activity.created_at);
        const icon = getActivityIcon(activity.type);
        
        html += `
            <div class="activity-item ${index === activities.length - 1 ? 'last-item' : ''}">
                <div class="flex items-start space-x-3">
                    <div class="p-2 rounded-full ${getActivityColor(activity.type)}">
                        <i data-lucide="${icon}" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">${activity.student_name}</p>
                        <p class="text-sm text-gray-600">${activity.description}</p>
                        <p class="text-xs text-gray-400 mt-1">${timeAgo}</p>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    lucide.createIcons();
}

// Display error state for recent activity
function displayRecentActivityError() {
    const container = document.getElementById('recent-activity');
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center py-8 text-gray-500">
            <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4 text-red-300"></i>
            <p>Failed to load recent activity</p>
            <button onclick="loadRecentActivity()" class="text-emerald-600 hover:text-emerald-700 text-sm mt-2">
                Try again
            </button>
        </div>
    `;
    lucide.createIcons();
}

// Get activity icon based on type
function getActivityIcon(type) {
    const icons = {
        'login': 'log-in',
        'quiz_completed': 'clipboard-check',
        'module_completed': 'book-open',
        'course_completed': 'award',
        'quiz_failed': 'x-circle',
        'enrollment': 'user-plus'
    };
    return icons[type] || 'activity';
}

// Get activity color based on type
function getActivityColor(type) {
    const colors = {
        'login': 'bg-blue-100 text-blue-600',
        'quiz_completed': 'bg-green-100 text-green-600',
        'module_completed': 'bg-purple-100 text-purple-600',
        'course_completed': 'bg-yellow-100 text-yellow-600',
        'quiz_failed': 'bg-red-100 text-red-600',
        'enrollment': 'bg-emerald-100 text-emerald-600'
    };
    return colors[type] || 'bg-gray-100 text-gray-600';
}

// Format time ago
function formatTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diffInSeconds = Math.floor((now - time) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    }
}

// Load students data
function loadStudents() {
    const loadingHtml = `
        <tr>
            <td colspan="6" class="text-center py-8">
                <div class="loading-spinner mx-auto"></div>
                <p class="text-gray-500 mt-2">Loading students...</p>
            </td>
        </tr>
    `;
    
    const tableBody = document.getElementById('students-table-body');
    if (tableBody) {
        tableBody.innerHTML = loadingHtml;
    }
    
    const params = new URLSearchParams({
        page: currentPage,
        limit: studentsPerPage,
        filter: currentFilter,
        search: currentSearch
    });
    
    fetch(`api/get_students.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStudents(data.students);
                displayPagination(data.total, data.page, data.totalPages);
            } else {
                displayStudentsError(data.message);
            }
        })
        .catch(error => {
            console.error('Error loading students:', error);
            displayStudentsError('Failed to load students');
        });
}

// Display students in table
function displayStudents(students) {
    const tableBody = document.getElementById('students-table-body');
    if (!tableBody) return;
    
    if (students.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-8">
                    <i data-lucide="users" class="w-12 h-12 mx-auto mb-4 text-gray-300"></i>
                    <p class="text-gray-500">No students found</p>
                </td>
            </tr>
        `;
        lucide.createIcons();
        return;
    }
    
    let html = '';
    students.forEach(student => {
        const statusClass = student.status === 'active' ? 'status-active' : 'status-inactive';
        const lastLogin = student.last_login ? formatTimeAgo(student.last_login) : 'Never';
        const progress = student.overall_progress || 0;
        
        html += `
            <tr>
                <td>
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mr-3">
                            <span class="text-emerald-600 font-semibold">${student.username.charAt(0).toUpperCase()}</span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">${escapeHtml(student.username)}</p>
                            <p class="text-sm text-gray-500">${escapeHtml(student.email)}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="${statusClass}">${student.status}</span>
                </td>
                <td>
                    <span class="text-sm text-gray-900">${student.enrolled_courses}</span>
                </td>
                <td>
                    <div class="flex items-center">
                        <div class="progress-container w-16 mr-2">
                            <div class="progress-bar" style="width: ${progress}%"></div>
                        </div>
                        <span class="text-sm text-gray-600">${progress}%</span>
                    </div>
                </td>
                <td>
                    <span class="text-sm text-gray-600">${lastLogin}</span>
                </td>
                <td>
                    <div class="flex space-x-2">
                        <a href="student_detail.php?id=${student.id}" class="action-btn action-btn-primary">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                            View
                        </a>
                        <button onclick="viewProgress(${student.id})" class="action-btn action-btn-secondary">
                            <i data-lucide="trending-up" class="w-4 h-4"></i>
                            Progress
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    lucide.createIcons();
}

// Display students error
function displayStudentsError(message) {
    const tableBody = document.getElementById('students-table-body');
    if (!tableBody) return;
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center py-8">
                <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4 text-red-300"></i>
                <p class="text-red-500 mb-2">${escapeHtml(message)}</p>
                <button onclick="loadStudents()" class="text-emerald-600 hover:text-emerald-700">
                    Try again
                </button>
            </td>
        </tr>
    `;
    lucide.createIcons();
}

// Display pagination
function displayPagination(total, current, totalPages) {
    const container = document.getElementById('pagination-container');
    if (!container) return;
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<div class="flex items-center justify-between">';
    html += `<p class="text-sm text-gray-700">Showing ${((current - 1) * studentsPerPage) + 1} to ${Math.min(current * studentsPerPage, total)} of ${total} students</p>`;
    html += '<div class="flex space-x-1">';
    
    // Previous button
    if (current > 1) {
        html += `<button class="page-btn px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50" data-page="${current - 1}">Previous</button>`;
    }
    
    // Page numbers
    const startPage = Math.max(1, current - 2);
    const endPage = Math.min(totalPages, current + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === current ? 'bg-emerald-600 text-white' : 'text-gray-700 hover:bg-gray-50';
        html += `<button class="page-btn px-3 py-2 text-sm border border-gray-300 rounded-md ${activeClass}" data-page="${i}">${i}</button>`;
    }
    
    // Next button
    if (current < totalPages) {
        html += `<button class="page-btn px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50" data-page="${current + 1}">Next</button>`;
    }
    
    html += '</div></div>';
    container.innerHTML = html;
}

// Handle search
function handleSearch(e) {
    currentSearch = e.target.value;
    currentPage = 1;
    loadStudents();
}

// Handle filter
function handleFilter(e) {
    currentFilter = e.target.value;
    currentPage = 1;
    loadStudents();
}

// Handle export
function handleExport() {
    const params = new URLSearchParams({
        export: 'true',
        filter: currentFilter,
        search: currentSearch
    });
    
    window.open(`api/export_students.php?${params}`, '_blank');
}

// View student progress modal
function viewProgress(studentId) {
    showModal();
    
    fetch(`api/get_student_progress.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayProgressModal(data.student, data.progress);
            } else {
                displayModalError(data.message);
            }
        })
        .catch(error => {
            console.error('Error loading progress:', error);
            displayModalError('Failed to load progress data');
        });
}

// Show modal
function showModal() {
    const modal = document.getElementById('progress-modal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

// Close modal
function closeModal() {
    const modal = document.getElementById('progress-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Display progress modal
function displayProgressModal(student, progress) {
    const modalContent = document.getElementById('modal-content');
    if (!modalContent) return;
    
    let html = `
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Progress for ${escapeHtml(student.username)}</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <div class="space-y-4">
    `;
    
    progress.forEach(course => {
        html += `
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-medium text-gray-900">${escapeHtml(course.course_title)}</h4>
                    <span class="text-sm text-gray-500">${course.completion_percentage}% complete</span>
                </div>
                <div class="progress-container mb-2">
                    <div class="progress-bar" style="width: ${course.completion_percentage}%"></div>
                </div>
                <div class="text-sm text-gray-600">
                    <p>Modules completed: ${course.completed_modules} / ${course.total_modules}</p>
                    <p>Quiz average: ${course.quiz_average || 'N/A'}</p>
                    <p>Last activity: ${course.last_activity ? formatTimeAgo(course.last_activity) : 'No activity'}</p>
                </div>
            </div>
        `;
    });
    
    html += '</div></div>';
    modalContent.innerHTML = html;
    lucide.createIcons();
}

// Display modal error
function displayModalError(message) {
    const modalContent = document.getElementById('modal-content');
    if (!modalContent) return;
    
    modalContent.innerHTML = `
        <div class="p-6 text-center">
            <i data-lucide="alert-circle" class="w-12 h-12 mx-auto mb-4 text-red-300"></i>
            <p class="text-red-500 mb-4">${escapeHtml(message)}</p>
            <button onclick="closeModal()" class="action-btn action-btn-secondary">Close</button>
        </div>
    `;
    lucide.createIcons();
}

// Initialize charts
function initializeCharts() {
    // Load Chart.js if not already loaded
    if (typeof Chart === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = function() {
            createCharts();
        };
        document.head.appendChild(script);
    } else {
        createCharts();
    }
}

// Create charts
function createCharts() {
    createCompletionChart();
    createEngagementChart();
}

// Create completion chart
function createCompletionChart() {
    const ctx = document.getElementById('completionChart');
    if (!ctx) return;
    
    fetch('api/get_completion_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'In Progress', 'Not Started'],
                        datasets: [{
                            data: [data.completed, data.in_progress, data.not_started],
                            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading completion stats:', error);
        });
}

// Create engagement chart
function createEngagementChart() {
    const ctx = document.getElementById('engagementChart');
    if (!ctx) return;
    
    fetch('api/get_engagement_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Active Students',
                            data: data.values,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading engagement stats:', error);
        });
}

// Utility function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fixed top-4 right-4 z-50 min-w-80 max-w-md`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}" class="w-5 h-5 mr-2"></i>
            <span>${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-auto">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    lucide.createIcons();
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Load progress data (for progress tracking page)
function loadProgressData() {
    // Implementation for progress tracking page
    console.log('Loading progress data...');
}

// Load quiz data (for quiz performance page)
function loadQuizData() {
    // Implementation for quiz performance page
    console.log('Loading quiz data...');
}

// Load engagement data (for engagement monitoring page)
function loadEngagementData() {
    // Implementation for engagement monitoring page
    console.log('Loading engagement data...');
}
