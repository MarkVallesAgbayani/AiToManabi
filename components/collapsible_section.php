<?php
/**
 * Collapsible Section Component
 * A reusable component for creating collapsible sections with smooth animations
 */

/**
 * Generate a collapsible section with header and content
 * 
 * @param string $id Unique identifier for the collapsible section
 * @param string $title The title to display in the header
 * @param string $content The HTML content to show/hide
 * @param bool $defaultCollapsed Whether the section starts collapsed (default: false)
 * @param string $headerClasses Additional CSS classes for the header
 * @param string $contentClasses Additional CSS classes for the content container
 * @param string $iconType Type of icon to use: 'chevron', 'plus', 'arrow' (default: 'chevron')
 * @return string HTML for the collapsible section
 */
function generateCollapsibleSection($id, $title, $content, $defaultCollapsed = false, $headerClasses = '', $contentClasses = '', $iconType = 'chevron') {
    $contentState = $defaultCollapsed ? 'collapsed' : 'expanded';
    $iconState = $defaultCollapsed ? '' : 'rotated';
    
    // Define icons
    $icons = [
        'chevron' => '<svg class="w-5 h-5 collapsible-icon ' . $iconState . '" id="' . $id . '-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                      </svg>',
        'plus' => '<svg class="w-5 h-5 collapsible-icon ' . $iconState . '" id="' . $id . '-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                   </svg>',
        'arrow' => '<svg class="w-5 h-5 collapsible-icon ' . $iconState . '" id="' . $id . '-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>'
    ];
    
    $selectedIcon = $icons[$iconType] ?? $icons['chevron'];
    
    $html = '
    <div class="collapsible-section">
        <button type="button" 
                class="collapsible-header w-full flex items-center justify-between p-3 text-left bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-colors duration-200 ' . $headerClasses . '"
                onclick="toggleCollapsible(\'' . $id . '\')"
                aria-expanded="' . ($defaultCollapsed ? 'false' : 'true') . '"
                aria-controls="' . $id . '-content">
            <h4 class="text-sm font-medium text-gray-900">' . htmlspecialchars($title) . '</h4>
            ' . $selectedIcon . '
        </button>
        <div class="collapsible-content ' . $contentState . ' ' . $contentClasses . '" 
             id="' . $id . '-content"
             aria-labelledby="' . $id . '-header">
            ' . $content . '
        </div>
    </div>';
    
    return $html;
}

/**
 * Get the CSS styles for collapsible sections
 * @return string CSS styles
 */
function getCollapsibleCSS() {
    return '
        .collapsible-section {
            margin-bottom: 1rem;
        }
        
        .collapsible-header {
            cursor: pointer;
            user-select: none;
        }
        
        .collapsible-header:focus {
            outline: 2px solid #3B82F6;
            outline-offset: 2px;
        }
        
        .collapsible-content {
            transition: all 0.3s ease-in-out;
            overflow: hidden;
        }
        
        .collapsible-content.collapsed {
            max-height: 0;
            opacity: 0;
            padding-top: 0;
            padding-bottom: 0;
            margin-top: 0;
            margin-bottom: 0;
        }
        
        .collapsible-content.expanded {
            max-height: 2000px;
            opacity: 1;
        }
        
        .collapsible-icon {
            transition: transform 0.3s ease-in-out;
            flex-shrink: 0;
        }
        
        .collapsible-icon.rotated {
            transform: rotate(180deg);
        }
        
        /* Plus icon specific rotation */
        .collapsible-icon.plus-rotated {
            transform: rotate(45deg);
        }
        
        /* Arrow icon specific rotation */
        .collapsible-icon.arrow-rotated {
            transform: rotate(90deg);
        }
    ';
}

/**
 * Check if a collapsible section should be collapsed based on user preferences or defaults
 * @param string $id The section ID
 * @param bool $defaultState Default state if no preference is stored
 * @return bool Whether the section should be collapsed
 */
function shouldBeCollapsed($id, $defaultState = false) {
    // You can extend this to check user preferences from database/session
    return isset($_SESSION['collapsible_states'][$id]) ? $_SESSION['collapsible_states'][$id] : $defaultState;
}

/**
 * Save collapsible state to session (for persistence across page loads)
 * @param string $id The section ID
 * @param bool $collapsed Whether the section is collapsed
 */
function saveCollapsibleState($id, $collapsed) {
    if (!isset($_SESSION['collapsible_states'])) {
        $_SESSION['collapsible_states'] = [];
    }
    $_SESSION['collapsible_states'][$id] = $collapsed;
}
?>
