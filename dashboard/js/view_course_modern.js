/**
 * Modern View Course JavaScript
 * Handles animations, interactions, and progressive enhancement
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modern enhancements
    initializeAnimations();
    initializeProgressBars();
    initializeScrollEffects();
    initializeButtonInteractions();
    initializeImageLoading();
    initializeProgressTracking();
});

/**
 * Initialize entrance animations for page elements
 */
function initializeAnimations() {
    // Add fade-in animation to main elements
    const animatedElements = document.querySelectorAll('.course-header-card, .enrollment-section, .course-description');
    
    animatedElements.forEach((element, index) => {
        element.classList.add('fade-in');
        
        setTimeout(() => {
            element.classList.add('visible');
        }, index * 200);
    });

    // Initialize intersection observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe elements that should animate on scroll
    const scrollElements = document.querySelectorAll('.slide-up');
    scrollElements.forEach(element => {
        observer.observe(element);
    });
}

/**
 * Initialize progress bar animations
 */
function initializeProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    
    progressBars.forEach(bar => {
        const targetWidth = bar.getAttribute('data-progress') || 0;
        
        // Set color based on progress following dashboard.php logic
        if (targetWidth >= 100) {
            bar.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        } else {
            bar.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%)';
        }
        
        // Set initial width immediately for visibility
        bar.style.width = targetWidth + '%';
        
        // Animate progress bar with delay for visual effect
        setTimeout(() => {
            bar.style.transition = 'width 0.8s ease-out';
            bar.style.width = targetWidth + '%';
        }, 800);
    });
}

/**
 * Initialize scroll-based effects
 */
function initializeScrollEffects() {
    let ticking = false;
    
    function updateScrollEffects() {
        const scrollY = window.scrollY;
        const nav = document.querySelector('.modern-nav');
        
        if (nav) {
            // Check if dark mode is active
            const isDarkMode = document.documentElement.classList.contains('dark');
            
            if (scrollY > 100) {
                if (isDarkMode) {
                    nav.style.background = 'rgba(20, 20, 20, 0.98)';
                    nav.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.4)';
                } else {
                    nav.style.background = 'rgba(255, 255, 255, 0.98)';
                    nav.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
                }
            } else {
                if (isDarkMode) {
                    nav.style.background = 'rgba(20, 20, 20, 0.95)';
                    nav.style.boxShadow = 'none';
                } else {
                    nav.style.background = 'rgba(255, 255, 255, 0.95)';
                    nav.style.boxShadow = 'none';
                }
            }
        }
        
        // Parallax effect for abstract shapes
        const shapes = document.querySelectorAll('.abstract-shape');
        shapes.forEach((shape, index) => {
            const speed = 0.5 + (index * 0.1);
            const yPos = -(scrollY * speed);
            shape.style.transform = `translateY(${yPos}px) rotate(${scrollY * 0.1}deg)`;
        });
        
        ticking = false;
    }
    
    function requestScrollUpdate() {
        if (!ticking) {
            requestAnimationFrame(updateScrollEffects);
            ticking = true;
        }
    }
    
    window.addEventListener('scroll', requestScrollUpdate);
}

/**
 * Initialize button interactions and loading states
 */
function initializeButtonInteractions() {
    // Add click effects to buttons
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Create ripple effect
            createRippleEffect(e, this);
            
            // Add loading state for enrollment buttons
            if (this.classList.contains('btn-primary') && this.textContent.includes('Enroll')) {
                addLoadingState(this);
            }
        });
        
        // Add hover sound effect (optional)
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

/**
 * Create ripple effect on button click
 */
function createRippleEffect(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    `;
    
    // Ensure button has relative positioning
    if (getComputedStyle(element).position === 'static') {
        element.style.position = 'relative';
    }
    
    element.appendChild(ripple);
    
    // Remove ripple after animation
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Add loading state to button
 */
function addLoadingState(button) {
    const originalText = button.innerHTML;
    const loadingText = `
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Processing...
    `;
    
    button.innerHTML = loadingText;
    button.disabled = true;
    
    // Reset after 3 seconds if not redirected
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    }, 3000);
}

/**
 * Initialize image loading effects
 */
function initializeImageLoading() {
    const images = document.querySelectorAll('img');
    
    images.forEach(img => {
        if (img.complete) {
            img.classList.add('loaded');
        } else {
            img.addEventListener('load', function() {
                this.classList.add('loaded');
            });
            
            img.addEventListener('error', function() {
                // Handle image load error
                this.src = '../uploads/course_images/default-course.jpg';
                this.classList.add('loaded');
            });
        }
    });
}

/**
 * Initialize responsive navigation
 */
function initializeResponsiveNav() {
    const nav = document.querySelector('.modern-nav');
    const toggleButton = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (toggleButton && navMenu) {
        toggleButton.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            toggleButton.classList.toggle('active');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!nav.contains(e.target)) {
                navMenu.classList.remove('active');
                toggleButton.classList.remove('active');
            }
        });
    }
}

/**
 * Initialize tooltips for interactive elements
 */
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            showTooltip(this);
        });
        
        element.addEventListener('mouseleave', function() {
            hideTooltip();
        });
    });
}

/**
 * Show tooltip
 */
function showTooltip(element) {
    const tooltipText = element.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        z-index: 1000;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    // Show tooltip
    setTimeout(() => {
        tooltip.style.opacity = '1';
    }, 10);
}

/**
 * Hide tooltip
 */
function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.style.opacity = '0';
        setTimeout(() => {
            tooltip.remove();
        }, 300);
    }
}

/**
 * Initialize form enhancements
 */
function initializeFormEnhancements() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitButton) {
                addLoadingState(submitButton);
            }
        });
    });
}

// Add CSS for ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    img {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    img.loaded {
        opacity: 1;
    }
    
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
`;
document.head.appendChild(style);

/**
 * Progress Tracking System for View Course
 */
class ViewCourseProgressTracker {
    constructor() {
        this.courseId = this.extractCourseId();
        this.refreshInterval = null;
        this.isRefreshing = false;
    }

    extractCourseId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    async fetchCourseData() {
        if (!this.courseId) return null;

        try {
            const response = await fetch(`../api/check_enrollment.php?course_id=${this.courseId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching course data:', error);
            return null;
        }
    }

    updateCourseUI(courseData) {
        const enrollmentSection = document.querySelector(`[data-course-id="${this.courseId}"]`);
        if (!enrollmentSection) return;

        // Update total students count
        const totalStudents = document.querySelector('.total-students');
        if (totalStudents && courseData.total_enrollments !== undefined) {
            totalStudents.textContent = `${courseData.total_enrollments} students enrolled`;
        }

        // If enrolled, update progress information
        if (courseData.is_enrolled && courseData.progress) {
            const progressData = courseData.progress;

            // Update progress bar
            const progressBar = enrollmentSection.querySelector('.progress-fill');
            const progressPercentage = enrollmentSection.querySelector('.progress-percentage');

            if (progressBar && progressData.completion_percentage !== undefined) {
                progressBar.style.transition = 'width 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                progressBar.style.width = `${progressData.completion_percentage}%`;
                progressBar.setAttribute('data-progress', progressData.completion_percentage);

                // Update progress bar color following dashboard.php logic
                if (progressData.completion_percentage >= 100) {
                    progressBar.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                } else {
                    progressBar.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%)';
                }
            }

            if (progressPercentage) {
                const currentValue = parseInt(progressPercentage.textContent) || 0;
                const targetValue = Math.round(progressData.completion_percentage || 0);
                this.animateProgressCounter(progressPercentage, currentValue, targetValue);
            }

            // Update sections completed
            const completedCount = enrollmentSection.querySelector('.completed-count');
            const totalCount = enrollmentSection.querySelector('.total-count');
            
            if (completedCount && progressData.completed_sections !== undefined) {
                completedCount.textContent = progressData.completed_sections;
            }
            if (totalCount && progressData.total_sections !== undefined) {
                totalCount.textContent = progressData.total_sections;
            }

            // Update continue button text based on progress
            const continueBtn = enrollmentSection.querySelector('.continue-btn');
            if (continueBtn) {
                const status = this.determineProgressStatus(progressData.completion_percentage);
                continueBtn.textContent = this.getButtonText(status);
            }

            // Update enrollment status text for completed courses
            const enrollmentText = enrollmentSection.querySelector('.enrollment-text h3');
            if (enrollmentText && progressData.completion_percentage >= 100) {
                enrollmentText.innerHTML = 'Course Completed! 🎉';
            }
        }

        // Update enrollment button text for non-enrolled courses
        const enrollBtn = document.querySelector('.enroll-btn');
        if (enrollBtn && !courseData.is_enrolled) {
            if (courseData.is_free) {
                enrollBtn.textContent = 'Enroll Free';
            } else {
                enrollBtn.textContent = `Enroll (₱${courseData.price})`;
            }
        }
    }

    animateProgressCounter(element, start, end, duration = 1000) {
        if (start === end) return;

        const range = end - start;
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(start + (range * easeOutCubic));
            
            // Handle percentage display - add % only when > 0
            if (element.classList.contains('progress-percentage')) {
                element.textContent = current > 0 ? `${current}%` : '0';
            } else {
                element.textContent = current;
            }
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    determineProgressStatus(percentage) {
        if (percentage >= 100) return 'completed';
        if (percentage > 0) return 'in_progress';
        return 'not_started';
    }

    getButtonText(status) {
        const buttonTextMap = {
            'not_started': 'Start Learning',
            'in_progress': 'Continue Learning',
            'completed': 'Review Course'
        };
        return buttonTextMap[status] || 'Continue Learning';
    }

    async refreshCourseData() {
        if (this.isRefreshing || !this.courseId) return;
        this.isRefreshing = true;

        try {
            const courseData = await this.fetchCourseData();
            if (!courseData || !courseData.success) {
                console.warn('Failed to fetch course data');
                return;
            }

            this.updateCourseUI(courseData);
            console.log('View course data refreshed successfully');
        } catch (error) {
            console.error('Error refreshing view course:', error);
        } finally {
            this.isRefreshing = false;
        }
    }

    startPeriodicRefresh(interval = 30000) { // 30 seconds
        this.stopPeriodicRefresh();
        
        this.refreshInterval = setInterval(() => {
            this.refreshCourseData();
        }, interval);

        console.log(`Started view course refresh every ${interval/1000} seconds`);
    }

    stopPeriodicRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    init() {
        if (!this.courseId) {
            console.warn('No course ID found, skipping progress tracking');
            return;
        }

        // Initial refresh with delay
        setTimeout(() => {
            this.refreshCourseData();
        }, 1500);

        // Start periodic updates
        this.startPeriodicRefresh();

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPeriodicRefresh();
            } else {
                this.startPeriodicRefresh();
                this.refreshCourseData();
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.stopPeriodicRefresh();
        });
    }
}

// Initialize progress tracking
function initializeProgressTracking() {
    window.viewCourseProgressTracker = new ViewCourseProgressTracker();
    window.viewCourseProgressTracker.init();
}

// Make available globally
window.ViewCourseProgressTracker = ViewCourseProgressTracker;

// Initialize all enhancements
document.addEventListener('DOMContentLoaded', function() {
    initializeResponsiveNav();
    initializeTooltips();
    initializeFormEnhancements();
    initializeProgressTracking();
});

// Handle dark mode changes
if (window.Alpine) {
    Alpine.store('darkMode', {
        on: localStorage.getItem('darkMode') === 'true',
        toggle() {
            this.on = !this.on;
            localStorage.setItem('darkMode', this.on);
            
            // Update scroll effects when dark mode changes
            const event = new Event('scroll');
            window.dispatchEvent(event);
        }
    });
}
