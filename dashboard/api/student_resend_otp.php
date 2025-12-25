<?php
/**
 * Student Resend OTP API
 * Handles resending OTP codes for various operations for students
 */

// Disable error display to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $type = $input['type'] ?? 'password_change';
    
    // Get user email
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Generate new OTP
    $otp = generateSecureOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $verificationToken = bin2hex(random_bytes(32));
    
    // Insert OTP into database
    $otpStmt = $pdo->prepare("
        INSERT INTO otps (user_id, email, otp_code, type, expires_at, verification_token, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $otpStmt->execute([$_SESSION['user_id'], $user['email'], $otp, $type, $expiresAt, $verificationToken]);
    
    // Send OTP email using PHPMailer directly
    try {
        require_once '../../vendor/autoload.php';
        require_once '../../config/app_config.php';
        
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = SMTP_HOST;
        $mailer->SMTPAuth = true;
        $mailer->Username = SMTP_USERNAME;
        $mailer->Password = SMTP_PASSWORD;
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = SMTP_PORT;
        $mailer->setFrom(FROM_EMAIL, FROM_NAME);
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';
        
        $mailer->addAddress($user['email']);
        $mailer->isHTML(true);
        $mailer->Subject = "Password Change Verification - Japanese Learning Platform";
        $mailer->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #2563eb; text-align: center;'>Password Change Verification</h2>
                    <p>Hello,</p>
                    <p>You have requested to change your password. Please use the following verification code to complete the process:</p>
                    <div style='background-color: #f3f4f6; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                        <h1 style='color: #1f2937; font-size: 32px; letter-spacing: 4px; margin: 0;'>{$otp}</h1>
                    </div>
                    <p><strong>This code will expire in 5 minutes.</strong></p>
                    <p>If you did not request this password change, please ignore this email and contact support immediately.</p>
                    <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #6b7280; text-align: center;'>
                        This is an automated message from Japanese Learning Platform. Please do not reply to this email.
                    </p>
                </div>
            </body>
            </html>
        ";
        
        $email_sent = $mailer->send();
        
        if (!$email_sent) {
            throw new Exception('Failed to send OTP email: ' . $mailer->ErrorInfo);
        }
        
    } catch (Exception $mailError) {
        throw new Exception('Failed to send OTP email: ' . $mailError->getMessage());
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'OTP code resent successfully'
    ]);
    
} catch (Exception $e) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generateSecureOTP() {
    // Use cryptographically secure random
    $bytes = random_bytes(3); // 3 bytes = 24 bits, enough for 6-digit OTP
    $number = unpack('N', "\x00" . $bytes)[1];
    return str_pad($number % 1000000, 6, '0', STR_PAD_LEFT);
}
?>
