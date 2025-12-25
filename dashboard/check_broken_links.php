<?php
require_once('../config/database.php');

function checkBrokenLinks($pdo) {
    try {
        $links = [];
        
        // Get all unique URLs from your database
        // Adjust these table/column names to match YOUR actual database structure
        
        // Example 1: Check if you have a courses table with content URLs
        $tables = ['courses', 'lessons', 'modules', 'content'];
        $urlColumns = ['content_url', 'video_url', 'resource_url', 'link_url', 'image_url'];
        
        foreach ($tables as $table) {
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                // Get columns in this table
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($urlColumns as $urlCol) {
                    if (in_array($urlCol, $columns)) {
                        // Fetch URLs from this column
                        $sql = "SELECT DISTINCT 
                                    $urlCol as url,
                                    COALESCE(title, name, '$table entry') as reference_page,
                                    '$table' as reference_module
                                FROM $table 
                                WHERE $urlCol IS NOT NULL 
                                AND $urlCol != '' 
                                AND $urlCol LIKE 'http%'";
                        
                        $stmt = $pdo->query($sql);
                        $tableLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $links = array_merge($links, $tableLinks);
                    }
                }
            }
        }
        
        // If no links found, create some test broken links
        if (empty($links)) {
            echo "No URLs found in database. Creating test broken links...\n";
            $testLinks = [
                ['https://httpstat.us/404', 'Test Page 404', 'Test Module', 404, 'critical'],
                ['https://httpstat.us/500', 'Test Page 500', 'Test Module', 500, 'critical'],
                ['https://httpstat.us/403', 'Test Page 403', 'Test Module', 403, 'warning'],
            ];
            
            foreach ($testLinks as $link) {
                $stmt = $pdo->prepare("
                    INSERT INTO broken_links 
                    (url, reference_page, reference_module, status_code, severity, first_detected, last_checked)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$link[0], $link[1], $link[2], $link[3], $link[4]]);
            }
            echo "Test broken links created!\n";
            return true;
        }
        
        echo "Found " . count($links) . " URLs to check...\n";
        
        // Check each link
        foreach ($links as $linkData) {
            $url = $linkData['url'];
            $referencePage = $linkData['reference_page'] ?? 'Unknown';
            $referenceModule = $linkData['reference_module'] ?? 'Unknown';
            
            echo "Checking: $url ... ";
            
            // Check if URL is accessible
            $statusCode = checkUrlStatus($url);
            echo "Status: $statusCode\n";
            
            // Determine severity
            $severity = ($statusCode >= 500 || $statusCode == 404) ? 'critical' : 'warning';
            
            // Check if link already exists in broken links
            $stmt = $pdo->prepare("
                SELECT id FROM broken_links 
                WHERE url = ?
            ");
            $stmt->execute([$url]);
            $existing = $stmt->fetch();
            
            if ($statusCode >= 400) {
                // Link is broken
                if ($existing) {
                    // Update existing record
                    $stmt = $pdo->prepare("
                        UPDATE broken_links 
                        SET last_checked = NOW(), 
                            status_code = ?, 
                            severity = ?,
                            reference_page = ?,
                            reference_module = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$statusCode, $severity, $referencePage, $referenceModule, $existing['id']]);
                    echo "  → Updated existing broken link\n";
                } else {
                    // Insert new broken link
                    $stmt = $pdo->prepare("
                        INSERT INTO broken_links 
                        (url, reference_page, reference_module, status_code, severity, first_detected, last_checked)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$url, $referencePage, $referenceModule, $statusCode, $severity]);
                    echo "  → Added new broken link\n";
                }
            } else {
                // Link is working, remove from broken links if it exists
                if ($existing) {
                    $stmt = $pdo->prepare("DELETE FROM broken_links WHERE id = ?");
                    $stmt->execute([$existing['id']]);
                    echo "  → Removed fixed link\n";
                }
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Broken links check error: " . $e->getMessage());
        echo "ERROR: " . $e->getMessage() . "\n";
        return false;
    }
}

function checkUrlStatus($url) {
    // Skip if curl is not available
    if (!function_exists('curl_init')) {
        return 0;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  CURL Error: $error ";
    }
    
    return $statusCode ?: 0;
}

// Run the check
if (php_sapi_name() === 'cli' || (isset($_GET['check']) && $_GET['check'] === 'true')) {
    echo "Starting broken links check...\n";
    checkBrokenLinks($pdo);
    echo "Broken links check completed!\n";
} else {
    echo "Access this page with ?check=true to run the link checker.";
}
?>
