<?php
require_once '../config/database.php';

try {
    // Add new columns for soft delete functionality
    $pdo->exec("
        ALTER TABLE course_category
        ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
        ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS restored_at TIMESTAMP NULL DEFAULT NULL
    ");

    // Update existing records to have 'active' status
    $pdo->exec("UPDATE course_category SET status = 'active' WHERE status IS NULL");

    echo "Database updated successfully!\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
} 