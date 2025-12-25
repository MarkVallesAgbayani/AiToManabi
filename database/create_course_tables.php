<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Create courses table
    $sql = "CREATE TABLE IF NOT EXISTS courses (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        category_id INT,
        price DECIMAL(10,2) DEFAULT 0.00,
        image_path VARCHAR(255),
        teacher_id INT NOT NULL,
        status ENUM('draft', 'published') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    echo "Created courses table\n";

    // Create chapters table
    $sql = "CREATE TABLE IF NOT EXISTS chapters (
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
    echo "Created chapters table\n";

    // Create sections table
    $sql = "CREATE TABLE IF NOT EXISTS sections (
        id INT PRIMARY KEY AUTO_INCREMENT,
        chapter_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        content_type ENUM('text', 'video', 'file') DEFAULT 'text',
        order_index INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Created sections table\n";

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

    // Create categories table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Created categories table\n";

    // Insert some default categories if none exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $defaultCategories = [
            'Beginner Japanese',
            'Intermediate Japanese',
            'Advanced Japanese',
            'JLPT N5',
            'JLPT N4',
            'JLPT N3',
            'JLPT N2',
            'JLPT N1',
            'Business Japanese',
            'Conversational Japanese'
        ];
        
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        foreach ($defaultCategories as $category) {
            $stmt->execute([$category]);
        }
        echo "Added default categories\n";
    }

    $pdo->commit();
    echo "Successfully created all course-related tables";
} catch(PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?> 