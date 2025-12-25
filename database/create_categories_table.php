<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Check if categories table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        $pdo->beginTransaction();

        // Create categories table
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )";
        $pdo->exec($sql);
        echo "Created categories table\n";

        // Check if category column exists in courses table
        $stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'category'");
        if ($stmt->rowCount() > 0) {
            // Add category_id column and drop old category column
            $sql = "ALTER TABLE courses 
                    ADD COLUMN category_id INT,
                    ADD FOREIGN KEY (category_id) REFERENCES categories(id),
                    DROP COLUMN category";
            $pdo->exec($sql);
            echo "Updated courses table structure\n";
        }

        $pdo->commit();
        echo "Successfully completed database updates";
    } else {
        echo "Categories table already exists. No changes needed.";
    }
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?> 