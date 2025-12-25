<?php
session_start();
require_once '../config/database.php';
require_once '../includes/email_notifications.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // Generate a random password
        $temp_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();
        
        // Insert new user
        // Set is_first_login = TRUE for admin and teacher roles
        $is_first_login = in_array($role === 'hybrid' ? 'teacher' : $role, ['admin', 'teacher']) ? TRUE : FALSE;
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status, is_first_login)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([$username, $email, $hashed_password, $role === 'hybrid' ? 'teacher' : $role, $is_first_login]);
        
        $user_id = $pdo->lastInsertId();
        
        // Add permissions for hybrid role
        if ($role === 'hybrid') {
            $permissions = ['nav_dashboard', 'nav_courses', 'nav_content', 'nav_audit', 'nav_settings', 'nav_users', 'nav_reports'];
            $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_name) VALUES (?, ?)");
            foreach ($permissions as $permission) {
                $stmt->execute([$user_id, $permission]);
            }
        }
        
        // Log admin action
        $stmt = $pdo->prepare("
            INSERT INTO admin_action_logs (admin_id, user_id, action, details)
            VALUES (?, ?, 'create_user', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $user_id,
            json_encode(['role' => $role, 'username' => $username])
        ]);
        
        $pdo->commit();
        
        // Send welcome email with temporary password
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . bin2hex(random_bytes(32));
        sendPasswordResetEmail($email, $username, $reset_link);
        
        $_SESSION['success'] = "User created successfully. A welcome email has been sent.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error creating user: " . $e->getMessage();
    }
    
    header("Location: users.php");
    exit();
}

// If not POST request, redirect to users page
header("Location: users.php");
exit(); 