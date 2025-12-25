// Add User Modal functionality
function handleRoleChange(event) {
    const role = event.target.value;
    const form = event.target.form;
    const templateSelect = form.querySelector('[name="template_id"]');
    const options = templateSelect.options;
    const permissionsSection = form.querySelector('[x-show="!selectedTemplate"]');
    
    // Reset template selection when role changes
    templateSelect.value = '';
    
    // Show/hide template options based on role
    let hasVisibleOptions = false;
    for (let i = 1; i < options.length; i++) {
        const option = options[i];
        const isMatchingRole = option.dataset.role === role;
        option.style.display = isMatchingRole ? '' : 'none';
        option.disabled = !isMatchingRole;
        if (isMatchingRole) hasVisibleOptions = true;
    }

    // Show/hide "Custom Permissions" option based on role selection
    options[0].style.display = role ? '' : 'none';
    options[0].disabled = !role;

    // Show permissions section if a role is selected and no template is chosen
    if (permissionsSection) {
        if (!role) {
            permissionsSection.style.display = 'none';
        } else {
            permissionsSection.style.display = templateSelect.value ? 'none' : '';
        }
    }

    // Update template select styling
    templateSelect.disabled = !role;
    if (!role) {
        templateSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
    } else {
        templateSelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
    }

    // Update help text
    const helpText = form.querySelector('[data-help-text="template"]');
    if (helpText) {
        if (!role) {
            helpText.textContent = 'Please select a role first';
            helpText.classList.add('text-gray-400');
        } else if (!hasVisibleOptions) {
            helpText.textContent = 'No templates available for this role';
            helpText.classList.add('text-gray-400');
        } else {
            helpText.textContent = 'Choose a template or customize permissions below';
            helpText.classList.remove('text-gray-400');
        }
    }
}

function toggleCategoryPermissions(event, category) {
    event.preventDefault();
    const form = event.target.closest('form');
    const checkboxes = form.querySelectorAll(`input[data-category="${category}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });

    // Update the toggle button text
    const button = event.target.closest('button');
    if (button) {
        button.textContent = !allChecked ? 'Unselect All' : 'Select All';
    }
}

function validateForm(form) {
    const username = form.querySelector('[name="username"]').value.trim();
    const email = form.querySelector('[name="email"]').value.trim();
    const role = form.querySelector('[name="role"]').value;
    const templateId = form.querySelector('[name="template_id"]').value;
    const permissions = form.querySelectorAll('input[name="permissions[]"]:checked');
    
    const errors = [];
    
    // Required field validation
    if (!username) {
        errors.push('Username is required');
    } else if (username.length < 3) {
        errors.push('Username must be at least 3 characters long');
    }
    
    if (!email) {
        errors.push('Email is required');
    } else if (!isValidEmail(email)) {
        errors.push('Please enter a valid email address');
    }
    
    if (!role) {
        errors.push('Please select a role');
    }
    
    // Template or permissions validation
    if (!templateId && permissions.length === 0) {
        errors.push('Please either select a role template or assign specific permissions');
    }
    
    return {
        valid: errors.length === 0,
        errors: errors
    };
}

function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

function confirmAddUser(event) {
    event.preventDefault();
    const form = event.target;
    
    // Validate form
    const validation = validateForm(form);
    if (!validation.valid) {
        Swal.fire({
            title: 'Form Validation Error',
            html: validation.errors.join('<br>'),
            icon: 'error',
            confirmButtonColor: '#0284c7'
        });
        return;
    }
    
    const formData = new FormData(form);
    const templateId = formData.get('template_id');
    const permissions = formData.getAll('permissions[]');
    
    // Format permissions for display
    let permissionsDisplay = '';
    if (templateId) {
        const templateName = form.querySelector(`option[value="${templateId}"]`).textContent;
        permissionsDisplay = `Template: ${templateName.trim()}`;
    } else {
        const categories = {};
        permissions.forEach(perm => {
            const checkbox = form.querySelector(`input[value="${perm}"]`);
            const category = checkbox.dataset.category;
            categories[category] = (categories[category] || 0) + 1;
        });
        
        permissionsDisplay = Object.entries(categories)
            .map(([category, count]) => `${category.replace('_', ' ')} (${count})`)
            .join(', ');
    }

    // Show confirmation dialog
    Swal.fire({
        title: 'Confirm User Creation',
        html: `
            <div class="text-left">
                <p class="mb-2"><strong>Username:</strong> ${formData.get('username')}</p>
                <p class="mb-2"><strong>Email:</strong> ${formData.get('email')}</p>
                <p class="mb-2"><strong>Role:</strong> ${formData.get('role')}</p>
                <p class="mb-2"><strong>Permissions:</strong> ${permissionsDisplay || 'None'}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0284c7',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Create User',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'swal2-confirm',
            cancelButton: 'swal2-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            submitForm(form);
        }
    });
}

function submitForm(form) {
    // Show loading state
    Swal.fire({
        title: 'Creating User',
        text: 'Please wait while we create the user account...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Submit the form
    form.submit();
}

// Initialize form on page load
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're in the new modal structure (Alpine.js based)
    const alpineModal = document.querySelector('[x-data="addUserWizard"]');
    if (alpineModal) {
        // New modal structure - Alpine.js handles everything
        console.log('Alpine.js modal detected - no additional JS needed');
        return;
    }
    
    // Old modal structure - keep existing logic
    const roleSelect = document.querySelector('select[name="role"]');
    if (roleSelect) {
        // Initialize role dropdown state
        handleRoleChange({ target: roleSelect });
        
        // Add change event listener
        roleSelect.addEventListener('change', handleRoleChange);
    }
    
    // Add form validation listeners
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        // Add input validation styling
        const inputs = addUserForm.querySelectorAll('input[required], select[required]');
        inputs.forEach(input => {
            // Add initial validation state
            if (input.hasAttribute('required') && !input.value) {
                input.classList.add('border-gray-300');
            }
            
            input.addEventListener('invalid', (e) => {
                e.preventDefault();
                input.classList.remove('border-gray-300');
                input.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                
                // Add error message
                const errorDiv = input.parentNode.querySelector('.error-message');
                if (!errorDiv) {
                    const div = document.createElement('div');
                    div.className = 'error-message mt-1 text-sm text-red-600';
                    div.textContent = input.validationMessage || 'This field is required';
                    input.parentNode.appendChild(div);
                }
            });
            
            input.addEventListener('input', () => {
                input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                input.classList.add('border-gray-300');
                const errorDiv = input.parentNode.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.remove();
                }
            });
        });

        // Add template change listener
        const templateSelect = addUserForm.querySelector('[name="template_id"]');
        if (templateSelect && roleSelect) {
            // Initialize template select state
            templateSelect.disabled = !roleSelect.value;
            if (!roleSelect.value) {
                templateSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            }
            
            templateSelect.addEventListener('change', (e) => {
                const permissionsSection = addUserForm.querySelector('[x-show="!selectedTemplate"]');
                if (permissionsSection) {
                    permissionsSection.style.display = e.target.value ? 'none' : '';
                }
                
                // Clear all permission checkboxes when template is selected
                if (e.target.value) {
                    const checkboxes = addUserForm.querySelectorAll('input[name="permissions[]"]');
                    checkboxes.forEach(checkbox => checkbox.checked = false);
                }
            });
        }
    }
}); 