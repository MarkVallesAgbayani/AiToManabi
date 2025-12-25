// Signup Page JavaScript
// =====================

// Global variables
let hasScrolledToBottom = false;
let termsAccepted = false;

// Validation helper functions
function isValidEmail(email) {
    // Enhanced email validation regex - more strict and accurate
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    
    // Additional validation checks
    if (!emailRegex.test(email)) {
        return false;
    }
    
    // Check for valid TLD (at least 2 characters)
    const parts = email.split('.');
    const tld = parts[parts.length - 1];
    if (tld.length < 2 || tld.length > 10) {
        return false;
    }
    
    // Check for consecutive dots
    if (email.includes('..')) {
        return false;
    }
    
    // Check email length
    if (email.length > 254) {
        return false;
    }
    
    // Check local part length (before @)
    const localPart = email.split('@')[0];
    if (localPart.length > 64) {
        return false;
    }
    
    return true;
}

function isValidUsername(username) {
    // Username should be 3-20 characters, alphanumeric and underscores only
    const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
    return usernameRegex.test(username);
}

// Phone number validation and formatting functions
function getPhoneValidationRules(countryCode) {
    // Only Philippines is supported
    return {
        formats: [
            /^09\d{9}$/, // Local format: 09XXXXXXXXX (11 digits total)
            /^9\d{9}$/,  // Without leading 0: 9XXXXXXXXX (10 digits total)
            /^\+639\d{9}$/, // International format: +639XXXXXXXXX
            /^639\d{9}$/    // Without plus: 639XXXXXXXXX
        ],
        formatDisplay: '9XX XXX XXXX or 09XX XXX XXXX',
        maxLength: 13,  // For +639XXXXXXXXX
        minLength: 10   // For 9XXXXXXXXX
    };
}

// Format phone number as user types - Philippines only, numbers only
function formatPhoneNumber(value, countryCode) {
    // Remove all non-digit characters (strict numeric only)
    let cleaned = value.replace(/[^\d]/g, '');
    
    // Handle country code prefix if user types +63 or 63
    if (cleaned.startsWith('63') && cleaned.length > 2) {
        cleaned = cleaned.substring(2); // Remove country code if user types it
    }
    
    // Support both formats without auto-conversion
    if (cleaned.startsWith('09')) {
        // Format: 09XX XXX XXXX (11 digits total)
        if (cleaned.length > 11) {
            cleaned = cleaned.substring(0, 11);
        }
        if (cleaned.length <= 4) return cleaned;
        if (cleaned.length <= 7) return cleaned.substring(0, 4) + ' ' + cleaned.substring(4);
        return cleaned.substring(0, 4) + ' ' + cleaned.substring(4, 7) + ' ' + cleaned.substring(7);
    } else if (cleaned.startsWith('9')) {
        // Format: 9XX XXX XXXX (10 digits total)
        if (cleaned.length > 10) {
            cleaned = cleaned.substring(0, 10);
        }
        if (cleaned.length <= 3) return cleaned;
        if (cleaned.length <= 6) return cleaned.substring(0, 3) + ' ' + cleaned.substring(3);
        return cleaned.substring(0, 3) + ' ' + cleaned.substring(3, 6) + ' ' + cleaned.substring(6);
    } else {
        // For other inputs starting with different digits, just return as-is (user might be typing)
        return cleaned;
    }
}

// Convert display format to API format (+639XXXXXXXXX)
function convertToAPIFormat(phoneNumber) {
    // Remove all non-digit characters
    let cleaned = phoneNumber.replace(/[^\d]/g, '');
    
    console.log('🔄 convertToAPIFormat - Input:', phoneNumber);
    console.log('🔄 convertToAPIFormat - Cleaned:', cleaned);
    
    // Remove country code if present
    if (cleaned.startsWith('63')) {
        cleaned = cleaned.substring(2);
        console.log('🔄 convertToAPIFormat - Removed country code:', cleaned);
    }
    
    // Remove leading 0 if present
    if (cleaned.startsWith('0')) {
        cleaned = cleaned.substring(1);
        console.log('🔄 convertToAPIFormat - Removed leading 0:', cleaned);
    }
    
    console.log('🔄 convertToAPIFormat - Final cleaned:', cleaned, 'Length:', cleaned.length, 'Starts with 9:', cleaned.startsWith('9'));
    
    // Should now have 10 digits starting with 9
    if (cleaned.startsWith('9') && cleaned.length === 10) {
        const result = '+63' + cleaned;
        console.log('🔄 convertToAPIFormat - Success:', result);
        return result;
    }
    
    // If it's already 9XXXXXXXXX (10 digits) - this is duplicate but keeping for safety
    if (cleaned.length === 10 && cleaned.startsWith('9')) {
        const result = '+63' + cleaned;
        console.log('🔄 convertToAPIFormat - Success (duplicate check):', result);
        return result;
    }
    
    // Invalid format
    console.log('❌ convertToAPIFormat - Invalid format, returning null');
    return null;
}

// Update phone format hint - Philippines only
function updatePhoneFormatHint(countryCode) {
    const hintElement = document.getElementById('phone-format-hint');
    if (!hintElement) return;
    
    const rules = getPhoneValidationRules(countryCode);
    hintElement.textContent = 'Format: ' + rules.formatDisplay;
}

// Validate phone number format
function validatePhoneNumber(phoneNumber, countryCode) {
    if (!phoneNumber || phoneNumber.trim() === '') {
        return { valid: false, message: 'Contact number is required.' };
    }
    
    const rules = getPhoneValidationRules(countryCode);
    const cleaned = phoneNumber.replace(/[^\d+]/g, '');
    
    // Debug logging
    console.log('🔍 validatePhoneNumber - Original:', phoneNumber);
    console.log('🔍 validatePhoneNumber - Cleaned:', cleaned);
    console.log('🔍 validatePhoneNumber - Length:', cleaned.length);
    console.log('🔍 validatePhoneNumber - Patterns to test:', rules.formats);
    
    // Check if any format matches
    const isValid = rules.formats.some(format => {
        const matches = format.test(cleaned);
        console.log(`🔍 Testing pattern ${format} against "${cleaned}": ${matches}`);
        return matches;
    });
    
    if (!isValid) {
        console.log('❌ validatePhoneNumber - No patterns matched!');
        return { 
            valid: false, 
            message: `Please enter a valid Philippine mobile number. Expected: ${rules.formatDisplay}. Got: ${cleaned} (${cleaned.length} digits)` 
        };
    }
    
    // Additional validation: Must start with 9 after removing country code and leading 0
    const apiFormat = convertToAPIFormat(cleaned);
    if (!apiFormat) {
        return {
            valid: false,
            message: 'Invalid phone number format. Philippine mobile numbers start with 09.'
        };
    }
    
    // Validate that it's a valid Philippine mobile prefix
    const mobilePrefix = apiFormat.substring(3, 6); // Get first 3 digits after +63
    
    // Accept all Philippine mobile number prefixes (9XX format)
    // All Philippine mobile numbers start with 9XX where XX can be any digits
    const prefixPattern = /^9\d{2}$/;
    
    if (!prefixPattern.test(mobilePrefix)) {
        return {
            valid: false,
            message: 'Please enter a valid Philippine mobile number starting with 9.'
        };
    }
    
    return { valid: true, message: 'Valid phone number format', apiFormat: apiFormat };
}

// Enhanced name validation function
function isValidName(name) {
    // Allow letters, spaces, hyphens, and apostrophes only
    // Length between 30-50 characters (enforced separately to show specific messages)
    const nameRegex = /^[a-zA-ZÀ-ÿ\u0100-\u017F\u1E00-\u1EFF\s'-]{1,50}$/;
    
    if (!nameRegex.test(name)) {
        return false;
    }
    
    // Check for consecutive spaces, hyphens, or apostrophes
    if (/[\s'-]{2,}/.test(name)) {
        return false;
    }
    
    // Check that name doesn't start or end with space, hyphen, or apostrophe
    if (/^[\s'-]|[\s'-]$/.test(name)) {
        return false;
    }
    
    return true;
}

// Password strength validation function
function getPasswordStrength(password) {
    let score = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 12) score += 2;
    else if (password.length >= 8) score += 1;
    else feedback.push('Use at least 12 characters');
    
    // Character type checks
    if (/[a-z]/.test(password)) score += 1;
    else feedback.push('Add lowercase letters');
    
    if (/[A-Z]/.test(password)) score += 1;
    else feedback.push('Add uppercase letters');
    
    if (/[0-9]/.test(password)) score += 1;
    else feedback.push('Add numbers');
    
    if (/[^a-zA-Z0-9]/.test(password)) score += 1;
    else feedback.push('Add special characters');
    
    // Advanced checks
    if (password.length >= 16) score += 1;
    if (/[^a-zA-Z0-9].*[^a-zA-Z0-9]/.test(password)) score += 1;
    
    let strength = 'very-weak';
    if (score >= 7) strength = 'very-strong';
    else if (score >= 6) strength = 'strong';
    else if (score >= 4) strength = 'medium';
    else if (score >= 2) strength = 'weak';
    
    return { strength, score, feedback };
}

// Enhanced email validation function with specific error messages
function validateEmailWithFeedback(email) {
    if (!email || email.trim() === '') {
        return { valid: false, message: 'Email address is required.' };
    }
    
    // Check for basic email structure
    if (!email.includes('@')) {
        return { valid: false, message: 'Email must contain @ symbol.' };
    }
    
    // Check for consecutive dots
    if (email.includes('..')) {
        return { valid: false, message: 'Email cannot contain consecutive dots.' };
    }
    
    // Check email length
    if (email.length > 254) {
        return { valid: false, message: 'Email address is too long.' };
    }
    
    // Check local part length (before @)
    const emailParts = email.split('@');
    if (emailParts.length !== 2) {
        return { valid: false, message: 'Please enter a valid email address.' };
    }
    
    const localPart = emailParts[0];
    const domainPart = emailParts[1];
    
    if (localPart.length === 0) {
        return { valid: false, message: 'Email must have content before @ symbol.' };
    }
    
    if (localPart.length > 64) {
        return { valid: false, message: 'Email address format is invalid.' };
    }
    
    if (domainPart.length === 0) {
        return { valid: false, message: 'Email must have a domain after @ symbol.' };
    }
    
    // Check for valid domain structure
    if (!domainPart.includes('.')) {
        return { valid: false, message: 'Email domain must contain a dot.' };
    }
    
    // Check for valid TLD
    const domainParts = domainPart.split('.');
    const tld = domainParts[domainParts.length - 1];
    if (tld.length < 2 || tld.length > 10) {
        return { valid: false, message: 'Please enter a valid email address.' };
    }
    
    // Allow only approved/common email providers
    const allowedDomains = new Set([
        'gmail.com',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'icloud.com',
        'proton.me',
        'protonmail.com',
    ]);
    const domainLower = domainPart.toLowerCase();
    if (!allowedDomains.has(domainLower) && !/\.edu\.ph$/i.test(domainLower)) {
        return { valid: false, message: 'Please use an email from approved providers (e.g., gmail.com, outlook.com).' };
    }

    // Use the existing enhanced regex
    if (!isValidEmail(email)) {
        return { valid: false, message: 'Please enter a valid email address.' };
    }
    
    return { valid: true, message: 'Valid email format' };
}

// Inline validation functions
function showInlineError(fieldName, message, type = 'error') {
    const errorElement = document.getElementById(fieldName + '-error');
    const inputContainer = document.querySelector(`input[name="${fieldName}"]`)?.closest('.modern-input');
    
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.className = `inline-error-message show ${type}`;
    }
    
    if (inputContainer) {
        inputContainer.classList.remove('has-success');
        inputContainer.classList.add('has-error');
    }
    
    // Update form grid spacing
    updateFormGridSpacing();
}

function showInlineSuccess(fieldName, message) {
    const errorElement = document.getElementById(fieldName + '-error');
    const inputContainer = document.querySelector(`input[name="${fieldName}"]`)?.closest('.modern-input');
    
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.className = 'inline-error-message show success';
    }
    
    if (inputContainer) {
        inputContainer.classList.remove('has-error');
        inputContainer.classList.add('has-success');
    }
    
    // Update form grid spacing
    updateFormGridSpacing();
}

function hideInlineError(fieldName) {
    const errorElement = document.getElementById(fieldName + '-error');
    const inputContainer = document.querySelector(`input[name="${fieldName}"]`)?.closest('.modern-input');
    
    if (errorElement) {
        errorElement.className = 'inline-error-message';
        errorElement.textContent = '';
    }
    
    if (inputContainer) {
        inputContainer.classList.remove('has-error', 'has-success');
    }
    
    // Update form grid spacing
    updateFormGridSpacing();
}

// Function to manage form grid spacing based on error presence
function updateFormGridSpacing() {
    const formGrid = document.querySelector('.form-grid');
    if (!formGrid) return;
    
    // Check if any error messages are currently visible in the form grid
    const visibleErrors = formGrid.querySelectorAll('.inline-error-message.show');
    
    if (visibleErrors.length > 0) {
        formGrid.classList.add('has-errors');
    } else {
        formGrid.classList.remove('has-errors');
    }
}

// Check if username exists (AJAX call)
async function checkUsernameExists(username) {
    try {
        const response = await fetch('../auth/check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=check_username&username=${encodeURIComponent(username)}`
        });
        const data = await response.json();
        return data.exists;
    } catch (error) {
        console.error('Error checking username:', error);
        return false;
    }
}

// Check if email exists (AJAX call)
async function checkEmailExists(email) {
    try {
        const response = await fetch('../auth/check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=check_email&email=${encodeURIComponent(email)}`
        });
        const data = await response.json();
        return data.exists;
    } catch (error) {
        console.error('Error checking email:', error);
        return false;
    }
}

// Check if phone number exists (AJAX call)
async function checkPhoneExists(phoneNumber, countryCode) {
    try {
        console.log('📱 checkPhoneExists called with:', { phoneNumber, countryCode });
        
        const requestBody = `action=check_phone&phone_number=${encodeURIComponent(phoneNumber)}&country_code=${encodeURIComponent(countryCode)}`;
        console.log('📱 Request body:', requestBody);
        
        const response = await fetch('../auth/check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: requestBody
        });
        
        if (!response.ok) {
            console.error('❌ HTTP Response not ok:', response.status, response.statusText);
            return false;
        }
        
        const data = await response.json();
        console.log('📱 API Response:', data);
        return data.exists;
    } catch (error) {
        console.error('❌ Error checking phone number:', error);
        return false;
    }
}

// Wait for DOM to be ready and particles.js to load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Initialize particles
    initializeParticles();
    
    // Initialize form functionality
    initializeForm();
    
    
    // Initialize modern alert system
    initializeAlerts();
    
    // Initialize password tooltip
    initializePasswordTooltip();
});

// Particles.js initialization
function initializeParticles() {
    // Check if we're on mobile - disable particles on mobile screens
    if (window.innerWidth <= 767) {
        console.log('Mobile screen detected, particles.js disabled');
        return;
    }
    
    const particlesContainer = document.getElementById('particles-js');
    if (!particlesContainer) {
        console.error('particles-js container not found');
        return;
    }
    
    if (typeof particlesJS === 'undefined') {
        console.error('particlesJS is not loaded');
        return;
    }
    
    console.log('particlesJS is available, loading configuration...');
    
    particlesJS("particles-js", {
        "particles": {
            "number": { 
                "value": 80, 
                "density": { 
                    "enable": true, 
                    "value_area": 800 
                } 
            },
            "color": { "value": "#ffffff" },
            "shape": { 
                "type": "circle",
                "stroke": {
                    "width": 0,
                    "color": "#000000"
                }
            },
            "opacity": { 
                "value": 0.5, 
                "random": false,
                "anim": {
                    "enable": false,
                    "speed": 1,
                    "opacity_min": 0.1,
                    "sync": false
                }
            },
            "size": { 
                "value": 3, 
                "random": true,
                "anim": {
                    "enable": false,
                    "speed": 40,
                    "size_min": 0.1,
                    "sync": false
                }
            },
            "line_linked": { 
                "enable": true, 
                "distance": 150, 
                "color": "#ffffff", 
                "opacity": 0.4, 
                "width": 1 
            },
            "move": { 
                "enable": true, 
                "speed": 6, 
                "direction": "none", 
                "random": false, 
                "straight": false, 
                "out_mode": "out", 
                "bounce": false,
                "attract": {
                    "enable": false,
                    "rotateX": 600,
                    "rotateY": 1200
                }
            }
        },
        "interactivity": {
            "detect_on": "canvas",
            "events": {
                "onhover": { 
                    "enable": true, 
                    "mode": "repulse" 
                },
                "onclick": { 
                    "enable": true, 
                    "mode": "push" 
                },
                "resize": true
            },
            "modes": {
                "grab": {
                    "distance": 400,
                    "line_linked": {
                        "opacity": 1
                    }
                },
                "bubble": {
                    "distance": 400,
                    "size": 40,
                    "duration": 2,
                    "opacity": 8,
                    "speed": 3
                },
                "repulse": { 
                    "distance": 100, 
                    "duration": 0.4 
                },
                "push": { 
                    "particles_nb": 4 
                },
                "remove": {
                    "particles_nb": 2
                }
            }
        },
        "retina_detect": true
    });
    
    // Check particles visibility after loading
    setTimeout(function() {
        const canvas = document.querySelector('#particles-js canvas');
        if (canvas) {
            console.log('Canvas found, particles should be visible');
            console.log('Canvas dimensions:', canvas.width, 'x', canvas.height);
        } else {
            console.log('Canvas still not found after timeout');
        }
    }, 2000);
}

// Form initialization
function initializeForm() {
    const form = document.querySelector('form');
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('confirm_password');
    const termsContainer = document.getElementById('terms-container');
    const termsCheckbox = document.getElementById('terms-checkbox');
    const agreeButton = document.getElementById('agree-button');

    if (!form) return;

    // Terms container scroll detection
// Add this inside initializeForm() function, replace the existing terms container code

// Terms container scroll detection
if (termsContainer && termsCheckbox && agreeButton) {
    console.log('✅ Terms modal elements found, initializing scroll detection');
    
    termsContainer.addEventListener('scroll', function() {
        // Check if user has scrolled to bottom
        const scrolledToBottom = Math.abs(
            this.scrollHeight - this.scrollTop - this.clientHeight
        ) < 5; // 5px threshold for scroll detection
        
        console.log('📜 Scroll detected:', {
            scrollHeight: this.scrollHeight,
            scrollTop: this.scrollTop,
            clientHeight: this.clientHeight,
            isAtBottom: scrolledToBottom,
            difference: Math.abs(this.scrollHeight - this.scrollTop - this.clientHeight)
        });
        
        if (scrolledToBottom && !hasScrolledToBottom) {
            hasScrolledToBottom = true;
            termsCheckbox.disabled = false;
            console.log('✅ User scrolled to bottom - checkbox enabled');
            
            // Visual feedback
            showAlert('info', 'Please check the box to accept the terms.', true, 3000);
        }
    });

    // Enable agree button only when checkbox is checked
    termsCheckbox.addEventListener('change', function() {
        agreeButton.disabled = !this.checked;
        console.log('✅ Checkbox changed:', {
            checked: this.checked,
            agreeButtonDisabled: agreeButton.disabled
        });
    });
}

            // Form submission handling
        form.addEventListener('submit', async function(e) {
          // Don't prevent default yet - we'll do it only if validation fails
          
          console.log('🚀 Dual Verification Form submission started');
          
          // Add a delay to make console messages visible
          await new Promise(resolve => setTimeout(resolve, 100));
          
          // Clear all previous inline errors
          const errorElements = form.querySelectorAll('.inline-error-message');
          errorElements.forEach(el => {
            el.className = 'inline-error-message';
            el.textContent = '';
          });
          
          // Clear input error states
          const inputContainers = form.querySelectorAll('.modern-input');
          inputContainers.forEach(container => {
            container.classList.remove('has-error', 'has-success');
          });
          
          let hasErrors = false;

          // Check if terms are accepted
// Replace the terms check in your form submit handler with this:

// Check if terms are accepted
const termsInput = document.getElementById('terms');
console.log('=== TERMS VALIDATION DEBUG ===');
console.log('Terms input element:', termsInput);
console.log('Terms input value:', termsInput ? termsInput.value : 'NOT FOUND');
console.log('Terms input value type:', typeof (termsInput ? termsInput.value : undefined));
console.log('Terms value === "1":', termsInput ? (termsInput.value === '1') : false);
console.log('Terms value === 1:', termsInput ? (termsInput.value === 1) : false);
console.log('termsAccepted global:', termsAccepted);
console.log('==============================');

if (!termsInput || termsInput.value !== '1') {
  e.preventDefault(); // Prevent form submission
  console.log('❌ BLOCKING: Terms not accepted');
  console.log('❌ Terms value is:', termsInput ? termsInput.value : 'INPUT NOT FOUND');
  
  // Show alert and modal
  showAlert('warning', 'Please read and accept the Terms & Conditions to continue.', true, 5000);
  
  // Highlight the terms section with animation
  const termsSection = document.querySelector('.mb-4');
  if (termsSection) {
      termsSection.style.animation = 'shake 0.5s';
      termsSection.style.border = '2px solid #ef4444';
      termsSection.style.borderRadius = '8px';
      termsSection.style.padding = '12px';
      
      setTimeout(() => {
          termsSection.style.animation = '';
          termsSection.style.border = '';
          termsSection.style.padding = '';
      }, 2000);
  }
  
  setTimeout(() => {
    showTermsModal();
  }, 500);
  
  return false;
}
console.log('✅ Terms validation passed, value is:', termsInput.value);

          // Client-side validation
          const formData = new FormData(form);
          const firstName = formData.get('first_name');
          const lastName = formData.get('last_name');
          const middleName = formData.get('middle_name');
          const username = formData.get('username');
          const email = formData.get('email');
          
          console.log('🔍 DEBUG: Form data extracted:', { firstName, lastName, middleName, username, email, password: passwordField ? passwordField.value : 'not found', confirmPassword: confirmField ? confirmField.value : 'not found' });

          // Enhanced name field validation
          if (!firstName || firstName.trim() === '') {
            console.log('First name validation failed: empty');
            showInlineError('first_name', 'First Name is required.');
            hasErrors = true;
          } else if (firstName.trim().length < 3) {
            console.log('First name validation failed: too short');
            showInlineError('first_name', 'First name must be at least 3 characters.');
            hasErrors = true;
          } else if (firstName.trim().length > 50) {
            console.log('First name validation failed: too long');
            showInlineError('first_name', 'First name cannot exceed 50 characters.');
            hasErrors = true;
          } else if (!isValidName(firstName.trim())) {
            console.log('First name validation failed: invalid format');
            showInlineError('first_name', 'Only letters, spaces, hyphens allowed.');
            hasErrors = true;
          } else {
            console.log('First name validation passed');
          }
          
          if (!lastName || lastName.trim() === '') {
            showInlineError('last_name', 'Last Name is required.');
            hasErrors = true;
          } else if (lastName.trim().length < 3) {
            showInlineError('last_name', 'Last name must be at least 3 characters.');
            hasErrors = true;
          } else if (lastName.trim().length > 50) {
            showInlineError('last_name', 'Last name cannot exceed 50 characters.');
            hasErrors = true;
          } else if (!isValidName(lastName.trim())) {
            showInlineError('last_name', 'Only letters, spaces, hyphens allowed.');
            hasErrors = true;
          }
          
          // Middle name validation (optional but must be valid if provided)
          if (middleName && middleName.trim() !== '') {
            if (middleName.trim().length < 3) {
              showInlineError('middle_name', 'Middle name must be at least 3 characters.');
              hasErrors = true;
            } else if (middleName.trim().length > 50) {
              showInlineError('middle_name', 'Middle name cannot exceed 50 characters.');
              hasErrors = true;
            } else if (!isValidName(middleName.trim())) {
              showInlineError('middle_name', 'Only letters, spaces, hyphens allowed.');
              hasErrors = true;
            }
          }

          // Username validation
          if (!username || username.trim() === '') {
            showInlineError('username', 'Username is required.');
            hasErrors = true;
          } else if (!isValidUsername(username)) {
            showInlineError('username', 'Username must be 3-20 characters long and contain only letters, numbers, and underscores.');
            hasErrors = true;
          }

          // Enhanced email validation
          if (!email || email.trim() === '') {
            showInlineError('email', 'Email address is required.');
            hasErrors = true;
          } else {
            const validation = validateEmailWithFeedback(email);
            if (!validation.valid) {
              showInlineError('email', validation.message);
              hasErrors = true;
            }
          }

          // Phone number validation
          const phoneNumber = formData.get('phone_number');
          const countryCode = formData.get('country_code');
          
          if (!phoneNumber || phoneNumber.trim() === '') {
            showInlineError('phone_number', 'Contact number is required.');
            hasErrors = true;
          } else {
            const phoneValidation = validatePhoneNumber(phoneNumber, countryCode);
            if (!phoneValidation.valid) {
              showInlineError('phone_number', phoneValidation.message);
              hasErrors = true;
            }
          }

          // Password validation
          if (passwordField && confirmField && passwordField.value !== confirmField.value) {
            showInlineError('confirm_password', 'Passwords do not match!');
            hasErrors = true;
          }
          
          if (passwordField) {
            const password = passwordField.value;
            
            if (password.length < 12) {
              showInlineError('password', 'Password must be at least 12 characters long!');
              hasErrors = true;
            } else if (!/[A-Z]/.test(password)) {
              showInlineError('password', 'Password must include at least one uppercase letter!');
              hasErrors = true;
            } else if (!/[a-z]/.test(password)) {
              showInlineError('password', 'Password must include at least one lowercase letter!');
              hasErrors = true;
            } else if (!/[0-9]/.test(password)) {
              showInlineError('password', 'Password must include at least one number!');
              hasErrors = true;
            } else if (!/[^a-zA-Z0-9]/.test(password)) {
              showInlineError('password', 'Password must include at least one special character!');
              hasErrors = true;
            }
          }

          // If there are validation errors, stop here
          if (hasErrors) {
            e.preventDefault(); // Prevent form submission
            console.log('❌ BLOCKING: Validation errors found, stopping submission');
            console.log('❌ BLOCKING: hasErrors =', hasErrors);
            return false;
          }

          // If all validations pass, submit the form normally
          console.log('✅ SUCCESS: All validations passed, submitting form to auth.php');
          
          // Allow the form to submit naturally to auth.php
          // The existing auth.php will handle user creation and email verification
          return true;
          
          return false;
        });

        // Real-time field validation
    const usernameField = form.querySelector('input[name="username"]');
    const emailField = form.querySelector('input[name="email"]');
    const firstNameField = form.querySelector('input[name="first_name"]');
    const lastNameField = form.querySelector('input[name="last_name"]');
    
        // First Name validation
    if (firstNameField) {
        firstNameField.addEventListener('blur', function() {
            const firstName = this.value.trim();
            if (firstName === '') {
                showInlineError('first_name', 'First Name is required.');
            } else if (firstName.length < 3) {
                showInlineError('first_name', 'First name must be at least 3 characters.');
            } else if (firstName.length > 50) {
                showInlineError('first_name', 'First name cannot exceed 50 characters.');
            } else if (!isValidName(firstName)) {
                showInlineError('first_name', 'Only letters, spaces, hyphens allowed.');
            } else {
                showInlineSuccess('first_name', 'Valid first name');
            }
        });
        
        firstNameField.addEventListener('input', function() {
            const firstName = this.value.trim();
            if (firstName === '') {
                hideInlineError('first_name');
            } else if (firstName.length < 3) {
                showInlineError('first_name', 'First name must be at least 3 characters.');
            } else if (firstName.length > 50) {
                showInlineError('first_name', 'First name cannot exceed 50 characters.');
            } else if (!isValidName(firstName)) {
                showInlineError('first_name', 'Only letters, spaces, hyphens allowed.');
            } else {
                hideInlineError('first_name');
            }
        });
    }
    
        // Last Name validation
    if (lastNameField) {
        lastNameField.addEventListener('blur', function() {
            const lastName = this.value.trim();
            if (lastName === '') {
                showInlineError('last_name', 'Last Name is required.');
            } else if (lastName.length < 3) {
                showInlineError('last_name', 'Last name must be at least 3 characters.');
            } else if (lastName.length > 50) {
                showInlineError('last_name', 'Last name cannot exceed 50 characters.');
            } else if (!isValidName(lastName)) {
                showInlineError('last_name', 'Only letters, spaces, hyphens allowed.');
            } else {
                showInlineSuccess('last_name', 'Valid last name');
            }
        });
        
        lastNameField.addEventListener('input', function() {
            const lastName = this.value.trim();
            if (lastName === '') {
                hideInlineError('last_name');
            } else if (lastName.length < 3) {
                showInlineError('last_name', 'Last name must be at least 3 characters.');
            } else if (lastName.length > 50) {
                showInlineError('last_name', 'Last name cannot exceed 50 characters.');
            } else if (!isValidName(lastName)) {
                showInlineError('last_name', 'Only letters, spaces, hyphens allowed.');
            } else {
                hideInlineError('last_name');
            }
        });
    }
    
    // Middle Name validation (optional field)
    const middleNameField = form.querySelector('input[name="middle_name"]');
    if (middleNameField) {
        middleNameField.addEventListener('blur', function() {
            const middleName = this.value.trim();
            if (middleName !== '') {
                if (middleName.length < 3) {
                    showInlineError('middle_name', 'Middle name must be at least 3 characters.');
                } else if (middleName.length > 50) {
                    showInlineError('middle_name', 'Middle name cannot exceed 50 characters.');
                } else if (!isValidName(middleName)) {
                    showInlineError('middle_name', 'Only letters, spaces, hyphens allowed.');
                } else {
                    showInlineSuccess('middle_name', 'Valid middle name');
                }
            } else {
                hideInlineError('middle_name');
            }
        });
        
        middleNameField.addEventListener('input', function() {
            const middleName = this.value.trim();
            if (middleName === '') {
                hideInlineError('middle_name');
            } else if (middleName.length < 3) {
                showInlineError('middle_name', 'Middle name must be at least 3 characters.');
            } else if (middleName.length > 50) {
                showInlineError('middle_name', 'Middle name cannot exceed 50 characters.');
            } else if (!isValidName(middleName)) {
                showInlineError('middle_name', 'Only letters, spaces, hyphens allowed.');
            } else {
                hideInlineError('middle_name');
            }
        });
    }
    
    // Username validation with existence check
    if (usernameField) {
        let usernameTimeout;
        
        usernameField.addEventListener('blur', async function() {
            const username = this.value.trim();
            if (username === '') {
                showInlineError('username', 'Username is required.');
                return;
            }
            
            if (!isValidUsername(username)) {
                showInlineError('username', 'Username must be 3-20 characters long and contain only letters, numbers, and underscores.');
                return;
            }
            
            // Check if username exists
            try {
                const exists = await checkUsernameExists(username);
                if (exists) {
                    showInlineError('username', 'That username is taken. Please choose another.');
                } else {
                    showInlineSuccess('username', 'Username is available!');
                }
            } catch (error) {
                console.error('Error checking username:', error);
            }
        });
        
        usernameField.addEventListener('input', function() {
            const username = this.value.trim();
            
            // Clear previous timeout
            clearTimeout(usernameTimeout);
            
            if (username === '') {
                hideInlineError('username');
                return;
            }
            
            // Real-time format validation
            if (!isValidUsername(username)) {
                showInlineError('username', 'Username must be 3-20 characters long and contain only letters, numbers, and underscores.');
            } else {
                hideInlineError('username');
                
                // Debounced existence check (wait 500ms after user stops typing)
                usernameTimeout = setTimeout(async () => {
                    try {
                        const exists = await checkUsernameExists(username);
                        if (exists) {
                            showInlineError('username', 'That username is taken. Please choose another.');
                        } else {
                            showInlineSuccess('username', 'Username is available!');
                        }
                    } catch (error) {
                        console.error('Error checking username:', error);
                    }
                }, 500);
            }
        });
    }
    
    // Email validation with existence check
    if (emailField) {
        let emailTimeout;
                
        emailField.addEventListener('blur', async function() {
            const email = this.value.trim();
            
            if (email === '') {
                showInlineError('email', 'Email address is required.');
                return;
            }
            
            const validation = validateEmailWithFeedback(email);
            if (!validation.valid) {
                showInlineError('email', validation.message);
                return;
            }
            
            // Check if email exists
            try {
                const exists = await checkEmailExists(email);
                if (exists) {
                    showInlineError('email', 'This email is already registered.');
                } else {
                    showInlineSuccess('email', 'Email is available!');
                }
            } catch (error) {
                console.error('Error checking email:', error);
            }
        });
        
        emailField.addEventListener('input', function() {
            const email = this.value.trim();
            
            // Clear previous timeout
            clearTimeout(emailTimeout);
                        
            if (email === '') {
                hideInlineError('email');
                return;
            }
            
            // Real-time format validation with enhanced feedback
            const validation = validateEmailWithFeedback(email);
            if (!validation.valid) {
                showInlineError('email', validation.message);
            } else {
                hideInlineError('email');
                
                // Debounced existence check (wait 800ms after user stops typing)
                emailTimeout = setTimeout(async () => {
                    try {
                        const exists = await checkEmailExists(email);
                        if (exists) {
                            showInlineError('email', 'This email is already registered.');
                        } else {
                            showInlineSuccess('email', 'Email is available!');
                        }
                    } catch (error) {
                        console.error('Error checking email:', error);
                    }
                }, 800);
            }
        });
    }

    // Phone number validation with existence check and formatting
    const phoneField = form.querySelector('input[name="phone_number"]');
    const countryField = form.querySelector('input[name="country_code"]'); // Now a hidden input
    
    if (phoneField) {
        // Initialize with Philippines format hint
        updatePhoneFormatHint('+63');
        
        let phoneValidationTimeout;
        
        async function validatePhoneField() {
            const phoneNumber = phoneField.value.trim();
            const countryCode = '+63'; // Fixed to Philippines only
            
            console.log('🔍 Phone validation - Input:', phoneNumber, 'Country:', countryCode);
            
            if (!phoneNumber) {
                hideInlineError('phone_number');
                return;
            }
            
            // Validate format
            const formatValidation = validatePhoneNumber(phoneNumber, countryCode);
            if (!formatValidation.valid) {
                console.log('❌ Phone format validation failed:', formatValidation.message);
                showInlineError('phone_number', formatValidation.message);
                return;
            }
            
            console.log('✅ Phone format validation passed');
            
            // Check if phone exists - send clean number without formatting
            try {
                console.log('🔍 Checking phone availability...');
                // Clean the phone number before sending to API (remove spaces and formatting)
                const cleanPhoneNumber = phoneNumber.replace(/[^\d]/g, '');
                console.log('🔍 Clean phone number for API check:', cleanPhoneNumber);
                const exists = await checkPhoneExists(cleanPhoneNumber, countryCode);
                console.log('📞 Phone availability result:', exists ? 'EXISTS' : 'AVAILABLE');
                
                if (exists) {
                    showInlineError('phone_number', 'Phone number already registered');
                } else {
                    showInlineSuccess('phone_number', 'Phone number is available');
                }
            } catch (error) {
                console.error('❌ Phone validation error:', error);
                showInlineError('phone_number', 'Unable to verify phone number availability.');
            }
        }
        
        phoneField.addEventListener('input', function() {
            const countryCode = '+63'; // Fixed to Philippines only
            
            // Format phone number as user types
            this.value = formatPhoneNumber(this.value, countryCode);
            
            // Clear previous timeout
            clearTimeout(phoneValidationTimeout);
            
            // Validate after user stops typing (500ms delay)
            phoneValidationTimeout = setTimeout(validatePhoneField, 500);
        });
        
        phoneField.addEventListener('blur', function() {
            clearTimeout(phoneValidationTimeout);
            if (this.value.trim()) {
                validatePhoneField();
            }
        });
        
        phoneField.addEventListener('focus', function() {
            if (!this.value.trim()) {
                hideInlineError('phone_number');
            }
        });
        
        // Prevent non-numeric input on keypress
        phoneField.addEventListener('keypress', function(e) {
            // Allow backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
        
        // Remove non-numeric characters on paste
        phoneField.addEventListener('paste', function(e) {
            setTimeout(() => {
                let pastedValue = this.value;
                // Remove all non-numeric characters
                let cleanedValue = pastedValue.replace(/[^\d]/g, '');
                if (pastedValue !== cleanedValue) {
                    this.value = cleanedValue;
                    // Trigger formatting
                    this.value = formatPhoneNumber(cleanedValue, '+63');
                }
            }, 0);
        });
        
        // Additional keydown handler for more key combinations
        phoneField.addEventListener('keydown', function(e) {
            // Allow navigation keys
            const allowedKeys = [
                8,  // backspace
                9,  // tab
                27, // escape
                13, // enter
                46, // delete
                35, // end
                36, // home
                37, // left arrow
                38, // up arrow
                39, // right arrow
                40  // down arrow
            ];
            
            // Allow Ctrl combinations
            if (e.ctrlKey && [65, 67, 86, 88].includes(e.keyCode)) {
                return;
            }
            
            if (allowedKeys.includes(e.keyCode)) {
                return;
            }
            
            // Allow numbers (0-9) on main keyboard and numpad
            if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 96 && e.keyCode <= 105)) {
                return;
            }
            
            // Block everything else
            e.preventDefault();
        });
    }

    // Password validation
    if (passwordField) {
        passwordField.addEventListener('input', function() {
          checkPasswordStrength(this.value);
        });
    }

    if (confirmField) {
        confirmField.addEventListener('input', function() {
            const matchText = document.getElementById('password-match');
            if (matchText) {
                if (this.value === passwordField.value) {
                    matchText.textContent = 'Passwords match';
                    matchText.style.color = '#10b981';
                } else {
                    matchText.textContent = 'Passwords do not match';
                    matchText.style.color = '#ef4444';
                }
            }
        });
    }
}

// Modal functions
function showTermsModal() {
    const termsModal = document.getElementById('termsModal');
    if (termsModal) {
        termsModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Reset scroll state when opening modal
        hasScrolledToBottom = false;
        
        const termsCheckbox = document.getElementById('terms-checkbox');
        const agreeButton = document.getElementById('agree-button');
        
        // Reset checkbox and button state
        if (termsCheckbox) {
            termsCheckbox.checked = false;
            termsCheckbox.disabled = true;
        }
        if (agreeButton) {
            agreeButton.disabled = true;
        }
        
        console.log('✅ Terms modal opened, checkbox and button reset');
    }
}

function hideTermsModal() {
    const termsModal = document.getElementById('termsModal');
    if (termsModal) {
        termsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function acceptTerms() {
    console.log('🔍 acceptTerms() called');
    
    // Verify checkbox is actually checked
    const termsCheckbox = document.getElementById('terms-checkbox');
    if (!termsCheckbox || !termsCheckbox.checked) {
        console.error('❌ Checkbox not checked! Cannot accept terms.');
        showAlert('warning', 'Please check the box to accept the Terms & Conditions.');
        return false;
    }
    
    console.log('✅ Checkbox verified as checked');
    
    // Set the hidden input value
    const termsInput = document.getElementById('terms');
    if (termsInput) {
        termsInput.value = '1';
        console.log('✅ Terms input value set to:', termsInput.value);
        console.log('✅ Terms input value type:', typeof termsInput.value);
        
        // Visual feedback - change the terms link text
        const termsSection = document.querySelector('.mb-4 .text-xs.text-gray-600');
        if (termsSection) {
            termsSection.innerHTML = `
                <span class="text-green-600 font-medium">✓ Terms & Conditions Accepted</span>
                <button type="button"  
                        onclick="showTermsModal()" 
                        class="terms-link focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 ml-2">
                    (Click to review again)
                </button>
            `;
        }
    } else {
        console.error('❌ Terms hidden input not found!');
        return false;
    }
    
    termsAccepted = true;
    console.log('✅ termsAccepted global variable set to:', termsAccepted);
    
    hideTermsModal();
    
    // Show success alert
    showAlert('success', 'Terms & Conditions accepted successfully!', true, 3000);
    
    return true;
}

// Password functions
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const button = field.parentElement.querySelector('.password-toggle');
    const eyeIcon = button.querySelector('svg');
    
    if (field.type === 'password') {
        field.type = 'text';
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        `;
    } else {
        field.type = 'password';
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        `;
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    const checks = {
        length: password.length >= 12,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        numbers: /[0-9]/.test(password),
        special: /[^a-zA-Z0-9]/.test(password)
    };

    // Calculate strength
    strength += checks.length ? 25 : 0;
    strength += checks.uppercase ? 20 : 0;
    strength += checks.lowercase ? 20 : 0;
    strength += checks.numbers ? 20 : 0;
    strength += checks.special ? 15 : 0;

    const bar = document.getElementById('strength-bar');
    const text = document.getElementById('strength-text');
    
    if (bar && text) {
        bar.style.width = strength + '%';
        bar.className = '';
        
        if (strength < 40) {
            bar.classList.add('strength-weak');
            text.textContent = 'Weak';
            text.style.color = '#ef4444';
        } else if (strength < 70) {
            bar.classList.add('strength-medium');
            text.textContent = 'Medium';
            text.style.color = '#f59e0b';
        } else if (strength < 90) {
            bar.classList.add('strength-strong');
            text.textContent = 'Strong';
            text.style.color = '#10b981';
        } else {
            bar.classList.add('strength-very-strong');
            text.textContent = 'Very Strong';
            text.style.color = '#059669';
        }
    }
}

// Password tooltip functions
function showPasswordTooltip() {
    const tooltip = document.getElementById('password-tooltip');
    if (tooltip) {
        tooltip.classList.remove('hidden');
    }
}

function hidePasswordTooltip() {
    const tooltip = document.getElementById('password-tooltip');
    if (tooltip) {
        tooltip.classList.add('hidden');
    }
}

function initializePasswordTooltip() {
    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.addEventListener('input', function() {
            const val = this.value;
            const lengthCheck = document.getElementById('length-check-tooltip');
            const uppercaseCheck = document.getElementById('uppercase-check-tooltip');
            const lowercaseCheck = document.getElementById('lowercase-check-tooltip');
            const numberCheck = document.getElementById('number-check-tooltip');
            const specialCheck = document.getElementById('special-check-tooltip');
            
            if (lengthCheck) lengthCheck.style.color = val.length >= 12 ? '#10b981' : '#6b7280';
            if (uppercaseCheck) uppercaseCheck.style.color = /[A-Z]/.test(val) ? '#10b981' : '#6b7280';
            if (lowercaseCheck) lowercaseCheck.style.color = /[a-z]/.test(val) ? '#10b981' : '#6b7280';
            if (numberCheck) numberCheck.style.color = /[0-9]/.test(val) ? '#10b981' : '#6b7280';
            if (specialCheck) specialCheck.style.color = /[^a-zA-Z0-9]/.test(val) ? '#10b981' : '#6b7280';
        });
    }
}


// Modern Alert System
function initializeAlerts() {
    // Check for session alerts
    if (typeof sessionAlert !== 'undefined' && sessionAlert) {
        showAlert(sessionAlert.type, sessionAlert.message);
    }
    
    // Check for session inline errors
    if (typeof sessionInlineErrors !== 'undefined' && sessionInlineErrors) {
        for (const [fieldName, errorData] of Object.entries(sessionInlineErrors)) {
            if (errorData.message) {
                showInlineError(fieldName, errorData.message, errorData.type || 'error');
            }
        }
    }
    
    // Initial form grid spacing check
    setTimeout(() => {
        updateFormGridSpacing();
    }, 100);
}

function showAlert(type, message, autoDismiss = true, duration = 5000) {
    const container = document.getElementById('alert-container');
    if (!container) {
        console.warn('Alert container not found');
        return;
    }

    // Clear any existing alerts of the same type to prevent spam
    const existingAlerts = container.querySelectorAll(`.alert-${type}`);
    existingAlerts.forEach(alert => {
        if (alert.querySelector('.alert-message').textContent === message) {
            dismissAlert(alert.id);
        }
    });

    const alertId = 'alert-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    
    const alertElement = document.createElement('div');
    alertElement.id = alertId;
    alertElement.className = `alert alert-${type} ${autoDismiss ? 'auto-dismiss' : ''}`;
    alertElement.setAttribute('role', 'alert');
    alertElement.setAttribute('aria-live', 'polite');
    
    const iconSvg = getAlertIcon(type);
    
    alertElement.innerHTML = `
        <div class="alert-content">
            <div class="alert-icon">
                ${iconSvg}
            </div>
            <div class="alert-message">${message}</div>
            <button class="alert-close" onclick="dismissAlert('${alertId}')" aria-label="Close alert" type="button">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        ${autoDismiss ? '<div class="alert-progress"><div class="alert-progress-bar"></div></div>' : ''}
    `;
    
    container.appendChild(alertElement);
    
    // Trigger animation
    requestAnimationFrame(() => {
        alertElement.style.animation = 'slideInRight 0.4s ease-out forwards';
    });
    
    // Auto-dismiss after specified duration
    if (autoDismiss) {
        setTimeout(() => {
            dismissAlert(alertId);
        }, duration);
    }
    
    return alertId;
}

function dismissAlert(alertId) {
    const alert = document.getElementById(alertId);
    if (alert) {
        alert.classList.add('hiding');
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 300);
    }
}

function getAlertIcon(type) {
    const icons = {
        error: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>`,
        success: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>`,
        warning: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
        </svg>`,
        info: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>`
    };
    
    return icons[type] || icons.info;
}

// Event listeners
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideTermsModal();
    }
});

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const termsModal = document.getElementById('termsModal');
    if (termsModal) {
        termsModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideTermsModal();
            }
        });
    }
});

// Stats and particle counter (hidden)
if (typeof Stats !== 'undefined') {
    const stats = new Stats();
    stats.setMode(0);
    stats.domElement.style.position = 'absolute';
    stats.domElement.style.left = '0px';
    stats.domElement.style.top = '0px';
    stats.domElement.style.display = 'none';
    document.body.appendChild(stats.domElement);
}

// Handle window resize for mobile/desktop switching
window.addEventListener('resize', function() {
    const particlesContainer = document.getElementById('particles-js');
    if (particlesContainer) {
        if (window.innerWidth <= 767) {
            // Mobile - hide particles
            particlesContainer.style.display = 'none';
        } else {
            // Desktop - show particles
            particlesContainer.style.display = 'block';
            // Reinitialize particles if they were disabled
            if (typeof particlesJS !== 'undefined' && !particlesContainer.hasAttribute('data-particles-initialized')) {
                initializeParticles();
                particlesContainer.setAttribute('data-particles-initialized', 'true');
            }
        }
    }
});

// Modern Confirm Dialog System
function showModernConfirmDialog(title, message, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm = null, onCancel = null) {
  const modal = document.getElementById('confirmModal');
  const titleElement = document.getElementById('confirm-modal-title');
  const messageElement = document.getElementById('confirm-modal-message');
  const confirmButton = document.getElementById('confirm-modal-confirm');
  const cancelButton = document.getElementById('confirm-modal-cancel');
  
  if (!modal || !titleElement || !messageElement || !confirmButton || !cancelButton) {
    console.error('Confirm modal elements not found');
    return;
  }
  
  // Set content
  titleElement.textContent = title;
  messageElement.textContent = message;
  confirmButton.textContent = confirmText;
  cancelButton.textContent = cancelText;
  
  // Clear previous event listeners
  const newConfirmButton = confirmButton.cloneNode(true);
  const newCancelButton = cancelButton.cloneNode(true);
  confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
  cancelButton.parentNode.replaceChild(newCancelButton, cancelButton);
  
  // Add new event listeners
  newConfirmButton.addEventListener('click', function() {
    hideModernConfirmDialog();
    if (onConfirm && typeof onConfirm === 'function') {
      onConfirm();
    }
  });
  
  newCancelButton.addEventListener('click', function() {
    hideModernConfirmDialog();
    if (onCancel && typeof onCancel === 'function') {
      onCancel();
    }
  });
  
  // Show modal
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  
  // Focus on cancel button for better UX
  setTimeout(() => {
    newCancelButton.focus();
  }, 100);
}

function hideModernConfirmDialog() {
  const modal = document.getElementById('confirmModal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
  }
}

// Close confirm modal when clicking outside or pressing Escape
document.addEventListener('DOMContentLoaded', function() {
  const confirmModal = document.getElementById('confirmModal');
  if (confirmModal) {
    confirmModal.addEventListener('click', function(e) {
      if (e.target === this) {
        hideModernConfirmDialog();
      }
    });
  }
  
  // Handle Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const confirmModal = document.getElementById('confirmModal');
      if (confirmModal && !confirmModal.classList.contains('hidden')) {
        hideModernConfirmDialog();
      }
    }
  });
});

// Make functions globally available
window.showTermsModal = showTermsModal;
window.hideTermsModal = hideTermsModal;
window.acceptTerms = acceptTerms;
window.togglePassword = togglePassword;
window.showPasswordTooltip = showPasswordTooltip;
window.hidePasswordTooltip = hidePasswordTooltip;
window.showAlert = showAlert;
window.dismissAlert = dismissAlert;
window.showModernConfirmDialog = showModernConfirmDialog;
window.hideModernConfirmDialog = hideModernConfirmDialog;