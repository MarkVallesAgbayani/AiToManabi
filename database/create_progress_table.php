<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Create progress table
    $sql = "CREATE TABLE IF NOT EXISTS progress (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        section_id INT NOT NULL,
        completed TINYINT(1) DEFAULT 0,
        completion_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (section_id) REFERENCES sections(id),
        UNIQUE KEY unique_progress (user_id, section_id)
    )";
    
    $pdo->exec($sql);
    echo "Successfully created progress table";

    $pdo->commit();
} catch(PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?> 