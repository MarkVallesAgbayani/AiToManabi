/**
 * Teacher Settings JavaScript
 * Handles profile information, photo upload, and settings management
 */

class TeacherSettings {
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
    }

    async loadSettings() {
        try {
            const response = await fetch('api/teacher_settings.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();
            if (data.success) {
                this.settings = data.data;
                this.originalSettings = JSON.parse(JSON.stringify(data.data));
                this.updateUI();
            } else {
                this.showMessage('Failed to load settings', 'error');
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showMessage('Error loading settings', 'error');
        }
    }

    bindEvents() {
        // Profile picture upload - handle both instances
        const changePhotoBtn = document.getElementById('change-photo-btn');
        const changePhotoBtn2 = document.getElementById('change-photo-btn-2');
        const cameraIconBtn = document.getElementById('camera-icon-btn');
        const photoInput = document.getElementById('photo-input');
        const photoInput2 = document.getElementById('photo-input-2');
        
        if (changePhotoBtn) {
            changePhotoBtn.addEventListener('click', () => {
                photoInput?.click();
            });
        }

        if (changePhotoBtn2) {
            changePhotoBtn2.addEventListener('click', () => {
                photoInput2?.click();
            });
        }
        
        if (cameraIconBtn) {
            cameraIconBtn.addEventListener('click', () => {
                photoInput?.click();
            });
        }

        if (photoInput) {
            photoInput.addEventListener('change', (e) => {
                this.handlePhotoUpload(e.target.files[0]);
            });
        }

        if (photoInput2) {
            photoInput2.addEventListener('change', (e) => {
                this.handlePhotoUpload(e.target.files[0]);
            });
        }

        // Display name inputs (handle both instances)
        const displayNameInput = document.getElementById('display-name');
        const displayNameInput2 = document.getElementById('display-name-2');
        
        if (displayNameInput) {
            displayNameInput.addEventListener('input', (e) => {
                this.settings.profile.displayName = e.target.value;
                // Sync with second input
                if (displayNameInput2) {
                    displayNameInput2.value = e.target.value;
                }
                this.updateSaveButton();
            });
        }
        
        if (displayNameInput2) {
            displayNameInput2.addEventListener('input', (e) => {
                this.settings.profile.displayName = e.target.value;
                // Sync with first input
                if (displayNameInput) {
                    displayNameInput.value = e.target.value;
                }
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

        // Reset to defaults button
        const resetBtn = document.getElementById('reset-settings');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetToDefaults();
            });
        }

        // Notification checkboxes
        this.bindNotificationEvents();
    }

    bindNotificationEvents() {
        const notificationCheckboxes = document.querySelectorAll('input[type="checkbox"]');
        notificationCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const id = e.target.id;
                const value = e.target.checked;
                
                // Map checkbox IDs to settings structure
                if (id === 'profile-visible') {
                    this.settings.security.profileVisible = value;
                }
                
                this.updateSaveButton();
            });
        });
    }

    async handlePhotoUpload(file) {
        if (!file) return;

        // Validate file
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB

        if (!allowedTypes.includes(file.type)) {
            this.showMessage('Invalid file type. Only JPG, PNG, and GIF files are allowed.', 'error');
            return;
        }

        if (file.size > maxSize) {
            this.showMessage('File size too large. Maximum size is 2MB.', 'error');
            return;
        }

        // Show loading state
        const changePhotoBtn = document.getElementById('change-photo-btn');
        if (changePhotoBtn) {
            changePhotoBtn.disabled = true;
            changePhotoBtn.textContent = 'Uploading...';
        }

        try {
            const formData = new FormData();
            formData.append('profile_picture', file);

            const response = await fetch('api/upload_profile_picture.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.settings.profile.profilePicture = data.file_path;
                const webPath = 'uploads/profile_pictures/' + data.file_path;
                console.log('Upload successful, updating profile picture with path:', webPath);
                this.updateProfilePicture(webPath);
                this.updateSidebarProfile();
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

    updateProfilePicture(imagePath) {
        const profilePictures = document.querySelectorAll('.profile-picture');
        const placeholders = document.querySelectorAll('.profile-picture-placeholder');
        
        if (imagePath) {
            profilePictures.forEach(img => {
                img.src = imagePath;
                img.style.display = 'block';
            });
            placeholders.forEach(placeholder => {
                placeholder.style.display = 'none';
            });
        } else {
            profilePictures.forEach(img => {
                img.style.display = 'none';
            });
            placeholders.forEach(placeholder => {
                placeholder.style.display = 'flex';
            });
        }
    }

    updateProfilePictureOnLoad() {
        const existingProfilePictures = document.querySelectorAll('.profile-picture');
        
        existingProfilePictures.forEach(img => {
            if (img.src && img.src !== window.location.href) {
                img.style.display = 'block';
                const placeholder = img.parentElement.querySelector('.profile-picture-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            }
        });
    }

    updateUI() {
        // Update display name (both inputs)
        const displayNameInput = document.getElementById('display-name');
        const displayNameInput2 = document.getElementById('display-name-2');
        
        if (this.settings.profile) {
            const displayName = this.settings.profile.displayName || '';
            if (displayNameInput) {
                displayNameInput.value = displayName;
            }
            if (displayNameInput2) {
                displayNameInput2.value = displayName;
            }
        }

        // Update profile picture
        if (this.settings.profile && this.settings.profile.profilePicture) {
            const webPath = this.settings.profile.profilePicture.startsWith('uploads/') ? 
                this.settings.profile.profilePicture : 
                'uploads/profile_pictures/' + this.settings.profile.profilePicture;
            this.updateProfilePicture(webPath);
        }

        // Update notification checkboxes
        this.updateNotificationCheckboxes();

        // Update save button state
        this.updateSaveButton();
    }

    updateNotificationCheckboxes() {
        const profileVisible = document.getElementById('profile-visible');
        if (profileVisible && this.settings.security) {
            profileVisible.checked = this.settings.security.profileVisible;
        }
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
        // Show modern confirmation modal first
        this.showModernConfirm(
            'Save All Changes',
            'Are you sure you want to save all your profile changes? This will update your display name, profile picture, and other settings.',
            'Save Changes',
            'Cancel',
            () => {
                this.proceedWithSave();
            }
        );
    }

    async proceedWithSave() {
        const saveBtn = document.getElementById('save-settings');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = `
                <svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Saving...
            `;
        }

        try {
            const response = await fetch('api/teacher_settings.php', {
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
                this.updateLastSavedTime();
                this.updateSidebarProfile();
                
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
                saveBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save All Changes
                `;
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
                this.proceedWithReset();
            }
        );
    }

    async proceedWithReset() {
        const resetBtn = document.getElementById('reset-settings');
        if (resetBtn) {
            resetBtn.disabled = true;
            resetBtn.textContent = 'Resetting...';
        }

        try {
            this.settings = this.getDefaultSettings();
            this.updateUI();
            this.showToast('Settings reset to defaults. Click "Save All Changes" to apply.', 'info');
        } catch (error) {
            console.error('Error resetting settings:', error);
            this.showToast('Error resetting settings', 'error');
        } finally {
            if (resetBtn) {
                resetBtn.disabled = false;
                resetBtn.textContent = 'Reset to Defaults';
            }
        }
    }

    getDefaultSettings() {
        return {
            profile: {
                firstName: '',
                lastName: '',
                displayName: '',
                profilePicture: '',
                bio: '',
                phone: '',
                languages: ''
            },
            security: {
                profileVisible: true,
                contactVisible: true
            }
        };
    }

    showMessage(message, type = 'info') {
        const existingMessages = document.querySelectorAll('.settings-message');
        existingMessages.forEach(msg => msg.remove());

        const messageDiv = document.createElement('div');
        messageDiv.className = `settings-message ${type}`;
        messageDiv.textContent = message;

        const settingsContainer = document.querySelector('.settings-container');
        if (settingsContainer) {
            settingsContainer.insertBefore(messageDiv, settingsContainer.firstChild);
        }

        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
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
        
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', handleEscape);
                if (onCancel) onCancel();
            }
        };
        document.addEventListener('keydown', handleEscape);
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
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }
        }, 4000);
    }

    updateLastSavedTime() {
        const lastSavedElements = document.querySelectorAll('#last-saved, #last-saved-bottom');
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        
        lastSavedElements.forEach(element => {
            element.textContent = `Last saved: ${timeString}`;
        });
    }

    updateSidebarProfile() {
        const sidebarProfilePictures = document.querySelectorAll('.sidebar-profile-picture');
        const sidebarProfilePlaceholders = document.querySelectorAll('.sidebar-profile-placeholder');
        const sidebarDisplayNames = document.querySelectorAll('.sidebar-display-name');
        
        if (this.settings.profile) {
            const displayName = this.settings.profile.displayName || '';
            const profilePicture = this.settings.profile.profilePicture || '';
            
            sidebarDisplayNames.forEach(element => {
                element.textContent = displayName;
            });
            
            if (profilePicture) {
                const webPath = profilePicture.startsWith('uploads/') ? profilePicture : 'uploads/profile_pictures/' + profilePicture;
                sidebarProfilePictures.forEach(img => {
                    img.src = webPath;
                    img.style.display = 'block';
                });
                sidebarProfilePlaceholders.forEach(placeholder => {
                    placeholder.style.display = 'none';
                });
            } else {
                sidebarProfilePictures.forEach(img => {
                    img.style.display = 'none';
                });
                sidebarProfilePlaceholders.forEach(placeholder => {
                    placeholder.style.display = 'flex';
                });
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new TeacherSettings();
});
