/**
 * Student Settings JavaScript
 * Handles profile information, photo upload, and settings management with auto-save
 */

class StudentSettings {
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
        
        // Ensure settings object is properly initialized
        if (!this.settings || !this.settings.profile) {
            console.log('Initializing default student settings');
            this.settings = this.getDefaultSettings();
            this.originalSettings = JSON.parse(JSON.stringify(this.settings));
        }
    }

    async loadSettings() {
        try {
            const response = await fetch('api/student_settings.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();
            console.log('Student settings loaded:', data);
            
            if (data.success) {
                this.settings = data.data;
                this.originalSettings = JSON.parse(JSON.stringify(data.data));
                console.log('Student settings initialized:', this.settings);
                this.updateUI();
            } else {
                console.error('Failed to load student settings:', data);
                this.showMessage('Failed to load settings', 'error');
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showMessage('Error loading settings', 'error');
        }
    }

    bindEvents() {
        // Profile picture upload
        const changePhotoBtn = document.getElementById('change-photo-btn');
        const cameraIconBtn = document.getElementById('camera-icon-btn');
        const photoInput = document.getElementById('photo-input');
        
        if (changePhotoBtn && photoInput) {
            changePhotoBtn.addEventListener('click', () => {
                photoInput.click();
            });
        }

        if (cameraIconBtn && photoInput) {
            cameraIconBtn.addEventListener('click', () => {
                photoInput.click();
            });
        }
        
        if (photoInput) {
            photoInput.addEventListener('change', (e) => {
                this.handlePhotoUpload(e.target.files[0]);
            });
        }

        // Display name input with auto-save and real-time validation
        const displayNameInput = document.getElementById('display-name');
        if (displayNameInput) {
            let saveTimeout;
            displayNameInput.addEventListener('input', (e) => {
                // Ensure settings object is properly initialized
                if (!this.settings) {
                    this.settings = this.getDefaultSettings();
                }
                if (!this.settings.profile) {
                    this.settings.profile = {};
                }
                
                this.settings.profile.displayName = e.target.value;
                console.log('Display name updated:', e.target.value);
                
                // Real-time validation feedback
                this.validateDisplayName(e.target);
                
                // Update navigation display name in real-time
                this.updateNavigationDisplayName();
                
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    this.autoSave();
                }, 1000);
            });
        }

        // Email input with auto-save
        const emailInput = document.getElementById('email');
        if (emailInput) {
            let saveTimeout;
            emailInput.addEventListener('input', (e) => {
                this.settings.profile.email = e.target.value;
                
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    this.autoSave();
                }, 1000);
            });
        }

        // Save changes button
        const saveBtn = document.getElementById('save-settings');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
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

        // Keyboard shortcut for saving (Ctrl+S)
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                this.saveSettings();
            }
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

            const response = await fetch('api/upload_student_profile_picture.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.settings.profile.profilePicture = data.file_path;
                const webPath = data.web_path || '../uploads/profile_pictures/' + data.file_path;
                this.updateProfilePicture(webPath);
                this.updateNavigationProfilePicture();
                this.showDialog('Profile picture updated successfully!', 'success');
                this.autoSave();
            } else {
                this.showDialog(data.error || 'Failed to upload profile picture', 'error');
            }
        } catch (error) {
            console.error('Error uploading photo:', error);
            this.showDialog('Error uploading profile picture', 'error');
        } finally {
            // Reset button state
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
        // Check if there's already a profile picture displayed in the HTML
        const existingProfilePictures = document.querySelectorAll('.profile-picture');
        const existingPlaceholders = document.querySelectorAll('.profile-picture-placeholder');
        
        // If there are existing profile pictures with src, make sure they're visible
        existingProfilePictures.forEach(img => {
            if (img.src && img.src !== window.location.href) {
                img.style.display = 'block';
                // Hide corresponding placeholder
                const placeholder = img.parentElement.querySelector('.profile-picture-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            }
        });
    }

    updateUI() {
        // Update display name
        const displayNameInput = document.getElementById('display-name');
        if (this.settings.profile && displayNameInput) {
            const displayName = this.settings.profile.displayName || '';
            displayNameInput.value = displayName;
        }

        // Update email
        const emailInput = document.getElementById('email');
        if (this.settings.profile && emailInput) {
            const email = this.settings.profile.email || '';
            emailInput.value = email;
        }

        // Update profile picture
        if (this.settings.profile && this.settings.profile.profilePicture) {
            const webPath = this.settings.profile.profilePicture.startsWith('../uploads/') ? 
                this.settings.profile.profilePicture : 
                '../uploads/profile_pictures/' + this.settings.profile.profilePicture;
            this.updateProfilePicture(webPath);
        }

        // Update navigation
        this.updateNavigationDisplayName();
        this.updateNavigationProfilePicture();
    }

    updateNavigationDisplayName() {
        const navDisplayName = document.querySelector('nav .text-gray-900.dark\\:text-white');
        if (navDisplayName && this.settings.profile) {
            const displayName = this.settings.profile.displayName || 'Student';
            navDisplayName.textContent = displayName;
        }
    }

    updateNavigationProfilePicture() {
        const navProfilePicture = document.querySelector('nav .w-10.h-10.rounded-full');
        if (navProfilePicture && this.settings.profile && this.settings.profile.profilePicture) {
            const webPath = this.settings.profile.profilePicture.startsWith('../uploads/') ? 
                this.settings.profile.profilePicture : 
                '../uploads/profile_pictures/' + this.settings.profile.profilePicture;
            
            // Check if it's already an img or a div
            if (navProfilePicture.tagName === 'IMG') {
                // Update existing img src
                navProfilePicture.src = webPath;
            } else {
                // Replace div with img
                const img = document.createElement('img');
                img.src = webPath;
                img.className = 'w-10 h-10 rounded-full object-cover';
                img.alt = 'Profile Picture';
                
                // Replace the div with the img
                navProfilePicture.parentNode.replaceChild(img, navProfilePicture);
            }
        }
    }

    async autoSave() {
        // Validate settings before auto-saving
        const validationResult = this.validateSettings();
        if (!validationResult.isValid) {
            console.warn('Auto-save skipped due to validation errors:', validationResult.message);
            return;
        }

        try {
            const response = await fetch('api/student_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.settings)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.originalSettings = JSON.parse(JSON.stringify(this.settings));
                // Update timestamp from server response if available
                if (data.updated_at) {
                    document.body.setAttribute('data-last-updated', data.updated_at);
                }
                this.updateLastSavedTime();
            } else {
                console.error('Auto-save failed:', data.message || data.error);
                // Show subtle notification for auto-save failures
                this.showAutoSaveError(data.message || data.error);
            }
        } catch (error) {
            console.error('Error auto-saving settings:', error);
            // Show subtle notification for auto-save failures
            this.showAutoSaveError('Auto-save failed. Your changes are saved locally.');
        }
    }

    async saveSettings() {
        // Ensure settings object is properly initialized
        if (!this.settings) {
            this.settings = this.getDefaultSettings();
        }
        if (!this.settings.profile) {
            this.settings.profile = {};
        }
        if (!this.settings.security) {
            this.settings.security = {};
        }
        
        console.log('Current settings before save:', this.settings);
        
        // Validate settings before saving
        const validationResult = this.validateSettings();
        if (!validationResult.isValid) {
            this.showDialog(validationResult.message, 'error');
            return;
        }

        // Show modern confirmation modal
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
            saveBtn.textContent = 'Saving...';
        }

        // Show saving dialog
        this.showDialog('Saving your changes...', 'info');

        console.log('Saving student settings:', this.settings);

        try {
            const response = await fetch('api/student_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.settings)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            let data;
            try {
                data = await response.json();
                console.log('Student settings save response:', data);
            } catch (parseError) {
                console.error('Failed to parse API response:', parseError);
                this.showDialog('Invalid response from server. Please try again.', 'error');
                return;
            }

            if (data.success) {
                this.originalSettings = JSON.parse(JSON.stringify(this.settings));
                // Update timestamp from server response if available
                if (data.updated_at) {
                    document.body.setAttribute('data-last-updated', data.updated_at);
                }
                this.showDialog('Settings saved successfully!', 'success');
                this.updateLastSavedTime();
            } else {
                console.error('Student settings save failed:', data);
                console.error('Error details:', JSON.stringify(data, null, 2));
                const errorMessage = data.message || data.error || 'Failed to save settings';
                this.showDialog(`Save failed: ${errorMessage}`, 'error');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            let errorMessage = 'Error saving settings';
            
            if (error.message.includes('HTTP error')) {
                errorMessage = 'Server error occurred. Please try again.';
            } else if (error.message.includes('fetch')) {
                errorMessage = 'Network error. Please check your connection.';
            } else {
                errorMessage = `Error: ${error.message}`;
            }
            
            this.showDialog(errorMessage, 'error');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save All Changes';
            }
        }
    }

    async resetToDefaults() {
        this.showModernConfirm(
            'Reset to Defaults',
            'Are you sure you want to reset all your settings to defaults? This will clear your display name, profile picture, and other custom settings. This action cannot be undone.',
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
            // Reset to default settings
            this.settings = this.getDefaultSettings();
            this.updateUI();
            this.showMessage('Settings reset to defaults. Click "Save All Changes" to apply.', 'info');
        } catch (error) {
            console.error('Error resetting settings:', error);
            this.showMessage('Error resetting settings', 'error');
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
                displayName: '',
                profilePicture: '',
                email: ''
            },
            security: {
                profileVisible: true
            }
        };
    }

    showMessage(message, type = 'info') {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.settings-message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `settings-message ${type}`;
        messageDiv.textContent = message;

        // Insert at the top of the settings container
        const settingsContainer = document.querySelector('.settings-container');
        if (settingsContainer) {
            settingsContainer.insertBefore(messageDiv, settingsContainer.firstChild);
        }

        // Auto-remove after 5 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    showDialog(message, type = 'info') {
        // Remove existing dialogs
        const existingDialogs = document.querySelectorAll('.dialog-alert');
        existingDialogs.forEach(dialog => dialog.remove());

        // Create dialog element
        const dialog = document.createElement('div');
        dialog.className = `dialog-alert fixed top-4 right-4 z-50 max-w-sm w-full bg-white dark:bg-dark-surface rounded-lg shadow-lg border border-gray-200 dark:border-dark-border p-4 transform transition-all duration-300 ease-in-out`;
        
        // Set initial position (off-screen)
        dialog.style.transform = 'translateX(100%)';
        
        // Add type-specific styling
        let iconColor = 'text-blue-500';
        let bgColor = 'bg-blue-50 dark:bg-blue-900/20';
        let borderColor = 'border-blue-200 dark:border-blue-800';
        
        if (type === 'success') {
            iconColor = 'text-green-500';
            bgColor = 'bg-green-50 dark:bg-green-900/20';
            borderColor = 'border-green-200 dark:border-green-800';
        } else if (type === 'error') {
            iconColor = 'text-red-500';
            bgColor = 'bg-red-50 dark:bg-red-900/20';
            borderColor = 'border-red-200 dark:border-red-800';
        } else if (type === 'warning') {
            iconColor = 'text-yellow-500';
            bgColor = 'bg-yellow-50 dark:bg-yellow-900/20';
            borderColor = 'border-yellow-200 dark:border-yellow-800';
        }
        
        dialog.innerHTML = `
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 ${iconColor}" fill="currentColor" viewBox="0 0 20 20">
                        ${type === 'success' ? 
                            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>' :
                            type === 'error' ?
                            '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>' :
                            '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>'
                        }
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">${message}</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <button class="inline-flex text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none" onclick="this.parentElement.parentElement.parentElement.remove()">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        // Add to body
        document.body.appendChild(dialog);
        
        // Animate in
        setTimeout(() => {
            dialog.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-remove after 4 seconds
        setTimeout(() => {
            if (dialog.parentNode) {
                dialog.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (dialog.parentNode) {
                        dialog.remove();
                    }
                }, 300);
            }
        }, 4000);
    }

    validateDisplayName(input) {
        const value = input.value.trim();
        const maxLength = 30;
        
        // Remove existing validation classes
        input.classList.remove('border-red-500', 'border-green-500');
        
        if (value.length === 0) {
            // Empty is okay
            return;
        }
        
        if (value.length > maxLength) {
            input.classList.add('border-red-500');
            this.showFieldError(input, `Display name must be ${maxLength} characters or less`);
        } else if (value.length < 2) {
            input.classList.add('border-red-500');
            this.showFieldError(input, 'Display name must be at least 2 characters');
        } else if (value.includes('<') || value.includes('>')) {
            input.classList.add('border-red-500');
            this.showFieldError(input, 'Display name cannot contain HTML tags');
        } else if (!/^[a-zA-Z\s\-'\.]+$/.test(value)) {
            input.classList.add('border-red-500');
            this.showFieldError(input, 'Display name can only contain letters, spaces, hyphens, apostrophes, and periods');
        } else if (value.includes('--') || value.includes("''") || value.includes('..')) {
            input.classList.add('border-red-500');
            this.showFieldError(input, 'Display name cannot have consecutive special characters');
        } else if (value.startsWith('-') || value.startsWith("'") || value.startsWith('.') ||
                   value.endsWith('-') || value.endsWith("'") || value.endsWith('.')) {
            input.classList.add('border-red-500');
            this.showFieldError(input, 'Display name cannot start or end with special characters');
        } else {
            input.classList.add('border-green-500');
            this.clearFieldError(input);
        }
    }

    showFieldError(input, message) {
        // Remove existing error message
        this.clearFieldError(input);
        
        // Create error message element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-xs text-red-600 mt-1';
        errorDiv.textContent = message;
        
        // Insert after the input
        input.parentNode.insertBefore(errorDiv, input.nextSibling);
    }

    clearFieldError(input) {
        const existingError = input.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }

    showAutoSaveError(message) {
        // Create subtle auto-save error notification
        const notification = document.createElement('div');
        notification.className = 'auto-save-error fixed top-4 right-4 z-40 max-w-sm w-full bg-yellow-50 dark:bg-yellow-900/20 rounded-lg shadow-lg border border-yellow-200 dark:border-yellow-800 p-3 transform transition-all duration-300 ease-in-out';
        notification.style.transform = 'translateX(100%)';
        
        notification.innerHTML = `
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-2 flex-1">
                    <p class="text-xs font-medium text-yellow-800 dark:text-yellow-200">Auto-save failed</p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-300">${message}</p>
                </div>
                <button class="ml-2 text-yellow-400 hover:text-yellow-600" onclick="this.parentElement.parentElement.remove()">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 5000);
    }

    validateSettings() {
        const errors = [];
        
        // Validate display name
        if (this.settings.profile && this.settings.profile.displayName) {
            const displayName = this.settings.profile.displayName.trim();
            if (displayName.length > 30) {
                errors.push('Display name must be 30 characters or less');
            }
            if (displayName.length < 2) {
                errors.push('Display name must be at least 2 characters');
            }
            if (displayName.includes('<') || displayName.includes('>')) {
                errors.push('Display name cannot contain HTML tags');
            }
            if (!/^[a-zA-Z\s\-'\.]+$/.test(displayName)) {
                errors.push('Display name can only contain letters, spaces, hyphens, apostrophes, and periods');
            }
            if (displayName.includes('--') || displayName.includes("''") || displayName.includes('..')) {
                errors.push('Display name cannot have consecutive special characters');
            }
            if (displayName.startsWith('-') || displayName.startsWith("'") || displayName.startsWith('.') ||
                displayName.endsWith('-') || displayName.endsWith("'") || displayName.endsWith('.')) {
                errors.push('Display name cannot start or end with special characters');
            }
        }
        
        // Validate email if present
        if (this.settings.profile && this.settings.profile.email) {
            const email = this.settings.profile.email.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errors.push('Please enter a valid email address');
            }
        }
        
        // Validate bio if present
        if (this.settings.profile && this.settings.profile.bio) {
            const bio = this.settings.profile.bio.trim();
            if (bio.length > 500) {
                errors.push('Bio must be 500 characters or less');
            }
        }
        
        // Validate phone if present
        if (this.settings.profile && this.settings.profile.phone) {
            const phone = this.settings.profile.phone.trim();
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{7,20}$/;
            if (!phoneRegex.test(phone)) {
                errors.push('Please enter a valid phone number');
            }
        }
        
        if (errors.length > 0) {
            return {
                isValid: false,
                message: errors.join('. ') + '.'
            };
        }
        
        return { isValid: true };
    }

    showModernConfirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm = null, onCancel = null) {
        // Remove any existing confirm dialogs
        const existingDialogs = document.querySelectorAll('.modern-confirm-dialog');
        existingDialogs.forEach(dialog => dialog.remove());
        
        // Create the modal backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70] modern-confirm-dialog';
        
        // Create the dialog content
        backdrop.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4" id="confirmDialog">
                <div class="p-6">
                    <!-- Header with icon -->
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">${title}</h3>
                    </div>
                    
                    <!-- Message -->
                    <p class="text-gray-600 mb-6 leading-relaxed">${message}</p>
                    
                    <!-- Action buttons -->
                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancelBtn" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium">
                            ${cancelText}
                        </button>
                        <button type="button" id="confirmBtn" class="px-6 py-2.5 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:from-red-600 hover:to-red-700 font-medium shadow-lg">
                            ${confirmText}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(backdrop);
        
        // Event handlers
        const cancelBtn = backdrop.querySelector('#cancelBtn');
        const confirmBtn = backdrop.querySelector('#confirmBtn');
        
        cancelBtn.addEventListener('click', () => {
            backdrop.remove();
            if (onCancel) onCancel();
        });
        
        confirmBtn.addEventListener('click', () => {
            backdrop.remove();
            if (onConfirm) onConfirm();
        });
        
        // Close on backdrop click
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                backdrop.remove();
                if (onCancel) onCancel();
            }
        });
        
        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                backdrop.remove();
                document.removeEventListener('keydown', handleEscape);
                if (onCancel) onCancel();
            }
        };
        document.addEventListener('keydown', handleEscape);
    }

    updateLastSavedTime() {
        const now = new Date();
        
        // Update the body data attribute with current timestamp
        document.body.setAttribute('data-last-updated', now.toISOString());
        
        // Update the timestamp displays using the global function if it exists
        if (typeof updateTimestampDisplays === 'function') {
            updateTimestampDisplays();
        } else {
            // Fallback to simple time display
            const timeString = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'Asia/Manila'
            });
            
            const lastSavedElements = document.querySelectorAll('#last-updated-time, #last-updated-time-bottom');
            lastSavedElements.forEach(element => {
                if (element) {
                    element.textContent = 'Just now';
                }
            });
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new StudentSettings();
});