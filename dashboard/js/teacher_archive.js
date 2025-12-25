// Teacher Archive JavaScript Functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    initializeArchivePage();
});

function initializeArchivePage() {
    // Add any initialization logic here
    console.log('Archive page initialized');
}

// Custom confirmation dialog system
function showCustomConfirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm = null, onCancel = null) {
    // Remove any existing confirm dialogs
    const existingDialogs = document.querySelectorAll('#customConfirmDialog');
    existingDialogs.forEach(dialog => {
        if (dialog.style.display !== 'none') {
            dialog.style.display = 'none';
        }
    });
    
    const dialog = document.getElementById('customConfirmDialog');
    const titleEl = document.getElementById('confirmTitle');
    const messageEl = document.getElementById('confirmMessage');
    const confirmBtn = document.getElementById('confirmBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    
    // Set content
    titleEl.textContent = title;
    messageEl.textContent = message;
    confirmBtn.textContent = confirmText;
    cancelBtn.textContent = cancelText;
    
    // Show dialog
    dialog.classList.remove('hidden');
    dialog.style.display = 'flex';
    
    // Event handlers
    const handleConfirm = () => {
        dialog.classList.add('hidden');
        dialog.style.display = 'none';
        if (onConfirm) onConfirm();
    };
    
    const handleCancel = () => {
        dialog.classList.add('hidden');
        dialog.style.display = 'none';
        if (onCancel) onCancel();
    };
    
    // Remove existing event listeners
    confirmBtn.replaceWith(confirmBtn.cloneNode(true));
    cancelBtn.replaceWith(cancelBtn.cloneNode(true));
    
    // Add new event listeners
    document.getElementById('confirmBtn').onclick = handleConfirm;
    document.getElementById('cancelBtn').onclick = handleCancel;
    
    // Close on backdrop click
    dialog.onclick = (e) => {
        if (e.target === dialog) {
            handleCancel();
        }
    };
    
    // Close on Escape key
    const handleEscape = (e) => {
        if (e.key === 'Escape') {
            handleCancel();
            document.removeEventListener('keydown', handleEscape);
        }
    };
    document.addEventListener('keydown', handleEscape);
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove any existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => {
        notification.remove();
    });
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add to container
    const container = document.getElementById('notificationContainer');
    container.appendChild(notification);
    
    // Show notification with animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Hide notification after 4 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 4000);
}

// Restore course function
function restoreCourse(courseId, courseTitle) {
    showCustomConfirm(
        'Restore Course',
        `Are you sure you want to restore "${courseTitle}"? This will move it back to your drafts where you can edit and publish it again.`,
        'Yes, Restore',
        'Cancel',
        () => {
            // Proceed with restore
            proceedWithRestore(courseId, courseTitle);
        }
    );
}

// Proceed with restore operation
function proceedWithRestore(courseId, courseTitle) {
    // Show loading state
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Restoring...';
    button.disabled = true;
    
    // Make API call
    fetch('teacher_archive.php?ajax=1', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=restore&course_id=${courseId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Remove the course card from the page
            const courseCard = button.closest('.group');
            courseCard.style.transition = 'all 0.3s ease';
            courseCard.style.transform = 'scale(0.8)';
            courseCard.style.opacity = '0';
            
            setTimeout(() => {
                courseCard.remove();
                
                // Check if no more courses
                const remainingCourses = document.querySelectorAll('.group');
                if (remainingCourses.length === 0) {
                    // Reload page to show empty state
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            }, 300);
        } else {
            showNotification(data.message || 'Failed to restore course', 'error');
            // Restore button state
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while restoring the course', 'error');
        // Restore button state
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Delete permanently function
function deletePermanently(courseId, courseTitle) {
    showCustomConfirm(
        'Permanent Deletion Warning',
        `⚠️ WARNING: You are about to permanently delete "${courseTitle}". This action will:
        
• Remove all course data permanently
• Delete all sections, chapters, and quizzes
• Remove all student progress and enrollments
• This action CANNOT be undone

Are you absolutely sure you want to proceed?`,
        'Yes, Delete Forever',
        'Cancel',
        () => {
            // Show second confirmation for permanent deletion
            showCustomConfirm(
                'Final Confirmation',
                `🚨 FINAL WARNING 🚨

You are about to permanently delete "${courseTitle}" and ALL its data.

This includes:
• Course content and materials
• All sections and chapters
• All quizzes and questions
• Student progress and enrollments
• All associated files and media

This action is IRREVERSIBLE and will permanently remove everything.

Type "DELETE" to confirm you understand this action cannot be undone.`,
                'I Understand - Delete Forever',
                'Cancel',
                () => {
                    // Proceed with permanent deletion
                    proceedWithPermanentDelete(courseId, courseTitle);
                }
            );
        }
    );
}

// Proceed with permanent deletion
function proceedWithPermanentDelete(courseId, courseTitle) {
    // Show loading state
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
    button.disabled = true;
    
    // Make API call
    fetch('teacher_archive.php?ajax=1', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_permanent&course_id=${courseId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Remove the course card from the page
            const courseCard = button.closest('.group');
            courseCard.style.transition = 'all 0.3s ease';
            courseCard.style.transform = 'scale(0.8)';
            courseCard.style.opacity = '0';
            
            setTimeout(() => {
                courseCard.remove();
                
                // Check if no more courses
                const remainingCourses = document.querySelectorAll('.group');
                if (remainingCourses.length === 0) {
                    // Reload page to show empty state
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            }, 300);
        } else {
            showNotification(data.message || 'Failed to delete course', 'error');
            // Restore button state
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while deleting the course', 'error');
        // Restore button state
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Utility function to escape HTML
function escapeHtml(text) {
    if (typeof text !== 'string') return text === undefined || text === null ? '' : String(text);
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add keyboard navigation support
document.addEventListener('keydown', function(e) {
    // Close dialogs with Escape key
    if (e.key === 'Escape') {
        const dialog = document.getElementById('customConfirmDialog');
        if (dialog && !dialog.classList.contains('hidden')) {
            const cancelBtn = document.getElementById('cancelBtn');
            if (cancelBtn) {
                cancelBtn.click();
            }
        }
    }
});

// Add focus management for accessibility
function manageFocus() {
    const dialog = document.getElementById('customConfirmDialog');
    if (dialog && !dialog.classList.contains('hidden')) {
        const confirmBtn = document.getElementById('confirmBtn');
        if (confirmBtn) {
            confirmBtn.focus();
        }
    }
}

// Initialize focus management
document.addEventListener('DOMContentLoaded', function() {
    // Add focus management when dialogs are shown
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                const dialog = document.getElementById('customConfirmDialog');
                if (dialog && !dialog.classList.contains('hidden')) {
                    setTimeout(manageFocus, 100);
                }
            }
        });
    });
    
    const dialog = document.getElementById('customConfirmDialog');
    if (dialog) {
        observer.observe(dialog, { attributes: true });
    }
});

// Add loading states for better UX
function addLoadingState(element) {
    element.classList.add('loading');
    element.style.pointerEvents = 'none';
}

function removeLoadingState(element) {
    element.classList.remove('loading');
    element.style.pointerEvents = 'auto';
}

// Export functions for global access
window.restoreCourse = restoreCourse;
window.deletePermanently = deletePermanently;
window.showCustomConfirm = showCustomConfirm;
window.showNotification = showNotification;
