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
    error_log("[BAN_USER] " . $message);
}

// Include required files
require_once '../config/database.php';
require_once '../config/app_config.php';
require_once '../vendor/autoload.php';
require_once 'audit_logger.php';

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
    $ban_reason = isset($_POST['ban_reason']) ? trim($_POST['ban_reason']) : '';

    if (!$user_id || $user_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid user ID']);
    }

    if (empty($ban_reason)) {
        sendJsonResponse(['success' => false, 'message' => 'Ban reason is required']);
    }

    if (strlen($ban_reason) > 500) {
        sendJsonResponse(['success' => false, 'message' => 'Ban reason must be 500 characters or less']);
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

        // Check if user is already banned
        if ($user['status'] === 'banned') {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'User is already banned']);
        }

        // Check if user is trying to ban themselves
        if ($user_id == $_SESSION['user_id']) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'You cannot ban yourself']);
        }

        // Update user status to banned and add ban reason
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'banned', 
                ban_reason = ?, 
                banned_at = NOW(), 
                banned_by = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        
        $updateSuccess = $stmt->execute([$ban_reason, $_SESSION['user_id'], $user_id]);
        
        if (!$updateSuccess) {
            throw new Exception("Failed to update user status");
        }
        
        // NEW: Force logout the banned user by invalidating their sessions
        require_once __DIR__ . '/../includes/session_validator.php';
        $sessionValidator = new SessionValidator($pdo);
        $sessionValidator->invalidateUserSessions($user_id, 'banned', $_SESSION['user_id']);

        // Log audit entry
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->logEntry([
            'action_type' => 'UPDATE',
            'action_description' => 'Banned user: ' . $user['username'],
            'resource_type' => 'User Account',
            'resource_id' => 'User ID: ' . $user_id,
            'resource_name' => $user['username'],
            'outcome' => 'Success',
            'old_value' => 'Status: active',
            'new_value' => 'Status: banned, Reason: ' . $ban_reason,
            'context' => [
                'user_id' => $user_id,
                'username' => $user['username'],
                'ban_reason' => $ban_reason,
                'banned_by' => $_SESSION['user_id']
            ]
        ]);

        // Log the ban action (only if table exists)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO admin_action_logs (admin_id, user_id, action, details, created_at) 
                VALUES (?, ?, 'ban_user', ?, NOW())
            ");
            $log_details = json_encode([
                'reason' => $ban_reason,
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
                $email_sent = sendBanNotificationEmail($user['email'], $user['username'], $ban_reason);
            }
        } catch (Exception $email_error) {
            logError("Email sending failed: " . $email_error->getMessage());
        }

        // Prepare success response
        $response = [
            'success' => true,
            'message' => 'User has been banned successfully',
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'ban_reason' => $ban_reason,
            'email_sent' => $email_sent,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Send success response
        sendJsonResponse($response);

    } catch (Exception $dbError) {
        $pdo->rollBack();
        logError("Database transaction failed: " . $dbError->getMessage());
        sendJsonResponse(['success' => false, 'message' => 'Failed to ban user due to database error']);
    }

} catch (Exception $e) {
    logError("General error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred while processing the request']);
}

// Email function for ban notification
function sendBanNotificationEmail($to_email, $username, $ban_reason) {
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
        $mailer->Subject = 'Account Access Restricted - AiToManabi LMS';
        
        // Safe HTML escaping
        $safe_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safe_ban_reason = htmlspecialchars($ban_reason, ENT_QUOTES, 'UTF-8');
        $current_date = date('F j, Y \a\t g:i A');

        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Account Restricted</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                
                <div style='background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>🚫 Account Access Restricted</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Your account has been temporarily restricted</p>
                </div>
                
                <div style='padding: 30px;'>
                    <p style='color: #333; margin-bottom: 20px; line-height: 1.6;'>
                        Hello <strong>{$safe_username}</strong>,
                    </p>
                    <p style='color: #333; margin-bottom: 25px; line-height: 1.6;'>
                        Your account access has been temporarily restricted for the following reason:
                    </p>
                    
                    <div style='background: #fef2f2; border-left: 4px solid #dc2626; padding: 20px; margin: 25px 0; border-radius: 5px;'>
                        <h4 style='margin: 0 0 15px 0; color: #dc2626; font-size: 16px; font-weight: bold;'>Reason for Restriction:</h4>
                        <p style='margin: 0; color: #7f1d1d; font-size: 14px; line-height: 1.6; font-style: italic;'>
                            \"{$safe_ban_reason}\"
                        </p>
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                        <p style='margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: bold;'>What happens next?</p>
                        <ul style='margin: 0; padding-left: 20px; color: #333; font-size: 14px; line-height: 1.6;'>
                            <li>You will not be able to log in to your account until the restriction is lifted</li>
                            <li>All your course progress and data remain safe and will be restored when access is reinstated</li>
                            <li>If you believe this action was taken in error, please contact our support team</li>
                        </ul>
                    </div>
                    
                    <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                        <p style='margin: 0 0 15px 0; color:rgb(199, 2, 2); font-size: 16px; font-weight: bold;'>Need Help?</p>
                        <p style='margin: 0; color: #333; font-size: 14px; line-height: 1.6;'>
                            If you have questions about this restriction or need to appeal this decision, please contact our support team at 
                            <a href='mailto:aitosensei@aitomanabi.com' style='color: #dc2626; text-decoration: none;'>aitosensei@aitomanabi.com</a>
                        </p>
                    </div>
                    
                    <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                        <p style='color: #666; font-size: 14px; margin: 0 0 10px 0;'>
                            Best regards,<br><strong>AiToManabi LMS Team</strong>
                        </p>
                        <p style='color: #666; font-size: 12px; margin: 0;'>
                            This is an automated message. Please do not reply to this email.
                        </p>
                        <p style='color: #666; font-size: 12px; margin: 5px 0 0 0;'>
                            Restriction applied on: {$current_date} (Philippine Time)
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