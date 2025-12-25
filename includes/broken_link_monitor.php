<?php
// This is an API endpoint to receive broken link reports from JavaScript
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

require_once(__DIR__ . '/../config/database.php');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['url']) || !isset($data['statusCode'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing required fields']));
}

try {
    $url = $data['url'];
    $statusCode = $data['statusCode'];
    $referencePage = $data['page'] ?? $_SERVER['HTTP_REFERER'] ?? 'Unknown';
    $referenceModule = $data['module'] ?? 'Website Pages';
    
    // Only save if it's a broken link
    if ($statusCode >= 400 || $statusCode == 0) {
        $severity = ($statusCode >= 500 || $statusCode == 404) ? 'critical' : 'warning';
        
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM broken_links WHERE url = ?");
        $stmt->execute([$url]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE broken_links 
                SET last_checked = NOW(), status_code = ?, severity = ?
                WHERE id = ?
            ");
            $stmt->execute([$statusCode, $severity, $existing['id']]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO broken_links 
                (url, reference_page, reference_module, status_code, severity, first_detected, last_checked)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$url, $referencePage, $referenceModule, $statusCode, $severity]);
        }
        
        echo json_encode(['success' => true, 'action' => 'saved', 'url' => $url]);
    } else {
        // Link is working, remove from database if exists
        $stmt = $pdo->prepare("DELETE FROM broken_links WHERE url = ?");
        $stmt->execute([$url]);
        
        echo json_encode(['success' => true, 'action' => 'removed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
