/**
 * Collapsible Section JavaScript
 * Handles the interactive behavior for collapsible sections
 */

/**
 * Toggle a collapsible section
 * @param {string} id - The unique identifier of the collapsible section
 */
function toggleCollapsible(id) {
    const content = document.getElementById(id + '-content');
    const icon = document.getElementById(id + '-icon');
    const header = content.previousElementSibling;
    
    if (!content || !icon) {
        console.error('Collapsible elements not found for ID:', id);
        return;
    }
    
    const isCollapsed = content.classList.contains('collapsed');
    
    if (isCollapsed) {
        expandSection(content, icon, header);
    } else {
        collapseSection(content, icon, header);
    }
    
    // Save state to localStorage for persistence
    saveCollapsibleState(id, !isCollapsed);
}

/**
 * Expand a collapsible section
 * @param {HTMLElement} content - The content element
 * @param {HTMLElement} icon - The icon element
 * @param {HTMLElement} header - The header button element
 */
function expandSection(content, icon, header) {
    content.classList.remove('collapsed');
    content.classList.add('expanded');
    icon.classList.add('rotated');
    
    if (header) {
        header.setAttribute('aria-expanded', 'true');
    }
}

/**
 * Collapse a collapsible section
 * @param {HTMLElement} content - The content element
 * @param {HTMLElement} icon - The icon element
 * @param {HTMLElement} header - The header button element
 */
function collapseSection(content, icon, header) {
    content.classList.remove('expanded');
    content.classList.add('collapsed');
    icon.classList.remove('rotated');
    
    if (header) {
        header.setAttribute('aria-expanded', 'false');
    }
}

/**
 * Expand all collapsible sections on the page
 */
function expandAllCollapsible() {
    const allSections = document.querySelectorAll('.collapsible-content');
    allSections.forEach(content => {
        const id = content.id.replace('-content', '');
        const icon = document.getElementById(id + '-icon');
        const header = content.previousElementSibling;
        
        if (content.classList.contains('collapsed')) {
            expandSection(content, icon, header);
            saveCollapsibleState(id, false);
        }
    });
}

/**
 * Collapse all collapsible sections on the page
 */
function collapseAllCollapsible() {
    const allSections = document.querySelectorAll('.collapsible-content');
    allSections.forEach(content => {
        const id = content.id.replace('-content', '');
        const icon = document.getElementById(id + '-icon');
        const header = content.previousElementSibling;
        
        if (content.classList.contains('expanded')) {
            collapseSection(content, icon, header);
            saveCollapsibleState(id, true);
        }
    });
}

/**
 * Save collapsible state to localStorage
 * @param {string} id - The section ID
 * @param {boolean} collapsed - Whether the section is collapsed
 */
function saveCollapsibleState(id, collapsed) {
    try {
        let states = JSON.parse(localStorage.getItem('collapsibleStates') || '{}');
        states[id] = collapsed;
        localStorage.setItem('collapsibleStates', JSON.stringify(states));
    } catch (error) {
        console.warn('Could not save collapsible state:', error);
    }
}

/**
 * Load collapsible states from localStorage
 */
function loadCollapsibleStates() {
    try {
        const states = JSON.parse(localStorage.getItem('collapsibleStates') || '{}');
        
        Object.keys(states).forEach(id => {
            const content = document.getElementById(id + '-content');
            const icon = document.getElementById(id + '-icon');
            const header = content?.previousElementSibling;
            
            if (content && icon) {
                if (states[id]) {
                    collapseSection(content, icon, header);
                } else {
                    expandSection(content, icon, header);
                }
            }
        });
    } catch (error) {
        console.warn('Could not load collapsible states:', error);
    }
}

/**
 * Initialize collapsible functionality when the page loads
 */
function initializeCollapsible() {
    // Don't load saved states - always start with default states
    // loadCollapsibleStates(); // Commented out to always start fresh
    
    // Add keyboard navigation support
    document.addEventListener('keydown', function(event) {
        if (event.target.classList.contains('collapsible-header')) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.target.click();
            }
        }
    });
    
    console.log('Collapsible sections initialized (starting fresh)');
}

/**
 * Toggle multiple sections at once based on a pattern
 * @param {string} pattern - Pattern to match section IDs (regex)
 * @param {boolean} forceState - Force expand (true) or collapse (false), or toggle (undefined)
 */
function toggleCollapsibleByPattern(pattern, forceState) {
    const regex = new RegExp(pattern);
    const allSections = document.querySelectorAll('.collapsible-content');
    
    allSections.forEach(content => {
        const id = content.id.replace('-content', '');
        if (regex.test(id)) {
            const icon = document.getElementById(id + '-icon');
            const header = content.previousElementSibling;
            const isCollapsed = content.classList.contains('collapsed');
            
            if (forceState === undefined) {
                // Toggle
                if (isCollapsed) {
                    expandSection(content, icon, header);
                    saveCollapsibleState(id, false);
                } else {
                    collapseSection(content, icon, header);
                    saveCollapsibleState(id, true);
                }
            } else if (forceState && isCollapsed) {
                // Force expand
                expandSection(content, icon, header);
                saveCollapsibleState(id, false);
            } else if (!forceState && !isCollapsed) {
                // Force collapse
                collapseSection(content, icon, header);
                saveCollapsibleState(id, true);
            }
        }
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initializeCollapsible);

// Also initialize if the script is loaded after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCollapsible);
} else {
    initializeCollapsible();
}
