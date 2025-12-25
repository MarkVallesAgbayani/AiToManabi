-- Update course structure for Japanese LMS
SET FOREIGN_KEY_CHECKS=0;

-- Drop redundant tables if they exist
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS course_progress;

-- Update courses table
ALTER TABLE courses
MODIFY COLUMN status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
ADD COLUMN is_published BOOLEAN DEFAULT FALSE,
ADD COLUMN published_at TIMESTAMP NULL;

-- Update chapters table structure
DROP TABLE IF EXISTS chapters;
CREATE TABLE chapters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update sections table structure
DROP TABLE IF EXISTS sections;
CREATE TABLE sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chapter_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT,
    content_type ENUM('text', 'video', 'file', 'quiz') NOT NULL DEFAULT 'text',
    video_url VARCHAR(255),
    file_path VARCHAR(255),
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update quizzes table structure
DROP TABLE IF EXISTS quiz_choices;
DROP TABLE IF EXISTS quiz_questions;
DROP TABLE IF EXISTS quizzes;

CREATE TABLE quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    passing_score INT NOT NULL DEFAULT 70,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false') NOT NULL DEFAULT 'multiple_choice',
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_choices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX idx_chapters_course ON chapters(course_id);
CREATE INDEX idx_sections_chapter ON sections(chapter_id);
CREATE INDEX idx_quizzes_section ON quizzes(section_id);
CREATE INDEX idx_questions_quiz ON quiz_questions(quiz_id);
CREATE INDEX idx_choices_question ON quiz_choices(question_id);

SET FOREIGN_KEY_CHECKS=1;

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS before_chapter_delete;
DROP TRIGGER IF EXISTS before_section_delete;
DROP TRIGGER IF EXISTS before_quiz_delete;

-- Create triggers for maintaining order
DELIMITER //

CREATE TRIGGER before_chapter_delete 
BEFORE DELETE ON chapters
FOR EACH ROW
BEGIN
    UPDATE chapters 
    SET order_index = order_index - 1
    WHERE course_id = OLD.course_id 
    AND order_index > OLD.order_index;
END //

CREATE TRIGGER before_section_delete
BEFORE DELETE ON sections
FOR EACH ROW
BEGIN
    UPDATE sections
    SET order_index = order_index - 1
    WHERE chapter_id = OLD.chapter_id
    AND order_index > OLD.order_index;
END //

CREATE TRIGGER before_quiz_delete
BEFORE DELETE ON quizzes
FOR EACH ROW
BEGIN
    UPDATE quizzes
    SET order_index = order_index - 1
    WHERE section_id = OLD.section_id
    AND order_index > OLD.order_index;
END //

DELIMITER ; 