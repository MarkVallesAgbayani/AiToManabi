<?php
if (session_status() == PHP_SESSION_NONE) {
session_start();
}
require_once '../config/database.php';
require_once '../config/app_config.php';
require_once '../vendor/autoload.php';
require_once 'audit_logger.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email function for password reset using the same system as the rest of the app
if (!function_exists('sendAdminPasswordResetEmail')) {
    function sendAdminPasswordResetEmail($to_email, $username, $temp_password) {
        $mailer = new PHPMailer(true);
        
        try {
            // Use the same SMTP configuration as the rest of the app
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
            $mailer->Port = SMTP_PORT;
            $mailer->SMTPSecure = SMTP_SECURE;
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];
            
            // Set sender
            $mailer->setFrom(FROM_EMAIL, FROM_NAME);
            $mailer->addAddress($to_email, $username);
            
            // Set charset and encoding
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            
            // Email content
            $mailer->isHTML(true);
            $mailer->Subject = 'Password Reset Complete - AiToManabi LMS';
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>🔐 Password Reset Complete</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Your account password has been reset</p>
                </div>
                
                <div style='background: white; padding: 30px; border: 1px solid #e1e5e9; border-radius: 0 0 10px 10px;'>
                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                        Hello <strong>{$username}</strong>,
                    </p>
                    <p style='color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                        Your password has been reset by an administrator. Please use the temporary password below to log in:
                    </p>
                    
                    <div style='background: #e3f2fd; border: 2px solid #dc2626; padding: 20px; margin: 25px 0; text-align: center; border-radius: 8px;'>
                        <p style='margin: 0 0 15px 0; color: #dc2626; font-size: 16px; font-weight: bold;'>Your Temporary Password:</p>
                        <div style='background: white; padding: 15px; border: 2px dashed #b91c1c; border-radius: 5px; display: inline-block;'>
                            <span style='font-size: 24px; font-weight: bold; color: #b91c1c; font-family: monospace; letter-spacing: 2px;'>{$temp_password}</span>
                        </div>
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                        <p style='margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: bold;'>Important Instructions:</p>
                        <ul style='margin: 0; padding-left: 20px; color: #333; font-size: 14px; line-height: 1.6;'>
                            <li>Use this temporary password to log in to your account</li>
                            <li>You will be prompted to create a new password on first login</li>
                            <li>This password is case-sensitive</li>
                            <li>If you have any issues, contact your administrator</li>
                        </ul>
                    </div>
                    
                    <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                        <p style='color: #666; font-size: 14px; margin: 0 0 10px 0;'>
                            Best regards,<br><strong>AiToManabi LMS Team</strong>
                        </p>
                        <p style='color: #666; font-size: 12px; margin: 0;'>
                            This is an automated message. Please do not reply to this email.
                        </p>
                        <p style='color: #666; font-size: 12px; margin: 5px 0 0 0;'>
                            If you did not request this password reset, please contact your administrator immediately.
                        </p>
                    </div>
                </div>
            </div>";
            
            $mailer->Body = $body;
            
            return $mailer->send();
            
        } catch (Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            return false;
        }
    }
}

// Function to generate a unique temporary password that hasn't been used before
function generateUniqueTemporaryPassword($pdo, $user_id) {
    $max_attempts = 100; // Prevent infinite loops
    $attempts = 0;
    
    do {
        // Generate a secure temporary password (8 characters, easy to type)
        $temp_password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
        
        // Check if this password has been used before for this user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_history WHERE user_id = ? AND password_hash = ?");
        $stmt->execute([$user_id, password_hash($temp_password, PASSWORD_DEFAULT)]);
        $count = $stmt->fetchColumn();
        
        $attempts++;
        
        // If password is unique or we've tried too many times, use it
        if ($count == 0 || $attempts >= $max_attempts) {
            break;
        }
    } while ($attempts < $max_attempts);
    
    // If we couldn't generate a unique password after max attempts, add timestamp to make it unique
    if ($attempts >= $max_attempts) {
        $temp_password = substr($temp_password, 0, 6) . substr(str_pad((string)(time() % 100), 2, '0', STR_PAD_LEFT), -2);
    }
    
    return $temp_password;
}

// Set JSON header for AJAX responses
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, just log them

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get user info
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        // Generate a secure temporary password that hasn't been used before
        $temp_password = generateUniqueTemporaryPassword($pdo, $user_id);
        
        // Update user password - check if columns exist first
        $update_sql = "UPDATE users SET password = ?";
        $params = [password_hash($temp_password, PASSWORD_DEFAULT)];
        
        // Check if is_first_login column exists
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_first_login'")->fetch();
        if ($columns) {
            $update_sql .= ", is_first_login = TRUE";
        }
        
        // Check if status column exists
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch();
        if ($columns) {
            $update_sql .= ", status = 'active'";
        }
        
        // Check if login_attempts column exists
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'login_attempts'")->fetch();
        if ($columns) {
            $update_sql .= ", login_attempts = 0";
        }
        
        // Check if updated_at column exists
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'updated_at'")->fetch();
        if ($columns) {
            $update_sql .= ", updated_at = NOW()";
        }
        
        // IMPORTANT: Set password_reset_notification_shown = FALSE to trigger notification
        // This tells the system that an admin has reset the password and user needs to be notified
        // The notification will show until user changes password or dismisses it
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_notification_shown'")->fetch();
        if ($columns) {
            $update_sql .= ", password_reset_notification_shown = FALSE";
        }
        
        $update_sql .= " WHERE id = ?";
        $params[] = $user_id;
        
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute($params);
        
        // Store the generated password in password_history table
        $stmt = $pdo->prepare("INSERT INTO password_history (user_id, password_hash, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, password_hash($temp_password, PASSWORD_DEFAULT)]);
        
        // Log audit entry
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->logEntry([
            'action_type' => 'UPDATE',
            'action_description' => 'Quick reset password for user: ' . $user['username'] . ' (password stored in history)',
            'resource_type' => 'User Account',
            'resource_id' => 'User ID: ' . $user_id,
            'resource_name' => $user['username'],
            'outcome' => 'Success',
            'old_value' => 'Password: [encrypted]',
            'new_value' => 'Password: [reset to temporary]',
            'context' => [
                'user_id' => $user_id,
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'reset_by' => $_SESSION['user_id'],
                'is_first_login' => true
            ]
        ]);
        
        // Log the action - check if table exists first
        $tables = $pdo->query("SHOW TABLES LIKE 'admin_action_logs'")->fetch();
        if ($tables) {
            try {
        $stmt = $pdo->prepare("
                    INSERT INTO admin_action_logs (admin_id, user_id, action, details, created_at) 
                    VALUES (?, ?, 'password_reset', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            $user_id, 
                    'Admin quick reset password for ' . $user['role'] . ' account: ' . $user['username'] . ' (unique password generated)'
        ]);
            } catch (Exception $log_error) {
                // Log error but don't fail the password reset
                error_log("Failed to log admin action: " . $log_error->getMessage());
            }
        }
        
        // Send email with temporary password
        $email_sent = false;
        try {
            $email_sent = sendAdminPasswordResetEmail($user['email'], $user['username'], $temp_password);
        } catch (Exception $email_error) {
            error_log("Email sending failed: " . $email_error->getMessage());
        }
        
        $pdo->commit();
        
        // Return success response with user details
        echo json_encode([
            'success' => true, 
            'message' => 'Password reset successfully! Unique temporary password generated.',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'temp_password' => $temp_password,
            'email_sent' => $email_sent,
            'timestamp' => date('Y-m-d H:i:s', time() + (8 * 3600)) // Philippine time
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Password reset error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'message' => 'Error resetting password: ' . $e->getMessage(),
            'debug' => [
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit(); 