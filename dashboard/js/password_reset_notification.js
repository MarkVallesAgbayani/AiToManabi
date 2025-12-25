/**
 * Password Reset Notification Modal
 * Reusable component for showing password reset notifications to users
 */

class PasswordResetNotification {
    constructor(userRole, settingsUrl) {
        this.userRole = userRole;
        this.settingsUrl = settingsUrl;
        this.modalShown = false;
    }

    /**
     * Check if user needs to see password reset notification
     */
    async checkPasswordResetNotification() {
        try {
            const response = await fetch('api/check_password_reset_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error('Failed to check notification status');
            }

            const data = await response.json();
            
            if (data.needs_notification) {
                this.showNotificationModal(data.reset_info);
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Error checking password reset notification:', error);
            return false;
        }
    }

    /**
     * Show the password reset notification modal
     */
    showNotificationModal(resetInfo) {
        if (this.modalShown) return;
        this.modalShown = true;

        const modalContent = this.generateModalContent(resetInfo);
        
        Swal.fire({
            title: 'Password Reset',
            html: modalContent,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: 'Change Password',
            cancelButtonText: 'Later',
            allowOutsideClick: true,
            allowEscapeKey: true,
            focusConfirm: false,
            width: '600px',
            padding: '2rem',
            customClass: {
                popup: 'modern-rectangle-modal',
                title: 'modal-title',
                htmlContainer: 'modal-content',
                confirmButton: 'modal-confirm-btn',
                cancelButton: 'modal-cancel-btn'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            },
            didOpen: () => {
                // Apply custom styles
                const style = document.createElement('style');
                style.innerHTML = `
                    .modern-rectangle-modal {
                        border-radius: 16px !important;
                        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
                    }
                    .modal-title {
                        font-size: 1.5rem !important;
                        font-weight: 600 !important;
                        margin-bottom: 1rem !important;
                    }
                    .modal-content {
                        text-align: left !important;
                    }
                    .modal-confirm-btn,
                    .modal-cancel-btn {
                        border-radius: 8px !important;
                        padding: 0.625rem 1.5rem !important;
                        font-weight: 500 !important;
                    }
                `;
                document.head.appendChild(style);
            }
        }).then((result) => {
            this.modalShown = false;
            
            if (result.isConfirmed) {
                // User clicked "Change Password" - mark as shown and redirect
                this.markNotificationAsShown();
                window.location.href = this.settingsUrl;
            } else {
                // User clicked "Later" - mark as shown for this session only
                this.markNotificationAsShown();
            }
        });
    }

    /**
     * Generate modal content based on user role
     */
    generateModalContent(resetInfo) {
        const roleSpecificInfo = this.getRoleSpecificInfo();
        const resetDate = new Date(resetInfo.reset_date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="flex-shrink: 0;">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </div>
                </div>
                <div style="flex: 1;">
                    <p style="color: #374151; font-size: 0.95rem; margin: 0 0 0.5rem 0; line-height: 1.6;">
                        Your ${roleSpecificInfo.roleName} password was reset by an administrator on <strong>${resetDate}</strong>.
                    </p>
                    <p style="color: #6b7280; font-size: 0.875rem; margin: 0 0 0.25rem 0;">
                        Please change your password for security.
                    </p>
                    <p style="color: #9ca3af; font-size: 0.75rem; margin: 0;">
                        This notification will show until you change your password.
                    </p>
                </div>
            </div>
        `;
    }

    /**
     * Get role-specific information and instructions
     */
    getRoleSpecificInfo() {
        switch (this.userRole) {
            case 'admin':
                return {
                    roleName: 'administrator account',
                    instructions: [
                        'Your administrator account password has been reset',
                        'Please create a new secure password as soon as possible',
                        'Use a strong password with at least 8 characters',
                        'Include uppercase, lowercase, numbers, and special characters'
                    ],
                    recommendation: 'Click "Go to Settings" to change your password immediately for enhanced security.'
                };
            
            case 'teacher':
                return {
                    roleName: 'teacher account',
                    instructions: [
                        'Your teacher account password has been reset by an administrator',
                        'Please create a new secure password as soon as possible',
                        'Use a strong password with at least 8 characters',
                        'Include uppercase, lowercase, numbers, and special characters'
                    ],
                    recommendation: 'Click "Go to Settings" to change your password and secure your account.'
                };
            
            case 'student':
                return {
                    roleName: 'student account',
                    instructions: [
                        'Your student account password has been reset by an administrator',
                        'Please create a new secure password as soon as possible',
                        'Use a strong password with at least 8 characters',
                        'Include uppercase, lowercase, numbers, and special characters'
                    ],
                    recommendation: 'Click "Go to Settings" to change your password and continue your learning journey securely.'
                };
            
            default:
                return {
                    roleName: 'account',
                    instructions: [
                        'Your account password has been reset by an administrator',
                        'Please create a new secure password as soon as possible',
                        'Use a strong password with at least 8 characters'
                    ],
                    recommendation: 'Click "Go to Settings" to change your password.'
                };
        }
    }

    /**
     * Mark notification as shown in database and session
     */
    markNotificationAsShown() {
        // Mark in session storage to prevent showing again this session
        sessionStorage.setItem('password_reset_notification_shown', 'true');
        
        // Also mark in database so it doesn't show again
        fetch('api/mark_password_reset_notification_shown.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        }).catch(error => {
            console.error('Error marking notification as shown:', error);
        });
    }

    /**
     * Initialize the notification check
     */
    async init() {
        if (sessionStorage.getItem('password_reset_notification_shown')) {
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.checkPasswordResetNotification());
        } else {
            await this.checkPasswordResetNotification();
        }
    }
}

// Export for use in other files
window.PasswordResetNotification = PasswordResetNotification;
