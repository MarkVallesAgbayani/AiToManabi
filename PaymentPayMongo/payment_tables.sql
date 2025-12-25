-- Create payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status VARCHAR(50) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    paymongo_id VARCHAR(255) NULL,
    invoice_number VARCHAR(50) NOT NULL,
    payment_type VARCHAR(20) NOT NULL DEFAULT 'PAID', -- 'PAID' or 'FREE'
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create payment_details table for additional information
CREATE TABLE IF NOT EXISTS payment_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    course_id INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Payment sessions table to track checkout sessions
CREATE TABLE IF NOT EXISTS payment_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    checkout_session_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

-- Enrollments table to track course enrollments
CREATE TABLE IF NOT EXISTS enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY unique_enrollment (user_id, course_id)
);

-- Temporary checkout mapping table
CREATE TABLE IF NOT EXISTS temp_checkout_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temp_id VARCHAR(255) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    checkout_session_id VARCHAR(255) NULL,
    invoice_number VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
); 