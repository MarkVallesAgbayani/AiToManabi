/**
 * Student Password Change Functionality
 * Handles password change form with strength validation and OTP verification for students
 */

class StudentPasswordChangeManager {
    constructor() {
        this.form = document.getElementById('password-change-form');
        this.currentPasswordInput = document.getElementById('current-password');
        this.newPasswordInput = document.getElementById('new-password');
        this.confirmPasswordInput = document.getElementById('confirm-new-password');
        this.submitBtn = document.getElementById('change-password-btn');
        this.submitText = document.getElementById('change-password-text');
        this.passwordTooltip = document.getElementById('password-tooltip');
        this.strengthBar = document.getElementById('strength-bar');
        this.strengthText = document.getElementById('strength-text');
        this.passwordMatch = document.getElementById('password-match');
        
        // OTP Modal elements
        this.otpModal = document.getElementById('otp-modal');
        this.otpForm = document.getElementById('otp-verification-form');
        this.otpCodeInput = document.getElementById('otp-code');
        this.otpError = document.getElementById('otp-error');
        this.verifyOtpBtn = document.getElementById('verify-otp-btn');
        this.resendOtpBtn = document.getElementById('resend-otp-btn');
        this.otpTimer = document.getElementById('otp-timer');
        
        this.otpTimerInterval = null;
        this.otpTimeLeft = 0;
        
        this.init();
    }
    
    init() {
        if (!this.form) {
            console.log('Student password change form not found');
            return;
        }
        
        console.log('Student password change manager initialized');
        
        // Test API connection
        this.testAPIConnection();
        
        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Real-time validation
        if (this.newPasswordInput) {
            this.newPasswordInput.addEventListener('input', () => this.validatePassword());
        }
        if (this.confirmPasswordInput) {
            this.confirmPasswordInput.addEventListener('input', () => this.validatePasswordMatch());
        }
        
        // Password visibility toggles
        this.setupPasswordToggles();
        
        // Tooltip positioning
        this.setupTooltipPositioning();
        
        // OTP modal setup
        this.setupOTPModal();
    }
    
    async testAPIConnection() {
        try {
            const response = await fetch('./api/student_settings.php');
            const result = await response.json();
            console.log('Student API test result:', result);
        } catch (error) {
            console.error('Student API test failed:', error);
        }
    }
    
    setupPasswordToggles() {
        // Add event listeners to password toggle buttons
        const toggleButtons = document.querySelectorAll('[data-input]');
        console.log('Found toggle buttons:', toggleButtons.length);
        
        toggleButtons.forEach(button => {
            const inputId = button.getAttribute('data-input');
            console.log('Setting up toggle for input:', inputId);
            
            // Add event listener
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Toggle button clicked for:', inputId);
                this.togglePasswordVisibility(inputId);
            });
            
            // Add hover tooltip
            button.addEventListener('mouseenter', () => {
                this.showPasswordToggleTooltip(button, inputId);
            });
            
            button.addEventListener('mouseleave', () => {
                this.hidePasswordToggleTooltip();
            });
        });
    }
    
    setupTooltipPositioning() {
        if (this.newPasswordInput && this.passwordTooltip) {
            this.newPasswordInput.addEventListener('focus', () => this.showPasswordTooltip());
            this.newPasswordInput.addEventListener('blur', () => this.hidePasswordTooltip());
            this.newPasswordInput.addEventListener('mouseover', () => this.showPasswordTooltip());
            this.newPasswordInput.addEventListener('mouseout', () => this.hidePasswordTooltip());
        }
    }
    
    togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(inputId + '-icon');
        
        console.log('Toggling visibility for:', inputId, 'Input:', input, 'Icon:', icon);
        
        if (!input || !icon) {
            console.log('Missing input or icon for:', inputId);
            return;
        }
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
            `;
            console.log('Password shown for:', inputId);
        } else {
            input.type = 'password';
            icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            `;
            console.log('Password hidden for:', inputId);
        }
        
        // Update tooltip if it's visible
        const tooltip = document.getElementById('password-toggle-tooltip');
        if (tooltip) {
            const isPassword = input.type === 'password';
            tooltip.textContent = isPassword ? 'Show password' : 'Hide password';
        }
    }
    
    showPasswordTooltip() {
        if (this.passwordTooltip) {
            this.passwordTooltip.classList.remove('hidden');
            this.updatePasswordRequirements();
            
            // Position tooltip relative to input
            const inputRect = this.newPasswordInput.getBoundingClientRect();
            const tooltipRect = this.passwordTooltip.getBoundingClientRect();
            
            // Position tooltip below the input
            this.passwordTooltip.style.position = 'absolute';
            this.passwordTooltip.style.top = '100%';
            this.passwordTooltip.style.left = '0';
            this.passwordTooltip.style.zIndex = '50';
        }
    }
    
    hidePasswordTooltip() {
        if (this.passwordTooltip) {
            this.passwordTooltip.classList.add('hidden');
        }
    }
    
    showPasswordToggleTooltip(button, inputId) {
        // Remove existing tooltip
        this.hidePasswordToggleTooltip();
        
        const input = document.getElementById(inputId);
        if (!input) return;
        
        const isPassword = input.type === 'password';
        const tooltipText = isPassword ? 'Show password' : 'Hide password';
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.id = 'password-toggle-tooltip';
        tooltip.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-800 rounded shadow-lg pointer-events-none';
        tooltip.textContent = tooltipText;
        
        // Position tooltip
        const buttonRect = button.getBoundingClientRect();
        tooltip.style.position = 'fixed';
        tooltip.style.top = (buttonRect.top - 30) + 'px';
        tooltip.style.left = (buttonRect.left + buttonRect.width / 2) + 'px';
        tooltip.style.transform = 'translateX(-50%)';
        
        document.body.appendChild(tooltip);
    }
    
    hidePasswordToggleTooltip() {
        const tooltip = document.getElementById('password-toggle-tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }
    
    setupOTPModal() {
        if (this.otpForm) {
            this.otpForm.addEventListener('submit', (e) => {
                e.preventDefault(); // Prevent form submission
                this.handleOTPVerification(e);
            });
        }
        
        if (this.resendOtpBtn) {
            this.resendOtpBtn.addEventListener('click', () => this.resendOTP());
        }
        
        if (this.verifyOtpBtn) {
            this.verifyOtpBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleOTPVerification(e);
            });
        }
        
        if (this.otpCodeInput) {
            this.otpCodeInput.addEventListener('input', (e) => {
                // Auto-format OTP input
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                if (value.length > 6) value = value.substring(0, 6);
                e.target.value = value;
                
                // Clear any previous error messages when user types
                this.otpError.textContent = '';
            });
        }
    }
    
    showOTPModal() {
        if (this.otpModal) {
            this.otpModal.classList.remove('hidden');
            this.otpCodeInput.focus();
            this.startOTPTimer();
        }
    }
    
    hideOTPModal() {
        if (this.otpModal) {
            this.otpModal.classList.add('hidden');
            this.clearOTPTimer();
            this.otpCodeInput.value = '';
            this.otpError.textContent = '';
        }
    }
    
    startOTPTimer() {
        this.otpTimeLeft = 300; // 5 minutes
        this.updateOTPTimer();
        
        this.otpTimerInterval = setInterval(() => {
            this.otpTimeLeft--;
            this.updateOTPTimer();
            
            if (this.otpTimeLeft <= 0) {
                this.clearOTPTimer();
            }
        }, 1000);
    }
    
    updateOTPTimer() {
        if (this.otpTimer) {
            const minutes = Math.floor(this.otpTimeLeft / 60);
            const seconds = this.otpTimeLeft % 60;
            this.otpTimer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')} remaining`;
            
            if (this.otpTimeLeft <= 0) {
                this.otpTimer.textContent = 'Code expired';
                this.resendOtpBtn.disabled = false;
            } else {
                this.resendOtpBtn.disabled = true;
            }
        }
    }
    
    clearOTPTimer() {
        if (this.otpTimerInterval) {
            clearInterval(this.otpTimerInterval);
            this.otpTimerInterval = null;
        }
    }
    
    async handleOTPVerification(e) {
        e.preventDefault();
        
        const otpCode = this.otpCodeInput.value.trim();
        
        if (!otpCode || otpCode.length !== 6) {
            this.otpError.textContent = 'Please enter a valid 6-digit code';
            return;
        }
        
        this.setOTPLoading(true);
        
        try {
            const response = await fetch('./api/student_verify_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    otp_code: otpCode,
                    type: 'password_change'
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned invalid response format');
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess(result.message || 'Password change verified successfully!');
                this.hideOTPModal();
                this.form.reset();
                this.strengthBar.style.width = '0%';
                this.strengthText.textContent = '';
                this.passwordMatch.textContent = '';
            } else {
                this.otpError.textContent = result.error || 'Invalid verification code';
            }
        } catch (error) {
            console.error('Student OTP verification error:', error);
            this.otpError.textContent = 'Network error. Please try again.';
        } finally {
            this.setOTPLoading(false);
        }
    }
    
    async resendOTP() {
        this.setOTPLoading(true);
        
        try {
            const response = await fetch('./api/student_resend_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'password_change'
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned invalid response format');
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Verification code resent successfully!');
                this.startOTPTimer();
            } else {
                this.otpError.textContent = result.error || 'Failed to resend code';
            }
        } catch (error) {
            console.error('Student resend OTP error:', error);
            this.otpError.textContent = 'Network error. Please try again.';
        } finally {
            this.setOTPLoading(false);
        }
    }
    
    setOTPLoading(loading) {
        if (this.verifyOtpBtn) {
            this.verifyOtpBtn.disabled = loading;
            if (loading) {
                this.verifyOtpBtn.innerHTML = `
                    <svg class="w-4 h-4 animate-spin mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Verifying...
                `;
            } else {
                this.verifyOtpBtn.innerHTML = 'Verify & Complete';
            }
        }
    }
    
    updatePasswordRequirements() {
        const password = this.newPasswordInput.value;
        const requirements = {
            length: password.length >= 12,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^a-zA-Z0-9]/.test(password)
        };
        
        // Update requirement indicators
        Object.keys(requirements).forEach(req => {
            const element = document.getElementById(`${req}-check-tooltip`);
            if (element) {
                element.className = `requirement ${requirements[req] ? 'met' : 'unmet'} text-xs`;
            }
        });
    }
    
    validatePassword() {
        const password = this.newPasswordInput.value;
        const strength = this.calculatePasswordStrength(password);
        
        // Update strength bar
        this.strengthBar.style.width = strength.percentage + '%';
        this.strengthBar.className = `strength-${strength.level} h-2 rounded-full transition-all duration-300`;
        this.strengthText.textContent = strength.text;
        this.strengthText.className = `text-xs block mt-1 text-${strength.color}`;
        
        // Update requirements
        this.updatePasswordRequirements();
        
        // Validate password match if confirm password is filled
        if (this.confirmPasswordInput.value) {
            this.validatePasswordMatch();
        }
        
        return strength.score >= 3;
    }
    
    calculatePasswordStrength(password) {
        let score = 0;
        const checks = {
            length: password.length >= 12,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^a-zA-Z0-9]/.test(password)
        };
        
        score = Object.values(checks).filter(Boolean).length;
        
        let level, text, color, percentage;
        
        if (score < 2) {
            level = 'weak';
            text = 'Very Weak';
            color = 'red-500';
            percentage = 20;
        } else if (score < 3) {
            level = 'weak';
            text = 'Weak';
            color = 'red-500';
            percentage = 40;
        } else if (score < 4) {
            level = 'medium';
            text = 'Medium';
            color = 'yellow-500';
            percentage = 60;
        } else if (score < 5) {
            level = 'strong';
            text = 'Strong';
            color = 'blue-500';
            percentage = 80;
        } else {
            level = 'very-strong';
            text = 'Very Strong';
            color = 'green-500';
            percentage = 100;
        }
        
        return { score, level, text, color, percentage };
    }
    
    validatePasswordMatch() {
        const password = this.newPasswordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;
        
        if (confirmPassword && password !== confirmPassword) {
            this.passwordMatch.textContent = 'Passwords do not match';
            this.passwordMatch.className = 'text-xs block mt-1 text-red-500';
            return false;
        } else if (confirmPassword && password === confirmPassword) {
            this.passwordMatch.textContent = 'Passwords match';
            this.passwordMatch.className = 'text-xs block mt-1 text-green-500';
            return true;
        } else {
            this.passwordMatch.textContent = '';
            this.passwordMatch.className = 'text-xs block mt-1';
            return true;
        }
    }
    
    clearErrors() {
        const errorElements = [
            'current-password-error',
            'new-password-error',
            'confirm-password-error'
        ];
        
        errorElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = '';
            }
        });
    }
    
    showError(field, message) {
        const errorElement = document.getElementById(`${field}-error`);
        if (errorElement) {
            errorElement.textContent = message;
        }
    }
    
    setLoading(loading) {
        if (loading) {
            this.submitBtn.disabled = true;
            this.submitText.textContent = 'Changing Password...';
            this.submitBtn.innerHTML = `
                <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Changing Password...</span>
            `;
        } else {
            this.submitBtn.disabled = false;
            this.submitText.textContent = 'Change Password';
            this.submitBtn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
                <span>Change Password</span>
            `;
        }
    }
    
    showSuccess(message) {
        // Create success alert
        const alert = document.createElement('div');
        alert.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
        alert.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>${message}</span>
        `;
        
        document.body.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }
    
    showErrorAlert(message) {
        // Create error alert
        const alert = document.createElement('div');
        alert.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
        alert.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span>${message}</span>
        `;
        
        document.body.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        this.clearErrors();
        
        // Get form data
        const formData = {
            current_password: this.currentPasswordInput.value,
            new_password: this.newPasswordInput.value,
            confirm_password: this.confirmPasswordInput.value
        };
        
        // Client-side validation
        if (!formData.current_password) {
            this.showError('current-password', 'Current password is required');
            return;
        }
        
        if (!formData.new_password) {
            this.showError('new-password', 'New password is required');
            return;
        }
        
        if (!formData.confirm_password) {
            this.showError('confirm-password', 'Please confirm your new password');
            return;
        }
        
        if (!this.validatePassword()) {
            this.showError('new-password', 'Password does not meet strength requirements');
            return;
        }
        
        if (!this.validatePasswordMatch()) {
            this.showError('confirm-password', 'Passwords do not match');
            return;
        }
        
        // Submit form
        this.setLoading(true);
        
        try {
            console.log('Sending student password change request:', formData);
            
            const response = await fetch('./api/student_settings.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned invalid response format');
            }
            
            const result = await response.json();
            console.log('Response result:', result);
            
            if (result.success) {
                this.showSuccess(result.message);
                
                // If OTP was sent, show OTP modal
                if (result.otp_sent) {
                    setTimeout(() => {
                        this.showOTPModal();
                    }, 1000);
                } else {
                    // If no OTP needed, reset form
                    this.form.reset();
                    this.strengthBar.style.width = '0%';
                    this.strengthText.textContent = '';
                    this.passwordMatch.textContent = '';
                }
            } else {
                this.showErrorAlert(result.error || 'Failed to change password');
            }
        } catch (error) {
            console.error('Student password change error:', error);
            this.showErrorAlert('Network error. Please try again. Error: ' + error.message);
        } finally {
            this.setLoading(false);
        }
    }
}

// Global functions for backward compatibility (if needed)
function togglePasswordVisibility(inputId) {
    if (window.studentPasswordChangeManager) {
        window.studentPasswordChangeManager.togglePasswordVisibility(inputId);
    }
}

function showPasswordTooltip() {
    if (window.studentPasswordChangeManager) {
        window.studentPasswordChangeManager.showPasswordTooltip();
    }
}

function hidePasswordTooltip() {
    if (window.studentPasswordChangeManager) {
        window.studentPasswordChangeManager.hidePasswordTooltip();
    }
}

function hideOTPModal() {
    if (window.studentPasswordChangeManager) {
        window.studentPasswordChangeManager.hideOTPModal();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.studentPasswordChangeManager = new StudentPasswordChangeManager();
});

// CSS for password strength meter
const style = document.createElement('style');
style.textContent = `
    .password-strength-meter {
        background-color: #e5e7eb;
        border-radius: 0.5rem;
        height: 0.5rem;
        overflow: hidden;
    }
    
    .strength-weak {
        background-color: #ef4444;
    }
    
    .strength-medium {
        background-color: #f59e0b;
    }
    
    .strength-strong {
        background-color: #3b82f6;
    }
    
    .strength-very-strong {
        background-color: #10b981;
    }
    
    .requirement.met {
        color: #10b981;
    }
    
    .requirement.unmet {
        color: #ef4444;
    }
    
    #password-tooltip {
        position: absolute;
        z-index: 50;
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        padding: 1rem;
        margin-top: 0.5rem;
        width: 20rem;
    }
`;
document.head.appendChild(style);
