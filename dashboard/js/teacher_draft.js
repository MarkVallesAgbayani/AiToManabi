/**
 * Teacher Draft Course Management JavaScript
 * Handles draft course operations like publish, delete, and duplicate
 */

class DraftManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupDropdowns();
    }

    bindEvents() {
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.draft-dropdown')) {
                this.closeAllDropdowns();
            }
        });

        // Handle keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllDropdowns();
            }
        });
    }

    setupDropdowns() {
        // Initialize Alpine.js data for dropdowns
        if (typeof Alpine !== 'undefined') {
            Alpine.data('draftDropdown', () => ({
                open: false,
                toggle() {
                    this.open = !this.open;
                },
                close() {
                    this.open = false;
                }
            }));
        }
    }

    closeAllDropdowns() {
        // Close all draft dropdowns
        const dropdowns = document.querySelectorAll('[x-data*="draftDropdown"]');
        dropdowns.forEach(dropdown => {
            if (dropdown._x_dataStack && dropdown._x_dataStack[0]) {
                dropdown._x_dataStack[0].open = false;
            }
        });
    }

    async publishDraft(courseId, courseTitle) {
        // Show custom confirmation dialog
        showCustomConfirm(`Are you sure you want to publish "${courseTitle}"? This will make it available to students.`, () => {
            this.proceedWithPublish(courseId, courseTitle);
        });
    }
    
    async proceedWithPublish(courseId, courseTitle) {
        
        try {
            this.showLoading(courseId);
            
            const response = await fetch('teacher_drafts.php?ajax=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'publish',
                    course_id: courseId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`"${courseTitle}" published successfully!`, 'success');
                // Reload page after a short delay to show the notification
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.showNotification(`Error publishing course: ${data.message}`, 'error');
            }
        } catch (error) {
            console.error('Error publishing draft:', error);
            this.showNotification('An error occurred while publishing the course.', 'error');
        } finally {
            this.hideLoading(courseId);
        }
    }

    async archiveDraft(courseId, courseTitle) {
        // Show custom confirmation dialog
        showCustomConfirm(`Are you sure you want to archive "${courseTitle}"? This will hide it from your drafts but you can restore it anytime from the Archived page.`, () => {
            this.proceedWithArchive(courseId, courseTitle);
        });
    }
    
    async proceedWithArchive(courseId, courseTitle) {

        try {
            this.showLoading(courseId);
            
            const response = await fetch('teacher_drafts.php?ajax=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'archive',
                    course_id: courseId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`"${courseTitle}" archived successfully!`, 'success');
                // Remove the draft item from the UI
                this.removeDraftItem(courseId);
                // Update draft count
                this.updateDraftCount();
            } else {
                this.showNotification(`Error archiving course: ${data.message}`, 'error');
            }
        } catch (error) {
            console.error('Error archiving draft:', error);
            this.showNotification('An error occurred while archiving the course.', 'error');
        } finally {
            this.hideLoading(courseId);
        }
    }

    async duplicateDraft(courseId, courseTitle) {
        // Show custom confirmation dialog
        showCustomConfirm(`Are you sure you want to duplicate "${courseTitle}"? This will create a copy of the course.`, () => {
            this.proceedWithDuplicate(courseId, courseTitle);
        });
    }
    
    async proceedWithDuplicate(courseId, courseTitle) {
        
        try {
            this.showLoading(courseId);
            
            const response = await fetch('teacher_drafts.php?ajax=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'duplicate',
                    course_id: courseId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`"${data.data.title}" created successfully!`, 'success');
                // Reload page to show the new draft
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.showNotification(`Error duplicating course: ${data.message}`, 'error');
            }
        } catch (error) {
            console.error('Error duplicating draft:', error);
            this.showNotification('An error occurred while duplicating the course.', 'error');
        } finally {
            this.hideLoading(courseId);
        }
    }

    showLoading(courseId) {
        const draftItem = document.querySelector(`[data-course-id="${courseId}"]`);
        if (draftItem) {
            draftItem.classList.add('draft-loading');
        }
    }

    hideLoading(courseId) {
        const draftItem = document.querySelector(`[data-course-id="${courseId}"]`);
        if (draftItem) {
            draftItem.classList.remove('draft-loading');
        }
    }

    removeDraftItem(courseId) {
        const draftItem = document.querySelector(`[data-course-id="${courseId}"]`);
        if (draftItem) {
            draftItem.style.transition = 'all 0.3s ease-out';
            draftItem.style.transform = 'translateX(100%)';
            draftItem.style.opacity = '0';
            setTimeout(() => {
                draftItem.remove();
            }, 300);
        }
    }

    updateDraftCount() {
        const draftCount = document.querySelectorAll('.draft-item').length;
        const countElement = document.querySelector('.draft-count');
        if (countElement) {
            countElement.textContent = draftCount;
            if (draftCount === 0) {
                // Hide the dropdown button if no drafts
                const dropdownButton = document.querySelector('.draft-dropdown-button');
                if (dropdownButton) {
                    dropdownButton.style.display = 'none';
                }
            }
        }
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Add icon based on type
        const icon = this.getNotificationIcon(type);
        if (icon) {
            notification.innerHTML = `${icon} ${message}`;
        }
        
        document.body.appendChild(notification);
        
        // Animate in
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });
        
        // Remove after 4 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 4000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
            error: '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
            info: '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        };
        return icons[type] || '';
    }

    // Utility method to format dates
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // Method to refresh draft list (useful for real-time updates)
    async refreshDraftList() {
        try {
            const response = await fetch(`courses_by_category.php?category_id=${this.getCurrentCategoryId()}&ajax=draft_list`);
            const data = await response.json();
            
            if (data.success) {
                this.updateDraftList(data.drafts);
            }
        } catch (error) {
            console.error('Error refreshing draft list:', error);
        }
    }

    getCurrentCategoryId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('category_id');
    }

    updateDraftList(drafts) {
        const draftContainer = document.querySelector('.draft-list');
        if (!draftContainer) return;

        if (drafts.length === 0) {
            draftContainer.innerHTML = `
                <div class="draft-empty-state">
                    <div class="draft-empty-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500">No draft courses found</p>
                </div>
            `;
            return;
        }

        draftContainer.innerHTML = drafts.map(draft => `
            <div class="draft-item" data-course-id="${draft.id}">
                <div class="flex-1">
                    <h4 class="draft-item-title">${this.escapeHtml(draft.title)}</h4>
                    <p class="draft-item-date">Created: ${this.formatDate(draft.created_at)}</p>
                </div>
                <div class="draft-actions">
                    <a href="teacher_create_module.php?id=${draft.id}" 
                       class="draft-action-btn draft-edit-btn">
                        Edit
                    </a>
                    <button onclick="draftManager.publishDraft(${draft.id}, '${this.escapeHtml(draft.title)}')" 
                            class="draft-action-btn draft-publish-btn">
                        Publish
                    </button>
                    <button onclick="draftManager.archiveDraft(${draft.id}, '${this.escapeHtml(draft.title)}')" 
                            class="draft-action-btn draft-archive-btn">
                        Archive
                    </button>
                </div>
            </div>
        `).join('');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global functions for backward compatibility
function publishDraft(courseId) {
    if (window.draftManager) {
        const courseTitle = document.querySelector(`[data-course-id="${courseId}"] .draft-item-title`)?.textContent || 'Course';
        window.draftManager.publishDraft(courseId, courseTitle);
    }
}

function archiveDraft(courseId, courseTitle) {
    if (window.draftManager) {
        window.draftManager.archiveDraft(courseId, courseTitle);
    }
}

function duplicateDraft(courseId) {
    if (window.draftManager) {
        const courseTitle = document.querySelector(`[data-course-id="${courseId}"] .draft-item-title`)?.textContent || 'Course';
        window.draftManager.duplicateDraft(courseId, courseTitle);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.draftManager = new DraftManager();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DraftManager;
}
