<?php
require_once 'config/database.php';

echo "=== MANUAL PERMISSION INSERTION TEST ===\n\n";

try {
    // Test inserting just one permission first
    $test_sql = "INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (5, 371)";
    
    echo "Testing SQL: $test_sql\n";
    
    $stmt = $pdo->prepare($test_sql);
    $result = $stmt->execute();
    
    echo "Execution result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Affected rows: " . $stmt->rowCount() . "\n\n";
    
    // Check if permission ID 371 exists
    $stmt = $pdo->prepare('SELECT * FROM permissions WHERE id = 371');
    $stmt->execute();
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($permission) {
        echo "✅ Permission ID 371 exists: " . $permission['name'] . "\n";
    } else {
        echo "❌ Permission ID 371 does not exist!\n";
    }
    
    // Check if template ID 5 exists
    $stmt = $pdo->prepare('SELECT * FROM role_templates WHERE id = 5');
    $stmt->execute();
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        echo "✅ Template ID 5 exists: " . $template['name'] . "\n";
    } else {
        echo "❌ Template ID 5 does not exist!\n";
    }
    
    // Check if this combination already exists
    $stmt = $pdo->prepare('SELECT * FROM role_template_permissions WHERE template_id = 5 AND permission_id = 371');
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "⚠️  Permission already exists in template\n";
    } else {
        echo "✅ Permission does not exist in template yet\n";
    }
    
    // Let's also check what permissions are currently in template 5
    echo "\n=== CURRENT PERMISSIONS IN TEMPLATE 5 ===\n";
    $stmt = $pdo->prepare('
        SELECT p.id, p.name 
        FROM role_template_permissions rtp 
        JOIN permissions p ON rtp.permission_id = p.id 
        WHERE rtp.template_id = 5 
        ORDER BY p.name
    ');
    $stmt->execute();
    $current_perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Currently has " . count($current_perms) . " permissions:\n";
    foreach ($current_perms as $perm) {
        echo "  - {$perm['id']}: {$perm['name']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>