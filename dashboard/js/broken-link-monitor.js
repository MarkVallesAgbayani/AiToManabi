// Real-time Broken Link Monitor - WORKS ON LOCALHOST & HOSTINGER
(function() {
    'use strict';
    
    // Auto-detect base path for both localhost and Hostinger
    function getBasePath() {
        const path = window.location.pathname;
        // If path contains /dashboard/, extract everything before it
        if (path.includes('/dashboard/')) {
            return path.substring(0, path.indexOf('/dashboard/') + 1);
        }
        // For root-level pages, just return /
        return '/';
    }
    
    const MONITOR_API = window.location.origin + getBasePath() + 'includes/broken_link_monitor.php';
    const checkedUrls = new Set();
    
    // Debug: Show API endpoint
    console.log('📡 API Endpoint:', MONITOR_API);
    
    function getPageName() {
        return document.title.split(' - ')[0] || 'Website Page';
    }
    
    function reportBrokenLink(url, statusCode) {
        if (checkedUrls.has(url)) return;
        checkedUrls.add(url);
        
        console.log('🔴 Broken link detected:', url, 'Status:', statusCode);
        
        fetch(MONITOR_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                url: url,
                statusCode: statusCode,
                page: window.location.pathname,
                module: getPageName()
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('API returned ' + response.status);
            }
            return response.json();
        })
        .then(data => console.log('✅ Saved to database:', data))
        .catch(err => console.error('❌ Report error:', err));
    }
    
    // ACTIVELY CHECK CSS BY FETCHING
    function checkStylesheetByFetch(href) {
        fetch(href, { 
            method: 'HEAD',
            cache: 'no-cache'
        })
        .then(response => {
            if (!response.ok) {
                console.log('💥 CSS FAILED (fetch):', href, 'Status:', response.status);
                reportBrokenLink(href, response.status);
            } else {
                console.log('✅ CSS OK:', href);
            }
        })
        .catch(error => {
            console.log('💥 CSS FETCH ERROR:', href, error.message);
            reportBrokenLink(href, 404);
        });
    }
    
    function checkStylesheets() {
        const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
        console.log('🎨 Checking', stylesheets.length, 'CSS files...');
        
        stylesheets.forEach((link, index) => {
            const href = link.href;
            if (!href) return;
            
            console.log(`CSS ${index + 1}:`, href);
            
            // Method 1: Try error event (unreliable)
            link.addEventListener('error', function() {
                console.log('💥 CSS ERROR EVENT:', href);
                reportBrokenLink(href, 404);
            }, { once: true });
            
            // Method 2: Actively fetch to check status (reliable!)
            setTimeout(() => {
                checkStylesheetByFetch(href);
            }, 1000);
        });
    }
    
    function monitorImages() {
        console.log('🖼️ Monitoring images...');
        document.querySelectorAll('img').forEach(img => {
            if (img.src && img.src.startsWith('http')) {
                if (img.complete && img.naturalHeight === 0) {
                    console.log('💥 Image failed:', img.src);
                    reportBrokenLink(img.src, 404);
                }
                img.addEventListener('error', () => {
                    console.log('💥 Image error:', img.src);
                    reportBrokenLink(img.src, 404);
                }, { once: true });
            }
        });
    }

function checkScriptByFetch(src) {
    // Skip checking popular CDNs (they work but block CORS)
    const trustedCDNs = [
        'cdn.tailwindcss.com',
        'cdn.jsdelivr.net',
        'unpkg.com',
        'cdnjs.cloudflare.com',
        'code.jquery.com',
        'fonts.googleapis.com',
        'fonts.gstatic.com'
    ];
    
    // Check if URL is from a trusted CDN
    if (trustedCDNs.some(cdn => src.includes(cdn))) {
        console.log('⏭️ Skipping trusted CDN:', src);
        return; // Don't check, assume it's working
    }
    
    // Only check non-CDN external resources
    fetch(src, { 
        method: 'HEAD',
        cache: 'no-cache'
    })
    .then(response => {
        if (!response.ok) {
            console.log('💥 JS FAILED:', src, 'Status:', response.status);
            reportBrokenLink(src, response.status);
        } else {
            console.log('✅ JS OK:', src);
        }
    })
    .catch(error => {
        console.log('💥 JS FETCH ERROR:', src);
        reportBrokenLink(src, 404);
    });
}

    
function monitorScripts() {
    console.log('📜 Monitoring scripts...');
    document.querySelectorAll('script[src]').forEach((script, index) => {
        if (script.src && script.src.startsWith('http')) {
            console.log(`Script ${index + 1}:`, script.src);
            
            // Error event listener
            script.addEventListener('error', () => {
                console.log('💥 Script error:', script.src);
                reportBrokenLink(script.src, 404);
            }, { once: true });
            
            // Also actively check
            setTimeout(() => {
                checkScriptByFetch(script.src);
            }, 1500);
        }
    });
}
    
    function init() {
        console.log('🚀 Broken Link Monitor Started');
        checkStylesheets();
        monitorImages();
        monitorScripts();
        console.log('✅ All monitors active!');
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
