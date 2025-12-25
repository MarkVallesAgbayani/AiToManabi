-- Add status column to courses table if not exists
ALTER TABLE courses
ADD COLUMN IF NOT EXISTS status ENUM('draft', 'published') DEFAULT 'draft',
ADD COLUMN IF NOT EXISTS category_id INT,
ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Drop existing foreign key constraints
ALTER TABLE sections 
DROP FOREIGN KEY IF EXISTS sections_ibfk_1;

-- Modify sections table to reference courses instead of chapters
ALTER TABLE sections
DROP COLUMN IF EXISTS chapter_id,
ADD COLUMN IF NOT EXISTS course_id INT NOT NULL AFTER id,
ADD COLUMN IF NOT EXISTS description TEXT AFTER title,
ADD COLUMN IF NOT EXISTS order_index INT NOT NULL DEFAULT 0,
ADD FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE;

-- Update chapters table structure
ALTER TABLE chapters
DROP FOREIGN KEY IF EXISTS chapters_ibfk_1,
DROP FOREIGN KEY IF EXISTS chapters_ibfk_2,
MODIFY COLUMN order_index INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS content TEXT AFTER description,
ADD COLUMN IF NOT EXISTS course_id INT NOT NULL AFTER id,
ADD COLUMN IF NOT EXISTS section_id INT NOT NULL AFTER course_id,
ADD FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
ADD FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE;

-- Create quizzes table if not exists
CREATE TABLE IF NOT EXISTS quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
);

-- Create quiz_questions table if not exists
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    text TEXT NOT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Create quiz_choices table if not exists
CREATE TABLE IF NOT EXISTS quiz_choices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
); 