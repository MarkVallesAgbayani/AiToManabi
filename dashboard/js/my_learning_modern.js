/**
 * Modern My Learning JavaScript
 * Handles animations, interactions, and enhanced functionality
 */

class MyLearningManager {
    constructor() {
        this.courses = [];
        this.filteredCourses = [];
        this.progressTracker = null;
        this.init();
    }

    init() {
        this.createAbstractBackground();
        this.initializeAnimations();
        this.setupFilterFunctionality();
        this.setupProgressAnimations();
        this.setupInteractionEffects();
        this.updateScrollEffects();
        this.initializeProgressTracking();
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            this.animatePageLoad();
        });
    }

    createAbstractBackground() {
        // Create abstract background container
        const abstractBg = document.createElement('div');
        abstractBg.className = 'abstract-bg';
        
        // Create floating shapes
        for (let i = 1; i <= 4; i++) {
            const shape = document.createElement('div');
            shape.className = `abstract-shape shape-${i}`;
            abstractBg.appendChild(shape);
        }
        
        document.body.appendChild(abstractBg);
    }

    initializeAnimations() {
        // Set initial progress bar widths based on data-progress attributes
        this.setInitialProgressBars();
        
        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.fade-in, .slide-up').forEach(el => {
            observer.observe(el);
        });
    }

    setupFilterFunctionality() {
        const courseGrid = document.getElementById('courseGrid');
        const courseSearch = document.getElementById('courseSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const progressFilter = document.getElementById('progressFilter');
        const sortBy = document.getElementById('sortBy');

        if (!courseGrid) return;

        // Store original courses
        this.courses = Array.from(courseGrid.children);
        this.filteredCourses = [...this.courses];

        // Enhanced filter function
        const filterAndSortCourses = () => {
            const searchTerm = courseSearch?.value.toLowerCase() || '';
            const category = categoryFilter?.value || '';
            const progress = progressFilter?.value || '';
            const sort = sortBy?.value || 'last_accessed';

            // Filter courses
            this.filteredCourses = this.courses.filter(course => {
                const title = course.querySelector('.course-title')?.textContent.toLowerCase() || '';
                const description = course.querySelector('.course-description')?.textContent.toLowerCase() || '';
                const courseCategory = course.querySelector('.course-category')?.textContent.trim() || '';
                const progressStatus = course.querySelector('.course-status-text')?.textContent.trim().toLowerCase().replace(/\s+/g, '_') || '';

                const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
                const matchesCategory = !category || courseCategory === category;
                const matchesProgress = !progress || progressStatus.includes(progress);

                return matchesSearch && matchesCategory && matchesProgress;
            });

            // Sort courses
            this.sortCourses(sort);

            // Update display with animation
            this.updateCourseDisplay();
        };

        // Add event listeners with debouncing for search
        if (courseSearch) {
            let searchTimeout;
            courseSearch.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(filterAndSortCourses, 300);
            });
        }

        [categoryFilter, progressFilter, sortBy].forEach(filter => {
            filter?.addEventListener('change', filterAndSortCourses);
        });

        // Initial filter/sort
        filterAndSortCourses();
    }

    sortCourses(sortBy) {
        this.filteredCourses.sort((a, b) => {
            switch(sortBy) {
                case 'title':
                    const titleA = a.querySelector('.course-title')?.textContent || '';
                    const titleB = b.querySelector('.course-title')?.textContent || '';
                    return titleA.localeCompare(titleB);
                
                case 'progress':
                    const progressA = this.getProgressPercentage(a);
                    const progressB = this.getProgressPercentage(b);
                    return progressB - progressA;
                
                case 'enrolled_date':
                    const dateA = this.getEnrolledDate(a);
                    const dateB = this.getEnrolledDate(b);
                    return new Date(dateB) - new Date(dateA);
                
                case 'last_accessed':
                default:
                    const lastA = this.getLastAccessedDate(a);
                    const lastB = this.getLastAccessedDate(b);
                    return new Date(lastB) - new Date(lastA);
            }
        });
    }

    getProgressPercentage(courseElement) {
        const progressText = courseElement.querySelector('.progress-percentage')?.textContent || '0%';
        return parseInt(progressText.replace('%', '')) || 0;
    }

    getEnrolledDate(courseElement) {
        const enrolledText = courseElement.querySelector('.course-enrolled-date')?.textContent || '';
        const dateMatch = enrolledText.match(/Enrolled: (.+)/);
        return dateMatch ? dateMatch[1] : '1970-01-01';
    }

    getLastAccessedDate(courseElement) {
        const lastAccessedText = courseElement.querySelector('.last-accessed')?.textContent || '';
        const dateMatch = lastAccessedText.match(/Last accessed: (.+)/);
        return dateMatch ? dateMatch[1] : '1970-01-01 00:00:00';
    }

    updateCourseDisplay() {
        const courseGrid = document.getElementById('courseGrid');
        if (!courseGrid) return;

        // Fade out current courses
        Array.from(courseGrid.children).forEach((course, index) => {
            course.style.opacity = '0';
            course.style.transform = 'translateY(20px)';
            setTimeout(() => {
                course.style.display = 'none';
            }, 200);
        });

        // Update grid with filtered courses
        setTimeout(() => {
            courseGrid.innerHTML = '';
            
            if (this.filteredCourses.length === 0) {
                this.showEmptyState(courseGrid);
                return;
            }

            this.filteredCourses.forEach((course, index) => {
                courseGrid.appendChild(course);
                course.style.display = 'block';
                
                // Animate in with stagger
                setTimeout(() => {
                    course.style.opacity = '1';
                    course.style.transform = 'translateY(0)';
                    course.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                }, index * 100);
            });
        }, 250);
    }

    showEmptyState(container) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state fade-in';
        emptyState.innerHTML = `
            <div class="empty-icon">🔍</div>
            <h3 class="empty-title rubik-bold">No Modules Found</h3>
            <p class="empty-description rubik-regular">Try adjusting your search filters to find more courses.</p>
        `;
        container.appendChild(emptyState);
        
        setTimeout(() => {
            emptyState.classList.add('visible');
        }, 100);
    }

    setupProgressAnimations() {
        // Animate progress bars on page load
        document.querySelectorAll('.progress-fill').forEach(progressBar => {
            const percentage = progressBar.getAttribute('data-progress') || 
                            progressBar.style.width.replace('%', '') || '0';
            
            // Start from 0 and animate to target
            progressBar.style.width = '0%';
            
            setTimeout(() => {
                progressBar.style.width = `${percentage}%`;
            }, 500);
        });

        // Add hover effects to progress sections
        document.querySelectorAll('.progress-section').forEach(section => {
            section.addEventListener('mouseenter', () => {
                const progressBar = section.querySelector('.progress-fill');
                if (progressBar) {
                    progressBar.style.transform = 'scaleY(1.2)';
                    progressBar.style.filter = 'brightness(1.1)';
                }
            });

            section.addEventListener('mouseleave', () => {
                const progressBar = section.querySelector('.progress-fill');
                if (progressBar) {
                    progressBar.style.transform = 'scaleY(1)';
                    progressBar.style.filter = 'brightness(1)';
                }
            });
        });
    }

    setInitialProgressBars() {
        // Set progress bar widths based on data-progress attributes immediately
        document.querySelectorAll('.progress-fill').forEach(progressBar => {
            const percentage = progressBar.getAttribute('data-progress') || 0;
            progressBar.style.width = `${percentage}%`;
            
            // Add completion styling if 100%
            if (percentage >= 100) {
                progressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
            }
        });
    }

    // Progress Tracking Integration
    initializeProgressTracking() {
        this.progressTracker = new MyLearningProgressTracker();
        this.progressTracker.init();
    }

    setupInteractionEffects() {
        // Enhanced hover effects for course cards
        document.querySelectorAll('.course-card').forEach(card => {
            let hoverTimeout;

            card.addEventListener('mouseenter', () => {
                clearTimeout(hoverTimeout);
                
                // Add glow effect
                card.style.boxShadow = '0 20px 60px rgba(239, 68, 68, 0.15)';
                
                // Animate image
                const image = card.querySelector('.course-image');
                if (image) {
                    image.style.transform = 'scale(1.05)';
                }

                // Animate status badge
                const badge = card.querySelector('.course-status-badge');
                if (badge) {
                    badge.style.transform = 'scale(1.05)';
                }
            });

            card.addEventListener('mouseleave', () => {
                hoverTimeout = setTimeout(() => {
                    card.style.boxShadow = '';
                    
                    const image = card.querySelector('.course-image');
                    if (image) {
                        image.style.transform = 'scale(1)';
                    }

                    const badge = card.querySelector('.course-status-badge');
                    if (badge) {
                        badge.style.transform = 'scale(1)';
                    }
                }, 100);
            });
        });

        // Button loading states
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', () => {
                if (button.classList.contains('btn-primary')) {
                    const originalText = button.textContent;
                    button.style.position = 'relative';
                    button.style.color = 'transparent';
                    
                    // Add loading spinner
                    const spinner = document.createElement('div');
                    spinner.innerHTML = `
                        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle>
                            <path fill="currentColor" opacity="0.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    `;
                    spinner.style.position = 'absolute';
                    spinner.style.top = '50%';
                    spinner.style.left = '50%';
                    spinner.style.transform = 'translate(-50%, -50%)';
                    spinner.style.color = 'white';
                    
                    button.appendChild(spinner);
                    
                    // Reset after navigation (this would be cancelled by page navigation)
                    setTimeout(() => {
                        button.style.color = '';
                        button.removeChild(spinner);
                    }, 2000);
                }
            });
        });
    }

    updateScrollEffects() {
        const nav = document.querySelector('nav');
        if (!nav) return;

        let lastScrollTop = 0;
        const scrollThreshold = 10;

        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Update navigation style
            if (scrollTop > scrollThreshold) {
                nav.classList.add('modern-nav');
                nav.style.transform = 'translateY(0)';
                nav.style.backdropFilter = 'blur(10px)';
            } else {
                nav.classList.remove('modern-nav');
                nav.style.backdropFilter = 'blur(5px)';
            }

            // Hide/show navigation on scroll direction
            if (Math.abs(scrollTop - lastScrollTop) > scrollThreshold) {
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    // Scrolling down
                    nav.style.transform = 'translateY(-100%)';
                } else {
                    // Scrolling up
                    nav.style.transform = 'translateY(0)';
                }
                lastScrollTop = scrollTop;
            }
        });
    }

    animatePageLoad() {
        // Animate page elements on load
        const elementsToAnimate = [
            { selector: '.page-header', delay: 0 },
            { selector: '.filter-section', delay: 200 },
            { selector: '.course-card', delay: 400, stagger: 100 }
        ];

        elementsToAnimate.forEach(({ selector, delay, stagger = 0 }) => {
            const elements = document.querySelectorAll(selector);
            elements.forEach((element, index) => {
                element.classList.add('fade-in');
                setTimeout(() => {
                    element.classList.add('visible');
                }, delay + (index * stagger));
            });
        });

        // Animate progress bars after course cards
        setTimeout(() => {
            this.setupProgressAnimations();
        }, 800);
    }

    // Utility function for smooth scrolling
    smoothScrollTo(target, duration = 800) {
        const targetElement = document.querySelector(target);
        if (!targetElement) return;

        const targetPosition = targetElement.offsetTop - 100;
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const run = easeInOutQuad(timeElapsed, startPosition, distance, duration);
            window.scrollTo(0, run);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }

        function easeInOutQuad(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return c / 2 * t * t + b;
            t--;
            return -c / 2 * (t * (t - 2) - 1) + b;
        }

        requestAnimationFrame(animation);
    }
}

// Dark mode utilities
class DarkModeManager {
    constructor() {
        this.init();
    }

    init() {
        // Ensure dark mode state is properly applied to abstract shapes
        const updateShapeOpacity = () => {
            const isDark = document.documentElement.classList.contains('dark');
            const shapes = document.querySelectorAll('.abstract-shape');
            
            shapes.forEach(shape => {
                shape.style.opacity = isDark ? '0.12' : '0.08';
            });
        };

        // Watch for dark mode changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    updateShapeOpacity();
                }
            });
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });

        // Initial update
        updateShapeOpacity();
    }
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new MyLearningManager();
    new DarkModeManager();
});

// CSS Animation styles for loading spinner
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);

// Export for potential external use
window.MyLearningManager = MyLearningManager;

// Progress Tracking System for My Learning
class MyLearningProgressTracker {
    constructor() {
        this.refreshInterval = null;
        this.isRefreshing = false;
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

    async fetchAllEnrolledProgress() {
        try {
            const response = await fetch('../api/get_all_enrolled_progress.php', {
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
            console.error('Error fetching enrolled progress:', error);
            return null;
        }
    }

    updateCourseProgressUI(courseId, progressData) {
        const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
        if (!courseCard) return;

        // Update progress bar
        const progressBar = courseCard.querySelector('.progress-fill');
        const progressPercentage = courseCard.querySelector('.progress-percentage');
        const courseStatus = courseCard.querySelector('.course-status');
        const statusText = courseCard.querySelector('.course-status-text');

        if (progressBar && progressData.completion_percentage !== undefined) {
            // Smooth progress bar animation
            progressBar.style.transition = 'width 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            progressBar.style.width = `${progressData.completion_percentage}%`;
            progressBar.setAttribute('data-progress', progressData.completion_percentage);

            // Add visual feedback for completion
            if (progressData.completion_percentage === 100) {
                progressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
                setTimeout(() => {
                    courseCard.classList.add('course-completed');
                    this.showCompletionAnimation(courseCard);
                }, 800);
            }
        }

        if (progressPercentage) {
            const currentValue = parseInt(progressPercentage.textContent) || 0;
            const targetValue = Math.round(progressData.completion_percentage || 0);
            this.animateProgressCounter(progressPercentage, currentValue, targetValue);
        }

        // Update course status with enhanced styling
        if (courseStatus && statusText) {
            const newStatus = this.determineProgressStatus(progressData.completion_percentage);
            const statusClasses = ['status-not-started', 'status-in-progress', 'status-completed'];
            
            statusClasses.forEach(cls => courseStatus.classList.remove(cls));
            courseStatus.classList.add(`status-${newStatus.replace('_', '-')}`);
            statusText.textContent = this.formatStatusText(newStatus);

            // Add completion celebration
            if (newStatus === 'completed' && !courseCard.classList.contains('celebration-shown')) {
                courseCard.classList.add('celebration-shown');
                this.triggerCompletionCelebration(courseCard);
            }
        }

        // Update sections completed
        const sectionsText = courseCard.querySelector('.sections-completed');
        if (sectionsText && progressData.completed_sections !== undefined) {
            sectionsText.textContent = `${progressData.completed_sections} / ${progressData.total_sections} sections completed`;
        }

        // Update last accessed with more user-friendly format
        const lastAccessedElement = courseCard.querySelector('.last-accessed');
        if (lastAccessedElement && progressData.last_accessed_at) {
            lastAccessedElement.textContent = `Last accessed: ${this.formatLastAccessed(progressData.last_accessed_at)}`;
        }

        // Update continue learning button
        const continueBtn = courseCard.querySelector('.continue-btn');
        if (continueBtn) {
            if (progressData.completion_percentage === 100) {
                continueBtn.textContent = 'Review Module';
                continueBtn.classList.add('btn-completed');
            } else if (progressData.completion_percentage > 0) {
                continueBtn.textContent = 'Continue Learning';
                continueBtn.classList.remove('btn-completed');
            } else {
                continueBtn.textContent = 'Start Learning';
                continueBtn.classList.remove('btn-completed');
            }
        }
    }

    animateProgressCounter(element, start, end, duration = 1200) {
        if (start === end) return;

        const range = end - start;
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function for smooth animation
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
        const diffMinutes = Math.floor(diffTime / (1000 * 60));
        const diffHours = Math.floor(diffTime / (1000 * 60 * 60));
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

        if (diffMinutes < 5) return 'Just now';
        if (diffMinutes < 60) return `${diffMinutes} minutes ago`;
        if (diffHours < 24) return `${diffHours} hours ago`;
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
        return date.toLocaleDateString();
    }

    showCompletionAnimation(courseCard) {
        // Create completion badge
        const badge = document.createElement('div');
        badge.className = 'completion-badge';
        badge.innerHTML = '🎉 Completed!';
        badge.style.cssText = `
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.3s ease;
            z-index: 10;
        `;

        courseCard.style.position = 'relative';
        courseCard.appendChild(badge);

        // Animate badge appearance
        setTimeout(() => {
            badge.style.opacity = '1';
            badge.style.transform = 'scale(1)';
        }, 100);
    }

    triggerCompletionCelebration(courseCard) {
        // Create confetti effect
        const confettiColors = ['#ef4444', '#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'];
        
        for (let i = 0; i < 15; i++) {
            setTimeout(() => {
                this.createConfetti(courseCard, confettiColors[i % confettiColors.length]);
            }, i * 50);
        }
    }

    createConfetti(container, color) {
        const confetti = document.createElement('div');
        confetti.style.cssText = `
            position: absolute;
            width: 8px;
            height: 8px;
            background: ${color};
            top: 50%;
            left: 50%;
            pointer-events: none;
            z-index: 20;
            border-radius: 2px;
        `;

        container.appendChild(confetti);

        // Animate confetti
        const angle = Math.random() * 360;
        const velocity = 50 + Math.random() * 50;
        const gravity = 0.5;
        let x = 0, y = 0, vx = Math.cos(angle) * velocity, vy = Math.sin(angle) * velocity;

        const animate = () => {
            x += vx;
            y += vy;
            vy += gravity;

            confetti.style.transform = `translate(${x}px, ${y}px) rotate(${x}deg)`;
            confetti.style.opacity = Math.max(0, 1 - Math.abs(y) / 200);

            if (confetti.style.opacity > 0) {
                requestAnimationFrame(animate);
            } else {
                confetti.remove();
            }
        };

        requestAnimationFrame(animate);
    }

    async refreshAllProgress() {
        if (this.isRefreshing) return;
        this.isRefreshing = true;

        try {
            const progressData = await this.fetchAllEnrolledProgress();
            if (!progressData || !progressData.success) {
                console.warn('Failed to fetch enrolled progress data');
                return;
            }

            // Update individual course progress
            if (progressData.courses) {
                progressData.courses.forEach(courseData => {
                    this.updateCourseProgressUI(courseData.course_id, courseData);
                });
            }

            console.log('My Learning progress refreshed successfully');
        } catch (error) {
            console.error('Error refreshing My Learning progress:', error);
        } finally {
            this.isRefreshing = false;
        }
    }

    startPeriodicRefresh(interval = 45000) { // 45 seconds
        this.stopPeriodicRefresh();
        
        this.refreshInterval = setInterval(() => {
            this.refreshAllProgress();
        }, interval);

        console.log(`Started My Learning progress refresh every ${interval/1000} seconds`);
    }

    stopPeriodicRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    init() {
        // Update initial status badges based on current progress data
        this.updateInitialStatusBadges();
        
        // Initial progress fetch
        setTimeout(() => {
            this.refreshAllProgress();
        }, 1000); // Small delay to ensure page is loaded

        // Start periodic updates
        this.startPeriodicRefresh();

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPeriodicRefresh();
            } else {
                this.startPeriodicRefresh();
                this.refreshAllProgress(); // Immediate refresh when page becomes visible
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.stopPeriodicRefresh();
        });
    }

    updateInitialStatusBadges() {
        // Update status badges based on current progress data for immediate feedback
        const courseCards = document.querySelectorAll('.course-card[data-course-id]');
        courseCards.forEach(card => {
            const progressBar = card.querySelector('.progress-fill');
            const progressPercentage = card.querySelector('.progress-percentage');
            const courseStatus = card.querySelector('.course-status');
            const statusText = card.querySelector('.course-status-text');
            const continueBtn = card.querySelector('.continue-btn');
            
            if (progressBar && progressPercentage) {
                const percentage = progressBar.getAttribute('data-progress') || 0;
                
                // Animate progress bar
                progressBar.style.width = `${percentage}%`;
                
                // Determine status based on percentage
                let newStatus = 'not_started';
                if (percentage >= 100) {
                    newStatus = 'completed';
                } else if (percentage > 0) {
                    newStatus = 'in_progress';
                }
                
                // Update status badge
                if (courseStatus && statusText) {
                    const statusClasses = ['status-not-started', 'status-in-progress', 'status-completed'];
                    statusClasses.forEach(cls => courseStatus.classList.remove(cls));
                    courseStatus.classList.add(`status-${newStatus.replace('_', '-')}`);
                    statusText.textContent = this.formatStatusText(newStatus);
                }
                
                // Update button text
                if (continueBtn) {
                    if (percentage >= 100) {
                        continueBtn.textContent = 'Review Module';
                        continueBtn.classList.add('btn-completed');
                    } else if (percentage > 0) {
                        continueBtn.textContent = 'Continue Learning';
                        continueBtn.classList.remove('btn-completed');
                    } else {
                        continueBtn.textContent = 'Start Module';
                        continueBtn.classList.remove('btn-completed');
                    }
                }
            }
        });
    }
}
window.DarkModeManager = DarkModeManager;
