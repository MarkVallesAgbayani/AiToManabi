// Enhanced Course Card Interactions

class CourseCardManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupHoverEffects();
        this.setupPurchaseButtons();
        this.setupImageLazyLoading();
        this.setupScrollAnimations();
        this.setupTooltips();
        this.setupMobileOptimizations();
    }

    // Enhanced hover effects
    setupHoverEffects() {
        const courseCards = document.querySelectorAll('.course-card');
        
        courseCards.forEach(card => {
            card.addEventListener('mouseenter', (e) => {
                this.onCardHover(e.target);
            });
            
            card.addEventListener('mouseleave', (e) => {
                this.onCardLeave(e.target);
            });
        });
    }

    onCardHover(card) {
        // Add dynamic glow effect
        card.style.transform = 'translateY(-8px)';
        card.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        
        // Animate the purchase button
        const button = card.querySelector('.btn-purchase');
        if (button) {
            button.style.transform = 'scale(1.05)';
            button.style.boxShadow = '0 20px 40px rgba(239, 68, 68, 0.3)';
        }

        // Animate price highlight
        const price = card.querySelector('.price-highlight');
        if (price) {
            price.style.transform = 'scale(1.1) rotate(-2deg)';
        }
    }

    onCardLeave(card) {
        card.style.transform = 'translateY(0)';
        
        const button = card.querySelector('.btn-purchase');
        if (button) {
            button.style.transform = 'scale(1)';
            button.style.boxShadow = '0 4px 15px rgba(239, 68, 68, 0.2)';
        }

        const price = card.querySelector('.price-highlight');
        if (price) {
            price.style.transform = 'scale(1) rotate(0deg)';
        }
    }

    // Purchase button functionality
    setupPurchaseButtons() {
        const purchaseButtons = document.querySelectorAll('.btn-purchase');
        
        purchaseButtons.forEach(button => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                const courseId = button.dataset.courseId;
                const courseTitle = button.dataset.courseTitle;
                const coursePrice = button.dataset.coursePrice;
                
                await this.handlePurchase(courseId, courseTitle, coursePrice, button);
            });
        });
    }

    async handlePurchase(courseId, courseTitle, coursePrice, button) {
        // Show loading state
        const originalText = button.innerHTML;
        button.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Processing...
        `;
        button.disabled = true;

        try {
            // Check if user is logged in
            const response = await fetch('/AIToManabi_Updated/api/check_enrollment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    course_id: courseId,
                    action: 'check_login'
                })
            });

            const result = await response.json();

            if (!result.logged_in) {
                // Redirect to login
                this.showLoginPrompt(courseId, courseTitle);
            } else if (result.enrolled) {
                // User is already enrolled, redirect to course
                window.location.href = `/AIToManabi_Updated/course_view.php?id=${courseId}`;
            } else {
                // Proceed with purchase
                await this.initiatePurchase(courseId, courseTitle, coursePrice);
            }
        } catch (error) {
            console.error('Purchase error:', error);
            this.showErrorMessage('Something went wrong. Please try again.');
        } finally {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    showLoginPrompt(courseId, courseTitle) {
        const modal = this.createModal(
            'Login Required',
            `Please log in to purchase "${courseTitle}"`,
            [
                {
                    text: 'Login',
                    action: () => {
                        window.location.href = `/AIToManabi_Updated/dashboard/login.php?redirect=course_${courseId}`;
                    },
                    class: 'bg-red-500 hover:bg-red-600 text-white'
                },
                {
                    text: 'Sign Up',
                    action: () => {
                        window.location.href = `/AIToManabi_Updated/dashboard/signup.php?redirect=course_${courseId}`;
                    },
                    class: 'bg-gray-500 hover:bg-gray-600 text-white'
                }
            ]
        );
        document.body.appendChild(modal);
    }

    async initiatePurchase(courseId, courseTitle, coursePrice) {
        // Here you would integrate with your payment system
        // For now, we'll simulate enrollment
        try {
            const response = await fetch('/AIToManabi_Updated/api/save_course.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    course_id: courseId,
                    action: 'enroll'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccessMessage(`Successfully enrolled in "${courseTitle}"!`);
                setTimeout(() => {
                    window.location.href = `/AIToManabi_Updated/course_view.php?id=${courseId}`;
                }, 2000);
            } else {
                this.showErrorMessage(result.message || 'Enrollment failed');
            }
        } catch (error) {
            this.showErrorMessage('Enrollment failed. Please try again.');
        }
    }

    // Image lazy loading
    setupImageLazyLoading() {
        const images = document.querySelectorAll('.course-card img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('opacity-0');
                    img.classList.add('opacity-100');
                    observer.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // Scroll animations
    setupScrollAnimations() {
        const cards = document.querySelectorAll('.course-card');
        
        const cardObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 100);
                }
            });
        }, {
            threshold: 0.1
        });

        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            cardObserver.observe(card);
        });
    }

    // Tooltip functionality
    setupTooltips() {
        const elements = document.querySelectorAll('[data-tooltip]');
        
        elements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            
            element.addEventListener('mouseleave', (e) => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'absolute z-50 px-3 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg shadow-sm tooltip';
        tooltip.innerHTML = text;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    }

    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    // Mobile optimizations
    setupMobileOptimizations() {
        if (window.innerWidth <= 768) {
            const cards = document.querySelectorAll('.course-card');
            
            cards.forEach(card => {
                // Touch-friendly interactions
                card.addEventListener('touchstart', (e) => {
                    card.style.transform = 'scale(0.98)';
                });
                
                card.addEventListener('touchend', (e) => {
                    setTimeout(() => {
                        card.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        }
    }

    // Utility methods
    createModal(title, message, buttons) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        
        const modalContent = `
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                    <h3 class="text-lg font-medium text-gray-900">${title}</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">${message}</p>
                    </div>
                    <div class="items-center px-4 py-3 space-x-2">
                        ${buttons.map(button => `
                            <button class="px-4 py-2 rounded-md text-sm font-medium ${button.class}" onclick="${button.action.toString().replace('function ', '').replace('()', '')}(); this.closest('.fixed').remove();">
                                ${button.text}
                            </button>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
        
        modal.innerHTML = modalContent;
        return modal;
    }

    showSuccessMessage(message) {
        this.showNotification(message, 'success');
    }

    showErrorMessage(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        notification.innerHTML = `
            <div class="flex items-center">
                <span>${message}</span>
                <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
}

// Search functionality
class CourseSearch {
    constructor() {
        this.setupSearch();
    }

    setupSearch() {
        const searchInput = document.getElementById('courseSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.filterCourses.bind(this), 300));
        }
        
        if (categoryFilter) {
            categoryFilter.addEventListener('change', this.filterCourses.bind(this));
        }
    }

    filterCourses() {
        const searchTerm = document.getElementById('courseSearch')?.value.toLowerCase() || '';
        const selectedCategory = document.getElementById('categoryFilter')?.value || '';
        const courseCards = document.querySelectorAll('.course-card');
        
        courseCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const description = card.querySelector('p').textContent.toLowerCase();
            const category = card.dataset.category || '';
            
            const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
            const matchesCategory = !selectedCategory || category === selectedCategory;
            
            if (matchesSearch && matchesCategory) {
                card.style.display = 'block';
                card.style.animation = 'fadeInUp 0.6s ease-out forwards';
            } else {
                card.style.display = 'none';
            }
        });
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new CourseCardManager();
    new CourseSearch();
    
    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Performance monitoring
const performanceMonitor = {
    markStart: (name) => {
        if (window.performance && window.performance.mark) {
            window.performance.mark(`${name}-start`);
        }
    },
    
    markEnd: (name) => {
        if (window.performance && window.performance.mark) {
            window.performance.mark(`${name}-end`);
            window.performance.measure(name, `${name}-start`, `${name}-end`);
        }
    }
};

// Track page load performance
window.addEventListener('load', () => {
    performanceMonitor.markEnd('page-load');
    console.log('Page loaded successfully');
});
