    // Wait for DOM to be ready and particles.js to load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded');
        
        // Check if we're on mobile - disable particles on mobile screens
        if (window.innerWidth <= 767) {
            console.log('Mobile screen detected, particles.js disabled');
            return;
        }
        
        // Check if particles container exists
        const particlesContainer = document.getElementById('particles-js');
        if (!particlesContainer) {
          console.error('particles-js container not found');
          return;
        }
        console.log('particles-js container found:', particlesContainer);
        
        // Check if particlesJS is available
        if (typeof particlesJS === 'undefined') {
          console.error('particlesJS is not loaded');
          return;
        }
        
        console.log('particlesJS is available, loading inline configuration...');
        
        // Use inline configuration for better reliability
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
        
        // Add a timeout to check if particles are visible after loading
        setTimeout(function() {
          const canvas = document.querySelector('#particles-js canvas');
          if (!canvas) {
            console.log('Canvas still not found after timeout');
          } else {
            console.log('Canvas found, particles should be visible');
            console.log('Canvas dimensions:', canvas.width, 'x', canvas.height);
            console.log('Canvas style:', canvas.style.cssText);
            
            // Check if particles are actually being rendered
            const ctx = canvas.getContext('2d');
            if (ctx) {
              const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
              const hasPixels = imageData.data.some(pixel => pixel !== 0);
              console.log('Canvas has visible pixels:', hasPixels);
            }
          }
        }, 2000);
      });
  
      // Stats and particle counter (hidden)
      var count_particles, stats, update;
      
      // Check if Stats is available before using it
      if (typeof Stats !== 'undefined') {
        stats = new Stats;
        stats.setMode(0);
        stats.domElement.style.position = 'absolute';
        stats.domElement.style.left = '0px';
        stats.domElement.style.top = '0px';
        stats.domElement.style.display = 'none'; // Hide stats panel
        document.body.appendChild(stats.domElement);
      }
      
      count_particles = document.querySelector('.js-count-particles');
      update = function() {
        if (stats && typeof stats.begin === 'function') {
          stats.begin();
          stats.end();
        }
        if (window.pJSDom && window.pJSDom[0] && window.pJSDom[0].pJS && window.pJSDom[0].pJS.particles && window.pJSDom[0].pJS.particles.array) {
          count_particles.innerText = window.pJSDom[0].pJS.particles.array.length;
        }
        requestAnimationFrame(update);
      };
      requestAnimationFrame(update);
  
      function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
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
  
      // Inline Error Display Functions
      function showInlineError(fieldId, message, type = 'error') {
          const errorElement = document.getElementById(fieldId + '-error');
          const inputContainer = document.querySelector(`input[name="${fieldId}"]`)?.closest('.modern-input');
          
          if (errorElement) {
              errorElement.textContent = message;
              errorElement.className = `inline-error-message show ${type}`;
          }
          
          if (inputContainer) {
              inputContainer.classList.remove('has-error', 'has-success', 'has-warning');
              inputContainer.classList.add(`has-${type}`);
          }
      }

      function hideInlineError(fieldId) {
          const errorElement = document.getElementById(fieldId + '-error');
          const inputContainer = document.querySelector(`input[name="${fieldId}"]`)?.closest('.modern-input');
          
          if (errorElement) {
              errorElement.className = 'inline-error-message';
              errorElement.textContent = '';
          }
          
          if (inputContainer) {
              inputContainer.classList.remove('has-error', 'has-success', 'has-warning');
          }
      }

      function clearAllInlineErrors() {
          const errorElements = document.querySelectorAll('.inline-error-message');
          const inputContainers = document.querySelectorAll('.modern-input');
          
          errorElements.forEach(element => {
              element.className = 'inline-error-message';
              element.textContent = '';
          });
          
          inputContainers.forEach(container => {
              container.classList.remove('has-error', 'has-success', 'has-warning');
          });
      }

      // Initialize inline errors on page load
      function initializeInlineErrors() {
          // Check for session errors
          if (typeof sessionErrors !== 'undefined' && sessionErrors) {
              Object.keys(sessionErrors).forEach(fieldId => {
                  showInlineError(fieldId, sessionErrors[fieldId]);
              });
          }
      }

      // Initialize inline errors when DOM is loaded
      document.addEventListener('DOMContentLoaded', initializeInlineErrors);

      // Add form validation and feedback
      document.addEventListener('DOMContentLoaded', function() {
          const loginForm = document.querySelector('form[action="../auth/auth.php"]');
          if (loginForm) {
              loginForm.addEventListener('submit', function(e) {
                  const emailOrUsername = loginForm.querySelector('input[name="email"]').value.trim();
                  const password = loginForm.querySelector('input[name="password"]').value.trim();

                  // Clear any existing inline errors
                  clearAllInlineErrors();

                  let hasErrors = false;

                  // Validate fields
                  if (!emailOrUsername) {
                      e.preventDefault();
                      showInlineError('email', 'Please enter your email or username.');
                      hasErrors = true;
                  } else if (emailOrUsername.length < 3) {
                      e.preventDefault();
                      showInlineError('email', 'Email or username must be at least 3 characters long.');
                      hasErrors = true;
                  }

                  if (!password) {
                      e.preventDefault();
                      showInlineError('password', 'Please enter your password.');
                      hasErrors = true;
                  }

                  if (hasErrors) {
                      return false;
                  }

                  // Show clean loading state - only if validation passes
                  const submitButton = loginForm.querySelector('button[type="submit"]');
                  if (submitButton) {
                      const btnText = submitButton.querySelector('#btnText');
                      const btnSpinner = submitButton.querySelector('#btnSpinner');
                      
                      if (btnText && btnSpinner) {
                          btnText.classList.add('hidden');
                          btnSpinner.classList.remove('hidden');
                          submitButton.disabled = true;
                      }
                  }

                  return true;
              });

              // Add real-time validation feedback
              const emailField = loginForm.querySelector('input[name="email"]');
              const passwordField = loginForm.querySelector('input[name="password"]');

              if (emailField) {
                  // Enhanced email/username validation
                  emailField.addEventListener('blur', function() {
                      const value = this.value.trim();
                      if (value === '') {
                          // Don't show error on blur if field is empty - wait for form submission
                          return;
                      } else if (value.length < 3) {
                          showInlineError('email', 'Email or username must be at least 3 characters long.', 'warning');
                      } else if (value.length > 100) {
                          showInlineError('email', 'Email or username is too long.', 'warning');
                      } else {
                          // Valid length - check if it looks like email format
                          if (value.includes('@') && !isValidEmail(value)) {
                              showInlineError('email', 'Please enter a valid email address.', 'warning');
                          } else {
                              hideInlineError('email');
                          }
                      }
                  });

                  emailField.addEventListener('input', function() {
                      const value = this.value.trim();
                      // Clear error when user starts typing and field becomes valid
                      if (value.length >= 3 && value.length <= 100) {
                          if (!value.includes('@') || isValidEmail(value)) {
                              hideInlineError('email');
                          }
                      }
                  });
              }

              if (passwordField) {
                  // Enhanced password validation
                  passwordField.addEventListener('blur', function() {
                      const value = this.value;
                      if (value === '') {
                          // Don't show error on blur if field is empty - wait for form submission
                          return;
                      } else {
                          hideInlineError('password');
                      }
                  });

                  passwordField.addEventListener('input', function() {
                      const value = this.value;
                      // Clear error when user starts typing and field becomes valid
                      if (value.length >= 6) {
                          hideInlineError('password');
                      }
                  });
              }
          }
      });

      // Email validation helper function
      function isValidEmail(email) {
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          return emailRegex.test(email);
      }

      // Modern Alert System
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

      // Initialize alerts on page load
      function initializeAlerts() {
          // Check for session alerts
          if (typeof sessionAlert !== 'undefined' && sessionAlert) {
              showAlert(sessionAlert.type, sessionAlert.message);
          }
          
          // Handle timeout message
          if (typeof timeoutMessage !== 'undefined' && timeoutMessage) {
              showAlert('warning', timeoutMessage);
          }
      }

      // Initialize alerts when DOM is ready
      initializeAlerts();

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
                      // Re-run particles initialization
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
                      particlesContainer.setAttribute('data-particles-initialized', 'true');
                  }
              }
          }
      });

      // Make functions globally available
      window.showAlert = showAlert;
      window.dismissAlert = dismissAlert;