/**
 * Modern Teacher Settings JavaScript
 * Handles dropdown navigation, form validation, and settings management
 */

class TeacherSettings {
    constructor() {
        this.currentSection = 'profile';
        this.hasUnsavedChanges = false;
        this.settings = {};
        
        this.init();
    }

    async init() {
        this.setupEventListeners();
        await this.loadSettings();
        this.updateLastSaved();
        
        // Initialize with profile section
        this.showSection('profile');
    }

    setupEventListeners() {
        // Navigation buttons
        document.querySelectorAll('.settings-nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const section = e.currentTarget.dataset.section;
                this.showSection(section);
            });
        });

        // Form inputs - track changes
        document.querySelectorAll('.settings-input, .settings-checkbox').forEach(input => {
            input.addEventListener('input', () => this.markAsChanged());
            input.addEventListener('change', () => this.markAsChanged());
        });

        // Save button
        const saveBtn = document.getElementById('save-settings');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveSettings());
        }

        // Reset button
        const resetBtn = document.getElementById('reset-settings');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetSettings());
        }

        // Password change validation
        const newPassword = document.getElementById('new-password');
        const confirmPassword = document.getElementById('confirm-password');
        if (newPassword && confirmPassword) {
            confirmPassword.addEventListener('input', () => {
                this.validatePasswordMatch();
            });
        }

        // Profile picture change
        const changePhotoBtn = document.querySelector('button:contains("Change Photo")');
        if (changePhotoBtn) {
            changePhotoBtn.addEventListener('click', () => this.changeProfilePicture());
        }

        // Prevent navigation if unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }

    showSection(sectionName) {
        // Update navigation
        document.querySelectorAll('.settings-nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.querySelector(`[data-section="${sectionName}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Update content sections
        document.querySelectorAll('.settings-section').forEach(section => {
            section.classList.remove('active');
        });

        const activeSection = document.getElementById(`${sectionName}-section`);
        if (activeSection) {
            activeSection.classList.add('active');
        }

        this.currentSection = sectionName;
        
        // Load section-specific data if needed
        this.loadSectionData(sectionName);
    }

    loadSectionData(sectionName) {
        switch (sectionName) {
            case 'profile':
                this.loadProfileData();
                break;
            case 'notifications':
                this.loadNotificationSettings();
                break;
            case 'security':
                // Security data should not be pre-loaded for security reasons
                break;
        }
    }

    loadProfileData() {
        // Load profile data from localStorage or server
        const profile = this.getStoredSettings('profile') || {};
        
        this.setInputValue('display-name', profile.displayName || '');
    }

    loadNotificationSettings() {
        const notifications = this.getStoredSettings('notifications') || {};
        
        this.setCheckboxValue('email-enrollments', notifications.emailEnrollments || false);
        this.setCheckboxValue('email-progress', notifications.emailProgress || false);
        this.setCheckboxValue('email-completions', notifications.emailCompletions || false);
        this.setCheckboxValue('email-reports', notifications.emailReports || false);
    }



    markAsChanged() {
        if (!this.hasUnsavedChanges) {
            this.hasUnsavedChanges = true;
            this.updateSaveButton();
        }
    }

    updateSaveButton() {
        const saveBtn = document.getElementById('save-settings');
        if (!saveBtn) return;

        if (this.hasUnsavedChanges) {
            saveBtn.textContent = 'Save Changes';
            saveBtn.classList.add('bg-yellow-600', 'hover:bg-yellow-700');
            saveBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
        } else {
            saveBtn.textContent = 'Save All Changes';
            saveBtn.classList.remove('bg-yellow-600', 'hover:bg-yellow-700');
            saveBtn.classList.add('bg-primary-600', 'hover:bg-primary-700');
        }
    }

    async saveSettings() {
        const saveBtn = document.getElementById('save-settings');
        if (!saveBtn) return;

        // Show loading state
        saveBtn.classList.add('loading');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            // Collect all settings
            const allSettings = {
                profile: this.collectProfileSettings(),
                notifications: this.collectNotificationSettings(),
                security: this.collectSecuritySettings(),
            };

            // Save to server (implement API call)
            const success = await this.saveToServer(allSettings);
            
            if (success) {
                // Save to localStorage as backup
                localStorage.setItem('teacherSettings', JSON.stringify(allSettings));
                
                this.hasUnsavedChanges = false;
                this.updateSaveButton();
                this.updateLastSaved();
                this.showMessage('Settings saved successfully!', 'success');
            } else {
                throw new Error('Failed to save settings');
            }

        } catch (error) {
            console.error('Error saving settings:', error);
            this.showMessage('Failed to save settings. Please try again.', 'error');
        } finally {
            // Reset button state
            saveBtn.classList.remove('loading');
            saveBtn.disabled = false;
        }
    }

    collectProfileSettings() {
        return {
            displayName: this.getInputValue('display-name')
        };
    }

    collectNotificationSettings() {
        return {
            emailEnrollments: this.getCheckboxValue('email-enrollments'),
            emailProgress: this.getCheckboxValue('email-progress'),
            emailCompletions: this.getCheckboxValue('email-completions'),
            emailReports: this.getCheckboxValue('email-reports')
        };
    }


    collectSecuritySettings() {
        return {
            email: this.getInputValue('email'),
            profileVisible: this.getCheckboxValue('profile-visible')
            // Note: Password changes should be handled separately for security
        };
    }


    async saveToServer(settings) {
        try {
            const response = await fetch('api/teacher_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(settings)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Error saving to server:', error);
            return false;
        }
    }

    resetSettings() {
        if (!confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
            return;
        }

        // Clear localStorage
        localStorage.removeItem('teacherSettings');
        
        // Reset all form fields to defaults
        this.resetToDefaults();
        
        this.hasUnsavedChanges = false;
        this.updateSaveButton();
        this.showMessage('Settings reset to defaults', 'info');
    }

    resetToDefaults() {
        // Reset profile
        this.setInputValue('display-name', '');

        // Reset notifications
        document.querySelectorAll('.settings-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });


    }

    validatePasswordMatch() {
        const newPassword = document.getElementById('new-password');
        const confirmPassword = document.getElementById('confirm-password');
        
        if (!newPassword || !confirmPassword) return;

        if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
            confirmPassword.classList.add('invalid');
        } else {
            confirmPassword.setCustomValidity('');
            confirmPassword.classList.remove('invalid');
        }
    }

    changeProfilePicture() {
        // Create file input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/gif';
        fileInput.style.display = 'none';
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleProfilePictureUpload(file);
            }
        });
        
        document.body.appendChild(fileInput);
        fileInput.click();
        document.body.removeChild(fileInput);
    }

    handleProfilePictureUpload(file) {
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            this.showMessage('File size must be less than 2MB', 'error');
            return;
        }

        // Validate file type
        if (!file.type.match(/^image\/(jpeg|png|gif)$/)) {
            this.showMessage('Please select a valid image file (JPG, PNG, or GIF)', 'error');
            return;
        }

        // TODO: Implement actual upload
        this.showMessage('Profile picture upload functionality will be implemented', 'info');
    }

    async loadSettings() {
        try {
            // First try to load from server
            const response = await fetch('api/teacher_settings.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.settings = data.data;
                    return;
                }
            }
        } catch (error) {
            console.error('Error loading settings from server:', error);
        }

        // Fallback to localStorage
        try {
            const saved = localStorage.getItem('teacherSettings');
            if (saved) {
                this.settings = JSON.parse(saved);
            }
        } catch (error) {
            console.error('Error loading settings from localStorage:', error);
        }
    }

    getStoredSettings(section) {
        return this.settings[section] || {};
    }

    updateLastSaved() {
        const lastSavedElement = document.getElementById('last-saved');
        if (lastSavedElement) {
            const now = new Date();
            lastSavedElement.textContent = `Last saved: ${now.toLocaleString()}`;
        }
    }

    showMessage(message, type = 'info') {
        // Remove existing messages
        document.querySelectorAll('.settings-message').forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `settings-message ${type}`;
        messageDiv.textContent = message;

        // Insert at the top of the settings content
        const settingsContent = document.querySelector('.bg-white.shadow.rounded-lg');
        if (settingsContent) {
            settingsContent.insertBefore(messageDiv, settingsContent.firstChild);
        }

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Utility methods
    getInputValue(id) {
        const element = document.getElementById(id);
        return element ? element.value : '';
    }

    setInputValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.value = value;
        }
    }

    getCheckboxValue(id) {
        const element = document.getElementById(id);
        return element ? element.checked : false;
    }

    setCheckboxValue(id, checked) {
        const element = document.getElementById(id);
        if (element) {
            element.checked = checked;
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.teacherSettings = new TeacherSettings();
});

// Export for potential use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TeacherSettings;
}
