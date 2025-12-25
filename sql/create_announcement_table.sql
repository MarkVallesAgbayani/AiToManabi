DROP TABLE IF EXISTS announcement_banner;
CREATE TABLE announcement_banner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    background_color VARCHAR(20) DEFAULT '#FFFFFF',
    text_color VARCHAR(20) DEFAULT '#1A1A1A',
    button_text VARCHAR(100),
    button_url VARCHAR(255),
    button_color VARCHAR(20),
    button_icon VARCHAR(50),
    discount_value VARCHAR(20),
    discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    start_date DATETIME,
    end_date DATETIME,
    is_published BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
); 