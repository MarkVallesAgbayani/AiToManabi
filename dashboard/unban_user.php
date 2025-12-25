<?php
// Suppress all output except JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to catch any unexpected output
ob_start();

// Set content type to JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Function to send clean JSON response
function sendJsonResponse($data) {
    // Clear any buffered output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh buffer
    ob_start();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit();
}

// Function to log errors
function logError($message) {
    error_log("[UNBAN_USER] " . $message);
}

// Include required files
require_once '../config/database.php';
require_once '../config/app_config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    // Check if user is logged in and has admin privileges
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized access']);
    }

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(['success' => false, 'message' => 'Invalid request method']);
    }

    // Get and validate input data
    $user_id = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : 0;

    if (!$user_id || $user_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid user ID']);
    }

    // Check database connection
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        logError("Database connection not available");
        sendJsonResponse(['success' => false, 'message' => 'Database connection error']);
    }

    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Get user information
        $stmt = $pdo->prepare("SELECT id, username, email, role, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'User not found']);
        }

        // Check if user is actually banned
        if ($user['status'] !== 'banned') {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'User is not banned']);
        }

        // Update user status to active and add unban information
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'active', 
                unbanned_at = NOW(), 
                unbanned_by = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        
        $updateSuccess = $stmt->execute([$_SESSION['user_id'], $user_id]);
        
        if (!$updateSuccess) {
            throw new Exception("Failed to update user status");
        }

        // Log the unban action (only if table exists)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO admin_action_logs (admin_id, user_id, action, details, created_at) 
                VALUES (?, ?, 'unban_user', ?, NOW())
            ");
            $log_details = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'admin_username' => $_SESSION['username'] ?? 'Unknown'
            ], JSON_UNESCAPED_UNICODE);
            $stmt->execute([$_SESSION['user_id'], $user_id, $log_details]);
        } catch (Exception $logError) {
            // Don't fail the entire operation if logging fails
            logError("Failed to log admin action: " . $logError->getMessage());
        }

        // Commit the transaction
        $pdo->commit();

        // Send email notification (don't fail if email fails)
        $email_sent = false;
        try {
            if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                $email_sent = sendUnbanNotificationEmail($user['email'], $user['username']);
            }
        } catch (Exception $email_error) {
            logError("Email sending failed: " . $email_error->getMessage());
        }

        // Prepare success response
        $response = [
            'success' => true,
            'message' => 'User has been unbanned successfully',
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'email_sent' => $email_sent,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Send success response
        sendJsonResponse($response);

    } catch (Exception $dbError) {
        $pdo->rollBack();
        logError("Database transaction failed: " . $dbError->getMessage());
        sendJsonResponse(['success' => false, 'message' => 'Failed to unban user due to database error']);
    }

} catch (Exception $e) {
    logError("General error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred while processing the request']);
}

// Email function for unban notification
function sendUnbanNotificationEmail($to_email, $username) {
    // Check if required email constants are defined
    $required_constants = ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_PORT', 'FROM_EMAIL', 'FROM_NAME'];
    foreach ($required_constants as $constant) {
        if (!defined($constant) || empty(constant($constant))) {
            logError("Email configuration missing: $constant");
            return false;
        }
    }

    try {
        $mailer = new PHPMailer(true);

        // Server settings
        $mailer->isSMTP();
        $mailer->Host = SMTP_HOST;
        $mailer->SMTPAuth = true;
        $mailer->Username = SMTP_USERNAME;
        $mailer->Password = SMTP_PASSWORD;
        $mailer->Port = SMTP_PORT;
        
        // Handle SMTP security
        if (defined('SMTP_SECURE')) {
            $mailer->SMTPSecure = SMTP_SECURE;
        } else {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Relaxed SSL options for compatibility
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        // Recipients
        $mailer->setFrom(FROM_EMAIL, FROM_NAME);
        $mailer->addAddress($to_email, $username);

        // Content
        $mailer->isHTML(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->Subject = 'Account Access Restored - AiToManabi LMS';
            
        // Safe HTML escaping
        $safe_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $current_date = date('F j, Y \a\t g:i A');

        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Account Access Restored</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                
                <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>✅ Account Access Restored</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Your account has been unbanned</p>
                </div>
                
                <div style='padding: 30px;'>
                    <p style='color: #333; margin-bottom: 20px; line-height: 1.6;'>
                        Hello <strong>{$safe_username}</strong>,
                    </p>
                    <p style='color: #333; margin-bottom: 25px; line-height: 1.6;'>
                        Great news! Your account access has been restored. You can now log in to your account normally.
                    </p>
                    
                    <div style='background: #f0fdf4; border: 2px solid #10b981; padding: 20px; margin: 25px 0; text-align: center; border-radius: 8px;'>
                        <p style='margin: 0 0 15px 0; color: #10b981; font-size: 16px; font-weight: bold;'>Account Status: Active</p>
                        <div style='background: white; padding: 15px; border: 2px dashed #10b981; border-radius: 5px; display: inline-block;'>
                            <span style='font-size: 18px; font-weight: bold; color: #10b981;'>✅ You can now log in</span>
                        </div>
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                        <p style='margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: bold;'>What you can do now:</p>
                        <ul style='margin: 0; padding-left: 20px; color: #333; font-size: 14px; line-height: 1.6;'>
                            <li>Log in to your account with your existing credentials</li>
                            <li>Access all your courses and materials</li>
                            <li>Continue your learning journey</li>
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
                            Access restored on: {$current_date} (Philippine Time)
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
            
        $mailer->Body = $body;
        
        return $mailer->send();

    } catch (Exception $e) {
        logError("Email sending failed: " . $e->getMessage());
        return false;
    }
}
?>