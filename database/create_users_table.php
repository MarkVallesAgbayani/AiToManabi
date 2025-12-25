<?php

// Add login_time column to users table
$pdo->exec("ALTER TABLE users ADD COLUMN login_time DATETIME NULL AFTER last_login_at"); 