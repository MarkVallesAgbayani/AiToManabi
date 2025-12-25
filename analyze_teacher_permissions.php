<?php
require_once 'config/database.php';

echo "=== ANALYZING TEACHER PERMISSIONS ===\n\n";

// 1. Find all teacher-related permissions in the system
echo "1. All teacher-related permissions in the system:\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT id, name, description, category 
    FROM permissions 
    WHERE name LIKE '%teacher%' 
       OR category LIKE '%teacher%'
       OR description LIKE '%teacher%'
    ORDER BY name
");
$stmt->execute();
$all_teacher_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($all_teacher_permissions) . " teacher-related permissions:\n";
foreach ($all_teacher_permissions as $perm) {
    echo "- ID: {$perm['id']}, Name: {$perm['name']}, Category: {$perm['category']}\n";
    echo "  Description: {$perm['description']}\n\n";
}

// 2. Check what permissions are currently in Default Teacher template
echo "2. Permissions currently in Default Teacher template:\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.description 
    FROM role_template_permissions rtp 
    JOIN permissions p ON rtp.permission_id = p.id 
    WHERE rtp.template_id = 5
    ORDER BY p.name
");
$stmt->execute();
$current_teacher_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Default Teacher template currently has " . count($current_teacher_permissions) . " permissions:\n";
$current_permission_ids = [];
foreach ($current_teacher_permissions as $perm) {
    echo "- {$perm['name']}\n";
    $current_permission_ids[] = $perm['id'];
}

// 3. Find missing teacher permissions
echo "\n3. Missing teacher permissions from Default Teacher template:\n";
echo str_repeat("-", 60) . "\n";

$missing_permissions = [];
foreach ($all_teacher_permissions as $perm) {
    if (!in_array($perm['id'], $current_permission_ids)) {
        $missing_permissions[] = $perm;
        echo "❌ MISSING: {$perm['name']} (ID: {$perm['id']})\n";
        echo "   Description: {$perm['description']}\n\n";
    }
}

if (empty($missing_permissions)) {
    echo "✅ All teacher permissions are already in Default Teacher template!\n";
} else {
    echo "\n4. SQL QUERY to add missing teacher permissions:\n";
    echo str_repeat("=", 60) . "\n";
    
    echo "-- Add missing teacher permissions to Default Teacher template (ID: 5)\n";
    foreach ($missing_permissions as $perm) {
        echo "INSERT IGNORE INTO role_template_permissions (template_id, permission_id) VALUES (5, {$perm['id']}); -- {$perm['name']}\n";
    }
    
    echo "\n-- Verify the additions\n";
    echo "SELECT COUNT(*) as total_permissions FROM role_template_permissions WHERE template_id = 5;\n";
    echo "SELECT p.name FROM role_template_permissions rtp JOIN permissions p ON rtp.permission_id = p.id WHERE rtp.template_id = 5 ORDER BY p.name;\n";
}

// 5. Also check for navigation permissions that teachers might need
echo "\n5. Navigation permissions analysis:\n";
echo str_repeat("-", 60) . "\n";

$stmt = $pdo->prepare("
    SELECT id, name, description 
    FROM permissions 
    WHERE name LIKE 'nav_%' 
    ORDER BY name
");
$stmt->execute();
$nav_permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "All navigation permissions:\n";
foreach ($nav_permissions as $perm) {
    $in_template = in_array($perm['id'], $current_permission_ids) ? "✅ IN TEMPLATE" : "❌ NOT IN TEMPLATE";
    $is_teacher_nav = (strpos($perm['name'], 'teacher') !== false) ? " [TEACHER NAV]" : "";
    echo "- {$perm['name']}{$is_teacher_nav} - {$in_template}\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";
?>