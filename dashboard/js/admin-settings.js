/**
 * Admin Settings JavaScript
 * Handles profile information, photo upload, password change, and settings management
 */

class AdminSettings {
    constructor() {
        this.settings = {};
        this.originalSettings = {};
        this.init();
    }

    async init() {
        await this.loadSettings();
        this.bindEvents();
        this.updateUI();
        this.updateProfilePictureOnLoad();
        this.initPasswordVisibilityToggles();
        this.initPasswordStrengthMeter();
    }

    async loadSettings() {
        try {
            const response = await fetch('api/admin_settings.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                this.settings = data.data;
                this.originalSettings = JSON.parse(JSON.stringify(data.data));
                this.updateUI();
            } else {
                this.settings = this.getDefaultSettings();
                this.originalSettings = JSON.parse(JSON.stringify(this.settings));
                this.updateUI();
                console.error('Failed to load settings:', data.message);
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            this.settings = this.getDefaultSettings();
            this.originalSettings = JSON.parse(JSON.stringify(this.settings));
            this.updateUI();
        }
    }

    bindEvents() {
        // Profile picture upload
        const changePhotoBtn = document.getElementById('change-photo-btn');
        const cameraIconBtn = document.getElementById('camera-icon-btn');
        const photoInput = document.getElementById('photo-input');
        
        if (changePhotoBtn && photoInput) {
            changePhotoBtn.addEventListener('click', () => photoInput.click());
            photoInput.addEventListener('change', (e) => this.handlePhotoUpload(e.target.files[0]));
        }

        // Camera icon button
        if (cameraIconBtn && photoInput) {
            cameraIconBtn.addEventListener('click', () => photoInput.click());
        }

        // Display name input
        const displayNameInput = document.getElementById('display-name');
        if (displayNameInput) {
            displayNameInput.addEventListener('input', (e) => {
                if (!this.settings.profile) {
                    this.settings.profile = {
                        displayName: '',
                        profilePicture: ''
                    };
                }
                this.settings.profile.displayName = e.target.value;
                this.updateSaveButton();
            });
        }

        // Save changes button
        const saveBtn = document.getElementById('save-settings');
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.saveSettings();
            });
        }

// Change password form
const changePasswordForm = document.getElementById('change-password-form');
if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.changePassword();
    });
}
        // Reset to defaults button
        const resetBtn = document.getElementById('reset-settings');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetToDefaults());
        }
    }

    initPasswordVisibilityToggles() {
        document.querySelectorAll('[data-input]').forEach(button => {
            button.addEventListener('click', function() {
                const inputId = this.getAttribute('data-input');
                const input = document.getElementById(inputId);
                const icon = document.getElementById(inputId + '-icon');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
                } else {
                    input.type = 'password';
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
                }
            });
        });
    }

    initPasswordStrengthMeter() {
        const newPasswordInput = document.getElementById('new-password');
        if (!newPasswordInput) return;

        newPasswordInput.addEventListener('input', (e) => {
            const password = e.target.value;
            this.updatePasswordStrength(password);
        });

        newPasswordInput.addEventListener('focus', () => {
            document.getElementById('password-tooltip')?.classList.remove('hidden');
        });

        newPasswordInput.addEventListener('blur', () => {
            setTimeout(() => {
                document.getElementById('password-tooltip')?.classList.add('hidden');
            }, 200);
        });
    }

    updatePasswordStrength(password) {
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        
        if (!strengthBar || !strengthText) return;

        let strength = 0;
        const checks = {
            length: password.length >= 12,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /\d/.test(password),
            special: /[^a-zA-Z0-9]/.test(password)
        };

        ['length', 'uppercase', 'lowercase', 'number', 'special'].forEach(check => {
            const element = document.getElementById(`${check}-check-tooltip`);
            if (element) {
                element.className = checks[check] ? 'requirement met text-xs text-green-600' : 'requirement unmet text-xs text-gray-600';
            }
        });

        Object.values(checks).forEach(check => {
            if (check) strength += 20;
        });

        strengthBar.style.width = strength + '%';
        
        if (strength < 40) {
            strengthBar.className = 'strength-weak h-2 rounded-full transition-all duration-300 bg-red-500';
            strengthText.textContent = 'Weak password';
            strengthText.className = 'text-xs text-red-600 block mt-1';
        } else if (strength < 80) {
            strengthBar.className = 'strength-medium h-2 rounded-full transition-all duration-300 bg-yellow-500';
            strengthText.textContent = 'Medium password';
            strengthText.className = 'text-xs text-yellow-600 block mt-1';
        } else {
            strengthBar.className = 'strength-strong h-2 rounded-full transition-all duration-300 bg-green-500';
            strengthText.textContent = 'Strong password';
            strengthText.className = 'text-xs text-green-600 block mt-1';
        }
    }

    async handlePhotoUpload(file) {
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024;

        if (!allowedTypes.includes(file.type)) {
            this.showToast('Invalid file type. Only JPG, PNG, and GIF files are allowed.', 'error');
            return;
        }

        if (file.size > maxSize) {
            this.showToast('File size too large. Maximum size is 2MB.', 'error');
            return;
        }

        const changePhotoBtn = document.getElementById('change-photo-btn');
        if (changePhotoBtn) {
            changePhotoBtn.disabled = true;
            changePhotoBtn.textContent = 'Uploading...';
        }

        try {
            const formData = new FormData();
            formData.append('profile_picture', file);

            const response = await fetch('api/upload_profile_picture_admin.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.settings.profile.profilePicture = data.file_path;
                const webPath = '../uploads/profile_pictures/' + data.file_path;
                this.updateProfilePicture(webPath);
                this.showToast('Profile picture updated successfully!', 'success');
                this.updateSaveButton();
            } else {
                this.showToast(data.error || 'Failed to upload profile picture', 'error');
            }
        } catch (error) {
            console.error('Error uploading photo:', error);
            this.showToast('Error uploading profile picture', 'error');
        } finally {
            if (changePhotoBtn) {
                changePhotoBtn.disabled = false;
                changePhotoBtn.textContent = 'Change Photo';
            }
        }
    }

    async changePassword() {
        const currentPassword = document.getElementById('current-password')?.value;
        const newPassword = document.getElementById('new-password')?.value;
        const confirmPassword = document.getElementById('confirm-password')?.value;

        if (!currentPassword || !newPassword || !confirmPassword) {
            this.showToast('Please fill in all password fields', 'error');
            return;
        }

        if (newPassword !== confirmPassword) {
            this.showToast('New passwords do not match', 'error');
            return;
        }

        if (newPassword.length < 12) {
            this.showToast('Password must be at least 12 characters long', 'error');
            return;
        }

        this.showModernConfirm(
            'Change Password',
            'Are you sure you want to change your password? You will need to use the new password on your next login.',
            'Change Password',
            'Cancel',
            () => this.proceedWithPasswordChange(currentPassword, newPassword, confirmPassword)
        );
    }

    async proceedWithPasswordChange(currentPassword, newPassword, confirmPassword) {
        const submitBtn = document.querySelector('#change-password-form button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Changing...';
        }

        try {
            const response = await fetch('api/change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword  // ✅ ADDED THIS
                })
            });

            const data = await response.json();

            if (data.success && data.otp_sent) {
                // ✅ Show OTP modal
                this.showToast(data.message, 'success');
                setTimeout(() => {
                    if (window.adminPasswordChangeManager) {
                        window.adminPasswordChangeManager.showOTPModal();
                    }
                }, 1000);
            } else if (data.success) {
                this.showToast('Password changed successfully!', 'success');
                document.getElementById('change-password-form')?.reset();
                document.getElementById('strength-bar').style.width = '0%';
                document.getElementById('strength-text').textContent = '';
            } else {
                this.showToast(data.error || 'Failed to change password', 'error');
            }
        } catch (error) {
            console.error('Error changing password:', error);
            this.showToast('Error changing password', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>Change Password';
            }
        }
    }

    updateProfilePicture(imagePath) {
        const profilePictures = document.querySelectorAll('.profile-picture');
        const placeholders = document.querySelectorAll('.profile-picture-placeholder');
        const sidebarProfilePictures = document.querySelectorAll('.sidebar-profile-picture');
        const sidebarPlaceholders = document.querySelectorAll('.sidebar-profile-placeholder');
        
        if (imagePath) {
            // Update main profile pictures
            profilePictures.forEach(img => {
                img.src = imagePath;
                img.style.display = 'block';
            });
            placeholders.forEach(placeholder => {
                placeholder.style.display = 'none';
            });
            
            // Update sidebar profile pictures
            sidebarProfilePictures.forEach(img => {
                img.src = imagePath;
                img.style.display = 'block';
            });
            sidebarPlaceholders.forEach(placeholder => {
                placeholder.style.display = 'none';
            });
        }
    }

    updateProfilePictureOnLoad() {
        const existingProfilePictures = document.querySelectorAll('.profile-picture');
        const existingSidebarProfilePictures = document.querySelectorAll('.sidebar-profile-picture');
        
        existingProfilePictures.forEach(img => {
            if (img.src && img.src !== window.location.href) {
                img.style.display = 'block';
                const placeholder = img.parentElement.querySelector('.profile-picture-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            }
        });
        
        existingSidebarProfilePictures.forEach(img => {
            if (img.src && img.src !== window.location.href) {
                img.style.display = 'block';
                const placeholder = img.parentElement.querySelector('.sidebar-profile-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            }
        });
    }

    updateUI() {
        const displayNameInput = document.getElementById('display-name');
        
        if (!this.settings.profile) {
            this.settings.profile = {
                displayName: '',
                profilePicture: ''
            };
        }
        
        if (displayNameInput) {
            displayNameInput.value = this.settings.profile.displayName || '';
        }

        if (this.settings.profile.profilePicture) {
            const webPath = this.settings.profile.profilePicture.startsWith('uploads/') || 
                            this.settings.profile.profilePicture.startsWith('../uploads/') ? 
                this.settings.profile.profilePicture : 
                '../uploads/profile_pictures/' + this.settings.profile.profilePicture;
            this.updateProfilePicture(webPath);
        }

        this.updateSaveButton();
    }

    updateSaveButton() {
        const saveBtn = document.getElementById('save-settings');
        if (!saveBtn) return;

        const hasChanges = JSON.stringify(this.settings) !== JSON.stringify(this.originalSettings);
        saveBtn.disabled = !hasChanges;
        
        if (hasChanges) {
            saveBtn.classList.add('bg-primary-600', 'hover:bg-primary-700');
            saveBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
        } else {
            saveBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            saveBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
        }
    }

    async saveSettings() {
        this.showModernConfirm(
            'Save All Changes',
            'Are you sure you want to save all your profile changes? This will update your display name and profile picture.',
            'Save Changes',
            'Cancel',
            () => this.proceedWithSave()
        );
    }

    async proceedWithSave() {
        const saveBtn = document.getElementById('save-settings');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...';
        }

        try {
            const response = await fetch('api/admin_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.settings)
            });

            const data = await response.json();

            if (data.success) {
                this.originalSettings = JSON.parse(JSON.stringify(this.settings));
                this.showToast('Settings saved successfully!', 'success');
                this.updateSaveButton();
                
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Failed to save settings');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showToast('Failed to save changes. Please try again.', 'error');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Save All Changes';
            }
        }
    }

    async resetToDefaults() {
        this.showModernConfirm(
            'Reset to Defaults',
            'Are you sure you want to reset all settings to defaults? This action cannot be undone.',
            'Reset Settings',
            'Cancel',
            () => {
                this.settings = this.getDefaultSettings();
                this.updateUI();
                this.showToast('Settings reset to defaults. Click "Save All Changes" to apply.', 'info');
            }
        );
    }

    getDefaultSettings() {
        return {
            profile: {
                displayName: '',
                profilePicture: ''
            }
        };
    }

    showModernConfirm(title, message, confirmText, cancelText, onConfirm, onCancel) {
        const existingDialogs = document.querySelectorAll('.modern-confirm-dialog');
        existingDialogs.forEach(dialog => dialog.remove());
        
        const backdrop = document.createElement('div');
        backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999] modern-confirm-dialog transition-all duration-300';
        backdrop.style.opacity = '0';
        
        backdrop.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform scale-95 transition-all duration-300" id="confirmDialog">
                <div class="p-8">
                    <div class="flex items-start mb-6">
                        <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">${title}</h3>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-8 leading-relaxed text-base">${message}</p>
                    <div class="flex gap-3">
                        <button type="button" id="cancelBtn" class="flex-1 px-6 py-3.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium transition-all duration-200 transform hover:scale-105">
                            ${cancelText}
                        </button>
                        <button type="button" id="confirmBtn" class="flex-1 px-6 py-3.5 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-xl hover:from-primary-700 hover:to-primary-800 font-medium shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>${confirmText}</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(backdrop);
        setTimeout(() => { 
            backdrop.style.opacity = '1'; 
            document.querySelector('#confirmDialog').style.transform = 'scale(1)'; 
        }, 10);
        
        const cancelBtn = backdrop.querySelector('#cancelBtn');
        const confirmBtn = backdrop.querySelector('#confirmBtn');
        
        const closeModal = () => { 
            backdrop.style.opacity = '0'; 
            document.querySelector('#confirmDialog').style.transform = 'scale(0.95)'; 
            setTimeout(() => backdrop.remove(), 300); 
        };
        
        cancelBtn.addEventListener('click', () => { closeModal(); if (onCancel) onCancel(); });
        confirmBtn.addEventListener('click', () => { closeModal(); if (onConfirm) onConfirm(); });
        backdrop.addEventListener('click', (e) => { if (e.target === backdrop) { closeModal(); if (onCancel) onCancel(); } });
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

document.addEventListener('DOMContentLoaded', () => {
    new AdminSettings();
});

console.log('Admin Settings initialized successfully!');
