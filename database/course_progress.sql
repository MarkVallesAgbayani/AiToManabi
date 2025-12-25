-- Create course_progress table
CREATE TABLE IF NOT EXISTS `course_progress` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `course_id` int(11) NOT NULL,
    `section_id` int(11) DEFAULT NULL,
    `chapter_id` int(11) DEFAULT NULL,
    `progress_percentage` decimal(5,2) DEFAULT 0.00,
    `status` ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    `last_accessed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_course_unique` (`user_id`, `course_id`),
    KEY `course_progress_user_id_foreign` (`user_id`),
    KEY `course_progress_course_id_foreign` (`course_id`),
    KEY `course_progress_section_id_foreign` (`section_id`),
    KEY `course_progress_chapter_id_foreign` (`chapter_id`),
    CONSTRAINT `course_progress_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `course_progress_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `course_progress_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
    CONSTRAINT `course_progress_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add trigger to update progress_percentage when status changes
DELIMITER //
CREATE TRIGGER `update_progress_percentage_on_status_change` 
BEFORE UPDATE ON `course_progress`
FOR EACH ROW
BEGIN
    IF NEW.status = 'not_started' THEN
        SET NEW.progress_percentage = 0.00;
    ELSEIF NEW.status = 'completed' THEN
        SET NEW.progress_percentage = 100.00;
    END IF;
END//
DELIMITER ; 