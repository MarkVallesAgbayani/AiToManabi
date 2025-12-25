-- Drop the existing foreign key constraint if it exists
SET FOREIGN_KEY_CHECKS=0;

-- Drop and recreate the categories table
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some default categories
INSERT INTO `categories` (`name`) VALUES 
('Beginner Japanese'),
('Intermediate Japanese'),
('Advanced Japanese'),
('JLPT N5'),
('JLPT N4'),
('JLPT N3'),
('JLPT N2'),
('JLPT N1');

SET FOREIGN_KEY_CHECKS=1; 