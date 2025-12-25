-- Create chapters table
CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content_type ENUM('text', 'video') NOT NULL DEFAULT 'text',
    content TEXT,
    video_url VARCHAR(255),
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create quizzes table
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create quiz questions table
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    text TEXT NOT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create quiz choices table
CREATE TABLE IF NOT EXISTS quiz_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create triggers to maintain order
DELIMITER //

CREATE TRIGGER before_chapter_insert
BEFORE INSERT ON chapters
FOR EACH ROW
BEGIN
    SET NEW.order_index = (
        SELECT COALESCE(MAX(order_index), -1) + 1
        FROM chapters
        WHERE section_id = NEW.section_id
    );
END//

CREATE TRIGGER before_quiz_insert
BEFORE INSERT ON quizzes
FOR EACH ROW
BEGIN
    SET NEW.order_index = (
        SELECT COALESCE(MAX(order_index), -1) + 1
        FROM quizzes
        WHERE section_id = NEW.section_id
    );
END//

CREATE TRIGGER before_question_insert
BEFORE INSERT ON quiz_questions
FOR EACH ROW
BEGIN
    SET NEW.order_index = (
        SELECT COALESCE(MAX(order_index), -1) + 1
        FROM quiz_questions
        WHERE quiz_id = NEW.quiz_id
    );
END//

CREATE TRIGGER before_choice_insert
BEFORE INSERT ON quiz_choices
FOR EACH ROW
BEGIN
    SET NEW.order_index = (
        SELECT COALESCE(MAX(order_index), -1) + 1
        FROM quiz_choices
        WHERE question_id = NEW.question_id
    );
END//

DELIMITER ; 