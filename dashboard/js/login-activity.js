// Login Activity Report JavaScript

// Export functionality
function exportData(type, format) {
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', format);
    urlParams.set('type', type);
    
    // Only keep filters relevant to the export type
    if (type === 'login') {
        // Remove broken links filters for login export
        const brokenLinkParams = ['broken_severity_filter', 'broken_status_filter', 'broken_page'];
        brokenLinkParams.forEach(param => urlParams.delete(param));
    } else if (type === 'broken_links') {
        // Remove login filters for broken links export  
        const loginParams = ['login_search', 'login_role_filter', 'login_status_filter', 'login_date_from', 'login_date_to', 'login_page'];
        loginParams.forEach(param => urlParams.delete(param));
    }
    
    const exportUrl = window.location.pathname + '?' + urlParams.toString();
    
    // Use direct download for all formats (CSV, Excel, and PDF)
    const downloadLink = document.createElement('a');
    downloadLink.href = exportUrl;
    downloadLink.style.display = 'none';
    
    // For PDF, add download attribute to force download
    if (format === 'pdf') {
        downloadLink.download = `${type}_report_${new Date().toISOString().split('T')[0]}.pdf`;
    }
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
    
    showNotification(`${format.toUpperCase()} export started for ${type.replace('_', ' ')}`, 'success');
}

// Real-time filtering functionality
function setupRealTimeFilters() {
    // Setup real-time filtering for login activity form
    const loginForm = document.getElementById('loginFilterForm');
    if (loginForm) {
        const loginInputs = loginForm.querySelectorAll('input, select');
        loginInputs.forEach(input => {
            // Add event listeners for real-time filtering
            if (input.type === 'text' || input.type === 'search') {
                // For text inputs, use input event with debouncing
                let debounceTimer;
                input.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        submitLoginFilters();
                    }, 500); // 500ms delay for text inputs
                });
            } else if (input.type === 'date' || input.tagName === 'SELECT') {
                // For dropdowns and date inputs, apply immediately
                input.addEventListener('change', function() {
                    submitLoginFilters();
                });
            }
        });
    }

    // Setup real-time filtering for broken links form
    const brokenLinksForm = document.getElementById('brokenLinksFilterForm');
    if (brokenLinksForm) {
        const brokenInputs = brokenLinksForm.querySelectorAll('select');
        brokenInputs.forEach(input => {
            input.addEventListener('change', function() {
                submitBrokenLinksFilters();
            });
        });
    }
}

// Submit login filters function
function submitLoginFilters() {
    const form = document.getElementById('loginFilterForm');
    if (!form) return;
    
    // Show loading indicator
    showLoadingIndicator('login');
    
    // Create FormData and convert to URLSearchParams
    const formData = new FormData(form);
    const urlParams = new URLSearchParams(window.location.search);
    
    // Clear existing login filter parameters
    const loginParams = ['login_search', 'login_role_filter', 'login_status_filter', 'login_date_from', 'login_date_to', 'login_page'];
    loginParams.forEach(param => urlParams.delete(param));
    
    // Add current form values
    for (let [key, value] of formData.entries()) {
        if (value.trim() !== '') {
            urlParams.set(key, value);
        }
    }
    
    // Navigate to new URL
    window.location.href = window.location.pathname + '?' + urlParams.toString();
}

// Submit broken links filters function
function submitBrokenLinksFilters() {
    const form = document.getElementById('brokenLinksFilterForm');
    if (!form) return;
    
    // Show loading indicator
    showLoadingIndicator('broken_links');
    
    // Create FormData and convert to URLSearchParams
    const formData = new FormData(form);
    const urlParams = new URLSearchParams(window.location.search);
    
    // Clear existing broken links filter parameters
    const brokenParams = ['broken_severity_filter', 'broken_status_filter', 'broken_page'];
    brokenParams.forEach(param => urlParams.delete(param));
    
    // Add current form values
    for (let [key, value] of formData.entries()) {
        if (value.trim() !== '' && !key.startsWith('login_')) {
            urlParams.set(key, value);
        }
    }
    
    // Navigate to new URL
    window.location.href = window.location.pathname + '?' + urlParams.toString();
}

// Show loading indicator
function showLoadingIndicator(type) {
    const selector = type === 'login' ? '#loginFilterForm' : '#brokenLinksFilterForm';
    const form = document.querySelector(selector);
    
    if (form) {
        // Create or update loading overlay
        let overlay = form.querySelector('.filter-loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'filter-loading-overlay';
            overlay.innerHTML = `
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000;">
                    <div style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="width: 16px; height: 16px; border: 2px solid #e5e7eb; border-top: 2px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                        <span style="color: #374151; font-size: 14px;">Applying filters...</span>
                    </div>
                </div>
            `;
            form.style.position = 'relative';
            form.appendChild(overlay);
            
            // Add CSS animation if not already present
            if (!document.getElementById('filter-loading-styles')) {
                const style = document.createElement('style');
                style.id = 'filter-loading-styles';
                style.textContent = `
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
    }
}

// Notification system
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer;">×</button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'warning' ? '#f59e0b' : '#6b7280'};
        color: white;
        padding: 12px 16px;
        border-radius: 6px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        max-width: 300px;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Initialize tooltips and real-time filtering
document.addEventListener('DOMContentLoaded', function() {
    // Setup tooltips
    const tooltips = {
        'csv': 'Export data as CSV file',
        'excel': 'Export data as Excel file', 
        'pdf': 'Export data as PDF file'
    };
    
    document.querySelectorAll('.export-buttons button').forEach(button => {
        const onclick = button.getAttribute('onclick');
        if (onclick) {
            const matches = onclick.match(/exportData\('[^']+',\s*'([^']+)'\)/);
            if (matches && tooltips[matches[1]]) {
                button.title = tooltips[matches[1]];
            }
        }
    });
    
    // Setup real-time filtering
    setupRealTimeFilters();
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const dropdowns = document.querySelectorAll('.notification-dropdown');
    dropdowns.forEach(dropdown => {
        if (!dropdown.contains(e.target) && !e.target.closest('.notification-bell')) {
            dropdown.classList.remove('show');
        }
    });
});