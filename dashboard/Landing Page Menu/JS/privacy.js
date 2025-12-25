// Privacy Policy Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize smooth scrolling for anchor links
    initializeSmoothScrolling();
    
    
    // Initialize header scroll effects
    initializeHeaderEffects();
    
    // Initialize mobile menu functionality
    initializeMobileMenu();
    
    // Initialize accessibility features
    initializeAccessibility();
});

// Smooth scrolling for anchor links
function initializeSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const headerHeight = document.getElementById('main-header').offsetHeight;
                const targetPosition = target.offsetTop - headerHeight - 20;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Update URL without jumping
                history.pushState(null, null, this.getAttribute('href'));
            }
        });
    });
}


// Header scroll effects
function initializeHeaderEffects() {
    const header = document.getElementById('main-header');
    let lastScrollTop = 0;
    let ticking = false;
    
    function updateHeader() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Add/remove backdrop blur based on scroll position
        if (scrollTop > 100) {
            header.classList.add('bg-white/95', 'backdrop-blur-md');
            header.classList.remove('bg-white');
        } else {
            header.classList.remove('bg-white/95', 'backdrop-blur-md');
            header.classList.add('bg-white');
        }
        
        // Hide/show header on scroll direction
        if (scrollTop > lastScrollTop && scrollTop > 200) {
            // Scrolling down
            header.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            header.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
        ticking = false;
    }
    
    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }
    
    window.addEventListener('scroll', requestTick, { passive: true });
}

// Mobile menu functionality
function initializeMobileMenu() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            
            // Update aria-expanded attribute
            const isExpanded = !mobileMenu.classList.contains('hidden');
            mobileMenuButton.setAttribute('aria-expanded', isExpanded);
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.add('hidden');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Close mobile menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
    }
}

// Accessibility features
function initializeAccessibility() {
    // Add skip to content link
    const skipLink = document.createElement('a');
    skipLink.href = '#main-content';
    skipLink.textContent = 'Skip to main content';
    skipLink.className = 'sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-red-600 text-white px-4 py-2 rounded z-50';
    document.body.insertBefore(skipLink, document.body.firstChild);
    
    // Add main content id
    const mainContent = document.querySelector('main');
    if (mainContent) {
        mainContent.id = 'main-content';
    }
    
    // Improve focus management for details/summary elements
    const detailsElements = document.querySelectorAll('details');
    detailsElements.forEach(details => {
        const summary = details.querySelector('summary');
        if (summary) {
            summary.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    details.open = !details.open;
                }
            });
        }
    });
    
}

// Utility function to debounce function calls
function debounce(func, wait) {
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

// Print functionality
function printPrivacy() {
    window.print();
}

// Share functionality
function sharePrivacy() {
    if (navigator.share) {
        navigator.share({
            title: 'Privacy Policy - AiToManabi',
            text: 'Read our Privacy Policy',
            url: window.location.href
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href).then(() => {
            // Show a temporary notification
            const notification = document.createElement('div');
            notification.textContent = 'Link copied to clipboard!';
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        });
    }
}

// Search functionality for privacy content
function initializeSearch() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search privacy policy...';
    searchInput.className = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500';
    
    const searchContainer = document.createElement('div');
    searchContainer.className = 'mb-6';
    searchContainer.appendChild(searchInput);
    
    const contentContainer = document.querySelector('.prose');
    if (contentContainer) {
        contentContainer.parentNode.insertBefore(searchContainer, contentContainer);
        
        searchInput.addEventListener('input', debounce(function() {
            const searchTerm = this.value.toLowerCase();
            const content = contentContainer.textContent.toLowerCase();
            
            if (searchTerm && content.includes(searchTerm)) {
                // Highlight search results (simplified version)
                contentContainer.style.backgroundColor = searchTerm ? '#fef2f2' : 'transparent';
            } else {
                contentContainer.style.backgroundColor = 'transparent';
            }
        }, 300));
    }
}

// Initialize search if needed
// initializeSearch();

// Console welcome message
console.log('%c🔒 Privacy Policy Page Loaded', 'color: #dc2626; font-size: 16px; font-weight: bold;');
console.log('%cAiToManabi Privacy Documentation', 'color: #6b7280; font-size: 12px;');
