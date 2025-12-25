<?php
session_start();
require_once '../config/database.php';
require_once '../config/app_config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Function to log errors
function logError($message) {
    error_log("[MODULE_COMPLETION_EMAIL] " . $message);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['course_id'])) {
        throw new Exception('Course ID is required');
    }
    
    $course_id = (int)$input['course_id'];
    $user_id = $_SESSION['user_id'];
    
    // Get course title and user info
    $stmt = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        throw new Exception('Course not found');
    }
    
    $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Send module completion email
    $result = sendModuleCompletionEmail($user['email'], $user['username'], $course['title']);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Module completion email sent successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send module completion email'
        ]);
    }
    
} catch (Exception $e) {
    logError("Module completion email error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}

// Email function for module completion notification
function sendModuleCompletionEmail($to_email, $username, $course_title) {
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
        $mailer->Subject = 'Congratulations! You\'ve Completed a Module';
        
        // Set timezone to Philippines for timestamp
        date_default_timezone_set('Asia/Manila');
        $completion_date = date('F j, Y \a\t g:i A');
        
        // Safe HTML escaping
        $safe_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safe_course_title = htmlspecialchars($course_title, ENT_QUOTES, 'UTF-8');

        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Module Completed</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                
                <div style='background: linear-gradient(135deg, #2c5aa0 0%, #1e40af 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>🎉 Congratulations!</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Module Completed Successfully</p>
                </div>
                
                <div style='padding: 30px;'>
                    <p style='color: #333; margin-bottom: 20px; line-height: 1.6;'>
                        Dear <strong>{$safe_username}</strong>,
                    </p>
                    <p style='color: #333; margin-bottom: 25px; line-height: 1.6;'>
                        Congratulations! You have successfully completed the module:
                    </p>
                    
                    <div style='background: #f0f9ff; border-left: 4px solid #2c5aa0; padding: 20px; margin: 25px 0; border-radius: 5px;'>
                        <h3 style='margin: 0 0 10px 0; color: #2c5aa0; font-size: 18px; font-weight: bold;'>\"{$safe_course_title}\"</h3>
                        <p style='margin: 0; color: #1e40af; font-size: 14px;'>Completed on: {$completion_date} (Philippine Time)</p>
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; text-align: center;'>
                        <p style='margin: 0 0 20px 0; color: #333; font-size: 16px;'>Your dedication to learning is commendable! Keep up the excellent work.</p>
                        <div style='background: #2c5aa0; color: white; padding: 12px 24px; border-radius: 6px; display: inline-block; font-weight: bold;'>
                            Continue Learning
                        </div>
                    </div>
                    
                    <div style='border-top: 1px solid #e1e5e9; padding-top: 20px; margin-top: 25px;'>
                        <p style='color: #666; font-size: 14px; margin: 0 0 10px 0;'>
                            Best regards,<br><strong>AiToManabi LMS Team</strong>
                        </p>
                        <p style='color: #666; font-size: 12px; margin: 0;'>
                            This is an automated notification from Japanese LMS.
                        </p>
                        <p style='color: #666; font-size: 12px; margin: 5px 0 0 0;'>
                            Keep up the great work in your learning journey!
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
