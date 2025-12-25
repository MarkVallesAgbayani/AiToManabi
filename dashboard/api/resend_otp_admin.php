<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $userId = $_SESSION['user_id'];
    
    if (empty($type)) {
        echo json_encode(['success' => false, 'error' => 'Missing type']);
        exit;
    }
    
    // Get user email
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Generate new OTP
    $otp = generateSecureOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $verificationToken = bin2hex(random_bytes(32));
    
    // Delete old OTPs
    $deleteStmt = $pdo->prepare("DELETE FROM otps WHERE user_id = ? AND type = ?");
    $deleteStmt->execute([$userId, $type]);
    
    // Insert new OTP
    $otpStmt = $pdo->prepare("
        INSERT INTO otps (user_id, email, otp_code, type, expires_at, verification_token, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $otpStmt->execute([$userId, $user['email'], $otp, $type, $expiresAt, $verificationToken]);
    
    // Send OTP email
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../config/app_config.php';
    
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
                <p>Here is your new verification code:</p>
                <div style='background-color: #f3f4f6; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                    <h1 style='color: #1f2937; font-size: 32px; letter-spacing: 4px; margin: 0;'>{$otp}</h1>
                </div>
                <p><strong>This code will expire in 5 minutes.</strong></p>
            </div>
        </body>
        </html>
    ";
    
    $mailer->send();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Verification code resent successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Resend OTP error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to resend verification code']);
}

function generateSecureOTP() {
    $bytes = random_bytes(3);
    $number = unpack('N', "\x00" . $bytes)[1];
    return str_pad($number % 1000000, 6, '0', STR_PAD_LEFT);
}
?>
