/* ================================================
   MODERN STUDENT COURSES JAVASCRIPT
   ================================================ */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Student Courses Modern JS loaded');
    
    // Initialize animations
    initializeAnimations();
    
    // Initialize category filters
    initializeCategoryFilters();
    
    // Initialize card interactions
    initializeCardInteractions();
    
    // Initialize loading states
    initializeLoadingStates();
    
    // Initialize progress tracking
    initializeProgressTracking();
});

/**
 * Initialize scroll-based animations
 */
function initializeAnimations() {
    // Add slide-up animation to course cards
    const courseCards = document.querySelectorAll('.course-card');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                }, index * 100);
                cardObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    courseCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        cardObserver.observe(card);
    });

    // Animate page elements on load
    const pageHeader = document.querySelector('.page-header');
    const filterContainer = document.querySelector('.filter-container');
    
    if (pageHeader) {
        pageHeader.style.opacity = '0';
        pageHeader.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            pageHeader.style.opacity = '1';
            pageHeader.style.transform = 'translateY(0)';
            pageHeader.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
        }, 200);
    }
    
    if (filterContainer) {
        filterContainer.style.opacity = '0';
        filterContainer.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            filterContainer.style.opacity = '1';
            filterContainer.style.transform = 'translateY(0)';
            filterContainer.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
        }, 400);
    }
}

/**
 * Initialize category filtering functionality
 */
function initializeCategoryFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const courseCards = document.querySelectorAll('.course-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => {
                btn.classList.remove('active');
            });

            // Add active class to clicked button
            this.classList.add('active');

            const category = this.dataset.category;

            // Filter courses with animation
            courseCards.forEach((card, index) => {
                const cardCategory = card.dataset.category;
                const shouldShow = category === 'all' || cardCategory === category;
                
                if (shouldShow) {
                    // Show card with staggered animation
                    setTimeout(() => {
                        card.style.display = 'block';
                        requestAnimationFrame(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0) scale(1)';
                        });
                    }, index * 50);
                } else {
                    // Hide card with animation
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px) scale(0.95)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 300);
                }
            });

            // Update URL without page reload (optional)
            if (history.pushState && category !== 'all') {
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('category', category);
                history.pushState(null, '', newUrl);
            } else if (history.pushState && category === 'all') {
                const newUrl = new URL(window.location);
                newUrl.searchParams.delete('category');
                history.pushState(null, '', newUrl);
            }
        });
    });

    // Check URL for initial category filter
    const urlParams = new URLSearchParams(window.location.search);
    const initialCategory = urlParams.get('category');
    if (initialCategory) {
        const targetButton = document.querySelector(`[data-category="${initialCategory}"]`);
        if (targetButton) {
            targetButton.click();
        }
    }
}

/**
 * Initialize card interaction effects
 */
function initializeCardInteractions() {
    const courseCards = document.querySelectorAll('.course-card');

    courseCards.forEach(card => {
        // Add subtle parallax effect on mouse move
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / centerY * -5;
            const rotateY = (x - centerX) / centerX * 5;
            
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-10px)`;
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px)';
        });

        // Add click ripple effect
        card.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

/**
 * Initialize loading states for buttons
 */
function initializeLoadingStates() {
    // Only handle loading state after form submit, not on button click
    const enrollForms = document.querySelectorAll('form[method="POST"]');
    enrollForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = form.querySelector('button[type="submit"]');
            if (button && !button.disabled) {
                // Show loading state
                const originalText = button.textContent;
                button.disabled = true;
                button.style.opacity = '0.7';
                button.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...`
                ;
            }
        });
    });

    // For non-form buttons (like paid enroll), keep previous logic
    const enrollButtons = document.querySelectorAll('.course-btn');
    enrollButtons.forEach(button => {
        // Only attach if not inside a form
        if (!button.closest('form')) {
            button.addEventListener('click', function(e) {
                if (this.textContent.includes('Continue Learning')) {
                    return;
                }
                const originalText = this.textContent;
                this.disabled = true;
                this.style.opacity = '0.7';
                this.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...`
                ;
                setTimeout(() => {
                    this.disabled = false;
                    this.style.opacity = '1';
                    this.textContent = originalText;
                }, 3000);
            });
        }
    });
}

/**
 * Initialize search functionality (if search input exists)
 */
function initializeSearch() {
    const searchInput = document.querySelector('#courseSearch');
    if (!searchInput) return;

    const courseCards = document.querySelectorAll('.course-card');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.toLowerCase().trim();

        searchTimeout = setTimeout(() => {
            courseCards.forEach(card => {
                const title = card.querySelector('.course-title').textContent.toLowerCase();
                const description = card.querySelector('.course-description').textContent.toLowerCase();
                const teacher = card.querySelector('.course-teacher').textContent.toLowerCase();
                
                const matches = title.includes(searchTerm) || 
                               description.includes(searchTerm) || 
                               teacher.includes(searchTerm);

                if (matches || searchTerm === '') {
                    card.style.display = 'block';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 300);
                }
            });
        }, 300);
    });
}

/**
 * Utility function to show notifications
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Style the notification
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '1rem 1.5rem',
        borderRadius: '12px',
        color: 'white',
        fontWeight: '600',
        zIndex: '9999',
        transform: 'translateX(400px)',
        transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
    });
    
    // Set background color based on type
    switch (type) {
        case 'success':
            notification.style.background = 'linear-gradient(135deg, #11998e, #38ef7d)';
            break;
        case 'error':
            notification.style.background = 'linear-gradient(135deg, #ff6b6b, #ff8e53)';
            break;
        default:
            notification.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
    }
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Animate out
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}

// Add CSS for ripple effect and animations
const style = document.createElement('style');
style.textContent = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes completionPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); box-shadow: 0 0 20px rgba(16, 185, 129, 0.5); }
        100% { transform: scale(1); }
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
 * Progress Tracking System for Student Courses
 */
class StudentCoursesProgressTracker {
    constructor() {
        this.refreshInterval = null;
        this.isRefreshing = false;
    }

    async fetchCourseProgress(courseId) {
        try {
            const response = await fetch(`../api/check_enrollment.php?course_id=${courseId}`, {
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
            console.error('Error fetching course progress:', error);
            return null;
        }
    }

    async fetchAllCoursesData() {
        try {
            const response = await fetch('../api/get_all_courses_with_progress.php', {
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
            console.error('Error fetching all courses data:', error);
            return null;
        }
    }

    updateCourseUI(courseId, courseData) {
        const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
        if (!courseCard) return;

        // Update enrollment count
        const enrollmentCount = courseCard.querySelector('.enrollment-count');
        if (enrollmentCount && courseData.total_enrollments !== undefined) {
            enrollmentCount.textContent = `${courseData.total_enrollments} students`;
        }

        // If the course is enrolled, update progress information
        if (courseData.is_enrolled && courseData.progress) {
            const progressData = courseData.progress;

            // Update course status badge
            const statusBadge = courseCard.querySelector('.course-status-badge');
            if (statusBadge) {
                const newStatus = this.determineProgressStatus(progressData.completion_percentage);
                const statusClasses = ['status-not-started', 'status-in-progress', 'status-completed'];
                
                statusClasses.forEach(cls => statusBadge.classList.remove(cls));
                statusBadge.classList.add(`status-${newStatus.replace('_', '-')}`);
                statusBadge.textContent = this.formatStatusText(newStatus);
            }

            // Update continue learning button
            const continueBtn = courseCard.querySelector('.continue-btn');
            if (continueBtn) {
                if (progressData.completion_percentage === 100) {
                    continueBtn.textContent = 'Review Course';
                    continueBtn.classList.add('btn-completed');
                } else if (progressData.completion_percentage > 0) {
                    continueBtn.textContent = 'Continue Learning';
                    continueBtn.classList.remove('btn-completed');
                } else {
                    continueBtn.textContent = 'Start Learning';
                    continueBtn.classList.remove('btn-completed');
                }
            }

            // Add completion celebration if just completed
            if (progressData.completion_percentage === 100 && !courseCard.classList.contains('celebration-shown')) {
                courseCard.classList.add('celebration-shown');
                this.triggerCompletionCelebration(courseCard);
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
            
            element.textContent = current + '%';
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    determineProgressStatus(percentage) {
        if (percentage === 100) return 'completed';
        if (percentage > 0) return 'in_progress';
        return 'not_started';
    }

    formatStatusText(status) {
        const statusMap = {
            'not_started': 'Not Started',
            'in_progress': 'In Progress',
            'completed': 'Completed'
        };
        return statusMap[status] || 'Unknown';
    }

    formatLastAccessed(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
        return date.toLocaleDateString();
    }

    triggerCompletionCelebration(courseCard) {
        // Add a subtle completion animation to the status badge
        const statusBadge = courseCard.querySelector('.course-status-badge');
        if (statusBadge) {
            statusBadge.style.animation = 'completionPulse 0.6s ease-in-out';
            setTimeout(() => {
                statusBadge.style.animation = '';
            }, 600);
        }
    }

    async refreshAllCourses() {
        if (this.isRefreshing) return;
        this.isRefreshing = true;

        try {
            const coursesData = await this.fetchAllCoursesData();
            if (!coursesData || !coursesData.success) {
                console.warn('Failed to fetch courses data');
                return;
            }

            // Update individual course data
            if (coursesData.courses) {
                coursesData.courses.forEach(courseData => {
                    this.updateCourseUI(courseData.course_id, courseData);
                });
            }

            console.log('Student courses data refreshed successfully');
        } catch (error) {
            console.error('Error refreshing student courses:', error);
        } finally {
            this.isRefreshing = false;
        }
    }

    startPeriodicRefresh(interval = 60000) { // 60 seconds
        this.stopPeriodicRefresh();
        
        this.refreshInterval = setInterval(() => {
            this.refreshAllCourses();
        }, interval);

        console.log(`Started student courses refresh every ${interval/1000} seconds`);
    }

    stopPeriodicRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    init() {
        // Check and update status badges on initial load
        this.updateInitialStatusBadges();
        
        // Disable automatic refresh to prevent status badge conflicts
        // Initial refresh with delay (disabled to prevent override)
        // setTimeout(() => {
        //     this.refreshAllCourses();
        // }, 2000);

        // Start periodic updates (disabled to prevent override)
        // this.startPeriodicRefresh();

        // Handle page visibility changes (disabled)
        // document.addEventListener('visibilitychange', () => {
        //     if (document.hidden) {
        //         this.stopPeriodicRefresh();
        //     } else {
        //         this.startPeriodicRefresh();
        //         this.refreshAllCourses();
        //     }
        // });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.stopPeriodicRefresh();
        });
    }

    updateInitialStatusBadges() {
        // Update status badges based on PHP-calculated progress data
        const courseCards = document.querySelectorAll('.course-card[data-course-id]');
        courseCards.forEach(card => {
            const statusBadge = card.querySelector('.course-status-badge');
            
            if (statusBadge) {
                // Get the actual progress status from PHP data attributes
                const progressStatus = card.dataset.progressStatus;
                const completionPercentage = parseFloat(card.dataset.completionPercentage) || 0;
                
                console.log(`Course ${card.dataset.courseId}: Status=${progressStatus}, Progress=${completionPercentage}%`);
                
                if (progressStatus) {
                    // Use the PHP-calculated status directly
                    const statusClasses = ['status-not-started', 'status-in-progress', 'status-completed'];
                    statusClasses.forEach(cls => statusBadge.classList.remove(cls));
                    statusBadge.classList.add(`status-${progressStatus.replace('_', '-')}`);
                    statusBadge.textContent = this.formatStatusText(progressStatus);
                } else {
                    // Fallback: derive status from completion percentage if status is missing
                    let fallbackStatus = 'not_started';
                    if (completionPercentage >= 100) {
                        fallbackStatus = 'completed';
                    } else if (completionPercentage > 0) {
                        fallbackStatus = 'in_progress';
                    }
                    
                    const statusClasses = ['status-not-started', 'status-in-progress', 'status-completed'];
                    statusClasses.forEach(cls => statusBadge.classList.remove(cls));
                    statusBadge.classList.add(`status-${fallbackStatus.replace('_', '-')}`);
                    statusBadge.textContent = this.formatStatusText(fallbackStatus);
                    
                    console.log(`Course ${card.dataset.courseId}: Using fallback status=${fallbackStatus}`);
                }
            }
        });
    }
}

// Initialize progress tracking
function initializeProgressTracking() {
    window.studentCoursesProgressTracker = new StudentCoursesProgressTracker();
    window.studentCoursesProgressTracker.init();
}

// Make available globally
window.StudentCoursesProgressTracker = StudentCoursesProgressTracker;

