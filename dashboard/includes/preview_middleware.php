<?php
/**
 * Preview Mode Middleware
 * 
 * This file should be included at the top of any course viewing files
 * to add preview mode support and prevent database writes.
 */

// Include preview helpers
require_once 'preview_helpers.php';

// Check if this is a preview mode request
if (isPreviewMode()) {
    // Log the preview mode access
    logPreviewAction('preview_access', [
        'course_id' => $_GET['id'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'access_mode' => getPreviewAccessMode()
    ]);
    
    // Override any potential database write operations
    // This is a safety net - individual functions should also check allowDatabaseWrite()
    
    // Add a warning banner if not already present
    if (!isset($preview_banner_added)) {
        echo getPreviewBanner($_GET['id'] ?? null);
        $preview_banner_added = true;
    }
    
    // Add preview mode styles
    if (!isset($preview_styles_added)) {
        echo '<style>' . getPreviewModeStyles() . '</style>';
        $preview_styles_added = true;
    }
}

/**
 * Safe database write function for preview mode
 * Use this instead of direct PDO operations when in preview mode
 */
function safeDbWrite($pdo, $sql, $params = [], $operation = '') {
    if (!allowDatabaseWrite($operation)) {
        logPreviewAction('blocked_db_write', [
            'operation' => $operation,
            'sql' => $sql
        ]);
        return false;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Database error in preview mode: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe database read function for preview mode
 * This allows reads but logs them for debugging
 */
function safeDbRead($pdo, $sql, $params = []) {
    if (isPreviewMode()) {
        logPreviewAction('db_read', [
            'sql' => $sql,
            'params' => $params
        ]);
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Database read error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get preview mode JavaScript safeguards
 */
function getPreviewModeJS() {
    if (!isPreviewMode()) {
        return '';
    }
    
    return '
    <script>
        // Preview Mode JavaScript Safeguards
        (function() {
            console.log("Preview Mode: JavaScript safeguards loaded");
            
            // Override form submissions
            document.addEventListener("submit", function(e) {
                e.preventDefault();
                alert("Preview Mode: Form submissions are disabled. No data will be saved.");
                return false;
            });
            
            // Override fetch requests
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                console.warn("Preview Mode: AJAX request blocked:", args[0]);
                return Promise.reject(new Error("Preview Mode: Data saving is disabled"));
            };
            
            // Override XMLHttpRequest
            const originalXHR = window.XMLHttpRequest;
            window.XMLHttpRequest = function() {
                const xhr = new originalXHR();
                const originalOpen = xhr.open;
                xhr.open = function(method, url, ...args) {
                    console.warn("Preview Mode: XHR request blocked:", method, url);
                    throw new Error("Preview Mode: Data saving is disabled");
                };
                return xhr;
            };
            
            // Add preview mode indicator to console
            console.log("%cPreview Mode Active", "color: #e11d48; font-weight: bold; font-size: 16px;");
            console.log("All data saving operations are disabled in preview mode.");
        })();
    </script>';
}
?>
