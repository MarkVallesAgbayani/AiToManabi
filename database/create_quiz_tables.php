<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Create quizzes table
    $sql = "CREATE TABLE IF NOT EXISTS quizzes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        order_index INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Created quizzes table\n";

    // Create quiz questions table
    $sql = "CREATE TABLE IF NOT EXISTS quiz_questions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quiz_id INT NOT NULL,
        text TEXT NOT NULL,
        order_index INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Created quiz_questions table\n";

    // Create quiz choices table
    $sql = "CREATE TABLE IF NOT EXISTS quiz_choices (
        id INT PRIMARY KEY AUTO_INCREMENT,
        question_id INT NOT NULL,
        text TEXT NOT NULL,
        is_correct BOOLEAN NOT NULL DEFAULT 0,
        order_index INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Created quiz_choices table\n";

    // Create quiz_attempts table
    $sql = "CREATE TABLE IF NOT EXISTS quiz_attempts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        quiz_id INT NOT NULL,
        student_id INT NOT NULL,
        score DECIMAL(5,2) NOT NULL DEFAULT 0,
        total_points INT DEFAULT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Created quiz_attempts table\n";

    // Create quiz_answers table
    $sql = "CREATE TABLE IF NOT EXISTS quiz_answers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        answer_text TEXT,
        is_correct BOOLEAN NOT NULL DEFAULT 0,
        points_earned DECIMAL(5,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Created quiz_answers table\n";

    echo "Database update completed successfully\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 