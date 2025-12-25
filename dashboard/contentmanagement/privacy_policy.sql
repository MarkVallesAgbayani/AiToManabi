-- Create privacy_policy table
CREATE TABLE IF NOT EXISTS privacy_policy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
