/**
 * Admin Password Change Functionality with OTP
 */

class AdminPasswordChangeManager {
    constructor() {
        this.currentPasswordInput = document.getElementById('current-password');
        this.newPasswordInput = document.getElementById('new-password');
        this.confirmPasswordInput = document.getElementById('confirm-password');
        this.strengthBar = document.getElementById('strength-bar');
        this.strengthText = document.getElementById('strength-text');
        this.passwordTooltip = document.getElementById('password-tooltip');
        
        // OTP Modal elements
        this.otpModal = document.getElementById('otp-modal');
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
        console.log('Admin password change manager initialized');
        
        // Password strength validation
        if (this.newPasswordInput) {
            this.newPasswordInput.addEventListener('input', () => this.validatePassword());
            this.newPasswordInput.addEventListener('focus', () => this.showPasswordTooltip());
            this.newPasswordInput.addEventListener('blur', () => this.hidePasswordTooltip());
        }
        
        if (this.confirmPasswordInput) {
            this.confirmPasswordInput.addEventListener('input', () => this.validatePasswordMatch());
        }
        
        // Setup OTP modal
        this.setupOTPModal();
    }
    
    setupOTPModal() {
        if (this.verifyOtpBtn) {
            this.verifyOtpBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleOTPVerification();
            });
        }
        
        if (this.resendOtpBtn) {
            this.resendOtpBtn.addEventListener('click', () => this.resendOTP());
        }
        
        if (this.otpCodeInput) {
            this.otpCodeInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 6) value = value.substring(0, 6);
                e.target.value = value;
                this.otpError.textContent = '';
            });
            
            // Submit on Enter key
            this.otpCodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.handleOTPVerification();
                }
            });
        }
        
        // Close modal on backdrop click
        if (this.otpModal) {
            this.otpModal.addEventListener('click', (e) => {
                if (e.target === this.otpModal) {
                    this.hideOTPModal();
                }
            });
        }
    }
    
    showPasswordTooltip() {
        if (this.passwordTooltip) {
            this.passwordTooltip.classList.remove('hidden');
            this.updatePasswordRequirements();
        }
    }
    
    hidePasswordTooltip() {
        if (this.passwordTooltip) {
            setTimeout(() => {
                this.passwordTooltip.classList.add('hidden');
            }, 200);
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
        
        this.strengthBar.style.width = strength.percentage + '%';
        this.strengthBar.className = `strength-${strength.level} h-2 rounded-full transition-all duration-300`;
        this.strengthText.textContent = strength.text;
        this.strengthText.className = `text-xs block mt-1 text-${strength.color}`;
        
        this.updatePasswordRequirements();
        
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
        
        if (score < 3) {
            level = 'weak';
            text = 'Weak';
            color = 'red-600';
            percentage = 40;
        } else if (score < 4) {
            level = 'medium';
            text = 'Medium';
            color = 'yellow-600';
            percentage = 60;
        } else if (score < 5) {
            level = 'strong';
            text = 'Strong';
            color = 'green-600';
            percentage = 80;
        } else {
            level = 'strong';
            text = 'Very Strong';
            color = 'green-600';
            percentage = 100;
        }
        
        return { score, level, text, color, percentage };
    }
    
    validatePasswordMatch() {
        const password = this.newPasswordInput.value;
        const confirmPassword = this.confirmPasswordInput.value;
        const matchElement = document.getElementById('password-match');
        
        if (!matchElement) return true;
        
        if (confirmPassword && password !== confirmPassword) {
            matchElement.textContent = 'Passwords do not match';
            matchElement.className = 'text-xs block mt-1 text-red-600';
            return false;
        } else if (confirmPassword && password === confirmPassword) {
            matchElement.textContent = 'Passwords match';
            matchElement.className = 'text-xs block mt-1 text-green-600';
            return true;
        } else {
            matchElement.textContent = '';
            return true;
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
    
    async handleOTPVerification() {
        const otpCode = this.otpCodeInput.value.trim();
        
        if (!otpCode || otpCode.length !== 6) {
            this.otpError.textContent = 'Please enter a valid 6-digit code';
            return;
        }
        
        this.setOTPLoading(true);
        
        try {
            const response = await fetch('api/verify_otp_admin.php', {  // ✅ ADMIN SPECIFIC
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    otp_code: otpCode,
                    type: 'password_change'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast(result.message || 'Password changed successfully!', 'success');
                this.hideOTPModal();
                
                // Reset form
                if (this.currentPasswordInput) this.currentPasswordInput.value = '';
                if (this.newPasswordInput) this.newPasswordInput.value = '';
                if (this.confirmPasswordInput) this.confirmPasswordInput.value = '';
                this.strengthBar.style.width = '0%';
                this.strengthText.textContent = '';
                const matchElement = document.getElementById('password-match');
                if (matchElement) matchElement.textContent = '';
            } else {
                this.otpError.textContent = result.error || 'Invalid verification code';
            }
        } catch (error) {
            console.error('OTP verification error:', error);
            this.otpError.textContent = 'Network error. Please try again.';
        } finally {
            this.setOTPLoading(false);
        }
    }
    
    async resendOTP() {
        this.setOTPLoading(true);
        
        try {
            const response = await fetch('api/resend_otp_admin.php', {  // ✅ ADMIN SPECIFIC
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'password_change'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('Verification code resent successfully!', 'success');
                this.startOTPTimer();
            } else {
                this.otpError.textContent = result.error || 'Failed to resend code';
            }
        } catch (error) {
            console.error('Resend OTP error:', error);
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
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Verifying...
                `;
            } else {
                this.verifyOtpBtn.innerHTML = 'Verify & Complete';
            }
        }
    }
    
    showToast(message, type = 'info') {
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = 'toast-notification fixed top-4 right-4 z-[10000] max-w-md w-full transform transition-all duration-300';
        toast.style.transform = 'translateX(100%)';
        
        let bgColor = 'bg-blue-500';
        let icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
        
        if (type === 'success') {
            bgColor = 'bg-green-500';
            icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
        } else if (type === 'error') {
            bgColor = 'bg-red-500';
            icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
        }
        
        toast.innerHTML = `
            <div class="${bgColor} text-white rounded-xl shadow-2xl p-4 flex items-center gap-3">
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    ${icon}
                </svg>
                <span class="font-medium flex-1">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => toast.style.transform = 'translateX(0)', 10);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }
        }, 4000);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.adminPasswordChangeManager = new AdminPasswordChangeManager();
});

// CSS
const style = document.createElement('style');
style.textContent = `
    .strength-weak { background-color: #ef4444; }
    .strength-medium { background-color: #f59e0b; }
    .strength-strong { background-color: #10b981; }
    .requirement.met { color: #10b981; }
    .requirement.unmet { color: #6b7280; }
`;
document.head.appendChild(style);
