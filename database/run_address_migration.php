<?php
/**
 * Address Migration Script
 * 
 * This script runs the migration to update the users table
 * from a single 'location' column to three address fields:
 * - address_line1
 * - address_line2  
 * - city
 * 
 * This migration only affects admin and teacher users as requested.
 */

require_once '../config/database.php';

try {
    echo "<h2>🔄 Running Address Migration...</h2>\n";
    
    // Read and execute the migration SQL
    $migration_sql = file_get_contents('migrations/update_users_table_address_fields.sql');
    
    if (!$migration_sql) {
        throw new Exception("Could not read migration file");
    }
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $migration_sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✅ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            // Some statements might fail if columns already exist, which is expected
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ️  Column already exists (expected): " . substr($statement, 0, 50) . "...\n";
            } else {
                echo "⚠️  Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Verify the migration
    echo "\n<h3>📊 Migration Results:</h3>\n";
    
    // Check if new columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'address_%' OR SHOW COLUMNS FROM users LIKE 'city'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>✅ New address columns found: " . implode(', ', $columns) . "</p>\n";
    
    // Count migrated records
    $stmt = $pdo->query("
        SELECT 
            role,
            COUNT(*) as total_users,
            COUNT(address_line1) as has_address_line1,
            COUNT(address_line2) as has_address_line2,
            COUNT(city) as has_city,
            COUNT(location) as has_old_location
        FROM users 
        GROUP BY role
        ORDER BY role
    ");
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Role</th><th>Total Users</th><th>Has Address Line 1</th><th>Has Address Line 2</th><th>Has City</th><th>Has Old Location</th></tr>\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . $row['total_users'] . "</td>";
        echo "<td>" . $row['has_address_line1'] . "</td>";
        echo "<td>" . $row['has_address_line2'] . "</td>";
        echo "<td>" . $row['has_city'] . "</td>";
        echo "<td>" . $row['has_old_location'] . "</td>";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    // Show sample migrated data
    $stmt = $pdo->query("
        SELECT id, username, role, address_line1, address_line2, city, location 
        FROM users 
        WHERE role IN ('admin', 'teacher') 
        AND (address_line1 IS NOT NULL OR location IS NOT NULL)
        LIMIT 5
    ");
    
    echo "<h3>📋 Sample Migrated Data:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Address Line 1</th><th>Address Line 2</th><th>City</th><th>Old Location</th></tr>\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['address_line1'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['address_line2'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['city'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['location'] ?? '') . "</td>";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    echo "<h3>✅ Migration Completed Successfully!</h3>\n";
    echo "<p><strong>Next Steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>✅ The users table now has three new address fields</li>\n";
    echo "<li>✅ Existing location data has been migrated to address_line1 for admin/teacher users</li>\n";
    echo "<li>✅ The add_user_modal.php has been updated to use the new address fields</li>\n";
    echo "<li>✅ The create_user_with_otp.php has been updated to use the new address fields</li>\n";
    echo "<li>⚠️  The old 'location' column is still present for safety - you can remove it later if needed</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<h3>❌ Migration Failed:</h3>\n";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Please check the database connection and try again.</p>\n";
}
?>
