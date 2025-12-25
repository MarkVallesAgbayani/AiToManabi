<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Create video_progress table to track individual video completions
    $sql = "CREATE TABLE IF NOT EXISTS video_progress (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        chapter_id INT NOT NULL,
        section_id INT NOT NULL,
        course_id INT NOT NULL,
        completed TINYINT(1) DEFAULT 0,
        completion_percentage DECIMAL(5,2) DEFAULT 0.00,
        watch_time_seconds INT DEFAULT 0,
        total_duration_seconds INT DEFAULT 0,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (chapter_id) REFERENCES chapters(id),
        FOREIGN KEY (section_id) REFERENCES sections(id),
        FOREIGN KEY (course_id) REFERENCES courses(id),
        UNIQUE KEY unique_video_progress (student_id, chapter_id)
    )";
    
    $pdo->exec($sql);
    echo "Successfully created video_progress table\n";

    // Create text_progress table to track text content completions  
    $sql2 = "CREATE TABLE IF NOT EXISTS text_progress (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        chapter_id INT NOT NULL,
        section_id INT NOT NULL,
        course_id INT NOT NULL,
        completed TINYINT(1) DEFAULT 0,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (chapter_id) REFERENCES chapters(id),
        FOREIGN KEY (section_id) REFERENCES sections(id),
        FOREIGN KEY (course_id) REFERENCES courses(id),
        UNIQUE KEY unique_text_progress (student_id, chapter_id)
    )";
    
    $pdo->exec($sql2);
    echo "Successfully created text_progress table\n";

    $pdo->commit();
    echo "Database tables created successfully for video-based progress tracking!\n";
    
} catch(PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
