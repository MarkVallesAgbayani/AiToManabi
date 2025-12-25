class QuestionTypeManager {
    constructor() {
        this.teacherId = window.teacherId || null;
        this.savedTypes = new Set();
        this.availableTypes = {};
        this.defaultTypes = ['multiple_choice', 'true_false', 'pronunciation'];
        this.apiUrl = './api/question_types_api.php'; // Fixed path with ./
        this.isLoading = false;
        
        // Initialize
        this.init();
    }

    async init() {
        try {
            await this.loadAvailableTypes();
            await this.loadSavedTypes();
            this.updateDropdown();
        } catch (error) {
            console.error('Failed to initialize QuestionTypeManager:', error);
            this.showNotification('Failed to load question types', 'error');
            // Fallback to localStorage if database fails
            this.loadSavedTypesFromStorage();
        }
    }

    // Load available question types from server
    async loadAvailableTypes() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_available_types'
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's valid JSON
            const responseText = await response.text();
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Response is not valid JSON:', responseText);
                throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}...`);
            }
            
            if (data.success) {
                this.availableTypes = data.available_types;
            } else {
                throw new Error(data.message || 'Failed to load available types');
            }
        } catch (error) {
            console.error('Error loading available types:', error);
            throw error;
        }
    }

    // Load saved question types from database
    async loadSavedTypes() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_saved_types'
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's valid JSON
            const responseText = await response.text();
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Response is not valid JSON:', responseText);
                throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}...`);
            }
            
            if (data.success) {
                this.savedTypes = new Set([...this.defaultTypes, ...(data.saved_types || [])]);
            } else {
                throw new Error(data.message || 'Failed to load saved types');
            }
        } catch (error) {
            console.error('Error loading saved types:', error);
            throw error;
        }
    }

    // Fallback: Load from localStorage if database fails
    loadSavedTypesFromStorage() {
        try {
            const storageKey = `question_types_${this.teacherId}`;
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                const types = JSON.parse(saved);
                this.savedTypes = new Set([...this.defaultTypes, ...types]);
            } else {
                this.savedTypes = new Set(this.defaultTypes);
            }
        } catch (error) {
            console.error('Error loading from localStorage:', error);
            this.savedTypes = new Set(this.defaultTypes);
        }
        this.updateDropdown();
    }

    // Add a question type to database
    async addQuestionType(typeId) {
        if (!typeId || this.isLoading) return false;
        
        try {
            this.isLoading = true;
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_type&question_type_id=${encodeURIComponent(typeId)}`
            });
            
            const data = await response.json();
            if (data.success) {
                this.savedTypes.add(typeId);
                this.updateDropdown();
                this.showNotification(data.message || 'Question type added successfully', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Failed to add question type');
            }
        } catch (error) {
            console.error('Error adding question type:', error);
            this.showNotification('Failed to add question type', 'error');
            return false;
        } finally {
            this.isLoading = false;
        }
    }

    // Remove a question type from database
    async removeQuestionType(typeId) {
        if (this.defaultTypes.includes(typeId)) {
            this.showNotification('Cannot remove default question type', 'warning');
            return false;
        }
        
        if (this.isLoading) return false;
        
        try {
            this.isLoading = true;
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_type&question_type_id=${encodeURIComponent(typeId)}`
            });
            
            const data = await response.json();
            if (data.success) {
                this.savedTypes.delete(typeId);
                this.updateDropdown();
                this.showNotification(data.message || 'Question type removed successfully', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Failed to remove question type');
            }
        } catch (error) {
            console.error('Error removing question type:', error);
            this.showNotification('Failed to remove question type', 'error');
            return false;
        } finally {
            this.isLoading = false;
        }
    }

    // Reset to default types
    async resetToDefaults() {
        if (!confirm('Are you sure you want to reset to default question types? This will remove all your custom types.')) {
            return false;
        }
        
        if (this.isLoading) return false;
        
        try {
            this.isLoading = true;
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reset_to_defaults'
            });
            
            const data = await response.json();
            if (data.success) {
                this.savedTypes = new Set(this.defaultTypes);
                this.updateDropdown();
                this.showNotification(data.message || 'Reset to defaults successfully', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Failed to reset to defaults');
            }
        } catch (error) {
            console.error('Error resetting to defaults:', error);
            this.showNotification('Failed to reset to defaults', 'error');
            return false;
        } finally {
            this.isLoading = false;
        }
    }

    // Get all saved question types
    getSavedTypes() {
        return Array.from(this.savedTypes);
    }

    // Get custom (non-default) question types
    getCustomTypes() {
        return Array.from(this.savedTypes).filter(type => 
            !this.defaultTypes.includes(type)
        );
    }

    // Check if a type is saved
    isTypeSaved(typeId) {
        return this.savedTypes.has(typeId);
    }

    // Update the question type dropdown
    updateDropdown() {
        const dropdown = document.getElementById('questionType');
        if (!dropdown) return;

        // Store current selection
        const currentValue = dropdown.value;

        // Clear existing options except defaults
        const options = dropdown.querySelectorAll('option');
        options.forEach(option => {
            if (!this.defaultTypes.includes(option.value)) {
                option.remove();
            }
        });

        // Add saved custom types
        this.getCustomTypes().forEach(typeId => {
            const displayName = this.getQuestionTypeDisplayName(typeId);
            const option = document.createElement('option');
            option.value = typeId;
            option.textContent = displayName;
            dropdown.appendChild(option);
        });

        // Restore selection if still valid
        if (Array.from(dropdown.options).some(opt => opt.value === currentValue)) {
            dropdown.value = currentValue;
        }

        // Trigger change event to update UI
        dropdown.dispatchEvent(new Event('change'));
    }

    // Public method to refresh dropdown from outside
    async refreshDropdown() {
        await this.loadSavedTypes();
        this.updateDropdown();
    }

    // Show question type management UI
    showManagementUI() {
        const modal = this.createManagementModal();
        document.body.appendChild(modal);
        
        // Render the types
        this.renderAvailableTypes();
        this.renderSavedTypes();

        // Focus search input
        setTimeout(() => {
            const searchInput = document.getElementById('questionTypeManagementSearch');
            if (searchInput) searchInput.focus();
        }, 100);
    }

    // Create the management modal
    createManagementModal() {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[80] overflow-y-auto';
        modal.id = 'questionTypeManagementModal';

        modal.innerHTML = `
            <div class="bg-white rounded-lg w-full max-w-5xl mx-4 my-8 shadow-2xl">
                <!-- Header -->
                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold">Manage Question Types</h3>
                        <button type="button" id="closeQuestionTypeManagement" class="text-white hover:text-gray-200 text-2xl">×</button>
                    </div>
                    
                    <!-- Search -->
                    <div class="mb-4">
                        <input type="text" id="questionTypeManagementSearch" placeholder="Search question types..." 
                               class="w-full px-4 py-2 rounded-lg border-0 text-gray-800 placeholder-gray-500">
                    </div>
                </div>
                
                <!-- Content -->
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Available Types -->
                        <div>
                            <h4 class="text-lg font-semibold mb-4 text-gray-800">Available Question Types</h4>
                            <div id="availableTypesList" class="space-y-2 max-h-96 overflow-y-auto">
                                <!-- Available types will be rendered here -->
                            </div>
                        </div>
                        
                        <!-- Saved Types -->
                        <div>
                            <h4 class="text-lg font-semibold mb-4 text-gray-800">Your Saved Types</h4>
                            <div id="savedTypesList" class="space-y-2 max-h-96 overflow-y-auto">
                                <!-- Saved types will be rendered here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex justify-between items-center">
                    <button type="button" id="resetToDefaults" class="btn bg-orange-500 text-white hover:bg-orange-600">
                        <i class="fas fa-undo mr-2"></i> Reset to Defaults
                    </button>
                    <div class="flex space-x-3">
                        <button type="button" id="cancelQuestionTypeManagement" class="btn btn-secondary">Cancel</button>
                        <button type="button" id="saveQuestionTypeManagement" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        `;

        this.setupModalEventListeners(modal);
        return modal;
    }

    // Render available question types
    renderAvailableTypes() {
        const container = document.getElementById('availableTypesList');
        if (!container) return;

        const searchTerm = document.getElementById('questionTypeManagementSearch')?.value.toLowerCase() || '';
        
        container.innerHTML = '';

        // Group by category
        const categories = {};
        Object.values(this.availableTypes).forEach(type => {
            if (!categories[type.category]) {
                categories[type.category] = [];
            }
            categories[type.category].push(type);
        });

        Object.keys(categories).sort().forEach(category => {
            const categoryDiv = document.createElement('div');
            categoryDiv.className = 'mb-4';
            
            categoryDiv.innerHTML = `
                <h5 class="text-sm font-semibold text-gray-600 mb-2 uppercase tracking-wide">${category}</h5>
                <div class="space-y-1">
                    ${categories[category]
                        .filter(type => !searchTerm || 
                            type.name.toLowerCase().includes(searchTerm) || 
                            type.description.toLowerCase().includes(searchTerm))
                        .map(type => `
                            <div class="question-type-card p-3 border rounded-lg hover:bg-gray-50 cursor-pointer ${this.isTypeSaved(type.id) ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200'}" 
                                 data-type-id="${type.id}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h6 class="font-medium text-gray-900">${type.name}</h6>
                                        <p class="text-xs text-gray-600 mt-1">${type.description}</p>
                                    </div>
                                    <div class="ml-3">
                                        ${this.isTypeSaved(type.id) 
                                            ? '<i class="fas fa-check-circle text-green-500"></i>' 
                                            : '<i class="fas fa-plus-circle text-blue-500"></i>'}
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                </div>
            `;
            
            container.appendChild(categoryDiv);
        });

        // Add click handlers
        container.querySelectorAll('.question-type-card').forEach(card => {
            card.addEventListener('click', async (e) => {
                const typeId = card.dataset.typeId;
                if (this.isTypeSaved(typeId)) {
                    if (this.defaultTypes.includes(typeId)) {
                        this.showNotification('Cannot remove default question type', 'warning');
                        return;
                    }
                    await this.removeQuestionType(typeId);
                } else {
                    await this.addQuestionType(typeId);
                }
                this.renderAvailableTypes();
                this.renderSavedTypes();
                this.updateDropdown();
            });
        });
    }

    // Render saved question types
    renderSavedTypes() {
        const container = document.getElementById('savedTypesList');
        if (!container) return;

        container.innerHTML = '';

        if (this.savedTypes.size === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">No question types saved yet.</p>';
            return;
        }

        const savedArray = Array.from(this.savedTypes).map(typeId => {
            const type = this.availableTypes[typeId];
            return type ? { ...type, id: typeId } : { id: typeId, name: this.getQuestionTypeDisplayName(typeId), category: 'Unknown', description: '' };
        });

        // Group by category
        const categories = {};
        savedArray.forEach(type => {
            if (!categories[type.category]) {
                categories[type.category] = [];
            }
            categories[type.category].push(type);
        });

        Object.keys(categories).sort().forEach(category => {
            const categoryDiv = document.createElement('div');
            categoryDiv.className = 'mb-4';
            
            categoryDiv.innerHTML = `
                <h5 class="text-sm font-semibold text-gray-600 mb-2 uppercase tracking-wide">${category}</h5>
                <div class="space-y-1">
                    ${categories[category].map(type => `
                        <div class="question-type-card p-3 border border-blue-200 bg-blue-50 rounded-lg">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h6 class="font-medium text-gray-900">${type.name}</h6>
                                    <p class="text-xs text-gray-600 mt-1">${type.description}</p>
                                    ${this.defaultTypes.includes(type.id) ? '<span class="text-xs text-blue-600 font-medium">Default</span>' : ''}
                                </div>
                                <div class="ml-3">
                                    ${this.defaultTypes.includes(type.id) 
                                        ? '<i class="fas fa-lock text-gray-400" title="Default type - cannot be removed"></i>' 
                                        : `<button class="text-red-500 hover:text-red-700" onclick="window.questionTypeManager.removeQuestionType('${type.id}').then(() => { window.questionTypeManager.renderAvailableTypes(); window.questionTypeManager.renderSavedTypes(); })" title="Remove"><i class="fas fa-trash"></i></button>`}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            container.appendChild(categoryDiv);
        });
    }

    // Setup event listeners for the modal
    setupModalEventListeners(modal) {
        // Close modal
        modal.querySelector('#closeQuestionTypeManagement').addEventListener('click', async () => {
            // Reload saved types from database to ensure we have the latest
            await this.loadSavedTypes();
            this.updateDropdown();
            modal.remove();
        });

        modal.querySelector('#cancelQuestionTypeManagement').addEventListener('click', async () => {
            // Reload saved types from database to ensure we have the latest
            await this.loadSavedTypes();
            this.updateDropdown();
            modal.remove();
        });

        // Save and close (no longer needed since we save in real-time)
        modal.querySelector('#saveQuestionTypeManagement').addEventListener('click', async () => {
            // Reload saved types from database to ensure we have the latest
            await this.loadSavedTypes();
            this.updateDropdown();
            this.showNotification('Question types are automatically saved!', 'success');
            modal.remove();
        });

        // Reset to defaults
        modal.querySelector('#resetToDefaults').addEventListener('click', async () => {
            const success = await this.resetToDefaults();
            if (success) {
                this.renderAvailableTypes();
                this.renderSavedTypes();
                this.updateDropdown();
            }
        });

        // Search functionality
        modal.querySelector('#questionTypeManagementSearch').addEventListener('input', (e) => {
            this.renderAvailableTypes(); // Re-render with search filter
        });
    }

    // Setup global event listeners  
    setupEventListeners() {
        // Add management button when dropdown is available
        const observer = new MutationObserver(() => {
            this.addManagementButton();
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
        
        // Initial setup
        setTimeout(() => {
            this.addManagementButton();
            this.updateDropdown();
        }, 1000);
    }

    // Add the management button to the UI
    addManagementButton() {
        const dropdown = document.getElementById('questionType');
        if (!dropdown || document.getElementById('manageQuestionTypesBtn')) return;

        const container = dropdown.parentElement;
        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'manageQuestionTypesBtn';
        button.className = 'btn bg-purple-500 text-white hover:bg-purple-600 text-sm mt-2 w-full';
        button.innerHTML = '<i class="fas fa-cog mr-2"></i> Manage Question Types';
        
        button.addEventListener('click', () => {
            this.showManagementUI();
        });

        container.appendChild(button);
    }

    // Get all available question types (excluding Character Writing)
    getAllQuestionTypes() {
        return [
            // Basic Question Types
            {
                id: 'multiple_choice',
                name: 'Multiple Choice',
                category: 'Basic',
                description: 'Traditional multiple choice with 2-6 options',
                capabilities: ['Text'],
                difficulty: 'Easy',
                icon: '📝'
            },
            {
                id: 'true_false',
                name: 'True/False',
                category: 'Basic',
                description: 'Simple true or false questions',
                capabilities: ['Text'],
                difficulty: 'Easy',
                icon: '✓'
            },
            {
                id: 'fill_blank',
                name: 'Fill in the Blank',
                category: 'Basic',
                description: 'Students fill in missing words or characters',
                capabilities: ['Text'],
                difficulty: 'Medium',
                icon: '📄'
            },
            
            // Vocabulary Question Types
            {
                id: 'word_definition',
                name: 'Word Definition',
                category: 'Vocabulary',
                description: 'Match words with their correct definitions',
                capabilities: ['Text'],
                difficulty: 'Medium',
                icon: '📚'
            },
            
            // Audio & Pronunciation
            {
                id: 'pronunciation',
                name: 'Pronunciation Check',
                category: 'Audio',
                description: 'Students pronounce Japanese words for accuracy check',
                capabilities: ['Audio', 'Speech Recognition'],
                difficulty: 'Medium',
                icon: '🎤'
            },
            
            // Writing & Translation (Character Writing removed as requested)
            {
                id: 'sentence_translation',
                name: 'Sentence Translation',
                category: 'Writing',
                description: 'Translate between Japanese and English',
                capabilities: ['Text'],
                difficulty: 'Hard',
                icon: '🌐'
            },
        ];
    }

    // Helper functions
    getDifficultyBadgeClass(difficulty) {
        switch(difficulty) {
            case 'Easy': return 'bg-green-100 text-green-700';
            case 'Medium': return 'bg-yellow-100 text-yellow-700';
            case 'Hard': return 'bg-red-100 text-red-700';
            default: return 'bg-gray-100 text-gray-700';
        }
    }

    getQuestionTypeDisplayName(typeId) {
        const type = this.getAllQuestionTypes().find(t => t.id === typeId);
        return type ? type.name : typeId;
    }

    showNotification(message, type = 'info') {
        // Use existing notification system if available
        if (window.showNotification) {
            window.showNotification(message, type);
            return;
        }

        // Fallback notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-[100] ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    // Additional helper functions
    getQuestionTypeDisplayName(typeId) {
        if (this.availableTypes[typeId]) {
            return this.availableTypes[typeId].name;
        }
        
        // Fallback for known types
        const displayNames = {
            'multiple_choice': 'Multiple Choice',
            'true_false': 'True/False',
            'pronunciation': 'Pronunciation Check',
            'fill_blank': 'Fill in the Blank',
            'word_definition': 'Word Definition',
            'sentence_translation': 'Sentence Translation'
        };
        
        return displayNames[typeId] || typeId;
    }

    showNotification(message, type = 'info') {
        // Use existing notification system if available
        if (window.showNotification) {
            window.showNotification(message, type);
            return;
        }
        
        // Fallback notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
        }`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    // Setup global event listeners  
    setupEventListeners() {
        // Add management button when dropdown is available
        const observer = new MutationObserver(() => {
            this.addManagementButton();
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
        
        // Try to add the button immediately if dropdown exists
        this.addManagementButton();
    }

    // Add the management button to the UI
    addManagementButton() {
        const dropdown = document.getElementById('questionType');
        const existingBtn = document.getElementById('manageQuestionTypesBtn');
        
        // If button already exists in HTML, just add event listener
        if (existingBtn && !existingBtn.hasAttribute('data-listener-added')) {
            existingBtn.addEventListener('click', () => {
                this.showManagementUI();
            });
            existingBtn.setAttribute('data-listener-added', 'true');
            return;
        }
        
        // Fallback: create button if not found in HTML
        if (!dropdown || existingBtn) return;

        const container = dropdown.parentElement;
        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'manageQuestionTypesBtn';
        button.className = 'btn bg-purple-500 text-white hover:bg-purple-600 text-sm mt-2 w-full';
        button.innerHTML = '<i class="fas fa-cog mr-2"></i> Manage Question Types';
        
        button.addEventListener('click', () => {
            this.showManagementUI();
        });

        container.appendChild(button);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Make sure teacherId is available
    window.teacherId = window.teacherId || 'default';
    
    // Initialize the question type manager
    window.questionTypeManager = new QuestionTypeManager();
    
    // Global function to refresh dropdown from anywhere
    window.refreshQuestionTypeDropdown = () => {
        if (window.questionTypeManager) {
            window.questionTypeManager.refreshDropdown();
        }
    };
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = QuestionTypeManager;
}
