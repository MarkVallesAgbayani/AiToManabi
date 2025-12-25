<?php
require_once('../config/database.php');

// Pages to scan for broken links
$pagesToScan = [
    'dashboard/admin.php',
    'users.php',
    'login-activity.php',
    'usage-analytics.php',
    'user-role-report.php',
    '../index.php',
    '../courses.php',
    '../login.php',
];

function scanPageForLinks($filepath) {
    $links = [];
    
    if (!file_exists($filepath)) {
        return $links;
    }
    
    $content = file_get_contents($filepath);
    
    // Find all links in href, src, url() attributes
    $patterns = [
        '/<link[^>]+href=["\']([^"\']+)["\']/i',  // CSS links
        '/<script[^>]+src=["\']([^"\']+)["\']/i', // JS files
        '/<img[^>]+src=["\']([^"\']+)["\']/i',    // Images
        '/<a[^>]+href=["\']([^"\']+)["\']/i',     // Links
        '/url\(["\']?([^"\']+)["\']?\)/i',        // CSS url()
    ];
    
    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                // Only check external URLs (http/https)
                if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
                    $links[] = [
                        'url' => $url,
                        'page' => basename($filepath),
                        'type' => 'External Resource'
                    ];
                }
            }
        }
    }
    
    return $links;
}

function checkUrlStatus($url) {
    if (!function_exists('curl_init')) {
        return 0;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $statusCode ?: 0;
}

echo "<h1>Website Link Scanner</h1>";
echo "<p>Scanning your website for broken links...</p><hr>";

$allLinks = [];
$baseDir = dirname(__DIR__) . '/';

// Scan each page
foreach ($pagesToScan as $page) {
    $fullPath = $baseDir . $page;
    echo "<h3>Scanning: $page</h3>";
    
    $pageLinks = scanPageForLinks($fullPath);
    
    if (empty($pageLinks)) {
        echo "<p style='color: gray;'>No external links found</p>";
    } else {
        echo "<p>Found " . count($pageLinks) . " external links</p>";
        $allLinks = array_merge($allLinks, $pageLinks);
    }
}

// Remove duplicates
$uniqueLinks = [];
foreach ($allLinks as $link) {
    $key = $link['url'];
    if (!isset($uniqueLinks[$key])) {
        $uniqueLinks[$key] = $link;
    }
}

echo "<hr><h2>Checking Links Status...</h2>";
echo "<p>Total unique links to check: " . count($uniqueLinks) . "</p>";

$brokenCount = 0;
$workingCount = 0;

foreach ($uniqueLinks as $linkData) {
    $url = $linkData['url'];
    $page = $linkData['page'];
    
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>";
    echo "<strong>URL:</strong> " . htmlspecialchars($url) . "<br>";
    echo "<strong>Found in:</strong> " . htmlspecialchars($page) . "<br>";
    echo "<strong>Status:</strong> ";
    
    $statusCode = checkUrlStatus($url);
    
    if ($statusCode >= 400 || $statusCode == 0) {
        // Broken link
        $severity = ($statusCode >= 500 || $statusCode == 404) ? 'critical' : 'warning';
        echo "<span style='color: red; font-weight: bold;'>BROKEN ($statusCode)</span><br>";
        $brokenCount++;
        
        // Save to database
        $stmt = $pdo->prepare("
            SELECT id FROM broken_links WHERE url = ?
        ");
        $stmt->execute([$url]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE broken_links 
                SET last_checked = NOW(), status_code = ?, severity = ?, reference_page = ?
                WHERE id = ?
            ");
            $stmt->execute([$statusCode, $severity, $page, $existing['id']]);
            echo "<em>Updated in database</em>";
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO broken_links 
                (url, reference_page, reference_module, status_code, severity, first_detected, last_checked)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$url, $page, 'Website Pages', $statusCode, $severity]);
            echo "<em>Added to database</em>";
        }
    } else {
        echo "<span style='color: green; font-weight: bold;'>WORKING ($statusCode)</span>";
        $workingCount++;
        
        // Remove from broken_links if it was there
        $stmt = $pdo->prepare("DELETE FROM broken_links WHERE url = ?");
        $stmt->execute([$url]);
    }
    
    echo "</div>";
}

echo "<hr><h2>Summary</h2>";
echo "<p><strong>Total Links Scanned:</strong> " . count($uniqueLinks) . "</p>";
echo "<p style='color: green;'><strong>Working Links:</strong> $workingCount</p>";
echo "<p style='color: red;'><strong>Broken Links:</strong> $brokenCount</p>";
echo "<p><a href='login-activity.php'>View Broken Links Report</a></p>";
?>
