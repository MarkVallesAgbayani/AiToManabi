<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Create enrollments table
    $sql = "CREATE TABLE IF NOT EXISTS enrollments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        course_id INT NOT NULL,
        student_id INT NOT NULL,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id),
        FOREIGN KEY (student_id) REFERENCES users(id),
        UNIQUE KEY unique_enrollment (course_id, student_id)
    )";
    $pdo->exec($sql);
    echo "Successfully created enrollments table\n";

    // Create course_progress table
    $sql = "CREATE TABLE IF NOT EXISTS course_progress (
        id INT PRIMARY KEY AUTO_INCREMENT,
        enrollment_id INT NOT NULL,
        lesson_id INT NOT NULL,
        status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id),
        FOREIGN KEY (lesson_id) REFERENCES lessons(id),
        UNIQUE KEY unique_progress (enrollment_id, lesson_id)
    )";
    $pdo->exec($sql);
    echo "Successfully created course_progress table\n";

    $pdo->commit();
    echo "Database update completed successfully\n";

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?> 