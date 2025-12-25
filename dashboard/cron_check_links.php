<?php
// This file is specifically for cron job execution
// It runs without HTML output

require_once('../config/database.php');

// Log file for cron execution
$logFile = __DIR__ . '/cron_log.txt';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function checkBrokenLinks($pdo) {
    try {
        $links = [];
        
        // Scan files for external links
        $filesToScan = [
            __DIR__ . '/admin.php',
            __DIR__ . '/login-activity.php',
            __DIR__ . '/users.php',
        ];
        
        foreach ($filesToScan as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                
                // Find all external URLs
                preg_match_all('/<(?:link|script|img|a)[^>]+(?:href|src)=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
                
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $url) {
                        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
                            $links[] = [
                                'url' => $url,
                                'page' => basename($file),
                                'module' => 'Website Pages'
                            ];
                        }
                    }
                }
            }
        }
        
        // Remove duplicates
        $uniqueLinks = array_unique(array_column($links, 'url'));
        $links = array_filter($links, function($link) use (&$uniqueLinks) {
            if (in_array($link['url'], $uniqueLinks)) {
                $uniqueLinks = array_diff($uniqueLinks, [$link['url']]);
                return true;
            }
            return false;
        });
        
        logMessage("Found " . count($links) . " unique URLs to check");
        
        $checkedCount = 0;
        $brokenCount = 0;
        
        // Check each link
        foreach ($links as $linkData) {
            $url = $linkData['url'];
            $page = $linkData['page'];
            $module = $linkData['module'];
            
            // Check URL status
            $statusCode = checkUrlStatus($url);
            $checkedCount++;
            
            logMessage("[$checkedCount] $url - Status: $statusCode");
            
            // Determine if broken
            if ($statusCode >= 400 || $statusCode == 0) {
                $severity = ($statusCode >= 500 || $statusCode == 404) ? 'critical' : 'warning';
                $brokenCount++;
                
                // Check if exists
                $stmt = $pdo->prepare("SELECT id FROM broken_links WHERE url = ?");
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
                    logMessage("  → Updated existing broken link");
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO broken_links 
                        (url, reference_page, reference_module, status_code, severity, first_detected, last_checked)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$url, $page, $module, $statusCode, $severity]);
                    logMessage("  → Added new broken link");
                }
            } else {
                // Remove if working
                $stmt = $pdo->prepare("DELETE FROM broken_links WHERE url = ?");
                $stmt->execute([$url]);
            }
        }
        
        logMessage("Scan complete: $checkedCount checked, $brokenCount broken");
        return true;
        
    } catch (Exception $e) {
        logMessage("ERROR: " . $e->getMessage());
        return false;
    }
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
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $statusCode ?: 0;
}

// Run the check
logMessage("=== Cron job started ===");
checkBrokenLinks($pdo);
logMessage("=== Cron job finished ===\n");

echo "Cron job executed successfully at " . date('Y-m-d H:i:s');
?>
