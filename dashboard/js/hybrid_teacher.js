// Hybrid Teacher Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Hybrid Teacher Dashboard loaded');
    
    // Initialize dashboard
    initializeDashboard();
    
    // Setup navigation
    setupNavigation();
    
    // Setup responsive behavior
    setupResponsive();
    
    // Setup accessibility
    setupAccessibility();
});

// Dashboard initialization
function initializeDashboard() {
    // Add loading states
    addLoadingStates();
    
    // Setup quick action handlers
    setupQuickActions();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Setup auto-refresh for stats
    setupAutoRefresh();
}

// RBAC Modal System
function initializeRBACModal() {
    // Create modal HTML if it doesn't exist
    if (!document.getElementById('rbac-modal-overlay')) {
        const modalHTML = `
            <div id="rbac-modal-overlay" class="rbac-modal-overlay">
                <div class="rbac-modal">
                    <div class="rbac-modal-header">
                        <div class="rbac-modal-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-600">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <h3 class="rbac-modal-title">Access Restricted</h3>
                    </div>
                    <div class="rbac-modal-content">
                        <p>This feature is currently locked because your role does not have access to it. Please contact your administrator if you believe this is an error.</p>
                    </div>
                    <div class="rbac-modal-actions">
                        <button class="rbac-modal-btn rbac-modal-btn-secondary" onclick="closeRBACModal()">
                            Cancel
                        </button>
                        <button class="rbac-modal-btn rbac-modal-btn-primary" onclick="contactAdmin()">
                            Contact Admin
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
}

function showRBACModal(featureName = '') {
    const overlay = document.getElementById('rbac-modal-overlay');
    const content = overlay.querySelector('.rbac-modal-content p');
    
    if (featureName) {
        content.innerHTML = `The <strong>"${featureName}"</strong> feature is currently locked because your role does not have access to it. Please contact your administrator if you believe this is an error.`;
    } else {
        content.innerHTML = 'This feature is currently locked because your role does not have access to it. Please contact your administrator if you believe this is an error.';
    }
    
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Add click outside to close
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeRBACModal();
        }
    });
    
    // Add escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRBACModal();
        }
    });
}

function closeRBACModal() {
    const overlay = document.getElementById('rbac-modal-overlay');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

function contactAdmin() {
    // You can customize this function based on your needs
    // Options: redirect to contact form, open email client, show admin contact info, etc.
    
    // Example 1: Open email client
    window.location.href = 'mailto:admin@yourcompany.com?subject=Access Request&body=Hello, I need access to a locked feature in the dashboard. Please review my permissions.';
    
    // Example 2: Redirect to contact page
    // window.location.href = '/contact-admin';
    
    // Example 3: Show admin contact info
    // showNotification('Admin Contact: admin@yourcompany.com or call (555) 123-4567', 'info');
    
    closeRBACModal();
}

// Setup locked items with enhanced functionality
function setupLockedItems() {
    const lockedItems = document.querySelectorAll('.locked-item');
    
    lockedItems.forEach(item => {
        // Hide feature icons, keep only lock icon
        hideFeatureIcons(item);
        
        // Add tooltip
        addTooltipToLockedItem(item);
        
        // Add click handler for notification alert
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get feature name from the item
            const featureName = getFeatureName(item);
            showNotification(`🔒 Access Denied: "${featureName}" requires admin permissions. Contact your administrator to request access.`, 'warning');
            
            // Add subtle animation feedback
            item.style.transform = 'scale(0.98)';
            setTimeout(() => {
                item.style.transform = '';
            }, 150);
        });
        
        // Add keyboard support
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const featureName = getFeatureName(item);
                showNotification(`🔒 Access Denied: "${featureName}" requires admin permissions. Contact your administrator to request access.`, 'warning');
            }
        });
        
        // Make focusable for accessibility
        if (!item.hasAttribute('tabindex')) {
            item.setAttribute('tabindex', '0');
        }
        
        // Add ARIA attributes
        item.setAttribute('aria-disabled', 'true');
        item.setAttribute('aria-label', item.textContent.trim() + ' - Access restricted');
    });
    
    // Handle quick action locked items (with cursor-not-allowed)
    const quickActionLockedItems = document.querySelectorAll('.cursor-not-allowed');
    
    quickActionLockedItems.forEach(item => {
        // Hide feature icons, keep only lock icon
        hideFeatureIcons(item);
        
        // Add click handler for notification alert
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get feature name from the item
            const featureName = getFeatureName(item);
            showNotification(`🔒 Access Denied: "${featureName}" requires admin permissions. Contact your administrator to request access.`, 'warning');
            
            // Add subtle animation feedback
            item.style.transform = 'scale(0.98)';
            setTimeout(() => {
                item.style.transform = '';
            }, 150);
        });
        
        // Add keyboard support
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const featureName = getFeatureName(item);
                showNotification(`🔒 Access Denied: "${featureName}" requires admin permissions. Contact your administrator to request access.`, 'warning');
            }
        });
        
        // Make focusable for accessibility
        if (!item.hasAttribute('tabindex')) {
            item.setAttribute('tabindex', '0');
        }
        
        // Add ARIA attributes
        item.setAttribute('aria-disabled', 'true');
        item.setAttribute('aria-label', item.textContent.trim() + ' - Access restricted');
    });
}

function hideFeatureIcons(item) {
    // Find all SVG icons in the item
    const allIcons = item.querySelectorAll('svg');
    
    allIcons.forEach(icon => {
        // Keep only the lock icon (has class 'lock-icon' or 'lucide-lock')
        if (!icon.classList.contains('lock-icon') && 
            !icon.classList.contains('lucide-lock')) {
            // Hide the feature icon
            icon.style.display = 'none';
        }
    });
}

function addTooltipToLockedItem(item) {
    // Check if tooltip already exists
    if (item.querySelector('.locked-tooltip')) {
        return;
    }
    
    const tooltip = document.createElement('div');
    tooltip.className = 'locked-tooltip';
    tooltip.textContent = 'Locked — Admin access required';
    item.appendChild(tooltip);
}

function getFeatureName(item) {
    // Extract feature name from the locked item
    const textElement = item.querySelector('span') || item;
    const text = textElement.textContent.trim();
    
    // Remove common suffixes like "(Locked)"
    return text.replace(/\s*\(.*?\)\s*$/, '').trim();
}

// Enhanced notification system with RBAC context
function showRBACNotification(message, type = 'warning') {
    const notification = document.createElement('div');
    const colors = {
        info: 'bg-blue-100 border-blue-400 text-blue-700',
        success: 'bg-green-100 border-green-400 text-green-700',
        warning: 'bg-amber-100 border-amber-400 text-amber-700',
        error: 'bg-red-100 border-red-400 text-red-700'
    };
    
    const icons = {
        info: '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>',
        success: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
        warning: '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
        error: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>'
    };
    
    notification.className = `fixed top-4 right-4 p-4 border-l-4 rounded-lg shadow-lg z-50 max-w-md ${colors[type]}`;
    notification.innerHTML = `
        <div class="flex items-start">
            <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                ${icons[type]}
            </svg>
            <div class="flex-1">
                <div class="font-medium">Access Restricted</div>
                <div class="text-sm mt-1">${message}</div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-500 hover:text-gray-700 flex-shrink-0">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 7 seconds for RBAC notifications (longer than regular notifications)
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, 7000);
}

// Navigation setup
function setupNavigation() {
    const navLinks = document.querySelectorAll('.nav-link:not(.locked-item)');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active', 'bg-primary-50', 'text-primary-700'));
            
            // Add active class to clicked link
            this.classList.add('active', 'bg-primary-50', 'text-primary-700');
            
            // Add ripple effect
            addRippleEffect(this, e);
        });
    });
}

// Add ripple effect to navigation
function addRippleEffect(element, event) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(34, 197, 94, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    // Add CSS animation
    if (!document.getElementById('ripple-animation')) {
        const style = document.createElement('style');
        style.id = 'ripple-animation';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// Loading states for content
function addLoadingStates() {
    const quickActions = document.querySelectorAll('.quick-action-card');
    
    quickActions.forEach(action => {
        action.addEventListener('click', function() {
            if (!this.classList.contains('locked-item')) {
                const originalContent = this.innerHTML;
                const loadingSpinner = '<div class="loading-spinner mr-3"></div>Loading...';
                
                this.innerHTML = loadingSpinner;
                this.style.pointerEvents = 'none';
                
                // Restore after delay (for demo purposes)
                setTimeout(() => {
                    this.innerHTML = originalContent;
                    this.style.pointerEvents = 'auto';
                }, 1000);
            }
        });
    });
}

// Quick actions setup
function setupQuickActions() {
    // Teacher quick actions
    const createModuleBtn = document.querySelector('a[href="teacher_create_module.php"]');
    const viewCoursesBtn = document.querySelector('a[href="courses_available.php"]');
    
    if (createModuleBtn) {
        createModuleBtn.addEventListener('click', function(e) {
            showNotification('Redirecting to module creation...', 'info');
        });
    }
    
    if (viewCoursesBtn) {
        viewCoursesBtn.addEventListener('click', function(e) {
            showNotification('Loading your courses...', 'info');
        });
    }
    
    // Admin quick actions
    const adminButtons = document.querySelectorAll('[onclick^="load"]');
    adminButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            showNotification('Loading admin interface...', 'info');
        });
    });
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const colors = {
        info: 'bg-blue-100 border-blue-400 text-blue-700',
        success: 'bg-green-100 border-green-400 text-green-700',
        warning: 'bg-yellow-100 border-yellow-400 text-yellow-700',
        error: 'bg-red-100 border-red-400 text-red-700'
    };
    
    notification.className = `fixed top-4 right-4 p-4 border-l-4 rounded shadow-lg z-50 ${colors[type]}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            ${message}
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Enhanced tooltip initialization
function initializeTooltips() {
    // Tooltips are now handled in setupLockedItems() for locked items
    // This function can handle other tooltips if needed
    
    // Example: Add tooltips to other elements
    const elementsWithTooltips = document.querySelectorAll('[data-tooltip]');
    elementsWithTooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            showCustomTooltip(this, this.getAttribute('data-tooltip'));
        });
        
        element.addEventListener('mouseleave', function() {
            hideCustomTooltip();
        });
    });
}

function showCustomTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.id = 'custom-tooltip';
    tooltip.className = 'absolute bg-gray-800 text-white text-xs px-2 py-1 rounded shadow-lg z-50';
    tooltip.textContent = text;
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + 'px';
    tooltip.style.top = (rect.top - 30) + 'px';
    
    document.body.appendChild(tooltip);
}

function hideCustomTooltip() {
    const tooltip = document.getElementById('custom-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Responsive behavior
function setupResponsive() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // Mobile menu toggle (if needed)
    function createMobileToggle() {
        if (window.innerWidth <= 768) {
            const toggle = document.createElement('button');
            toggle.className = 'fixed top-4 left-4 z-50 bg-green-600 text-white p-2 rounded md:hidden';
            toggle.innerHTML = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            `;
            
            toggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
            
            document.body.appendChild(toggle);
        }
    }
    
    window.addEventListener('resize', createMobileToggle);
    createMobileToggle();
}

// Accessibility setup
function setupAccessibility() {
    // Add ARIA labels
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        if (link.classList.contains('locked-item')) {
            link.setAttribute('aria-disabled', 'true');
            link.setAttribute('aria-label', link.textContent.trim() + ' - Access denied');
        } else {
            link.setAttribute('aria-label', link.textContent.trim());
        }
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideTooltip();
        }
    });
}

// Auto-refresh stats
function setupAutoRefresh() {
    // Refresh stats every 5 minutes
    setInterval(function() {
        refreshDashboardStats();
    }, 5 * 60 * 1000);
}

function refreshDashboardStats() {
    // This would typically make an AJAX call to get updated stats
    console.log('Refreshing dashboard statistics...');
    
    // For now, just add a subtle animation to indicate refresh
    const statCards = document.querySelectorAll('.grid .bg-white');
    statCards.forEach(card => {
        card.style.opacity = '0.7';
        setTimeout(() => {
            card.style.opacity = '1';
        }, 200);
    });
}

// Admin content loading functions
function loadAdminDashboard() {
    console.log('Loading admin dashboard...');
    showLoadingState('Loading admin dashboard...');
    
    const mainContent = document.querySelector('.main-content .content-area');
    if (mainContent) {
        const iframe = document.createElement('iframe');
        iframe.src = 'admin.php';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '8px';
        
        iframe.onload = function() {
            hideLoadingState();
        };
        
        mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 Admin Dashboard</h2></div>';
        mainContent.appendChild(iframe);
    }
}

function loadPaymentHistory() {
    console.log('Loading payment history...');
    showLoadingState('Loading payment history...');
    
    const mainContent = document.querySelector('.main-content .content-area');
    if (mainContent) {
        const iframe = document.createElement('iframe');
        iframe.src = 'admin.php?view=payments';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '8px';
        
        iframe.onload = function() {
            hideLoadingState();
        };
        
        mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 Payment History</h2></div>';
        mainContent.appendChild(iframe);
    }
}

function loadUserManagement() {
    console.log('Loading user management...');
    showLoadingState('Loading user management...');
    
    const mainContent = document.querySelector('.main-content .content-area');
    if (mainContent) {
        const iframe = document.createElement('iframe');
        iframe.src = 'users.php';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '8px';
        
        iframe.onload = function() {
            hideLoadingState();
        };
        
        mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 User Management</h2></div>';
        mainContent.appendChild(iframe);
    }
}

function loadCourseManagement() {
    console.log('Loading course management...');
    showLoadingState('Loading course management...');
    
    const mainContent = document.querySelector('.main-content .content-area');
    if (mainContent) {
        const iframe = document.createElement('iframe');
        iframe.src = 'course_management_admin.php';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '8px';
        
        iframe.onload = function() {
            hideLoadingState();
        };
        
        mainContent.innerHTML = '<div class="p-6"><h2 class="text-xl font-semibold mb-4 text-green-700">🟢 Course Management</h2></div>';
        mainContent.appendChild(iframe);
    }
}

// Loading state functions
function showLoadingState(message = 'Loading...') {
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loading-overlay';
    loadingOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    loadingOverlay.innerHTML = `
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="loading-spinner"></div>
            <span class="text-gray-700">${message}</span>
        </div>
    `;
    
    document.body.appendChild(loadingOverlay);
}

function hideLoadingState() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

// Export functions for global access
window.loadAdminDashboard = loadAdminDashboard;
window.loadPaymentHistory = loadPaymentHistory;
window.loadUserManagement = loadUserManagement;
window.loadCourseManagement = loadCourseManagement;
window.showNotification = showNotification;
