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
    error_log("[RESTORE_USER] " . $message);
}

// Include required files
require_once '../config/database.php';
require_once '../config/app_config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(['success' => false, 'message' => 'Invalid request method']);
    }

    // Get and validate input data
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($user_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid user ID']);
    }

    // Check if user exists and is deleted
    $stmt = $pdo->prepare("
        SELECT id, username, email, role, status, deleted_at, restoration_deadline
        FROM users 
        WHERE id = ? AND deleted_at IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJsonResponse(['success' => false, 'message' => 'User not found or not deleted']);
    }

    // Check if restoration deadline has passed
    if ($user['restoration_deadline'] && strtotime($user['restoration_deadline']) < time()) {
        sendJsonResponse(['success' => false, 'message' => 'Restoration deadline has passed. User cannot be restored.']);
    }

    // Start database transaction
    $pdo->beginTransaction();

    try {
        // Restore the user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET deleted_at = NULL, 
                deleted_by = NULL, 
                deletion_reason = NULL, 
                restoration_deadline = NULL,
                status = 'active'
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);

        // Log the restoration action
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_action_logs (admin_id, user_id, action, details, created_at) 
                    VALUES (?, ?, 'user_restored', ?, NOW())
                ");
                $log_details = json_encode([
                    'restored_user' => $user['username'],
                    'restored_email' => $user['email'],
                    'restoration_timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE);
                $stmt->execute([$_SESSION['user_id'], $user_id, $log_details]);
            } catch (Exception $logError) {
                // Don't fail if logging fails
                logError("Failed to log restoration: " . $logError->getMessage());
            }
        }

        // Send notification email to the restored user
        $email_sent = false;
        try {
            $email_sent = sendRestorationNotificationEmail($user);
        } catch (Exception $emailError) {
            logError("Failed to send restoration email: " . $emailError->getMessage());
        }

        // Commit transaction
        $pdo->commit();

        sendJsonResponse([
            'success' => true,
            'message' => 'User restored successfully',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'email_sent' => $email_sent,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logError("General error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred while processing the restoration request']);
}

// Email function for sending restoration notifications
function sendRestorationNotificationEmail($user) {
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
        $mailer->addAddress($user['email'], $user['username']);

        // Content
        $mailer->isHTML(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->Subject = 'Account Restored - AiToManabi LMS';
        
        // Safe HTML escaping
        $safe_username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
        $current_date = date('F j, Y \a\t g:i A');

        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Account Restored</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                
                <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>✅ Account Restored</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Your account has been successfully restored</p>
                </div>
                
                <div style='padding: 30px;'>
                    <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                        <h3 style='margin: 0 0 15px 0; color: #059669; font-size: 18px;'>Account Status</h3>
                        <p style='margin: 0 0 10px 0; color: #374151;'><strong>Username:</strong> {$safe_username}</p>
                        <p style='margin: 0 0 10px 0; color: #374151;'><strong>Status:</strong> Active</p>
                        <p style='margin: 0 0 15px 0; color: #374151;'><strong>Restored:</strong> {$current_date} (Philippine Time)</p>
                    </div>
                    
                    <div style='background: #dbeafe; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                        <h4 style='margin: 0 0 15px 0; color: #1d4ed8; font-size: 16px;'>What This Means:</h4>
                        <ul style='margin: 0; padding-left: 20px; color: #374151; font-size: 14px; line-height: 1.6;'>
                            <li>Your account is now active and accessible</li>
                            <li>You can log in with your existing credentials</li>
                            <li>All your data and settings have been preserved</li>
                            <li>You have full access to all your previous features</li>
                        </ul>
                    </div>
                    
                    <div style='background: #fef3c7; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                        <h4 style='margin: 0 0 15px 0; color: #d97706; font-size: 16px;'>Next Steps:</h4>
                        <div style='background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #d97706;'>
                            <p style='margin: 0; color: #374151; line-height: 1.6;'>
                                You can now log in to your account using your existing username and password. 
                                If you have any issues accessing your account, please contact our support team.
                            </p>
                        </div>
                    </div>
                    
                    <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                        <p style='color: #6b7280; font-size: 14px; margin: 0 0 10px 0;'>
                            Welcome back to AiToManabi LMS! We're glad to have you back.
                        </p>
                        <p style='color: #6b7280; font-size: 12px; margin: 0;'>
                            This is an automated notification from the AiToManabi LMS system.
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