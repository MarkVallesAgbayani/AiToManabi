-- Add password reset notification field to users table
-- This field tracks whether user has been notified about password reset

ALTER TABLE `users` 
ADD COLUMN `password_reset_notification_shown` TINYINT(1) DEFAULT 0 
COMMENT 'Tracks if user has been shown password reset notification modal';

-- Add index for performance
ALTER TABLE `users` 
ADD INDEX `idx_password_reset_notification` (`password_reset_notification_shown`);


CREATE TABLE `password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;