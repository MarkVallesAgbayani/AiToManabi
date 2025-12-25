// User Roles Report JavaScript - Modal, Search & Interactive Features
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeTableSorting();
    initializeSearchFeatures();
});

// Initialize Event Listeners
function initializeEventListeners() {
    // Mobile sidebar toggle
    const mobileToggle = document.querySelector('[onclick="toggleMobileSidebar()"]');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleMobileSidebar);
    }

    // Notification bell already has onclick handler from notification system
    // No need to add another event listener here
    // Click-outside handling is also done by the notification system

    // Close modal when clicking outside
    // (This is now handled in the viewUserDetails function)

    // ESC key to close modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeUserModal();
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        }
    });

    // Auto-submit form when filters change
    const filterForm = document.querySelector('form');
    const filterInputs = filterForm.querySelectorAll('select');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Add loading state
            showLoadingState();
            // Submit form after short delay
            setTimeout(() => {
                filterForm.submit();
            }, 300);
        });
    });

    // Search input with debounce
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    showLoadingState();
                    setTimeout(() => {
                        filterForm.submit();
                    }, 300);
                }
            }, 500);
        });
    }

    // Table row hover effects
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(2px)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
}

// Initialize Table Sorting
function initializeTableSorting() {
    const sortableHeaders = document.querySelectorAll('.sortable');
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortBy = this.getAttribute('data-sort');
            const currentSort = new URLSearchParams(window.location.search).get('sort_by');
            const currentOrder = new URLSearchParams(window.location.search).get('sort_order');
            
            let newOrder = 'ASC';
            if (currentSort === sortBy && currentOrder === 'ASC') {
                newOrder = 'DESC';
            }
            
            // Update URL with new sort parameters
            const url = new URL(window.location);
            url.searchParams.set('sort_by', sortBy);
            url.searchParams.set('sort_order', newOrder);
            url.searchParams.set('page', '1'); // Reset to first page
            
            // Add loading state
            showLoadingState();
            
            // Navigate to sorted results
            window.location.href = url.toString();
        });
    });
    
    // Update visual indicators for current sort
    updateSortIndicators();
}

// Update Sort Visual Indicators
function updateSortIndicators() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort_by');
    const currentOrder = urlParams.get('sort_order');
    
    document.querySelectorAll('.sortable').forEach(header => {
        header.classList.remove('sorted-asc', 'sorted-desc');
        
        if (header.getAttribute('data-sort') === currentSort) {
            if (currentOrder === 'ASC') {
                header.classList.add('sorted-asc');
            } else {
                header.classList.add('sorted-desc');
            }
        }
    });
}

// Initialize Search Features
function initializeSearchFeatures() {
    // Add search suggestions (if needed in future)
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            this.style.borderColor = 'var(--primary-500)';
        });
        
        searchInput.addEventListener('blur', function() {
            this.style.borderColor = '';
        });
    }
    
    // Highlight search terms in results
    highlightSearchTerms();
}

// Highlight Search Terms
function highlightSearchTerms() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get('search');
    
    if (searchTerm && searchTerm.length >= 3) {
        const tableRows = document.querySelectorAll('tbody tr');
        const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
        
        tableRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                if (cell.querySelector('button')) return; // Skip action column
                
                const originalText = cell.textContent;
                if (originalText.toLowerCase().includes(searchTerm.toLowerCase())) {
                    cell.innerHTML = originalText.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>');
                }
            });
        });
    }
}

// User Details Modal Functions
async function viewUserDetails(userId) {
    const modal = document.getElementById('userDetailsModal');
    const modalContent = document.getElementById('modalContent');
    
    // Show modal with loading state
    modal.style.display = 'flex';
    modalContent.innerHTML = `
        <div class="flex items-center justify-center py-8">
            <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="ml-2 text-gray-600">Loading user details...</span>
        </div>
    `;
    
    // Add click outside to close functionality
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeUserModal();
        }
    });
    
    try {
        const response = await fetch(`get_user_details.php?user_id=${userId}`);
        const data = await response.json();
        
        if (!data.success) {
            modalContent.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-red-800 font-medium">Error Loading User Details</p>
                            <p class="text-red-600 text-sm">${escapeHtml(data.message || 'Failed to load user details')}</p>
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        // Check if user is restricted (deleted/banned/suspended)
        if (data.restricted === true) {
            modalContent.innerHTML = generateRestrictedUserHTML(data.user);
            return;
        }
        
        // For active users, show full details
        modalContent.innerHTML = generateUserDetailsHTML(data);
        
        // Add animations to modal content
        const elements = modalContent.querySelectorAll('.user-profile-header, .detail-card, .course-item, .stat-card');
        elements.forEach((el, index) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            setTimeout(() => {
                el.style.transition = 'all 0.3s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, index * 50);
        });
        
    } catch (error) {
        console.error('Error fetching user details:', error);
        modalContent.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-red-800 font-medium">Connection Error</p>
                        <p class="text-red-600 text-sm">Failed to load user details. Please check your connection and try again.</p>
                    </div>
                </div>
            </div>
        `;
    }
}

// Generate Restricted User HTML (for deleted/banned/suspended users)
function generateRestrictedUserHTML(user) {
    const statusColors = {
        'deleted': 'bg-red-100 text-red-800 border-red-300',
        'banned': 'bg-red-100 text-red-800 border-red-300',
        'suspended': 'bg-orange-100 text-orange-800 border-orange-300'
    };
    
    const statusIcons = {
        'deleted': '🗑️',
        'banned': '🚫',
        'suspended': '⏸️'
    };
    
    const status = user.status.toLowerCase();
    const colorClass = statusColors[status] || 'bg-gray-100 text-gray-800 border-gray-300';
    const icon = statusIcons[status] || '⚠️';
    
    return `
        <div class="restricted-user-notice">
            <div class="text-center py-8">
                <div class="text-6xl mb-4">${icon}</div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Account ${capitalizeFirst(status)}</h3>
                <p class="text-gray-600 mb-6">${escapeHtml(user.message)}</p>
                
                <div class="max-w-md mx-auto ${colorClass} border-2 rounded-lg p-6">
                    <div class="space-y-3">
                        <div class="flex justify-between items-center border-b border-current border-opacity-20 pb-2">
                            <span class="font-semibold">User ID:</span>
                            <span class="font-mono">#${user.id}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-current border-opacity-20 pb-2">
                            <span class="font-semibold">Username:</span>
                            <span>@${escapeHtml(user.username)}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Account Status:</span>
                            <span class="font-bold uppercase">${escapeHtml(user.status)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 text-sm text-gray-500">
                    <p>⚠️ For security and privacy reasons, detailed account information is not available.</p>
                    <p class="mt-2">Contact a system administrator for more information.</p>
                </div>
            </div>
        </div>
    `;
}

// Generate User Details HTML
function generateUserDetailsHTML(data) {
    const user = data.user;
    const roleData = data.role_data;
    
    let html = `
        <div class="user-profile-header">
            <div class="flex-shrink-0">
                ${user.profile_picture ? 
                    `<img src="${escapeHtml(user.profile_picture)}" alt="Profile" class="user-avatar">` :
                    `<div class="user-avatar-placeholder">${user.first_name ? user.first_name.charAt(0).toUpperCase() : user.username.charAt(0).toUpperCase()}</div>`
                }
            </div>
            <div class="user-info flex-1">
                <h3>${escapeHtml(user.first_name && user.last_name ? user.first_name + ' ' + user.last_name : user.username)}</h3>
                <p class="text-lg font-medium">${escapeHtml(user.email)}</p>
                <p class="text-sm">@${escapeHtml(user.username)}</p>
                <div class="user-status">
                    <span class="status-badge ${user.role}">${getRoleIcon(user.role)} ${capitalizeFirst(user.role)}</span>
                    <span class="status-badge ${['active', 'Active', null].includes(user.status) ? 'active' : 'inactive'} ml-2">
                        ${['active', 'Active', null].includes(user.status) ? '✅ Active' : '❌ ' + capitalizeFirst(user.status || 'inactive')}
                    </span>
                </div>
            </div>
        </div>
        
        <div class="user-details-grid">
            <div class="detail-card">
                <h4>User ID</h4>
                <p>#${user.id}</p>
            </div>
            <div class="detail-card">
                <h4>Date Registered</h4>
                <p>${user.createdAt || 'N/A'}</p>
            </div>
        </div>
    `;
    
    // Add role-specific content
    html += generateRoleSpecificContent(user.role, roleData);
    
    return html;
}

// Generate Role-Specific Content
function generateRoleSpecificContent(role, roleData) {
    switch (role) {
        case 'student':
            return generateStudentContent(roleData);
        case 'teacher':
            return generateTeacherContent(roleData);
        case 'admin':
            return generateAdminContent(roleData);
        default:
            return '';
    }
}

// Generate Student Content
function generateStudentContent(roleData) {
    let html = '<div class="role-content">';
    
    if (roleData.enrolled_courses && roleData.enrolled_courses.length > 0) {
        html += `
            <h4>🎓 Enrolled Courses (${roleData.enrolled_courses.length})</h4>
            <div class="course-list">
        `;
        
        roleData.enrolled_courses.forEach(course => {
            const completionPercentage = course.completion_percentage || 0;
            const status = course.completion_status || 'in_progress';
            
            html += `
                <div class="course-item">
                    <h5>${escapeHtml(course.title)}</h5>
                    <p>${escapeHtml(course.description || 'No description available')}</p>
                    <div class="course-meta">
                        <span>Enrolled: ${formatDate(course.enrolled_at)}</span>
                        <span>Status: ${capitalizeFirst(status.replace('_', ' '))}</span>
                        ${course.completed_at ? `<span>Completed: ${formatDate(course.completed_at)}</span>` : ''}
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${completionPercentage}%"></div>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">${completionPercentage}% Complete</div>
                </div>
            `;
        });
        
        html += '</div>';
    } else {
        html += '<p class="text-gray-500">No enrolled courses found.</p>';
    }
    
    // Quiz attempts
    if (roleData.quiz_attempts && roleData.quiz_attempts.length > 0) {
        html += `
            <h4 class="mt-6">📝 Recent Quiz Attempts</h4>
            <div class="course-list">
        `;
        
        roleData.quiz_attempts.forEach(attempt => {
            const percentage = attempt.max_score > 0 ? Math.round((attempt.score / attempt.max_score) * 100) : 0;
            
            html += `
                <div class="course-item">
                    <h5>${escapeHtml(attempt.quiz_title)}</h5>
                    <p>Course: ${escapeHtml(attempt.course_title)}</p>
                    <div class="course-meta">
                        <span>Score: ${attempt.score}/${attempt.max_score} (${percentage}%)</span>
                        <span>Completed: ${formatDate(attempt.completed_at)}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${percentage}%"></div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    html += '</div>';
    return html;
}

// Generate Teacher Content
function generateTeacherContent(roleData) {
    let html = '<div class="role-content">';
    
    if (roleData.teaching_courses && roleData.teaching_courses.length > 0) {
        html += `
            <h4>👨‍🏫 Teaching Courses (${roleData.teaching_courses.length})</h4>
            <div class="course-list">
        `;
        
        roleData.teaching_courses.forEach(course => {
            html += `
                <div class="course-item">
                    <h5>${escapeHtml(course.title)}</h5>
                    <p>${escapeHtml(course.description || 'No description available')}</p>
                    <div class="course-meta">
                        <span>Created: ${formatDate(course.created_at)}</span>
                        <span>Status: ${capitalizeFirst(course.status || 'draft')}</span>
                        <span>Students: ${course.enrolled_students}</span>
                        <span>Chapters: ${course.total_chapters}</span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    } else {
        html += '<p class="text-gray-500">No courses created yet.</p>';
    }
    
    // Recent student activity
    if (roleData.student_activity && roleData.student_activity.length > 0) {
        html += `
            <h4 class="mt-6">📊 Recent Student Activity</h4>
            <div class="course-list">
        `;
        
        roleData.student_activity.forEach(activity => {
            html += `
                <div class="course-item">
                    <h5>${escapeHtml(activity.first_name + ' ' + activity.last_name)} (@${escapeHtml(activity.username)})</h5>
                    <p>Course: ${escapeHtml(activity.course_title)}</p>
                    <div class="course-meta">
                        <span>Enrolled: ${formatDate(activity.enrolled_at)}</span>
                        <span>Status: ${activity.completed_at ? 'Completed on ' + formatDate(activity.completed_at) : 'In Progress'}</span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    html += '</div>';
    return html;
}

// Generate Admin Content
function generateAdminContent(roleData) {
    let html = '<div class="role-content">';
    
    html += `
        <h4>👑 System Overview</h4>
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-number">${roleData.total_users || 0}</div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${roleData.total_courses || 0}</div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${roleData.total_enrollments || 0}</div>
                <div class="stat-label">Total Enrollments</div>
            </div>
        </div>
    `;
    
    // Recent admin actions
    if (roleData.recent_actions && roleData.recent_actions.length > 0) {
        html += `
            <h4 class="mt-6">📋 Recent Actions</h4>
            <div class="course-list">
        `;
        
        roleData.recent_actions.forEach(action => {
            html += `
                <div class="course-item">
                    <h5>${escapeHtml(action.action)}</h5>
                    <p>Details: ${escapeHtml(action.details || 'N/A')}</p>
                    <div class="course-meta">
                        <span>Date: ${formatDate(action.created_at)}</span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    } else {
        html += '<p class="text-gray-500 mt-4">No recent admin actions recorded.</p>';
    }
    
    html += '</div>';
    return html;
}

// Close User Modal
function closeUserModal() {
    const modal = document.getElementById('userDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Export functionality
function exportData(format) {
    // Validate export columns are selected for PDF export
    if (format === 'pdf') {
        const checkboxes = document.querySelectorAll('input[name="export_columns[]"]:checked');
        if (checkboxes.length === 0) {
            showValidationModal();
            return;
        }
    }
    
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.set('export', format);
    
    // Get all form data including export columns
    const form = document.getElementById('filterForm');
    if (form) {
        const formData = new FormData(form);
        
        // Clear existing export columns from URL params
        currentParams.delete('export_columns[]');
        
        // Add all form data to URL params
        for (let [key, value] of formData.entries()) {
            if (key === 'export_columns[]') {
                currentParams.append('export_columns[]', value);
            } else {
                currentParams.set(key, value);
            }
        }
    }
    
    // Show loading state
    const exportBtn = event.target;
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<span class="animate-spin mr-2">⏳</span> Exporting...';
    exportBtn.disabled = true;
    
    // Use direct download for all formats (CSV, Excel, and PDF)
    const link = document.createElement('a');
    link.href = `user-role-report.php?${currentParams.toString()}`;
    link.style.display = 'none';
    
    // For PDF, we can optionally add download attribute to force download
    if (format === 'pdf') {
        link.download = `user_role_report_${new Date().toISOString().split('T')[0]}.pdf`;
    }
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button after delay
    setTimeout(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        showNotification(`${format.toUpperCase()} export completed`, 'success', 3000);
    }, 2000);
}

// Mobile Sidebar Toggle
function toggleMobileSidebar() {
    const sidebar = document.querySelector('.bg-white.w-64');
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
    }
}

// Notification System - Only define if not already defined
if (typeof toggleNotifications !== 'function') {
    window.toggleNotifications = function() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }
}

// Loading States
function showLoadingState() {
    // Add loading overlay
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-50';
    overlay.innerHTML = `
        <div class="bg-white rounded-lg p-6 flex items-center gap-3 shadow-lg">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
            <span class="text-gray-700 font-medium">Loading user data...</span>
        </div>
    `;
    document.body.appendChild(overlay);
}

function hideLoadingState() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Notification System
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} fixed top-4 right-4 z-50 min-w-80 transform transition-all duration-300 translate-x-full`;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg hover:opacity-70">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Slide in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, duration);
}

// Utility Functions
function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getRoleIcon(role) {
    switch (role) {
        case 'student': return '🎓';
        case 'teacher': return '👨‍🏫';
        case 'admin': return '👑';
        default: return '👤';
    }
}

function formatNumber(number) {
    return new Intl.NumberFormat().format(number);
}

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'r':
                e.preventDefault();
                location.reload();
                break;
            case 'e':
                e.preventDefault();
                exportData('csv');
                break;
            case 'n':
                e.preventDefault();
                toggleNotifications();
                break;
            case 'f':
                e.preventDefault();
                const searchInput = document.getElementById('search');
                if (searchInput) searchInput.focus();
                break;
        }
    }
});

// Performance monitoring
const performanceObserver = new PerformanceObserver((list) => {
    for (const entry of list.getEntries()) {
        if (entry.entryType === 'navigation') {
            console.log(`Page load time: ${entry.loadEventEnd - entry.loadEventStart}ms`);
        }
    }
});

if (typeof PerformanceObserver !== 'undefined') {
    performanceObserver.observe({ entryTypes: ['navigation'] });
}

// Auto-hide loading state on page load
window.addEventListener('load', function() {
    hideLoadingState();
});

// Export functions for potential external use
window.userRoleFunctions = {
    viewUserDetails,
    closeUserModal,
    exportData,
    toggleMobileSidebar,
    // toggleNotifications is provided by the global notification system
    showNotification,
    formatDate,
    formatNumber,
    capitalizeFirst,
    escapeHtml
};

// Analytics tracking (optional)
function trackEvent(category, action, label = '') {
    // Implement analytics tracking here if needed
    console.log(`Analytics: ${category} - ${action} - ${label}`);
}

// Track user interactions
document.addEventListener('click', function(e) {
    if (e.target.matches('.export-buttons button')) {
        trackEvent('Export', 'Click', e.target.textContent.trim());
    }
    
    if (e.target.matches('.view-btn')) {
        trackEvent('User Details', 'View', 'Modal Open');
    }
    
    if (e.target.matches('.sortable')) {
        trackEvent('Table Sort', 'Click', e.target.getAttribute('data-sort'));
    }
});

// Initialize tooltips for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Add tooltips to action buttons
    const viewButtons = document.querySelectorAll('.view-btn');
    viewButtons.forEach(btn => {
        btn.setAttribute('title', 'Click to view detailed user information');
    });
    
    // Add tooltips to sortable headers
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.setAttribute('title', 'Click to sort by this column');
    });
    
    // Add tooltips to export buttons
    const exportButtons = document.querySelectorAll('.export-buttons button');
    exportButtons.forEach(btn => {
        let format = 'CSV';
        if (btn.textContent.includes('Excel')) format = 'Excel';
        if (btn.textContent.includes('PDF')) format = 'PDF';
        btn.setAttribute('title', `Export user data as ${format} file`);
    });
});

// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-fadeInUp');
        }
    });
}, observerOptions);

// Observe elements for animations
document.addEventListener('DOMContentLoaded', function() {
    const elementsToAnimate = document.querySelectorAll('.role-summary-card, .filter-form, table');
    elementsToAnimate.forEach(el => observer.observe(el));
});

// Show modern validation modal (like usage-analytics.php)
function showValidationModal() {
    // Remove existing modal if any
    const existingModal = document.getElementById('validationModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'validationModal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-gray-900">Export Validation Required</h3>
                    </div>
                </div>
                <div class="mb-6">
                    <p class="text-sm text-gray-600">Please select at least one export column to generate the PDF report.</p>
                </div>
                <div class="flex justify-end">
                    <button onclick="closeValidationModal()" class="bg-primary-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors">
                        OK
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeValidationModal();
        }
    });
}

// Close validation modal
function closeValidationModal() {
    const modal = document.getElementById('validationModal');
    if (modal) {
        modal.remove();
    }
}

console.log('User Roles Report Dashboard initialized successfully!');
