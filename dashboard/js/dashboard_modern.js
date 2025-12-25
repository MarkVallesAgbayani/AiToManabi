/**
 * Modern Dashboard JavaScript
 * Handles animations, interactions, and progressive enhancement
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard enhancements
    initializeAnimations();
    initializeProgressBars();
    initializeCardInteractions();
    initializeScrollEffects();
    initializeTooltips();
    initializeProgressTracking();
    
    // Fallback progress bar initialization
    setTimeout(setInitialProgressBars, 500);
});

/**
 * Fallback function to set initial progress bar widths
 */
function setInitialProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const progress = parseInt(bar.dataset.progress) || 0;
        if (bar.style.width === '0%' || !bar.style.width) {
            bar.style.width = progress + '%';
        }
        
        // Set correct background color
        if (progress >= 100) {
            bar.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        } else {
            bar.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%)';
        }
    });
    
    // Also update progress percentages
    const progressPercentages = document.querySelectorAll('.progress-percentage');
    progressPercentages.forEach(element => {
        const courseCard = element.closest('.course-card');
        if (courseCard) {
            const progressBar = courseCard.querySelector('.progress-fill');
            if (progressBar) {
                const progress = parseInt(progressBar.dataset.progress) || 0;
                element.textContent = progress > 0 ? `${progress}%` : '0';
            }
        }
    });
}

/**
 * Initialize entrance animations for dashboard elements
 */
function initializeAnimations() {
    // Add fade-in animation to stats cards with staggered delay
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });

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
}

/**
 * Initialize animated progress bars
 */
function initializeProgressBars() {
    // Wait for DOM to be fully loaded
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.progress-fill');
        console.log(`Found ${progressBars.length} progress bars to initialize`);
        
        progressBars.forEach((progressBar, index) => {
            const targetWidth = progressBar.dataset.progress || '0';
            console.log(`Progress bar ${index}: target width ${targetWidth}%`);
            
            // Reset width initially and set appropriate background
            progressBar.style.width = '0%';
            
            // Set the correct background color based on progress
            if (parseInt(targetWidth) >= 100) {
                progressBar.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            } else {
                progressBar.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%)';
            }
            
            // Create intersection observer for each progress bar
            const progressObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const bar = entry.target;
                        const progress = bar.dataset.progress || '0';
                        
                        // Animate to target width with delay
                        setTimeout(() => {
                            bar.style.transition = 'width 1.5s cubic-bezier(0.4, 0, 0.2, 1)';
                            bar.style.width = progress + '%';
                        }, index * 200 + 300);
                        
                        progressObserver.unobserve(bar);
                    }
                });
            }, { 
                threshold: 0.3,
                rootMargin: '0px 0px -50px 0px'
            });

            progressObserver.observe(progressBar);
        });
    }, 100);
}

/**
 * Initialize card interaction effects
 */
function initializeCardInteractions() {
    // Add hover sound effect (optional)
    const cards = document.querySelectorAll('.course-card, .stat-card');
    
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Add click ripple effect to buttons
    const buttons = document.querySelectorAll('.continue-btn, .empty-action');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            createRippleEffect(e, this);
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
        pointer-events: none;
        animation: ripple 0.6s ease-out;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    // Remove ripple after animation
    setTimeout(() => {
        ripple.remove();
    }, 600);
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
 * Initialize tooltips for enhanced UX
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
    const tooltipText = element.dataset.tooltip;
    const tooltip = document.createElement('div');
    
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        z-index: 1000;
        pointer-events: none;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.3s ease;
        white-space: nowrap;
    `;
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.bottom + 10 + 'px';
    
    // Animate in
    requestAnimationFrame(() => {
        tooltip.style.opacity = '1';
        tooltip.style.transform = 'translateY(0)';
    });
}

/**
 * Hide tooltip
 */
function hideTooltip() {
    const tooltip = document.querySelector('.custom-tooltip');
    if (tooltip) {
        tooltip.style.opacity = '0';
        tooltip.style.transform = 'translateY(10px)';
        setTimeout(() => tooltip.remove(), 300);
    }
}

/**
 * Utility function to format numbers
 */
function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

/**
 * Utility function to animate counters
 */
function animateCounter(element, start, end, duration = 1000) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= end) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 16);
}

/**
 * Initialize counter animations for stats
 */
function initializeCounters() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.textContent);
                entry.target.textContent = '0';
                animateCounter(entry.target, 0, target, 1500);
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    statNumbers.forEach(number => {
        counterObserver.observe(number);
    });
}

/**
 * Add loading states for dynamic content
 */
function showLoadingState(container) {
    const skeleton = document.createElement('div');
    skeleton.className = 'skeleton';
    skeleton.style.cssText = `
        width: 100%;
        height: 200px;
        border-radius: 16px;
        margin-bottom: 1rem;
    `;
    container.appendChild(skeleton);
}

/**
 * Remove loading states
 */
function hideLoadingState(container) {
    const skeletons = container.querySelectorAll('.skeleton');
    skeletons.forEach(skeleton => skeleton.remove());
}

/**
 * Handle responsive navigation
 */
function initializeResponsiveNav() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            this.classList.toggle('active');
        });
    }
}

/**
 * Initialize all enhancements after DOM is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure all elements are rendered
    setTimeout(() => {
        initializeCounters();
        initializeResponsiveNav();
    }, 100);
});

/**
 * Handle page visibility changes for performance
 */
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Pause animations when page is not visible
        document.querySelectorAll('.abstract-shape').forEach(shape => {
            shape.style.animationPlayState = 'paused';
        });
    } else {
        // Resume animations when page becomes visible
        document.querySelectorAll('.abstract-shape').forEach(shape => {
            shape.style.animationPlayState = 'running';
        });
    }
});

/**
 * Export functions for external use
 */
window.DashboardModern = {
    animateCounter,
    createRippleEffect,
    showLoadingState,
    hideLoadingState,
    formatNumber
};

/**
 * Progress Tracking System
 */
class ProgressTracker {
    constructor() {
        this.progressUpdateInterval = null;
        this.lastProgressUpdate = {};
    }

    async fetchCourseProgress(courseId) {
        try {
            const response = await fetch(`../api/get_course_progress.php?course_id=${courseId}`, {
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

    async fetchAllProgressData() {
        try {
            const response = await fetch('../api/get_all_progress.php', {
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
            console.error('Error fetching all progress data:', error);
            return null;
        }
    }

    updateProgressUI(courseId, progressData) {
        const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
        if (!courseCard) return;

        // Update progress bar
        const progressBar = courseCard.querySelector('.progress-fill');
        const progressPercentage = courseCard.querySelector('.progress-percentage');
        const statusBadge = courseCard.querySelector('.course-status-badge');

        if (progressBar && progressData.completion_percentage !== undefined) {
            // Animate progress bar update
            progressBar.style.transition = 'width 0.5s ease-in-out';
            progressBar.style.width = `${progressData.completion_percentage}%`;
            progressBar.setAttribute('data-progress', progressData.completion_percentage);
            
            // Update color based on completion
            if (progressData.completion_percentage >= 100) {
                progressBar.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            } else {
                progressBar.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%)';
            }
        }

        if (progressPercentage) {
            // Animate percentage counter with % only when > 0
            const targetValue = Math.round(progressData.completion_percentage || 0);
            const currentValue = parseInt(progressPercentage.textContent) || 0;
            this.animateProgressCounter(progressPercentage, currentValue, targetValue);
        }

        // Update status badge for enrolled courses
        if (statusBadge && progressData.completion_percentage !== undefined) {
            const newStatus = this.determineProgressStatus(progressData.completion_percentage);
            const statusClasses = ['not-started', 'in-progress', 'completed'];
            
            // Remove all status classes
            statusClasses.forEach(cls => statusBadge.classList.remove(cls));
            
            // Add new status class
            statusBadge.classList.add(newStatus.replace('_', '-'));
            statusBadge.textContent = this.formatStatusText(newStatus);
        }

        // Update button text based on progress status
        const continueBtn = courseCard.querySelector('.continue-btn');
        if (continueBtn && progressData.completion_percentage !== undefined) {
            const newStatus = this.determineProgressStatus(progressData.completion_percentage);
            continueBtn.textContent = this.getButtonText(newStatus);
        }

        // Update completed sections text
        const sectionsText = courseCard.querySelector('.sections-completed');
        if (sectionsText && progressData.completed_sections !== undefined) {
            sectionsText.textContent = `${progressData.completed_sections} / ${progressData.total_sections} sections`;
        }
    }

    updateDashboardStats(allProgressData) {
        if (!allProgressData || !allProgressData.stats) return;

        const stats = allProgressData.stats;

        // Update enrolled courses count
        const enrolledStat = document.querySelector('.stat-card.enrolled .stat-number');
        if (enrolledStat) {
            this.animateProgressCounter(enrolledStat, parseInt(enrolledStat.textContent) || 0, stats.enrolled_courses || 0);
        }

        // Update completed courses count
        const completedStat = document.querySelector('.stat-card.completed .stat-number');
        if (completedStat) {
            this.animateProgressCounter(completedStat, parseInt(completedStat.textContent) || 0, stats.completed_courses || 0);
        }

        // Update overall progress if available
        const overallProgressBar = document.querySelector('.overall-progress-bar');
        if (overallProgressBar && stats.overall_progress !== undefined) {
            overallProgressBar.style.width = `${stats.overall_progress}%`;
        }
    }

    animateProgressCounter(element, start, end, duration = 1000) {
        if (start === end) return;

        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            
            // Handle percentage display - add % only when > 0
            const value = Math.floor(current);
            if (element.classList.contains('progress-percentage')) {
                element.textContent = value > 0 ? `${value}%` : '0';
            } else {
                element.textContent = value;
            }
        }, 16);
    }

    determineProgressStatus(percentage) {
        if (percentage >= 100) return 'completed';
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

    getButtonText(status) {
        const buttonTextMap = {
            'not_started': 'Start Module',
            'in_progress': 'Continue Learning',
            'completed': 'Review Module'
        };
        return buttonTextMap[status] || 'View Module';
    }

    async refreshAllProgress() {
        try {
            const allProgressData = await this.fetchAllProgressData();
            if (!allProgressData || !allProgressData.success) {
                console.warn('Failed to fetch progress data');
                return;
            }

            // Update dashboard stats
            this.updateDashboardStats(allProgressData);

            // Update individual course progress
            if (allProgressData.courses) {
                allProgressData.courses.forEach(courseData => {
                    this.updateProgressUI(courseData.course_id, courseData);
                });
            }

            console.log('Progress data refreshed successfully');
        } catch (error) {
            console.error('Error refreshing progress:', error);
        }
    }

    startPeriodicProgressUpdate(interval = 30000) { // 30 seconds
        this.stopPeriodicProgressUpdate(); // Clear any existing interval
        
        this.progressUpdateInterval = setInterval(() => {
            this.refreshAllProgress();
        }, interval);

        console.log(`Started periodic progress updates every ${interval/1000} seconds`);
    }

    stopPeriodicProgressUpdate() {
        if (this.progressUpdateInterval) {
            clearInterval(this.progressUpdateInterval);
            this.progressUpdateInterval = null;
            console.log('Stopped periodic progress updates');
        }
    }

    // Initialize progress tracking when page becomes visible
    init() {
        // Initial progress fetch
        this.refreshAllProgress();

        // Start periodic updates
        this.startPeriodicProgressUpdate();

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPeriodicProgressUpdate();
            } else {
                this.startPeriodicProgressUpdate();
                this.refreshAllProgress(); // Immediate refresh when page becomes visible
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.stopPeriodicProgressUpdate();
        });
    }
}

// Initialize progress tracking
function initializeProgressTracking() {
    window.progressTracker = new ProgressTracker();
    window.progressTracker.init();
}

// Make progress tracker available globally
window.ProgressTracker = ProgressTracker;
