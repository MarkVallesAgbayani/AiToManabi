/**
 * Session Timeout Manager
 * Handles user inactivity detection and session management
 */
class SessionTimeoutManager {
    constructor(options = {}) {
        // Configuration
        this.timeoutDuration = options.timeoutDuration || 15 * 60 * 1000; // 15 minutes in milliseconds
        this.warningTime = options.warningTime || 3 * 60 * 1000; // 3 minutes before timeout
        this.checkInterval = options.checkInterval || 30 * 1000; // Check every 30 seconds
        this.logoutEndpoint = options.logoutEndpoint || 'logout.php';
        this.extendEndpoint = options.extendEndpoint || 'extend_session.php';
        
        // State
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.isLoggingOut = false;
        this.checkTimer = null;
        this.warningTimer = null;
        this.logoutTimer = null;
        
        // DOM elements
        this.warningModal = null;
        
        // Bind methods
        this.handleActivity = this.handleActivity.bind(this);
        this.checkInactivity = this.checkInactivity.bind(this);
        this.showWarning = this.showWarning.bind(this);
        this.hideWarning = this.hideWarning.bind(this);
        this.extendSession = this.extendSession.bind(this);
        this.logout = this.logout.bind(this);
        
        this.init();
    }
    
    init() {
        this.createWarningModal();
        this.attachEventListeners();
        this.startInactivityCheck();
        
        console.log('Session timeout manager initialized');
    }
    
    createWarningModal() {
        // Create modal HTML
        const modalHTML = `
            <div id="sessionTimeoutModal" class="session-timeout-modal hidden">
                <div class="session-timeout-overlay"></div>
                <div class="session-timeout-content">
                    <div class="session-timeout-header">
                        <div class="session-timeout-icon">
                            <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <h3 class="session-timeout-title">Session Timeout Warning</h3>
                    </div>
                    <div class="session-timeout-body">
                        <p class="session-timeout-message">
                            You have been inactive for a while. Your session will expire in 
                            <span id="sessionCountdown" class="font-bold text-red-600">3:00</span> minutes.
                        </p>
                        <p class="session-timeout-submessage">
                            Click "Stay Logged In" to continue your session, or you will be automatically logged out.
                        </p>
                    </div>
                    <div class="session-timeout-actions">
                        <button id="stayLoggedInBtn" class="session-timeout-btn session-timeout-btn-primary">
                            Stay Logged In
                        </button>
                        <button id="logoutNowBtn" class="session-timeout-btn session-timeout-btn-secondary">
                            Logout Now
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.warningModal = document.getElementById('sessionTimeoutModal');
        
        // Add event listeners to modal buttons
        document.getElementById('stayLoggedInBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('Stay Logged In button clicked');
            this.extendSession();
        });
        document.getElementById('logoutNowBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('Logout Now button clicked');
            
            // Simple test - just redirect immediately
            console.log('Redirecting to login page...');
            window.location.href = 'login.php?timeout=1&message=You have been logged out.';
        });
        
        // Prevent modal clicks from triggering activity
        this.warningModal.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Add CSS styles
        this.addModalStyles();
    }
    
    addModalStyles() {
        const styles = `
            <style id="session-timeout-styles">
                .session-timeout-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .session-timeout-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(4px);
                }
                
                .session-timeout-content {
                    position: relative;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                    max-width: 500px;
                    width: 90%;
                    margin: 0 auto;
                    animation: sessionTimeoutSlideIn 0.3s ease-out;
                }
                
                .dark .session-timeout-content {
                    background: #27272a;
                    color: white;
                }
                
                .session-timeout-header {
                    display: flex;
                    align-items: center;
                    padding: 24px 24px 16px 24px;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .dark .session-timeout-header {
                    border-bottom-color: #3f3f46;
                }
                
                .session-timeout-icon {
                    margin-right: 12px;
                }
                
                .session-timeout-title {
                    font-size: 20px;
                    font-weight: 600;
                    color: #1f2937;
                    margin: 0;
                }
                
                .dark .session-timeout-title {
                    color: white;
                }
                
                .session-timeout-body {
                    padding: 16px 24px;
                }
                
                .session-timeout-message {
                    font-size: 16px;
                    color: #374151;
                    margin-bottom: 8px;
                }
                
                .dark .session-timeout-message {
                    color: #d1d5db;
                }
                
                .session-timeout-submessage {
                    font-size: 14px;
                    color: #6b7280;
                    margin: 0;
                }
                
                .dark .session-timeout-submessage {
                    color: #9ca3af;
                }
                
                .session-timeout-actions {
                    display: flex;
                    gap: 12px;
                    padding: 16px 24px 24px 24px;
                    justify-content: flex-end;
                }
                
                .session-timeout-btn {
                    padding: 10px 20px;
                    border-radius: 8px;
                    font-weight: 500;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    border: none;
                }
                
                .session-timeout-btn-primary {
                    background: #ef4444;
                    color: white;
                }
                
                .session-timeout-btn-primary:hover {
                    background: #dc2626;
                }
                
                .session-timeout-btn-secondary {
                    background: #6b7280;
                    color: white;
                }
                
                .session-timeout-btn-secondary:hover {
                    background: #4b5563;
                }
                
                @keyframes sessionTimeoutSlideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-20px) scale(0.95);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }
                
                .session-timeout-modal.hidden {
                    display: none !important;
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', styles);
    }
    
    attachEventListeners() {
        // Mouse and keyboard events
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(eventType => {
            document.addEventListener(eventType, (event) => {
                this.handleActivity(event);
            }, true);
        });
        
        // Page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.handleActivity();
            }
        });
        
        // Window focus/blur
        window.addEventListener('focus', () => this.handleActivity());
        window.addEventListener('blur', () => this.handleActivity());
    }
    
    handleActivity(event) {
        // Don't reset activity if warning popup is visible and user is just moving mouse
        if (this.warningShown && event && event.type === 'mousemove') {
            return; // Ignore mouse movements when warning is shown
        }
        
        // Don't reset activity if the event target is within the modal
        if (this.warningShown && event && event.target && this.warningModal.contains(event.target)) {
            return; // Ignore any activity within the modal
        }
        
        this.lastActivity = Date.now();
        
        // Hide warning if it's shown and reset timers (but not for mouse movements or modal interactions)
        if (this.warningShown && (!event || (event.type !== 'mousemove' && !this.warningModal.contains(event.target)))) {
            this.hideWarning();
        }
    }
    
    startInactivityCheck() {
        this.checkTimer = setInterval(this.checkInactivity, this.checkInterval);
    }
    
    checkInactivity() {
        const now = Date.now();
        const timeSinceActivity = now - this.lastActivity;
        const timeUntilTimeout = this.timeoutDuration - timeSinceActivity;
        const timeUntilWarning = this.warningTime - timeSinceActivity;
        
        // Show warning if time is up and not already shown
        if (timeUntilWarning <= 0 && !this.warningShown && !this.isLoggingOut) {
            this.showWarning();
        }
        
        // Auto logout if timeout reached
        if (timeUntilTimeout <= 0 && !this.isLoggingOut) {
            this.logout();
        }
    }
    
    showWarning() {
        this.warningShown = true;
        this.warningModal.classList.remove('hidden');
        
        // Start countdown
        this.startCountdown();
        
        // Set auto logout timer
        const remainingTime = this.timeoutDuration - (Date.now() - this.lastActivity);
        this.logoutTimer = setTimeout(() => {
            if (this.warningShown) {
                this.logout();
            }
        }, remainingTime);
    }
    
    startCountdown() {
        const countdownElement = document.getElementById('sessionCountdown');
        const updateCountdown = () => {
            if (!this.warningShown) return;
            
            const remainingTime = this.timeoutDuration - (Date.now() - this.lastActivity);
            
            if (remainingTime <= 0) {
                this.logout();
                return;
            }
            
            const minutes = Math.floor(remainingTime / 60000);
            const seconds = Math.floor((remainingTime % 60000) / 1000);
            
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            setTimeout(updateCountdown, 1000);
        };
        
        updateCountdown();
    }
    
    hideWarning() {
        this.warningShown = false;
        this.warningModal.classList.add('hidden');
        
        if (this.logoutTimer) {
            clearTimeout(this.logoutTimer);
            this.logoutTimer = null;
        }
    }
    
    async extendSession() {
        try {
            const response = await fetch(this.extendEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                this.hideWarning();
                this.lastActivity = Date.now();
                console.log('Session extended successfully');
            } else {
                throw new Error('Failed to extend session');
            }
        } catch (error) {
            console.error('Error extending session:', error);
            // Still hide warning and reset activity to give user a chance
            this.hideWarning();
            this.lastActivity = Date.now();
        }
    }
    
    async logout() {
        console.log('Logout method called, isLoggingOut:', this.isLoggingOut);
        if (this.isLoggingOut) return;
        
        this.isLoggingOut = true;
        console.log('Starting logout process...');
        this.hideWarning();
        
        // Clear all timers
        if (this.checkTimer) {
            clearInterval(this.checkTimer);
        }
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
        }
        if (this.logoutTimer) {
            clearTimeout(this.logoutTimer);
        }
        
        try {
            // Call logout endpoint
            const response = await fetch(this.logoutEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            console.log('Logout response:', response.status, response.statusText);
            
            // Redirect to login page with message
            console.log('Redirecting to login page...');
            window.location.href = 'login.php?timeout=1&message=' + encodeURIComponent('Your session has expired due to inactivity. Please log in again.');
            
        } catch (error) {
            console.error('Error during logout:', error);
            // Still redirect even if logout call fails
            window.location.href = 'login.php?timeout=1&message=Your session has expired due to inactivity. Please log in again.';
        }
    }
    
    destroy() {
        // Remove event listeners
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(eventType => {
            document.removeEventListener(eventType, this.handleActivity, true);
        });
        
        // Clear timers
        if (this.checkTimer) {
            clearInterval(this.checkTimer);
        }
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
        }
        if (this.logoutTimer) {
            clearTimeout(this.logoutTimer);
        }
        
        // Remove modal
        if (this.warningModal) {
            this.warningModal.remove();
        }
        
        // Remove styles
        const styles = document.getElementById('session-timeout-styles');
        if (styles) {
            styles.remove();
        }
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize for authenticated users
    if (typeof window.sessionTimeoutManager === 'undefined') {
        window.sessionTimeoutManager = new SessionTimeoutManager({
            timeoutDuration: 15 * 60 * 1000, // 15 minutes
            warningTime: 3 * 60 * 1000, // 3 minutes before timeout
            logoutEndpoint: 'logout.php',
            extendEndpoint: 'extend_session.php'
        });
    }
});
