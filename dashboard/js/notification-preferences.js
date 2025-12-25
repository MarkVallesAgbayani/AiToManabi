/**
 * Notification Preferences Management JavaScript
 * Handles saving and loading notification preferences
 */

class NotificationPreferencesManager {
    constructor() {
        this.apiUrl = 'api/notification_preferences_api.php';
        this.preferences = {};
        this.isLoading = false;
        this.init();
    }

    init() {
        this.loadPreferences();
        this.bindEvents();
        console.log('Notification Preferences Manager initialized');
    }

    bindEvents() {
        // Handle checkbox changes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('notification-checkbox')) {
                this.handlePreferenceChange(e.target);
            }
        });

        // Handle frequency radio changes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('frequency-radio')) {
                this.handleFrequencyChange(e.target);
            }
        });

        // Handle save all button
        const saveButton = document.getElementById('save-settings');
        if (saveButton) {
            saveButton.addEventListener('click', () => {
                this.saveAllPreferences();
            });
        }

        // Handle reset button
        const resetButton = document.getElementById('reset-settings');
        if (resetButton) {
            resetButton.addEventListener('click', () => {
                this.resetToDefaults();
            });
        }
    }

    async loadPreferences() {
        try {
            this.isLoading = true;
            this.showLoadingState();

            const response = await fetch(`${this.apiUrl}?action=get_preferences`);
            const data = await response.json();

            if (data.success) {
                this.preferences = data.preferences;
                this.populatePreferences();
            } else {
                this.showErrorMessage('Failed to load preferences');
            }
        } catch (error) {
            console.error('Error loading preferences:', error);
            this.showErrorMessage('Error loading preferences');
        } finally {
            this.isLoading = false;
            this.hideLoadingState();
        }
    }

    populatePreferences() {
        // Populate checkboxes
        Object.keys(this.preferences).forEach(category => {
            Object.keys(this.preferences[category]).forEach(key => {
                const checkbox = document.querySelector(`[data-category="${category}"][data-key="${key}"]`);
                if (checkbox) {
                    checkbox.checked = this.preferences[category][key].is_enabled;
                }
            });
        });

        // Set default frequency (most common frequency from preferences)
        const frequencies = {};
        Object.keys(this.preferences).forEach(category => {
            Object.keys(this.preferences[category]).forEach(key => {
                const freq = this.preferences[category][key].frequency;
                frequencies[freq] = (frequencies[freq] || 0) + 1;
            });
        });

        const mostCommonFrequency = Object.keys(frequencies).reduce((a, b) => 
            frequencies[a] > frequencies[b] ? a : b, 'real_time'
        );

        const frequencyRadio = document.querySelector(`[name="notification-frequency"][value="${mostCommonFrequency}"]`);
        if (frequencyRadio) {
            frequencyRadio.checked = true;
        }
    }

    async handlePreferenceChange(checkbox) {
        const category = checkbox.dataset.category;
        const key = checkbox.dataset.key;
        const enabled = checkbox.checked;

        try {
            this.showSavingState(checkbox);

            const formData = new FormData();
            formData.append('action', 'save_single_preference');
            formData.append('category', category);
            formData.append('key', key);
            formData.append('enabled', enabled ? '1' : '0');

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSavedState(checkbox);
                this.updatePreferences(category, key, enabled);
                this.showSuccessMessage('Preference saved');
            } else {
                checkbox.checked = !enabled; // Revert on error
                this.showErrorMessage('Failed to save preference');
            }
        } catch (error) {
            console.error('Error saving preference:', error);
            checkbox.checked = !enabled; // Revert on error
            this.showErrorMessage('Error saving preference');
        } finally {
            this.hideSavingState(checkbox);
        }
    }

    async handleFrequencyChange(radio) {
        const frequency = radio.value;
        
        // Update all preferences with this frequency
        const checkboxes = document.querySelectorAll('.notification-checkbox:checked');
        const promises = [];

        checkboxes.forEach(checkbox => {
            const category = checkbox.dataset.category;
            const key = checkbox.dataset.key;
            
            promises.push(this.updatePreferenceFrequency(category, key, frequency));
        });

        try {
            await Promise.all(promises);
            this.showSuccessMessage('Frequency updated for all enabled preferences');
        } catch (error) {
            console.error('Error updating frequency:', error);
            this.showErrorMessage('Error updating frequency');
        }
    }

    async updatePreferenceFrequency(category, key, frequency) {
        const formData = new FormData();
        formData.append('action', 'save_single_preference');
        formData.append('category', category);
        formData.append('key', key);
        formData.append('enabled', '1');
        formData.append('frequency', frequency);

        const response = await fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        });

        return response.json();
    }

    async saveAllPreferences() {
        try {
            this.isLoading = true;
            this.showLoadingState();

            const preferences = this.collectAllPreferences();
            
            const formData = new FormData();
            formData.append('action', 'save_preferences');
            formData.append('preferences', JSON.stringify(preferences));

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccessMessage(`Successfully saved ${data.saved_count} preferences`);
                this.preferences = preferences;
            } else {
                this.showErrorMessage('Failed to save preferences');
            }
        } catch (error) {
            console.error('Error saving preferences:', error);
            this.showErrorMessage('Error saving preferences');
        } finally {
            this.isLoading = false;
            this.hideLoadingState();
        }
    }

    async resetToDefaults() {
        if (!confirm('Are you sure you want to reset all notification preferences to defaults? This action cannot be undone.')) {
            return;
        }

        try {
            this.isLoading = true;
            this.showLoadingState();

            const formData = new FormData();
            formData.append('action', 'reset_to_defaults');

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.preferences = data.preferences;
                this.populatePreferences();
                this.showSuccessMessage('Preferences reset to defaults');
            } else {
                this.showErrorMessage('Failed to reset preferences');
            }
        } catch (error) {
            console.error('Error resetting preferences:', error);
            this.showErrorMessage('Error resetting preferences');
        } finally {
            this.isLoading = false;
            this.hideLoadingState();
        }
    }

    collectAllPreferences() {
        const preferences = {};
        const checkboxes = document.querySelectorAll('.notification-checkbox');
        const frequency = document.querySelector('[name="notification-frequency"]:checked')?.value || 'real_time';

        checkboxes.forEach(checkbox => {
            const category = checkbox.dataset.category;
            const key = checkbox.dataset.key;
            const enabled = checkbox.checked;

            if (!preferences[category]) {
                preferences[category] = {};
            }

            preferences[category][key] = {
                enabled: enabled,
                method: 'in_app',
                priority: this.determinePriority(category, key),
                frequency: frequency
            };
        });

        return preferences;
    }

    determinePriority(category, key) {
        const priorityMap = {
            'student_progress': {
                'new_enrollments': 'high',
                'course_completions': 'high',
                'quiz_completions': 'medium',
                'low_performance_alerts': 'high',
                'struggling_students': 'high',
                'weekly_progress_summaries': 'medium'
            },
            'student_engagement': {
                'inactive_students': 'medium',
                'high_performing_students': 'medium'
            },
            'course_management': {
                'course_milestones': 'medium',
                'course_status_changes': 'high'
            },
            'system_administrative': {
                'security_alerts': 'critical'
            },
            'reporting_analytics': {
                'daily_activity_summaries': 'low',
                'weekly_engagement_reports': 'medium'
            }
        };

        return priorityMap[category]?.[key] || 'medium';
    }

    updatePreferences(category, key, enabled) {
        if (!this.preferences[category]) {
            this.preferences[category] = {};
        }
        if (!this.preferences[category][key]) {
            this.preferences[category][key] = {};
        }
        this.preferences[category][key].is_enabled = enabled;
    }

    showLoadingState() {
        const saveButton = document.getElementById('save-settings');
        const resetButton = document.getElementById('reset-settings');
        
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Loading...';
        }
        
        if (resetButton) {
            resetButton.disabled = true;
        }
    }

    hideLoadingState() {
        const saveButton = document.getElementById('save-settings');
        const resetButton = document.getElementById('reset-settings');
        
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Save All Changes';
        }
        
        if (resetButton) {
            resetButton.disabled = false;
        }
    }

    showSavingState(element) {
        element.closest('.notification-preference-item').classList.add('preference-saving');
    }

    hideSavingState(element) {
        element.closest('.notification-preference-item').classList.remove('preference-saving');
    }

    showSavedState(element) {
        const item = element.closest('.notification-preference-item');
        item.classList.add('preference-saved');
        setTimeout(() => {
            item.classList.remove('preference-saved');
        }, 600);
    }

    showSuccessMessage(message) {
        this.showMessage(message, 'success');
    }

    showErrorMessage(message) {
        this.showMessage(message, 'error');
    }

    showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.notification-preference-message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageEl = document.createElement('div');
        messageEl.className = `notification-preference-message fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        }`;
        messageEl.textContent = message;
        document.body.appendChild(messageEl);

        // Remove after 3 seconds
        setTimeout(() => {
            messageEl.remove();
        }, 3000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the settings page
    if (document.querySelector('.notification-checkbox')) {
        window.notificationPreferencesManager = new NotificationPreferencesManager();
    }
});
