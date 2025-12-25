<?php
require_once 'config/database.php';

echo "=== ADDING ALL TEACHER PERMISSIONS TO DEFAULT TEACHER TEMPLATE ===\n\n";

try {
    $pdo->beginTransaction();
    
    // Read and execute the SQL file
    $sql = file_get_contents('complete_teacher_permissions.sql');
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $added_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, 'SELECT') === 0) {
            continue; // Skip comments and SELECT statements
        }
        
        if (strpos($statement, 'INSERT IGNORE') === 0) {
            $stmt = $pdo->prepare($statement);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $added_count++;
            }
        }
    }
    
    $pdo->commit();
    
    echo "✅ Added $added_count new permissions to Default Teacher template\n\n";
    
    // Verify the results
    echo "=== VERIFICATION ===\n";
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM role_template_permissions WHERE template_id = 5');
    $stmt->execute();
    $total_permissions = $stmt->fetchColumn();
    
    echo "Default Teacher template now has: $total_permissions total permissions\n\n";
    
    // Check if any teacher permissions are still missing
    $stmt = $pdo->prepare("
        SELECT p.name 
        FROM permissions p 
        LEFT JOIN role_template_permissions rtp ON p.id = rtp.permission_id AND rtp.template_id = 5
        WHERE (p.name LIKE '%teacher%' OR p.category LIKE '%teacher%' OR p.description LIKE '%teacher%')
          AND rtp.permission_id IS NULL
        ORDER BY p.name
    ");
    $stmt->execute();
    $missing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($missing)) {
        echo "🎉 ALL TEACHER PERMISSIONS ARE NOW IN DEFAULT TEACHER TEMPLATE!\n";
    } else {
        echo "⚠️  Still missing " . count($missing) . " teacher permissions:\n";
        foreach ($missing as $perm) {
            echo "  - $perm\n";
        }
    }
    
    // Update existing teacher users with new permissions
    echo "\n=== UPDATING EXISTING TEACHER USERS ===\n";
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        WHERE ur.template_id = 5 AND u.role = 'teacher'
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($teachers as $teacher) {
        echo "Updating permissions for {$teacher['username']} (ID: {$teacher['id']})...\n";
        
        // Clear existing permissions
        $stmt = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$teacher['id']]);
        
        // Add all current template permissions
        $stmt = $pdo->prepare('
            INSERT INTO user_permissions (user_id, permission_name, granted_by)
            SELECT ?, p.name, 1
            FROM role_template_permissions rtp
            JOIN permissions p ON rtp.permission_id = p.id
            WHERE rtp.template_id = 5
        ');
        $stmt->execute([$teacher['id']]);
        
        $updated_count = $stmt->rowCount();
        echo "  ✅ Updated with $updated_count permissions\n";
    }
    
    echo "\n🎉 ALL DONE! Default Teacher template now has complete permissions.\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>